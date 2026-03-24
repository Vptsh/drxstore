<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; startSession();
header('Content-Type: text/html; charset=utf-8');
$mid=getInt('mid'); $today=date('Y-m-d');
if(!$mid){echo '<option value="">Select medicine</option>';exit;}
$batches=$db->find('batches',fn($b)=>(int)($b['medicine_id']??0)===(int)$mid&&($b['quantity']??0)>0&&($b['expiry_date']??'')>=$today);
if(empty($batches)){echo '<option value="">No valid batches</option>';exit;}
echo '<option value="">— Select Batch —</option>';
foreach($batches as $b) printf('<option value="%d" data-stock="%d" data-mrp="%.2f">Batch %s (Stock:%d Exp:%s)</option>',$b['id'],$b['quantity'],$b['mrp'],e($b['batch_no']),$b['quantity'],dateF($b['expiry_date']??''));
