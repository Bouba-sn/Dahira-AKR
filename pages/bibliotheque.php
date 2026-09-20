<?php
// pages/bibliotheque.php
$pageTitle = 'Bibliothèque';
require_once __DIR__ . '/../includes/header.php';

$pdo = db();

// Recherche
$search = isset($_GET['q']) ? sanitize($_GET['q']) : '';

// Auteurs
$where = '';
$params = [];
if ($search) {
    $where = "WHERE nom LIKE ? OR biographie LIKE ?";
    $params = ["%$search%", "%$search%"];
}
$stmt = $pdo->prepare("SELECT * FROM auteurs $where ORDER BY nom ASC");
$stmt->execute($params);
$auteurs = $stmt->fetchAll();

// Compter les écrits par auteur
$ecritsCount = [];
if (!empty($auteurs)) {
    $ids = array_column($auteurs, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $countStmt = $pdo->prepare("SELECT auteur_id, COUNT(*) as cnt FROM ecrits WHERE auteur_id IN ($placeholders) GROUP BY auteur_id");
    $countStmt->execute($ids);
    foreach ($countStmt->fetchAll() as $row) {
        $ecritsCount[$row['auteur_id']] = $row['cnt'];
    }
}
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 px-4" style="height: calc(3.5rem + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px);">
    <div class="flex items-center h-14 gap-3">
        <a href="/pages/accueil.php" class="w-8 h-8 flex items-center justify-center text-slate-600 dark:text-slate-300 flex-shrink-0 bg-slate-100 dark:bg-slate-800 rounded-full active:scale-95 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <div class="flex-1">
            <h1 class="font-bold text-slate-900 dark:text-white line-clamp-1">Bibliothèque</h1>
            <p class="text-[10px] text-slate-400">Écrits et auteurs islamiques</p>
        </div>
    </div>
</div>

<main class="page-content bg-slate-50 dark:bg-slate-950 min-h-screen">

    <!-- BANNIÈRE -->
    <div class="relative px-4 py-8 text-white overflow-hidden shadow-sm rounded-b-3xl sm:rounded-none">
        <div class="absolute inset-0 z-0 bg-emerald-900">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-900 to-emerald-700"></div>
        </div>
        <div class="relative z-10">
            <p class="arabic text-3xl pb-1 drop-shadow-md">المكتبة الإسلامية</p>
            <p class="text-xs font-bold mt-2 pl-3 border-l-4 border-gold-400 drop-shadow-sm text-slate-100 uppercase tracking-widest">Qasidas, Wirds et Livres</p>
        </div>
    </div>

    <!-- RECHERCHE -->
    <div class="px-4 mt-4">
        <form method="GET" class="relative">
            <input type="text" name="q" value="<?= e($search) ?>"
                   placeholder="Rechercher un auteur..."
                   class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500">
            <svg class="absolute left-3 top-3 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
        </form>
    </div>

    <!-- LISTE DES AUTEURS -->
    <div class="px-4 mt-4">
        <?php if ($search): ?>
        <p class="text-xs text-slate-400 mb-3"><?= count($auteurs) ?> résultat<?= count($auteurs) !== 1 ? 's' : '' ?></p>
        <?php endif; ?>

        <?php if (empty($auteurs)): ?>
        <div class="text-center py-12 text-slate-400">
            <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-750 flex items-center justify-center text-slate-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
            <p class="text-slate-500 dark:text-slate-400 text-sm">Aucun auteur trouvé</p>
        </div>
        <?php else: ?>
        <div class="space-y-3">
        <?php foreach ($auteurs as $a): ?>
        <a href="/pages/auteur.php?id=<?= $a['id'] ?>" class="card-hover flex items-center gap-3 bg-white dark:bg-slate-800 rounded-2xl p-3 border border-slate-100 dark:border-slate-700 shadow-sm">
            <div class="w-14 h-14 rounded-full bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0 overflow-hidden">
                <?php if ($a['photo']): ?>
                <img src="/assets/uploads/<?= e($a['photo']) ?>" alt="<?= e($a['nom']) ?>" class="w-full h-full object-cover" loading="lazy" decoding="async" onerror="this.onerror=null; this.src='/assets/placeholder.jpg'">
                <?php else: ?>
                <span class="text-xl font-bold text-emerald-600"><?= mb_strtoupper(mb_substr($a['nom'], 0, 1)) ?></span>
                <?php endif; ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-bold text-sm text-slate-800 dark:text-slate-100"><?= e($a['nom']) ?></p>
                <p class="text-xs text-slate-400 line-clamp-1"><?= e(mb_substr($a['biographie'] ?? '', 0, 80)) ?></p>
                <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium mt-1"><?= $ecritsCount[$a['id']] ?? 0 ?> écrit(s)</p>
            </div>
            <svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
        </a>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="h-8"></div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
