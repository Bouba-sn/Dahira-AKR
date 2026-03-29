<?php
// pages/accueil.php
$pageTitle = 'Accueil';
require_once __DIR__ . '/../includes/header.php';

// Récupérer les données
$pdo = db();

// Rappel du jour
$today = date('Y-m-d');
$rappel = $pdo->query("SELECT * FROM rappels WHERE actif=1 ORDER BY RAND() LIMIT 1")->fetch();

// Événements à venir
$stmt = $pdo->prepare("SELECT * FROM evenements WHERE date_evenement >= NOW() ORDER BY date_evenement ASC LIMIT 5");
$stmt->execute();
$evenements = $stmt->fetchAll();

// Prochaine Dahira du samedi
$dahira = $pdo->query("SELECT * FROM evenements WHERE type='dahira_samedi' AND date_evenement >= NOW() ORDER BY date_evenement ASC LIMIT 1")->fetch();
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-primary-900 text-white px-4 pt-safe-top">
    <div class="flex items-center justify-between h-14">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center overflow-hidden">
                <img src="/assets/uploads/1.png" alt="Logo" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='/assets/uploads/1.jpg'">
            </div>
            <div>
                <p class="text-xs opacity-70 leading-none">بسم الله</p>
                <p class="text-sm font-semibold leading-tight">Dahira AKR</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <!-- Dark mode -->
            <button onclick="toggleDarkMode()" class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </button>
            <!-- Notifications -->
            <button onclick="requestNotifications()" class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
            </button>
            <?php if (isLoggedIn()): 
                $uStmt = $pdo->prepare("SELECT statut_adhesion FROM utilisateurs WHERE id=?");
                $uStmt->execute([$_SESSION['user_id']]);
                $usrAdhesion = $uStmt->fetchColumn() ?: 'non_membre';
            ?>
            <a href="<?= isAdmin() ? '/admin/dashboard.php' : '/pages/parametres.php' ?>" class="relative w-8 h-8 rounded-full bg-gold-500 flex items-center justify-center text-primary-900 font-bold text-xs ring-2 ring-white/20">
                <?= strtoupper(substr($_SESSION['user_nom'] ?? 'U', 0, 1)) ?>
                <?php if ($usrAdhesion === 'membre'): ?>
                <span class="absolute -top-1 -right-1 w-3 h-3 bg-green-500 rounded-full border border-primary-900 flex items-center justify-center" title="Membre certifié">
                    <svg class="w-2 h-2 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>
                </span>
                <?php endif; ?>
            </a>
            <?php else: ?>
            <a href="/public/login.php" class="text-xs bg-white/20 px-3 py-1 rounded-full font-medium">Connexion</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<main class="page-content">

    <!-- HERO SECTION -->
    <div class="relative overflow-hidden bg-gradient-to-b from-primary-900 via-primary-800 to-primary-700 text-white px-4 pb-8 pt-4">
        <!-- Motif décoratif -->
        <div class="absolute inset-0 opacity-5">
            <div class="absolute top-4 right-4 text-9xl arabic">بِسْمِ اللّهِ</div>
        </div>

        <div class="relative text-center w-full">
            <!-- 1 photo statique -->
            <div id="hero-image" class="w-40 h-40 mx-auto mb-3 flex items-center justify-center shadow-2xl overflow-hidden z-10 relative rounded-2xl border border-white/20">
                <img src="/assets/uploads/6.png" alt="Hero" class="w-full h-full object-cover">
            </div>
            
            <h1 class="text-xl font-bold leading-tight mb-1">Dahira A Khiba-i</h1>
            <h2 class="text-base font-light opacity-90 mb-1">Rassouloulahi</h2>
            <p class="text-xs opacity-60 arabic text-center mb-6">الطريقة التجانية</p>

            <div class="space-y-4 max-w-sm mx-auto">
                <!-- DAHIRA DU SAMEDI (sur le bleu) -->
                <?php if ($dahira): ?>
                <div class="bg-white/10 backdrop-blur-md text-white rounded-2xl p-4 border border-white/20 cursor-pointer shadow-lg active:scale-95 transition-transform text-left flex items-start gap-4" onclick="showEventModal(<?= htmlspecialchars(json_encode(['titre' => $dahira['nom_complet'], 'date' => date('d/m/Y H:i', strtotime($dahira['date_evenement'])), 'adresse' => $dahira['adresse'] ?? '', 'description' => $dahira['description'] ?? '', 'image' => $dahira['image'] ?? ''])) ?>)">
                    <div class="w-12 h-12 rounded-xl bg-gold-500 text-primary-900 flex flex-col items-center justify-center flex-shrink-0 font-bold leading-none">
                        <span class="text-lg"><?= date('d', strtotime($dahira['date_evenement'])) ?></span>
                        <span class="text-[10px] uppercase font-semibold"><?= date('M', strtotime($dahira['date_evenement'])) ?></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-[10px] bg-gold-500/20 text-gold-200 font-bold px-2 py-0.5 rounded-full border border-gold-500/30">Dahira du Samedi</span>
                        </div>
                        <p class="font-semibold text-sm truncate"><?= e($dahira['nom_complet']) ?></p>
                        <p class="text-[11px] opacity-80 truncate mt-0.5 flex items-center gap-1">
                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <?= e($dahira['adresse'] ?? 'Lieu non défini') ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- BOUTON ADHÉSION -->
                <?php 
                $usrAdh = isset($usrAdhesion) ? $usrAdhesion : 'non_membre';
                if (!isLoggedIn() || $usrAdh === 'non_membre'): ?>
                <button onclick="openAdhesionModal()" class="w-full bg-gold-400 hover:bg-gold-500 text-primary-900 font-bold py-3.5 rounded-xl shadow-xl transition-colors text-sm flex items-center justify-center gap-2">
                    <span>👋</span> Adhérer au Dahira
                </button>
                <?php elseif ($usrAdh === 'en_attente'): ?>
                <div class="w-full bg-white/10 border border-white/20 text-white font-medium py-3 rounded-xl text-sm flex items-center justify-center gap-2 backdrop-blur-sm">
                    <span>⏳</span> Adhésion en attente de confirmation...
                </div>
                <?php endif; // Si membre, on cache le bouton ?>
            </div>
        </div>
    </div>

    <!-- SECTION TITRE ÉVÉNEMENTS (DÉPLACÉ EN HAUT) -->
    <div class="px-4 mt-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Événements à venir</h3>
            <span class="text-xs text-primary-700 dark:text-blue-400 font-medium"><?= count($evenements) ?> événements</span>
        </div>

        <?php if (empty($evenements)): ?>
        <div class="text-center py-8 text-slate-400">
            <p class="text-sm mt-4">Aucun événement à venir</p>
        </div>
        <?php else: ?>

        <!-- Les événements restants à venir -->

        <!-- Liste événements -->
        <div class="space-y-2">
        <?php foreach ($evenements as $ev): 
            $typeLabels = ['gamou'=>'Gamou','ziar'=>'Ziar','dahira_samedi'=>'Dahira','autre'=>'Événement'];
            $typeColors = ['gamou'=>'bg-purple-100 text-purple-700','ziar'=>'bg-green-100 text-green-700','dahira_samedi'=>'bg-blue-100 text-blue-700','autre'=>'bg-slate-100 text-slate-600'];
            $type = $ev['type'];
        ?>
        <div class="bg-white dark:bg-slate-800 rounded-xl p-3 border border-slate-100 dark:border-slate-700 card-hover flex items-center gap-3 fade-in-up cursor-pointer" onclick="showEventModal(<?= htmlspecialchars(json_encode(['titre' => $ev['nom_complet'], 'date' => date('d/m/Y H:i', strtotime($ev['date_evenement'])), 'adresse' => $ev['adresse'] ?? '', 'description' => $ev['description'] ?? '', 'image' => $ev['image'] ?? ''])) ?>)">
            <div class="w-10 h-10 rounded-xl bg-slate-50 dark:bg-slate-700 flex items-center justify-center flex-shrink-0 overflow-hidden">
                <?php if (!empty($ev['image'])): ?>
                <img src="/assets/uploads/<?= e($ev['image']) ?>" class="w-full h-full object-cover" alt="Event Image">
                <?php else: ?>
                <span class="text-sm font-bold text-slate-400"><?= mb_strtoupper(mb_substr($typeLabels[$type] ?? 'E', 0, 1)) ?></span>
                <?php endif; ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-0.5">
                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full <?= $typeColors[$type] ?? 'bg-slate-100 text-slate-600' ?>">
                        <?= $typeLabels[$type] ?? 'Événement' ?>
                    </span>
                    <span class="text-[10px] text-slate-400"><?= date('d/m/Y', strtotime($ev['date_evenement'])) ?></span>
                </div>
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate"><?= e($ev['nom_complet']) ?></p>
                <?php if ($ev['adresse']): ?>
                <p class="text-xs text-slate-400 truncate"><?= e($ev['adresse']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>

    <!-- RAPPEL DU JOUR (DÉPLACÉ EN BAS) -->
    <?php if ($rappel): ?>
    <div class="mx-4 mt-6 relative z-10">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg p-4 border border-slate-100 dark:border-slate-700 fade-in-up">
            <div class="flex items-center gap-2 mb-3">
                <span class="text-xs font-semibold text-primary-900 dark:text-blue-300 uppercase tracking-wide">Rappel du jour</span>
            </div>
            <p class="arabic text-xl leading-relaxed text-slate-800 dark:text-slate-100 mb-2"><?= e($rappel['texte_arabe']) ?></p>
            <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed"><?= e($rappel['texte_francais']) ?></p>
            <?php if ($rappel['source']): ?>
            <p class="text-xs text-primary-700 dark:text-blue-400 mt-2 font-medium">— <?= e($rappel['source']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>



    <!-- PWA INSTALL -->
    <div id="pwa-install-btn" style="display:none;" class="mx-4 my-4">
        <button onclick="installPWA()" class="w-full bg-primary-900 text-white rounded-2xl p-3 flex items-center justify-center gap-2 text-sm font-medium">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 2v13m0 0-4-4m4 4 4-4"/><rect x="2" y="17" width="20" height="5" rx="2"/>
            </svg>
            Installer l'application
        </button>
    </div>

    <!-- FOOTER SPIRITUEL -->
    <div class="px-4 py-6 text-center">
        <p class="arabic text-2xl text-primary-900 dark:text-blue-400 mb-1">الطريقة التجانية</p>
        <p class="text-xs text-slate-400">Dahira A Khiba-i Rassouloulahi</p>
    </div>

</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const slides = document.querySelectorAll('.slide-img');
    if (slides.length > 0) {
        let currentSlide = 0;
        setInterval(() => {
            slides[currentSlide].classList.remove('opacity-100');
            slides[currentSlide].classList.add('opacity-0');
            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].classList.remove('opacity-0');
            slides[currentSlide].classList.add('opacity-100');
        }, 3500); // Change photo toutes les 3.5 secondes
    }
});

function requestNotifications() {
    if ('Notification' in window) {
        Notification.requestPermission().then(perm => {
            if (perm === 'granted') showToast('🔔 Notifications activées !');
            else showToast('Notifications désactivées');
        });
    }
}

function showEventModal(data) {
    const modal = document.getElementById('event-modal');
    const content = document.getElementById('event-modal-content');
    
    document.getElementById('modal-ev-title').textContent = data.titre;
    document.getElementById('modal-ev-date').textContent = data.date;
    document.getElementById('modal-ev-address').textContent = data.adresse || 'Lieu non spécifié';
    document.getElementById('modal-ev-desc').textContent = data.description || 'Aucune description.';
    
    const imgObj = document.getElementById('modal-ev-img');
    const phObj = document.getElementById('modal-ev-placeholder');
    if (data.image) {
        imgObj.src = '/assets/uploads/' + data.image;
        imgObj.classList.remove('hidden');
        phObj.classList.add('hidden');
    } else {
        imgObj.classList.add('hidden');
        phObj.classList.remove('hidden');
    }
    
    modal.classList.remove('opacity-0', 'pointer-events-none');
    setTimeout(() => {
        content.classList.remove('translate-y-full', 'sm:translate-y-4', 'scale-95', 'opacity-0');
        content.classList.add('translate-y-0', 'scale-100', 'opacity-100');
    }, 10);
}

function closeEventModal() {
    const modal = document.getElementById('event-modal');
    const content = document.getElementById('event-modal-content');
    
    content.classList.remove('translate-y-0', 'scale-100', 'opacity-100');
    content.classList.add('translate-y-full', 'sm:translate-y-4', 'scale-95', 'opacity-0');
    
    setTimeout(() => {
        modal.classList.add('opacity-0', 'pointer-events-none');
    }, 300);
}

function openAdhesionModal() {
    <?php if (!isLoggedIn()): ?>
        window.location.href = '/public/login.php';
        return;
    <?php endif; ?>
    const modal = document.getElementById('adhesion-modal');
    if(!modal) return;
    const content = document.getElementById('adhesion-modal-content');
    modal.classList.remove('opacity-0', 'pointer-events-none');
    setTimeout(() => {
        content.classList.remove('translate-y-full', 'sm:translate-y-4', 'scale-95', 'opacity-0');
        content.classList.add('translate-y-0', 'scale-100', 'opacity-100');
    }, 10);
}

function closeAdhesionModal() {
    const modal = document.getElementById('adhesion-modal');
    const content = document.getElementById('adhesion-modal-content');
    content.classList.remove('translate-y-0', 'scale-100', 'opacity-100');
    content.classList.add('translate-y-full', 'sm:translate-y-4', 'scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('opacity-0', 'pointer-events-none');
    }, 300);
}

function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const originalText = btn.textContent;
        btn.textContent = 'Copié !';
        btn.classList.add('bg-green-100', 'text-green-800');
        setTimeout(() => {
            btn.textContent = originalText;
            btn.classList.remove('bg-green-100', 'text-green-800');
        }, 2000);
    });
}
</script>

<!-- Event Modal -->
<div id="event-modal" class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300">
    <div class="bg-white dark:bg-slate-900 w-full sm:w-[28rem] max-h-[90vh] overflow-y-auto rounded-t-3xl sm:rounded-3xl shadow-2xl transform translate-y-full sm:translate-y-4 scale-95 opacity-0 transition-all duration-300 flex flex-col" id="event-modal-content">
        <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mt-4 sm:hidden flex-shrink-0"></div>
        
        <div class="relative w-full bg-slate-100 dark:bg-slate-900 mt-4 sm:mt-0 sm:rounded-t-3xl overflow-hidden shrink-0 flex items-center justify-center" style="min-h: 14rem; max-h: 50vh;">
            <img id="modal-ev-img" src="" class="max-w-full max-h-[50vh] object-contain hidden relative z-10">
            <div class="absolute inset-0 bg-gradient-to-t from-slate-200 to-slate-100 dark:from-slate-800 dark:to-slate-900"></div>
            <div id="modal-ev-placeholder" class="absolute inset-0 w-full h-full flex items-center justify-center text-6xl hidden opacity-20 text-primary-900 z-0">🕌</div>
            <button onclick="closeEventModal()" class="absolute top-4 right-4 w-8 h-8 bg-black/50 hover:bg-black/80 text-white rounded-full flex items-center justify-center backdrop-blur-md z-20 transition-colors">✕</button>
        </div>
        
        <div class="p-6 flex-1">
            <p id="modal-ev-date" class="text-xs font-bold text-primary-900 dark:text-blue-400 mb-1"></p>
            <h3 id="modal-ev-title" class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-3 leading-tight"></h3>
            
            <div class="flex items-start gap-2 mb-4 text-sm text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-800 p-3 rounded-xl border border-slate-100 dark:border-slate-700">
                <span class="mt-0.5 opacity-60">📍</span>
                <p id="modal-ev-address" class="flex-1"></p>
            </div>
            
            <div class="mb-2">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Description de l'événement</p>
                <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-4 border border-slate-100 dark:border-slate-700">
                    <p id="modal-ev-desc" class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isLoggedIn()): ?>
<!-- Modal Adhesion -->
<div id="adhesion-modal" class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300">
    <div class="bg-white dark:bg-slate-900 w-full sm:w-[28rem] rounded-t-3xl sm:rounded-3xl shadow-2xl transform translate-y-full sm:translate-y-4 scale-95 opacity-0 transition-all duration-300 flex flex-col p-6" id="adhesion-modal-content">
        <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mb-4 sm:hidden flex-shrink-0"></div>
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-1">Formulaire d'adhésion</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Devenez membre officiel du Dahira A Khiba-i Rassouloulahi.</p>
        
        <form action="/pages/adhesion_action.php" method="POST" class="space-y-4" id="adhesion-form">
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nom complet</label>
                <input type="text" value="<?= e($_SESSION['user_nom'] ?? '') ?>" readonly class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm focus:outline-none opacity-70 cursor-not-allowed">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Téléphone</label>
                <input type="tel" name="telephone" required placeholder="+221 77 000 00 00" class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 outline-none transition-shadow">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Adresse complète</label>
                <input type="text" name="adresse" required placeholder="Ex: Parcelles Assainies, Dakar" class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 outline-none transition-shadow">
            </div>
            
            <div class="bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-500 p-4 rounded-r-xl my-5">
                <p class="text-xs text-amber-800 dark:text-amber-200 font-bold mb-1">Frais d'adhésion : 2000 FCFA</p>
                <p class="text-[11px] text-amber-700 dark:text-amber-300/80 mb-2 leading-tight">Veuillez envoyer ce montant par Wave ou Orange Money au numéro suivant pour confirmer votre adhésion :</p>
                <div class="flex items-center gap-2">
                    <span class="font-bold text-sm text-slate-900 dark:text-white" id="numero-adhesion">77 000 00 00</span>
                    <button type="button" onclick="copyToClipboard('7700000000', this)" class="text-[10px] font-bold uppercase tracking-wide bg-amber-200 dark:bg-amber-800 text-amber-800 dark:text-amber-200 px-2.5 py-1.5 rounded-lg active:scale-95 transition-transform">Copier</button>
                </div>
            </div>
            
            <div class="pt-2">
                <button type="submit" class="w-full bg-primary-900 hover:bg-primary-800 text-white font-bold py-3.5 rounded-xl transition-colors shadow-lg active:scale-95 flex items-center justify-center gap-2">
                    J'ai effectué le transfert
                </button>
                <button type="button" onclick="closeAdhesionModal()" class="w-full mt-3 py-3 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 font-semibold text-sm transition-colors rounded-xl">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
