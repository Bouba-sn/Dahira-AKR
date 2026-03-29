<?php
// pages/biographie.php
$pageTitle = 'Biographie';
require_once __DIR__ . '/../includes/header.php';

// Lire le JSON
$jsonPath = __DIR__ . '/../biographie.json';
$famille = [];
if (file_exists($jsonPath)) {
    $data = json_decode(file_get_contents($jsonPath), true);
    if (isset($data['famille'])) {
        $famille = $data['famille'];
    }
}
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 px-4">
    <div class="flex items-center h-14 gap-3">
        <a href="/pages/accueil.php" class="w-8 h-8 flex items-center justify-center text-slate-600 dark:text-slate-300 flex-shrink-0 bg-slate-100 dark:bg-slate-800 rounded-full active:scale-95 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <div class="flex-1">
            <h1 class="font-bold text-slate-900 dark:text-white line-clamp-1">Biographie</h1>
            <p class="text-[10px] text-slate-400">Généalogie et Histoire</p>
        </div>
    </div>
</div>

<main class="page-content bg-slate-50 dark:bg-slate-900/50 min-h-screen pb-safe">
    <!-- BANNIÈRE -->
    <div class="relative px-4 py-8 text-white overflow-hidden shadow-sm rounded-b-3xl sm:rounded-none">
        <!-- Background flouté -->
        <div class="absolute inset-0 z-0 bg-primary-900">
            <img src="/assets/uploads/9.jpg" class="w-full h-full object-cover blur-sm opacity-100 scale-110" alt="Background">
            <div class="absolute inset-0 bg-primary-900/60 mix-blend-multiply"></div>
            <div class="absolute inset-0 bg-gradient-to-b from-black/20 to-transparent"></div>
        </div>
        
        <div class="relative z-10">
            <p class="arabic text-4xl pb-1 drop-shadow-md">تاريخ العائلة</p>
            <p class="text-xs font-bold mt-2 pl-3 border-l-4 border-gold-400 drop-shadow-sm text-slate-100 uppercase tracking-widest">Parcours et biographies</p>
        </div>
    </div>

    <!-- LISTE DES MARABOUTS -->
    <div class="px-4 mt-6 pb-8">
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <?php foreach ($famille as $index => $marabout): 
                $dates = "";
                if (!empty($marabout['annee_naissance'])) {
                    $dates .= $marabout['annee_naissance'];
                    if (!empty($marabout['annee_mort'])) {
                        $dates .= " - " . $marabout['annee_mort'];
                    } else {
                        $dates .= " - Présent";
                    }
                }
            ?>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-md card-hover overflow-hidden flex flex-col cursor-pointer fade-in-up transition-transform active:scale-95 group" onclick="showBioModal(<?= htmlspecialchars(json_encode([
                'nom' => $marabout['nom'],
                'pere' => $marabout['pere'] ?? '',
                'dates' => $dates,
                'biographie' => $marabout['biographie'] ?? '',
                'photo' => $marabout['photo'] ?? ''
            ])) ?>)">
                <!-- Photo Top -->
                <div class="w-full aspect-square bg-slate-100 dark:bg-slate-700 relative text-primary-900 dark:text-blue-400 font-bold text-4xl flex items-center justify-center overflow-hidden">
                    <?php if (!empty($marabout['photo'])): ?>
                        <img src="/assets/uploads/<?= e($marabout['photo']) ?>" class="w-full h-full object-cover relative z-10 transition-transform duration-500 group-hover:scale-110">
                    <?php else: ?>
                        <span class="opacity-50"><?= mb_strtoupper(mb_substr($marabout['nom'], 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Text Bottom -->
                <div class="p-3 text-center flex-1 flex flex-col justify-center items-center bg-white dark:bg-slate-800">
                    <p class="text-sm font-bold text-slate-900 dark:text-white leading-tight mb-0.5"><?= e($marabout['nom']) ?></p>
                    <?php if (!empty($marabout['pere'])): ?>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 truncate w-full">Fils de <?= e($marabout['pere']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<!-- MODALE BIOGRAPHIE -->
<div id="bio-modal" class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300">
    <div class="bg-white dark:bg-slate-900 w-full sm:w-[32rem] max-h-[90vh] overflow-y-auto rounded-t-3xl sm:rounded-3xl shadow-2xl transform translate-y-full sm:translate-y-4 scale-95 opacity-0 transition-all duration-300 flex flex-col" id="bio-modal-content">
        <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mt-4 sm:hidden flex-shrink-0"></div>
        
        <div class="p-6 pb-4 border-b border-slate-100 dark:border-slate-800 flex items-start justify-between gap-4 sticky top-0 bg-white/95 dark:bg-slate-900/95 backdrop-blur z-10">
            <div class="pr-6">
                <h3 id="modal-bio-nom" class="text-xl font-bold text-slate-800 dark:text-slate-100 leading-tight mb-1.5"></h3>
                <p id="modal-bio-pere" class="text-xs text-primary-700 dark:text-blue-400 font-medium"></p>
                <p id="modal-bio-dates" class="text-[10px] bg-slate-100 dark:bg-slate-800 inline-block px-2 py-0.5 rounded-lg text-slate-500 dark:text-slate-400 mt-2 tracking-wide font-semibold"></p>
            </div>
            <button onclick="closeBioModal()" class="w-8 h-8 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-full flex items-center justify-center shrink-0 transition-colors">✕</button>
        </div>
        
        <!-- Conteneur Photo optionnel en haut -->
        <div id="modal-bio-photo-container" class="px-6 pt-6 hidden">
            <div class="relative w-full flex justify-center cursor-pointer active:scale-[0.98] transition-transform" onclick="openImageViewer()">
                <img id="modal-bio-photo" src="" alt="Photo" class="w-auto h-auto max-w-full max-h-72 rounded-2xl shadow-sm object-contain">
            </div>
        </div>

        <div class="p-6 pt-5 flex-1 relative">
            <div class="prose prose-sm dark:prose-invert max-w-none text-slate-600 dark:text-slate-300 leading-relaxed text-justify" id="modal-bio-texte">
            </div>
        </div>
    </div>
</div>

<!-- IMAGE VIEWER PLEIN ECRAN -->
<div id="image-viewer-modal" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/95 backdrop-blur-md opacity-0 pointer-events-none transition-opacity duration-300" onclick="closeImageViewer()">
    <button class="absolute top-4 right-4 sm:top-6 sm:right-6 w-12 h-12 bg-white/10 text-white rounded-full flex items-center justify-center z-[80] hover:bg-white/20 transition-colors backdrop-blur">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
    <img id="viewer-full-image" src="" class="max-w-full max-h-[90vh] object-contain transform scale-95 transition-transform duration-300" onclick="event.stopPropagation()">
</div>

<script>
let currentPhotoUrl = '';

function showBioModal(data) {
    const modal = document.getElementById('bio-modal');
    const content = document.getElementById('bio-modal-content');
    
    document.getElementById('modal-bio-nom').textContent = data.nom;
    
    if (data.pere) {
        document.getElementById('modal-bio-pere').innerHTML = '<span class="opacity-60">Fils de</span> ' + data.pere;
    } else {
        document.getElementById('modal-bio-pere').textContent = '';
    }
    
    const datesEl = document.getElementById('modal-bio-dates');
    if (data.dates) {
        datesEl.textContent = data.dates;
        datesEl.classList.remove('hidden');
    } else {
        datesEl.classList.add('hidden');
    }
    
    const photoContainer = document.getElementById('modal-bio-photo-container');
    const photoImg = document.getElementById('modal-bio-photo');
    if (data.photo) {
        currentPhotoUrl = '/assets/uploads/' + data.photo;
        photoImg.src = currentPhotoUrl;
        photoContainer.classList.remove('hidden');
    } else {
        currentPhotoUrl = '';
        photoContainer.classList.add('hidden');
    }

    const bioText = data.biographie ? data.biographie.split('\n').filter(p => p.trim()).map(p => `<p class="mb-4">${p}</p>`).join('') : '<p class="italic opacity-50">Aucune biographie disponible pour le moment.</p>';
    document.getElementById('modal-bio-texte').innerHTML = bioText;
    
    modal.classList.remove('opacity-0', 'pointer-events-none');
    setTimeout(() => {
        content.classList.remove('translate-y-full', 'sm:translate-y-4', 'scale-95', 'opacity-0');
        content.classList.add('translate-y-0', 'scale-100', 'opacity-100');
    }, 10);
}

function closeBioModal() {
    const modal = document.getElementById('bio-modal');
    const content = document.getElementById('bio-modal-content');
    
    content.classList.remove('translate-y-0', 'scale-100', 'opacity-100');
    content.classList.add('translate-y-full', 'sm:translate-y-4', 'scale-95', 'opacity-0');
    
    setTimeout(() => {
        modal.classList.add('opacity-0', 'pointer-events-none');
    }, 300);
}

function openImageViewer() {
    if (!currentPhotoUrl) return;
    const modal = document.getElementById('image-viewer-modal');
    const img = document.getElementById('viewer-full-image');
    img.src = currentPhotoUrl;
    modal.classList.remove('opacity-0', 'pointer-events-none');
    setTimeout(() => {
        img.classList.remove('scale-95');
        img.classList.add('scale-100');
    }, 10);
}

function closeImageViewer() {
    const modal = document.getElementById('image-viewer-modal');
    const img = document.getElementById('viewer-full-image');
    img.classList.remove('scale-100');
    img.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('opacity-0', 'pointer-events-none');
    }, 150);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
