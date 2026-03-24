<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; startSession();
if(!empty($_SESSION['supplier_id'])){header('Location: index.php?p=sup_dash');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $ctx='supplier';
    $uname=post('username'); $pw=post('password');
    if(isLockedOut($ctx)){$error='Too many attempts. Wait '.LOCKOUT_MIN.' min.';}
    elseif(!$uname||!$pw){$error='Enter credentials.';}
    else{
        $su=$db->findOne('supplier_users',fn($u)=>strtolower($u['username']??'')===strtolower($uname)&&($u['active']??true));
        if($su&&password_verify($pw,$su['password']??'')){
            clearAttempts($ctx); session_regenerate_id(true);
            $_SESSION['supplier_id']=$su['id']; $_SESSION['supplier_name']=$su['username'];
            $sup=$db->findOne('suppliers',fn($s)=>$s['id']===$su['supplier_id']);
            $_SESSION['supplier_company']=$sup['name']??'Supplier';
            $db->update('supplier_users',fn($u)=>$u['id']===$su['id'],['last_login'=>date('Y-m-d H:i:s')]);
            setFlash('success','Welcome, '.($su['username']).'!'); header('Location: index.php?p=sup_dash');exit;
        } else { recordAttempt($ctx); $error='Invalid credentials.'; }
    }
}
$cfg=getSettings();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Supplier Login — <?=e($cfg['store_name']??APP_NAME)?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= assetUrl('assets/css/app.css') ?>"></head><body>
<div class="auth-page">
<div class="auth-card">
  <div class="auth-logo"><div class="auth-logo-icon" style="background:#166534"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h1"/><path d="M14 9h1"/><path d="M9 14h1"/><path d="M14 14h1"/></svg></div><div class="auth-logo-text"><h1><?=e($cfg['store_name']??APP_NAME)?></h1><p>Supplier Portal</p></div></div>
  <h2 class="auth-h2">Supplier Sign In</h2>
  <p class="auth-sub">Enter your supplier portal credentials.</p>
  <?php if($error):?><div class="alert alert-danger"><span class="alert-body"><?=e($error)?></span></div><?php endif;?>
  <form method="POST" novalidate><?=csrfField()?>
    <div class="form-group"><label class="form-label">Username</label><input class="form-control" type="text" name="username" value="<?=e(post('username'))?>" required autofocus autocomplete="username"></div>
    <div class="form-group" style="margin-bottom:18px"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required></div>
    <button type="submit" class="btn btn-success btn-block btn-lg">Sign In</button>
  </form>
  <div class="auth-divider" style="margin-top:16px">other portals</div>
  <div class="flex gap-2 justify-center flex-wrap"><a href="index.php?p=login" class="btn btn-ghost btn-sm">Admin</a><a href="index.php?p=cust_login" class="btn btn-ghost btn-sm">Patient</a></div>
  <div class="auth-footer">Developed with &#x1FAC0; by <strong>Vineet</strong></div>
</div></div>
<script src="<?= assetUrl('assets/js/app.js') ?>"></script></body></html>
