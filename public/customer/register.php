<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; startSession();
if (!defined('VERIFY_TOKEN_TTL')) define('VERIFY_TOKEN_TTL', 86400);
if(!empty($_SESSION['cust_id'])){header('Location: index.php?p=cust_dash');exit;}
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf();
    $name=post('name'); $email=post('email'); $phone=post('phone'); $pw=post('password'); $conf=post('confirm');
    if(!$name)$errors[]='Full name required.';
    if(!$email||!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Valid email required.';
    if(!$phone)$errors[]='Phone required.';
    if(strlen($pw)<6)$errors[]='Password min 6 characters.';
    if($pw!==$conf)$errors[]='Passwords do not match.';
    if($email&&$db->findOne('customers',fn($c)=>strtolower($c['email']??'')===strtolower($email)))$errors[]='Email already registered.';
    if(empty($errors)){
        $token=bin2hex(random_bytes(32));
        $db->insert('customers',[
            'name'=>$name,
            'email'=>$email,
            'phone'=>$phone,
            'password'=>password_hash($pw,PASSWORD_BCRYPT),
            'active'=>1,
            'verified'=>0,
            'verify_token'=>$token,
            'verify_sent_at'=>date('Y-m-d H:i:s'),
            'created_at'=>date('Y-m-d H:i:s')
        ]);
        // Send verification email
        $store=storeName(); $vurl=siteUrl('verify',['token'=>$token]);
        $body=mailWrap("Verify Your Email","<p>Dear {$name},</p><p>Thank you for registering at <strong>{$store}</strong>!</p><p>Please click the button below to verify your email address:</p><a href='{$vurl}' class='btn'>Verify My Email</a><p>Or copy this link:<br><code>{$vurl}</code></p><p>If you did not register, ignore this email.</p>");
        sendMail($email,"Verify Your Email — {$store}",$body);
        setFlash('success','Account created successfully! A verification link has been sent to ' . $email . '. Please verify your email before logging in.');
        header('Location: index.php?p=cust_login'); exit;
    }
}
$cfg=getSettings();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Register — <?=e($cfg['store_name']??APP_NAME)?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?php echo assetUrl('assets/css/app.css'); ?>"></head><body>
<div class="auth-page">
<div class="auth-card" style="max-width:440px">
  <div class="auth-logo"><div class="auth-logo-icon" style="background:#581c87"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div><div class="auth-logo-text"><h1><?=e($cfg['store_name']??APP_NAME)?></h1><p>Create Patient Account</p></div></div>
  <h2 class="auth-h2">Create Your Account</h2>
  <p class="auth-sub">Email verification required after registration.</p>
  <?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>
  <form method="POST" novalidate><?=csrfField()?>
    <div class="form-group"><label class="form-label">Full Name <span class="req">*</span></label><input class="form-control" type="text" name="name" value="<?=e(post('name'))?>" required autofocus></div>
    <div class="form-group"><label class="form-label">Email Address <span class="req">*</span></label><input class="form-control" type="email" name="email" value="<?=e(post('email'))?>" required><div class="form-hint">A verification link will be sent to this email.</div></div>
    <div class="form-group"><label class="form-label">Phone <span class="req">*</span></label><input class="form-control" type="tel" name="phone" value="<?=e(post('phone'))?>" required></div>
    <div class="form-group"><label class="form-label">Password <span class="req">*</span></label><input class="form-control" type="password" name="password" required placeholder="Min 6 characters"></div>
    <div class="form-group" style="margin-bottom:18px"><label class="form-label">Confirm Password <span class="req">*</span></label><input class="form-control" type="password" name="confirm" required></div>
    <button type="submit" class="btn btn-block btn-lg" style="background:#7b2d8b;color:#fff">Create Account</button>
  </form>
  <div class="auth-divider" style="margin-top:14px">already registered?</div>
  <a href="index.php?p=cust_login" class="btn btn-ghost btn-block">Sign In</a>
  <div class="auth-footer mt-2">Developed with 🫀 by <strong>Vineet</strong></div>
</div></div>
<script src="<?php echo assetUrl('assets/js/app.js'); ?>"></script></body></html>
