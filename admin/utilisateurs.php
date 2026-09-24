<?php
// admin/utilisateurs.php
$pageTitle = 'Membres';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$msg = '';

// Changer le role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_role']) && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $uid = (int)$_POST['toggle_role'];
    if ($uid !== (int)$_SESSION['user_id']) {
        $cur = $pdo->prepare("SELECT role FROM utilisateurs WHERE id=?");
        $cur->execute([$uid]);
        $curRole = $cur->fetchColumn();
        $newRole = $curRole === 'admin' ? 'user' : 'admin';
        $pdo->prepare("UPDATE utilisateurs SET role=? WHERE id=?")->execute([$newRole, $uid]);
        $msg = "Role mis a jour en $newRole.";
    }
}



// Supprimer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $uid = (int)$_POST['delete_id'];
    if ($uid !== (int)$_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM utilisateurs WHERE id=?")->execute([$uid]);
        $msg = 'Membre supprime.';
    }
}

// Modifier la fonction du membre pour la carte officielle (gérée par l'administrateur)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_fonction']) && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $uid = (int)($_POST['user_id'] ?? 0);
    $nouvelleFonction = trim($_POST['fonction'] ?? '');
    if (empty($nouvelleFonction) || in_array(strtolower($nouvelleFonction), ['enfant', 'bureau', 'simple'])) {
        $nouvelleFonction = 'Membre';
    }
    $pdo->prepare("UPDATE utilisateurs SET fonction = ? WHERE id = ?")->execute([$nouvelleFonction, $uid]);
    $msg = 'Fonction sur la carte mise à jour avec succès : ' . $nouvelleFonction;
}

$search = sanitize($_GET['q'] ?? '');
$where  = $search ? "WHERE nom LIKE ? OR email LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%"] : [];
$stmt   = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM commandes c WHERE c.user_id=u.id) as nb_commandes FROM utilisateurs u $where ORDER BY u.created_at DESC");
$stmt->execute($params);
$membres = $stmt->fetchAll();
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-primary-900 text-white px-4" style="height: calc(3.5rem + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px);">
    <div class="flex items-center h-14 gap-3">
        <a href="/admin/dashboard.php" class="w-8 h-8 flex items-center justify-center text-white/70">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h1 class="font-bold text-sm flex-1">Membres</h1>
        <span class="text-xs opacity-70"><?= count($membres) ?></span>
    </div>
</div>

<main class="pb-32 bg-slate-50 dark:bg-slate-950 min-h-screen px-4">

    <?php if ($msg): ?>
    <div class="flash-success-box mt-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 rounded-xl text-green-700 text-sm flex items-center justify-between gap-2">
        <span><?= e($msg) ?></span>
        <button type="button" onclick="dismissFlash(this.closest('.flash-success-box'))" class="text-green-600 hover:text-green-800 dark:hover:text-green-200 p-1 transition-colors" title="Fermer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <?php endif; ?>

    <!-- RECHERCHE -->
    <div class="mt-4">
        <form method="GET" class="relative">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Rechercher un membre..."
                   class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl pl-9 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none">
            <svg class="absolute left-3 top-3 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </form>
    </div>

    <!-- LISTE -->
    <div class="mt-3 space-y-2">
    <?php foreach ($membres as $m): ?>
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 p-3 flex items-center gap-3">
        <div class="w-10 h-10 rounded-full <?= $m['role'] === 'admin' ? 'bg-gold-400 dark:bg-yellow-600' : 'bg-primary-900' ?> flex items-center justify-center text-white font-bold flex-shrink-0">
            <?= strtoupper(substr($m['nom'], 0, 1)) ?>
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                <p class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate"><?= e(trim(($m['prenom'] ?? '') . ' ' . ($m['nom'] ?? ''))) ?></p>
                <?php if ($m['role'] === 'admin'): ?>
                <span class="text-[9px] bg-yellow-100 dark:bg-yellow-950/60 text-yellow-700 dark:text-yellow-300 border border-yellow-300/60 px-1.5 py-0.2 rounded-full font-bold">ADMIN</span>
                <?php endif; ?>
                <?php if ($m['statut_adhesion'] === 'membre'): ?>
                <span class="text-[9px] bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-300/80 px-1.5 py-0.2 rounded-full font-bold">MEMBRE</span>
                <?php elseif ($m['statut_adhesion'] === 'en_attente'): ?>
                <span class="text-[9px] bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-300/80 px-1.5 py-0.2 rounded-full font-bold">EN ATTENTE</span>
                <?php endif; ?>
                <?php if (!empty($m['carte_physique'])): ?>
                <span class="text-[9px] bg-teal-50 dark:bg-teal-950/60 text-teal-700 dark:text-teal-300 border border-teal-300/80 px-1.5 py-0.2 rounded-full font-bold" title="Détient une carte physique officielle">
                    CARTE PHYSIQUE<?= !empty($m['numero_carte']) ? ' (' . e($m['numero_carte']) . ')' : '' ?>
                </span>
                <?php endif; ?>
                <span class="text-[9px] bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 px-1.5 py-0.2 rounded-full font-medium" title="Fonction sur la carte de membre">
                    <?= e(!empty($m['fonction']) && !in_array(strtolower($m['fonction']), ['enfant','bureau','simple']) ? $m['fonction'] : 'Membre') ?>
                </span>
            </div>
            <p class="text-xs text-slate-400 truncate"><?= e($m['email']) ?></p>
            <p class="text-xs text-slate-400">Inscrit le <?= date('d/m/Y', strtotime($m['created_at'])) ?> · <?= $m['nb_commandes'] ?> commande(s)</p>
        </div>
        <div class="flex gap-1 flex-shrink-0">
            <?php if ($m['statut_adhesion'] === 'en_attente'): ?>
            <!-- Valider adhésion avec catégorie -->
            <button type="button" onclick="openValiderModal(<?= $m['id'] ?>, '<?= e(addslashes(trim(($m['prenom'] ?? '') . ' ' . ($m['nom'] ?? '')))) ?>', '<?= e($m['categorie_membre'] ?? 'simple') ?>')"
               class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-xs transition-colors shadow-xs" title="Valider l'adhésion et définir la catégorie">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </button>
            <?php endif; ?>

            <!-- Modifier la fonction pour la carte -->
            <button type="button" onclick="openFonctionModal(<?= $m['id'] ?>, '<?= e(addslashes(trim(($m['prenom'] ?? '') . ' ' . ($m['nom'] ?? '')))) ?>', '<?= e(addslashes(!empty($m['fonction']) && !in_array(strtolower($m['fonction']), ['enfant','bureau','simple']) ? $m['fonction'] : 'Membre')) ?>')"
               class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xs transition-colors" title="Modifier la fonction sur la carte de membre">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            </button>



            <?php if ($m['id'] != $_SESSION['user_id']): ?>
            <form method="POST" class="inline">
                <?= csrfField() ?>
                <input type="hidden" name="toggle_role" value="<?= $m['id'] ?>">
                <button type="submit" title="Changer le rôle admin/user"
                   class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-900/30 text-amber-600 flex items-center justify-center text-xs">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </button>
            </form>
            <form method="POST" onsubmit="return confirm('Supprimer ce membre ?')" class="inline">
                <?= csrfField() ?>
                <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
                <button type="submit" class="w-7 h-7 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-500 flex items-center justify-center text-xs" title="Supprimer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </form>
            <?php else: ?>
            <span class="text-[10px] text-slate-300 px-2">Vous</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($membres)): ?>
    <div class="text-center py-10 text-slate-400">
        <div class="w-12 h-12 mx-auto mb-2 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        </div>
        <p class="text-sm">Aucun membre trouvé</p>
    </div>
    <?php endif; ?>
    </div>

</main>

<!-- MODAL MODIFICATION DE FONCTION (ADMIN) -->
<div id="modal-fonction" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-sm w-full p-5 shadow-2xl border border-slate-100 dark:border-slate-700 animate-in fade-in duration-200">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-700 mb-3">
            <h3 class="font-bold text-sm text-slate-900 dark:text-white">Fonction sur la carte</h3>
            <button type="button" onclick="closeFonctionModal()" class="w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <p id="modal-fnc-membre" class="text-xs text-slate-500 dark:text-slate-400 mb-3 font-medium"></p>
        <form method="POST" class="space-y-3">
            <?= csrfField() ?>
            <input type="hidden" name="update_fonction" value="1">
            <input type="hidden" id="modal-fnc-uid" name="user_id" value="">
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Intitulé de la fonction *</label>
                <input type="text" id="modal-fnc-input" name="fonction" required placeholder="Ex: Membre, Président, Trésorier..."
                       class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <p class="text-[10px] text-slate-400 mt-1">Cette mention figurera sous FONCTION sur la carte de membre officielle.</p>
            </div>
            <div class="flex gap-2 pt-1">
                <button type="button" onclick="closeFonctionModal()" class="flex-1 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 text-xs font-semibold transition-colors">Annuler</button>
                <button type="submit" class="flex-1 py-2 rounded-xl bg-primary-900 hover:bg-primary-800 text-white text-xs font-bold transition-colors">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL VALIDATION ADHESION (ADMIN) -->
<div id="modal-valider-adhesion" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 max-w-sm w-full shadow-2xl border border-slate-100 dark:border-slate-700 animate-fade-in">
        <div class="flex items-center justify-between pb-2 mb-3 border-b border-slate-100 dark:border-slate-700">
            <h3 class="font-bold text-sm text-slate-900 dark:text-white">Valider l'Adhésion</h3>
            <button type="button" onclick="closeValiderModal()" class="w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center">
                ✕
            </button>
        </div>
        <form method="POST" action="/admin/valider_adhesion.php" class="space-y-3">
            <?= csrfField() ?>
            <input type="hidden" id="valider-user-id" name="user_id" value="">
            <input type="hidden" name="redirect_to" value="/admin/utilisateurs.php">
            
            <p class="text-xs text-slate-600 dark:text-slate-300">
                Membre : <strong id="valider-nom-label" class="text-slate-900 dark:text-white font-bold"></strong>
            </p>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Catégorie du membre *</label>
                <select name="categorie_membre" id="valider-cat-select" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white text-xs font-semibold rounded-xl px-3 py-2 outline-none focus:ring-2 focus:ring-primary-500">
                    <option value="simple">Membre Simple (30 000 F / an)</option>
                    <option value="bureau">Membre Bureau (35 000 F / an)</option>
                    <option value="enfant">Enfant (15 000 F / an)</option>
                </select>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="button" onclick="closeValiderModal()" class="flex-1 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold">Annuler</button>
                <button type="submit" name="action" value="valider" class="flex-1 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-xs">Valider</button>
            </div>
        </form>
    </div>
</div>

<script>
function openValiderModal(userId, nomMembre, currentCat) {
    document.getElementById('valider-user-id').value = userId;
    document.getElementById('valider-nom-label').textContent = nomMembre;
    const catSelect = document.getElementById('valider-cat-select');
    if (catSelect) catSelect.value = currentCat || 'simple';
    const m = document.getElementById('modal-valider-adhesion');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeValiderModal() {
    const m = document.getElementById('modal-valider-adhesion');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
function openFonctionModal(userId, nomMembre, fonctionActuelle) {
    document.getElementById('modal-fnc-uid').value = userId;
    document.getElementById('modal-fnc-membre').textContent = 'Membre : ' + nomMembre;
    document.getElementById('modal-fnc-input').value = fonctionActuelle;
    const m = document.getElementById('modal-fonction');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeFonctionModal() {
    const m = document.getElementById('modal-fonction');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
