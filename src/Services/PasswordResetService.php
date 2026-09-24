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
     * Nettoie et normalise un numéro de téléphone
     */
    public static function normalizePhone(string $rawPhone): array
    {
        $digits = preg_replace('/\D/', '', $rawPhone);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 9 && in_array(substr($digits, 0, 1), ['7', '3'])) {
            $national = $digits;
            $international = '221' . $digits;
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '221')) {
            $national = substr($digits, 3);
            $international = $digits;
        } else {
            $national = strlen($digits) >= 9 ? substr($digits, -9) : $digits;
            $international = $digits;
        }

        return [
            'raw' => trim($rawPhone),
            'digits' => $digits,
            'national' => $national,
            'international' => $international,
        ];
    }

    /**
     * Recherche un utilisateur par numéro de téléphone
     */
    public function findUserByPhone(string $phone): ?array
    {
        $norm = self::normalizePhone($phone);
        $national = $norm['national'];
        $digits = $norm['digits'];

        if (empty($digits)) {
            return null;
        }

        $cleanSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telephone, ' ', ''), '-', ''), '+', ''), '.', ''), '/', '')";

        $stmt = $this->pdo->prepare("
            SELECT id, nom, prenom, email, telephone, role 
            FROM utilisateurs 
            WHERE {$cleanSql} = ? 
               OR {$cleanSql} = ?
               OR {$cleanSql} LIKE ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$digits, $national, '%' . $national]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Recherche un utilisateur par email
     */
    public function findUserByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, nom, prenom, email, telephone FROM utilisateurs WHERE email = ? LIMIT 1");
        $stmt->execute([trim($email)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Génère un code de réinitialisation pour un numéro WhatsApp, l'enregistre et prépare le lien WhatsApp.
     * Validité : 15 minutes.
     */
    public function createResetCodeForPhone(string $phone): ?array
    {
        $user = $this->findUserByPhone($phone);
        if (!$user) {
            return null;
        }

        $norm = self::normalizePhone($user['telephone'] ?: $phone);
        $cleanPhone = $norm['national'];
        $waPhone = $norm['international'];

        // Purger les anciens codes pour ce téléphone ou cet email
        $del = $this->pdo->prepare("
            DELETE FROM password_resets 
            WHERE telephone = ? 
               OR telephone = ? 
               OR (email = ? AND email IS NOT NULL AND email != '')
        ");
        $del->execute([$cleanPhone, $user['telephone'], $user['email'] ?? '']);

        // Code numérique à 6 chiffres aléatoire
        $code = (string)random_int(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', time() + (15 * 60)); // 15 minutes

        // Insertion dans password_resets
        $ins = $this->pdo->prepare("
            INSERT INTO password_resets (telephone, email, code, expires_at) 
            VALUES (?, ?, ?, ?)
        ");
        $ins->execute([$cleanPhone, $user['email'] ?? null, $code, $expiresAt]);

        // Message WhatsApp
        $userName = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
        $greeting = !empty($userName) ? "Assalamou aleykoum {$userName}" : "Assalamou aleykoum";

        $message = "{$greeting},\n\n"
                 . "Votre code de réinitialisation Dahira AKR est :\n"
                 . "*{$code}*\n\n"
                 . "Ce code est valable pendant 15 minutes. Utilisez-le pour définir votre nouveau mot de passe.";

        $waUrl = "https://api.whatsapp.com/send?phone=" . $waPhone . "&text=" . urlencode($message);

        return [
            'user' => $user,
            'code' => $code,
            'phone' => $user['telephone'] ?: $phone,
            'clean_phone' => $cleanPhone,
            'wa_phone' => $waPhone,
            'wa_url' => $waUrl,
            'message' => $message,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Vérifie si un code est valide et non expiré pour un téléphone donné.
     */
    public function verifyPhoneCode(string $phone, string $code): bool
    {
        $norm = self::normalizePhone($phone);
        $cleanCode = preg_replace('/\D/', '', $code);

        if (strlen($cleanCode) !== 6) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $cleanSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telephone, ' ', ''), '-', ''), '+', ''), '.', ''), '/', '')";

        $stmt = $this->pdo->prepare("
            SELECT id FROM password_resets 
            WHERE ({$cleanSql} = ? OR {$cleanSql} = ? OR {$cleanSql} LIKE ? OR telephone = ?)
              AND code = ? 
              AND expires_at > ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([
            $norm['digits'], 
            $norm['national'], 
            '%' . $norm['national'], 
            $phone, 
            $cleanCode, 
            $now
        ]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * Réinitialise le mot de passe de l'utilisateur après validation du code par téléphone.
     */
    public function resetPasswordByPhone(string $phone, string $code, string $newPassword): bool
    {
        if (!$this->verifyPhoneCode($phone, $code)) {
            throw new Exception("Le code de vérification est invalide ou a expiré.");
        }

        if (strlen($newPassword) < 6) {
            throw new Exception("Le mot de passe doit comporter au moins 6 caractères.");
        }

        $user = $this->findUserByPhone($phone);
        if (!$user) {
            throw new Exception("Utilisateur introuvable.");
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $upd = $this->pdo->prepare("UPDATE utilisateurs SET password = ? WHERE id = ?");
        $upd->execute([$hash, $user['id']]);

        // Supprimer le code utilisé
        $norm = self::normalizePhone($phone);
        $cleanSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telephone, ' ', ''), '-', ''), '+', ''), '.', ''), '/', '')";
        $del = $this->pdo->prepare("
            DELETE FROM password_resets 
            WHERE {$cleanSql} = ? 
               OR {$cleanSql} = ? 
               OR {$cleanSql} LIKE ? 
               OR telephone = ? 
               OR (email = ? AND email IS NOT NULL AND email != '')
        ");
        $del->execute([
            $norm['digits'], 
            $norm['national'], 
            '%' . $norm['national'], 
            $phone, 
            $user['email'] ?? ''
        ]);

        return true;
    }

    /**
     * Méthodes de réinitialisation par email conservées pour compatibilité
     */
    public function createResetCode(string $email): ?string
    {
        $email = trim(strtolower($email));
        $user = $this->findUserByEmail($email);

        if (!$user) {
            return null;
        }

        $del = $this->pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $del->execute([$email]);

        $code = (string)random_int(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', time() + (15 * 60));

        $ins = $this->pdo->prepare("INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
        $ins->execute([$email, $code, $expiresAt]);

        $this->emailService->sendPasswordResetCode($user['email'], $user['nom'], $code);

        return $code;
    }

    public function verifyCode(string $email, string $code): bool
    {
        $email = trim(strtolower($email));
        $code = preg_replace('/\D/', '', $code);

        if (strlen($code) !== 6) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("SELECT id FROM password_resets WHERE email = ? AND code = ? AND expires_at > ? LIMIT 1");
        $stmt->execute([$email, $code, $now]);

        return (bool)$stmt->fetchColumn();
    }

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

        $del = $this->pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $del->execute([$email]);

        return true;
    }
}
