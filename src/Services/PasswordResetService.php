<?php

namespace App\Services;

use PDO;
use Exception;

class PasswordResetService
{
    private PDO $pdo;
    private EmailService $emailService;

    public function __construct(PDO $pdo, ?EmailService $emailService = null)
    {
        $this->pdo = $pdo;
        $this->emailService = $emailService ?? new EmailService();
    }

    /**
     * Recherche un utilisateur par email
     */
    public function findUserByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, nom, email FROM utilisateurs WHERE email = ? LIMIT 1");
        $stmt->execute([trim($email)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Génère un code de vérification à 6 chiffres, l'enregistre et l'envoie par email.
     * Validité : 15 minutes.
     */
    public function createResetCode(string $email): ?string
    {
        $email = trim(strtolower($email));
        $user = $this->findUserByEmail($email);

        if (!$user) {
            return null;
        }

        // Purger les anciens codes pour cet email
        $del = $this->pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $del->execute([$email]);

        // Code numérique à 6 chiffres aléatoire et sécurisé
        $code = (string)random_int(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', time() + (15 * 60)); // 15 minutes

        // Insertion
        $ins = $this->pdo->prepare("INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
        $ins->execute([$email, $code, $expiresAt]);

        // Envoi de l'email
        $this->emailService->sendPasswordResetCode($user['email'], $user['nom'], $code);

        return $code;
    }

    /**
     * Vérifie si un code est valide et non expiré pour un email donné.
     */
    public function verifyCode(string $email, string $code): bool
    {
        $email = trim(strtolower($email));
        $code = preg_replace('/\D/', '', $code); // chiffres uniquement

        if (strlen($code) !== 6) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("SELECT id FROM password_resets WHERE email = ? AND code = ? AND expires_at > ? LIMIT 1");
        $stmt->execute([$email, $code, $now]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * Réinitialise le mot de passe de l'utilisateur après validation du code.
     */
    public function resetPassword(string $email, string $code, string $newPassword): bool
    {
        $email = trim(strtolower($email));
        
        if (!$this->verifyCode($email, $code)) {
            throw new Exception("Le code de vérification est invalide ou a expiré.");
        }

        if (strlen($newPassword) < 6) {
            throw new Exception("Le mot de passe doit comporter au moins 6 caractères.");
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $upd = $this->pdo->prepare("UPDATE utilisateurs SET password = ? WHERE email = ?");
        $upd->execute([$hash, $email]);

        // Supprimer le code utilisé
        $del = $this->pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $del->execute([$email]);

        return true;
    }
}
