<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
/**
 * DRXStore - Customers | Developed by Vineet | psvineet@zohomail.in
 */
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_admin.php'; requireStaff();
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $act=post('action'); $id=postInt('id');
    $name=post('name'); $phone=post('phone'); $email=post('email'); $addr=post('address'); $dob=post('dob');
    if(!$name)$errors[]='Name required.'; if(!$phone)$errors[]='Phone required.';
    if(empty($errors)){
        $d=['name'=>$name,'phone'=>$phone,'email'=>$email,'address'=>$addr,'dob'=>($dob?:null)];
        if($act==='edit'&&$id){$d['updated_at']=date('Y-m-d H:i:s');$db->update('customers',fn($c)=>$c['id']===$id,$d);setFlash('success','Updated.');}
        else{$d['created_at']=date('Y-m-d H:i:s');$d['active']=1;$d['verified']=0;$db->insert('customers',$d);setFlash('success','Added.');}
        header('Location: index.php?p=customers');exit;
    }
}
if(get('action')==='delete'&&getInt('id')){$db->delete('customers',fn($c)=>$c['id']===getInt('id'));setFlash('success','Deleted.');header('Location: index.php?p=customers');exit;}
if(get('action')==='resend_verify'&&getInt('id')){
    $rc=$db->findOne('customers',fn($c)=>$c['id']===getInt('id'));
    if($rc&&empty($rc['verified'])&&!empty($rc['email'])){
        $tok=bin2hex(random_bytes(32));
        $db->update('customers',fn($c)=>$c['id']===getInt('id'),['verify_token'=>$tok]);
        $store=storeName(); $vurl=siteUrl('verify',['token'=>$tok]);
        $body=mailWrap("Verify Your Email","<p>Dear {$rc['name']},</p><p>An admin has resent your verification link for <strong>{$store}</strong>.</p><p>Please click the button below to verify your email address:</p><a href='{$vurl}' class='btn'>Verify My Email</a><p>Or copy this link:<br><code>{$vurl}</code></p><p>If you did not register, ignore this email.</p>");
        sendMail($rc['email'],"Verify Your Email — {$store}",$body);
        setFlash('success','Verification email resent to '.$rc['email'].'.');
    } else { setFlash('warning','Cannot resend — not found, already verified, or no email.'); }
    header('Location: index.php?p=customers');exit;
}
$custs=$db->table('customers'); usort($custs,fn($a,$b)=>strcasecmp($a['name'],$b['name']));
$q=get('q'); if($q){$ql=strtolower($q);$custs=array_values(array_filter($custs,fn($c)=>(strpos(strtolower($c['name']??''),$ql)!==false)||(strpos($c['phone']??'',$q)!==false)));}
$pag=paginate($custs,max(1,getInt('page',1)),PER_PAGE);
$edit=null; if(get('action')==='edit'&&getInt('id')) $edit=$db->findOne('customers',fn($c)=>$c['id']===getInt('id'));
adminHeader('Customers','customers');
?>
<div class="page-hdr">
  <div><div class="page-title"> Customers / Patients</div><div class="page-sub"><?=$db->count('customers')?> registered</div></div>
  <button class="btn btn-primary" onclick="openModal('cModal')">+ Add Customer</button>
</div>
<div class="card mb-2"><div class="card-body" style="padding:10px 16px"><form method="GET" class="flex gap-2"><input type="hidden" name="p" value="customers"><div class="search-bar"><input type="text" name="q" id="lsearch" value="<?=e($q)?>" placeholder="Search name, phone…"></div><button type="submit" class="btn btn-primary btn-sm">Search</button><?php if($q):?><a href="index.php?p=customers" class="btn btn-ghost btn-sm">Clear</a><?php endif;?></form></div></div>
<div class="card"><div class="card-hdr"><div class="card-title">Customer List</div></div><div class="card-body p0">
  <?php if(empty($pag['items'])):?><div class="empty-state"><p>No customers.</p></div><?php else:?>
  <div class="table-wrap"><table class="tbl" id="lsearchTbl">
    <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>DOB</th><th>Status</th><th>Orders</th><th>Spent</th><th></th></tr></thead>
    <tbody>
    <?php foreach($pag['items'] as $c):
      $orders=$db->count('sales',fn($s)=>($s['customer_id']??0)==$c['id']);
      $spent=(float)$db->sum('sales','grand_total',fn($s)=>($s['customer_id']??0)==$c['id']);
      $verified=!empty($c['verified']);
    ?>
    <tr>
      <td class="text-muted text-sm"><?=e($c['id'])?></td>
      <td class="fw-600"><?=e($c['name'])?></td>
      <td><?=e($c['phone'])?></td>
      <td class="text-sm"><?=e($c['email']??'—')?></td>
      <td class="text-sm text-muted"><?=e($c['dob']??'—')?></td>
      <td><?php if($verified):?><span class="chip chip-green">Verified</span><?php else:?><span class="chip chip-yellow">Unverified</span><?php endif;?></td>
      <td class="tc"><span class="chip chip-blue"><?=$orders?></span></td>
      <td class="fw-600 text-blue"><?=money($spent)?></td>
      <td><div class="flex gap-1">
        <a href="index.php?p=customers&action=edit&id=<?=$c['id']?>" class="btn btn-ghost btn-sm">Edit</a>
        <a href="index.php?p=sales_hist&customer_id=<?=$c['id']?>" class="btn btn-ghost btn-sm">Orders</a>
        <?php if(!$verified&&!empty($c['email'])):?>
        <a href="index.php?p=customers&action=resend_verify&id=<?=$c['id']?>" class="btn btn-sm" style="background:#f59e0b;color:#fff;display:inline-flex;align-items:center;gap:4px" title="Resend verification email"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg> Resend</a>
        <?php endif;?>
        <a href="index.php?p=customers&action=delete&id=<?=$c['id']?>" class="btn btn-danger btn-sm" data-confirm="Delete?">Delete</a>
      </div></td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
  <?=pagerHtml($pag,'index.php?p=customers'.($q?'&q='.urlencode($q):''))?>
  <?php endif;?>
</div></div>
<div class="modal-overlay <?=($edit||!empty($errors))?'open':''?>" id="cModal">
  <div class="modal"><div class="modal-hdr"><span class="modal-title"><?=$edit?'Edit':'Add'?> Customer</span><button class="modal-x" onclick="closeModal('cModal')">x</button></div>
    <form method="POST"><div class="modal-body">
      <?=csrfField()?><input type="hidden" name="action" value="<?=$edit?'edit':'add'?>">
      <?php if($edit):?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif;?>
      <?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Full Name <span class="req">*</span></label><input class="form-control" type="text" name="name" value="<?=e($edit['name']??post('name'))?>" required autofocus></div>
        <div class="form-group"><label class="form-label">Phone <span class="req">*</span></label><input class="form-control" type="tel" name="phone" value="<?=e($edit['phone']??post('phone'))?>" required></div>
      </div>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?=e($edit['email']??post('email'))?>"></div>
        <div class="form-group"><label class="form-label">Date of Birth</label><input class="form-control" type="date" name="dob" value="<?=e($edit['dob']??post('dob'))?>"></div>
      </div>
      <div class="form-group"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"><?=e($edit['address']??post('address'))?></textarea></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('cModal')">Cancel</button><button type="submit" class="btn btn-primary"><?=$edit?'Save':'Add'?></button></div>
    </form></div>
</div>
<?php if($edit):?><script>openModal('cModal');</script><?php endif;?>
<?php adminFooter();?>
