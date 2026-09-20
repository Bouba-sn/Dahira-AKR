<?php
// pages/panier.php
$pageTitle = 'Panier';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 px-4" style="height: calc(3.5rem + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px);">
    <div class="flex items-center h-14 gap-3">
        <a href="/pages/tidiany-way.php" class="w-8 h-8 flex items-center justify-center text-slate-600 dark:text-slate-300">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h1 class="font-bold text-slate-900 dark:text-white flex-1">Mon panier</h1>
        <a href="/pages/mes_commandes.php" class="flex flex-col items-center justify-center w-12 text-slate-600 dark:text-slate-300 active:scale-95 transition-transform" title="Mes Commandes">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 8v13H3V8M1 3h22v5H1zM10 12h4"/></svg>
            <span class="text-[8px] font-bold mt-1 uppercase tracking-tight">Historique</span>
        </a>
    </div>
</div>

<main class="pb-40">
    <div id="cart-empty" class="text-center py-16 px-4" style="display:none;">
        <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 mx-auto flex items-center justify-center mb-3">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
        </div>
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
            
            <div class="border-t border-slate-100 dark:border-slate-700 pt-2 mt-2 flex justify-between">
                <span class="font-bold text-slate-800 dark:text-slate-100">Total</span>
                <span id="cart-total-display" class="font-bold text-lg text-primary-900 dark:text-blue-400">0 FCFA</span>
            </div>
        </div>
    </div>
</main>

<!-- BOUTON COMMANDER -->
<div id="checkout-bar" class="fixed bottom-[4.5rem] left-1/2 -translate-x-1/2 w-full max-w-[430px] z-40 px-4 bg-white/95 dark:bg-slate-900/95 backdrop-blur border-t border-slate-200 dark:border-slate-800 py-3 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]" style="display:none; margin-bottom: env(safe-area-inset-bottom);">
    <div class="flex justify-between items-center mb-3 px-1">
        <span class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">Total Panier</span>
        <span id="checkout-total" class="font-bold text-lg text-primary-900 dark:text-blue-400">0 FCFA</span>
    </div>
    <a href="/pages/commande.php" class="block w-full bg-primary-900 hover:bg-primary-800 text-white text-center rounded-xl py-3.5 font-semibold text-sm transition-colors shadow-sm hover:shadow-md active:shadow-md transition-all">
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
            <div class="w-14 h-14 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0 text-slate-400">
                ${item.image ? `<img src="/assets/uploads/${item.image}" class="w-full h-full object-cover rounded-xl" onerror="this.onerror=null; this.src='/assets/placeholder.jpg'">` : '<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>'}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate">${item.nom}</p>
                <p class="text-xs text-primary-900 dark:text-blue-400 font-bold">${item.prix.toLocaleString('fr-SN')} FCFA</p>
                <input type="text" 
                    placeholder="Détails (taille, marque...)" 
                    value="${item.details || ''}"
                    onchange="updateDetails(${item.id}, this.value)"
                    class="w-full mt-1 text-xs bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-2 py-1 text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none"
                >
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

function updateDetails(id, details) {
    let items = Cart.get();
    const idx = items.findIndex(i => i.id === id);
    if (idx > -1) {
        items[idx].details = details;
        Cart.save(items);
    }
}

document.addEventListener('DOMContentLoaded', renderCart);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
