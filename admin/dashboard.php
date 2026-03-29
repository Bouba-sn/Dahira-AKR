<?php
// admin/dashboard.php
$pageTitle = 'Dashboard Admin';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$pdo = db();

// Statistiques
$stats = [
    'utilisateurs' => $pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn(),
    'commandes'    => $pdo->query("SELECT COUNT(*) FROM commandes")->fetchColumn(),
    'produits'     => $pdo->query("SELECT COUNT(*) FROM produits WHERE actif=1")->fetchColumn(),
    'ecrits'       => $pdo->query("SELECT COUNT(*) FROM ecrits")->fetchColumn(),
    'revenue'      => $pdo->query("SELECT COALESCE(SUM(total),0) FROM commandes WHERE statut != 'annulee'")->fetchColumn(),
    'en_attente'   => $pdo->query("SELECT COUNT(*) FROM commandes WHERE statut='en_attente'")->fetchColumn(),
    'adh_attente'  => $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE statut_adhesion='en_attente'")->fetchColumn(),
];

// Dernières commandes
$commandes = $pdo->query("SELECT c.*, u.nom as client FROM commandes c JOIN utilisateurs u ON u.id = c.user_id ORDER BY c.date_commande DESC LIMIT 5")->fetchAll();
?>

<!-- TOP BAR ADMIN -->
<div class="sticky top-0 z-40 bg-primary-900 text-white px-4">
    <div class="flex items-center h-14 gap-3">
        <a href="/pages/accueil.php" class="w-8 h-8 flex items-center justify-center text-white/70">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <div class="flex-1">
            <h1 class="font-bold text-sm">Dashboard Admin 👑</h1>
            <p class="text-xs opacity-60">Dahira AKR</p>
        </div>
        <a href="/public/logout.php" class="text-xs bg-white/20 px-3 py-1 rounded-full">Déconnexion</a>
    </div>
</div>

<main class="page-content bg-slate-50 dark:bg-slate-950 min-h-screen">

    <!-- STATS -->
    <div class="px-4 pt-4 grid grid-cols-2 gap-3">
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-3 border border-slate-100 dark:border-slate-700 col-span-2">
            <p class="text-xs text-slate-400 mb-0.5">Chiffre d'affaires total</p>
            <p class="text-2xl font-bold text-primary-900 dark:text-blue-400"><?= number_format($stats['revenue'], 0, ',', ' ') ?> <span class="text-sm font-normal text-slate-400">FCFA</span></p>
            <?php if ($stats['en_attente'] > 0): ?>
            <p class="text-xs text-orange-500 mt-1">⏳ <?= $stats['en_attente'] ?> commande<?= $stats['en_attente'] > 1 ? 's' : '' ?> en attente</p>
            <?php endif; ?>
            <?php if ($stats['adh_attente'] > 0): ?>
            <p class="text-xs text-blue-500 mt-0.5">👋 <?= $stats['adh_attente'] ?> adhésion<?= $stats['adh_attente'] > 1 ? 's' : '' ?> en attente</p>
            <?php endif; ?>
        </div>

        <?php
        $statCards = [
            ['👥', 'Membres', $stats['utilisateurs'], 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400'],
            ['📦', 'Commandes', $stats['commandes'], 'bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400'],
            ['🛍️', 'Produits', $stats['produits'], 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400'],
            ['📚', 'Écrits', $stats['ecrits'], 'bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400'],
        ];
        foreach ($statCards as [$icon, $label, $val, $cls]):
        ?>
        <div class="<?= $cls ?> rounded-2xl p-3 text-center">
            <span class="text-xl block mb-0.5"><?= $icon ?></span>
            <p class="text-xl font-bold"><?= $val ?></p>
            <p class="text-xs opacity-70 font-medium"><?= $label ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- MENU GESTION -->
    <div class="px-4 mt-5">
        <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-3">Gestion</h2>
        <div class="grid grid-cols-2 gap-3">
            <?php
            $menus = [
                ['/admin/evenements.php', '📅', 'Événements', 'Gérer les Dahira'],
                ['/admin/boutique.php',   '🛍️', 'Produits',   'Boutique'],
                ['/admin/biographie.php','📖', 'Biographie','Filiations & parcours'],
                ['/admin/utilisateurs.php','👥', 'Membres',    'Gérer les comptes'],
                ['/admin/commandes.php',  '📦', 'Commandes',  'Suivi livraisons'],
                ['/admin/rappels.php',    '✨', 'Rappels',    'Rappel du jour'],
            ];
            foreach ($menus as [$url, $icon, $label, $sub]):
            ?>
            <a href="<?= $url ?>" class="card-hover bg-white dark:bg-slate-800 rounded-2xl p-3 border border-slate-100 dark:border-slate-700">
                <span class="text-2xl block mb-1"><?= $icon ?></span>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100"><?= $label ?></p>
                <p class="text-xs text-slate-400"><?= $sub ?></p>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ADHÉSIONS EN ATTENTE -->
    <?php
    $adhesions = $pdo->query("SELECT * FROM utilisateurs WHERE statut_adhesion = 'en_attente' ORDER BY telephone ASC")->fetchAll();
    if (!empty($adhesions)):
    ?>
    <div class="px-4 mt-5">
        <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-3">Adhésions en attente</h2>
        <div class="space-y-2">
            <?php foreach ($adhesions as $adh): ?>
            <div class="bg-white dark:bg-slate-800 rounded-xl p-3 border border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3 shadow-sm card-hover fade-in-up">
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-sm text-slate-900 dark:text-white truncate"><?= e($adh['nom']) ?></p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1 mt-0.5"><span class="opacity-60">📞</span> <?= e($adh['telephone']) ?></p>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate flex items-center gap-1 mt-0.5"><span class="opacity-60">📍</span> <?= e($adh['adresse']) ?></p>
                </div>
                <form action="/admin/valider_adhesion.php" method="POST" class="flex-shrink-0">
                    <input type="hidden" name="user_id" value="<?= $adh['id'] ?>">
                    <button type="submit" class="bg-green-100 hover:bg-green-200 dark:bg-green-900/40 text-green-700 dark:text-green-400 font-bold px-3 py-2 rounded-xl text-xs flex items-center gap-2 active:scale-95 transition-all shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg> Valider
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- DERNIÈRES COMMANDES -->
    <?php if (!empty($commandes)): ?>
    <div class="px-4 mt-5">
        <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-3">Dernières commandes</h2>
        <div class="space-y-2">
        <?php
        $statutColors = [
            'en_attente' => 'bg-orange-100 text-orange-600',
            'confirmee'  => 'bg-blue-100 text-blue-600',
            'expediee'   => 'bg-purple-100 text-purple-600',
            'livree'     => 'bg-green-100 text-green-600',
            'annulee'    => 'bg-red-100 text-red-600',
        ];
        $statutLabels = ['en_attente'=>'En attente','confirmee'=>'Confirmée','expediee'=>'Expédiée','livree'=>'Livrée','annulee'=>'Annulée'];
        foreach ($commandes as $c):
        ?>
        <div class="bg-white dark:bg-slate-800 rounded-xl p-3 border border-slate-100 dark:border-slate-700 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-slate-50 dark:bg-slate-700 flex items-center justify-center flex-shrink-0 text-lg">📦</div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate"><?= e($c['client']) ?></p>
                <p class="text-xs text-slate-400"><?= date('d/m/Y H:i', strtotime($c['date_commande'])) ?></p>
            </div>
            <div class="text-right flex-shrink-0">
                <p class="text-sm font-bold text-primary-900 dark:text-blue-400"><?= number_format($c['total'], 0, ',', ' ') ?> F</p>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium <?= $statutColors[$c['statut']] ?? '' ?>">
                    <?= $statutLabels[$c['statut']] ?? $c['statut'] ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <a href="/admin/commandes.php" class="block text-center text-xs text-primary-700 dark:text-blue-400 mt-3 font-medium">
            Voir toutes les commandes →
        </a>
    </div>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
