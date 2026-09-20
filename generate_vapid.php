<?php
/**
 * Générateur de clés VAPID pour les push notifications
 * Exécutez ce script une fois pour générer les clés
 * 
 * Utilise la library Minish Web Push PHP
 * Installez via: composer require minish/web-push-php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Minish\WebPush\Vapid;

$vapid = new Vapid();

$keys = $vapid->generateVapidKeys();

echo "=== Clés VAPID générées ===\n\n";
echo "VAPID_PUBLIC_KEY:\n" . $keys['publicKey'] . "\n\n";
echo "VAPID_PRIVATE_KEY:\n" . $keys['privateKey'] . "\n\n";
echo "Subject (mailto):\n" . $keys['subject'] . "\n\n";
echo "Copiez ces clés dans votre configuration.\n";

// Sauvegarder dans un fichier
$config = [
    'public' => $keys['publicKey'],
    'private' => $keys['privateKey'],
    'subject' => $keys['subject'],
    'created' => date('Y-m-d H:i:s')
];

file_put_contents(__DIR__ . '/vapid_keys.json', json_encode($config, JSON_PRETTY_PRINT));

echo "Clés sauvegardées dans vapid_keys.json\n";
