<?php
/**
 * DRXStore - Application Bootstrap
 * Developed by Vineet
 */

if (!defined('APP_NAME'))    define('APP_NAME',    'DRXStore');
if (!defined('APP_VERSION')) define('APP_VERSION', '2.0.0');
if (!defined('APP_AUTHOR'))  define('APP_AUTHOR',  'Vineet');
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'psvineet@zohomail.in');

if (!defined('ROOT'))     define('ROOT',     dirname(__DIR__));
if (!defined('DATA_DIR')) define('DATA_DIR', ROOT . '/data');

if (!defined('GST_RATE'))      define('GST_RATE',      0.18);
if (!defined('LOW_QTY'))       define('LOW_QTY',       10);
if (!defined('EXPIRY_DAYS'))   define('EXPIRY_DAYS',   90);
if (!defined('PER_PAGE'))      define('PER_PAGE',      25);
if (!defined('MAX_ATTEMPTS'))  define('MAX_ATTEMPTS',  5);
if (!defined('LOCKOUT_MIN'))   define('LOCKOUT_MIN',   15);
if (!defined('SESSION_TTL'))   define('SESSION_TTL',   7200);

if (!defined('DOSAGE_FORMS')) define('DOSAGE_FORMS', [
    'Tablet','Capsule','Syrup','Suspension','Injection','Infusion',
    'Suppository','Powder','Granules','Cream','Ointment','Gel',
    'Lotion','Drop','Inhaler','Patch','Spray','Emulsion','Solution','Other',
]);

if (!defined('STAFF_ALLOWED_PAGES')) define('STAFF_ALLOWED_PAGES', [
    'dashboard','medicines','batches','sales','sales_hist',
    'customers','invoice','view_inv','cart_add','cart_remove',
    'cart_clear','cart_setcust','finalize','get_batches',
    'expiry','returns','patient_messages','cust_messages',
    'purchase','view_po',
]);

ini_set('display_errors', 0);   // Never expose raw errors to browser
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kolkata');

require_once ROOT . '/helpers/JsonDB.php';
require_once ROOT . '/helpers/MySQLDB.php';
require_once ROOT . '/helpers/functions.php';
require_once ROOT . '/helpers/Mailer.php';
require_once ROOT . '/helpers/attribution.php';

// ── Fatal error helper ────────────────────────────────────────────────────
function _drxFatalError(string $title, string $body, string $hint = ''): never {
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">
    <title>' . htmlspecialchars($title) . ' — DRXStore</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
      *{box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
      background:#f8fafc;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
      .box{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.10);padding:36px 40px;max-width:580px;width:100%}
      .icon{width:52px;height:52px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;
      justify-content:center;margin-bottom:20px;font-size:24px}
      h2{margin:0 0 12px;color:#991b1b;font-size:1.25rem}
      p{margin:0 0 14px;color:#374151;line-height:1.6;font-size:.9rem}
      .hint{background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px 16px;
      font-size:.85rem;color:#1e40af;line-height:1.7}
      .hint strong{color:#1d4ed8}
      code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:.82rem;color:#0f172a}
      a{color:#2563eb}
      .back{display:inline-block;margin-top:18px;padding:8px 18px;background:#1e293b;color:#fff;
      border-radius:8px;text-decoration:none;font-size:.85rem}
    </style></head><body>
    <div class="box">
      <div class="icon">&#9888;</div>
      <h2>' . $title . '</h2>
      <p>' . $body . '</p>';
    if ($hint) echo '<div class="hint">' . $hint . '</div>';
    echo '<br><a href="index.php" class="back">&larr; Go to Home</a>
    </div></body></html>';
    exit;
}

// ── Ensure data dir is writable ──────────────────────────────────────────
if (!is_dir(DATA_DIR)) {
    if (!mkdir(DATA_DIR, 0755, true)) {
        _drxFatalError('Permission Error',
            'Cannot create <code>data/</code> directory.',
            'Run: <code>chmod 755 ' . htmlspecialchars(dirname(DATA_DIR)) . '</code> then reload.');
    }
}
if (!is_writable(DATA_DIR)) {
    _drxFatalError('Write Permission Error',
        'The <code>data/</code> directory is not writable.',
        'Run: <code>chmod -R 755 ' . htmlspecialchars(DATA_DIR) . '</code> then reload.');
}

// ── Load Database ─────────────────────────────────────────────────────────
$dbCfgFile = DATA_DIR . '/db_config.json';
if (file_exists($dbCfgFile)) {
    $dbCfg = json_decode(file_get_contents($dbCfgFile), true) ?? [];
    if (($dbCfg['driver'] ?? 'json') === 'mysql') {
        try {
            $db = new MySQLDB(
                $dbCfg['host']     ?? 'localhost',
                (int)($dbCfg['port']     ?? 3306),
                $dbCfg['name']     ?? 'drxstore',
                $dbCfg['user']     ?? 'root',
                $dbCfg['password'] ?? ''
            );
        } catch (Exception $ex) {
            // ── MySQL connection failed ───────────────────────────────────
            // This can happen after: deleting DB, wrong credentials, server down,
            // host changed. Show a clear actionable error — NOT a broken redirect.
            $msg = $ex->getMessage();
            $isNotFound = stripos($msg, 'Unknown database') !== false
                       || stripos($msg, 'Access denied')    !== false
                       || stripos($msg, 'Connection refused')!== false
                       || stripos($msg, 'getaddrinfo')      !== false;
            if ($isNotFound) {
                _drxFatalError(
                    'Database Not Found / Connection Failed',
                    'Could not connect to MySQL database <strong>' . htmlspecialchars($dbCfg['name'] ?? '') . '</strong>.<br>'
                    . 'Error: <code>' . htmlspecialchars($msg) . '</code>',
                    'Possible causes:<br>'
                    . '&bull; Database was deleted — recreate it in cPanel/phpMyAdmin with the same name.<br>'
                    . '&bull; Wrong credentials — check <code>data/db_config.json</code>.<br>'
                    . '&bull; After recreating the database, <a href="index.php?p=setup" style="color:#1d4ed8">run the setup wizard</a> to restore your tables,'
                    . ' or <strong>restore from your SQL backup</strong> via Settings &rarr; Backup &amp; Restore.'
                );
            }
            _drxFatalError('MySQL Error', htmlspecialchars($msg),
                'Check <code>data/db_config.json</code> or <a href="index.php?p=setup">run setup wizard</a>.');
        }
    } else {
        $db = new JsonDB(DATA_DIR);
    }
} else {
    $db = new JsonDB(DATA_DIR);
}

// ── Seed dosage categories if missing ────────────────────────────────────
try {
    if ($db->count('categories') === 0) {
        foreach (DOSAGE_FORMS as $f) {
            $db->insert('categories', ['name' => $f, 'type' => 'dosage']);
        }
    }
} catch (Exception $e) {
    // Silently ignore — categories seeding is non-critical
}