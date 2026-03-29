<?php
// pages/produit.php
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ? AND actif = 1");
$stmt->execute([$id]);
$produit = $stmt->fetch();

if (!$produit) { header('Location: /pages/tidiany-way.php'); exit; }

$pageTitle = $produit['nom'];

// Produits similaires
$related = $pdo->prepare("SELECT * FROM produits WHERE categorie = ? AND id != ? AND actif = 1 LIMIT 4");
$related->execute([$produit['categorie'], $id]);
$related = $related->fetchAll();
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 px-4">
    <div class="flex items-center h-14 gap-3">
        <a href="/pages/tidiany-way.php" class="w-8 h-8 flex items-center justify-center text-slate-600 dark:text-slate-300">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h1 class="font-bold text-slate-900 dark:text-white text-sm truncate flex-1"><?= e($produit['nom']) ?></h1>
        <a href="/pages/panier.php" class="relative w-9 h-9 flex items-center justify-center">
            <svg class="w-5 h-5 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" x2="21" y1="6" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
            <span id="cart-badge" class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full items-center justify-center hidden flex">0</span>
        </a>
    </div>
</div>

<main class="pb-32">

    <!-- IMAGE PRODUIT -->
    <div class="aspect-square bg-gradient-to-br from-amber-50 to-amber-100 dark:from-slate-700 dark:to-slate-600 flex items-center justify-center">
        <?php if ($produit['image']): ?>
        <img src="/assets/uploads/<?= e($produit['image']) ?>" alt="<?= e($produit['nom']) ?>" class="w-full h-full object-cover">
        <?php else: ?>
        <span class="text-7xl">📿</span>
        <?php endif; ?>
    </div>

    <!-- INFOS PRODUIT -->
    <div class="px-4 mt-4">
        <div class="flex items-start justify-between gap-3">
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex-1"><?= e($produit['nom']) ?></h2>
            <?php if ($produit['categorie']): ?>
            <span class="text-xs bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 px-2 py-1 rounded-full font-medium flex-shrink-0"><?= e($produit['categorie']) ?></span>
            <?php endif; ?>
        </div>

        <p class="text-2xl font-bold text-primary-900 dark:text-blue-400 mt-2"><?= number_format($produit['prix'], 0, ',', ' ') ?> <span class="text-sm font-normal">FCFA</span></p>

        <!-- Stock -->
        <div class="flex items-center gap-2 mt-2">
            <?php if ($produit['stock'] > 10): ?>
            <span class="flex items-center gap-1 text-xs text-green-600 dark:text-green-400"><span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>En stock (<?= $produit['stock'] ?> disponibles)</span>
            <?php elseif ($produit['stock'] > 0): ?>
            <span class="flex items-center gap-1 text-xs text-orange-500"><span class="w-2 h-2 rounded-full bg-orange-500 inline-block"></span>Plus que <?= $produit['stock'] ?> en stock !</span>
            <?php else: ?>
            <span class="flex items-center gap-1 text-xs text-red-500"><span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>Rupture de stock</span>
            <?php endif; ?>
        </div>

        <!-- Description -->
        <div class="mt-4">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-2">Description</h3>
            <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed"><?= nl2br(e($produit['description'] ?? '')) ?></p>
        </div>

        <!-- Infos livraison -->
        <div class="mt-4 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
            <p class="text-xs font-semibold text-slate-700 dark:text-slate-200 mb-2">🚚 Livraison & Paiement</p>
            <div class="space-y-1">
                <p class="text-xs text-slate-500 dark:text-slate-400">✓ Livraison disponible partout au Sénégal</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">✓ Paiement Wave, Orange Money ou à la livraison</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">✓ Délai: 2-5 jours ouvrables</p>
            </div>
        </div>
    </div>

    <!-- PRODUITS SIMILAIRES -->
    <?php if (!empty($related)): ?>
    <div class="px-4 mt-5">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3">Vous aimerez aussi</h3>
        <div class="flex gap-3 overflow-x-auto pb-2">
        <?php foreach ($related as $r): ?>
        <a href="/pages/produit.php?id=<?= $r['id'] ?>" class="flex-shrink-0 w-28 card-hover">
            <div class="aspect-square bg-amber-50 dark:bg-slate-700 rounded-xl flex items-center justify-center mb-1">
                <?php if ($r['image']): ?>
                <img data-src="/assets/uploads/<?= e($r['image']) ?>" class="w-full h-full object-cover rounded-xl">
                <?php else: ?>
                <span class="text-2xl">📿</span>
                <?php endif; ?>
            </div>
            <p class="text-xs font-medium text-slate-700 dark:text-slate-200 line-clamp-2 leading-tight"><?= e($r['nom']) ?></p>
            <p class="text-xs font-bold text-primary-900 dark:text-blue-400 mt-0.5"><?= number_format($r['prix'], 0, ',', ' ') ?> F</p>
        </a>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</main>

<!-- BOUTON FIXE BAS -->
<div class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] z-50 px-4 pb-4 bg-white/95 dark:bg-slate-900/95 backdrop-blur border-t border-slate-100 dark:border-slate-800 pt-3">
    <?php if ($produit['stock'] > 0): ?>
    <div class="flex gap-2">
        <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-700 rounded-xl px-3">
            <button onclick="changeQty(-1)" class="text-lg font-bold text-slate-600 dark:text-slate-300 py-2">−</button>
            <span id="qty-display" class="text-sm font-bold text-slate-800 dark:text-slate-100 min-w-[1.5rem] text-center">1</span>
            <button onclick="changeQty(1)"  class="text-lg font-bold text-slate-600 dark:text-slate-300 py-2">+</button>
        </div>
        <button onclick="addToCart()" class="flex-1 bg-primary-900 text-white rounded-xl py-3 font-semibold text-sm">
            Ajouter au panier
        </button>
    </div>
    <?php else: ?>
    <button disabled class="w-full bg-slate-200 dark:bg-slate-700 text-slate-400 rounded-xl py-3 font-semibold text-sm">
        Produit épuisé
    </button>
    <?php endif; ?>
</div>

<script>
let qty = 1;
function changeQty(d) {
    qty = Math.max(1, Math.min(<?= $produit['stock'] ?>, qty + d));
    document.getElementById('qty-display').textContent = qty;
}
function addToCart() {
    for (let i = 0; i < qty; i++) {
        Cart.add({id: <?= $produit['id'] ?>, nom: '<?= addslashes($produit['nom']) ?>', prix: <?= $produit['prix'] ?>, image: '<?= $produit['image'] ?>'});
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
