<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_portal.php'; requireSupplier();
$sid=$_SESSION['supplier_id']??0;
$su=$db->findOne('supplier_users',fn($u)=>$u['id']===$sid);
$sup=$db->findOne('suppliers',fn($s)=>$s['id']==($su['supplier_id']??0));
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $newpw=post('new_password'); $conf=post('confirm');
    $old=post('old_password');
    if(!password_verify($old,$su['password']??''))$errors[]='Current password incorrect.';
    elseif(strlen($newpw)<6)$errors[]='Min 6 characters.';
    elseif($newpw!==$conf)$errors[]='Passwords do not match.';
    if(empty($errors)){$db->update('supplier_users',fn($u)=>$u['id']===$sid,['password'=>password_hash($newpw,PASSWORD_BCRYPT)]);setFlash('success','Password changed successfully.');header('Location: index.php?p=sup_profile');exit;}
}
$navItems=['sup_dash'=>['icon'=>'grid','label'=>'Dashboard'],'sup_orders'=>['icon'=>'orders','label'=>'Purchase Orders'],'sup_profile'=>['icon'=>'user','label'=>'My Profile'],'sup_contact'=>['icon'=>'mail','label'=>'Contact Store']];
portalHeader('My Profile','supplier','sup_profile',$navItems,['name'=>'supplier_company']);
?>
<div class="page-hdr"><div class="page-title">My Profile</div></div>
<?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>
<div class="dash-grid">
  <div class="card"><div class="card-hdr"><div class="card-title">Company Details</div></div><div class="card-body">
    <?php if($sup): $items=['Company'=>$sup['name']??'','Contact'=>$sup['contact']??'','Phone'=>$sup['phone']??'','Email'=>$sup['email']??'','Address'=>$sup['address']??'','GST No'=>$sup['gst_no']??'','DL Number'=>$sup['dl_no']??''];
    foreach($items as $k=>$v):?><div style="display:flex;gap:8px;padding:7px 0;border-bottom:1px solid var(--g3);font-size:.83rem"><span style="width:90px;color:var(--g5);font-weight:600;flex-shrink:0"><?=e($k)?></span><span class="fw-600"><?=e($v)?:'—'?></span></div><?php endforeach;
    else:?><p class="text-muted">No company info found.</p><?php endif;?>
  </div></div>
  <div class="card"><div class="card-hdr"><div class="card-title">Change Password</div></div><div class="card-body">
    <form method="POST"><?=csrfField()?>
      <div class="form-group"><label class="form-label">Current Password</label><input class="form-control" type="password" name="old_password" required></div>
      <div class="form-group"><label class="form-label">New Password</label><input class="form-control" type="password" name="new_password" required placeholder="Min 6 characters"></div>
      <div class="form-group"><label class="form-label">Confirm New Password</label><input class="form-control" type="password" name="confirm" required></div>
      <button type="submit" class="btn btn-primary">Change Password</button>
    </form>
  </div></div>
</div>
<?php portalFooter();?>
