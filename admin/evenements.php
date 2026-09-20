<?php
// admin/evenements.php
$pageTitle = 'Gestion Événements';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$msg = '';

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $pdo->prepare("DELETE FROM evenements WHERE id=?")->execute([(int)$_POST['delete_id']]);
    $msg = 'Evenement supprime.';
}

// CREATE / UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $id          = (int)($_POST['id'] ?? 0);
    $type        = sanitize($_POST['type'] ?? 'autre');
    $nom         = sanitize($_POST['nom_complet'] ?? '');
    $adresse     = sanitize($_POST['adresse'] ?? '');
    $date        = sanitize($_POST['date_evenement'] ?? '');
    $description = sanitize($_POST['description'] ?? '');

    // Upload image
    $imageName = '';
    if (!empty($_FILES['image']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $imageName = 'ev_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../assets/uploads/' . $imageName);
        }
    }

    if ($id > 0) {
        $sql = "UPDATE evenements SET type=?, nom_complet=?, adresse=?, date_evenement=?, description=?" . ($imageName ? ", image=?" : "") . " WHERE id=?";
        $params = [$type, $nom, $adresse, $date, $description];
        if ($imageName) $params[] = $imageName;
        $params[] = $id;
    } else {
        $sql = "INSERT INTO evenements (type, nom_complet, adresse, date_evenement, description, image) VALUES (?,?,?,?,?,?)";
        $params = [$type, $nom, $adresse, $date, $description, $imageName];
    }
    $pdo->prepare($sql)->execute($params);
    $msg = $id > 0 ? 'Événement modifié avec succès.' : 'Événement ajouté avec succès.';
}

$evenements = $pdo->query("SELECT * FROM evenements ORDER BY date_evenement DESC")->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM evenements WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
}

$typeOptions = ['gamou'=>'Gamou','ziar'=>'Ziar','dahira_samedi'=>'Dahira du samedi','autre'=>'Autre'];
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-primary-900 text-white px-4" style="height: calc(3.5rem + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px);">
    <div class="flex items-center h-14 gap-3">
        <a href="/admin/dashboard.php" class="w-8 h-8 flex items-center justify-center text-white/70">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h1 class="font-bold text-sm flex-1">Événements</h1>
        <a href="?add=1" class="text-xs bg-white/20 px-3 py-1.5 rounded-full font-medium">+ Nouveau</a>
    </div>
</div>

<main class="pb-32 bg-slate-50 dark:bg-slate-950 min-h-screen px-4">

    <?php if ($msg): ?>
    <div class="flash-success-box mt-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-700 dark:text-green-400 text-sm flex items-center justify-between gap-2">
        <span><?= e($msg) ?></span>
        <button type="button" onclick="dismissFlash(this.closest('.flash-success-box'))" class="text-green-600 hover:text-green-800 dark:hover:text-green-200 p-1 transition-colors" title="Fermer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <?php endif; ?>

    <!-- FORMULAIRE AJOUT/ÉDITION -->
    <?php if (isset($_GET['add']) || $edit): ?>
    <div class="mt-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-4">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-4"><?= $edit ? 'Modifier' : 'Nouvel' ?> événement</h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-3">
            <?= csrfField() ?>
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Type</label>
                <select name="type" class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none">
                    <?php foreach ($typeOptions as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($edit['type'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Nom de l'événement *</label>
                <input type="text" name="nom_complet" required value="<?= e($edit['nom_complet'] ?? '') ?>"
                       class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none">
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Adresse</label>
                <input type="text" name="adresse" value="<?= e($edit['adresse'] ?? '') ?>"
                       class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none">
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Date et heure *</label>
                <input type="datetime-local" name="date_evenement" required
                       value="<?= $edit ? date('Y-m-d\TH:i', strtotime($edit['date_evenement'])) : '' ?>"
                       class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none">
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Description</label>
                <textarea name="description" rows="3"
                          class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none resize-none"><?= e($edit['description'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Image</label>
                <input type="file" name="image" accept="image/*"
                       class="w-full text-sm text-slate-500 dark:text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary-900 file:text-white file:text-xs file:font-medium">
            </div>

            <div class="flex gap-2 pt-1">
                <button type="submit" class="flex-1 bg-primary-900 text-white rounded-xl py-2.5 text-sm font-semibold shadow-sm hover:shadow-md active:shadow-md transition-all">
                    <?= $edit ? 'Modifier' : 'Ajouter' ?>
                </button>
                <a href="/admin/evenements.php" class="flex-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl py-2.5 text-sm font-medium text-center shadow-sm hover:shadow-md active:shadow-md transition-all">
                    Annuler
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- LISTE -->
    <div class="mt-4 space-y-2">
        <?php foreach ($evenements as $ev): ?>
        <div class="bg-white dark:bg-slate-800 rounded-xl p-3 border border-slate-100 dark:border-slate-700 flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-slate-700 text-primary-900 dark:text-blue-400 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate"><?= e($ev['nom_complet']) ?></p>
                <p class="text-xs text-slate-400"><?= date('d/m/Y H:i', strtotime($ev['date_evenement'])) ?></p>
                <?php if ($ev['adresse']): ?>
                <p class="text-xs text-slate-400 truncate flex items-center gap-1 mt-0.5">
                    <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span><?= e($ev['adresse']) ?></span>
                </p>
                <?php endif; ?>
            </div>
            <div class="flex gap-1 flex-shrink-0">
                <a href="?edit=<?= $ev['id'] ?>" class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center text-xs" title="Modifier">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </a>
                <form method="POST" onsubmit="return confirm('Supprimer cet événement ?')" class="inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="delete_id" value="<?= $ev['id'] ?>">
                    <button type="submit" class="w-7 h-7 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-500 flex items-center justify-center text-xs" title="Supprimer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
