<?php
// pages/auteur.php
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);

$auteur = $pdo->prepare("SELECT * FROM auteurs WHERE id = ?");
$auteur->execute([$id]);
$auteur = $auteur->fetch();

if (!$auteur) { header('Location: /pages/bibliotheque.php'); exit; }

$pageTitle = $auteur['nom'];

$ecrits = $pdo->prepare("SELECT * FROM ecrits WHERE auteur_id = ? ORDER BY id DESC");
$ecrits->execute([$id]);
$ecrits = $ecrits->fetchAll();

$typeLabels = ['qasida'=>'Qasida','wird'=>'Wird','livre'=>'Livre','discours'=>'Discours','autre'=>'Autre'];
$typeColors = ['qasida'=>'bg-purple-100 text-purple-700','wird'=>'bg-blue-100 text-blue-700','livre'=>'bg-green-100 text-green-700','discours'=>'bg-amber-100 text-amber-700','autre'=>'bg-slate-100 text-slate-600'];
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 px-4">
    <div class="flex items-center h-14 gap-3">
        <a href="/pages/bibliotheque.php" class="w-8 h-8 flex items-center justify-center text-slate-600 dark:text-slate-300">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h1 class="font-bold text-slate-900 dark:text-white text-sm truncate"><?= e($auteur['nom']) ?></h1>
    </div>
</div>

<main class="page-content">

    <!-- PROFIL AUTEUR -->
    <div class="bg-gradient-to-b from-emerald-900 to-emerald-700 px-4 pt-6 pb-12 text-white text-center">
        <div class="w-20 h-20 mx-auto rounded-full bg-white/20 border-2 border-white/30 flex items-center justify-center mb-3">
            <?php if ($auteur['photo']): ?>
            <img src="/assets/uploads/<?= e($auteur['photo']) ?>" class="w-full h-full rounded-full object-cover">
            <?php else: ?>
            <span class="text-3xl font-bold"><?= mb_strtoupper(mb_substr($auteur['nom'], 0, 1)) ?></span>
            <?php endif; ?>
        </div>
        <h2 class="text-lg font-bold"><?= e($auteur['nom']) ?></h2>
        <p class="text-xs opacity-70 mt-1"><?= count($ecrits) ?> écrits disponibles</p>
    </div>

    <!-- BIOGRAPHIE -->
    <div class="mx-4 -mt-6 relative z-10">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg p-4 border border-slate-100 dark:border-slate-700">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-2">📜 Biographie</h3>
            <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed"><?= nl2br(e($auteur['biographie'] ?? '')) ?></p>
        </div>
    </div>

    <!-- ÉCRITS -->
    <div class="px-4 mt-5">
        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 mb-3">Ses écrits</h3>

        <?php if (empty($ecrits)): ?>
        <div class="text-center py-8 text-slate-400">
            <span class="text-3xl block mb-2">📭</span>
            <p class="text-sm">Aucun écrit disponible</p>
        </div>
        <?php else: ?>
        <div class="space-y-2">
        <?php foreach ($ecrits as $ecrit): ?>
        <a href="/pages/ecrit.php?id=<?= $ecrit['id'] ?>" class="card-hover flex items-start gap-3 bg-white dark:bg-slate-800 rounded-xl p-3 border border-slate-100 dark:border-slate-700">
            <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                <span class="text-lg"><?= $ecrit['type'] === 'qasida' ? '📿' : ($ecrit['type'] === 'livre' ? '📖' : '📜') ?></span>
            </div>
            <div class="flex-1 min-w-0">
                <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium <?= $typeColors[$ecrit['type']] ?? 'bg-slate-100 text-slate-600' ?>">
                    <?= $typeLabels[$ecrit['type']] ?? 'Écrit' ?>
                </span>
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100 mt-1"><?= e($ecrit['titre']) ?></p>
                <?php if ($ecrit['titre_arabe']): ?>
                <p class="arabic text-sm text-slate-400 text-right"><?= e($ecrit['titre_arabe']) ?></p>
                <?php endif; ?>
            </div>
            <svg class="w-4 h-4 text-slate-300 flex-shrink-0 mt-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
        </a>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
