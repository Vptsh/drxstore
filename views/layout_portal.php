<?php
/**
 * DRXStore v4 - Portal Layout (Supplier + Customer)
 * Developed by Vineet | psvineet@zohomail.in
 */
function _portalNavIcon(string $name): string {
    $icons = [
        'grid'   => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'orders' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/>',
        'user'   => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'mail'   => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/>',
        'return' => '<polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/>',
        'truck'  => '<rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    ];
    $p = $icons[$name] ?? '';
    return '<span class="ni"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$p.'</svg></span>';
}

function portalHeader(string $title, string $type, string $activePage, array $navItems, array $sessionKeys): void {
    $cfg       = getSettings();
    $storeName = $cfg['store_name'] ?? APP_NAME;
    $flash     = getFlash();
    $nameKey   = $sessionKeys['name'] ?? 'Admin';
    $userName  = $_SESSION[$nameKey] ?? 'User';
    $initial   = strtoupper(substr($userName, 0, 1));

    // Color config per portal type
    $colors = [
        'supplier' => ['bg' => '#072c1a', 'ico' => '#166534', 'badge' => 'Supplier Portal'],
        'customer' => ['bg' => '#1a0a2e', 'ico' => '#581c87', 'badge' => 'Patient Portal'],
    ];
    $c = $colors[$type] ?? $colors['supplier'];

    $titleE = htmlspecialchars($title, ENT_QUOTES);
    $storeE = htmlspecialchars($storeName, ENT_QUOTES);
    $nameE  = htmlspecialchars($userName, ENT_QUOTES);

    $flashHtml = '';
    if ($flash) {
        $ft = htmlspecialchars($flash['type'], ENT_QUOTES);
        $fm = htmlspecialchars($flash['msg'], ENT_QUOTES);
        $flashHtml = '<div class="alert alert-'.$ft.'"><span class="alert-body">'.$fm.'</span><button class="alert-close" onclick="this.parentElement.remove()">&#x2715;</button></div>';
    }

    echo '<!DOCTYPE html><html lang="en"><head>' . "\n";
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . "\n";
    echo attrMeta() . "\n";
    echo '<title>' . $titleE . ' &mdash; ' . htmlspecialchars($c['badge'], ENT_QUOTES) . '</title>' . "\n";
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">' . "\n";
    echo '<link rel="stylesheet" href="' . assetUrl('assets/css/app.css') . '">' . "\n";
    echo '</head><body><div class="app-layout"><div class="mob-overlay" id="mobOverlay"></div>' . "\n";

    echo '<nav class="sidebar" id="sidebar" style="--sb-bg:' . $c['bg'] . '">' . "\n";
    echo '<div class="sb-head"><div class="sb-icon" style="background:' . $c['ico'] . '"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>';
    echo '<div><div class="sb-title">' . $storeE . '</div><div class="sb-portal">' . htmlspecialchars($c['badge'], ENT_QUOTES) . '</div></div></div>' . "\n";

    echo '<div class="sb-section">Menu</div><ul class="sb-nav">' . "\n";
    foreach ($navItems as $page => $info) {
        $cls  = $activePage === $page ? ' active' : '';
        $icon = _portalNavIcon($info['icon'] ?? 'grid');
        $lbl  = htmlspecialchars($info['label'], ENT_QUOTES);
        echo '<li><a href="index.php?p=' . $page . '" class="' . $cls . '">' . $icon . $lbl . '</a></li>' . "\n";
    }
    echo '<li><a href="index.php?p=logout" data-confirm="Sign out?">' . _portalNavIcon('logout') . 'Sign Out</a></li>' . "\n";
    echo '</ul>';
    echo '<div class="sb-foot">Developed with 🫀 by <strong>Vineet</strong></div>';
    echo '</nav>' . "\n";

    echo '<div class="main-content"><header class="topbar">';
    echo '<div class="topbar-left"><button class="menu-btn" id="menuBtn"><span></span><span></span><span></span></button>';
    echo '<span class="topbar-title">' . $titleE . '</span>';
    echo '<span class="portal-badge portal-' . $type . '">' . htmlspecialchars($c['badge'], ENT_QUOTES) . '</span>';
    echo '</div><div class="topbar-right">';
    echo '<div class="user-chip"><div class="u-avatar" style="background:' . $c['ico'] . '">' . $initial . '</div><span class="u-name">' . $nameE . '</span></div>';
    echo '</div></header><div class="page-wrap">' . "\n";
    echo $flashHtml;
}

function portalFooter(): void {
    echo '</div></div></div>';
    echo '<script src="' . assetUrl('assets/js/app.js') . '"></script>';
    echo attrComment();
    echo '</body></html>' . "\n";
}
