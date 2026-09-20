<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function isLoggedIn(): bool {
  return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
  return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin(string $redirect = '/public/login.php'): void {
  if (!isLoggedIn()) {
    header('Location:'. $redirect);
    exit;
  }
}

function requireAdmin(): void {
  requireLogin();
  if (!isAdmin()) {
    http_response_code(403);
    die('<p style="text-align:center;margin-top:4rem;font-family:sans-serif;">Accès réservé aux administrateurs.</p>');
  }
}

function isMembre(bool $resetCache = false): bool {
  static $isMembreCache = null;
  static $cachedUserId = null;

  $currentUserId = $_SESSION['user_id'] ?? null;
  if ($resetCache || $cachedUserId !== $currentUserId) {
    $isMembreCache = null;
    $cachedUserId = $currentUserId;
  }

  if ($isMembreCache !== null) {
    return $isMembreCache;
  }
  if (!isLoggedIn()) {
    return $isMembreCache = false;
  }
  if (isAdmin()) {
    return $isMembreCache = true;
  }
  if (isset($_SESSION['statut_adhesion'])) {
    return $isMembreCache = ($_SESSION['statut_adhesion'] === 'membre');
  }
  try {
    if (!function_exists('db') && file_exists(__DIR__ . '/../config/database.php')) {
      require_once __DIR__ . '/../config/database.php';
    }
    if (function_exists('db')) {
      $stmt = db()->prepare("SELECT statut_adhesion FROM utilisateurs WHERE id = ?");
      $stmt->execute([$_SESSION['user_id']]);
      $status = $stmt->fetchColumn() ?: 'non_membre';
      $_SESSION['statut_adhesion'] = $status;
      return $isMembreCache = ($status === 'membre');
    }
  } catch (\Throwable $e) {
    // Fallback silencieux en cas d'erreur
  }
  return $isMembreCache = (($_SESSION['statut_adhesion'] ?? '') === 'membre');
}

function requireMembre(string $redirect = '/pages/accueil.php'): void {
  if (!isLoggedIn()) {
    header('Location: /public/login.php');
    exit;
  }
  if (!isMembre()) {
    header('Location: ' . $redirect);
    exit;
  }
}

function getCurrentUser(): ?array {
  if (!isLoggedIn()) return null;
  return [
    'id'              => $_SESSION['user_id'],
    'nom'             => $_SESSION['user_nom'],
    'role'            => $_SESSION['role'],
    'statut_adhesion' => $_SESSION['statut_adhesion'] ?? null,
  ];
}

// Protection CSRF
function generateCsrfToken(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
  return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
  return'<input type="hidden"name="csrf_token"value="'. htmlspecialchars(generateCsrfToken()) .'">';
}

// Nettoyage XSS
function e(string $str): string {
  return htmlspecialchars($str, ENT_QUOTES,'UTF-8');
}

function sanitize(string $str): string {
  return trim(strip_tags($str));
}

// Chargement automatique Composer si disponible
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
  require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * Génère un token d'authentification HMAC signé pour la connexion WebSocket du client.
 */
function getWebSocketToken(): ?string {
  if (!isLoggedIn()) {
    return null;
  }
  $secretKey = getenv('WS_SECRET') ?: 'dahira_akr_default_secure_ws_secret_key_2026';
  $authService = new \App\Services\WebSocketAuthService($secretKey);
  return $authService->generateToken((int)$_SESSION['user_id'], 300);
}

// Fonction utilitaire pour envoyer une notification interne avec push WebSocket temps réel
function addNotification(int $userId, string $titre, string $message, string $lien = '#', string $type = 'systeme'): bool {
  try {
    $service = new \App\Services\NotificationService(db());
    $res = $service->notify($userId, $titre, $message, $lien, $type);
    return $res['db_saved'];
  } catch (\Throwable $e) {
    // Fallback direct en PDO en cas d'erreur exceptionnelle
    error_log('[addNotification] Erreur: ' . $e->getMessage());
    $pdo = db();
    $sql = "INSERT INTO notifications (user_id, titre, message, lien, type) VALUES (?, ?, ?, ?, ?)";
    return $pdo->prepare($sql)->execute([$userId, strip_tags($titre), strip_tags($message), $lien, $type]);
  }
}
