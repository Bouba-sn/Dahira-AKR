<?php
// includes/navbar-bottom.php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

function navItem(string $href, string $icon, string $label, string $page, string $current): string {
    $active = str_contains($current, $page);
    $activeClass = $active
        ? 'text-primary-900 dark:text-blue-300'
        : 'text-slate-400 dark:text-slate-500';
    $indicator = $active
        ? '<span class="w-1 h-1 rounded-full bg-primary-900 dark:bg-blue-300 mt-0.5"></span>'
        : '<span class="w-1 h-1 mt-0.5"></span>';

    return <<<HTML
    <a href="{$href}" class="flex flex-col items-center gap-0.5 py-2 px-3 flex-1 {$activeClass} transition-colors duration-200">
        {$icon}
        <span class="text-[10px] font-medium leading-tight">{$label}</span>
        {$indicator}
    </a>
    HTML;
}
?>

<nav id="bottom-nav" class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] z-50 bg-white/95 dark:bg-slate-900/95 border-t border-slate-100 dark:border-slate-800"
     style="padding-bottom: env(safe-area-inset-bottom);">
    <div class="flex items-center justify-around">

        <!-- Accueil -->
        <a href="/pages/accueil.php" class="flex flex-col items-center gap-0.5 py-2 px-3 flex-1 transition-colors duration-200
            <?= in_array($currentPage, ['accueil', 'index']) ? 'text-primary-900 dark:text-blue-300' : 'text-slate-400 dark:text-slate-500' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="<?= in_array($currentPage, ['accueil','index']) ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span class="text-[10px] font-medium">Accueil</span>
            <?php if (in_array($currentPage, ['accueil','index'])): ?><span class="w-1 h-1 rounded-full bg-primary-900 dark:bg-blue-300 mt-0.5"></span><?php else: ?><span class="w-1 h-1 mt-0.5"></span><?php endif; ?>
        </a>

        <!-- Tidiany Way -->
        <a href="/pages/tidiany-way.php" class="flex flex-col items-center gap-0.5 py-2 px-3 flex-1 transition-colors duration-200
            <?= in_array($currentPage, ['tidiany-way','produit','panier','commande']) ? 'text-primary-900 dark:text-blue-300' : 'text-slate-400 dark:text-slate-500' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="<?= in_array($currentPage, ['tidiany-way','produit','panier','commande']) ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" x2="21" y1="6" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
            <span class="text-[10px] font-medium">Boutique</span>
            <?php if (in_array($currentPage, ['tidiany-way','produit','panier','commande'])): ?><span class="w-1 h-1 rounded-full bg-primary-900 dark:bg-blue-300 mt-0.5"></span><?php else: ?><span class="w-1 h-1 mt-0.5"></span><?php endif; ?>
        </a>

        <!-- Biographie -->
        <a href="/pages/biographie.php" class="flex flex-col items-center gap-0.5 py-2 px-3 flex-1 transition-colors duration-200
            <?= in_array($currentPage, ['biographie']) ? 'text-primary-900 dark:text-blue-300' : 'text-slate-400 dark:text-slate-500' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="<?= in_array($currentPage, ['biographie']) ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><path d="M9 14h6"></path><path d="M9 10h6"></path><path d="M9 18h6"></path>
            </svg>
            <span class="text-[10px] font-medium">Biographie</span>
            <?php if (in_array($currentPage, ['biographie'])): ?><span class="w-1 h-1 rounded-full bg-primary-900 dark:bg-blue-300 mt-0.5"></span><?php else: ?><span class="w-1 h-1 mt-0.5"></span><?php endif; ?>
        </a>

        <!-- Paramètres -->
        <a href="/pages/parametres.php" class="flex flex-col items-center gap-0.5 py-2 px-3 flex-1 transition-colors duration-200
            <?= $currentPage === 'parametres' ? 'text-primary-900 dark:text-blue-300' : 'text-slate-400 dark:text-slate-500' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="<?= $currentPage === 'parametres' ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            <span class="text-[10px] font-medium">Paramètres</span>
            <?php if ($currentPage === 'parametres'): ?><span class="w-1 h-1 rounded-full bg-primary-900 dark:bg-blue-300 mt-0.5"></span><?php else: ?><span class="w-1 h-1 mt-0.5"></span><?php endif; ?>
        </a>

    </div>
</nav>
