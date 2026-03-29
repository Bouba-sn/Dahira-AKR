<?php
// pages/panier.php
$pageTitle = 'Panier';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 px-4">
    <div class="flex items-center h-14 gap-3">
        <a href="/pages/tidiany-way.php" class="w-8 h-8 flex items-center justify-center text-slate-600 dark:text-slate-300">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h1 class="font-bold text-slate-900 dark:text-white">Mon panier 🛒</h1>
    </div>
</div>

<main class="pb-36">
    <div id="cart-empty" class="text-center py-16 px-4" style="display:none;">
        <span class="text-5xl block mb-3">🛒</span>
        <h2 class="text-lg font-semibold text-slate-700 dark:text-slate-200 mb-2">Votre panier est vide</h2>
        <p class="text-sm text-slate-400 mb-5">Découvrez nos produits islamiques</p>
        <a href="/pages/tidiany-way.php" class="inline-block bg-primary-900 text-white px-6 py-2.5 rounded-xl text-sm font-medium">
            Voir la boutique
        </a>
    </div>

    <div id="cart-items" class="px-4 mt-4 space-y-2"></div>

    <!-- TOTAL & COMMANDER -->
    <div id="cart-summary" class="px-4 mt-4" style="display:none;">
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-4">
            <div class="flex justify-between text-sm mb-2">
                <span class="text-slate-500 dark:text-slate-400">Sous-total</span>
                <span id="cart-subtotal" class="font-medium text-slate-800 dark:text-slate-100">0 FCFA</span>
            </div>
            <div class="flex justify-between text-sm mb-2">
                <span class="text-slate-500 dark:text-slate-400">Livraison</span>
                <span class="text-green-600 dark:text-green-400 font-medium">Gratuite</span>
            </div>
            <div class="border-t border-slate-100 dark:border-slate-700 pt-2 mt-2 flex justify-between">
                <span class="font-bold text-slate-800 dark:text-slate-100">Total</span>
                <span id="cart-total-display" class="font-bold text-lg text-primary-900 dark:text-blue-400">0 FCFA</span>
            </div>
        </div>
    </div>
</main>

<!-- BOUTON COMMANDER -->
<div id="checkout-bar" class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] z-50 px-4 pb-safe bg-white/95 dark:bg-slate-900/95 backdrop-blur border-t border-slate-200 dark:border-slate-800 pt-3 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]" style="display:none; padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
    <div class="flex justify-between items-center mb-3 px-1">
        <span class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">🛒 Panier</span>
        <span id="checkout-total" class="font-bold text-lg text-primary-900 dark:text-blue-400">0 FCFA</span>
    </div>
    <a href="/pages/commande.php" class="block w-full bg-primary-900 hover:bg-primary-800 text-white text-center rounded-xl py-3.5 font-semibold text-sm transition-colors">
        Valider la commande →
    </a>
</div>

<script>
function renderCart() {
    const items = Cart.get();
    const container = document.getElementById('cart-items');
    const empty = document.getElementById('cart-empty');
    const summary = document.getElementById('cart-summary');
    const bar = document.getElementById('checkout-bar');

    if (items.length === 0) {
        empty.style.display = 'block';
        container.innerHTML = '';
        summary.style.display = 'none';
        bar.style.display = 'none';
        return;
    }

    empty.style.display = 'none';
    summary.style.display = 'block';
    bar.style.display = 'block';

    container.innerHTML = items.map(item => `
        <div class="flex items-center gap-3 bg-white dark:bg-slate-800 rounded-xl p-3 border border-slate-100 dark:border-slate-700">
            <div class="w-14 h-14 rounded-xl bg-amber-50 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                ${item.image ? `<img src="/assets/uploads/${item.image}" class="w-full h-full object-cover rounded-xl">` : '<span class="text-2xl">📿</span>'}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate">${item.nom}</p>
                <p class="text-xs text-primary-900 dark:text-blue-400 font-bold">${item.prix.toLocaleString('fr-SN')} FCFA</p>
                <div class="flex items-center gap-2 mt-1.5">
                    <button onclick="updateQty(${item.id}, ${item.qty - 1})" class="w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-sm font-bold text-slate-600 dark:text-slate-300">−</button>
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">${item.qty}</span>
                    <button onclick="updateQty(${item.id}, ${item.qty + 1})" class="w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-sm font-bold text-slate-600 dark:text-slate-300">+</button>
                </div>
            </div>
            <div class="text-right flex-shrink-0">
                <p class="text-sm font-bold text-slate-800 dark:text-slate-100">${(item.prix * item.qty).toLocaleString('fr-SN')} F</p>
                <button onclick="Cart.remove(${item.id}); renderCart();" class="mt-1 text-red-400 text-xs">Retirer</button>
            </div>
        </div>
    `).join('');

    const total = Cart.total();
    document.getElementById('cart-subtotal').textContent = total.toLocaleString('fr-SN') + ' FCFA';
    document.getElementById('cart-total-display').textContent = total.toLocaleString('fr-SN') + ' FCFA';
    document.getElementById('checkout-total').textContent = total.toLocaleString('fr-SN') + ' FCFA';
}

function updateQty(id, newQty) {
    let items = Cart.get();
    if (newQty <= 0) {
        items = items.filter(i => i.id !== id);
    } else {
        const idx = items.findIndex(i => i.id === id);
        if (idx > -1) items[idx].qty = newQty;
    }
    Cart.save(items);
    Cart.updateBadge();
    renderCart();
}

document.addEventListener('DOMContentLoaded', renderCart);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
