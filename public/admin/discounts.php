<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_admin.php'; requireAdmin();
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $act=post('action'); $id=postInt('id');
    $name=post('name'); $type=post('type','percent'); $val=postFloat('value'); $min=postFloat('min_amount');
    if(!$name)$errors[]='Name required.'; if($val<=0)$errors[]='Value must be > 0.'; if($type==='percent'&&$val>100)$errors[]='Max 100%.';
    if(empty($errors)){
        $d=['name'=>$name,'type'=>$type,'value'=>$val,'min_amount'=>$min,'active'=>isset($_POST['active'])?1:0,'updated_at'=>date('Y-m-d H:i:s')];
        if($act==='edit'&&$id){$db->update('discounts',fn($x)=>$x['id']===$id,$d);setFlash('success','Updated.');}
        else{$d['created_at']=date('Y-m-d H:i:s');$db->insert('discounts',$d);setFlash('success','Discount added.');}
        header('Location: index.php?p=discounts'); exit;
    }
}
if(get('action')==='delete'&&getInt('id')){$db->delete('discounts',fn($x)=>$x['id']===getInt('id'));setFlash('success','Deleted.');header('Location: index.php?p=discounts');exit;}
if(get('action')==='toggle'&&getInt('id')){$d=$db->findOne('discounts',fn($x)=>$x['id']===getInt('id'));if($d)$db->update('discounts',fn($x)=>$x['id']===getInt('id'),['active'=>($d['active']?0:1)]);header('Location: index.php?p=discounts');exit;}
$discs=$db->table('discounts');
$edit=null; if(get('action')==='edit'&&getInt('id'))$edit=$db->findOne('discounts',fn($x)=>$x['id']===getInt('id'));
adminHeader('Discounts','discounts');
?>
<div class="page-hdr"><div><div class="page-title"> Discounts & Schemes</div><div class="page-sub"><?=count($discs)?> discount(s)</div></div><button class="btn btn-primary" onclick="openModal('dModal')">+ Add Discount</button></div>
<div class="card"><div class="card-body p0">
  <?php if(empty($discs)):?><div class="empty-state"><p>No discounts configured.</p></div><?php else:?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>Name</th><th>Type</th><th>Value</th><th>Min Order</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach($discs as $d):?>
    <tr>
      <td class="fw-600"><?=e($d['name'])?></td>
      <td><span class="chip <?=($d['type']??'')==='percent'?'chip-blue':'chip-teal'?>"><?=($d['type']??'')==='percent'?'Percentage':'Flat'?></span></td>
      <td class="fw-600"><?=($d['type']??'')==='percent'?e($d['value']).'%':money($d['value'])?></td>
      <td><?=($d['min_amount']??0)>0?money($d['min_amount']):'—'?></td>
      <td><?=($d['active']??0)?'<span class="chip chip-green">Active</span>':'<span class="chip chip-gray">Inactive</span>'?></td>
      <td><div class="flex gap-1">
        <a href="index.php?p=discounts&action=edit&id=<?=$d['id']?>" class="btn btn-ghost btn-sm">Edit</a>
        <a href="index.php?p=discounts&action=toggle&id=<?=$d['id']?>" class="btn btn-ghost btn-sm"><?=($d['active']??0)?'Disable':'Enable'?></a>
        <a href="index.php?p=discounts&action=delete&id=<?=$d['id']?>" class="btn btn-danger btn-sm" data-confirm="Delete '<?=e($d['name'])?>'?">Delete</a>
      </div></td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
  <?php endif;?>
</div></div>
<div class="modal-overlay <?=($edit||!empty($errors))?'open':''?>" id="dModal">
  <div class="modal"><div class="modal-hdr"><span class="modal-title"><?=$edit?'Edit':'Add'?> Discount</span><button class="modal-x" onclick="closeModal('dModal')">x</button></div>
    <form method="POST"><div class="modal-body">
      <?=csrfField()?><input type="hidden" name="action" value="<?=$edit?'edit':'add'?>">
      <?php if($edit):?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif;?>
      <?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>
      <div class="form-group"><label class="form-label">Name <span class="req">*</span></label><input class="form-control" type="text" name="name" value="<?=e($edit['name']??post('name'))?>" required autofocus placeholder="e.g. Senior Citizen 10%"></div>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Type</label><select class="form-control" name="type"><option value="percent" <?=($edit['type']??post('type','percent'))==='percent'?'selected':''?>>Percentage (%)</option><option value="flat" <?=($edit['type']??post('type','percent'))==='flat'?'selected':''?>>Flat Amount (₹)</option></select></div>
        <div class="form-group"><label class="form-label">Value <span class="req">*</span></label><input class="form-control" type="number" step="0.01" name="value" value="<?=e($edit['value']??post('value'))?>" required placeholder="0"></div>
      </div>
      <div class="form-group"><label class="form-label">Min Order Amount (0 = no minimum)</label><input class="form-control" type="number" step="0.01" name="min_amount" value="<?=e($edit['min_amount']??post('min_amount','0'))?>"></div>
      <label style="display:flex;align-items:center;gap:6px;margin-top:6px;cursor:pointer"><input type="checkbox" name="active" value="1" <?=($edit?($edit['active']??1):1)?'checked':''?> style="width:14px;height:14px;accent-color:var(--navy)"><span style="font-size:.85rem;font-weight:500">Active</span></label>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('dModal')">Cancel</button><button type="submit" class="btn btn-primary"><?=$edit?'Save':'Add Discount'?></button></div>
    </form></div>
</div>
<?php if($edit):?><script>openModal('dModal');</script><?php endif;?>
<?php adminFooter();?>
