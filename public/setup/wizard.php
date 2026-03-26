<?php
/**
 * DRXStore - First-Launch Setup Wizard
 * Self-deletes after successful setup.
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT . '/config/app.php';
startSession();

if ($db->count('settings') > 0) { header('Location: index.php?p=login'); exit; }

// AJAX MySQL connection test
if ($_SERVER['REQUEST_METHOD']==='POST' && post('action') === 'test_mysql') {
    verifyCsrf();
    header('Content-Type: application/json');
    $err = MySQLDB::testConnection(post('host','localhost'), postInt('port',3306), post('name',''), post('user','root'), post('pass',''));
    echo json_encode(['ok'=>$err===null, 'msg'=>$err ?? 'Connection successful! Database ready.']);
    exit;
}

$step = postInt('step',1) ?: max(1,getInt('step',1));
$errors = [];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf(); $step = postInt('step',1);
    if ($step===1) {
        $n=post('store_name'); $e=post('store_email');
        if (!$n) $errors[]='Store name required.';
        if (!filter_var($e,FILTER_VALIDATE_EMAIL)) $errors[]='Valid email required.';
        if (empty($errors)) { $_SESSION['setup']=array_merge($_SESSION['setup']??[],['store_name'=>$n,'store_email'=>$e,'store_addr'=>post('store_address'),'store_phone'=>post('store_phone'),'store_gst'=>post('store_gst'),'store_dl'=>post('store_dl'),'currency'=>(in_array(post('currency'),['&#8377;','$','&euro;','&pound;'],true)?post('currency'):'&#8377;')]); $step=2; }
    } elseif ($step===2) {
        $fn=post('full_name'); $un=post('username'); $em=post('email'); $pw=post('password'); $cn=post('confirm');
        if (!$fn) $errors[]='Full name required.';
        if (!$un) $errors[]='Username required.';
        if (!filter_var($em,FILTER_VALIDATE_EMAIL)) $errors[]='Valid email required.';
        if (strlen($pw)<6) $errors[]='Password must be at least 6 characters.';
        if ($pw!==$cn) $errors[]='Passwords do not match.';
        if (empty($errors)) { $_SESSION['setup']['admin']=compact('fn','un','em','pw'); $step=3; }
    } elseif ($step===3) {
        $driver=post('driver','json'); $_SESSION['setup']['driver']=$driver;
        if ($driver==='mysql') {
            $mh=post('mysql_host','localhost'); $mp=postInt('mysql_port',3306); $mn=post('mysql_name'); $mu=post('mysql_user'); $ms=post('mysql_pass');
            if (!$mn) $errors[]='Database name required.';
            if (empty($errors)) {
                $err=MySQLDB::testConnection($mh,$mp,$mn,$mu,$ms);
                if ($err!==null) {
                    $errors[]='MySQL connection failed: '.$err;
                    $errors[]='Ensure MySQL is running, database exists, credentials correct. You can use JSON storage instead.';
                } else {
                    $_SESSION['setup']['mysql']=compact('mh','mp','mn','mu','ms');
                }
            }
        }
        if (empty($errors)) $step=4;
    } elseif ($step===4) {
        $s=$_SESSION['setup']??[]; $a=$s['admin']??[];
        if (empty($s)||empty($a)) { $step=1; } else {
            if (($s['driver']??'json')==='mysql'&&!empty($s['mysql'])) {
                $m=$s['mysql'];
                try { $pdo=new PDO("mysql:host={$m['mh']};port={$m['mp']};dbname={$m['mn']};charset=utf8mb4",$m['mu'],$m['ms'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); MySQLDB::createTables($pdo); $db=new MySQLDB($m['mh'],$m['mp'],$m['mn'],$m['mu'],$m['ms']); }
                catch(Exception $ex){$errors[]='MySQL table creation failed: '.$ex->getMessage();goto render;}
                file_put_contents(DATA_DIR.'/db_config.json',json_encode(['driver'=>'mysql','host'=>$m['mh'],'port'=>$m['mp'],'name'=>$m['mn'],'user'=>$m['mu'],'password'=>$m['ms']]));
            }
            // Seed categories
            if($db->count('categories')===0){foreach(DOSAGE_FORMS as $f)$db->insert('categories',['name'=>$f,'type'=>'dosage']);}
            $db->insert('settings',['store_name'=>$s['store_name']??APP_NAME,'store_address'=>$s['store_addr']??'','store_phone'=>$s['store_phone']??'','store_email'=>$s['store_email']??'','store_gst'=>$s['store_gst']??'','store_dl'=>$s['store_dl']??'','currency'=>$s['currency']??'&#8377;','storage'=>$s['driver']??'json','setup_done'=>1,'created_at'=>date('Y-m-d H:i:s')]);
            $db->insert('users',['name'=>$a['fn'],'username'=>$a['un'],'email'=>$a['em'],'password'=>password_hash($a['pw'],PASSWORD_BCRYPT),'role'=>'admin','active'=>1,'created_at'=>date('Y-m-d H:i:s')]);
            unset($_SESSION['setup']);
            // Self-delete setup folder
            @unlink(__FILE__);
            @rmdir(dirname(__FILE__));
            setFlash('success','Setup complete! Welcome to '.APP_NAME.'.');
            header('Location: index.php?p=login'); exit;
        }
    }
}
render:
$s=$_SESSION['setup']??[];
$stepTitles=['Store Details','Admin Account','Data Storage','Confirm'];
$currency_sym = $s['currency'] ?? '&#8377;';
?>
<!DOCTYPE html><html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Setup &mdash; <?=APP_NAME?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?=assetUrl('assets/css/app.css')?>">
</head><body>
<div class="setup-wrap"><div class="setup-card">
  <div class="setup-hdr"><h1><?=APP_NAME?> &mdash; Initial Setup</h1><p>Step <?=$step?> of 4 &mdash; <?=$stepTitles[$step-1]?></p></div>
  <div class="setup-steps">
    <?php for($i=1;$i<=4;$i++):?><div class="setup-step <?=$i===$step?'active':($i<$step?'done':'')?>">
      <?=$i<$step?'&#10003; ':''?><?=$stepTitles[$i-1]?></div><?php endfor;?>
  </div>
  <div class="setup-body">
    <?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>
    <form method="POST"><?=csrfField()?><input type="hidden" name="step" value="<?=$step?>">

      <?php if($step===1):?>
      <div class="form-section">Pharmacy Information</div>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Store Name <span class="req">*</span></label><input class="form-control" type="text" name="store_name" value="<?=e($s['store_name']??'')?>" required autofocus></div>
        <div class="form-group"><label class="form-label">Store Email <span class="req">*</span></label><input class="form-control" type="email" name="store_email" value="<?=e($s['store_email']??'')?>" required></div>
      </div>
      <div class="form-group"><label class="form-label">Address</label><textarea class="form-control" name="store_address" rows="2"><?=e($s['store_addr']??'')?></textarea></div>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Phone</label><input class="form-control" type="text" name="store_phone" value="<?=e($s['store_phone']??'')?>"></div>
        <div class="form-group"><label class="form-label">GST Number (GSTIN)</label><input class="form-control" type="text" name="store_gst" value="<?=e($s['store_gst']??'')?>" placeholder="15-digit GSTIN"></div>
      </div>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Drug Licence (DL) Number</label><input class="form-control" type="text" name="store_dl" value="<?=e($s['store_dl']??'')?>" placeholder="e.g. MH-XX-12345"><div class="form-hint">Optional — will appear on all invoices.</div></div>
      </div>
      <div class="form-group"><label class="form-label">Currency</label>
        <select class="form-control" name="currency">
          <?php foreach(['&#8377;'=>'&#8377; INR &mdash; Indian Rupee','$'=>'$ USD &mdash; US Dollar','&euro;'=>'&euro; EUR &mdash; Euro','&pound;'=>'&pound; GBP &mdash; British Pound'] as $cs=>$cl):?>
          <option value="<?=htmlspecialchars($cs,ENT_QUOTES)?>" <?=$currency_sym===$cs?'selected':''?>><?=$cl?></option>
          <?php endforeach;?>
        </select>
      </div>

      <?php elseif($step===2):?>
      <div class="form-section">Admin Account</div>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Full Name <span class="req">*</span></label><input class="form-control" type="text" name="full_name" value="<?=e($s['admin']['fn']??'')?>" required autofocus></div>
        <div class="form-group"><label class="form-label">Username <span class="req">*</span></label><input class="form-control" type="text" name="username" value="<?=e($s['admin']['un']??'')?>" required></div>
      </div>
      <div class="form-group"><label class="form-label">Email <span class="req">*</span></label><input class="form-control" type="email" name="email" value="<?=e($s['admin']['em']??'')?>" required></div>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Password <span class="req">*</span></label><input class="form-control" type="password" name="password" required placeholder="Min 6 characters"></div>
        <div class="form-group"><label class="form-label">Confirm Password <span class="req">*</span></label><input class="form-control" type="password" name="confirm" required></div>
      </div>

      <?php elseif($step===3):?>
      <div class="form-section">Data Storage</div>
      <div class="form-group"><label class="form-label">Storage Engine</label>
        <select class="form-control" name="driver" id="storageDriver" onchange="toggleMySQL()">
          <option value="json" <?=($s['driver']??'json')==='json'?'selected':''?>>JSON Files &mdash; No setup needed. Recommended for Termux &amp; small installs.</option>
          <option value="mysql" <?=($s['driver']??'')==='mysql'?'selected':''?>>MySQL / MariaDB &mdash; For production servers.</option>
        </select>
      </div>
      <div id="mysqlFields" style="<?=($s['driver']??'json')==='mysql'?'':'display:none'?>">
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Host</label><input class="form-control" type="text" name="mysql_host" id="mhost" value="<?=e($s['mysql']['mh']??'localhost')?>" placeholder="localhost"></div>
          <div class="form-group"><label class="form-label">Port</label><input class="form-control" type="number" name="mysql_port" id="mport" value="<?=e($s['mysql']['mp']??3306)?>"></div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Database Name <span class="req">*</span></label><input class="form-control" type="text" name="mysql_name" id="mname" value="<?=e($s['mysql']['mn']??'drxstore')?>" placeholder="drxstore"><div class="form-hint">Will be created automatically if it does not exist.</div></div>
          <div class="form-group"><label class="form-label">Username</label><input class="form-control" type="text" name="mysql_user" id="muser" value="<?=e($s['mysql']['mu']??'root')?>"></div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Password</label><input class="form-control" type="password" name="mysql_pass" id="mpass"></div>
          <div class="form-group" style="display:flex;align-items:flex-end"><button type="button" class="btn btn-ghost w-full" id="testBtn" onclick="testMysql()">Test Connection</button></div>
        </div>
        <div id="mysqlResult" style="display:none"></div>
      </div>

      <?php elseif($step===4):?>
      <div class="form-section">Review &amp; Confirm</div>
      <?php $info=['Store Name'=>$s['store_name']??'','Email'=>$s['store_email']??'','Phone'=>$s['store_phone']??'','GST'=>$s['store_gst']??'','DL Number'=>$s['store_dl']??'','Admin Name'=>$s['admin']['fn']??'','Username'=>$s['admin']['un']??'','Admin Email'=>$s['admin']['em']??'','Storage'=>strtoupper($s['driver']??'JSON')];?>
      <div style="background:var(--g1);border-radius:var(--rl);padding:16px;border:1px solid var(--g3)">
        <?php foreach($info as $k=>$v):?><div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--g3);font-size:.83rem"><span class="text-muted"><?=e($k)?></span><span class="fw-600"><?=e($v)?:'&mdash;'?></span></div><?php endforeach;?>
      </div>
      <div class="alert alert-info" style="margin-top:14px"><span class="alert-body">After clicking <strong>Complete Setup</strong>, this setup page will be deleted and you will be redirected to login.</span></div>
      <?php endif;?>

      <div class="flex gap-2" style="margin-top:20px">
        <?php if($step>1&&$step<4):?><a href="index.php?p=setup&step=<?=$step-1?>" class="btn btn-ghost">Back</a><?php endif;?>
        <button type="submit" class="btn btn-primary btn-lg"><?=$step===4?'Complete Setup':'Next Step'?></button>
      </div>
    </form>
  </div>
</div></div>
<script>
function toggleMySQL(){document.getElementById('mysqlFields').style.display=document.getElementById('storageDriver').value==='mysql'?'block':'none';}
function testMysql(){
  var btn=document.getElementById('testBtn'),res=document.getElementById('mysqlResult');
  btn.disabled=true;btn.textContent='Testing...';res.style.display='block';
  res.innerHTML='<div class="alert alert-info"><span class="alert-body">Connecting...</span></div>';
  var fd=new FormData();
  fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
  fd.append('action', 'test_mysql');
  fd.append('host', document.getElementById('mhost').value);
  fd.append('port', document.getElementById('mport').value);
  fd.append('name', document.getElementById('mname').value);
  fd.append('user', document.getElementById('muser').value);
  fd.append('pass', document.getElementById('mpass').value);
  fetch('index.php?p=setup',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
    res.innerHTML=d.ok?'<div class="alert alert-success"><span class="alert-body">'+d.msg+'</span></div>':'<div class="alert alert-danger"><span class="alert-body">'+d.msg+'</span></div>';
    btn.disabled=false;btn.textContent='Test Connection';
  }).catch(function(e){res.innerHTML='<div class="alert alert-danger"><span class="alert-body">Error: '+e.message+'</span></div>';btn.disabled=false;btn.textContent='Test Connection';});
}
</script></body></html>
