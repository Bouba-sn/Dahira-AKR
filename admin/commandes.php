<?php
// admin/commandes.php
$pageTitle = 'Commandes';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$pdo = db();
$msg = '';

// Mettre à jour le statut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $id     = (int)($_POST['commande_id'] ?? 0);
    $statut = sanitize($_POST['statut'] ?? '');
    $allowed = ['en_attente','confirmee','expediee','livree','annulee'];
    if ($id > 0 && in_array($statut, $allowed)) {
        $pdo->prepare("UPDATE commandes SET statut=? WHERE id=?")->execute([$statut, $id]);
        $msg = '✅ Statut mis à jour.';
    }
}

$filtre = $_GET['statut'] ?? '';
$where  = $filtre ? "WHERE c.statut = ?" : "";
$params = $filtre ? [$filtre] : [];
$stmt   = $pdo->prepare("SELECT c.*, u.nom as client, u.email FROM commandes c JOIN utilisateurs u ON u.id=c.user_id $where ORDER BY c.date_commande DESC");
$stmt->execute($params);
$commandes = $stmt->fetchAll();

$statutColors = ['en_attente'=>'bg-orange-100 text-orange-600','confirmee'=>'bg-blue-100 text-blue-600','expediee'=>'bg-purple-100 text-purple-600','livree'=>'bg-green-100 text-green-600','annulee'=>'bg-red-100 text-red-600'];
$statutLabels = ['en_attente'=>'En attente','confirmee'=>'Confirmée','expediee'=>'Expédiée','livree'=>'Livrée','annulee'=>'Annulée'];
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-primary-900 text-white px-4">
    <div class="flex items-center h-14 gap-3">
        <a href="/admin/dashboard.php" class="w-8 h-8 flex items-center justify-center text-white/70">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h1 class="font-bold text-sm flex-1">Commandes 📦</h1>
        <span class="text-xs opacity-70"><?= count($commandes) ?> commandes</span>
    </div>
</div>

<main class="pb-6 bg-slate-50 dark:bg-slate-950 min-h-screen px-4">

    <?php if ($msg): ?>
    <div class="mt-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 rounded-xl text-green-700 text-sm"><?= e($msg) ?></div>
    <?php endif; ?>

    <!-- FILTRES -->
    <div class="mt-4 flex gap-2 overflow-x-auto pb-1">
        <a href="?" class="flex-shrink-0 text-xs px-3 py-1.5 rounded-full font-medium <?= !$filtre ? 'bg-primary-900 text-white' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700' ?>">Tout</a>
        <?php foreach ($statutLabels as $val => $label): ?>
        <a href="?statut=<?= $val ?>" class="flex-shrink-0 text-xs px-3 py-1.5 rounded-full font-medium <?= $filtre === $val ? 'bg-primary-900 text-white' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- LISTE -->
    <div class="mt-3 space-y-3">
    <?php foreach ($commandes as $c): ?>
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 overflow-hidden">
        <!-- Header commande -->
        <div class="px-4 py-3 flex items-center justify-between border-b border-slate-100 dark:border-slate-700">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wide">Commande #<?= $c['id'] ?></p>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100"><?= e($c['client']) ?></p>
                <?php if ($c['telephone_client']): ?>
                <p class="text-xs font-mono font-bold text-slate-600 mt-0.5 bg-slate-100 dark:bg-slate-700 inline-block px-1.5 rounded">📞 <?= e($c['telephone_client']) ?></p>
                <?php endif; ?>
            </div>
            <div class="text-right">
                <p class="text-base font-bold text-primary-900 dark:text-blue-400"><?= number_format($c['total'], 0, ',', ' ') ?> F</p>
                <span class="text-[10px] px-2 py-0.5 rounded-full font-medium <?= $statutColors[$c['statut']] ?? '' ?>">
                    <?= $statutLabels[$c['statut']] ?? $c['statut'] ?>
                </span>
            </div>
        </div>
        <!-- Détails -->
        <div class="px-4 py-2 text-xs text-slate-400">
            <span>📅 <?= date('d/m/Y H:i', strtotime($c['date_commande'])) ?></span>
            <span class="mx-2">·</span>
            <span class="font-semibold <?= in_array($c['mode_paiement'], ['wave', 'orange_money']) ? 'text-orange-500' : '' ?>">💳 <?= ['livraison'=>'Livraison','wave'=>'Wave','orange_money'=>'Orange Money'][$c['mode_paiement']] ?? $c['mode_paiement'] ?></span>
        </div>
        <?php if ($c['adresse_livraison']): ?>
        <div class="px-4 pb-2 text-xs text-slate-500 dark:text-slate-400">
            📍 <?= e($c['adresse_livraison']) ?>
        </div>
        <?php endif; ?>
        
        <!-- Action statut -->
        <div class="px-4 pb-3 flex flex-col gap-2">
            <?php if ($c['statut'] === 'en_attente' && in_array($c['mode_paiement'], ['wave', 'orange_money'])): ?>
            <!-- Bouton Validation Rapide pour les paiements mobile -->
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="commande_id" value="<?= $c['id'] ?>">
                <input type="hidden" name="statut" value="confirmee">
                <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded-xl text-sm font-bold flex items-center justify-center gap-2 shadow-sm transition-colors" onclick="return confirm('Avez-vous bien reçu le paiement sur ce numéro ?')">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Confirmer la réception de l'argent
                </button>
            </form>
            <?php endif; ?>

            <form method="POST" class="flex items-center gap-2 mt-1">
                <?= csrfField() ?>
                <input type="hidden" name="commande_id" value="<?= $c['id'] ?>">
                <select name="statut" class="flex-1 border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-1.5 text-xs text-slate-800 dark:text-slate-100 focus:outline-none">
                    <?php foreach ($statutLabels as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $c['statut'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 px-3 py-1.5 rounded-xl text-xs font-medium">
                    Modifier
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($commandes)): ?>
    <div class="text-center py-12 text-slate-400">
        <span class="text-4xl block mb-2">📭</span>
        <p class="text-sm">Aucune commande trouvée</p>
    </div>
    <?php endif; ?>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
