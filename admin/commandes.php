<?php
// admin/commandes.php
$pageTitle = 'Commandes & Ventes Directes';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$msg = '';
$errorMsg = '';

$statutLabels = ['en_attente'=>'En attente','confirmee'=>'Confirmée','expediee'=>'Expédiée','livree'=>'Livrée','annulee'=>'Annulée'];
$allowed = array_keys($statutLabels);

// 1. Enregistrement d'une vente directe pour un client sans application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nouvelle_vente_directe' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $nomClient = trim($_POST['nom_client'] ?? '');
    $telClient = trim($_POST['telephone_client'] ?? '');
    $modePaiement = in_array($_POST['mode_paiement'] ?? '', ['especes', 'wave']) ? $_POST['mode_paiement'] : 'especes';
    $rawItems = $_POST['items'] ?? [];

    if (empty($nomClient)) {
        $errorMsg = "Veuillez renseigner le nom ou prénom du client.";
    } elseif (empty($rawItems) || !is_array($rawItems)) {
        $errorMsg = "Veuillez sélectionner au moins un produit à vendre.";
    } else {
        $total = 0;
        $orderLines = [];

        // Charger tous les produits actifs pour vérification
        $stmtP = $pdo->query("SELECT id, nom, prix, stock FROM produits WHERE actif = 1");
        $prodsById = [];
        while ($p = $stmtP->fetch()) {
            $prodsById[$p['id']] = $p;
        }

        foreach ($rawItems as $item) {
            $pId = (int)($item['produit_id'] ?? 0);
            $qty = (int)($item['quantite'] ?? 0);
            if ($pId > 0 && $qty > 0 && isset($prodsById[$pId])) {
                $p = $prodsById[$pId];
                if ($p['stock'] < $qty) {
                    $errorMsg = "Stock insuffisant pour : {$p['nom']} (Stock disponible : {$p['stock']}).";
                    break;
                }
                $sousTotal = $p['prix'] * $qty;
                $total += $sousTotal;
                $orderLines[] = [
                    'produit_id' => $pId,
                    'quantite' => $qty,
                    'prix_unitaire' => $p['prix'],
                    'details' => 'Vente directe au guichet'
                ];
            }
        }

        if (empty($errorMsg)) {
            if (empty($orderLines)) {
                $errorMsg = "Veuillez ajouter au moins un produit avec une quantité supérieure à 0.";
            } else {
                try {
                    $pdo->beginTransaction();

                    // Insérer la commande directe (statut 'livree' d'office)
                    $insCmd = $pdo->prepare("INSERT INTO commandes (user_id, nom_client, total, statut, mode_paiement, adresse_livraison, telephone_client) VALUES (?, ?, ?, 'livree', ?, 'Vente directe au guichet (Client sans application)', ?)");
                    $insCmd->execute([$_SESSION['user_id'] ?? null, $nomClient, $total, $modePaiement, $telClient]);
                    $cmdId = $pdo->lastInsertId();

                    // Insérer les détails et décrémenter le stock
                    $insD = $pdo->prepare("INSERT INTO commande_details (commande_id, produit_id, quantite, prix_unitaire, details) VALUES (?, ?, ?, ?, ?)");
                    $updStock = $pdo->prepare("UPDATE produits SET stock = stock - ? WHERE id = ? AND stock >= ?");

                    foreach ($orderLines as $line) {
                        $insD->execute([$cmdId, $line['produit_id'], $line['quantite'], $line['prix_unitaire'], $line['details']]);
                        $updStock->execute([$line['quantite'], $line['produit_id'], $line['quantite']]);
                    }

                    $pdo->commit();
                    $msg = "Vente directe #{$cmdId} pour {$nomClient} enregistrée avec succès (" . number_format($total, 0, ',', ' ') . " FCFA).";
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $errorMsg = "Erreur lors de l'enregistrement de la vente : " . $e->getMessage();
                }
            }
        }
    }
}

// 2. Mettre à jour le statut d'une commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_statut' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
  $id = (int)($_POST['commande_id'] ?? 0);
  $statut = sanitize($_POST['statut'] ?? '');
  if ($id > 0 && in_array($statut, $allowed)) {
    $pdo->prepare("UPDATE commandes SET statut=? WHERE id=?")->execute([$statut, $id]);
    $msg = 'Statut de la commande mis à jour.';

    $uid = $pdo->prepare("SELECT user_id FROM commandes WHERE id=?");
    $uid->execute([$id]);
    $userIdCmd = $uid->fetchColumn();
    if ($userIdCmd) {
      addNotification($userIdCmd, 'Commande mise à jour', "Votre commande #$id est désormais : " . $statutLabels[$statut], '/pages/parametres.php');
    }
  }
}

// Filtre et liste des commandes
$filtre = $_GET['statut'] ?? '';
$where = $filtre ? "WHERE c.statut = ?" : "";
$params = $filtre ? [$filtre] : [];
$stmt = $pdo->prepare("SELECT c.*, COALESCE(NULLIF(c.nom_client, ''), u.nom) as client, u.email FROM commandes c LEFT JOIN utilisateurs u ON u.id=c.user_id $where ORDER BY c.date_commande DESC");
$stmt->execute($params);
$commandes = $stmt->fetchAll();

// Get order details for each commande
$detailsStmt = $pdo->prepare("SELECT cd.*, p.nom as produit_nom FROM commande_details cd JOIN produits p ON p.id = cd.produit_id WHERE cd.commande_id = ?");
$commandesDetails = [];
foreach ($commandes as $c) {
    $detailsStmt->execute([$c['id']]);
    $commandesDetails[$c['id']] = $detailsStmt->fetchAll();
}

// Produits actifs pour la modale de vente directe
$produitsDisponibles = $pdo->query("SELECT id, nom, prix, stock FROM produits WHERE actif = 1 AND stock > 0 ORDER BY nom ASC")->fetchAll();

$statutColors = ['en_attente'=>'bg-orange-100 text-orange-600','confirmee'=>'bg-blue-100 text-blue-600','expediee'=>'bg-purple-100 text-purple-600','livree'=>'bg-green-100 text-green-600','annulee'=>'bg-red-100 text-red-600'];
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-primary-900 text-white px-4" style="height: calc(3.5rem + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px);">
  <div class="flex items-center justify-between h-14 gap-2">
    <div class="flex items-center gap-2.5 min-w-0">
      <a href="/admin/dashboard.php" class="w-8 h-8 flex items-center justify-center text-white/70 hover:text-white rounded-lg transition-colors">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
      </a>
      <div class="min-w-0">
        <h1 class="font-bold text-sm truncate leading-tight">Commandes & Ventes</h1>
        <span class="text-[10px] opacity-70 block"><?= count($commandes) ?> commande(s)</span>
      </div>
    </div>

    <!-- Bouton Nouvelle Vente Directe (Client sans application) -->
    <button type="button" onclick="openVenteDirecteModal()" class="bg-white/15 hover:bg-white/25 active:scale-95 text-white font-bold text-xs py-2 px-3 rounded-xl flex items-center gap-1.5 transition-all shadow-xs shrink-0">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      <span>+ Vente Directe</span>
    </button>
  </div>
</div>

<main class="pb-32 bg-slate-50 dark:bg-slate-950 min-h-screen px-4 max-w-3xl mx-auto">

  <?php if ($msg): ?>
  <div class="flash-success-box mt-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 rounded-xl text-green-700 text-xs sm:text-sm flex items-center justify-between gap-2 shadow-xs">
      <span><?= e($msg) ?></span>
      <button type="button" onclick="dismissFlash(this.closest('.flash-success-box'))" class="text-green-600 hover:text-green-800 dark:hover:text-green-200 p-1 transition-colors" title="Fermer">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
  </div>
  <?php endif; ?>

  <?php if ($errorMsg): ?>
  <div class="flash-error-box mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 rounded-xl text-red-700 text-xs sm:text-sm flex items-center justify-between gap-2 shadow-xs">
      <span><?= e($errorMsg) ?></span>
      <button type="button" onclick="dismissFlash(this.closest('.flash-error-box'))" class="text-red-600 hover:text-red-800 dark:hover:text-red-200 p-1 transition-colors" title="Fermer">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
  </div>
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

  <!-- LISTE DES COMMANDES -->
  <div class="mt-3 space-y-3">
  <?php foreach ($commandes as $c): 
      $isVenteDirecte = !empty($c['nom_client']);
  ?>
  <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 overflow-hidden shadow-xs">
    <!-- Header commande -->
    <div class="px-4 py-3 flex items-start justify-between border-b border-slate-100 dark:border-slate-700 gap-2">
      <div>
        <div class="flex items-center gap-1.5 flex-wrap">
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Commande #<?= $c['id'] ?></p>
          <?php if ($isVenteDirecte): ?>
          <span class="text-[10px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300/80 px-2 py-0.2 rounded-full">Vente directe (Sans appli)</span>
          <?php endif; ?>
        </div>
        <p class="text-sm font-bold text-slate-800 dark:text-slate-100 mt-0.5"><?= e($c['client'] ?: 'Client au comptoir') ?></p>
        <?php if ($c['telephone_client']): ?>
        <p class="text-xs font-mono font-bold text-slate-600 dark:text-slate-300 mt-0.5 bg-slate-100 dark:bg-slate-700/60 inline-block px-1.5 py-0.5 rounded">
          <?= e($c['telephone_client']) ?>
        </p>
        <?php endif; ?>
      </div>
      <div class="text-right">
        <p class="text-base font-bold text-primary-900 dark:text-blue-400"><?= number_format($c['total'], 0, ',', ' ') ?> F</p>
        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $statutColors[$c['statut']] ?? '' ?>">
          <?= $statutLabels[$c['statut']] ?? $c['statut'] ?>
        </span>
      </div>
    </div>

    <!-- Détails (date, paiement) -->
    <div class="px-4 py-2 text-xs text-slate-400 flex items-center flex-wrap gap-1.5">
      <span><?= date('d/m/Y H:i', strtotime($c['date_commande'])) ?></span>
      <span>·</span>
      <?php if ($c['mode_paiement'] === 'wave'): ?>
      <span class="inline-flex items-center gap-1.5 bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 text-sky-700 dark:text-sky-300 text-[11px] font-semibold px-2 py-0.5 rounded-md">
        <img src="/assets/wave-icon.png" alt="Wave" class="w-3.5 h-3.5 rounded-full object-contain">
        Wave
      </span>
      <?php elseif ($c['mode_paiement'] === 'especes'): ?>
      <span class="inline-flex items-center gap-1 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 text-[11px] font-semibold px-2 py-0.5 rounded-md">
        <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg>
        Espèces (Guichet)
      </span>
      <?php else: ?>
      <span class="font-semibold text-slate-600 dark:text-slate-300"><?= ['livraison'=>'Paiement livraison'][$c['mode_paiement']] ?? e($c['mode_paiement']) ?></span>
      <?php endif; ?>
    </div>
    
    <!-- Produits commandés -->
    <div class="px-4 py-2 space-y-1 bg-slate-50/50 dark:bg-slate-900/30">
      <?php 
      $items = $commandesDetails[$c['id']] ?? [];
      foreach ($items as $item): 
      ?>
      <div class="flex justify-between text-xs">
        <span class="text-slate-700 dark:text-slate-300">
          <?= e($item['produit_nom']) ?> <span class="font-bold">× <?= $item['quantite'] ?></span>
          <?php if (!empty($item['details'])): ?>
          <span class="ml-1 text-slate-400 text-[11px]">(<?= e($item['details']) ?>)</span>
          <?php endif; ?>
        </span>
        <span class="font-bold text-slate-700 dark:text-slate-300"><?= number_format($item['prix_unitaire'] * $item['quantite'], 0, ',', ' ') ?> F</span>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($c['adresse_livraison']): ?>
    <div class="px-4 py-1.5 text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1">
       <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
       <span><?= e($c['adresse_livraison']) ?></span>
    </div>
    <?php endif; ?>
    
    <!-- Action statut -->
    <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700/60 flex flex-col gap-2">
      <?php if ($c['statut'] === 'en_attente' && $c['mode_paiement'] === 'wave'): ?>
      <!-- Bouton Validation Rapide Wave -->
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_statut">
        <input type="hidden" name="commande_id" value="<?= $c['id'] ?>">
        <input type="hidden" name="statut" value="confirmee">
        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-2 rounded-xl text-xs font-bold flex items-center justify-center gap-2 shadow-xs transition-colors" onclick="return confirm('Confirmer la bonne réception du paiement Wave ?')">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          <span>Confirmer le paiement Wave</span>
        </button>
      </form>
      <?php endif; ?>

      <form method="POST" class="flex items-center gap-2">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_statut">
        <input type="hidden" name="commande_id" value="<?= $c['id'] ?>">
        <select name="statut" class="flex-1 border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-1.5 text-xs text-slate-800 dark:text-slate-100 focus:outline-none">
          <?php foreach ($statutLabels as $v => $l): ?>
          <option value="<?= $v ?>" <?= $c['statut'] === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 px-3 py-1.5 rounded-xl text-xs font-semibold hover:bg-slate-300 transition-colors">
          Modifier
        </button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if (empty($commandes)): ?>
  <div class="text-center py-12 text-slate-400">
    <p class="text-sm">Aucune commande trouvée</p>
  </div>
  <?php endif; ?>
  </div>
  
  <div class="h-24"></div>
</main>

<!-- ========================================================================= -->
<!-- MODAL ENREGISTRER UNE VENTE DIRECTE (CLIENT SANS APPLICATION) -->
<!-- ========================================================================= -->
<div id="modal-vente-directe" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-xs hidden items-center justify-center p-3 sm:p-4 overflow-y-auto">
  <div class="relative bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-6 max-w-lg w-full shadow-2xl my-auto animate-fade-in border border-slate-100 dark:border-slate-800">
    
    <!-- En-tête -->
    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
      <div>
        <h3 class="font-bold text-sm sm:text-base text-slate-900 dark:text-white">Nouvelle Vente Directe</h3>
        <p class="text-[11px] text-slate-400">Client au comptoir ne possédant pas l'application</p>
      </div>
      <button type="button" onclick="closeVenteDirecteModal()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <form method="POST" action="/admin/commandes.php" class="space-y-4 mt-4" id="form-vente-directe">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="nouvelle_vente_directe">

      <!-- Nom & Téléphone du client -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nom du client *</label>
          <input type="text" name="nom_client" required placeholder="Ex: Moussa Diop" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-primary-500 outline-none">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Téléphone (Optionnel)</label>
          <input type="tel" name="telephone_client" placeholder="Ex: 77 000 00 00" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-primary-500 outline-none">
        </div>
      </div>

      <!-- Mode de règlement -->
      <div>
        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Mode de règlement *</label>
        <div class="grid grid-cols-2 gap-2">
          <label class="flex items-center gap-2 p-2.5 rounded-xl border-2 border-primary-900 bg-primary-50/20 dark:bg-primary-950/30 cursor-pointer text-xs font-bold text-slate-900 dark:text-white" id="label-pay-especes">
            <input type="radio" name="mode_paiement" value="especes" checked class="accent-primary-900" onchange="updatePayRadio(this)">
            <span>Espèces (Sur place)</span>
          </label>
          <label class="flex items-center gap-2 p-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 cursor-pointer text-xs font-bold text-slate-900 dark:text-white" id="label-pay-wave">
            <input type="radio" name="mode_paiement" value="wave" class="accent-[#1da1f2]" onchange="updatePayRadio(this)">
            <span>Wave</span>
          </label>
        </div>
      </div>

      <!-- Sélection des Produits -->
      <div>
        <div class="flex items-center justify-between mb-1.5">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Articles achetés *</label>
          <button type="button" onclick="ajouterLigneProduit()" class="text-xs text-primary-700 dark:text-blue-400 font-bold hover:underline">
            + Ajouter un article
          </button>
        </div>

        <div id="container-produits" class="space-y-2 max-h-56 overflow-y-auto pr-1">
          <!-- Les lignes de produits s'insèrent ici -->
        </div>
      </div>

      <!-- Total & Actions -->
      <div class="p-3.5 bg-slate-50 dark:bg-slate-800/80 rounded-2xl border border-slate-100 dark:border-slate-700 flex items-center justify-between">
        <div>
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Total à encaisser :</span>
          <span id="total-vente-display" class="text-lg font-extrabold text-primary-900 dark:text-white">0 FCFA</span>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" onclick="closeVenteDirecteModal()" class="px-3 py-2 rounded-xl bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold hover:bg-slate-300 transition-colors">
            Annuler
          </button>
          <button type="submit" class="px-4 py-2 rounded-xl bg-primary-900 hover:bg-primary-800 text-white text-xs font-bold shadow-md hover:shadow-lg active:scale-95 transition-all">
            Enregistrer la vente
          </button>
        </div>
      </div>

    </form>
  </div>
</div>

<script>
const catalogueProduits = <?= json_encode($produitsDisponibles) ?>;

function openVenteDirecteModal() {
  const modal = document.getElementById('modal-vente-directe');
  if (!modal) return;
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  document.body.style.overflow = 'hidden';

  const container = document.getElementById('container-produits');
  if (container && container.children.length === 0) {
    ajouterLigneProduit();
  }
  calculerTotalVente();
}

function closeVenteDirecteModal() {
  const modal = document.getElementById('modal-vente-directe');
  if (!modal) return;
  modal.classList.add('hidden');
  modal.classList.remove('flex');
  document.body.style.overflow = '';
}

function updatePayRadio(radio) {
  const labelEsp = document.getElementById('label-pay-especes');
  const labelWave = document.getElementById('label-pay-wave');
  if (radio.value === 'especes') {
    labelEsp.className = 'flex items-center gap-2 p-2.5 rounded-xl border-2 border-primary-900 bg-primary-50/20 dark:bg-primary-950/30 cursor-pointer text-xs font-bold text-slate-900 dark:text-white';
    labelWave.className = 'flex items-center gap-2 p-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 cursor-pointer text-xs font-bold text-slate-900 dark:text-white';
  } else {
    labelEsp.className = 'flex items-center gap-2 p-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 cursor-pointer text-xs font-bold text-slate-900 dark:text-white';
    labelWave.className = 'flex items-center gap-2 p-2.5 rounded-xl border-2 border-primary-900 bg-primary-50/20 dark:bg-primary-950/30 cursor-pointer text-xs font-bold text-slate-900 dark:text-white';
  }
}

let indexLigne = 0;
function ajouterLigneProduit() {
  const container = document.getElementById('container-produits');
  if (!container) return;

  const idx = indexLigne++;
  const div = document.createElement('div');
  div.className = 'flex items-center gap-2 bg-slate-50 dark:bg-slate-800/50 p-2 rounded-xl border border-slate-200/80 dark:border-slate-700/80 text-xs ligne-produit';
  div.id = 'ligne-produit-' + idx;

  let optionsHtml = '<option value="">-- Sélectionner un article --</option>';
  catalogueProduits.forEach(p => {
    optionsHtml += `<option value="${p.id}" data-prix="${p.prix}" data-stock="${p.stock}">${p.nom} (${Number(p.prix).toLocaleString()} F - Stock: ${p.stock})</option>`;
  });

  div.innerHTML = `
    <div class="flex-1 min-w-0">
      <select name="items[${idx}][produit_id]" required class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1.5 text-xs text-slate-900 dark:text-white outline-none select-prod" onchange="onSelectProduit(this, ${idx})">
        ${optionsHtml}
      </select>
    </div>
    <div class="w-20">
      <input type="number" name="items[${idx}][quantite]" value="1" min="1" max="999" required class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1.5 text-xs text-center font-bold text-slate-900 dark:text-white outline-none input-qty" onchange="calculerTotalVente()" onkeyup="calculerTotalVente()">
    </div>
    <div class="w-24 text-right">
      <span class="font-bold text-slate-800 dark:text-slate-200 text-xs sous-total-display">0 F</span>
    </div>
    <button type="button" onclick="supprimerLigneProduit(${idx})" class="w-6 h-6 rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-950/50 flex items-center justify-center font-bold text-sm transition-colors" title="Supprimer">
      ✕
    </button>
  `;

  container.appendChild(div);
}

function onSelectProduit(selectElem, idx) {
  const selectedOpt = selectElem.selectedOptions[0];
  const stock = selectedOpt ? Number(selectedOpt.getAttribute('data-stock') || 0) : 0;
  const ligne = document.getElementById('ligne-produit-' + idx);
  if (ligne) {
    const qtyInput = ligne.querySelector('.input-qty');
    if (qtyInput && stock > 0) {
      qtyInput.max = stock;
    }
  }
  calculerTotalVente();
}

function supprimerLigneProduit(idx) {
  const ligne = document.getElementById('ligne-produit-' + idx);
  if (ligne) ligne.remove();
  calculerTotalVente();
}

function calculerTotalVente() {
  const container = document.getElementById('container-produits');
  if (!container) return;

  let total = 0;
  const lignes = container.querySelectorAll('.ligne-produit');
  lignes.forEach(l => {
    const select = l.querySelector('.select-prod');
    const qtyInput = l.querySelector('.input-qty');
    const sousTotalSpan = l.querySelector('.sous-total-display');

    if (select && qtyInput && sousTotalSpan) {
      const opt = select.selectedOptions[0];
      const prix = opt ? Number(opt.getAttribute('data-prix') || 0) : 0;
      const qty = Number(qtyInput.value || 0);
      const st = prix * qty;
      total += st;
      sousTotalSpan.textContent = st.toLocaleString() + ' F';
    }
  });

  const display = document.getElementById('total-vente-display');
  if (display) {
    display.textContent = total.toLocaleString() + ' FCFA';
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
