<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; startSession();

$token = trim(get('token') ?? '');
$success = false;
$custName = '';

if (!$token) {
    setFlash('danger','Invalid verification link.');
    header('Location: index.php?p=cust_login'); exit;
}

// FIX: use empty() not ===false — JSON stores verified as int 0; strict 0===false is FALSE in PHP
$cust = $db->findOne('customers', fn($c) => ($c['verify_token'] ?? '') === $token && empty($c['verified']));

if ($cust) {
    $db->update('customers', fn($c) => $c['id'] === $cust['id'], [
        'verified'    => 1,
        'verify_token'=> '',
        'verified_at' => date('Y-m-d H:i:s'),
    ]);
    // Auto-login the patient immediately
    session_regenerate_id(true);
    $_SESSION['cust_id']       = $cust['id'];
    $_SESSION['customer_name'] = $cust['name'];
    $_SESSION['cust_verified'] = true;
    $custName = $cust['name'];
    $success  = true;
}

$cfg = getSettings(); $store = storeName();
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=$success?'Email Verified':'Verification Failed'?> — <?=e($store)?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?=assetUrl('assets/css/app.css')?>">
<style>
.vpage{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f3f4f6;padding:24px}
.vcard{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);padding:48px 40px;max-width:440px;width:100%;text-align:center}
.vicon{width:72px;height:72px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px}
.vicon.ok{background:#d1fae5}.vicon.err{background:#fee2e2}
.vtitle{font-size:1.5rem;font-weight:700;margin-bottom:10px;color:#111827}
.vsub{color:#6b7280;font-size:.93rem;margin-bottom:28px;line-height:1.6}
.vname{color:#7b2d8b;font-weight:600}
.vbtn{display:inline-block;padding:11px 28px;border-radius:8px;font-weight:600;font-size:.93rem;text-decoration:none;cursor:pointer;border:none;transition:.15s}
.vbtn-p{background:#581c87;color:#fff}
.vbtn-p:hover{background:#6d28d9}
.vbtn-g{background:transparent;color:#6b7280;border:1px solid #e5e7eb;display:block;margin-top:10px}
.vstorename{font-size:.78rem;color:#9ca3af;margin-top:24px}
</style>
</head><body>
<div class="vpage"><div class="vcard">
<?php if ($success): ?>
  <div class="vicon ok">
    <svg width="36" height="36" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
  </div>
  <div class="vtitle">Email Verified!</div>
  <div class="vsub">Welcome, <span class="vname"><?=e($custName)?></span>!<br>Your email has been verified and you are now logged in.</div>
  <a href="index.php?p=cust_dash" class="vbtn vbtn-p">Go to Dashboard &rarr;</a>
<?php else: ?>
  <div class="vicon err">
    <svg width="36" height="36" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
  </div>
  <div class="vtitle">Link Invalid or Expired</div>
  <div class="vsub">This verification link has already been used or has expired.<br>Request a new one below if you still need to verify.</div>
  <a href="index.php?p=resend_verify" class="vbtn vbtn-p">Resend Verification Email</a>
  <a href="index.php?p=cust_login" class="vbtn vbtn-g">Back to Login</a>
<?php endif; ?>
  <div class="vstorename"><?=e($store)?></div>
</div></div>
</body></html>
