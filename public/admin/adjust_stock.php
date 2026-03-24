<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_admin.php'; requireAdmin();
$medMap=[]; foreach($db->table('medicines') as $m) $medMap[$m['id']]=$m; $errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $bid=postInt('batch_id'); $type=post('type'); $qty=postInt('qty'); $reason=post('reason');
    if(!$bid)$errors[]='Select batch.'; if($qty<1)$errors[]='Qty must be > 0.'; if(!$reason)$errors[]='Reason required.';
    if(!in_array($type,['add','remove']))$errors[]='Invalid type.';
    if(empty($errors)){
        $b=$db->findOne('batches',fn($b)=>(int)$b['id']===(int)$bid);
        if(!$b){$errors[]='Batch not found.';}
        elseif($type==='remove'&&$qty>($b['quantity']??0)){$errors[]='Cannot remove '.$qty.' — only '.($b['quantity']??0).' available.';}
        else{
            $nq=($b['quantity']??0)+($type==='add'?$qty:-$qty);
            $medId_a = (int)($b['medicine_id']??0);
            $totalBefore_a = (int)array_sum(array_column($db->find('batches', fn($bx) => (int)($bx['medicine_id']??0) === $medId_a), 'quantity'));
            $totalAfter_a  = $totalBefore_a + ($type==='add' ? $qty : -$qty);
            $db->update('batches',fn($b)=>(int)$b['id']===(int)$bid,['quantity'=>$nq]);
            $db->insert('stock_adjustments',['batch_id'=>$bid,'medicine_id'=>$medId_a,'type'=>$type,'quantity'=>$qty,'reason'=>$reason,'old_qty'=>$totalBefore_a,'new_qty'=>max(0,$totalAfter_a),'user_id'=>$_SESSION['admin_id']??0,'created_at'=>date('Y-m-d H:i:s')]);
            $med=$medMap[$b['medicine_id']??0]??null;
            setFlash('success',($type==='add'?'Added':'Removed').' '.$qty.' units for '.e($med['name']??'batch').'. New stock: '.$nq);
            header('Location: index.php?p=adjust'); exit;
        }
    }
}
$batches=$db->table('batches'); usort($batches,fn($a,$b)=>strcasecmp($medMap[$a['medicine_id']??0]['name']??'',$medMap[$b['medicine_id']??0]['name']??''));
$adj=$db->table('stock_adjustments'); usort($adj,fn($a,$b)=>($b['id']??0)<=>($a['id']??0)); $adj=array_slice($adj,0,50);
adminHeader('Stock Adjustment','adjust');
?>
<div class="page-hdr"><div><div class="page-title">Stock Adjustment</div></div><a href="index.php?p=batches" class="btn btn-ghost">Back to Batches</a></div>
<div class="dash-grid">
  <div class="card"><div class="card-hdr"><div class="card-title">Adjust Stock</div></div><div class="card-body">
    <?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span><button class="alert-close" onclick="this.parentElement.remove()">x</button></div><?php endforeach;?>
    <form method="POST"><?=csrfField()?>
      <div class="form-group"><label class="form-label">Batch <span class="req">*</span></label>
        <select class="form-control" name="batch_id" required><option value="">— Select —</option>
          <?php foreach($batches as $b): $med=$medMap[$b['medicine_id']??0]??null;?><option value="<?=e($b['id'])?>"><?=e($med['name']??'?')?> — <?=e($b['batch_no'])?> (<?=(int)($b['quantity']??0)?> units)</option><?php endforeach;?>
        </select>
      </div>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Type</label><select class="form-control" name="type"><option value="add">Add Stock</option><option value="remove">Remove Stock</option></select></div>
        <div class="form-group"><label class="form-label">Quantity <span class="req">*</span></label><input class="form-control" type="number" name="qty" min="1" required value="1"></div>
      </div>
      <div class="form-group"><label class="form-label">Reason <span class="req">*</span></label>
        <select class="form-control" name="reason" required><option value="">— Select reason —</option>
          <?php foreach(['New stock received','Return from customer','Damaged goods','Expired disposal','Theft / Loss','Correction / Audit','Transfer','Other'] as $r):?><option value="<?=e($r)?>"><?=e($r)?></option><?php endforeach;?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Apply Adjustment</button>
    </form>
  </div></div>
  <div class="card"><div class="card-hdr"><div class="card-title">Recent Adjustments</div></div><div class="card-body p0">
    <?php if(empty($adj)):?><div class="empty-state" style="padding:24px"><p>No adjustments yet.</p></div><?php else:?>
    <div class="table-wrap"><table class="tbl">
      <thead><tr><th>Date</th><th>Medicine</th><th>Type</th><th class="tc">Qty</th><th>Before</th><th>After</th><th>Reason</th></tr></thead>
      <tbody>
      <?php foreach($adj as $a): $med=$medMap[$a['medicine_id']??0]??null;?>
      <tr>
        <td class="text-sm text-muted"><?=dateF($a['created_at']??'')?></td>
        <td class="fw-600"><?=e($med['name']??'—')?></td>
        <td><?=($a['type']??'')==='add'?'<span class="chip chip-green">Added</span>':'<span class="chip chip-red">Removed</span>'?></td>
        <td class="tc fw-600"><?=e($a['quantity']??0)?></td>
        <td class="text-sm text-muted"><?=e($a['old_qty']??'—')?></td>
        <td class="text-sm fw-600"><?=e($a['new_qty']??'—')?></td>
        <td class="text-sm text-muted"><?=e($a['reason']??'—')?></td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table></div>
    <?php endif;?>
  </div></div>
</div>
<?php adminFooter();?>
