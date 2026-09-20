<?php
// pages/commande.php
$pageTitle = 'Commander';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$user = getCurrentUser();
$error = '';
$success = false;

$paiementConfig = file_exists(__DIR__ . '/../config/paiement.php') ? require __DIR__ . '/../config/paiement.php' : [];
$waveCfg = $paiementConfig['wave'] ?? [
    'nom'           => 'Caisse Dahira (Wave)',
    'telephone'     => '78 823 24 79',
    'raw_telephone' => '788232479',
    'lien_paiement' => 'https://pay.wave.com/m/M_dahira_akr',
    'logo'          => '/assets/wave-logo.png'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Vérification CSRF
  if (!verifyCsrfToken($_POST['csrf_token'] ??'')) {
    $error = 'Token invalide. Veuillez réessayer.';
  } else {
    $adresse   = sanitize($_POST['adresse'] ??'');
    $telephone  = sanitize($_POST['telephone'] ??'');
    $paiement  = sanitize($_POST['mode_paiement'] ??'wave');
    $cartJson  = $_POST['cart_data'] ??'[]';
    $cartItems  = json_decode($cartJson, true);

    // Récupérer les détails depuis les champs du formulaire
    $formDetails = [];
    foreach ($_POST as $key => $value) {
      if (strpos($key, 'details_') === 0) {
        $prodId = (int)str_replace('details_', '', $key);
        $formDetails[$prodId] = sanitize($value);
      }
    }

    if (empty($cartItems)) {
      $error = 'Votre panier est vide.';
    } elseif (empty($adresse) || empty($telephone)) {
      $error = 'Veuillez saisir votre adresse de livraison et votre numéro de téléphone.';
    } else {
      $pdo = db();
      
      // Auto-migration: add details column if not exists
      try {
        $pdo->exec("ALTER TABLE commande_details ADD COLUMN details VARCHAR(500) DEFAULT NULL");
      } catch (Exception $e) {}
      
      $total = array_sum(array_map(fn($i) => $i['prix'] * $i['qty'], $cartItems));

      try {
        $pdo->beginTransaction();

        // Créer la commande
        $stmt = $pdo->prepare("INSERT INTO commandes (user_id, total, statut, mode_paiement, adresse_livraison, telephone_client) VALUES (?, ?,'en_attente', ?, ?, ?)");
        $stmt->execute([$user['id'], $total, $paiement, $adresse, $telephone]);
        $commandeId = $pdo->lastInsertId();

        // Ajouter les détails
        $stmtD = $pdo->prepare("INSERT INTO commande_details (commande_id, produit_id, quantite, prix_unitaire, details) VALUES (?, ?, ?, ?, ?)");
        foreach ($cartItems as $item) {
          // Utiliser les détails du formulaire, sinon ceux du panier
          $details = $formDetails[$item['id']] ?? $item['details'] ?? null;
          $stmtD->execute([$commandeId, $item['id'], $item['qty'], $item['prix'], $details]);
          // Décrémenter le stock
          $pdo->prepare("UPDATE produits SET stock = stock - ? WHERE id = ? AND stock >= ?")->execute([$item['qty'], $item['id'], $item['qty']]);
        }

        $pdo->commit();
        $success = true;
        $orderId = $commandeId;
        
        // --- NOTIFIER LES ADMINS ---
        $admins = $pdo->query("SELECT id FROM utilisateurs WHERE role='admin'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($admins as $adminId) {
          addNotification($adminId,'Nouvelle Commande',"Une nouvelle commande de". e($total) ."FCFA a été passée.",'/admin/commandes.php');
        }
      } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Erreur lors de la commande. Veuillez réessayer.';
      }
    }
  }
}
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 px-4" style="height: calc(3.5rem + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px);">
  <div class="flex items-center h-14 gap-3">
    <a href="/pages/panier.php"class="w-8 h-8 flex items-center justify-center text-slate-600 dark:text-slate-300">
      <svg xmlns="http://www.w3.org/2000/svg"width="20"height="20"fill="none"stroke="currentColor"stroke-width="2"viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
    </a>
    <h1 class="font-bold text-slate-900 dark:text-white">Commander</h1>
  </div>
</div>

<main class="page-content px-4">

<?php if ($success): ?>
<!-- SUCCÈS -->
<div class="text-center py-12 fade-in-up">
  <div class="w-20 h-20 mx-auto rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mb-4">
    <svg class="w-10 h-10 text-green-600"fill="none"stroke="currentColor"stroke-width="2"viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
  </div>
  <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-2">Commande confirmée !</h2>
  <p class="text-sm text-slate-500 dark:text-slate-400 mb-1">Commande N° <strong><?= $orderId ?></strong></p>
  <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Redirection vers le suivi de votre commande...</p>
  <div class="inline-block w-6 h-6 border-2 border-primary-900 border-t-transparent rounded-full animate-spin"></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  Cart.clear();
  setTimeout(() => {
    window.location.href = '/pages/mes_commandes.php';
  }, 2000);
});
</script>

<?php else: ?>

<?php if ($error): ?>
<div class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-red-600 dark:text-red-400 text-sm">
  ️ <?= e($error) ?>
</div>
<?php endif; ?>

<!-- FORMULAIRE COMMANDE -->
<form method="POST"id="order-form"class="mt-4">
  <?= csrfField() ?>
  <input type="hidden"name="cart_data"id="cart-data-input">

  <!-- RÉCAPITULATIF PANIER -->
  <div class="mb-4">
    <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-2">Récapitulatif</h2>
    <div id="order-summary"class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3 text-sm text-slate-600 dark:text-slate-300">
      Chargement...
    </div>
  </div>

  <!-- ADRESSE & TÉLÉPHONE -->
  <div class="mb-4 space-y-3">
    <div>
      <label class="text-sm font-semibold text-slate-700 dark:text-slate-200 block mb-2">Adresse de livraison *</label>
      <textarea name="adresse"required rows="2"placeholder="Votre adresse complète (rue, quartier, ville...)"
        class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500 resize-none"></textarea>
    </div>
    <div>
      <label class="text-sm font-semibold text-slate-700 dark:text-slate-200 block mb-2">Numéro de téléphone *</label>
      <input type="tel"name="telephone"required placeholder="Ex: 77 123 45 67"value="<?= e($user['telephone'] ??'') ?>"
        class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500">
    </div>
  </div>

  <!-- DÉTAILS PRODUITS -->
  <div id="product-details-section" class="mb-4">
    <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-2">Détails produits (optionnel)</h2>
    <div id="product-details-inputs" class="space-y-2"></div>
  </div>

  <!-- MODE PAIEMENT -->
  <div class="mb-5 relative">
    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200 block mb-2">Mode de paiement *</label>
    <div class="space-y-2.5">
      <label class="flex items-center justify-between bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50/20 dark:has-[:checked]:bg-primary-950/30 rounded-2xl p-3.5 cursor-pointer transition-all shadow-2xs">
        <div class="flex items-center gap-3">
          <input type="radio" name="mode_paiement" value="wave" checked class="accent-primary-900 w-4 h-4" onchange="togglePaymentInfo(this.value)">
          <span class="text-sm font-bold text-slate-800 dark:text-slate-100">Wave</span>
        </div>
        <img src="/assets/wave-logo.png" alt="Wave" class="h-6 object-contain">
      </label>
    </div>

    <!-- INFO PAIEMENT DYNAMIQUE -->
    <div id="payment-info" class="mt-3 bg-slate-50 dark:bg-slate-800/80 rounded-2xl p-4 border border-slate-200 dark:border-slate-700 fade-in-up">
      <div class="flex items-center justify-between mb-2.5">
        <span class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wide">Paiement Wave</span>
        <span class="inline-flex items-center gap-1.5 bg-sky-50 dark:bg-sky-950/50 border border-sky-200 dark:border-sky-800 text-sky-700 dark:text-sky-300 text-[11px] font-semibold px-2 py-0.5 rounded-full">
          <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
          Direct
        </span>
      </div>

      <!-- Bouton direct Wave -->
      <a id="payment-direct-btn" href="<?= e($waveCfg['lien_paiement']) ?>" target="_blank" rel="noopener noreferrer" 
         class="w-full bg-[#1da1f2] hover:bg-[#1a94df] text-white font-bold p-3 rounded-2xl flex items-center justify-between transition-all active:scale-[0.98] shadow-md hover:shadow-lg group">
        <div class="flex items-center gap-2.5">
          <img id="payment-direct-logo" src="<?= e($waveCfg['logo']) ?>" alt="Wave" class="h-6 object-contain bg-white rounded-lg p-0.5">
          <div class="text-left">
            <span id="payment-direct-title" class="block text-xs sm:text-sm font-bold leading-tight">Payer avec Wave</span>
            <span id="payment-direct-desc" class="text-[10px] text-white/80">Ouvre directement votre application Wave</span>
          </div>
        </div>
        <span class="text-[11px] font-bold bg-white/20 px-2.5 py-1 rounded-xl flex items-center gap-1 group-hover:translate-x-0.5 transition-transform">
          Ouvrir Wave →
        </span>
      </a>
      <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2.5 leading-relaxed">
        Cliquez sur le bouton pour régler sur Wave, puis confirmez ci-dessous pour finaliser votre commande.
      </p>
    </div>
  </div>

  <button type="submit"class="w-full bg-primary-900 text-white rounded-xl py-3.5 font-semibold text-sm mb-3 shadow-sm hover:shadow-md active:shadow-md transition-all">
    Confirmer la commande
  </button>
  <a href="/pages/panier.php"class="block text-center text-sm text-slate-400 dark:text-slate-500">Retour au panier</a>

  <!-- Spacer massif pour le bas de page -->
  <div style="height: 120px; width: 100%; display: block;"></div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const items = Cart.get();
  const input = document.getElementById('cart-data-input');
  const summary = document.getElementById('order-summary');
  const detailsInputs = document.getElementById('product-details-inputs');
  const detailsSection = document.getElementById('product-details-section');

  input.value = JSON.stringify(items);

  if (items.length === 0) {
    summary.innerHTML = '<p class="text-center text-slate-400">Panier vide</p>';
    detailsSection.style.display = 'none';
    return;
  }

  detailsSection.style.display = 'block';

  summary.innerHTML = items.map(i =>
    `<div class="flex justify-between py-1">
      <span class="truncate flex-1">${i.nom} × ${i.qty}</span>
      <span class="font-medium ml-2">${(i.prix * i.qty).toLocaleString('fr-SN')} F</span>
    </div>`
  ).join('') + `<div class="border-t border-slate-200 dark:border-slate-700 mt-2 pt-2 flex justify-between font-bold">
    <span>Total</span>
    <span class="text-primary-900 dark:text-blue-400">${Cart.total().toLocaleString('fr-SN')}FCFA</span>
  </div>`;

  // Generate detail inputs
  detailsInputs.innerHTML = items.map(i => `
    <div class="bg-white dark:bg-slate-800 rounded-xl p-3 border border-slate-200 dark:border-slate-700">
      <p class="text-sm font-medium text-slate-800 dark:text-slate-100 mb-1">${i.nom}</p>
      <input type="text" 
        name="details_${i.id}" 
        value="${i.details || ''}"
        placeholder="Taille, marque, couleur..." 
        class="w-full text-sm bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500"
      >
    </div>
  `).join('');
});

function togglePaymentInfo(val) {
  const info = document.getElementById('payment-info');
  if (info) {
    info.style.display = (val === 'wave') ? 'block' : 'none';
  }
}
</script>

<?php endif; ?>
</main>

<?php require_once __DIR__ .'/../includes/footer.php'; ?>
