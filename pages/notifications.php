<?php
// pages/notifications.php
$pageTitle = 'Mes Notifications';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$userId = $_SESSION['user_id'];

// Marquer tout comme lu
$pdo->prepare("UPDATE notifications SET lu=1 WHERE user_id=? AND lu=0")->execute([$userId]);

// Récupérer les notifs
$notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
$notifs->execute([$userId]);
$notifications = $notifs->fetchAll();
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-primary-900 text-white px-4" style="height: calc(3.5rem + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px);">
  <div class="flex items-center h-14 gap-3">
    <a href="javascript:history.back()"class="w-8 h-8 flex items-center justify-center rounded-full bg-white/10 active:scale-95 transition-transform">
      <svg xmlns="http://www.w3.org/2000/svg"width="20"height="20"fill="none"stroke="currentColor"stroke-width="2"viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
    </a>
    <h1 class="font-bold text-lg flex-1">Notifications</h1>
  </div>
</div>

<main class="page-content px-4 py-6">
  <div class="space-y-3">
    <?php if (empty($notifications)): ?>
    <div class="text-center py-10 fade-in-up">
      <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-3">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      </div>
      <h2 class="text-slate-600 dark:text-slate-300 font-semibold text-sm">Aucune notification</h2>
      <p class="text-xs text-slate-400 mt-1">Vous n'avez rien de nouveau pour le moment.</p>
    </div>
    <?php else: ?>
      <?php foreach ($notifications as $n): ?>
      <a href="<?= e($n['lien']) ?>"class="group block bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-100 dark:border-slate-700 shadow-sm card-hover relative overflow-hidden fade-in-up">
        <?php if (!$n['lu']): ?>
        <div class="absolute top-0 left-0 w-1 h-full bg-red-500"></div>
        <?php endif; ?>
        <div class="flex gap-3 items-start">
          <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 <?= $n['type'] === 'commande'?'bg-amber-100 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400': ($n['type'] === 'adhesion'?'bg-green-100 text-green-600 dark:bg-green-950/40 dark:text-green-400':'bg-blue-100 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400') ?>">
            <?php if ($n['type'] === 'commande'): ?>
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <?php elseif ($n['type'] === 'adhesion'): ?>
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <?php else: ?>
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            <?php endif; ?>
          </div>
          <div>
            <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100 mb-0.5"><?= e($n['titre']) ?></h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed"><?= nl2br(e($n['message'])) ?></p>
            <span class="text-[10px] text-slate-400 mt-2 block"><?= date('d/m/Y à H:i', strtotime($n['created_at'])) ?></span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>

<?php require_once __DIR__ .'/../includes/footer.php'; ?>
