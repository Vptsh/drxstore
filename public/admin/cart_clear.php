<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; requireStaff();
$_SESSION['cart']=[]; unset($_SESSION['cart_cust']); unset($_SESSION['cart_disc']); unset($_SESSION['cart_disc_name']);
setFlash('success','Cart cleared.'); header('Location: index.php?p=sales');exit;
