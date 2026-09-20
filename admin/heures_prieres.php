<?php
// admin/heures_prieres.php
$pageTitle = 'Gestion Heures de Prière';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$msg = '';

// Toggle actif
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id']) && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
  $id = (int)$_POST['toggle_id'];
  $st = $pdo->prepare("SELECT actif FROM heures_prieres WHERE id=?");
  $st->execute([$id]);
  if ($st->fetchColumn() == 0) {
    $pdo->query("UPDATE heures_prieres SET actif=0");
    $pdo->prepare("UPDATE heures_prieres SET actif=1 WHERE id=?")->execute([$id]);
    $msg = 'Horaires actives pour l\'accueil.';
  }
}

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
  $pdo->prepare("DELETE FROM heures_prieres WHERE id=?")->execute([(int)$_POST['delete_id']]);
  $msg = 'Supprime.';
}

// CREATE / UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ??'')) {
  $id = (int)($_POST['id'] ?? 0);
  $date_debut = sanitize($_POST['date_debut'] ??'');
  $date_fin = sanitize($_POST['date_fin'] ??'');
  $fajr = sanitize($_POST['fajr'] ??'');
  $dhuhr = sanitize($_POST['dhuhr'] ??'');
  $asr = sanitize($_POST['asr'] ??'');
  $maghrib = sanitize($_POST['maghrib'] ??'');
  $isha = sanitize($_POST['isha'] ??'');
  
  // Rendre actif par défaut le nouveau
  $pdo->query("UPDATE heures_prieres SET actif=0");

  if ($id >0) {
    $sql = "UPDATE heures_prieres SET date_debut=?, date_fin=?, fajr=?, dhuhr=?, asr=?, maghrib=?, isha=?, actif=1 WHERE id=?";
    $pdo->prepare($sql)->execute([$date_debut, $date_fin, $fajr, $dhuhr, $asr, $maghrib, $isha, $id]);
    $msg = 'Horaires modifiés et activés.';
  } else {
    $sql = "INSERT INTO heures_prieres (date_debut, date_fin, fajr, dhuhr, asr, maghrib, isha, actif) VALUES (?,?,?,?,?,?,?, 1)";
    $pdo->prepare($sql)->execute([$date_debut, $date_fin, $fajr, $dhuhr, $asr, $maghrib, $isha]);
    $msg = 'Nouveaux horaires ajoutés et activés.';
  }
}

$prieres = $pdo->query("SELECT * FROM heures_prieres ORDER BY created_at DESC")->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
  $s = $pdo->prepare("SELECT * FROM heures_prieres WHERE id=?");
  $s->execute([(int)$_GET['edit']]);
  $edit = $s->fetch();
}
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-primary-900 text-white px-4" style="height: calc(3.5rem + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px);">
  <div class="flex items-center h-14 gap-3">
    <a href="/admin/dashboard.php" class="w-8 h-8 flex items-center justify-center text-white/70">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
    </a>
    <h1 class="font-bold text-sm flex-1">Heures de Prière</h1>
  </div>
</div>

<main class="pb-32 bg-slate-50 dark:bg-slate-950 min-h-screen px-4">

  <?php if ($msg): ?>
  <div class="flash-success-box mt-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-700 dark:text-green-400 text-sm flex items-center justify-between gap-2">
      <span><?= e($msg) ?></span>
      <button type="button" onclick="dismissFlash(this.closest('.flash-success-box'))" class="text-green-600 hover:text-green-800 dark:hover:text-green-200 p-1 transition-colors" title="Fermer">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
  </div>
  <?php endif; ?>

  <!-- FORMULAIRE AJOUT/ÉDITION -->
  <?php if (isset($_GET['add']) || $edit || empty($prieres)): ?>
  <div class="mt-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-4">
    <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-4"><?= $edit ?'Modifier':'Configurer'?> les horaires</h2>
    <form method="POST"class="space-y-4">
      <?= csrfField() ?>
      <?php if ($edit): ?><input type="hidden"name="id"value="<?= $edit['id'] ?>"><?php endif; ?>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Date de début</label>
          <input type="date"name="date_debut"required value="<?= e($edit['date_debut'] ??'') ?>"
              class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none">
        </div>
        <div>
          <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Date de fin</label>
          <input type="date"name="date_fin"required value="<?= e($edit['date_fin'] ??'') ?>"
              class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <!-- Fajr -->
        <div>
          <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Fajr</label>
          <input type="time"name="fajr"required value="<?= $edit ? date('H:i', strtotime($edit['fajr'])) :''?>"
              class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500">
        </div>
        <!-- Dhuhr -->
        <div>
          <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Dhuhr ️</label>
          <input type="time"name="dhuhr"required value="<?= $edit ? date('H:i', strtotime($edit['dhuhr'])) :''?>"
              class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500">
        </div>
        <!-- Asr -->
        <div>
          <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Asr</label>
          <input type="time"name="asr"required value="<?= $edit ? date('H:i', strtotime($edit['asr'])) :''?>"
              class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500">
        </div>
        <!-- Maghrib -->
        <div>
          <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Maghrib</label>
          <input type="time"name="maghrib"required value="<?= $edit ? date('H:i', strtotime($edit['maghrib'])) :''?>"
              class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500">
        </div>
        <!-- Isha -->
        <div class="col-span-2">
          <label class="text-xs font-medium text-slate-600 dark:text-slate-300 block mb-1">Isha</label>
          <input type="time"name="isha"required value="<?= $edit ? date('H:i', strtotime($edit['isha'])) :''?>"
              class="w-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500">
        </div>
      </div>

      <div class="flex gap-2 pt-2">
        <button type="submit"class="flex-1 bg-primary-900 text-white rounded-xl py-2.5 text-sm font-semibold shadow-md active:shadow-md transition-all active:scale-95">
          <?= $edit ?'Mettre à jour':'Enregistrer'?>
        </button>
        <a href="/admin/heures_prieres.php"class="flex-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl py-2.5 text-sm font-medium text-center active:scale-95 transition-all shadow-sm hover:shadow-md active:shadow-md">
          Annuler
        </a>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- LISTE DES HORAIRES -->
  <div class="mt-4 space-y-3">
    <?php foreach ($prieres as $p): ?>
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border <?= $p['actif'] ?'border-primary-500 ring-2 ring-primary-500/20':'border-slate-100 dark:border-slate-700 opacity-60'?>shadow-sm">
      
      <div class="flex justify-between items-start mb-3">
        <div>
          <?php 
          $moisAdmin = ['01'=>'janvier','02'=>'février','03'=>'mars','04'=>'avril','05'=>'mai','06'=>'juin','07'=>'juillet','08'=>'août','09'=>'septembre','10'=>'octobre','11'=>'novembre','12'=>'décembre'];
          $ddAdmin = date('d', strtotime($p['date_debut']));
          $dfAdmin = date('d', strtotime($p['date_fin'])) .' '. $moisAdmin[date('m', strtotime($p['date_fin']))];
          ?>
          <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100">Du <?= $ddAdmin ?>au <?= $dfAdmin ?></h3>
          <?php if($p['actif']): ?>
            <span class="text-[10px] font-bold text-primary-700 bg-primary-100 px-2 py-0.5 rounded-full mt-1 inline-block">Actif sur l'accueil</span>
          <?php endif; ?>
        </div>
        <div class="flex gap-1">
          <?php if(!$p['actif']): ?>
          <form method="POST" class="inline">
            <?= csrfField() ?>
            <input type="hidden" name="toggle_id" value="<?= $p['id'] ?>">
            <button type="submit" class="w-8 h-8 rounded-lg bg-green-50 text-green-600 flex items-center justify-center text-sm" title="Activer">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </button>
          </form>
          <?php endif; ?>
          <a href="?edit=<?= $p['id'] ?>" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-sm" title="Modifier">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
          </a>
          <form method="POST" onsubmit="return confirm('Confirmer la suppression ?')" class="inline">
            <?= csrfField() ?>
            <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
            <button type="submit" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 flex items-center justify-center text-sm" title="Supprimer">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </form>
        </div>
      </div>

      <!-- Grille des heures -->
      <div class="grid grid-cols-5 gap-1 text-center bg-slate-50 dark:bg-slate-900 rounded-xl p-2 border border-slate-100 dark:border-slate-700">
        <div>
          <p class="text-[9px] text-slate-400 uppercase font-bold mb-0.5">Fajr</p>
          <p class="text-xs font-bold text-slate-800 dark:text-slate-200"><?= date('H:i', strtotime($p['fajr'])) ?></p>
        </div>
        <div>
          <p class="text-[9px] text-slate-400 uppercase font-bold mb-0.5">Dhuhr</p>
          <p class="text-xs font-bold text-slate-800 dark:text-slate-200"><?= date('H:i', strtotime($p['dhuhr'])) ?></p>
        </div>
        <div>
          <p class="text-[9px] text-slate-400 uppercase font-bold mb-0.5">Asr</p>
          <p class="text-xs font-bold text-slate-800 dark:text-slate-200"><?= date('H:i', strtotime($p['asr'])) ?></p>
        </div>
        <div>
          <p class="text-[9px] text-slate-400 uppercase font-bold mb-0.5">Maghrib</p>
          <p class="text-xs font-bold text-slate-800 dark:text-slate-200"><?= date('H:i', strtotime($p['maghrib'])) ?></p>
        </div>
        <div>
          <p class="text-[9px] text-slate-400 uppercase font-bold mb-0.5">Isha</p>
          <p class="text-xs font-bold text-slate-800 dark:text-slate-200"><?= date('H:i', strtotime($p['isha'])) ?></p>
        </div>
      </div>
      
    </div>
    <?php endforeach; ?>
    
    <?php if(empty($prieres)): ?>
    <div class="text-center py-8 text-slate-400 text-sm">Aucun horaire configuré.</div>
    <?php endif; ?>
  </div>

</main>

<?php require_once __DIR__ .'/../includes/footer.php'; ?>
