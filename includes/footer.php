<?php // includes/footer.php ?>

<?php require_once __DIR__ . '/navbar-bottom.php'; ?>

</div><!-- /#app -->

<script>
// ============================================
// UTILITAIRES GLOBAUX
// ============================================

// Toast notification
function showToast(msg, duration = 2500) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), duration);
}

// Dark mode toggle
function toggleDarkMode() {
    const html = document.documentElement;
    const isDark = html.classList.toggle('dark');
    document.cookie = `dark_mode=${isDark ? '1' : '0'};path=/;max-age=31536000`;
    return isDark;
}

// Lazy loading images
const imageObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const img = entry.target;
            if (img.dataset.src) {
                img.src = img.dataset.src;
                img.onload = () => img.classList.add('loaded');
                img.removeAttribute('data-src');
                imageObserver.unobserve(img);
            }
        }
    });
}, { rootMargin: '50px' });

document.querySelectorAll('img[data-src]').forEach(img => imageObserver.observe(img));

// Panier (localStorage)
const Cart = {
    get() { return JSON.parse(localStorage.getItem('dahira_cart') || '[]'); },
    save(items) { localStorage.setItem('dahira_cart', JSON.stringify(items)); },
    add(product) {
        let items = this.get();
        const idx = items.findIndex(i => i.id === product.id);
        if (idx > -1) items[idx].qty++;
        else items.push({ ...product, qty: 1 });
        this.save(items);
        this.updateBadge();
        showToast('✓ Ajouté au panier');
    },
    remove(id) {
        const items = this.get().filter(i => i.id !== id);
        this.save(items);
        this.updateBadge();
    },
    total() { return this.get().reduce((s, i) => s + i.prix * i.qty, 0); },
    count() { return this.get().reduce((s, i) => s + i.qty, 0); },
    clear() { localStorage.removeItem('dahira_cart'); this.updateBadge(); },
    updateBadge() {
        const badge = document.getElementById('cart-badge');
        const count = this.count();
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }
    }
};

// Init badge
document.addEventListener('DOMContentLoaded', () => Cart.updateBadge());

// PWA Install prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    const btn = document.getElementById('pwa-install-btn');
    if (btn) btn.style.display = 'flex';
});

function installPWA() {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then(choice => {
        if (choice.outcome === 'accepted') showToast('🎉 Application installée !');
        deferredPrompt = null;
    });
}

// Format price
function formatPrice(price) {
    return new Intl.NumberFormat('fr-SN', { style: 'currency', currency: 'XOF', minimumFractionDigits: 0 }).format(price);
}
</script>
</body>
</html>
