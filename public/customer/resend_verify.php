<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; startSession();
if (!defined('VERIFY_TOKEN_TTL')) define('VERIFY_TOKEN_TTL', 86400);
if(!empty($_SESSION['cust_id'])&&!empty($_SESSION['cust_verified'])){header('Location: index.php?p=cust_dash');exit;}

$msg=''; $msgType=''; $submitted=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf();
    $email=trim(post('email'));
    $submitted=true;
    if(!$email||!filter_var($email,FILTER_VALIDATE_EMAIL)){
        $msg='Please enter a valid email address.'; $msgType='danger';
    } else {
        $cust=$db->findOne('customers',fn($c)=>strtolower($c['email']??'')===strtolower($email));
        if($cust&&empty($cust['verified'])){
            $tok=bin2hex(random_bytes(32));
            $db->update('customers',fn($c)=>$c['id']===$cust['id'],[
                'verify_token'=>$tok,
                'verify_sent_at'=>date('Y-m-d H:i:s')
            ]);
            $store=storeName(); $vurl=siteUrl('verify',['token'=>$tok]);
            $body=mailWrap("Verify Your Email","<p>Dear {$cust['name']},</p><p>You requested a new verification link for <strong>{$store}</strong>.</p><p>Please click the button below to verify your email address:</p><a href='{$vurl}' class='btn'>Verify My Email</a><p>Or copy this link:<br><code>{$vurl}</code></p><p>If you did not request this, ignore this email.</p>");
            sendMail($email,"Verify Your Email — {$store}",$body);
        }
        // Always show same message to prevent email enumeration
        $msg='If that email is registered and unverified, a new link has been sent. Please check your inbox.';
        $msgType='success';
    }
}
$cfg=getSettings(); $store=storeName();
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Resend Verification — <?=e($store)?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?=assetUrl('assets/css/app.css')?>">
</head><body>
<div class="auth-page"><div class="auth-card" style="max-width:420px">
  <div class="auth-logo">
    <div class="auth-logo-icon" style="background:#581c87">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
    </div>
    <div class="auth-logo-text"><h1><?=e($store)?></h1><p>Email Verification</p></div>
  </div>
  <h2 class="auth-h2">Resend Verification</h2>
  <p class="auth-sub">Enter your registered email and we'll send a new verification link.</p>
  <?php if($msg):?><div class="alert alert-<?=$msgType?>"><span class="alert-body"><?=e($msg)?></span></div><?php endif;?>
  <?php if(!($msgType==='success')):?>
  <form method="POST" novalidate><?=csrfField()?>
    <div class="form-group"><label class="form-label">Email Address</label>
      <input class="form-control" type="email" name="email" value="<?=e(post('email'))?>" required autofocus placeholder="your@email.com">
    </div>
    <button type="submit" class="btn btn-block btn-lg" style="background:#581c87;color:#fff">Send Verification Link</button>
  </form>
  <?php endif;?>
  <div class="auth-divider" style="margin-top:14px">already verified?</div>
  <a href="index.php?p=cust_login" class="btn btn-ghost btn-block">Sign In</a>
  <div class="auth-footer">Developed with &#x1FAC0; by <strong>Vineet</strong></div>
</div></div>
<script src="<?=assetUrl('assets/js/app.js')?>"></script>
</body></html>
