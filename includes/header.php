<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

$darkMode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === '1';
$bodyClass = $darkMode ? 'dark' : '';
?>
<!DOCTYPE html>
<html lang="fr" class="<?= $darkMode ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Dahira A Khiba-i Rassouloulahi - Boutique islamique, Bibliothèque et Événements">
    <meta name="theme-color" content="#1e3a8a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Dahira AKR">

    <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?>Dahira AKR</title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="/pwa/manifest.json">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192x192.png">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50:  '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        gold: {
                            400: '#fbbf24',
                            500: '#f59e0b',
                            600: '#d97706',
                        }
                    },
                    fontFamily: {
                        'arabic': ['Amiri', 'serif'],
                        'sans': ['Outfit', 'sans-serif'],
                    },
                    spacing: {
                        'safe-b': 'env(safe-area-inset-bottom)',
                    }
                }
            }
        }
    </script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * { -webkit-tap-highlight-color: transparent; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f0f4ff;
            min-height: 100dvh;
            overscroll-behavior: none;
        }
        .dark body { background-color: #0f172a; }

        /* App shell */
        #app {
            max-width: 430px;
            margin: 0 auto;
            min-height: 100dvh;
            background: white;
            position: relative;
            box-shadow: 0 0 40px rgba(30,58,138,0.15);
        }
        .dark #app { background: #1e293b; }

        /* Arabic text */
        .arabic { font-family: 'Amiri', serif; direction: rtl; text-align: right; }

        /* Bottom nav padding */
        .page-content { padding-bottom: calc(5.5rem + env(safe-area-inset-bottom)) !important; }

        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: fadeInUp 0.4s ease forwards; }

        @keyframes slideIn {
            from { transform: translateX(-100%); }
            to   { transform: translateX(0); }
        }

        /* Skeleton loader */
        .skeleton {
            background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 8px;
        }
        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #1e3a8a; border-radius: 2px; }

        /* Toast notification */
        #toast {
            position: fixed; bottom: 5.5rem; left: 50%; transform: translateX(-50%) translateY(100px);
            background: #1e3a8a; color: white; padding: 0.75rem 1.5rem;
            border-radius: 2rem; font-size: 0.875rem; font-weight: 500;
            transition: transform 0.3s ease; z-index: 9999; white-space: nowrap;
            box-shadow: 0 4px 20px rgba(30,58,138,0.4);
        }
        #toast.show { transform: translateX(-50%) translateY(0); }

        /* Lazy image */
        img[data-src] { opacity: 0; transition: opacity 0.3s; }
        img.loaded { opacity: 1; }

        /* Card hover */
        .card-hover { transition: transform 0.2s, box-shadow 0.2s; }
        .card-hover:active { transform: scale(0.97); }

        /* Bottom nav */
        #bottom-nav { backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
    </style>

    <!-- PWA Service Worker -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/pwa/service-worker.js')
                    .then(reg => console.log('[SW] Enregistré:', reg.scope))
                    .catch(err => console.log('[SW] Erreur:', err));
            });
        }
    </script>
</head>
<body class="<?= $bodyClass ?>">
<div id="app">

<!-- Toast global -->
<div id="toast"></div>
