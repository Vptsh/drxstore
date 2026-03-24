<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; requireStaff(); verifyCsrf();
$cid=postInt('customer_id');
$_SESSION['cart_cust']=$cid?($db->findOne('customers',fn($c)=>$c['id']===$cid)?$cid:null):null;
header('Location: index.php?p=sales');exit;
