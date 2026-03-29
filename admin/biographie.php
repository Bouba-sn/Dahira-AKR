<?php
// admin/biographie.php
$pageTitle = 'Gestion Biographie';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$jsonPath = __DIR__ . '/../biographie.json';
$data = ['famille' => []];

if (file_exists($jsonPath)) {
    $content = file_get_contents($jsonPath);
    if ($content) {
        $data = json_decode($content, true) ?? ['famille' => []];
    }
}

// Traitement du formulaire POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $nom = $_POST['nom'] ?? '';
        $pere = $_POST['pere'] ?? '';
        $naissance = $_POST['annee_naissance'] !== '' ? (int)$_POST['annee_naissance'] : null;
        $mort = $_POST['annee_mort'] !== '' ? (int)$_POST['annee_mort'] : null;
        $bio = $_POST['biographie'] ?? '';
        
        $marabout = [
            'nom' => $nom,
            'pere' => $pere,
            'annee_naissance' => $naissance,
            'annee_mort' => $mort,
            'biographie' => $bio
        ];

        // Gestion photo
        $photo = '';
        if ($action === 'edit') {
            $index = (int)$_POST['index'];
            if (isset($data['famille'][$index]['photo'])) {
                $photo = $data['famille'][$index]['photo'];
            }
        }

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('marabout_') . '.' . $ext;
            $dest = __DIR__ . '/../assets/uploads/' . $filename;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                $photo = $filename;
            }
        }
        if (!empty($photo)) {
            $marabout['photo'] = $photo;
        }

        if ($action === 'add') {
            $data['famille'][] = $marabout;
        } else {
            $index = (int)$_POST['index'];
            $data['famille'][$index] = $marabout;
        }

        file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        header('Location: /admin/biographie.php');
        exit;
    }

    if ($action === 'delete') {
        $index = (int)$_POST['index'];
        if (isset($data['famille'][$index])) {
            array_splice($data['famille'], $index, 1);
            file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        header('Location: /admin/biographie.php');
        exit;
    }
}

$famille = $data['famille'];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- TOP BAR ADMIN -->
<div class="sticky top-0 z-40 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 px-4">
    <div class="flex items-center h-14 gap-3">
        <a href="/admin/dashboard.php" class="w-8 h-8 flex items-center justify-center text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 rounded-full active:scale-95 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h1 class="font-bold text-slate-900 dark:text-white flex-1 text-sm">Gestion des Marabouts</h1>
        <button onclick="openModal('add')" class="text-xs bg-primary-900 hover:bg-primary-800 text-white px-3 py-1.5 rounded-lg active:scale-95 transition-transform font-medium flex items-center gap-1">
            <span>+</span> Ajouter
        </button>
    </div>
</div>

<main class="page-content bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="px-4 py-6">
        <div class="space-y-3">
            <?php foreach ($famille as $index => $marabout): ?>
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-100 dark:border-slate-700 flex items-center gap-4 shadow-sm">
                <div class="w-14 h-14 rounded-full bg-slate-100 dark:bg-slate-700 flex-shrink-0 flex items-center justify-center overflow-hidden border border-slate-200 dark:border-slate-600">
                    <?php if (!empty($marabout['photo'])): ?>
                    <img src="/assets/uploads/<?= e($marabout['photo']) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                    <span class="text-xl font-bold text-slate-400"><?= mb_strtoupper(mb_substr($marabout['nom'], 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-sm text-slate-900 dark:text-white truncate"><?= e($marabout['nom']) ?></p>
                    <?php if (!empty($marabout['pere'])): ?>
                    <p class="text-[11px] text-slate-500 truncate mb-1">Fils de <?= e($marabout['pere']) ?></p>
                    <?php endif; ?>
                    <div class="flex gap-2 text-xs">
                        <button onclick="openModal('edit', <?= $index ?>, <?= htmlspecialchars(json_encode($marabout)) ?>)" class="text-blue-600 font-medium px-2 py-1 bg-blue-50 dark:bg-blue-900/30 rounded">Modifier</button>
                        <form action="" method="POST" onsubmit="return confirm('Supprimer ce marabout ?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="index" value="<?= $index ?>">
                            <button type="submit" class="text-red-600 font-medium px-2 py-1 bg-red-50 dark:bg-red-900/30 rounded">Supprimer</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($famille)): ?>
            <div class="text-center py-10 opacity-50">Aucun membre enregistré.</div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- MODAL FORM -->
<div id="bio-modal" class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300">
    <div class="bg-white dark:bg-slate-900 w-full sm:w-[32rem] max-h-[90vh] overflow-y-auto rounded-t-3xl sm:rounded-3xl shadow-2xl transform translate-y-full sm:translate-y-4 scale-95 opacity-0 transition-all duration-300 p-6" id="bio-modal-content">
        <div class="flex items-center justify-between mb-4">
            <h3 id="modal-title" class="text-xl font-bold text-slate-900 dark:text-white"></h3>
            <button onclick="closeModal()" class="w-8 h-8 flex items-center justify-center bg-slate-100 dark:bg-slate-800 rounded-full">✕</button>
        </div>
        
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" id="modal-action" value="">
            <input type="hidden" name="index" id="modal-index" value="">
            
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nom du marabout *</label>
                <input type="text" name="nom" id="form-nom" required class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Père (Fils de ...)</label>
                <input type="text" name="pere" id="form-pere" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Année Naissance</label>
                    <input type="number" name="annee_naissance" id="form-naissance" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Année Décès</label>
                    <input type="number" name="annee_mort" id="form-mort" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Biographie</label>
                <textarea name="biographie" id="form-bio" rows="4" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 h-32"></textarea>
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Photo (optionnelle)</label>
                <input type="file" name="photo" accept="image/*" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm text-slate-500">
            </div>

            <button type="submit" class="w-full bg-primary-900 text-white font-bold py-3.5 rounded-xl mt-4 active:scale-95 transition-transform">
                Sauvegarder
            </button>
        </form>
    </div>
</div>

<script>
function openModal(action, index = null, data = null) {
    const modal = document.getElementById('bio-modal');
    const content = document.getElementById('bio-modal-content');
    
    document.getElementById('modal-action').value = action;
    document.getElementById('modal-title').textContent = action === 'add' ? 'Ajouter un marabout' : 'Modifier le marabout';
    
    if (action === 'edit' && data) {
        document.getElementById('modal-index').value = index;
        document.getElementById('form-nom').value = data.nom || '';
        document.getElementById('form-pere').value = data.pere || '';
        document.getElementById('form-naissance').value = data.annee_naissance || '';
        document.getElementById('form-mort').value = data.annee_mort || '';
        document.getElementById('form-bio').value = data.biographie || '';
    } else {
        document.getElementById('modal-index').value = '';
        document.getElementById('form-nom').value = '';
        document.getElementById('form-pere').value = '';
        document.getElementById('form-naissance').value = '';
        document.getElementById('form-mort').value = '';
        document.getElementById('form-bio').value = '';
    }
    
    modal.classList.remove('opacity-0', 'pointer-events-none');
    setTimeout(() => {
        content.classList.remove('translate-y-full', 'sm:translate-y-4', 'scale-95', 'opacity-0');
        content.classList.add('translate-y-0', 'scale-100', 'opacity-100');
    }, 10);
}

function closeModal() {
    const modal = document.getElementById('bio-modal');
    const content = document.getElementById('bio-modal-content');
    
    content.classList.remove('translate-y-0', 'scale-100', 'opacity-100');
    content.classList.add('translate-y-full', 'sm:translate-y-4', 'scale-95', 'opacity-0');
    
    setTimeout(() => {
        modal.classList.add('opacity-0', 'pointer-events-none');
    }, 300);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
