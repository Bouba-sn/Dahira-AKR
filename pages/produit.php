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
<div class="sticky top-0 z-40 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 px-4" style="height: calc(3.5rem + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px);">
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

<main class="pb-56">

    <!-- IMAGE PRODUIT -->
    <div class="aspect-[4/3] sm:aspect-square bg-gradient-to-br from-amber-50 to-amber-100 dark:from-slate-700 dark:to-slate-600 flex items-center justify-center max-w-lg mx-auto">
        <?php if ($produit['image']): ?>
        <img src="/assets/uploads/<?= e($produit['image']) ?>" alt="<?= e($produit['nom']) ?>" class="w-full h-full object-cover" decoding="async" onerror="this.onerror=null; this.src='/assets/placeholder.jpg'">
        <?php else: ?>
        <svg class="w-20 h-20 text-amber-300 dark:text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
        <?php endif; ?>
    </div>

    <!-- INFOS PRODUIT -->
    <div class="px-4 mt-4 max-w-lg mx-auto">
        <div class="flex items-start justify-between gap-3">
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex-1"><?= e($produit['nom']) ?></h2>
            <?php if ($produit['categorie']): ?>
            <span class="text-xs bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 px-2 py-1 rounded-full font-medium flex-shrink-0"><?= e($produit['categorie']) ?></span>
            <?php endif; ?>
        </div>

        <?php if (!empty($produit['details'])): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1"><?= e($produit['details']) ?></p>
        <?php endif; ?>

        <div class="flex items-center justify-between mt-2">
            <?php if (!empty($produit['en_promo']) && !empty($produit['prix_promo'])): ?>
            <p class="text-2xl font-bold text-primary-900 dark:text-blue-400">
                <span class="text-sm font-normal line-through text-slate-400"><?= number_format($produit['prix'], 0, ',', ' ') ?></span>
                <?= number_format($produit['prix_promo'], 0, ',', ' ') ?> <span class="text-sm font-normal">FCFA</span>
                <span class="text-xs bg-red-500 text-white px-1.5 py-0.5 rounded ml-2">PROMO</span>
            </p>
            <?php else: ?>
            <p class="text-2xl font-bold text-primary-900 dark:text-blue-400"><?= number_format($produit['prix'], 0, ',', ' ') ?> <span class="text-sm font-normal">FCFA</span></p>
            <?php endif; ?>
            
            <?php if ($produit['stock'] > 0): ?>
            <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-700 rounded-lg px-2">
                <button onclick="changeQty(-1)" class="text-lg font-bold text-slate-600 dark:text-slate-300 py-1">−</button>
                <span id="qty-display" class="text-sm font-bold text-slate-800 dark:text-slate-100 min-w-[1.5rem] text-center">1</span>
                <button onclick="changeQty(1)"  class="text-lg font-bold text-slate-600 dark:text-slate-300 py-1">+</button>
            </div>
            <?php endif; ?>
        </div>

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

        

    <!-- PRODUITS SIMILAIRES -->
    <?php if (!empty($related)): ?>
    <div class="px-4 mt-5 max-w-lg mx-auto">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3">Vous aimerez aussi</h3>
        <div class="flex gap-3 overflow-x-auto pb-4 scroll-smooth snap-x">
        <?php foreach ($related as $r): ?>
        <a href="/pages/produit.php?id=<?= $r['id'] ?>" class="flex-shrink-0 w-28 card-hover snap-start">
            <div class="aspect-square bg-amber-50 dark:bg-slate-700 rounded-xl flex items-center justify-center mb-1 overflow-hidden">
                <?php if ($r['image']): ?>
                <img src="/assets/uploads/<?= e($r['image']) ?>" alt="<?= e($r['nom']) ?>" loading="lazy" decoding="async" class="w-full h-full object-cover rounded-xl" onerror="this.onerror=null; this.src='/assets/placeholder.jpg'">
                <?php else: ?>
                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
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
<div class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-lg z-[60] px-4 pb-4 bg-white/95 dark:bg-slate-900/95 backdrop-blur border-t border-slate-100 dark:border-slate-800 pt-3">
    <?php if ($produit['stock'] > 0): ?>
    <button type="button" class="btn-add-to-cart w-full bg-primary-900 text-white rounded-xl py-3 font-semibold text-sm shadow-sm hover:shadow-md active:shadow-md transition-all">
        Ajouter au panier
    </button>
    <?php else: ?>
    <button disabled class="w-full bg-slate-200 dark:bg-slate-700 text-slate-400 rounded-xl py-3 font-semibold text-sm">
        Produit épuisé
    </button>
    <?php endif; ?>
</div>

<script>
let qty = 1;
const produitId = <?= $produit['id'] ?>;
const produitNom = <?= json_encode($produit['nom']) ?>;
const produitPrix = <?= (!empty($produit['en_promo']) && !empty($produit['prix_promo'])) ? $produit['prix_promo'] : $produit['prix'] ?>;
const produitImage = <?= json_encode($produit['image'] ?? '') ?>;
function changeQty(d) {
    qty = Math.max(1, Math.min(<?= $produit['stock'] ?>, qty + d));
    document.getElementById('qty-display').textContent = qty;
}
document.querySelector('.btn-add-to-cart').addEventListener('click', function() {
    for (let i = 0; i < qty; i++) {
        Cart.add({id: produitId, nom: produitNom, prix: produitPrix, image: produitImage});
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
