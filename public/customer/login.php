<?php
/**
 * DRXStore - Customer/Patient Login
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php';
startSession();
if (!empty($_SESSION['cust_id'])) { header('Location: index.php?p=cust_dash'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf(); $ctx = 'customer';
    $email = post('email'); $pw = post('password');
    if (isLockedOut($ctx)) {
        $error = 'Too many failed attempts. Please wait ' . LOCKOUT_MIN . ' minutes.';
    } elseif (!$email || !$pw) {
        $error = 'Please enter your email and password.';
    } else {
        $cust = $db->findOne('customers', fn($c) =>
            strtolower($c['email'] ?? '') === strtolower($email) &&
            ($c['active'] ?? true) &&
            !empty($c['password'])
        );
        if ($cust && password_verify($pw, $cust['password'])) {
            if (empty($cust['verified'])) {
                $error = 'Please verify your email before signing in.';
            } else {
                clearAttempts($ctx);
                session_regenerate_id(true);
                $_SESSION['cust_id']      = $cust['id'];
                $_SESSION['customer_name']= $cust['name'];
                $_SESSION['cust_verified']= true;
                $db->update('customers', fn($c) => $c['id'] === $cust['id'], ['last_login' => date('Y-m-d H:i:s')]);
                setFlash('success', 'Welcome, ' . $cust['name'] . '!');
                header('Location: index.php?p=cust_dash'); exit;
            }
        } else {
            recordAttempt($ctx);
            $error = 'Invalid email or password.';
        }
    }
}
$cfg = getSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Patient Login &mdash; <?=e($cfg['store_name']??APP_NAME)?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?=assetUrl('assets/css/app.css')?>">
</head>
<body>
<div class="auth-page">
<div class="auth-card">
  <div class="auth-logo">
    <div class="auth-logo-icon" style="background:#581c87">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    </div>
    <div class="auth-logo-text">
      <h1><?=e($cfg['store_name']??APP_NAME)?></h1>
      <p>Patient Portal</p>
    </div>
  </div>
  <h2 class="auth-h2">Patient Sign In</h2>
  <p class="auth-sub">Access your purchase history and orders.</p>

  <?php if ($error): ?>
  <div class="alert alert-danger"><span class="alert-body"><?=e($error)?></span></div>
  <?php endif; ?>

  <form method="POST" novalidate><?=csrfField()?>
    <div class="form-group"><label class="form-label">Email Address</label>
      <input class="form-control" type="email" name="email" value="<?=e(post('email'))?>" required autofocus>
    </div>
    <div class="form-group" style="margin-bottom:18px"><label class="form-label">Password</label>
      <input class="form-control" type="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-block btn-lg" style="background:#581c87;color:#fff;border:none;border-radius:var(--r);padding:10px;font-size:.9rem;font-weight:600;cursor:pointer">Sign In</button>
  </form>

  <div class="auth-divider" style="margin-top:16px">new patient?</div>
  <a href="index.php?p=cust_reg" class="btn btn-ghost btn-block">Create Account</a>
  <p style="text-align:center;margin-top:12px;font-size:.78rem;color:var(--g5)">
    Forgot password? Contact your pharmacy administrator.
  </p>
  <div class="auth-divider" style="margin-top:10px">email not verified?</div>
  <a href="index.php?p=resend_verify" class="btn btn-ghost btn-block" style="font-size:.85rem">Resend Verification Email</a>
  <div class="auth-divider" style="margin-top:10px">other portals</div>
  <div class="flex gap-2 justify-center flex-wrap">
    <a href="index.php?p=login" class="btn btn-ghost btn-sm">Admin</a>
    <a href="index.php?p=sup_login" class="btn btn-ghost btn-sm">Supplier</a>
  </div>
  <div class="auth-footer">Developed with &#x1FAC0; by <strong>Vineet</strong></div>
</div>
</div>
<script src="<?=assetUrl('assets/js/app.js')?>"></script>
</body></html>
