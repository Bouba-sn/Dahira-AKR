<?php
// pages/commande.php
$pageTitle = 'Commander';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$user = getCurrentUser();
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token invalide. Veuillez réessayer.';
    } else {
        $adresse     = sanitize($_POST['adresse'] ?? '');
        $telephone   = sanitize($_POST['telephone'] ?? '');
        $paiement    = sanitize($_POST['mode_paiement'] ?? 'wave');
        $cartJson    = $_POST['cart_data'] ?? '[]';
        $cartItems   = json_decode($cartJson, true);

        if (empty($cartItems)) {
            $error = 'Votre panier est vide.';
        } elseif (empty($adresse) || empty($telephone)) {
            $error = 'Veuillez saisir votre adresse de livraison et votre numéro de téléphone.';
        } else {
            $pdo = db();
            $total = array_sum(array_map(fn($i) => $i['prix'] * $i['qty'], $cartItems));

            try {
                $pdo->beginTransaction();

                // Créer la commande
                $stmt = $pdo->prepare("INSERT INTO commandes (user_id, total, statut, mode_paiement, adresse_livraison, telephone_client) VALUES (?, ?, 'en_attente', ?, ?, ?)");
                $stmt->execute([$user['id'], $total, $paiement, $adresse, $telephone]);
                $commandeId = $pdo->lastInsertId();

                // Ajouter les détails
                $stmtD = $pdo->prepare("INSERT INTO commande_details (commande_id, produit_id, quantite, prix_unitaire) VALUES (?, ?, ?, ?)");
                foreach ($cartItems as $item) {
                    $stmtD->execute([$commandeId, $item['id'], $item['qty'], $item['prix']]);
                    // Décrémenter le stock
                    $pdo->prepare("UPDATE produits SET stock = stock - ? WHERE id = ? AND stock >= ?")->execute([$item['qty'], $item['id'], $item['qty']]);
                }

                $pdo->commit();
                $success = true;
                $orderId = $commandeId;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Erreur lors de la commande. Veuillez réessayer.';
            }
        }
    }
}
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 px-4">
    <div class="flex items-center h-14 gap-3">
        <a href="/pages/panier.php" class="w-8 h-8 flex items-center justify-center text-slate-600 dark:text-slate-300">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h1 class="font-bold text-slate-900 dark:text-white">Commander</h1>
    </div>
</div>

<main class="page-content px-4">

<?php if ($success): ?>
<!-- SUCCÈS -->
<div class="text-center py-12 fade-in-up">
    <div class="w-20 h-20 mx-auto rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mb-4">
        <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-2">Commande confirmée ! 🎉</h2>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-1">Commande N° <strong><?= $orderId ?></strong></p>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Redirection vers le suivi de votre commande...</p>
    <div class="inline-block w-6 h-6 border-2 border-primary-900 border-t-transparent rounded-full animate-spin"></div>
</div>
<script>
// Attendre que footer.php charge l'objet Cart
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
    ⚠️ <?= e($error) ?>
</div>
<?php endif; ?>

<!-- FORMULAIRE COMMANDE -->
<form method="POST" id="order-form" class="mt-4">
    <?= csrfField() ?>
    <input type="hidden" name="cart_data" id="cart-data-input">

    <!-- RÉCAPITULATIF PANIER -->
    <div class="mb-4">
        <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-2">Récapitulatif</h2>
        <div id="order-summary" class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3 text-sm text-slate-600 dark:text-slate-300">
            Chargement...
        </div>
    </div>

    <!-- ADRESSE & TÉLÉPHONE -->
    <div class="mb-4 space-y-3">
        <div>
            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200 block mb-2">📍 Adresse de livraison *</label>
            <textarea name="adresse" required rows="2" placeholder="Votre adresse complète (rue, quartier, ville...)"
                class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500 resize-none"></textarea>
        </div>
        <div>
            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200 block mb-2">📱 Numéro de téléphone *</label>
            <input type="tel" name="telephone" required placeholder="Ex: 77 123 45 67" value="<?= e($user['telephone'] ?? '') ?>"
                class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500">
        </div>
    </div>

    <!-- MODE PAIEMENT -->
    <div class="mb-5 relative">
        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200 block mb-2">💳 Mode de paiement *</label>
        <div class="space-y-2">
            <label class="flex items-center gap-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3 cursor-pointer">
                <input type="radio" name="mode_paiement" value="wave" checked class="accent-primary-900" onchange="togglePaymentInfo(this.value)">
                <span class="text-sm text-slate-700 dark:text-slate-200 flex items-center gap-2">📱 Wave</span>
            </label>
            <label class="flex items-center gap-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3 cursor-pointer">
                <input type="radio" name="mode_paiement" value="orange_money" class="accent-primary-900" onchange="togglePaymentInfo(this.value)">
                <span class="text-sm text-slate-700 dark:text-slate-200 flex items-center gap-2">🟠 Orange Money</span>
            </label>
        </div>

        <!-- INFO PAIEMENT DYNAMIQUE -->
        <div id="payment-info" class="mt-3 bg-slate-50 dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700 fade-in-up">
            <p class="text-xs font-semibold text-slate-500 mb-2 uppercase tracking-wide">Veuillez transférer le montant au :</p>
            <div class="flex items-center justify-between bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg p-2 mb-2">
                <div>
                    <p class="font-mono font-bold text-lg text-slate-800 dark:text-slate-100 tracking-wider" id="payment-number">77 000 00 00</p>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 font-medium uppercase" id="payment-name">Caisse Dahira</p>
                </div>
                <button type="button" onclick="copyPaymentNumber()" class="bg-primary-50 text-primary-900 hover:bg-primary-100 dark:bg-slate-800 dark:text-blue-400 border border-primary-200 dark:border-slate-600 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors flex items-center gap-1 active:scale-95 shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    Copier
                </button>
            </div>
            <p class="text-[10px] text-slate-400 leading-tight">Envoyez l'argent via le mode sélectionné depuis le numéro entré ci-dessus. L'administrateur confirmera votre commande sous peu (Livraison de 2-5 jours).</p>
        </div>
    </div>

    <button type="submit" class="w-full bg-primary-900 text-white rounded-xl py-3.5 font-semibold text-sm mb-3">
        Confirmer la commande
    </button>
    <a href="/pages/panier.php" class="block text-center text-sm text-slate-400 dark:text-slate-500">Retour au panier</a>

    <!-- Spacer massif pour le bas de page -->
    <div style="height: 120px; width: 100%; display: block;"></div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const items = Cart.get();
    const input = document.getElementById('cart-data-input');
    const summary = document.getElementById('order-summary');

    input.value = JSON.stringify(items);

    if (items.length === 0) {
        summary.innerHTML = '<p class="text-center text-slate-400">Panier vide</p>';
        return;
    }

    summary.innerHTML = items.map(i =>
        `<div class="flex justify-between py-1">
            <span class="truncate flex-1">${i.nom} × ${i.qty}</span>
            <span class="font-medium ml-2">${(i.prix * i.qty).toLocaleString('fr-SN')} F</span>
        </div>`
    ).join('') + `<div class="border-t border-slate-200 dark:border-slate-700 mt-2 pt-2 flex justify-between font-bold">
        <span>Total</span>
        <span class="text-primary-900 dark:text-blue-400">${Cart.total().toLocaleString('fr-SN')} FCFA</span>
    </div>`;
});

function togglePaymentInfo(val) {
    const info = document.getElementById('payment-info');
    const num = document.getElementById('payment-number');
    const name = document.getElementById('payment-name');
    
    // Valeurs par défaut, idéalement à remplacer par vos vrais numéros Dahira
    const accounts = {
        'wave': { num: '77 123 45 67', name: 'Responsable Dahira (Wave)' },
        'orange_money': { num: '77 987 65 43', name: 'Responsable Dahira (Orange)' }
    };

    if (val === 'wave' || val === 'orange_money') {
        info.style.display = 'block';
        num.textContent = accounts[val].num;
        name.textContent = accounts[val].name;
    } else {
        info.style.display = 'none';
    }
}

function copyPaymentNumber() {
    const numStr = document.getElementById('payment-number').textContent;
    const plainNum = numStr.replace(/\s+/g, '');
    
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(plainNum).then(() => {
            showToast('✅ Numéro copié !');
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement("textarea");
        textArea.value = plainNum;
        textArea.style.position = "fixed";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            showToast('✅ Numéro copié !');
        } catch (err) {
            console.error('Copy failed', err);
        }
        document.body.removeChild(textArea);
    }
}
</script>

<?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
