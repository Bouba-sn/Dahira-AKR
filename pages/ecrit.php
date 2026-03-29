<?php
// pages/ecrit.php
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT e.*, a.nom as auteur_nom, a.id as auteur_id FROM ecrits e JOIN auteurs a ON a.id = e.auteur_id WHERE e.id = ?");
$stmt->execute([$id]);
$ecrit = $stmt->fetch();

if (!$ecrit) { header('Location: /pages/bibliotheque.php'); exit; }

$pageTitle = $ecrit['titre'];
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 px-4">
    <div class="flex items-center h-14 gap-3">
        <a href="/pages/auteur.php?id=<?= $ecrit['auteur_id'] ?>" class="w-8 h-8 flex items-center justify-center text-slate-600 dark:text-slate-300">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <div class="flex-1 min-w-0">
            <h1 class="font-bold text-slate-900 dark:text-white text-sm truncate"><?= e($ecrit['titre']) ?></h1>
            <p class="text-xs text-slate-400 truncate">— <?= e($ecrit['auteur_nom']) ?></p>
        </div>
        <!-- Taille du texte -->
        <div class="flex items-center gap-1">
            <button onclick="changeFontSize(-1)" class="w-7 h-7 bg-slate-100 dark:bg-slate-700 rounded-lg text-xs flex items-center justify-center font-bold text-slate-600 dark:text-slate-300">A-</button>
            <button onclick="changeFontSize(1)"  class="w-7 h-7 bg-slate-100 dark:bg-slate-700 rounded-lg text-sm flex items-center justify-center font-bold text-slate-600 dark:text-slate-300">A+</button>
        </div>
    </div>
</div>

<main class="page-content" id="ecrit-content">

    <!-- EN-TÊTE -->
    <div class="bg-gradient-to-b from-emerald-900 to-emerald-700 px-4 py-5 text-white">
        <?php if ($ecrit['titre_arabe']): ?>
        <p class="arabic text-xl text-center mb-2"><?= e($ecrit['titre_arabe']) ?></p>
        <?php endif; ?>
        <h2 class="text-base font-bold text-center"><?= e($ecrit['titre']) ?></h2>
        <p class="text-xs text-center opacity-70 mt-1">— <?= e($ecrit['auteur_nom']) ?></p>
    </div>

    <!-- ONGLETS LANGUE -->
    <div class="sticky top-14 z-30 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800">
        <div class="flex">
            <button id="tab-arabe" onclick="showTab('arabe')" class="flex-1 py-2.5 text-sm font-medium text-center border-b-2 border-emerald-600 text-emerald-600 transition-colors">
                🌙 Arabe
            </button>
            <button id="tab-francais" onclick="showTab('francais')" class="flex-1 py-2.5 text-sm font-medium text-center border-b-2 border-transparent text-slate-400 transition-colors">
                🇫🇷 Français
            </button>
            <button id="tab-bilingue" onclick="showTab('bilingue')" class="flex-1 py-2.5 text-sm font-medium text-center border-b-2 border-transparent text-slate-400 transition-colors">
                🔀 Bilingue
            </button>
        </div>
    </div>

    <!-- CONTENU ARABE -->
    <div id="content-arabe" class="px-4 py-5">
        <div id="arabic-text" class="arabic text-lg leading-loose text-slate-800 dark:text-slate-100" style="font-size: var(--arabic-size, 1.25rem);">
            <?= nl2br(e($ecrit['contenu_arabe'] ?? '')) ?>
        </div>
        <?php if (!$ecrit['contenu_arabe']): ?>
        <p class="text-center text-slate-400 py-8 text-sm">Texte arabe non disponible</p>
        <?php endif; ?>
    </div>

    <!-- CONTENU FRANÇAIS -->
    <div id="content-francais" class="px-4 py-5 hidden">
        <div id="french-text" class="text-base leading-relaxed text-slate-700 dark:text-slate-200" style="font-size: var(--french-size, 1rem);">
            <?= nl2br(e($ecrit['contenu_francais'] ?? '')) ?>
        </div>
        <?php if (!$ecrit['contenu_francais']): ?>
        <p class="text-center text-slate-400 py-8 text-sm">Traduction française non disponible</p>
        <?php endif; ?>
    </div>

    <!-- BILINGUE (côte à côte par paragraphe) -->
    <div id="content-bilingue" class="px-4 py-5 hidden">
        <?php
        $arabicLines  = explode("\n", $ecrit['contenu_arabe'] ?? '');
        $frenchLines  = explode("\n", $ecrit['contenu_francais'] ?? '');
        $maxLines     = max(count($arabicLines), count($frenchLines));
        for ($i = 0; $i < $maxLines; $i++):
            $ar = trim($arabicLines[$i] ?? '');
            $fr = trim($frenchLines[$i] ?? '');
            if (!$ar && !$fr) continue;
        ?>
        <div class="mb-5 border-b border-slate-100 dark:border-slate-700 pb-5">
            <?php if ($ar): ?>
            <p class="arabic text-lg leading-loose text-slate-800 dark:text-slate-100 mb-2"><?= e($ar) ?></p>
            <?php endif; ?>
            <?php if ($fr): ?>
            <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed"><?= e($fr) ?></p>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>

    <!-- ACTIONS -->
    <div class="mx-4 mb-4 flex gap-2">
        <button onclick="shareEcrit()" class="flex-1 py-2.5 bg-slate-100 dark:bg-slate-700 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-300 flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" x2="15.42" y1="13.51" y2="17.49"/><line x1="15.41" x2="8.59" y1="6.51" y2="10.49"/></svg>
            Partager
        </button>
        <a href="/pages/auteur.php?id=<?= $ecrit['auteur_id'] ?>" class="flex-1 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-medium flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
            Autres écrits
        </a>
    </div>

</main>

<script>
let arabicSize  = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--arabic-size') || '1.25');
let frenchSize  = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--french-size') || '1');

function showTab(tab) {
    ['arabe','francais','bilingue'].forEach(t => {
        document.getElementById('content-' + t).classList.toggle('hidden', t !== tab);
        const btn = document.getElementById('tab-' + t);
        btn.classList.toggle('border-emerald-600', t === tab);
        btn.classList.toggle('text-emerald-600', t === tab);
        btn.classList.toggle('border-transparent', t !== tab);
        btn.classList.toggle('text-slate-400', t !== tab);
    });
}

function changeFontSize(dir) {
    arabicSize = Math.max(0.9, Math.min(2.2, arabicSize + dir * 0.1));
    frenchSize = Math.max(0.75, Math.min(1.8, frenchSize + dir * 0.1));
    document.getElementById('arabic-text').style.fontSize = arabicSize + 'rem';
    document.getElementById('french-text').style.fontSize = frenchSize + 'rem';
}

function shareEcrit() {
    if (navigator.share) {
        navigator.share({
            title: '<?= addslashes($ecrit['titre']) ?>',
            text: '<?= addslashes(mb_substr($ecrit['contenu_francais'] ?? $ecrit['contenu_arabe'] ?? '', 0, 100)) ?>...',
            url: window.location.href
        });
    } else {
        navigator.clipboard?.writeText(window.location.href);
        showToast('🔗 Lien copié !');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
