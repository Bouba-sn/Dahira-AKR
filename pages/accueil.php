<?php
// pages/accueil.php
$pageTitle = 'Accueil';
require_once __DIR__ .'/../includes/header.php';

// Récupérer les données
$pdo = db();

// Heures de prière actives
$prieres = $pdo->query("SELECT * FROM heures_prieres WHERE actif=1 ORDER BY id DESC LIMIT 1")->fetch();

// Événements à venir
$stmt = $pdo->prepare("SELECT * FROM evenements WHERE date_evenement >= NOW() ORDER BY date_evenement ASC LIMIT 5");
$stmt->execute();
$evenements = $stmt->fetchAll();

// Prochaine Dahira du samedi
$dahira = $pdo->query("SELECT * FROM evenements WHERE type='dahira_samedi'AND date_evenement >= NOW() ORDER BY date_evenement ASC LIMIT 1")->fetch();
?>

<!-- TOP BAR (VISIBLE SUR MOBILE UNIQUEMENT, SUR DESKTOP LA NAVBAR PRINCIPALE PREND LE RELAIS) -->
<div class="sticky top-0 z-40 bg-primary-900 text-white px-4 flex items-center justify-between md:hidden" style="height: calc(3.5rem + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px);">
    <!-- Logo + Titre (Gauche) -->
    <div class="flex items-center gap-3">
      <div class="w-12 h-12 rounded-full flex items-center justify-center overflow-hidden shrink-0 logo-container">
        <img src="/assets/uploads/20.png"alt="Logo"class="w-full h-full object-contain"onerror="this.onerror=null; this.src='/assets/uploads/20.png'">
      </div>
      <p class="text-[17px] font-bold tracking-wide text-white">Dahira AKR</p>
    </div>
    <div class="flex items-center gap-2">
      <!-- Dark mode -->
      <button onclick="toggleDarkMode()"class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg"width="16"height="16"fill="none"stroke="currentColor"stroke-width="2"viewBox="0 0 24 24">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
      </button>
      <?php 
      $unreadCount = 0;
      $membreData = null;
      if (isLoggedIn()): 
        $uStmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id=?");
        $uStmt->execute([$_SESSION['user_id']]);
        $membreData = $uStmt->fetch();
        $usrAdhesion = $membreData['statut_adhesion'] ?? 'non_membre';
        
        $notifStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND lu=0");
        $notifStmt->execute([$_SESSION['user_id']]);
        $unreadCount = $notifStmt->fetchColumn();
      ?>
      <!-- Cloche Notifications Internes -->
      <a href="/pages/notifications.php"class="relative w-8 h-8 rounded-full bg-white/10 flex items-center justify-center active:scale-95 transition-transform">
        <svg xmlns="http://www.w3.org/2000/svg"width="16"height="16"fill="none"stroke="currentColor"stroke-width="2"viewBox="0 0 24 24">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <?php if ($unreadCount >0): ?>
        <span class="absolute top-0 right-0 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-primary-900 animate-pulse"></span>
        <?php endif; ?>
      </a>
      <a href="<?= isAdmin() ?'/admin/dashboard.php':'/pages/parametres.php'?>"class="relative w-8 h-8 rounded-full bg-gold-500 flex items-center justify-center text-primary-900 font-bold text-xs ring-2 ring-white/20">
        <?= strtoupper(substr($_SESSION['user_nom'] ??'U', 0, 1)) ?>
        <?php if ($usrAdhesion === 'membre'): ?>
        <span class="absolute -top-1 -right-1 w-3 h-3 bg-green-500 rounded-full border border-primary-900 flex items-center justify-center"title="Membre certifié">
          <svg class="w-2 h-2 text-white"fill="none"stroke="currentColor"stroke-width="3"viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>
        </span>
        <?php endif; ?>
      </a>
      <?php else: ?>
      <a href="/public/login.php"class="text-xs bg-white/20 px-3 py-1 rounded-full font-medium">Connexion</a>
      <?php endif; ?>
    </div>
</div>

<main class="page-content bg-slate-50 dark:bg-slate-950">

  <!-- HERO SECTION -->
  <div class="relative overflow-hidden bg-white dark:bg-slate-900 text-slate-900 dark:text-white px-4 pb-8 pt-6 border-b border-slate-100 dark:border-slate-800">
    <!-- Motif décoratif -->
    <div class="absolute inset-0 opacity-[0.03] dark:opacity-[0.05] pointer-events-none">
      <div class="absolute top-4 right-4 text-9xl arabic">بِسْمِ اللّهِ</div>
    </div>

    <div class="relative text-center w-full">
      <!-- 1 photo statique -->
      <div id="hero-image"class="w-40 h-40 mx-auto mb-4 flex items-center justify-center shadow-xl overflow-hidden z-10 relative rounded-2xl border border-slate-200 dark:border-slate-700">
        <img src="/assets/uploads/6.png" alt="Hero" class="w-full h-full object-cover" decoding="async" onerror="this.onerror=null; this.src='/assets/uploads/20.png'">
      </div>
      
      <h1 class="text-xl font-bold leading-tight mb-1 text-primary-900 dark:text-white">Dahira A Khiba-i</h1>
      <h2 class="text-base font-medium text-slate-600 dark:text-slate-300 mb-1">Rassouloulahi</h2>
      <p class="text-xs text-slate-400 arabic text-center mb-6">صلى الله عليه وسلم </p>

      <div class="space-y-4 max-w-sm mx-auto">
        <!-- DAHIRA DU SAMEDI -->
        <?php if ($dahira): ?>
        <div class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-2xl p-4 border border-slate-200 dark:border-slate-700 cursor-pointer shadow-sm active:scale-95 transition-transform text-left flex items-start gap-4"onclick="showEventModal(<?= htmlspecialchars(json_encode(['titre' => $dahira['nom_complet'],'date' =>date('d/m/Y H:i', strtotime($dahira['date_evenement'])),'adresse' => $dahira['adresse'] ??'','description' => $dahira['description'] ??'','image' => $dahira['image'] ??''])) ?>)">
          <div class="w-12 h-12 rounded-xl bg-primary-100 dark:bg-primary-900/50 text-primary-900 dark:text-blue-300 flex flex-col items-center justify-center flex-shrink-0 font-bold leading-none">
            <span class="text-lg"><?= date('d', strtotime($dahira['date_evenement'])) ?></span>
            <span class="text-[10px] uppercase font-semibold"><?php $moisFr = ['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Aoû','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc']; echo $moisFr[date('m', strtotime($dahira['date_evenement']))]; ?></span>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-0.5">
              <span class="text-[10px] bg-primary-50 dark:bg-slate-700 text-primary-700 dark:text-blue-300 font-bold px-2 py-0.5 rounded-full border border-primary-100 dark:border-slate-600">Dahira du Samedi</span>
            </div>
            <p class="font-semibold text-sm truncate"><?= e($dahira['nom_complet']) ?></p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5 flex items-center gap-1">
              <svg class="w-3 h-3 flex-shrink-0"fill="none"stroke="currentColor"stroke-width="2"viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12"cy="10"r="3"/></svg>
              <?= e($dahira['adresse'] ??'Lieu non défini') ?>
            </p>
          </div>
        </div>
        <?php endif; ?>
        
        <!-- BOUTON ADHÉSION -->
        <?php 
        $usrAdh = isset($usrAdhesion) ? $usrAdhesion :'non_membre';
        if (!isLoggedIn() || $usrAdh === 'non_membre'): ?>
        <button onclick="openAdhesionModal()"class="relative w-full max-w-[280px] mx-auto bg-gradient-to-r from-[#D4AF37] to-[#F3E5AB] hover:from-[#F3E5AB] hover:to-[#D4AF37] text-primary-900 font-bold py-2.5 rounded-xl shadow-md shadow-yellow-600/20 active:translate-y-0.5 active:border-b-0 transition-all text-sm border-b-[3px] border-[#B8860B] flex items-center justify-center gap-2 overflow-hidden group">
          <div class="absolute inset-0 bg-white/20 opacity-0 group-hover:opacity-100 transition-opacity"></div>
          <span class="text-lg group-hover:scale-110 transition-transform duration-300"></span> 
          <span class="tracking-wide drop-shadow-sm">Adhérer au Dahira</span>
        </button>
        <?php elseif ($usrAdh === 'en_attente'): ?>
        <div class="w-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-medium py-3 rounded-xl text-sm flex items-center justify-center gap-2">
          <span class="animate-spin w-4 h-4 border-2 border-primary-900 border-t-transparent rounded-full flex-shrink-0"></span>Adhésion en attente...
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- SECTION TITRE ÉVÉNEMENTS (DÉPLACÉ EN HAUT) -->
  <div class="px-4 mt-6">
    <div class="flex items-center justify-between mb-3">
      <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Événements à venir</h3>
      <span class="text-xs text-primary-700 dark:text-blue-400 font-medium"><?= count($evenements) ?>événements</span>
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
    <div class="bg-white dark:bg-slate-800 rounded-xl p-3 border border-slate-100 dark:border-slate-700 card-hover flex items-center gap-3 fade-in-up cursor-pointer"onclick="showEventModal(<?= htmlspecialchars(json_encode(['titre' => $ev['nom_complet'],'date' =>date('d/m/Y H:i', strtotime($ev['date_evenement'])),'adresse' => $ev['adresse'] ??'','description' => $ev['description'] ??'','image' => $ev['image'] ??''])) ?>)">
      <div class="w-10 h-10 rounded-xl bg-slate-50 dark:bg-slate-700 flex items-center justify-center flex-shrink-0 overflow-hidden">
        <?php if (!empty($ev['image'])): ?>
        <img src="/assets/uploads/<?= e($ev['image']) ?>" class="w-full h-full object-cover" alt="Event Image" loading="lazy" decoding="async" onerror="this.onerror=null; this.src='/assets/uploads/20.png'">
        <?php else: ?>
        <span class="text-sm font-bold text-slate-400"><?= mb_strtoupper(mb_substr($typeLabels[$type] ??'E', 0, 1)) ?></span>
        <?php endif; ?>
      </div>
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 mb-0.5">
          <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full <?= $typeColors[$type] ??'bg-slate-100 text-slate-600'?>">
            <?= $typeLabels[$type] ??'Événement'?>
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

  <!-- HEURES DE PRIÈRES -->
  <?php if ($prieres): ?>
  <div class="mx-4 mt-8 relative z-10 mb-2">
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-700 fade-in-up overflow-hidden relative">
      
      <!-- Décoration légère -->
      <div class="absolute -top-10 -right-10 w-24 h-24 bg-primary-50 rounded-full blur-2xl opacity-60"></div>

      <!-- Header -->
      <div class="px-5 py-3.5 border-b border-slate-50 dark:border-slate-700/50 flex items-center justify-between relative z-10">
        <span class="text-base font-black text-slate-800 dark:text-white flex items-center tracking-wide uppercase">
          Heures de Prière
        </span>
        <span class="text-xs font-bold text-primary-700 dark:text-blue-400 bg-primary-50 dark:bg-primary-900/30 px-3 py-1.5 rounded-full tracking-wide shadow-sm">
          <?php 
          $mois = ['01'=>'janv.','02'=>'févr.','03'=>'mars','04'=>'avr.','05'=>'mai','06'=>'juin','07'=>'juil.','08'=>'août','09'=>'sept.','10'=>'oct.','11'=>'nov.','12'=>'déc.'];
          $dd = date('d', strtotime($prieres['date_debut']));
          $df = date('d', strtotime($prieres['date_fin'])) .' '. $mois[date('m', strtotime($prieres['date_fin']))];
          echo"Du $dd au $df";
          ?>
        </span>
      </div>
      
      <!-- Timeline Prières -->
      <div class="p-5 flex items-center justify-between relative z-10">
        <?php
        $times = [
          ['Nom' => 'Fajr','Heure' => $prieres['fajr']],
          ['Nom' => 'Dhuhr','Heure' => $prieres['dhuhr']],
          ['Nom' => 'Asr','Heure' => $prieres['asr']],
          ['Nom' => 'Maghrib','Heure' => $prieres['maghrib']],
          ['Nom' => 'Isha','Heure' => $prieres['isha']],
        ];
        foreach ($times as $t):
        ?>
        <div class="flex flex-col items-center group cursor-default">
          <div class="w-1.5 h-1.5 rounded-full bg-primary-200 dark:bg-primary-900 mb-2 group-hover:bg-primary-500 transition-colors duration-300"></div>
          <span class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-0.5"><?= $t['Nom'] ?></span>
          <span class="text-lg font-black text-slate-800 dark:text-white group-hover:text-primary-600 transition-colors duration-300"><?= date('H:i', strtotime($t['Heure'])) ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- BOUTON NOTIFICATIONS PRIÈRES -->
      <div class="px-5 pt-3 flex items-center justify-between border-t border-slate-50 dark:border-slate-700/50">
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 text-gold-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
          <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Alerte prières</span>
          <span class="text-[10px] text-slate-400 hidden sm:inline">(Son & Notification)</span>
        </div>
        <div class="flex items-center gap-2.5">
          <button type="button" onclick="testAdhan()" class="text-[11px] font-semibold text-primary-700 dark:text-blue-400 bg-primary-50 dark:bg-primary-900/30 hover:bg-primary-100 px-2.5 py-1 rounded-lg transition-colors">
            Tester
          </button>
          <button onclick="togglePrieresNotif(this)" id="prieres-toggle-home"
                  class="relative w-11 h-6 rounded-full transition-colors duration-300 <?= isset($_COOKIE['notif_prieres_active']) && $_COOKIE['notif_prieres_active'] === '1' ? 'bg-primary-900' : 'bg-slate-200 dark:bg-slate-600' ?>">
            <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-300 <?= isset($_COOKIE['notif_prieres_active']) && $_COOKIE['notif_prieres_active'] === '1' ? 'translate-x-5' : '' ?>"></span>
          </button>
        </div>
      </div>

    </div>
  </div>
  <?php endif; ?>



  <!-- PWA INSTALL -->
  <div id="pwa-install-btn"style="display:none;"class="mx-4 my-4">
    <button onclick="installPWA()"class="w-full bg-primary-900 text-white rounded-2xl p-3 flex items-center justify-center gap-2 text-sm font-medium shadow-sm hover:shadow-md active:shadow-md transition-all">
      <svg xmlns="http://www.w3.org/2000/svg"width="16"height="16"fill="none"stroke="currentColor"stroke-width="2"viewBox="0 0 24 24">
        <path d="M12 2v13m0 0-4-4m4 4 4-4"/><rect x="2"y="17"width="20"height="5"rx="2"/>
      </svg>
      Installer l'application
    </button>
  </div>

  <!-- FOOTER SPIRITUEL -->
  <div class="px-4 py-6 text-center">
    <p class="arabic text-2xl text-primary-900 dark:text-blue-400 mb-1">تِجَانِيٌّ فِي الدُّنْيَا وَالْآخِرَةِ</p>
    <p class="text-xs text-slate-400">Dahira A Khiba-i Rassouloulahi</p>
  </div>

</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const slides = document.querySelectorAll('.slide-img');
  if (slides.length >0) {
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
  if ('Notification'in window) {
    Notification.requestPermission().then(perm => {
      if (perm === 'granted') showToast('Notifications activées !');
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
  
  modal.classList.remove('opacity-0','pointer-events-none');
  setTimeout(() => {
    content.classList.remove('translate-y-full','sm:translate-y-4','scale-95','opacity-0');
    content.classList.add('translate-y-0','scale-100','opacity-100');
  }, 10);
}

function closeEventModal() {
  const modal = document.getElementById('event-modal');
  const content = document.getElementById('event-modal-content');
  
  content.classList.remove('translate-y-0','scale-100','opacity-100');
  content.classList.add('translate-y-full','sm:translate-y-4','scale-95','opacity-0');
  
  setTimeout(() => {
    modal.classList.add('opacity-0','pointer-events-none');
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
  modal.classList.remove('opacity-0','pointer-events-none');
  setTimeout(() => {
    content.classList.remove('translate-y-full','sm:translate-y-4','scale-95','opacity-0');
    content.classList.add('translate-y-0','scale-100','opacity-100');
  }, 10);
}

function closeAdhesionModal() {
  const modal = document.getElementById('adhesion-modal');
  const content = document.getElementById('adhesion-modal-content');
  content.classList.remove('translate-y-0','scale-100','opacity-100');
  content.classList.add('translate-y-full','sm:translate-y-4','scale-95','opacity-0');
  setTimeout(() => {
    modal.classList.add('opacity-0','pointer-events-none');
  }, 300);
}

function copyToClipboard(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const originalText = btn.textContent;
    btn.textContent = 'Copié !';
    btn.classList.add('bg-green-100','text-green-800');
    setTimeout(() => {
      btn.textContent = originalText;
      btn.classList.remove('bg-green-100','text-green-800');
    }, 2000);
  });
}
</script>

<!-- Event Modal -->
<div id="event-modal"class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300">
  <div class="bg-white dark:bg-slate-900 w-full sm:w-[28rem] max-h-[90vh] overflow-y-auto rounded-t-3xl sm:rounded-3xl shadow-2xl transform translate-y-full sm:translate-y-4 scale-95 opacity-0 transition-all duration-300 flex flex-col"id="event-modal-content">
    <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mt-4 sm:hidden flex-shrink-0"></div>
    
    <div class="relative w-full bg-slate-100 dark:bg-slate-900 mt-4 sm:mt-0 sm:rounded-t-3xl overflow-hidden shrink-0 flex items-center justify-center"style="min-h: 14rem; max-h: 50vh;">
      <img id="modal-ev-img"src=""class="max-w-full max-h-[50vh] object-contain hidden relative z-10">
      <div class="absolute inset-0 bg-gradient-to-t from-slate-200 to-slate-100 dark:from-slate-800 dark:to-slate-900"></div>
      <div id="modal-ev-placeholder"class="absolute inset-0 w-full h-full flex items-center justify-center text-6xl hidden opacity-20 text-primary-900 z-0"></div>
      <button onclick="closeEventModal()" class="absolute top-4 right-4 w-8 h-8 bg-black/50 hover:bg-black/80 text-white rounded-full flex items-center justify-center backdrop-blur-md z-20 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    
    <div class="p-6 flex-1">
      <p id="modal-ev-date"class="text-xs font-bold text-primary-900 dark:text-blue-400 mb-1"></p>
      <h3 id="modal-ev-title"class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-3 leading-tight"></h3>
      
      <div class="flex items-start gap-2 mb-4 text-sm text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-800 p-3 rounded-xl border border-slate-100 dark:border-slate-700">
        <span class="mt-0.5 opacity-60"></span>
        <p id="modal-ev-address"class="flex-1"></p>
      </div>
      
      <div class="mb-2">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Description de l'événement</p>
        <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-4 border border-slate-100 dark:border-slate-700">
          <p id="modal-ev-desc"class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed"></p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if (isLoggedIn()): ?>
<!-- Modal Adhesion -->
<?php 
  $paiementConfig = file_exists(__DIR__ . '/../config/paiement.php') ? require __DIR__ . '/../config/paiement.php' : [];
  $waveCfg = $paiementConfig['wave'] ?? [
      'nom' => 'Caisse Dahira (Wave Business)',
      'telephone' => '78 823 24 79',
      'raw_telephone' => '788232479',
      'lien_paiement' => 'https://pay.wave.com/m/M_dahira_akr',
      'logo' => '/assets/wave-logo.png'
  ];
  $omCfg = $paiementConfig['orange_money'] ?? [
      'nom' => 'Caisse Dahira (Orange Money)',
      'telephone' => '78 823 24 79',
      'raw_telephone' => '788232479',
      'lien_paiement' => 'https://om.orange.sn/pay/dahira_akr',
      'logo' => '/assets/orange-money-logo.svg'
  ];
  $fraisAdh = $paiementConfig['frais_adhesion'] ?? 2000;
?>
<div id="adhesion-modal" class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300">
  <div class="bg-white dark:bg-slate-900 w-full sm:w-[30rem] max-h-[90vh] overflow-y-auto rounded-t-3xl sm:rounded-3xl shadow-2xl transform translate-y-full sm:translate-y-4 scale-95 opacity-0 transition-all duration-300 flex flex-col p-5 sm:p-6" id="adhesion-modal-content">
    <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mb-3 sm:hidden flex-shrink-0"></div>
    <div class="flex items-center justify-between mb-1">
      <h3 class="text-xl font-bold text-slate-900 dark:text-white">Formulaire d'adhésion</h3>
      <button type="button" onclick="closeAdhesionModal()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Devenez membre officiel du Dahira A Khiba-i Rassouloulahi (SAWS).</p>
    
    <form action="/pages/adhesion_action.php" method="POST" enctype="multipart/form-data" class="space-y-3.5" id="adhesion-form">
      <?= csrfField() ?>

      <!-- 1. SÉLECTEUR DE SITUATION (NOUVELLE vs CARTE PHYSIQUE EXISTANTE) -->
      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Votre situation :</label>
        <div class="grid grid-cols-2 gap-2">
          <label id="label-adh-nouvelle" class="relative flex flex-col p-3 rounded-2xl border-2 cursor-pointer transition-all border-primary-900 bg-primary-50/20 dark:bg-primary-950/30">
            <input type="radio" name="type_adhesion" value="nouvelle" checked class="sr-only" onchange="toggleTypeAdhesion('nouvelle')">
            <span class="text-xs font-bold text-slate-900 dark:text-white mb-0.5">Nouvelle adhésion</span>
            <span class="text-[11px] font-semibold text-primary-900 dark:text-blue-400"><?= number_format($fraisAdh, 0, ',', ' ') ?> FCFA</span>
          </label>
          
          <label id="label-adh-existante" class="relative flex flex-col p-3 rounded-2xl border-2 cursor-pointer transition-all border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:border-emerald-400">
            <input type="radio" name="type_adhesion" value="carte_existante" class="sr-only" onchange="toggleTypeAdhesion('carte_existante')">
            <span class="text-xs font-bold text-slate-900 dark:text-white mb-0.5">J'ai déjà ma carte</span>
            <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Carte physique</span>
          </label>
        </div>
      </div>

      <!-- Informations personnelles -->
      <div>
        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nom complet</label>
        <input type="text" value="<?= e($_SESSION['user_nom'] ?? '') ?>" readonly class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none opacity-70 cursor-not-allowed">
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Téléphone *</label>
          <input type="tel" name="telephone" required placeholder="+221 77 000 00 00" value="<?= e($membreData['telephone'] ?? '') ?>" class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-xs focus:ring-2 focus:ring-primary-500 outline-none transition-shadow">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Adresse complète *</label>
          <input type="text" name="adresse" required placeholder="Ex: Dakar, Pikine..." value="<?= e($membreData['adresse'] ?? '') ?>" class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-xs focus:ring-2 focus:ring-primary-500 outline-none transition-shadow">
        </div>
      </div>

      <!-- BLOC POUR MEMBRES AYANT DÉJÀ UNE CARTE PHYSIQUE -->
      <div id="bloc-carte-existante" class="hidden bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-300/80 dark:border-emerald-800/80 rounded-2xl p-3.5 space-y-2.5 fade-in-up">
        <div class="flex items-start gap-2.5">
          <div class="w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          </div>
          <div>
            <p class="text-xs font-bold text-emerald-900 dark:text-emerald-200">Carte physique (Vérification requise)</p>
            <p class="text-[11px] text-emerald-700 dark:text-emerald-300/90 leading-tight">Vous disposez déjà d'une carte physique officielle. Votre demande sera vérifiée et validée par l'administrateur.</p>
          </div>
        </div>
      </div>
      
      <!-- BLOC PAIEMENT FRAIS D'ADHÉSION (WAVE EXCLUSIF) -->
      <div id="bloc-paiement-adhesion" class="bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 p-4 rounded-2xl space-y-3">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs text-slate-900 dark:text-white font-bold">Frais d'adhésion : <?= number_format($fraisAdh, 0, ',', ' ') ?> FCFA</p>
            <p class="text-[10px] text-slate-400">Paiement direct et sécurisé via Wave</p>
          </div>
          <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 px-2 py-0.5 rounded-md">Requis</span>
        </div>

        <!-- Bouton Wave avec ouverture automatique et soumission directe -->
        <div>
          <button type="button" onclick="payerWaveEtSoumettre()" id="btn-payer-wave" 
                  class="w-full bg-[#1da1f2] hover:bg-[#1a94df] text-white font-bold p-3 rounded-xl flex items-center justify-between transition-all active:scale-[0.98] shadow-md hover:shadow-lg group">
            <div class="flex items-center gap-2.5">
              <img src="<?= e($waveCfg['logo']) ?>" alt="Wave" class="h-6 object-contain">
              <div class="text-left">
                <span class="block text-xs sm:text-sm font-bold leading-tight">Payer via Wave (<?= number_format($fraisAdh, 0, ',', ' ') ?> FCFA)</span>
              </div>
            </div>
            <span class="text-[11px] font-bold bg-white/20 px-2.5 py-1 rounded-lg border border-white/30 flex items-center gap-1 group-hover:translate-x-0.5 transition-transform">
              Payer & Envoyer →
            </span>
          </button>
        </div>
      </div>
      
      <div class="pt-1">
        <button type="submit" id="btn-submit-adhesion" class="w-full bg-primary-900 hover:bg-primary-800 text-white font-bold py-3 rounded-xl transition-colors shadow-lg active:scale-95 hidden items-center justify-center gap-2 text-xs sm:text-sm">
          <span>Soumettre ma carte pour vérification</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleTypeAdhesion(type) {
  const isPhysique = (type === 'carte_existante');
  const blocPhysique = document.getElementById('bloc-carte-existante');
  const blocPaiement = document.getElementById('bloc-paiement-adhesion');
  const btnSubmit = document.getElementById('btn-submit-adhesion');
  const labelNouvelle = document.getElementById('label-adh-nouvelle');
  const labelExistante = document.getElementById('label-adh-existante');

  if (isPhysique) {
    if (blocPhysique) blocPhysique.classList.remove('hidden');
    if (blocPaiement) blocPaiement.classList.add('hidden');
    if (btnSubmit) {
      btnSubmit.classList.remove('hidden');
      btnSubmit.classList.add('flex');
      btnSubmit.innerHTML = '<span>Soumettre ma carte pour vérification</span>';
    }

    // Styles des labels radio
    labelNouvelle.className = 'relative flex flex-col p-3 rounded-2xl border-2 cursor-pointer transition-all border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800';
    labelExistante.className = 'relative flex flex-col p-3 rounded-2xl border-2 cursor-pointer transition-all border-emerald-500 bg-emerald-50/20 dark:bg-emerald-950/30';
  } else {
    if (blocPhysique) blocPhysique.classList.add('hidden');
    if (blocPaiement) blocPaiement.classList.remove('hidden');
    if (btnSubmit) {
      btnSubmit.classList.add('hidden');
      btnSubmit.classList.remove('flex');
    }

    // Styles des labels radio
    labelNouvelle.className = 'relative flex flex-col p-3 rounded-2xl border-2 cursor-pointer transition-all border-primary-900 bg-primary-50/20 dark:bg-primary-950/30';
    labelExistante.className = 'relative flex flex-col p-3 rounded-2xl border-2 cursor-pointer transition-all border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:border-emerald-400';
  }
}

function payerWaveEtSoumettre() {
  const form = document.getElementById('adhesion-form');
  if (!form) return;

  const tel = form.querySelector('input[name="telephone"]');
  const adr = form.querySelector('input[name="adresse"]');

  if (!tel || !tel.value.trim()) {
    alert('Veuillez saisir votre numéro de téléphone.');
    if (tel) tel.focus();
    return;
  }
  if (!adr || !adr.value.trim()) {
    alert('Veuillez renseigner votre adresse.');
    if (adr) adr.focus();
    return;
  }

  const btn = document.getElementById('btn-payer-wave');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="flex items-center gap-2 justify-center py-1 w-full"><span class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span><span>Ouverture de Wave & transmission...</span></span>';
  }

  const waveUrl = <?= json_encode($waveCfg['lien_paiement']) ?>;
  window.open(waveUrl, '_blank');

  setTimeout(() => {
    form.submit();
  }, 400);
}

if (window.location.hash === '#adhesion') {
  setTimeout(openAdhesionModal, 400);
}
</script>
<?php endif; ?>

<?php require_once __DIR__ .'/../includes/footer.php'; ?>
