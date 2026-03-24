<?php
/**
 * DRXStore - Secure File Server for prescriptions
 * Serves uploaded files (prescriptions) through PHP, avoiding direct web access 403s
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php';
// Allow staff/admin OR customer who owns the message
startSession();
$isStaff    = !empty($_SESSION['admin_id']);
$isCust     = !empty($_SESSION['cust_id']);
if (!$isStaff && !$isCust) { http_response_code(403); exit('Access denied.'); }

$fname = basename(get('f', ''));
if (!$fname || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $fname)) {
    http_response_code(400); exit('Invalid file name.');
}

$path = DATA_DIR . '/uploads/prescriptions/' . $fname;
if (!file_exists($path)) { http_response_code(404); exit('File not found.'); }

// If customer, verify they own this message
if ($isCust && !$isStaff) {
    $cid = (int)$_SESSION['cust_id'];
    $msg = $db->findOne('patient_messages', fn($m) => ($m['customer_id']??0) === $cid && basename($m['file_path']??'') === $fname);
    if (!$msg) { http_response_code(403); exit('Access denied.'); }
}

$ext  = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
$mime = match($ext) {
    'pdf'        => 'application/pdf',
    'jpg','jpeg' => 'image/jpeg',
    'png'        => 'image/png',
    'heic'       => 'image/heic',
    default      => 'application/octet-stream',
};

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $fname . '"');
header('Cache-Control: private, max-age=3600');
readfile($path);
exit;
