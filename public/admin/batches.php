<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
/**
 * DRXStore - Batches/Stock | Developed by Vineet | psvineet@zohomail.in
 */
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_admin.php'; requireStaff();
$medMap=[]; foreach($db->table('medicines') as $m) $medMap[$m['id']]=$m;
$supMap=[]; foreach($db->table('suppliers') as $s) $supMap[$s['id']]=$s;
$today=date('Y-m-d'); $errors=[];

// AJAX batch list
if (!empty($_GET['ajax']) && $_GET['ajax']==='batches') {
    $mid=getInt('mid');
    $batches=$db->find('batches',fn($b)=>($b['medicine_id']??0)===$mid&&($b['quantity']??0)>0&&($b['expiry_date']??'')>=$today);
    if(empty($batches)){echo '<option value="">No valid batches</option>';exit;}
    echo '<option value="">— Select Batch —</option>';
    foreach($batches as $b) printf('<option value="%d" data-stock="%d" data-mrp="%.2f">Batch %s (Stock:%d Exp:%s)</option>',$b['id'],$b['quantity'],$b['mrp'],e($b['batch_no']),$b['quantity'],$b['expiry_date']??'');
    exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $act=post('action'); $id=postInt('id');
    $mid=postInt('medicine_id'); $bno=post('batch_no'); $exp=post('expiry');
    $mfg=post('mfg_date'); $qty=postInt('qty'); $pp=postFloat('purchase_price'); $mrp=postFloat('mrp'); $sup=postInt('supplier_id')?:null;
    if(!$mid) $errors[]='Select medicine.';
    if(!$bno) $errors[]='Batch number required.';
    if(!$exp) $errors[]='Expiry date required.';
    if($qty<=0) $errors[]='Qty must be > 0.';
    if($mrp<=0) $errors[]='MRP must be > 0.';
    if(empty($errors)){
        $data=['batch_no'=>$bno,'expiry_date'=>$exp,'mfg_date'=>$mfg,'quantity'=>$qty,'purchase_price'=>$pp,'mrp'=>$mrp,'supplier_id'=>$sup,'updated_at'=>date('Y-m-d H:i:s')];
        if($act==='edit'&&$id){$db->update('batches',fn($b)=>$b['id']===$id,$data);setFlash('success','Batch updated.');}
        else{$data['medicine_id']=$mid;$data['created_at']=date('Y-m-d H:i:s');$db->insert('batches',$data);setFlash('success','Batch added.');}
        header('Location: index.php?p=batches');exit;
    }
}
if(get('action')==='delete'&&getInt('id')){$db->delete('batches',fn($b)=>$b['id']===getInt('id'));setFlash('success','Batch deleted.');header('Location: index.php?p=batches');exit;}

$batches=$db->table('batches'); usort($batches,fn($a,$b)=>($b['id']??0)<=>($a['id']??0));
$q=get('q'); $fst=get('status'); $fmid=getInt('filter_med');
if($fmid) $batches=array_values(array_filter($batches,fn($b)=>($b['medicine_id']??0)===$fmid));
if($q){$ql=strtolower($q);$batches=array_values(array_filter($batches,function($b)use($ql,$medMap){return(strpos(strtolower($medMap[$b['medicine_id']??0]['name']??''),$ql)!==false)||(strpos(strtolower($b['batch_no']??''),$ql)!==false);}));}
if($fst==='low')      $batches=array_values(array_filter($batches,fn($b)=>($b['quantity']??0)>0&&($b['quantity']??0)<LOW_QTY));
if($fst==='out')      $batches=array_values(array_filter($batches,fn($b)=>($b['quantity']??0)===0));
if($fst==='expiring') $batches=array_values(array_filter($batches,fn($b)=>($b['expiry_date']??'')>=$today&&($b['expiry_date']??'')<=date('Y-m-d',strtotime('+'.EXPIRY_DAYS.'days'))&&($b['quantity']??0)>0));
if($fst==='expired')  $batches=array_values(array_filter($batches,fn($b)=>($b['expiry_date']??'')<$today&&($b['quantity']??0)>0));
$pag=paginate($batches,max(1,getInt('page',1)),PER_PAGE);
$edit=null; if(get('action')==='edit'&&getInt('id')) $edit=$db->findOne('batches',fn($b)=>$b['id']===getInt('id'));
$medicines=$db->table('medicines'); usort($medicines,fn($a,$b)=>strcasecmp($a['name'],$b['name']));
$suppliers=$db->table('suppliers');
adminHeader('Batches & Stock','batches');
?>
<div class="page-hdr">
  <div><div class="page-title"> Batches & Stock</div><div class="page-sub"><?=$db->count('batches')?> total batches</div></div>
  <button class="btn btn-primary" onclick="openModal('bModal')">+ Add Batch</button>
</div>
<div class="card mb-2"><div class="card-body" style="padding:10px 16px">
  <form method="GET" class="flex gap-2 flex-wrap items-center">
    <input type="hidden" name="p" value="batches">
    <div class="search-bar"><input type="text" name="q" value="<?=e($q)?>" placeholder="Search medicine or batch…"></div>
    <select class="form-control" name="status" style="width:auto;border-radius:6px">
      <option value="">All Status</option>
      <option value="low"      <?=$fst==='low'?'selected':''?>>Low Stock</option>
      <option value="out"      <?=$fst==='out'?'selected':''?>>Out of Stock</option>
      <option value="expiring" <?=$fst==='expiring'?'selected':''?>>Expiring 90d</option>
      <option value="expired"  <?=$fst==='expired'?'selected':''?>>Expired</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="index.php?p=batches" class="btn btn-ghost btn-sm">Reset</a>
  </form>
</div></div>
<div class="card">
  <div class="card-hdr"><div class="card-title">Batch List</div><span class="text-sm text-muted"><?=$pag['total']?> result(s)</span></div>
  <div class="card-body p0">
    <?php if(empty($pag['items'])):?><div class="empty-state"><p>No batches found.</p></div><?php else:?>
    <div class="table-wrap"><table class="tbl">
      <thead><tr><th>#</th><th>Medicine</th><th>Batch No</th><th>Mfg</th><th>Expiry</th><th>Stock</th><th class="tr">Purchase</th><th class="tr">MRP</th><th>Supplier</th><th></th></tr></thead>
      <tbody>
      <?php foreach($pag['items'] as $b):
        $med=$medMap[$b['medicine_id']??0]??null;
        $sup=$b['supplier_id']?($supMap[$b['supplier_id']]??null):null;
      ?>
      <tr>
        <td class="text-muted text-sm"><?=e($b['id'])?></td>
        <td class="fw-600"><?=e($med['name']??'—')?></td>
        <td><code class="mono"><?=e($b['batch_no'])?></code></td>
        <td class="text-sm text-muted"><?=e($b['mfg_date']??'—')?></td>
        <td><?=expiryChip($b['expiry_date']??'')?></td>
        <td><?=stockChip((int)($b['quantity']??0))?></td>
        <td class="tr text-sm"><?=money($b['purchase_price']??0)?></td>
        <td class="tr fw-600"><?=money($b['mrp']??0)?></td>
        <td class="text-sm text-muted"><?=e($sup['name']??'—')?></td>
        <td><div class="flex gap-1">
          <a href="index.php?p=batches&action=edit&id=<?=$b['id']?>" class="btn btn-ghost btn-sm">Edit</a>
          <a href="index.php?p=batches&action=delete&id=<?=$b['id']?>" class="btn btn-danger btn-sm" data-confirm="Delete?">Delete</a>
        </div></td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table></div>
    <?=pagerHtml($pag,'index.php?p=batches&q='.urlencode($q).'&status='.e($fst))?>
    <?php endif;?>
  </div>
</div>
<div class="modal-overlay <?=($edit||!empty($errors))?'open':''?>" id="bModal">
  <div class="modal modal-lg"><div class="modal-hdr"><span class="modal-title"><?=$edit?'Edit':'Add'?> Batch</span><button class="modal-x" onclick="closeModal('bModal')">x</button></div>
    <form method="POST"><div class="modal-body">
      <?=csrfField()?><input type="hidden" name="action" value="<?=$edit?'edit':'add'?>">
      <?php if($edit):?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif;?>
      <?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Medicine <span class="req">*</span></label>
          <select class="form-control" name="medicine_id" required <?=$edit?'disabled':''?> data-searchable data-placeholder="— Search Medicine —">
            <option value="">— Select —</option>
            <?php foreach($medicines as $m):?><option value="<?=$m['id']?>" data-gst="<?=e($m['gst_percent']??12)?>" <?=($edit['medicine_id']??postInt('medicine_id'))==$m['id']?'selected':''?>><?=e($m['name'])?> (GST <?=e($m['gst_percent']??12)?>%)</option><?php endforeach;?>
          </select>
          <?php if($edit):?><input type="hidden" name="medicine_id" value="<?=$edit['medicine_id']?>"><?php endif;?>
        </div>
        <div class="form-group"><label class="form-label">Batch Number <span class="req">*</span></label><input class="form-control" type="text" name="batch_no" value="<?=e($edit['batch_no']??post('batch_no'))?>" required></div>
      </div>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Mfg Date</label><input class="form-control" type="date" name="mfg_date" value="<?=e($edit['mfg_date']??post('mfg_date'))?>"></div>
        <div class="form-group"><label class="form-label">Expiry Date <span class="req">*</span></label><input class="form-control" type="date" name="expiry" value="<?=e($edit['expiry_date']??post('expiry'))?>" required></div>
      </div>
      <div class="form-row-3">
        <div class="form-group"><label class="form-label">Quantity <span class="req">*</span></label><input class="form-control" type="number" name="qty" value="<?=e($edit['quantity']??post('qty'))?>" min="1" required></div>
        <div class="form-group">
          <label class="form-label">Purchase Price — <span style="font-weight:400;color:var(--g5)">GST Inclusive</span></label>
          <input class="form-control" type="number" step="0.01" name="purchase_price" id="inp_pp" value="<?=e($edit['purchase_price']??post('purchase_price'))?>" min="0" oninput="calcGst()">
          <div id="pp_breakdown" class="form-hint" style="color:var(--teal);margin-top:4px"></div>
        </div>
        <div class="form-group">
          <label class="form-label">MRP <span class="req">*</span> — <span style="font-weight:400;color:var(--g5)">GST Inclusive</span></label>
          <input class="form-control" type="number" step="0.01" name="mrp" id="inp_mrp" value="<?=e($edit['mrp']??post('mrp'))?>" min="0.01" required oninput="calcGst()">
          <div id="mrp_breakdown" class="form-hint" style="color:var(--teal);margin-top:4px"></div>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Supplier</label>
        <select class="form-control" name="supplier_id"><option value="">— None —</option>
          <?php foreach($suppliers as $s):?><option value="<?=$s['id']?>" <?=($edit['supplier_id']??postInt('supplier_id'))==$s['id']?'selected':''?>><?=e($s['name'])?></option><?php endforeach;?>
        </select>
      </div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('bModal')">Cancel</button><button type="submit" class="btn btn-primary"><?=$edit?'Save':'Add Batch'?></button></div>
    </form></div>
</div>
<?php if($edit):?><script>openModal('bModal');</script><?php endif;?>
<script>
function calcGst(){
  // Get GST % from selected medicine
  var medSel = document.querySelector('select[name="medicine_id"]');
  var gstPct = 0;
  if(medSel && medSel.options[medSel.selectedIndex]){
    gstPct = parseFloat(medSel.options[medSel.selectedIndex].getAttribute('data-gst')||0)||0;
  }
  if(!gstPct) gstPct = 12; // default if not set

  // MRP breakdown
  var mrp = parseFloat(document.getElementById('inp_mrp')&&document.getElementById('inp_mrp').value)||0;
  if(mrp>0&&gstPct>0){
    var mrpBase = (mrp/(1+gstPct/100)).toFixed(2);
    var mrpGst  = (mrp-mrpBase).toFixed(2);
    var el = document.getElementById('mrp_breakdown');
    if(el) el.innerHTML = 'Base (excl. GST '+gstPct+'%): <strong>'+mrpBase+'</strong> &nbsp;|&nbsp; GST: <strong>'+mrpGst+'</strong>';
  } else {
    var el = document.getElementById('mrp_breakdown');
    if(el) el.innerHTML='';
  }

  // Purchase price breakdown
  var pp = parseFloat(document.getElementById('inp_pp')&&document.getElementById('inp_pp').value)||0;
  if(pp>0&&gstPct>0){
    var ppBase = (pp/(1+gstPct/100)).toFixed(2);
    var ppGst  = (pp-ppBase).toFixed(2);
    var el2 = document.getElementById('pp_breakdown');
    if(el2) el2.innerHTML = 'Base (excl. GST '+gstPct+'%): <strong>'+ppBase+'</strong> &nbsp;|&nbsp; GST: <strong>'+ppGst+'</strong>';
  } else {
    var el2 = document.getElementById('pp_breakdown');
    if(el2) el2.innerHTML='';
  }
}

// Re-run when medicine selection changes (different GST rate)
document.addEventListener('DOMContentLoaded',function(){
  var medSel=document.querySelector('select[name="medicine_id"]');
  if(medSel) medSel.addEventListener('change',calcGst);
  calcGst();
});
</script>
<?php adminFooter();?>
