<?php
// admin/boutique.php
$pageTitle = 'Gestion Boutique';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$pdo = db();
$msg = '';

// DELETE
if (isset($_GET['delete'])) {
    $pdo->prepare("UPDATE produits SET actif=0 WHERE id=?")->execute([(int)$_GET['delete']]);
    $msg = '✅ Produit désactivé.';
}

// CREATE / UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $id          = (int)($_POST['id'] ?? 0);
    $nom         = sanitize($_POST['nom'] ?? '');
    $prix        = (float)($_POST['prix'] ?? 0);
    $stock       = (int)($_POST['stock'] ?? 0);
    $categorie   = sanitize($_POST['categorie'] ?? '');
    $description = sanitize($_POST['description'] ?? '');

    $imageName = $id > 0 ? ($pdo->prepare("SELECT image FROM produits WHERE id=?") && ($s = $pdo->prepare("SELECT image FROM produits WHERE id=?")) && $s->execute([$id]) ? $s->fetchColumn() : '') : '';
    if ($id > 0) {
        $s = $pdo->prepare("SELECT image FROM produits WHERE id=?");
        $s->execute([$id]);
        $imageName = $s->fetchColumn() ?: '';
    }

    if (!empty($_FILES['image']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $imageName = 'prod_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../assets/uploads/' . $imageName);
        }
    }

    if ($id > 0) {
        $pdo->prepare("UPDATE produits SET nom=?, prix=?, stock=?, categorie=?, description=?, image=? WHERE id=?")
            ->execute([$nom, $prix, $stock, $categorie, $description, $imageName, $id]);
        $msg = '✅ Produit modifié.';
    } else {
        $pdo->prepare("INSERT INTO produits (nom, prix, stock, categorie, description, image, actif) VALUES (?,?,?,?,?,?,1)")
            ->execute([$nom, $prix, $stock, $categorie, $description, $imageName]);
        $msg = '✅ Produit ajouté.';
    }
}

$produits = $pdo->query("SELECT * FROM produits ORDER BY id DESC")->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM produits WHERE id=?");
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
        <h1 class="font-bold text-sm flex-1">Boutique 🛍️</h1>
        <a href="?add=1" class="text-xs bg-white/20 px-3 py-1.5 rounded-full font-medium">+ Produit</a>
    </div>
</div>

<main class="pb-6 bg-slate-50 dark:bg-slate-950 min-h-screen px-4">

    <?php if ($msg): ?>
    <div class="mt-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-700 dark:text-green-400 text-sm"><?= e($msg) ?></div>
    <?php endif; ?>

    <!-- FORMULAIRE -->
    <?php if (isset($_GET['add']) || $edit): ?>
    <div class="mt-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-4">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-4"><?= $edit ? 'Modifier' : 'Nouveau' ?> produit</h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-3">
            <?= csrfField() ?>
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Nom du produit *</label>
                <input type="text" name="nom" required value="<?= e($edit['nom'] ?? '') ?>"
                       class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Prix (FCFA) *</label>
                    <input type="number" name="prix" required min="0" step="100" value="<?= $edit['prix'] ?? '' ?>"
                           class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none">
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Stock *</label>
                    <input type="number" name="stock" required min="0" value="<?= $edit['stock'] ?? 0 ?>"
                           class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Catégorie</label>
                <input type="text" name="categorie" value="<?= e($edit['categorie'] ?? '') ?>"
                       placeholder="Ex: Livres, Parfums, Accessoires..."
                       class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none">
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Description</label>
                <textarea name="description" rows="3"
                          class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none resize-none"><?= e($edit['description'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Image</label>
                <?php if (!empty($edit['image'])): ?>
                <div class="w-16 h-16 rounded-xl bg-amber-50 flex items-center justify-center mb-2 overflow-hidden">
                    <img src="/assets/uploads/<?= e($edit['image']) ?>" class="w-full h-full object-cover">
                </div>
                <?php endif; ?>
                <input type="file" name="image" accept="image/*"
                       class="w-full text-sm text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary-900 file:text-white file:text-xs file:font-medium">
            </div>

            <div class="flex gap-2 pt-1">
                <button type="submit" class="flex-1 bg-primary-900 text-white rounded-xl py-2.5 text-sm font-semibold">
                    <?= $edit ? 'Modifier' : 'Ajouter' ?>
                </button>
                <a href="/admin/boutique.php" class="flex-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl py-2.5 text-sm font-medium text-center">Annuler</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- LISTE PRODUITS -->
    <div class="mt-4 space-y-2">
        <?php foreach ($produits as $p): ?>
        <div class="bg-white dark:bg-slate-800 rounded-xl p-3 border border-slate-100 dark:border-slate-700 flex items-center gap-3 <?= !$p['actif'] ? 'opacity-50' : '' ?>">
            <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-slate-700 flex items-center justify-center flex-shrink-0 overflow-hidden">
                <?php if ($p['image']): ?>
                <img src="/assets/uploads/<?= e($p['image']) ?>" class="w-full h-full object-cover">
                <?php else: ?>
                <span class="text-xl">📿</span>
                <?php endif; ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate"><?= e($p['nom']) ?></p>
                <p class="text-xs text-primary-900 dark:text-blue-400 font-bold"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</p>
                <div class="flex items-center gap-2 mt-0.5">
                    <?php if ($p['categorie']): ?>
                    <span class="text-[10px] bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded-full"><?= e($p['categorie']) ?></span>
                    <?php endif; ?>
                    <span class="text-[10px] <?= $p['stock'] > 0 ? 'text-green-600' : 'text-red-500' ?>">Stock: <?= $p['stock'] ?></span>
                </div>
            </div>
            <div class="flex gap-1 flex-shrink-0">
                <a href="?edit=<?= $p['id'] ?>" class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center text-xs">✏️</a>
                <a href="?delete=<?= $p['id'] ?>" onclick="return confirm('Désactiver ce produit ?')"
                   class="w-7 h-7 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-500 flex items-center justify-center text-xs">🗑️</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
