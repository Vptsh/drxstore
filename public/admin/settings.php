<?php
/**
 * DRXStore - Settings with SMTP Mail Configuration
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php';
require_once ROOT.'/views/layout_admin.php';
requireAdmin();
$cfg=$db->findOne('settings',fn($x)=>true)??[];
$errors=[];

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $act=post('action');

    if($act==='general'){
        $data=['store_name'=>post('store_name'),'store_address'=>post('store_address'),'store_phone'=>post('store_phone'),'store_email'=>post('store_email'),'store_gst'=>post('store_gst'),'store_dl'=>post('store_dl'),'currency'=>(function(){ $c=post('currency','&#8377;'); $safe=['&#8377;','$','&euro;','&pound;','&#165;']; return in_array($c,$safe,true)?$c:'&#8377;'; })(),'low_qty'=>max(1,postInt('low_qty',10)),'expiry_days'=>max(1,postInt('expiry_days',90)),'updated_at'=>date('Y-m-d H:i:s')];
        // Preserve SMTP fields
        // Preserve all general fields (SMTP only updates SMTP)
        foreach(['smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from','smtp_name','smtp_secure'] as $f) {
            if(isset($cfg[$f])) $data[$f]=$cfg[$f];
        }
        // Always preserve store fields when saving general
        foreach(['store_name','store_address','store_phone','store_email','store_gst','store_dl','currency','low_qty','expiry_days','storage','setup_done'] as $f) {
            if(!isset($data[$f]) && isset($cfg[$f])) $data[$f]=$cfg[$f];
        }
        if($db->count('settings')>0)$db->update('settings',fn($x)=>true,$data);else $db->insert('settings',$data);
        $GLOBALS['_settings_cache']=null; // bust static cache
        setFlash('success','Settings saved.');$GLOBALS['_settings_cache']=null;header('Location: index.php?p=settings');exit;
    }

    if($act==='smtp'){
        $data=['smtp_host'=>post('smtp_host'),'smtp_port'=>postInt('smtp_port',587),'smtp_user'=>post('smtp_user'),'smtp_from'=>post('smtp_from'),'smtp_name'=>post('smtp_name'),'smtp_secure'=>post('smtp_secure','tls'),'updated_at'=>date('Y-m-d H:i:s')];
        // Preserve all store/general fields when saving SMTP
        foreach(['store_name','store_address','store_phone','store_email','store_gst','store_dl','currency','low_qty','expiry_days','storage','setup_done'] as $f) {
            if(isset($cfg[$f])) $data[$f]=$cfg[$f];
        }
        $newpass=post('smtp_pass');
        if($newpass) $data['smtp_pass']=$newpass;
        else if(isset($cfg['smtp_pass'])) $data['smtp_pass']=$cfg['smtp_pass'];
        if($db->count('settings')>0){
            $db->update('settings',fn($x)=>true,$data);
        } else {
            $db->insert('settings',$data);
        }
        $GLOBALS['_settings_cache']=null;
        setFlash('success','SMTP settings saved.');header('Location: index.php?p=settings');exit;
    }

    if($act==='smtp_test'){
        $to=post('test_email');
        if(!filter_var($to,FILTER_VALIDATE_EMAIL)){$errors[]='Enter a valid email to test.';}
        else{
            $smtpCfg=['host'=>$cfg['smtp_host']??'','port'=>(int)($cfg['smtp_port']??587),'user'=>$cfg['smtp_user']??'','pass'=>$cfg['smtp_pass']??'','from'=>$cfg['smtp_from']??storeEmail(),'name'=>$cfg['smtp_name']??storeName(),'secure'=>$cfg['smtp_secure']??'tls'];
            $result=Mailer::test($to,$smtpCfg);
            if($result['success'])setFlash('success',$result['message']);
            else setFlash('danger','SMTP Test: '.$result['message']);
            header('Location: index.php?p=settings');exit;
        }
    }

    if($act==='password'){
        $old=post('old_pw');$new=post('new_pw');$conf=post('conf_pw');
        $u=$db->findOne('users',fn($u)=>$u['id']===$_SESSION['admin_id']);
        if(!password_verify($old,$u['password']??''))$errors[]='Current password incorrect.';
        elseif(strlen($new)<6)$errors[]='Min 6 characters.';
        elseif($new!==$conf)$errors[]='Passwords do not match.';
        else{$db->update('users',fn($u)=>$u['id']===$_SESSION['admin_id'],['password'=>password_hash($new,PASSWORD_BCRYPT)]);setFlash('success','Password changed.');header('Location: index.php?p=settings');exit;}
    }
}

// ── JSON → MySQL Migration ────────────────────────────────────────────
if (get('action') === 'test_migration') {
    header('Content-Type: application/json');
    $host = get('host','localhost'); $port = (int)get('port',3306);
    $name = get('name',''); $user = get('user','root'); $pass = get('pass','');
    if (!$name) { echo json_encode(['ok'=>false,'msg'=>'Database name required.']); exit; }
    $err = MySQLDB::testConnection($host, $port, $name, $user, $pass);
    echo json_encode(['ok'=>$err===null, 'msg'=>$err ?? 'Connection successful! Ready to migrate.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='do_migration') {
    verifyCsrf();
    requireAdmin();
    $host = post('host','localhost'); $port = (int)(post('port') ?: 3306);
    $name = post('name'); $user = post('user','root'); $pass = post('pass');
    if (!$name) { echo json_encode(['ok'=>false,'msg'=>'Database name required.']); exit; }
    header('Content-Type: application/json');

    // 1. Test connection and create DB
    $err = MySQLDB::testConnection($host, $port, $name, $user, $pass);
    if ($err !== null) { echo json_encode(['ok'=>false,'msg'=>'Connection failed: '.$err]); exit; }

    try {
        // 2. Connect and create tables
        $newPdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        MySQLDB::createTables($newPdo);
        $newDb = new MySQLDB($host, $port, $name, $user, $pass);

        // 3. Migrate all tables from JSON
        $tables = ['settings','users','medicines','categories','batches','suppliers','supplier_users',
                   'customers','sales','sales_items','purchase_orders','po_items','returns','return_items',
                   'discounts','stock_adjustments','login_attempts','customer_purchase_log',
                   'supplier_messages','patient_messages'];
        $counts = [];
        foreach ($tables as $tbl) {
            $rows = $db->table($tbl);
            $counts[$tbl] = 0;
            foreach ($rows as $row) {
                // Check if row already exists by id
                $existing = $newPdo->prepare("SELECT id FROM `{$tbl}` WHERE id=?")->execute([$row['id']??0]);
                $chk = $newPdo->prepare("SELECT COUNT(*) FROM `{$tbl}` WHERE id=?");
                $chk->execute([$row['id']??0]);
                if ((int)$chk->fetchColumn() === 0) {
                    $cols = implode(',', array_map(fn($k)=>"`{$k}`", array_keys($row)));
                    $phs  = implode(',', array_fill(0, count($row), '?'));
                    $vals = array_map(function($v){ return $v===true?1:($v===false?0:$v); }, array_values($row));
                    try {
                        $newPdo->prepare("INSERT INTO `{$tbl}` ({$cols}) VALUES ({$phs})")->execute($vals);
                        $counts[$tbl]++;
                    } catch(Exception $e) { /* skip duplicate or constraint error */ }
                }
            }
        }

        // 4. Save MySQL config
        file_put_contents(DATA_DIR.'/db_config.json', json_encode([
            'driver'=>'mysql','host'=>$host,'port'=>$port,'name'=>$name,'user'=>$user,'password'=>$pass
        ]));

        // 5. Reset settings storage flag
        $newDb->update('settings', fn($x)=>true, ['storage'=>'mysql']);

        $total = array_sum($counts);
        echo json_encode(['ok'=>true, 'msg'=>"Migration successful! {$total} records migrated to MySQL. Page will reload.", 'counts'=>$counts]);
    } catch (Exception $ex) {
        echo json_encode(['ok'=>false, 'msg'=>'Migration failed: '.$ex->getMessage()]);
    }
    exit;
}


// Reload cfg after save
$cfg=$db->findOne('settings',fn($x)=>true)??[];
// Auto-repair corrupted currency (e.g. after bad charset restore)
if(isset($cfg['currency'])){
    $rawCur=$cfg['currency'];
    $knownSafe=['&#8377;','$','&euro;','&pound;','&#165;'];
    if(!in_array($rawCur,$knownSafe,true)&&$rawCur!==''){
        $db->update('settings',fn($x)=>true,['currency'=>'&#8377;']);
        $cfg['currency']='&#8377;';
        $GLOBALS['_settings_cache']=null;
    }
}

// ── MySQL Backup ────────────────────────────────────────────────
if(get('action')==='backup_mysql'){
    $dbCfgFile = DATA_DIR.'/db_config.json';
    if(!file_exists($dbCfgFile)||($cfg['storage']??'json')!=='mysql'){
        setFlash('danger','Backup is only available for MySQL storage.');
        header('Location: index.php?p=settings'); exit;
    }
    $dbCfg = json_decode(file_get_contents($dbCfgFile),true)??[];
    try {
        $pdo = new PDO(
            "mysql:host={$dbCfg['host']};port={$dbCfg['port']};dbname={$dbCfg['name']};charset=utf8mb4",
            $dbCfg['user'], $dbCfg['password'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
        );
        $dbName = $dbCfg['name'];

        // ── Build a fully phpMyAdmin & mysqldump compatible SQL dump ──
        $lines = [];
        $lines[] = "-- DRXStore MySQL Backup";
        $lines[] = "-- Generated   : ".date('Y-m-d H:i:s');
        $lines[] = "-- Database    : {$dbName}";
        $lines[] = "-- App Version : ".APP_NAME." v".APP_VERSION;
        $lines[] = "-- Host        : ".$dbCfg['host'];
        $lines[] = "-- Compatible  : MySQL 5.7+ / MariaDB 10.3+ / phpMyAdmin";
        $lines[] = "";
        $lines[] = "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';";
        $lines[] = "SET FOREIGN_KEY_CHECKS = 0;";
        $lines[] = "SET NAMES utf8mb4;";
        $lines[] = "SET CHARACTER SET utf8mb4;";
        $lines[] = "";
        $lines[] = "-- NOTE: Connect to your target database before importing.";
        $lines[] = "-- The tables below will be created/replaced in the currently selected database.";
        $lines[] = "";

        // Get MySQL server version for comment
        $serverVer = $pdo->query("SELECT VERSION()")->fetchColumn();
        $lines[] = "-- MySQL Server Version: {$serverVer}";
        $lines[] = "";

        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);

        foreach($tables as $tbl){
            $lines[] = "";
            $lines[] = "-- --------------------------------------------------------";
            $lines[] = "-- Table structure for `{$tbl}`";
            $lines[] = "-- --------------------------------------------------------";
            $lines[] = "";
            $lines[] = "DROP TABLE IF EXISTS `{$tbl}`;";
            $lines[] = "SET @saved_cs_client = @@character_set_client;";
            $lines[] = "SET character_set_client = utf8mb4;";
            $row  = $pdo->query("SHOW CREATE TABLE `{$tbl}`")->fetch();
            $createSql = $row['Create Table'] ?? $row[1];
            // Ensure ENGINE and charset are explicitly set for compatibility
            $lines[] = $createSql . ";";
            $lines[] = "SET character_set_client = @saved_cs_client;";
            $lines[] = "";

            // Data — one INSERT per row for max compatibility with phpMyAdmin packet limits
            $stmt = $pdo->query("SELECT * FROM `{$tbl}`");
            $rowCount = 0;
            $cols = null;
            while($dataRow = $stmt->fetch(PDO::FETCH_ASSOC)){
                if($cols === null){
                    $cols = '`'.implode('`,`', array_keys($dataRow)).'`';
                    $lines[] = "-- Dumping data for table `{$tbl}`";
                    $lines[] = "";
                    $lines[] = "LOCK TABLES `{$tbl}` WRITE;";
                    $lines[] = "ALTER TABLE `{$tbl}` DISABLE KEYS;";
                }
                $vals = array_map(function($v) use ($pdo){
                    if($v === null) return 'NULL';
                    return $pdo->quote((string)$v);
                }, array_values($dataRow));
                $lines[] = "INSERT INTO `{$tbl}` ({$cols}) VALUES (".implode(',',$vals).");";
                $rowCount++;
            }
            if($cols !== null){
                $lines[] = "ALTER TABLE `{$tbl}` ENABLE KEYS;";
                $lines[] = "UNLOCK TABLES;";
                $lines[] = "-- {$rowCount} row(s) exported";
                $lines[] = "";
            }
        }

        $lines[] = "";
        $lines[] = "-- --------------------------------------------------------";
        $lines[] = "-- Restore compatibility footer";
        $lines[] = "-- --------------------------------------------------------";
        $lines[] = "SET FOREIGN_KEY_CHECKS = 1;";
        $lines[] = "SET UNIQUE_CHECKS = 1;";
        $lines[] = "";
        $lines[] = "-- DRXStore backup complete. Tables: ".count($tables).". Generated: ".date('Y-m-d H:i:s');

        $out = implode("\n", $lines);
        $filename = 'drxstore_backup_'.$dbName.'_'.date('Ymd_His').'.sql';

        if(ob_get_length()) ob_clean();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: '.strlen($out));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo $out; exit;

    } catch(Exception $ex){
        setFlash('danger','Backup failed: '.$ex->getMessage());
        header('Location: index.php?p=settings'); exit;
    }
}

// ── MySQL Restore ────────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='restore_mysql'){
    verifyCsrf();
    $dbCfgFile = DATA_DIR.'/db_config.json';
    if(!file_exists($dbCfgFile)||($cfg['storage']??'json')!=='mysql'){
        setFlash('danger','Restore is only available for MySQL storage.');
        header('Location: index.php?p=settings'); exit;
    }
    if(!isset($_FILES['sql_file'])||$_FILES['sql_file']['error']!==UPLOAD_ERR_OK){
        $uploadErr = $_FILES['sql_file']['error']??'no file';
        setFlash('danger','Upload failed (error '.$uploadErr.'). Check PHP upload_max_filesize and post_max_size.');
        header('Location: index.php?p=settings'); exit;
    }
    $ext = strtolower(pathinfo($_FILES['sql_file']['name'], PATHINFO_EXTENSION));
    if($ext !== 'sql'){
        setFlash('danger','Only .sql files are accepted.');
        header('Location: index.php?p=settings'); exit;
    }
    $sqlContent = file_get_contents($_FILES['sql_file']['tmp_name']);
    if(!$sqlContent || strlen($sqlContent) < 50){
        setFlash('danger','SQL file is empty or too small to be a valid backup.');
        header('Location: index.php?p=settings'); exit;
    }
    // Accept DRXStore backups OR any valid SQL file (e.g. phpMyAdmin exports)
    $looksLikeSql = (
        stripos($sqlContent, 'CREATE TABLE') !== false ||
        stripos($sqlContent, 'INSERT INTO')  !== false ||
        stripos($sqlContent, 'DROP TABLE')   !== false
    );
    if(!$looksLikeSql){
        setFlash('danger','This does not appear to be a valid SQL backup file (no CREATE TABLE / INSERT INTO found).');
        header('Location: index.php?p=settings'); exit;
    }

    $dbCfg = json_decode(file_get_contents($dbCfgFile),true)??[];
    try {
        $pdo = new PDO(
            "mysql:host={$dbCfg['host']};port={$dbCfg['port']};dbname={$dbCfg['name']};charset=utf8mb4",
            $dbCfg['user'], $dbCfg['password'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>true]
        );

        // ── Robust SQL statement parser ──────────────────────────────────────
        // Correctly handles:
        //   - Quoted strings (', ", `) with backslash and doubled-quote escapes
        //   - Line comments: -- ...
        //   - Block comments: /* ... */  (skipped entirely)
        //   - MySQL conditional comments: /*!nnnnn ... */ — EXECUTED as SQL
        //     (these are NOT comments to MySQL — they contain real statements)
        //   - Statement separator: ;
        // This is the root cause of "no active transaction" errors — the old
        // parser incorrectly skipped /*!...*/ the same as /* */ comments, so
        // FOREIGN_KEY_CHECKS and other session vars were never set/restored.
        $statements = [];
        $current    = '';
        $inString   = false;
        $stringChar = '';
        $len        = strlen($sqlContent);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sqlContent[$i];

            // Inside a quoted string
            if ($inString) {
                $current .= $ch;
                if ($ch === '\\' && $i + 1 < $len) {
                    $current .= $sqlContent[++$i]; // backslash escape
                    continue;
                }
                if ($ch === $stringChar) {
                    if ($i + 1 < $len && $sqlContent[$i + 1] === $stringChar) {
                        $current .= $sqlContent[++$i]; // doubled-quote ('' or "")
                        continue;
                    }
                    $inString = false;
                }
                continue;
            }

            // Start of a quoted string
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $inString   = true;
                $stringChar = $ch;
                $current   .= $ch;
                continue;
            }

            // Line comment: -- ...  (skip to end of line)
            if ($ch === '-' && $i + 1 < $len && $sqlContent[$i + 1] === '-') {
                while ($i < $len && $sqlContent[$i] !== "\n") $i++;
                continue;
            }

            // Block or conditional comment starting with /*
            if ($ch === '/' && $i + 1 < $len && $sqlContent[$i + 1] === '*') {
                // MySQL conditional comment /*!nnnnn ... */ — treat as executable SQL
                if ($i + 2 < $len && $sqlContent[$i + 2] === '!') {
                    $i += 3;
                    while ($i < $len && ctype_digit($sqlContent[$i])) $i++; // skip version number
                    // Collect everything up to closing */
                    $condContent = '';
                    while ($i + 1 < $len && !($sqlContent[$i] === '*' && $sqlContent[$i + 1] === '/')) {
                        $condContent .= $sqlContent[$i++];
                    }
                    $i += 1; // skip */
                    // Add the inner content as part of current statement
                    $current .= ' ' . trim($condContent) . ' ';
                    continue;
                }
                // Regular block comment /* ... */ — skip entirely
                $i += 2;
                while ($i + 1 < $len && !($sqlContent[$i] === '*' && $sqlContent[$i + 1] === '/')) $i++;
                $i += 1; // skip */
                continue;
            }

            // Statement terminator
            if ($ch === ';') {
                $stmt = trim($current);
                if ($stmt !== '' && $stmt !== ';') $statements[] = $stmt;
                $current = '';
                continue;
            }

            $current .= $ch;
        }
        // Trailing statement without semicolon
        $stmt = trim($current);
        if ($stmt !== '' && $stmt !== ';') $statements[] = $stmt;

        if (empty($statements)) {
            setFlash('danger', 'No valid SQL statements found in the backup file.');
            header('Location: index.php?p=settings'); exit;
        }

        // ── Execute statements one by one — NO transaction wrapper ──
        // MySQL DDL (DROP TABLE, CREATE TABLE) causes an implicit COMMIT, so
        // wrapping in PDO beginTransaction()/commit() throws "There is no active
        // transaction" — we execute statement-by-statement exactly like phpMyAdmin.
        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("SET UNIQUE_CHECKS = 0");
        $pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

        $count      = 0;
        $skipErrors = 0;
        $restErrors = [];
        foreach ($statements as $stmt) {
            $trimmed = trim($stmt);
            if ($trimmed === '') continue;
            // Skip pure SQL comments that slipped through
            if (substr($trimmed, 0, 2) === '--') continue;
            // Skip LOCK/UNLOCK TABLES — harmless to skip, avoids privilege errors on shared hosts
            $upper12 = strtoupper(substr($trimmed, 0, 12));
            if (strpos($upper12, 'LOCK TABLE') === 0 || strpos($upper12, 'UNLOCK TABL') === 0) continue;
            // Skip CREATE DATABASE and USE — shared hosting users have no CREATE DATABASE
            // privilege. The PDO DSN already targets the correct database.
            $upper16 = strtoupper(substr($trimmed, 0, 16));
            if (strpos($upper16, 'CREATE DATABASE') === 0) continue;
            if (strtoupper(substr($trimmed, 0, 4)) === 'USE ') continue;
            try {
                $pdo->exec($trimmed);
                $count++;
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                // Silently skip "already exists" — DROP IF EXISTS should prevent this,
                // but handle it gracefully if importing into an existing populated DB
                if (stripos($msg, 'already exists') !== false ||
                    stripos($msg, 'Duplicate entry') !== false) {
                    $skipErrors++;
                    continue;
                }
                // Unknown column / table on re-import — tolerate
                if (stripos($msg, 'Unknown column') !== false ||
                    stripos($msg, 'Unknown table') !== false) {
                    $skipErrors++;
                    continue;
                }
                $restErrors[] = substr($trimmed, 0, 120) . ' → ' . $msg;
                if (count($restErrors) >= 5) {
                    $restErrors[] = '...further errors suppressed';
                    break;
                }
            }
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        $pdo->exec("SET UNIQUE_CHECKS = 1");

        // Bust the settings cache so the restored settings load immediately
        $GLOBALS['_settings_cache'] = null;
        // Auto-repair currency field if it got corrupted during restore
        try {
            $cfgAfter = $pdo->query("SELECT currency FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $rawCur = $cfgAfter['currency'] ?? '';
            $safeCurs = ['&#8377;','$','&euro;','&pound;','&#165;'];
            if (!in_array($rawCur, $safeCurs, true)) {
                $pdo->exec("UPDATE settings SET currency='&#8377;' LIMIT 1");
            }
        } catch(Exception $e) { /* non-fatal */ }

        $skipNote = $skipErrors > 0 ? " ({$skipErrors} 'already exists' skipped safely)" : '';
        if(empty($restErrors)){
            setFlash('success',"Restore completed successfully. {$count} statements executed{$skipNote}. Please log out and back in to reload settings.");
        } else {
            setFlash('danger','Restore finished with '.count($restErrors).' error(s): '.implode(' | ', array_slice($restErrors,0,3)));
        }
        header('Location: index.php?p=settings'); exit;

    } catch(Exception $ex){
        setFlash('danger','Restore failed: '.$ex->getMessage());
        header('Location: index.php?p=settings'); exit;
    }
}


adminHeader('Settings','settings');
?>
<div class="page-hdr">
  <div>
    <div class="page-title">Settings</div>
    <div class="page-sub">Manage store configuration, email, security and system</div>
  </div>
</div>
<?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>

<!-- Settings Grid: Left + Right columns, equal gap on all cards -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start">

<!-- ══ LEFT COLUMN ══ -->
<div style="display:flex;flex-direction:column;gap:16px">

  <!-- Store Information -->
  <div class="card">
    <div class="card-hdr">
      <div class="card-title">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:middle;opacity:.6"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Store Information
      </div>
    </div>
    <div class="card-body">
      <form method="POST"><?=csrfField()?><input type="hidden" name="action" value="general">
        <div class="form-group"><label class="form-label">Store Name</label><input class="form-control" type="text" name="store_name" value="<?=e($cfg['store_name']??'')?>"></div>
        <div class="form-group"><label class="form-label">Address</label><textarea class="form-control" name="store_address" rows="2"><?=e($cfg['store_address']??'')?></textarea></div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Phone</label><input class="form-control" type="text" name="store_phone" value="<?=e($cfg['store_phone']??'')?>"></div>
          <div class="form-group"><label class="form-label">Email</label><input class="form-control" type="email" name="store_email" value="<?=e($cfg['store_email']??'')?>"><div class="form-hint">Used for store contact &amp; notifications.</div></div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">GST Number (GSTIN)</label><input class="form-control" type="text" name="store_gst" value="<?=e($cfg['store_gst']??'')?>" placeholder="15-digit GSTIN"></div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Drug Licence (DL) Number</label><input class="form-control" type="text" name="store_dl" value="<?=e($cfg['store_dl']??'')?>" placeholder="DL No. e.g. MH-XX-12345"><div class="form-hint">Used on all invoices and purchase orders.</div></div>
          <div class="form-group"><label class="form-label">Currency</label>
            <select class="form-control" name="currency">
              <?php $currOpts=['&#8377;'=>'&#8377; INR — Rupee','$'=>'$ USD — Dollar','&euro;'=>'&euro; EUR — Euro','&pound;'=>'&pound; GBP — Pound']; $curSym=currencySymbol();
              foreach($currOpts as $sym=>$lbl):?>
              <option value="<?=e($sym)?>" <?=$curSym===$sym?'selected':''?>><?=$lbl?></option>
              <?php endforeach;?>
            </select>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Low Stock Threshold</label><input class="form-control" type="number" name="low_qty" value="<?=e($cfg['low_qty']??LOW_QTY)?>" min="1"></div>
          <div class="form-group"><label class="form-label">Expiry Warning (days)</label><input class="form-control" type="number" name="expiry_days" value="<?=e($cfg['expiry_days']??EXPIRY_DAYS)?>" min="1"></div>
        </div>
        <button type="submit" class="btn btn-primary">Save Settings</button>
      </form>
    </div>
  </div>

  <!-- Change Password -->
  <div class="card">
    <div class="card-hdr">
      <div class="card-title">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:middle;opacity:.6"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Change Password
      </div>
    </div>
    <div class="card-body">
      <form method="POST"><?=csrfField()?><input type="hidden" name="action" value="password">
        <div class="form-group"><label class="form-label">Current Password</label><input class="form-control" type="password" name="old_pw" required></div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">New Password</label><input class="form-control" type="password" name="new_pw" required placeholder="Min 6 characters"></div>
          <div class="form-group"><label class="form-label">Confirm New Password</label><input class="form-control" type="password" name="conf_pw" required></div>
        </div>
        <button type="submit" class="btn btn-primary">Change Password</button>
      </form>
    </div>
  </div>

  <!-- System Information -->
  <div class="card">
    <div class="card-hdr">
      <div class="card-title">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:middle;opacity:.6"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        System Information
      </div>
    </div>
    <div class="card-body" style="padding-bottom:4px">
      <?php
      $storageMode = strtoupper($cfg['storage']??'JSON');
      $storageChip = '<span class="chip '.($storageMode==='MYSQL'?'chip-blue':'chip-green').'">'.$storageMode.'</span>';
      $info=[
        'Storage'   => $storageChip,
        'Medicines' => $db->count('medicines').' medicines',
        'Batches'   => $db->count('batches').' batches',
        'Customers' => $db->count('customers').' patients',
        'Suppliers' => $db->count('suppliers').' suppliers',
        'Sales'     => $db->count('sales').' invoices',
        'Revenue'   => money($db->sum('sales','grand_total')),
      ];
      foreach($info as $k=>$v):?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--g2);font-size:.83rem">
        <span class="text-muted"><?=e($k)?></span><span class="fw-600"><?=$v?></span>
      </div>
      <?php endforeach;?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;font-size:.83rem">
        <span class="text-muted">Version</span>
        <span class="chip chip-blue"><?=APP_NAME?> v<?=APP_VERSION?></span>
      </div>
    </div>
  </div>

</div><!-- end left column -->

<!-- ══ RIGHT COLUMN ══ -->
<div style="display:flex;flex-direction:column;gap:16px">

  <!-- SMTP Email -->
  <div class="card">
    <div class="card-hdr">
      <div class="card-title">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:middle;opacity:.6"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        SMTP Email Configuration
      </div>
      <?php if(!empty($cfg['smtp_host'])):?>
      <span class="chip chip-green" style="font-size:.68rem">Configured</span>
      <?php else:?>
      <span class="chip chip-orange" style="font-size:.68rem">Not Set</span>
      <?php endif;?>
    </div>
    <div class="card-body">
      <form method="POST"><?=csrfField()?><input type="hidden" name="action" value="smtp">
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">SMTP Host</label><input class="form-control" type="text" name="smtp_host" value="<?=e($cfg['smtp_host']??'')?>" placeholder="smtp.gmail.com"></div>
          <div class="form-group"><label class="form-label">Port</label><input class="form-control" type="number" name="smtp_port" value="<?=e($cfg['smtp_port']??587)?>" placeholder="587"></div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">SMTP Username</label><input class="form-control" type="email" name="smtp_user" value="<?=e($cfg['smtp_user']??'')?>" placeholder="you@gmail.com"></div>
          <div class="form-group"><label class="form-label">SMTP Password</label><input class="form-control" type="password" name="smtp_pass" placeholder="Leave blank to keep existing"></div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">From Email</label><input class="form-control" type="email" name="smtp_from" value="<?=e($cfg['smtp_from']??'')?>" placeholder="noreply@yourstore.com"></div>
          <div class="form-group"><label class="form-label">From Name</label><input class="form-control" type="text" name="smtp_name" value="<?=e($cfg['smtp_name']??'')?>" placeholder="<?=e($cfg['store_name']??'')?>"></div>
        </div>
        <div class="form-group"><label class="form-label">Encryption</label>
          <select class="form-control" name="smtp_secure">
            <option value="tls"  <?=($cfg['smtp_secure']??'tls')==='tls'?'selected':''?>>TLS (Port 587) — Recommended</option>
            <option value="ssl"  <?=($cfg['smtp_secure']??'')==='ssl'?'selected':''?>>SSL (Port 465)</option>
            <option value="none" <?=($cfg['smtp_secure']??'')==='none'?'selected':''?>>None (Not Recommended)</option>
          </select>
        </div>
        <div class="flex gap-2">
          <button type="submit" class="btn btn-primary">Save SMTP</button>
        </div>
      </form>
      <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--g3)">
        <form method="POST"><?=csrfField()?><input type="hidden" name="action" value="smtp_test">
          <div class="form-group" style="margin-bottom:0"><label class="form-label">Test SMTP — Send Test Email</label>
            <div class="flex gap-2">
              <input class="form-control" type="email" name="test_email" placeholder="test@example.com">
              <button type="submit" class="btn btn-ghost" style="white-space:nowrap">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                Send Test
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Database Backup & Restore -->
  <div class="card">
    <div class="card-hdr">
      <div class="card-title">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:middle;opacity:.6"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
        Database Backup &amp; Restore
      </div>
    </div>
    <div class="card-body">
      <?php if(($cfg['storage']??'json')==='mysql'): ?>
      <!-- Backup -->
      <div style="margin-bottom:16px">
        <div style="font-size:.82rem;font-weight:600;color:var(--navy);margin-bottom:6px">Backup</div>
        <div style="font-size:.81rem;color:var(--g6);margin-bottom:10px">Exports full database as SQL — compatible with phpMyAdmin &amp; mysqldump. No <code>CREATE DATABASE</code> — works on shared hosts.</div>
        <a href="index.php?p=settings&action=backup_mysql" class="btn btn-primary btn-sm">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Download Full Backup (.sql)
        </a>
      </div>
      <div style="padding-top:16px;border-top:1px solid var(--g3)">
        <div style="font-size:.82rem;font-weight:600;color:var(--navy);margin-bottom:6px">Restore</div>
        <div class="alert alert-warning" style="margin-bottom:10px"><span class="alert-body"><strong>Warning:</strong> This will overwrite ALL current data. Take a backup first.</span></div>
        <form method="POST" enctype="multipart/form-data"><?=csrfField()?><input type="hidden" name="action" value="restore_mysql">
          <div class="form-group"><label class="form-label">Upload .sql File</label>
            <input class="form-control" type="file" name="sql_file" accept=".sql" required>
            <div class="form-hint">Accepts DRXStore backups or phpMyAdmin exports.</div>
          </div>
          <button type="submit" class="btn btn-danger" onclick="return confirm('This will OVERWRITE all current data.\n\nAre you absolutely sure?')">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
            Restore Database
          </button>
        </form>
      </div>
      <?php else: ?>
      <div class="alert alert-info" style="margin-bottom:14px"><span class="alert-body">
        You are using <strong>JSON file storage</strong>. Your data is in the <code>data/</code> folder.<br>
        To switch to MySQL and migrate all your data, use the migration tool below.
      </span></div>
      <div style="border:1.5px solid var(--navy);border-radius:var(--rl);overflow:hidden">
        <div style="padding:12px 16px;background:var(--navy);color:#fff;font-size:.84rem;font-weight:600">
          Migrate JSON Data to MySQL
        </div>
        <div style="padding:16px" id="migrateBox">
          <div style="font-size:.81rem;color:var(--g6);margin-bottom:14px">Enter your MySQL connection details. All existing data will be migrated without loss.</div>
          <div class="form-row-2">
            <div class="form-group"><label class="form-label">Host</label><input class="form-control" type="text" id="mig_host" value="localhost" placeholder="localhost"></div>
            <div class="form-group"><label class="form-label">Port</label><input class="form-control" type="number" id="mig_port" value="3306"></div>
          </div>
          <div class="form-row-2">
            <div class="form-group"><label class="form-label">Database Name <span class="req">*</span></label><input class="form-control" type="text" id="mig_name" placeholder="drxstore"></div>
            <div class="form-group"><label class="form-label">Username</label><input class="form-control" type="text" id="mig_user" value="root"></div>
          </div>
          <div class="form-row-2">
            <div class="form-group"><label class="form-label">Password</label><input class="form-control" type="password" id="mig_pass"></div>
            <div class="form-group" style="display:flex;align-items:flex-end">
              <button type="button" class="btn btn-ghost w-full" id="migTestBtn" onclick="testMigration()">Test Connection</button>
            </div>
          </div>
          <div id="migResult" style="display:none;margin-bottom:12px"></div>
          <button type="button" class="btn btn-primary" id="migStartBtn" onclick="startMigration()" disabled>
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
            Migrate All Data to MySQL
          </button>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Software Update -->
  <div class="card">
    <div class="card-hdr">
      <div class="card-title">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:middle;opacity:.6"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
        Software Update
      </div>
      <span class="chip chip-blue" style="font-size:.68rem">v<?=APP_VERSION?></span>
    </div>
    <div class="card-body">
      <div style="margin-bottom:12px">
        <div style="font-size:.86rem;font-weight:600;color:var(--navy);margin-bottom:4px"><?=APP_NAME?> v<?=APP_VERSION?> installed</div>
        <div style="font-size:.79rem;color:var(--g6);line-height:1.55">Upload a new release ZIP to update. Your data, database and settings are never touched during update.</div>
      </div>
      <a href="index.php?p=sw_update" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
        Update Software
      </a>
    </div>
  </div>

</div><!-- end right column -->
</div><!-- end grid -->


<script>
function testMigration(){
  var btn=document.getElementById('migTestBtn');
  var res=document.getElementById('migResult');
  if(!btn||!res) return;
  btn.disabled=true; btn.textContent='Testing...';
  res.style.display='block';
  res.innerHTML='<div class="alert alert-info"><span class="alert-body">Connecting...</span></div>';
  var url='index.php?p=settings&action=test_migration'
    +'&host='+encodeURIComponent(document.getElementById('mig_host').value)
    +'&port='+encodeURIComponent(document.getElementById('mig_port').value)
    +'&name='+encodeURIComponent(document.getElementById('mig_name').value)
    +'&user='+encodeURIComponent(document.getElementById('mig_user').value)
    +'&pass='+encodeURIComponent(document.getElementById('mig_pass').value);
  fetch(url).then(r=>r.json()).then(d=>{
    res.innerHTML=d.ok
      ?'<div class="alert alert-success"><span class="alert-body">'+d.msg+'</span></div>'
      :'<div class="alert alert-danger"><span class="alert-body">'+d.msg+'</span></div>';
    if(d.ok) document.getElementById('migStartBtn').disabled=false;
    btn.disabled=false; btn.textContent='Test Connection';
  }).catch(e=>{
    res.innerHTML='<div class="alert alert-danger"><span class="alert-body">Error: '+e.message+'</span></div>';
    btn.disabled=false; btn.textContent='Test Connection';
  });
}
function startMigration(){
  if(!confirm('This will migrate ALL your JSON data to MySQL and switch storage. Your JSON files will remain as backup.

Proceed?')) return;
  var btn=document.getElementById('migStartBtn');
  var res=document.getElementById('migResult');
  btn.disabled=true; btn.textContent='Migrating...';
  res.style.display='block';
  res.innerHTML='<div class="alert alert-info"><span class="alert-body">Migration in progress — please wait...</span></div>';

  var fd=new FormData();
  fd.append('action','do_migration');
  var csrfEl = document.querySelector('input[name=csrf_token]');
  if(!csrfEl){res.innerHTML='<div class="alert alert-danger"><span class="alert-body">Security token missing. Please reload the page.</span></div>';btn.disabled=false;btn.textContent='Migrate All Data to MySQL';return;}
  fd.append('csrf_token', csrfEl.value);
  fd.append('host', document.getElementById('mig_host').value);
  fd.append('port', document.getElementById('mig_port').value);
  fd.append('name', document.getElementById('mig_name').value);
  fd.append('user', document.getElementById('mig_user').value);
  fd.append('pass', document.getElementById('mig_pass').value);

  fetch('index.php?p=settings', {method:'POST', body:fd})
    .then(r=>r.json())
    .then(d=>{
      if(d.ok){
        res.innerHTML='<div class="alert alert-success"><span class="alert-body"><strong>'+d.msg+'</strong></span></div>';
        setTimeout(()=>location.reload(), 2500);
      } else {
        res.innerHTML='<div class="alert alert-danger"><span class="alert-body">'+d.msg+'</span></div>';
        btn.disabled=false; btn.textContent='Migrate All Data to MySQL';
      }
    }).catch(e=>{
      res.innerHTML='<div class="alert alert-danger"><span class="alert-body">Error: '+e.message+'</span></div>';
      btn.disabled=false; btn.textContent='Migrate All Data to MySQL';
    });
}
</script>

<?php adminFooter();?>
