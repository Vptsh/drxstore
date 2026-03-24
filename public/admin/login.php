<?php
/**
 * DRXStore - Admin Login
 * Developed by Vineet | psvineet@zohomail.in
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT . '/config/app.php';
startSession();
if (!empty($_SESSION['admin_id'])) { header('Location: index.php?p=dashboard'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $ctx   = 'admin';
    $login = post('login');
    $pw    = post('password');

    if (isLockedOut($ctx)) {
        $error = 'Too many failed attempts. Please wait ' . LOCKOUT_MIN . ' minutes.';
    } elseif (!$login || !$pw) {
        $error = 'Please enter your credentials.';
    } else {
        $user = $db->findOne('users', fn($u) =>
            in_array($u['role'] ?? '', ['admin','staff']) &&
            (strtolower($u['email'] ?? '') === strtolower($login) || strtolower($u['username'] ?? '') === strtolower($login)) &&
            ($u['active'] ?? true)
        );
        if ($user && password_verify($pw, $user['password'])) {
            clearAttempts($ctx);
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $user['id'];
            $_SESSION['admin_name'] = $user['name'];
            $_SESSION['admin_role'] = $user['role'];
            $db->update('users', fn($u) => $u['id'] === $user['id'], ['last_login' => date('Y-m-d H:i:s')]);
            setFlash('success', 'Welcome, ' . $user['name'] . '!');
            header('Location: index.php?p=dashboard'); exit;
        } else {
            recordAttempt($ctx);
            $error = 'Incorrect username/email or password.';
        }
    }
}
$cfg = getSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign In — <?= e($cfg['store_name'] ?? APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?php echo assetUrl('assets/css/app.css'); ?>"><?php echo attrMeta(); ?>
</head>
<body>
<div class="auth-page">
<div class="auth-card">
  <div class="auth-logo">
    <div class="auth-logo-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></div>
    <div class="auth-logo-text">
      <h1><?= e($cfg['store_name'] ?? APP_NAME) ?></h1>
      <p>Pharmacy Management System</p>
    </div>
  </div>
  <h2 class="auth-h2">Admin Sign In</h2>
  <p class="auth-sub">Enter your credentials to access the dashboard.</p>

  <?php if ($error): ?>
  <div class="alert alert-danger"><span class="alert-body"><?= e($error) ?></span></div>
  <?php endif; ?>

  <form method="POST" novalidate>
    <?= csrfField() ?>
    <div class="form-group">
      <label class="form-label">Username or Email</label>
      <input class="form-control" type="text" name="login" value="<?= e(post('login')) ?>" required autofocus autocomplete="username">
    </div>
    <div class="form-group" style="margin-bottom:18px">
      <label class="form-label">Password</label>
      <input class="form-control" type="password" name="password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In</button>
  </form>

  <div class="auth-divider" style="margin-top:18px">other portals</div>
  <div class="flex gap-2 justify-center" style="flex-wrap:wrap">
    <a href="index.php?p=sup_login" class="btn btn-ghost btn-sm">Supplier Login</a>
    <a href="index.php?p=cust_login" class="btn btn-ghost btn-sm">Patient Login</a>
  </div>

  <div class="auth-footer">Developed with &#x1FAC0; by <strong>Vineet</strong></div>
</div>
</div>
<script src="<?php echo assetUrl('assets/js/app.js'); ?>"></script>
</body></html>
