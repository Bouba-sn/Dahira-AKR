<?php
// pages/tidiany-way.php
$pageTitle = 'Tidiany Way';
require_once __DIR__ . '/../includes/header.php';

$pdo = db();

// Filtres
$categorie = isset($_GET['cat']) ? sanitize($_GET['cat']) : '';
$search    = isset($_GET['q'])   ? sanitize($_GET['q'])   : '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 9;
$offset    = ($page - 1) * $perPage;

// Catégories disponibles
$categories = $pdo->query("SELECT DISTINCT categorie FROM produits WHERE actif=1 AND categorie IS NOT NULL ORDER BY categorie")->fetchAll(PDO::FETCH_COLUMN);

// Requête produits
$where = "WHERE actif=1";
$params = [];
if ($categorie) { $where .= " AND categorie = ?"; $params[] = $categorie; }
if ($search)    { $where .= " AND (nom LIKE ? OR description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$total = $pdo->prepare("SELECT COUNT(*) FROM produits $where");
$total->execute($params);
$totalCount = (int)$total->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $pdo->prepare("SELECT * FROM produits $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$produits = $stmt->fetchAll();
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 px-4">
    <div class="flex items-center h-14 gap-3">
        <a href="/pages/accueil.php" class="w-8 h-8 flex items-center justify-center text-slate-600 dark:text-slate-300">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <div class="flex-1 flex items-center gap-2">
            <div class="w-12 h-12 rounded-full overflow-hidden shrink-0 border border-slate-200 dark:border-slate-700 shadow-sm">
                <img src="/assets/uploads/4.jpg" alt="Logo" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='/assets/images/placeholder.png'">
            </div>
            <div>
                <h1 class="font-bold text-slate-900 dark:text-white line-clamp-1">Tidiany Way</h1>
                <p class="text-[10px] text-slate-400">Boutique islamique</p>
            </div>
        </div>
        <!-- Panier avec badge -->
        <a href="/pages/panier.php" class="relative flex flex-col items-center justify-center w-12 text-slate-600 dark:text-slate-300 active:scale-95 transition-transform" title="Mon Panier">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" x2="21" y1="6" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
            <span id="cart-badge" class="absolute top-0 right-1 w-3.5 h-3.5 bg-red-500 text-white text-[8px] font-bold rounded-full items-center justify-center hidden flex">0</span>
            <span class="text-[8px] font-bold mt-1 uppercase tracking-tight">Panier</span>
        </a>
    </div>
</div>

<main class="page-content">

    <!-- BANNIÈRE HERO -->
    <div class="relative px-4 py-8 text-white overflow-hidden shadow-sm rounded-b-3xl sm:rounded-none">
        <!-- Background image avec overlay -->
        <div class="absolute inset-0 z-0">
            <img src="/assets/uploads/7.jpg" class="w-full h-full object-cover opacity-100 scale-105" alt="Background">
            <div class="absolute inset-0 bg-gradient-to-r from-amber-900/95 to-amber-700/80 mix-blend-multiply"></div>
            <div class="absolute inset-0 bg-gradient-to-b from-black/10 to-transparent"></div>
        </div>
        
        <div class="relative z-10 flex items-center justify-between gap-4">
            <div class="flex-1">
                <h2 class="text-2xl font-bold mb-1.5 drop-shadow-md">Tidiany Way</h2>
                <p class="text-sm font-medium opacity-100 leading-tight text-amber-50 drop-shadow">Produits islamiques de qualité</p>
                <p class="text-[10px] font-bold mt-4 bg-white/20 backdrop-blur-sm border border-white/20 inline-block px-3 py-1.5 rounded-xl tracking-wide shadow-sm">Livraison partout dans le monde</p>
            </div>
            <div class="w-24 h-24 rounded-2xl overflow-hidden shrink-0 shadow-[0_8px_30px_rgb(0,0,0,0.3)] border-2 border-white/20 transform rotate-3">
                <img src="/assets/uploads/5.jpg" alt="Produits" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='/assets/images/placeholder.png'">
            </div>
        </div>
    </div>

    <!-- RECHERCHE -->
    <div class="px-4 mt-4">
        <form method="GET" class="relative">
            <input type="hidden" name="cat" value="<?= e($categorie) ?>">
            <input type="text" name="q" value="<?= e($search) ?>"
                   placeholder="Rechercher un produit..."
                   class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500">
            <svg class="absolute left-3 top-3 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
        </form>
    </div>

    <!-- FILTRES CATÉGORIES -->
    <?php if (!empty($categories)): ?>
    <div class="px-4 mt-3 flex gap-2 overflow-x-auto scrollbar-hide pb-1">
        <a href="/pages/tidiany-way.php<?= $search ? '?q='.urlencode($search) : '' ?>"
           class="flex-shrink-0 text-xs px-3 py-1.5 rounded-full font-medium transition-colors <?= !$categorie ? 'bg-primary-900 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300' ?>">
            Tout
        </a>
        <?php foreach ($categories as $cat): ?>
        <a href="?cat=<?= urlencode($cat) ?><?= $search ? '&q='.urlencode($search) : '' ?>"
           class="flex-shrink-0 text-xs px-3 py-1.5 rounded-full font-medium transition-colors <?= $categorie === $cat ? 'bg-primary-900 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300' ?>">
            <?= e($cat) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- RÉSULTATS -->
    <div class="px-4 mt-4">
        <?php if ($search || $categorie): ?>
        <p class="text-xs text-slate-400 mb-3"><?= $totalCount ?> résultat<?= $totalCount !== 1 ? 's' : '' ?></p>
        <?php endif; ?>

        <?php if (empty($produits)): ?>
        <div class="text-center py-12">
            <span class="text-4xl block mb-3">🔍</span>
            <p class="text-slate-500 dark:text-slate-400">Aucun produit trouvé</p>
        </div>
        <?php else: ?>

        <div class="grid grid-cols-2 gap-3">
        <?php foreach ($produits as $p): ?>
        <div class="card-hover bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 overflow-hidden fade-in-up">
            <a href="/pages/produit.php?id=<?= $p['id'] ?>">
                <div class="aspect-square bg-gradient-to-br from-amber-50 to-amber-100 dark:from-slate-700 dark:to-slate-600 flex items-center justify-center relative">
                    <?php if ($p['image']): ?>
                    <img data-src="/assets/uploads/<?= e($p['image']) ?>" alt="<?= e($p['nom']) ?>"
                         class="w-full h-full object-cover" src="/assets/placeholder.jpg">
                    <?php else: ?>
                    <span class="text-4xl">📿</span>
                    <?php endif; ?>
                    <?php if ($p['stock'] <= 5 && $p['stock'] > 0): ?>
                    <span class="absolute top-2 right-2 text-[10px] bg-orange-500 text-white px-1.5 py-0.5 rounded-full font-medium">Presque épuisé</span>
                    <?php elseif ($p['stock'] == 0): ?>
                    <span class="absolute top-2 right-2 text-[10px] bg-red-500 text-white px-1.5 py-0.5 rounded-full font-medium">Épuisé</span>
                    <?php endif; ?>
                </div>
            </a>
            <div class="p-2.5">
                <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 leading-tight line-clamp-2 mb-1"><?= e($p['nom']) ?></p>
                <p class="text-xs text-primary-900 dark:text-blue-400 font-bold"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</p>
                <?php if ($p['stock'] > 0): ?>
                <button onclick="Cart.add({id:<?= $p['id'] ?>, nom:'<?= addslashes($p['nom']) ?>', prix:<?= $p['prix'] ?>, image:'<?= $p['image'] ?>'})"
                        class="w-full mt-2 bg-primary-900 text-white text-xs py-1.5 rounded-xl font-medium active:scale-95 transition-transform">
                    + Panier
                </button>
                <?php else: ?>
                <button disabled class="w-full mt-2 bg-slate-200 dark:bg-slate-700 text-slate-400 text-xs py-1.5 rounded-xl font-medium">
                    Indisponible
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
        <div class="flex justify-center gap-2 mt-6">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?><?= $categorie ? '&cat='.urlencode($categorie) : '' ?><?= $search ? '&q='.urlencode($search) : '' ?>"
               class="w-8 h-8 rounded-full text-sm font-medium flex items-center justify-center <?= $i === $page ? 'bg-primary-900 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- PAIEMENTS ACCEPTÉS -->
    <div class="mx-4 my-6 p-3 bg-slate-50 dark:bg-slate-800 rounded-2xl">
        <p class="text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">💳 Modes de paiement</p>
        <div class="flex gap-2 flex-wrap">
            <span class="text-xs bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 px-2 py-1 rounded-lg text-slate-600 dark:text-slate-300">📱 Wave</span>
            <span class="text-xs bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 px-2 py-1 rounded-lg text-slate-600 dark:text-slate-300">🟠 Orange Money</span>
            <span class="text-xs bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 px-2 py-1 rounded-lg text-slate-600 dark:text-slate-300">🚚 À la livraison</span>
        </div>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
