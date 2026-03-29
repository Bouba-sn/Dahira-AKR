<?php
// admin/bibliotheque.php
$pageTitle = 'Bibliothèque Admin';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$pdo = db();
$msg  = '';
$tab  = $_GET['tab'] ?? 'auteurs';

// === AUTEURS ===
if ($tab === 'auteurs') {
    if (isset($_GET['delete_auteur'])) {
        $pdo->prepare("DELETE FROM auteurs WHERE id=?")->execute([(int)$_GET['delete_auteur']]);
        $msg = '✅ Auteur supprimé.';
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_auteur' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $id   = (int)($_POST['id'] ?? 0);
        $nom  = sanitize($_POST['nom'] ?? '');
        $bio  = sanitize($_POST['biographie'] ?? '');
        $ordre = (int)($_POST['ordre'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE auteurs SET nom=?, biographie=?, ordre=? WHERE id=?")->execute([$nom, $bio, $ordre, $id]);
            $msg = '✅ Auteur modifié.';
        } else {
            $pdo->prepare("INSERT INTO auteurs (nom, biographie, ordre) VALUES (?,?,?)")->execute([$nom, $bio, $ordre]);
            $msg = '✅ Auteur ajouté.';
        }
    }
    $auteurs = $pdo->query("SELECT a.*, COUNT(e.id) as nb FROM auteurs a LEFT JOIN ecrits e ON e.auteur_id=a.id GROUP BY a.id ORDER BY a.ordre")->fetchAll();
    $editA = null;
    if (isset($_GET['edit_auteur'])) {
        $s = $pdo->prepare("SELECT * FROM auteurs WHERE id=?");
        $s->execute([(int)$_GET['edit_auteur']]);
        $editA = $s->fetch();
    }
}

// === ÉCRITS ===
if ($tab === 'ecrits') {
    if (isset($_GET['delete_ecrit'])) {
        $pdo->prepare("DELETE FROM ecrits WHERE id=?")->execute([(int)$_GET['delete_ecrit']]);
        $msg = '✅ Écrit supprimé.';
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_ecrit' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $id         = (int)($_POST['id'] ?? 0);
        $auteurId   = (int)($_POST['auteur_id'] ?? 0);
        $titre      = sanitize($_POST['titre'] ?? '');
        $titreAr    = sanitize($_POST['titre_arabe'] ?? '');
        $contAr     = $_POST['contenu_arabe'] ?? '';
        $contFr     = $_POST['contenu_francais'] ?? '';
        $type       = sanitize($_POST['type'] ?? 'autre');
        if ($id > 0) {
            $pdo->prepare("UPDATE ecrits SET auteur_id=?, titre=?, titre_arabe=?, contenu_arabe=?, contenu_francais=?, type=? WHERE id=?")
                ->execute([$auteurId, $titre, $titreAr, $contAr, $contFr, $type, $id]);
            $msg = '✅ Écrit modifié.';
        } else {
            $pdo->prepare("INSERT INTO ecrits (auteur_id, titre, titre_arabe, contenu_arabe, contenu_francais, type) VALUES (?,?,?,?,?,?)")
                ->execute([$auteurId, $titre, $titreAr, $contAr, $contFr, $type]);
            $msg = '✅ Écrit ajouté.';
        }
    }
    $ecrits  = $pdo->query("SELECT e.*, a.nom as auteur_nom FROM ecrits e JOIN auteurs a ON a.id=e.auteur_id ORDER BY e.id DESC")->fetchAll();
    $auteurs = $pdo->query("SELECT id, nom FROM auteurs ORDER BY nom")->fetchAll();
    $editE   = null;
    if (isset($_GET['edit_ecrit'])) {
        $s = $pdo->prepare("SELECT * FROM ecrits WHERE id=?");
        $s->execute([(int)$_GET['edit_ecrit']]);
        $editE = $s->fetch();
    }
    $typeOpts = ['qasida'=>'Qasida','wird'=>'Wird','livre'=>'Livre','discours'=>'Discours','autre'=>'Autre'];
}
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-primary-900 text-white px-4">
    <div class="flex items-center h-14 gap-3">
        <a href="/admin/dashboard.php" class="w-8 h-8 flex items-center justify-center text-white/70">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h1 class="font-bold text-sm flex-1">Bibliothèque 📚</h1>
        <a href="?tab=<?= $tab ?>&add=1" class="text-xs bg-white/20 px-3 py-1.5 rounded-full font-medium">+ Ajouter</a>
    </div>
    <!-- Onglets -->
    <div class="flex border-t border-white/10">
        <a href="?tab=auteurs" class="flex-1 py-2 text-center text-xs font-medium <?= $tab === 'auteurs' ? 'text-white border-b-2 border-white' : 'text-white/50' ?>">Auteurs</a>
        <a href="?tab=ecrits"  class="flex-1 py-2 text-center text-xs font-medium <?= $tab === 'ecrits'  ? 'text-white border-b-2 border-white' : 'text-white/50' ?>">Écrits</a>
    </div>
</div>

<main class="pb-6 bg-slate-50 dark:bg-slate-950 min-h-screen px-4">

    <?php if ($msg): ?>
    <div class="mt-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-700 dark:text-green-400 text-sm"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if ($tab === 'auteurs'): ?>

    <!-- FORMULAIRE AUTEUR -->
    <?php if (isset($_GET['add']) || $editA): ?>
    <div class="mt-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-4">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-4"><?= $editA ? 'Modifier' : 'Nouvel' ?> auteur</h2>
        <form method="POST" class="space-y-3">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_auteur">
            <?php if ($editA): ?><input type="hidden" name="id" value="<?= $editA['id'] ?>"><?php endif; ?>
            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Nom *</label>
                <input type="text" name="nom" required value="<?= e($editA['nom'] ?? '') ?>"
                       class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Biographie</label>
                <textarea name="biographie" rows="4"
                          class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none resize-none"><?= e($editA['biographie'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Ordre d'affichage</label>
                <input type="number" name="ordre" min="0" value="<?= $editA['ordre'] ?? 0 ?>"
                       class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none">
            </div>
            <div class="flex gap-2 pt-1">
                <button type="submit" class="flex-1 bg-primary-900 text-white rounded-xl py-2.5 text-sm font-semibold">Enregistrer</button>
                <a href="?tab=auteurs" class="flex-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl py-2.5 text-sm font-medium text-center">Annuler</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- LISTE AUTEURS -->
    <div class="mt-4 space-y-2">
        <?php foreach ($auteurs as $a): ?>
        <div class="bg-white dark:bg-slate-800 rounded-xl p-3 border border-slate-100 dark:border-slate-700 flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center font-bold text-emerald-700 dark:text-emerald-400 flex-shrink-0">
                <?= mb_strtoupper(mb_substr($a['nom'], 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate"><?= e($a['nom']) ?></p>
                <p class="text-xs text-slate-400"><?= $a['nb'] ?> écrit<?= $a['nb'] != 1 ? 's' : '' ?></p>
            </div>
            <div class="flex gap-1">
                <a href="?tab=auteurs&edit_auteur=<?= $a['id'] ?>" class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center text-xs">✏️</a>
                <a href="?tab=auteurs&delete_auteur=<?= $a['id'] ?>" onclick="return confirm('Supprimer ?')" class="w-7 h-7 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-500 flex items-center justify-center text-xs">🗑️</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php elseif ($tab === 'ecrits'): ?>

    <!-- FORMULAIRE ÉCRIT -->
    <?php if (isset($_GET['add']) || $editE): ?>
    <div class="mt-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-4">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-4"><?= $editE ? 'Modifier' : 'Nouvel' ?> écrit</h2>
        <form method="POST" class="space-y-3">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_ecrit">
            <?php if ($editE): ?><input type="hidden" name="id" value="<?= $editE['id'] ?>"><?php endif; ?>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Auteur *</label>
                <select name="auteur_id" required class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none">
                    <?php foreach ($auteurs as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= ($editE['auteur_id'] ?? 0) == $a['id'] ? 'selected' : '' ?>><?= e($a['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Type</label>
                <select name="type" class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none">
                    <?php foreach ($typeOpts as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($editE['type'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Titre (français) *</label>
                <input type="text" name="titre" required value="<?= e($editE['titre'] ?? '') ?>"
                       class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none">
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Titre (arabe)</label>
                <input type="text" name="titre_arabe" value="<?= e($editE['titre_arabe'] ?? '') ?>" dir="rtl"
                       class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none font-arabic">
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Contenu arabe</label>
                <textarea name="contenu_arabe" rows="5" dir="rtl"
                          class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none resize-none font-arabic"><?= e($editE['contenu_arabe'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Traduction française</label>
                <textarea name="contenu_francais" rows="5"
                          class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none resize-none"><?= e($editE['contenu_francais'] ?? '') ?></textarea>
            </div>

            <div class="flex gap-2 pt-1">
                <button type="submit" class="flex-1 bg-primary-900 text-white rounded-xl py-2.5 text-sm font-semibold">Enregistrer</button>
                <a href="?tab=ecrits" class="flex-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl py-2.5 text-sm font-medium text-center">Annuler</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- LISTE ÉCRITS -->
    <div class="mt-4 space-y-2">
        <?php foreach ($ecrits as $e): ?>
        <div class="bg-white dark:bg-slate-800 rounded-xl p-3 border border-slate-100 dark:border-slate-700 flex items-start gap-3">
            <div class="w-9 h-9 rounded-xl bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center flex-shrink-0">
                <span class="text-lg"><?= $e['type'] === 'qasida' ? '📿' : ($e['type'] === 'livre' ? '📖' : '📜') ?></span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate"><?= e($e['titre']) ?></p>
                <p class="text-xs text-slate-400 truncate">— <?= e($e['auteur_nom']) ?></p>
            </div>
            <div class="flex gap-1">
                <a href="?tab=ecrits&edit_ecrit=<?= $e['id'] ?>" class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center text-xs">✏️</a>
                <a href="?tab=ecrits&delete_ecrit=<?= $e['id'] ?>" onclick="return confirm('Supprimer ?')" class="w-7 h-7 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-500 flex items-center justify-center text-xs">🗑️</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
