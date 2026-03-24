<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_portal.php'; requireCustomer();
$cid=$_SESSION['cust_id']??0;
$cust=$db->findOne('customers',fn($c)=>$c['id']===$cid);
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $act=post('action');
    if($act==='profile'){
        $name=post('name'); $phone=post('phone'); $addr=post('address'); $dob=post('dob');
        if(!$name)$errors[]='Name required.'; if(!$phone)$errors[]='Phone required.';
        if(empty($errors)){$db->update('customers',fn($c)=>$c['id']===$cid,['name'=>$name,'phone'=>$phone,'address'=>$addr,'dob'=>($dob?:null),'updated_at'=>date('Y-m-d H:i:s')]);$_SESSION['customer_name']=$name;setFlash('success','Profile updated.');header('Location: index.php?p=cust_profile');exit;}
    }
    if($act==='password'){
        $old=post('old_pw'); $new=post('new_pw'); $conf=post('conf_pw');
        if(!password_verify($old,$cust['password']??''))$errors[]='Current password incorrect.';
        elseif(strlen($new)<6)$errors[]='Min 6 characters.';
        elseif($new!==$conf)$errors[]='Passwords do not match.';
        if(empty($errors)){$db->update('customers',fn($c)=>$c['id']===$cid,['password'=>password_hash($new,PASSWORD_BCRYPT)]);setFlash('success','Password changed.');header('Location: index.php?p=cust_profile');exit;}
    }
}
$navItems=['cust_dash'=>['icon'=>'grid','label'=>'My Dashboard'],'cust_orders'=>['icon'=>'orders','label'=>'My Orders'],'cust_messages'=>['icon'=>'mail','label'=>'Messages'],'cust_return'=>['icon'=>'return','label'=>'Return Request'],'cust_profile'=>['icon'=>'user','label'=>'My Profile']];
portalHeader('My Profile','customer','cust_profile',$navItems,['name'=>'customer_name']);
?>
<div class="page-hdr"><div class="page-title">My Profile</div></div>
<?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>
<div class="dash-grid">
  <div class="card"><div class="card-hdr"><div class="card-title">Personal Information</div></div><div class="card-body">
    <form method="POST"><?=csrfField()?><input type="hidden" name="action" value="profile">
      <div class="form-group"><label class="form-label">Full Name <span class="req">*</span></label><input class="form-control" type="text" name="name" value="<?=e($cust['name']??'')?>" required></div>
      <div class="form-group"><label class="form-label">Email</label><input class="form-control" type="email" value="<?=e($cust['email']??'')?>" disabled><div class="form-hint">Email cannot be changed.</div></div>
      <div class="form-group"><label class="form-label">Phone <span class="req">*</span></label><input class="form-control" type="tel" name="phone" value="<?=e($cust['phone']??'')?>" required></div>
      <div class="form-group"><label class="form-label">Date of Birth</label><input class="form-control" type="date" name="dob" value="<?=e($cust['dob']??'')?>"></div>
      <div class="form-group"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"><?=e($cust['address']??'')?></textarea></div>
      <button type="submit" class="btn btn-primary">Save Profile</button>
    </form>
  </div></div>
  <div class="card"><div class="card-hdr"><div class="card-title">Change Password</div></div><div class="card-body">
    <form method="POST"><?=csrfField()?><input type="hidden" name="action" value="password">
      <div class="form-group"><label class="form-label">Current Password</label><input class="form-control" type="password" name="old_pw" required></div>
      <div class="form-group"><label class="form-label">New Password</label><input class="form-control" type="password" name="new_pw" required placeholder="Min 6 characters"></div>
      <div class="form-group"><label class="form-label">Confirm</label><input class="form-control" type="password" name="conf_pw" required></div>
      <button type="submit" class="btn btn-primary">Change Password</button>
    </form>
    <div style="margin-top:16px;padding:12px;background:var(--g1);border-radius:var(--rl);font-size:.82rem">
      <strong>Forgot password?</strong> Contact: <a href="mailto:<?=e(storeEmail())?>"><?=e(storeEmail())?></a>
    </div>
  </div></div>
</div>
<?php portalFooter();?>
