<?php
// pages/mes_commandes.php
$pageTitle = 'Mes Commandes';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$pdo = db();
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM commandes WHERE user_id = ? ORDER BY date_commande DESC");
$stmt->execute([$userId]);
$commandes_raw = $stmt->fetchAll();

$commandes = [];
foreach ($commandes_raw as $c) {
    $stmtD = $pdo->prepare("SELECT cd.quantite, cd.prix_unitaire, p.nom FROM commande_details cd LEFT JOIN produits p ON p.id = cd.produit_id WHERE cd.commande_id = ?");
    $stmtD->execute([$c['id']]);
    $c['details'] = $stmtD->fetchAll();
    $commandes[] = $c;
}

$statutLabels = [
    'en_attente' => 'En attente',
    'confirmee' => 'Confirmée',
    'expediee' => 'Expédiée',
    'livree' => 'Livrée',
    'annulee' => 'Annulée'
];

$statutColors = [
    'en_attente' => 'bg-orange-100 text-orange-600',
    'confirmee' => 'bg-blue-100 text-blue-600',
    'expediee' => 'bg-purple-100 text-purple-600',
    'livree' => 'bg-green-100 text-green-600',
    'annulee' => 'bg-red-100 text-red-600'
];

?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 px-4">
    <div class="flex items-center h-14 gap-3">
        <a href="/pages/panier.php" class="w-8 h-8 flex items-center justify-center text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 rounded-full active:scale-95 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h1 class="font-bold text-slate-900 dark:text-white flex-1">Mes Commandes 📦</h1>
    </div>
</div>

<main class="page-content bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="px-4 py-6">
        
        <?php if (empty($commandes)): ?>
        <div class="text-center py-16 px-4">
            <span class="text-5xl block mb-3 opacity-50">🛒</span>
            <h2 class="text-lg font-semibold text-slate-700 dark:text-slate-200 mb-2">Aucune commande</h2>
            <p class="text-sm text-slate-400 mb-5">Vous n'avez pas encore passé de commande.</p>
            <a href="/pages/tidiany-way.php" class="inline-block bg-primary-900 text-white px-6 py-2.5 rounded-xl text-sm font-medium">Boutique</a>
        </div>
        <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($commandes as $c): ?>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-4 shadow-sm fade-in-up">
                <div class="flex justify-between items-start mb-3 border-b border-slate-100 dark:border-slate-700 pb-3">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Commande #<?= $c['id'] ?></p>
                        <p class="text-[10px] text-slate-400 mt-0.5"><?= date('d M Y - H:i', strtotime($c['date_commande'])) ?></p>
                    </div>
                    <span class="text-[10px] px-2.5 py-1 rounded-full font-bold uppercase tracking-wider <?= $statutColors[$c['statut']] ?? 'bg-slate-100' ?>">
                        <?= $statutLabels[$c['statut']] ?? $c['statut'] ?>
                    </span>
                </div>
                
                <div class="flex justify-between items-center mb-3">
                    <p class="text-sm text-slate-600 dark:text-slate-400 font-medium whitespace-nowrap">Mode : <span class="text-slate-900 dark:text-slate-200 uppercase flex items-center"><?= str_replace('_', ' ', $c['mode_paiement']) ?></span></p>
                    <p class="text-lg font-bold text-primary-900 dark:text-blue-400"><?= number_format($c['total'], 0, ',', ' ') ?> F</p>
                </div>
                
                <!-- Détail des articles -->
                <div class="border-t border-slate-100 dark:border-slate-700 pt-3 mt-2">
                    <p class="text-xs font-semibold text-slate-500 mb-2 uppercase tracking-wide">Contenu :</p>
                    <div class="space-y-1.5">
                        <?php foreach ($c['details'] as $item): ?>
                        <div class="flex items-center gap-2 text-[13px] text-slate-700 dark:text-slate-300">
                            <span class="font-bold text-slate-400 w-5 text-right">x<?= $item['quantite'] ?></span>
                            <span class="flex-1 truncate"><?= e($item['nom']) ?></span>
                            <span class="font-medium text-slate-500"><?= number_format($item['prix_unitaire'] * $item['quantite'], 0, ',', ' ') ?> F</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($c['statut'] === 'en_attente'): ?>
                <div class="bg-orange-50 dark:bg-orange-900/10 border border-orange-200 dark:border-orange-800 text-orange-700 dark:text-orange-400 text-xs p-3 rounded-xl mt-3 flex items-start gap-2">
                    <span class="text-lg leading-none">⏳</span>
                    <p>En attente de vérification par l'administrateur. Si vous avez envoyé l'argent par Wave/Orange Money, la commande sera bientôt confirmée.</p>
                </div>
                <?php endif; ?>

                <?php if ($c['statut'] === 'confirmee' || $c['statut'] === 'expediee'): ?>
                <div class="bg-blue-50 py-3 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300 text-xs p-3 rounded-xl mt-3 flex items-center justify-center gap-2 font-bold shadow-inner">
                    <span class="text-lg animate-pulse">🚚</span>
                    <span>LIVRAISON DANS 2-5 JOURS MAXIMUM</span>
                </div>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
