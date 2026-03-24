<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; requireStaff();
$i=getInt('idx');
if(isset($_SESSION['cart'][$i])){array_splice($_SESSION['cart'],$i,1);setFlash('success','Removed.');}
header('Location: index.php?p=sales');exit;
