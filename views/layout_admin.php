<?php
/**
 * DRXStore v4 - Admin Layout
 * Developed by Vineet | psvineet@zohomail.in
 */

// SVG icon library - all inline, no emoji, no font icons
function _icon(string $name): string {
    $icons = [
        'grid'      => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
        'pill'      => '<svg viewBox="0 0 24 24"><path d="M10.5 3.5a5 5 0 0 1 7.07 7.07l-7.5 7.5a5 5 0 0 1-7.07-7.07l7.5-7.5z"/><line x1="8.5" y1="12" x2="15.5" y2="5"/></svg>',
        'upload'    => '<svg viewBox="0 0 24 24"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>',
        'box'       => '<svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
        'adjust'    => '<svg viewBox="0 0 24 24"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>',
        'truck'     => '<svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
        'clipboard' => '<svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>',
        'receipt'   => '<svg viewBox="0 0 24 24"><path d="M4 2v20l3-3 2 3 2-3 2 3 2-3 3 3V2l-3 3-2-3-2 3-2-3-2 3z"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="15" y2="13"/></svg>',
        'list'      => '<svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
        'users'     => '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'return'    => '<svg viewBox="0 0 24 24"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>',
        'tag'       => '<svg viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
        'book'      => '<svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
        'chart'     => '<svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'clock'     => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'user'      => '<svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'settings'  => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
        'logout'    => '<svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'cross'     => '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        'cross-sm'  => '<svg viewBox="0 0 24 24" width="10" height="10"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        'cross'     => '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        'menu'      => '<svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
        'shield'    => '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    ];
    return isset($icons[$name]) ? '<span class="ni"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . preg_replace('/<svg[^>]*>/', '', $icons[$name]) : '<span class="ni"> ';
}

function _navItem(string $page, string $label, string $icon, string $active, int $badge = 0): string {
    $icons = [
        'grid'      => '<polyline points="9 3 3 3 3 9"/><polyline points="3 15 3 21 9 21"/><polyline points="15 3 21 3 21 9"/><polyline points="21 15 21 21 15 21"/>',
        'pill'      => '<path d="M10.5 3.5a5 5 0 0 1 7.07 7.07l-7.5 7.5a5 5 0 0 1-7.07-7.07l7.5-7.5z"/><line x1="8.5" y1="12" x2="15.5" y2="5"/>',
        'upload'    => '<polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>',
        'box'       => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><line x1="12" y1="22" x2="12" y2="12"/>',
        'sliders'   => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
        'building'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h1"/><path d="M14 9h1"/><path d="M9 14h1"/><path d="M14 14h1"/>',
        'truck'     => '<rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'pos'       => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><line x1="12" y1="17" x2="12" y2="21"/>',
        'list'      => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
        'users'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'return'    => '<polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/>',
        'tag'       => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
        'book'      => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'chart'     => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'clock'     => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'user'      => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'gear'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'mail'      => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/>',
        'message'   => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    ];
    $svg = $icons[$icon] ?? '';
    $niHtml = $svg
        ? '<span class="ni"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $svg . '</svg></span>'
        : '<span class="ni"></span>';
    $url = 'index.php?p=' . $page;
    $cls = $active === $page ? ' active' : '';
    $b   = $badge > 0 ? '<span class="sb-badge">' . $badge . '</span>' : '';
    return "<li><a href=\"{$url}\" class=\"{$cls}\">{$niHtml}{$label}{$b}</a></li>\n";
}

function adminHeader(string $title, string $activePage = ''): void {
    global $db;
    $cfg       = getSettings();
    $storeName = $cfg['store_name'] ?? APP_NAME;
    $flash     = getFlash();
    $today     = date('Y-m-d');
    $warnD     = date('Y-m-d', strtotime('+' . EXPIRY_DAYS . ' days'));
    $nLow      = $db->count('batches', fn($b) => ($b['quantity']??0)>0 && ($b['quantity']??0)<LOW_QTY);
    $nExp      = $db->count('batches', fn($b) => ($b['quantity']??0)>0 && ($b['expiry_date']??'')>=$today && ($b['expiry_date']??'')<=$warnD);
    $nAlt      = $nLow + $nExp;
    $adminName = $_SESSION['admin_name'] ?? 'Admin';
    $initial   = strtoupper(substr($adminName, 0, 1));
    $titleE    = htmlspecialchars($title, ENT_QUOTES);
    $storeE    = htmlspecialchars($storeName, ENT_QUOTES);
    $nameE     = htmlspecialchars($adminName, ENT_QUOTES);

    $alert = $nAlt > 0
        ? '<a href="index.php?p=expiry" class="topbar-badge no-print">' . $nAlt . ' Alert' . ($nAlt>1?'s':'') . '</a>'
        : '';
    // Actionable notifications: pending returns + unread supplier + unread patient messages
    $nActPendRet = $db->count('returns', fn($r) => ($r['status']??'') === 'pending');
    $nActSupMsg  = $db->count('supplier_messages', fn($m) => ($m['status']??'') === 'unread');
    $nActPatMsg  = $db->count('patient_messages', fn($m) => ($m['direction']??'in') === 'in' && !($m['is_read']??false));
    $nActTotal   = $nActPendRet + $nActSupMsg + $nActPatMsg;
    $notifBadge  = '';
    if ($nActTotal > 0) {
        $notifItems = '';
        if ($nActPendRet > 0) $notifItems .= '<a href="index.php?p=returns" style="display:block;padding:8px 14px;border-bottom:1px solid #e4e7ed;font-size:.8rem;color:#1a1e2e;text-decoration:none">' . $nActPendRet . ' pending return request' . ($nActPendRet>1?'s':'') . '</a>';
        if ($nActSupMsg > 0)  $notifItems .= '<a href="index.php?p=suppliers" style="display:block;padding:8px 14px;border-bottom:1px solid #e4e7ed;font-size:.8rem;color:#1a1e2e;text-decoration:none">' . $nActSupMsg . ' unread supplier message' . ($nActSupMsg>1?'s':'') . '</a>';
        if ($nActPatMsg > 0)  $notifItems .= '<a href="index.php?p=patient_messages" style="display:block;padding:8px 14px;border-bottom:1px solid #e4e7ed;font-size:.8rem;color:#1a1e2e;text-decoration:none">' . $nActPatMsg . ' unread patient message' . ($nActPatMsg>1?'s':'') . '</a>';
        $notifBadge = '<div style="position:relative;display:inline-block" class="no-print" id="notifWrap">'
            . '<button id="notifBtn" style="background:none;border:1px solid #fecaca;border-radius:50%;width:32px;height:32px;cursor:pointer;position:relative;display:flex;align-items:center;justify-content:center">'
            . '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#991b1b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>'
            . '<span style="position:absolute;top:-2px;right:-2px;background:#991b1b;color:#fff;font-size:.55rem;font-weight:700;padding:1px 4px;border-radius:9px;min-width:14px;text-align:center">' . $nActTotal . '</span>'
            . '</button>'
            . '<div id="notifDrop" style="display:none;position:absolute;right:0;top:36px;background:#fff;border:1px solid #e4e7ed;border-radius:10px;box-shadow:0 8px 24px rgba(10,35,66,.14);min-width:260px;z-index:999;overflow:hidden">'
            . '<div style="padding:10px 14px;border-bottom:1px solid #e4e7ed;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7485">Notifications</div>'
            . $notifItems
            . '</div></div>';
    }

    $flashHtml = '';
    if ($flash) {
        $ft = htmlspecialchars($flash['type'], ENT_QUOTES);
        $fm = htmlspecialchars($flash['msg'],  ENT_QUOTES);
        $flashHtml = '<div class="alert alert-'.$ft.'"><span class="alert-body">'.$fm.'</span><button class="alert-close" onclick="this.parentElement.remove()">&#x2715;</button></div>';
    }

    echo '<!DOCTYPE html><html lang="en"><head>' . "\n";
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . "\n";
    echo '<title>' . $titleE . ' &mdash; ' . $storeE . '</title>' . "\n";
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">' . "\n";
    echo '<link rel="stylesheet" href="' . assetUrl('assets/css/app.css') . '">' . "\n";
    echo '<link rel="icon" href="data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'><rect width=\'100\' height=\'100\' rx=\'20\' fill=\'%230a2342\'/><text y=\'.88em\' x=\'.1em\' font-size=\'75\' fill=\'white\'>Rx</text></svg>">' . "\n";
    echo '</head><body><div class="app-layout"><div class="mob-overlay" id="mobOverlay"></div>' . "\n";

    // Sidebar
    echo '<nav class="sidebar" id="sidebar">' . "\n";
    echo '<div class="sb-head"><div class="sb-icon"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></div><div><div class="sb-title">' . $storeE . '</div><div class="sb-portal">Admin Portal</div></div></div>' . "\n";

    echo '<div class="sb-section">Overview</div><ul class="sb-nav">';
    echo _navItem('dashboard','Dashboard','grid',$activePage);
    echo '</ul><div class="sb-section">Inventory</div><ul class="sb-nav">';
    echo _navItem('medicines','Medicines','pill',$activePage);
    echo _navItem('batches','Batches &amp; Stock','box',$activePage,$nAlt);
    echo _navItem('adjust','Stock Adjustment','sliders',$activePage);
    $nMsg = (int)$db->count('supplier_messages', fn($m) => ($m['status']??'') === 'unread');
    echo _navItem('suppliers','Suppliers','building',$activePage,$nMsg);
    echo _navItem('purchase','Purchase Orders','truck',$activePage);
    echo '</ul><div class="sb-section">Sales</div><ul class="sb-nav">';
    echo _navItem('sales','New Sale / POS','pos',$activePage);
    echo _navItem('sales_hist','Sales History','list',$activePage);
    echo _navItem('customers','Customers','users',$activePage);
    $nPendingRet = (int)$db->count('returns', fn($r) => ($r['status']??'') === 'pending');
    echo _navItem('returns','Returns','return',$activePage,$nPendingRet);
    $nPMsg = (int)$db->count('patient_messages', fn($m) => ($m['direction']??'in') === 'in' && !($m['is_read']??false));
    echo _navItem('patient_messages','Patient Messages','mail',$activePage,$nPMsg);
    echo _navItem('discounts','Discounts','tag',$activePage);
    echo '</ul><div class="sb-section">Finance</div><ul class="sb-nav">';
    echo _navItem('ledger','Ledger','book',$activePage);
    echo _navItem('reports','Analytics','chart',$activePage);
    echo _navItem('expiry','Expiry Report','clock',$activePage,$nExp);
    echo '</ul><div class="sb-section">Administration</div><ul class="sb-nav">';
    if (($_SESSION['admin_role']??'') === 'admin') {
        echo _navItem('users','User Management','user',$activePage);
    }
    if (($_SESSION['admin_role']??'') === 'admin') {
        echo _navItem('settings','Settings','gear',$activePage);
    }
    echo _navItem('logout','Sign Out','logout','');
    echo '</ul>';

    echo '<div class="sb-foot">Developed with 🫀 by <strong>Vineet</strong></div>';
    echo '</nav>' . "\n";

    // Main
    echo '<div class="main-content"><header class="topbar">';
    echo '<div class="topbar-left">';
    echo '<button class="menu-btn" id="menuBtn" aria-label="Open navigation" onclick="openSidebar()" ontouchend="event.preventDefault();openSidebar()"><span></span><span></span><span></span></button>';
    echo '<span class="topbar-title">' . $titleE . '</span>';
    echo '</div><div class="topbar-right">';
    echo $alert;
    echo $notifBadge;
    echo '<a href="index.php?p=sales" class="topbar-new no-print">+ New Sale</a>';
    echo '<div class="user-chip"><div class="u-avatar">' . $initial . '</div><span class="u-name">' . $nameE . '</span></div>';
    echo '</div></header>';
    echo '<div class="page-wrap">' . "\n";
    echo $flashHtml;
}

function adminFooter(): void {
    echo '</div></div></div>';
    echo '<script src="' . assetUrl('assets/js/app.js') . '"></script>';
    echo attrComment();
    echo '</body></html>' . "\n";
}
