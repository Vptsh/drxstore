<?php
/**
 * DRXStore - Helper Functions
 * Developed by Vineet
 */

/* ── Output ── */
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
/**
 * Returns the currency HTML entity, robust against DB charset corruption.
 * Maps stored value → safe HTML entity. Handles: &#8377; INR ₹ ? ??? etc.
 */
function currencySymbol(): string {
    static $sym = null;
    if ($sym !== null) return $sym;
    $cfg = getSettings();
    $raw = $cfg['currency'] ?? '&#8377;';
    // Known safe HTML entities stored directly
    $knownEntities = ['&#8377;','$','&euro;','&pound;','&#165;','&#8364;'];
    if (in_array($raw, $knownEntities, true)) { $sym = $raw; return $sym; }
    // Safe ASCII codes (new system)
    $codeMap = ['INR'=>'&#8377;','USD'=>'$','EUR'=>'&euro;','GBP'=>'&pound;','JPY'=>'&#165;'];
    if (isset($codeMap[$raw])) { $sym = $codeMap[$raw]; return $sym; }
    // Detect raw UTF-8 rupee character ₹ (U+20B9) and common variants
    if ($raw === "â¹" || $raw === '₹') { $sym = '&#8377;'; return $sym; }
    if ($raw === '€') { $sym = '&euro;'; return $sym; }
    if ($raw === '£') { $sym = '&pound;'; return $sym; }
    // Anything that looks corrupted (contains '?', is empty, too long, non-ASCII junk)
    if ($raw === '' || $raw === '?' || $raw === '??' || $raw === '???' || strpos($raw,'?') !== false) {
        // Auto-repair: write &#8377; back to DB so future loads work
        global $db;
        if ($db) {
            try { $db->update('settings', fn($x) => true, ['currency' => '&#8377;']); } catch(Exception $e) {}
        }
        $sym = '&#8377;';
        return $sym;
    }
    // Fallback: return as-is if it looks like a short safe string
    $sym = strlen($raw) <= 8 ? $raw : '&#8377;';
    return $sym;
}
function money($v): string { return currencySymbol() . number_format((float)($v ?? 0), 2); }
function dateF(string $d): string { return $d ? date('d M Y', strtotime($d)) : '&mdash;'; }
function dateTimeF(string $d): string { return $d ? date('d M Y, h:i A', strtotime($d)) : '&mdash;'; }
function daysLeft(string $exp): int { return $exp ? (int)ceil((strtotime($exp) - time()) / 86400) : 0; }
function invNo(int $id): string { return 'DRX-' . date('Y') . '-' . str_pad($id, 5, '0', STR_PAD_LEFT); }
function poNo(int $id): string  { return 'PO-' . str_pad($id, 5, '0', STR_PAD_LEFT); }

function expiryChip(string $exp): string {
    if (!$exp) return '<span class="chip chip-gray">&mdash;</span>';
    $d = daysLeft($exp);
    if ($d < 0)   return '<span class="chip chip-red">Expired</span>';
    if ($d <= 30) return '<span class="chip chip-orange">' . dateF($exp) . '</span>';
    if ($d <= 90) return '<span class="chip chip-blue">' . dateF($exp) . '</span>';
    return '<span class="chip chip-green">' . dateF($exp) . '</span>';
}
function stockChip(int $qty): string {
    if ($qty === 0)    return '<span class="chip chip-red">Out of Stock</span>';
    if ($qty < LOW_QTY) return '<span class="chip chip-orange">Low: ' . $qty . '</span>';
    return '<span class="chip chip-green">' . $qty . ' units</span>';
}

/* ── Input ── */
function post(string $k, string $d = ''): string  { return trim((string)($_POST[$k] ?? $d)); }
function get(string $k,  string $d = ''): string  { return trim((string)($_GET[$k]  ?? $d)); }
function postInt(string $k, int $d = 0): int      { return max(0, (int)($_POST[$k] ?? $d)); }
function getInt(string $k,  int $d = 0): int      { return max(0, (int)($_GET[$k]  ?? $d)); }
function postFloat(string $k, float $d = 0): float { return max(0.0, (float)($_POST[$k] ?? $d)); }

/* ── Settings ── */
function getSettings(): array {
    global $db;
    static $cached = null;
    if ($cached === null) {
        $s = $db->findOne('settings', fn($x) => true);
        $cached = $s ?? [];
    }
    return $cached;
}
function storeName(): string  { return getSettings()['store_name']  ?? APP_NAME; }
function storeEmail(): string { return getSettings()['store_email'] ?? ADMIN_EMAIL; }
function storeCurrency(): string { return currencySymbol(); }

/* ── Flash messages ── */
function setFlash(string $type, string $msg): void {
    startSession(); $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function getFlash(): ?array {
    startSession();
    if (!empty($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

/* ── Pagination ── */
function paginate(array $items, int $page, int $per = 25): array {
    $total = count($items); $pages = max(1, (int)ceil($total / $per));
    $page  = max(1, min($page, $pages));
    return ['items' => array_slice($items, ($page-1)*$per, $per), 'total'=>$total, 'pages'=>$pages, 'current'=>$page];
}
function pagerHtml(array $pag, string $baseUrl): string {
    if ($pag['pages'] <= 1) return '';
    $h = '<div class="pager">';
    $h .= $pag['current'] > 1 ? '<a href="'.$baseUrl.'&page='.($pag['current']-1).'">&lsaquo;</a>' : '<span class="off">&lsaquo;</span>';
    for ($i=1; $i<=$pag['pages']; $i++) {
        $h .= $i===$pag['current'] ? '<span class="on">'.$i.'</span>' : '<a href="'.$baseUrl.'&page='.$i.'">'.$i.'</a>';
    }
    $h .= $pag['current'] < $pag['pages'] ? '<a href="'.$baseUrl.'&page='.($pag['current']+1).'">&rsaquo;</a>' : '<span class="off">&rsaquo;</span>';
    return $h . '</div>';
}

/* ── CSRF ── */
function csrfField(): string  { return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">'; }
function csrfToken(): string  { startSession(); if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verifyCsrf(): void   {
    startSession();
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403); die('Invalid security token. Please go back and try again.');
    }
}

/* ── Brute force ── */
function clientIp(): string {
    foreach (['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
        $v = $_SERVER[$k] ?? '';
        if ($v && filter_var(explode(',', $v)[0], FILTER_VALIDATE_IP)) return trim(explode(',', $v)[0]);
    }
    return '0.0.0.0';
}
function isLockedOut(string $context): bool {
    global $db;
    $ip    = clientIp();
    $since = date('Y-m-d H:i:s', time() - LOCKOUT_MIN * 60);
    $n     = $db->count('login_attempts', fn($a) => ($a['ip']??'') === $ip && ($a['context']??'') === $context && ($a['ts']??'') >= $since);
    return $n >= MAX_ATTEMPTS;
}
function recordAttempt(string $context): void {
    global $db;
    $db->insert('login_attempts', ['ip'=>clientIp(),'context'=>$context,'ts'=>date('Y-m-d H:i:s')]);
}
function clearAttempts(string $context): void {
    global $db;
    $ip = clientIp();
    $db->delete('login_attempts', fn($a) => ($a['ip']??'') === $ip && ($a['context']??'') === $context);
}

/* ── Sessions ── */
function startSession(): void {
    // Only configure session if it has not started yet AND headers not sent
    if (session_status() === PHP_SESSION_NONE) {
        // Only modify session path/params before headers are sent
        if (!headers_sent()) {
            $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
            $sp = session_save_path();
            if (empty($sp) || !is_writable($sp)) {
                $t = sys_get_temp_dir();
                if (is_writable($t)) session_save_path($t);
            }
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => $isHttps,
                'httponly'  => true,
                'samesite' => 'Lax',
            ]);
        }
        session_start();
    }
    // Session timeout check
    if (!empty($_SESSION['_last']) && (time() - $_SESSION['_last']) > SESSION_TTL) {
        $_SESSION = [];
        session_destroy();
        startSession();
        return;
    }
    $_SESSION['_last'] = time();
}

function requireAdmin(): void {
    startSession();
    if (empty($_SESSION['admin_id'])) { header('Location: index.php?p=login'); exit; }
}

function requireStaff(): void {
    startSession();
    if (empty($_SESSION['admin_id'])) { header('Location: index.php?p=login'); exit; }
    // Check staff page permissions
    if (($_SESSION['admin_role'] ?? '') === 'staff') {
        $p = get('p', '');
        if (!in_array($p, STAFF_ALLOWED_PAGES)) {
            setFlash('danger', 'You do not have permission to access this section.');
            header('Location: index.php?p=dashboard'); exit;
        }
    }
}

function requireSupplier(): void { startSession(); if (empty($_SESSION['supplier_id'])) { header('Location: index.php?p=sup_login'); exit; } }
function requireCustomer(): void { startSession(); if (empty($_SESSION['cust_id']))     { header('Location: index.php?p=cust_login');exit; } }

function isAdmin(): bool  { return ($_SESSION['admin_role'] ?? '') === 'admin'; }
function isStaff(): bool  { return ($_SESSION['admin_role'] ?? '') === 'staff'; }

/* ── Secure token ── */
function secureToken(): string { return bin2hex(random_bytes(32)); }

/* ── Mail template ── */
function mailTemplate(string $title, string $body): string {
    $store = storeName(); $year = date('Y');
    $adminEmail = storeEmail();
    return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;background:#f1f3f6;margin:0;padding:20px}
.wrap{max-width:560px;margin:auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(10,35,66,.1)}
.hdr{background:#0a2342;padding:22px 28px;color:#fff}
.hdr h1{margin:0;font-size:18px;font-weight:700}.hdr p{margin:4px 0 0;opacity:.75;font-size:12px}
.body{padding:24px 28px;font-size:14px;color:#1a1e2e;line-height:1.7}
.footer{padding:14px 28px;background:#f8f9fb;font-size:11px;color:#6b7485;text-align:center;border-top:1px solid #e4e7ed}
.btn{display:inline-block;padding:10px 22px;background:#0a2342;color:#fff!important;border-radius:6px;text-decoration:none;font-weight:600;margin:10px 0}
code{background:#f1f3f6;padding:2px 6px;border-radius:4px;font-family:monospace}
</style></head><body>
<div class="wrap">
  <div class="hdr"><h1>{$store}</h1><p>{$title}</p></div>
  <div class="body">{$body}</div>
  <div class="footer">Developed with &#x1FAC0; by <strong>Vineet</strong> &nbsp;&bull;&nbsp; &copy; {$year} {$store}</div>
</div></body></html>
HTML;
}

// Alias
function mailWrap(string $title, string $body): string { return mailTemplate($title, $body); }

/* ── Base URL (auto-detected, works from root or subdirectory) ── */

/* ── Amount in Words (INR) ── */
function amountInWords(float $amount): string {
    $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
             'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
             'Seventeen','Eighteen','Nineteen'];
    $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    $n = (int)round($amount);
    if ($n === 0) return 'Zero Rupees';
    $toWords = function(int $n) use ($ones, $tens, &$toWords): string {
        if ($n < 20) return $ones[$n];
        if ($n < 100) return $tens[(int)($n/10)] . ($n%10 ? ' '.$ones[$n%10] : '');
        if ($n < 1000) return $ones[(int)($n/100)] . ' Hundred' . ($n%100 ? ' '.$toWords($n%100) : '');
        if ($n < 100000) return $toWords((int)($n/1000)) . ' Thousand' . ($n%1000 ? ' '.$toWords($n%1000) : '');
        if ($n < 10000000) return $toWords((int)($n/100000)) . ' Lakh' . ($n%100000 ? ' '.$toWords($n%100000) : '');
        return $toWords((int)($n/10000000)) . ' Crore' . ($n%10000000 ? ' '.$toWords($n%10000000) : '');
    };
    $paise = (int)round(($amount - $n) * 100);
    $words = 'Rupees ' . $toWords($n);
    if ($paise > 0) $words .= ' and ' . $toWords($paise) . ' Paise';
    return $words;
}

function baseUrl(): string {
    static $base = null;
    if ($base !== null) return $base;
    // Method 1: DOCUMENT_ROOT vs physical app path
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $appRoot = rtrim(str_replace('\\', '/', ROOT), '/');
    if ($docRoot !== '' && strpos($appRoot, $docRoot) === 0) {
        $base = rtrim(str_replace($docRoot, '', $appRoot), '/');
        return $base;
    }
    // Method 2: SCRIPT_NAME
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir    = dirname($script);
    $base   = rtrim($dir === '.' ? '' : $dir, '/');
    return $base;
}
function assetUrl(string $path): string { return baseUrl() . '/' . ltrim($path, '/'); }
function url(string $p, array $q = []): string {
    $base = baseUrl() . '/index.php?p=' . $p;
    foreach ($q as $k => $v) $base .= '&' . urlencode((string)$k) . '=' . urlencode((string)$v);
    return $base;
}
function siteUrl(string $p = '', array $q = []): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = $p !== '' ? url($p, $q) : (baseUrl() ?: '/');
    return $scheme . '://' . $host . $path;
}

/* ── Alert Emails (call from dashboard on admin login) ── */
function sendStockAlertEmails(): void {
    global $db;
    // Only send once per day - use a flag file
    $flagFile = DATA_DIR . '/.alert_sent_' . date('Y-m-d');
    if (file_exists($flagFile)) return;

    $today   = date('Y-m-d');
    $warnD   = date('Y-m-d', strtotime('+' . EXPIRY_DAYS . ' days'));
    $lowStock= $db->find('batches', fn($b) => ($b['quantity']??0)>0 && ($b['quantity']??0)<LOW_QTY);
    $expiring= $db->find('batches', fn($b) => ($b['quantity']??0)>0 && ($b['expiry_date']??'')>=$today && ($b['expiry_date']??'')<=$warnD);
    $expired = $db->find('batches', fn($b) => ($b['quantity']??0)>0 && ($b['expiry_date']??'')<$today);

    if (empty($lowStock) && empty($expiring) && empty($expired)) return;

    $medMap=[]; foreach($db->table('medicines') as $m) $medMap[$m['id']]=$m;
    $store  = storeName();
    $to     = storeEmail();
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;

    $html = '<p>Daily inventory alert for <strong>' . e($store) . '</strong></p>';

    if (!empty($expired)) {
        $html .= '<h3 style="color:#991b1b">Expired Medicines (' . count($expired) . ')</h3><ul>';
        foreach(array_slice($expired,0,15) as $b) {
            $mn = $medMap[$b['medicine_id']??0]['name'] ?? 'Unknown';
            $html .= '<li>' . e($mn) . ' — Batch: ' . e($b['batch_no']??'') . ' — Qty: ' . ($b['quantity']??0) . ' — Expired: ' . ($b['expiry_date']??'') . '</li>';
        }
        $html .= '</ul>';
    }

    if (!empty($expiring)) {
        $html .= '<h3 style="color:#92400e">Expiring Within ' . EXPIRY_DAYS . ' Days (' . count($expiring) . ')</h3><ul>';
        foreach(array_slice($expiring,0,15) as $b) {
            $mn = $medMap[$b['medicine_id']??0]['name'] ?? 'Unknown';
            $html .= '<li>' . e($mn) . ' — Expires: ' . ($b['expiry_date']??'') . ' — Qty: ' . ($b['quantity']??0) . '</li>';
        }
        $html .= '</ul>';
    }

    if (!empty($lowStock)) {
        $html .= '<h3 style="color:#92400e">Low Stock (' . count($lowStock) . ' batches)</h3><ul>';
        foreach(array_slice($lowStock,0,15) as $b) {
            $mn = $medMap[$b['medicine_id']??0]['name'] ?? 'Unknown';
            $html .= '<li>' . e($mn) . ' — Batch: ' . e($b['batch_no']??'') . ' — Only ' . ($b['quantity']??0) . ' left</li>';
        }
        $html .= '</ul>';
    }

    $body = mailTemplate('Daily Inventory Alert — ' . date('d M Y'), $html . '<p>Please review and take necessary action from your DRXStore admin panel.</p>');
    sendMail($to, 'Inventory Alert — ' . $store . ' — ' . date('d M Y'), $body);

    // Mark as sent for today
    @file_put_contents($flagFile, date('Y-m-d H:i:s'));
    // Clean old flag files
    foreach(glob(DATA_DIR . '/.alert_sent_*') as $f) {
        if ($f !== $flagFile) @unlink($f);
    }
}
