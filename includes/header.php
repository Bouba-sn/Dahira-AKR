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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="format-detection" content="telephone=no">
    <meta name="format-detection" content="address=no">
    <meta name="format-detection" content="email=no">
    <meta name="description" content="Dahira A Khiba-i Rassouloulahi - Boutique islamique, Bibliothèque et Événements">
    <meta name="theme-color" content="#1e3a8a">
    <?php if (isLoggedIn()): ?>
    <meta name="ws-token" content="<?= e(getWebSocketToken() ?? '') ?>">
    <meta name="ws-user-id" content="<?= (int)$_SESSION['user_id'] ?>">
    <?php endif; ?>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Dahira AKR">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Dahira AKR">
    <meta name="msapplication-TileColor" content="#1e3a8a">
    <meta name="msapplication-tap-highlight" content="no">

    <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?>Dahira AKR</title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="/pwa/manifest.json">
    <link rel="apple-touch-icon" href="/assets/uploads/20.png">

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
    <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Montserrat:wght@800;900&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'Outfit';
            font-display: swap;
        }
    </style>

    <style>
        /* Critical CSS inline for faster render */
        body { 
            background: #f0f4ff; 
            font-family: 'Outfit', system-ui, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
#app {
            background: #f0f4ff;
            min-height: 100vh;
        }
        
        * { 
            -webkit-tap-highlight-color: transparent; 
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
            touch-action: pan-y;
        }
        
        html { scroll-behavior: auto; }
        html, body {
            font-family: 'Outfit', sans-serif;
            background-color: #f0f4ff;
            min-height: 100dvh;
            margin: 0;
            padding: 0;
            overscroll-behavior: none;
            -webkit-overflow-scrolling: touch;
            touch-action: pan-y;
        }
        
        /* Prevent pull-to-refresh and bounce */
        html {
            overflow: hidden;
            overscroll-behavior: none;
        }
        
        body {
            overflow: auto;
            position: relative;
            width: 100%;
            height: auto;
        }
        
        /* Fix for content appearing behind header on scroll */
        body > #app {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1;
        }
        
        /* Logo container - remove white background */
        .logo-container {
            background-color: transparent !important;
        }
        
        .logo-container img {
            background-color: transparent !important;
        }
        
        /* Pulse animation for cart buttons */
        @keyframes btnPulse {
            0% { transform: scale(1); }
            50% { transform: scale(0.95); }
            100% { transform: scale(1); }
        }
        .btn-add-cart:active, .btn-add-to-cart:active {
            animation: btnPulse 0.2s ease-in-out;
        }
        
        /* Desktop navbar - fixed at top, modern frosted glass */
        @media (min-width: 768px) {
            #bottom-nav {
                position: fixed !important;
                top: 0 !important;
                bottom: auto !important;
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                height: 64px !important;
                display: flex !important;
                align-items: center !important;
                z-index: 9999 !important;
                background: rgba(255, 255, 255, 0.94) !important;
                backdrop-filter: blur(16px) !important;
                -webkit-backdrop-filter: blur(16px) !important;
                border-top: none !important;
                border-bottom: 1px solid rgba(226, 232, 240, 0.9) !important;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04) !important;
            }
            .dark #bottom-nav {
                background: rgba(15, 23, 42, 0.94) !important;
                border-bottom: 1px solid rgba(51, 65, 85, 0.7) !important;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.25) !important;
            }
            #app {
                padding-top: 64px !important;
                padding-bottom: 0 !important;
            }
        }

/* App container - scrollable within fixed viewport */
        #app {
            max-width: 100%;
            width: 100%;
            height: 100dvh;
            background: #f0f4ff;
            position: relative;
            z-index: 1;
            overflow-y: scroll;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: none;
            touch-action: pan-y;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Hide scrollbar but keep scroll functionality */
        #app::-webkit-scrollbar { display: none; }
        #app { -ms-overflow-style: none; scrollbar-width: none; }

.dark #app { background: #1e293b; }

        /* Content area */
        .app-content {
            background: #f0f4ff;
            min-height: 100%;
        }
        
        .dark .app-content { background: #1e293b; }

        /* Arabic text */
        .arabic { font-family: 'Amiri', serif; direction: rtl; text-align: right; }

        /* Bottom nav padding */
        .page-content { padding-bottom: calc(6rem + env(safe-area-inset-bottom)) !important; }

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
            position: fixed; bottom: 5.5rem; left: 50%; transform: translateX(-50%) translateY(50px) scale(0.92);
            background: #0f172a; color: white; padding: 0.75rem 1.5rem;
            border-radius: 2rem; font-size: 0.875rem; font-weight: 600;
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease; 
            z-index: 9999; white-space: nowrap; opacity: 0; pointer-events: none;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1); cursor: pointer;
        }
        #toast.show { transform: translateX(-50%) translateY(0) scale(1); opacity: 1; pointer-events: auto; }

        /* Animation & auto-dismiss pour alertes flash */
        .flash-success-box {
            transition: opacity 0.35s ease, transform 0.35s ease, max-height 0.35s ease, margin 0.35s ease, padding 0.35s ease;
            max-height: 200px;
        }
        .flash-success-box.dismissing {
            opacity: 0 !important;
            transform: translateY(-8px) scale(0.98) !important;
            max-height: 0 !important;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            overflow: hidden !important;
            pointer-events: none !important;
        }

        /* Images smooth render */
        img { max-width: 100%; }
        img[data-src] { transition: opacity 0.3s; }
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
                navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
                    .then(reg => console.log('[SW] Enregistré:', reg.scope))
                    .catch(err => {
                        console.warn('[SW] Tentative fallback pwa:', err);
                        navigator.serviceWorker.register('/pwa/service-worker.js')
                            .catch(e => console.error('[SW] Erreur finale:', e));
                    });
            });
        }
    </script>
</head>
<body class="">

<div id="app">
<div class="app-content">

<!-- Toast global -->
<div id="toast"></div>
