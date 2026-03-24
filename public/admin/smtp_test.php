<?php
/**
 * SMTP test endpoint
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php';
requireAdmin(); verifyCsrf();
$to=post('test_email');
if(!filter_var($to,FILTER_VALIDATE_EMAIL)){setFlash('danger','Invalid email.');header('Location: index.php?p=settings');exit;}
$cfg=getSettings();
$smtpCfg=['host'=>$cfg['smtp_host']??'','port'=>(int)($cfg['smtp_port']??587),'user'=>$cfg['smtp_user']??'','pass'=>$cfg['smtp_pass']??'','from'=>$cfg['smtp_from']??storeEmail(),'name'=>$cfg['smtp_name']??storeName(),'secure'=>$cfg['smtp_secure']??'tls'];
$result=Mailer::test($to,$smtpCfg);
if($result['success'])setFlash('success',$result['message']);
else setFlash('danger','SMTP test failed: '.$result['message']);
header('Location: index.php?p=settings');exit;
