<?php
// admin/rappels.php
$pageTitle = 'Rappels du jour';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$pdo = db();
$msg = '';

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM rappels WHERE id=?")->execute([(int)$_GET['delete']]);
    $msg = '✅ Rappel supprimé.';
}

if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE rappels SET actif = 1 - actif WHERE id=?")->execute([(int)$_GET['toggle']]);
    $msg = '✅ Statut modifié.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $id  = (int)($_POST['id'] ?? 0);
    $ar  = $_POST['texte_arabe'] ?? '';
    $fr  = sanitize($_POST['texte_francais'] ?? '');
    $src = sanitize($_POST['source'] ?? '');

    if ($id > 0) {
        $pdo->prepare("UPDATE rappels SET texte_arabe=?, texte_francais=?, source=? WHERE id=?")->execute([$ar, $fr, $src, $id]);
        $msg = '✅ Rappel modifié.';
    } else {
        $pdo->prepare("INSERT INTO rappels (texte_arabe, texte_francais, source, actif) VALUES (?,?,?,1)")->execute([$ar, $fr, $src]);
        $msg = '✅ Rappel ajouté.';
    }
}

$rappels = $pdo->query("SELECT * FROM rappels ORDER BY id DESC")->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM rappels WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
}
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-primary-900 text-white px-4">
    <div class="flex items-center h-14 gap-3">
        <a href="/admin/dashboard.php" class="w-8 h-8 flex items-center justify-center text-white/70">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h1 class="font-bold text-sm flex-1">Rappels du jour ✨</h1>
        <a href="?add=1" class="text-xs bg-white/20 px-3 py-1.5 rounded-full font-medium">+ Ajouter</a>
    </div>
</div>

<main class="pb-6 bg-slate-50 dark:bg-slate-950 min-h-screen px-4">

    <?php if ($msg): ?>
    <div class="mt-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 rounded-xl text-green-700 text-sm"><?= e($msg) ?></div>
    <?php endif; ?>

    <!-- FORMULAIRE -->
    <?php if (isset($_GET['add']) || $edit): ?>
    <div class="mt-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-4">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-4"><?= $edit ? 'Modifier' : 'Nouveau' ?> rappel</h2>
        <form method="POST" class="space-y-3">
            <?= csrfField() ?>
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Texte arabe *</label>
                <textarea name="texte_arabe" rows="4" dir="rtl" required
                          class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none resize-none font-arabic text-lg"><?= e($edit['texte_arabe'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Traduction française *</label>
                <textarea name="texte_francais" rows="3" required
                          class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none resize-none"><?= e($edit['texte_francais'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Source</label>
                <input type="text" name="source" value="<?= e($edit['source'] ?? '') ?>" placeholder="Ex: Coran, Hadith, Wird Tijaniyya..."
                       class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none">
            </div>
            <div class="flex gap-2 pt-1">
                <button type="submit" class="flex-1 bg-primary-900 text-white rounded-xl py-2.5 text-sm font-semibold">Enregistrer</button>
                <a href="/admin/rappels.php" class="flex-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl py-2.5 text-sm font-medium text-center">Annuler</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- LISTE -->
    <div class="mt-4 space-y-3">
    <?php foreach ($rappels as $r): ?>
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 p-3 <?= !$r['actif'] ? 'opacity-60' : '' ?>">
        <div class="flex items-start justify-between gap-2 mb-2">
            <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $r['actif'] ? 'bg-green-100 text-green-600' : 'bg-slate-100 text-slate-400' ?>">
                <?= $r['actif'] ? 'Actif' : 'Inactif' ?>
            </span>
            <div class="flex gap-1">
                <a href="?toggle=<?= $r['id'] ?>" class="w-7 h-7 rounded-lg bg-green-50 dark:bg-green-900/20 text-green-600 flex items-center justify-center text-xs">⚡</a>
                <a href="?edit=<?= $r['id'] ?>"   class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center text-xs">✏️</a>
                <a href="?delete=<?= $r['id'] ?>" onclick="return confirm('Supprimer ?')" class="w-7 h-7 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-500 flex items-center justify-center text-xs">🗑️</a>
            </div>
        </div>
        <p class="arabic text-base leading-loose text-slate-700 dark:text-slate-200 mb-1"><?= e(mb_substr($r['texte_arabe'], 0, 100)) ?>...</p>
        <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2"><?= e(mb_substr($r['texte_francais'], 0, 120)) ?>...</p>
        <?php if ($r['source']): ?>
        <p class="text-[10px] text-primary-700 dark:text-blue-400 mt-1 font-medium">— <?= e($r['source']) ?></p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
