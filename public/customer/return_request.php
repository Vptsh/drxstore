<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_portal.php'; requireCustomer();
$cid=$_SESSION['cust_id']??0;
$errors=[];
$preselect=getInt('sale_id');
$submitted = get('submitted') === '1';
$myOrders=$db->find('sales',fn($s)=>($s['customer_id']??0)===$cid);
usort($myOrders,fn($a,$b)=>($b['id']??0)<=>($a['id']??0));
$allItems=$db->table('sales_items');
$medMap=[]; foreach($db->table('medicines') as $m) $medMap[$m['id']]=$m;

// Build map of already-returned item IDs (from processed/pending returns)
$myReturns=$db->find('returns',function($r)use($db,$cid){
    $s=$db->findOne('sales',fn($s)=>$s['id']==($r['sale_id']??0));
    return $s&&($s['customer_id']??0)===$cid;
});
usort($myReturns,fn($a,$b)=>($b['id']??0)<=>($a['id']??0));

// Collect all sale_item IDs already returned (from return_items table)
$returnedItemIds=[];
foreach($myReturns as $ret){
    if(in_array($ret['status']??'',['pending','processed'])){
        $ris=$db->find('return_items',fn($ri)=>($ri['return_id']??0)===$ret['id']);
        foreach($ris as $ri) $returnedItemIds[]=(int)($ri['sale_item_id']??0);
    }
}

// Also collect fully returned sales (old logic fallback)
$returnedSaleIds=[];
foreach($myReturns as $ret){
    if(($ret['status']??'')==='processed') $returnedSaleIds[]=(int)($ret['sale_id']??0);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf();
    $saleId=postInt('sale_id'); $reason=post('reason'); $itemIds=array_map('intval',$_POST['item_ids']??[]);
    $details=post('details');
    if(!$saleId)$errors[]='Select an order.';
    if(empty($itemIds))$errors[]='Select items to return.';
    if(!$reason)$errors[]='Select reason.';
    // Validate none of selected items already returned
    $alreadyRet=array_intersect($itemIds,$returnedItemIds);
    if(!empty($alreadyRet))$errors[]='Some selected items have already been returned.';
    if(empty($errors)){
        $sale=$db->findOne('sales',fn($s)=>$s['id']===$saleId&&($s['customer_id']??0)===$cid);
        if(!$sale){$errors[]='Order not found.';}
        else{
            $retItems=$db->find('sales_items',fn($si)=>($si['sale_id']??0)===$saleId&&in_array($si['id']??0,$itemIds));
            $retItemsTotal=array_sum(array_column($retItems,'price'));
            // Apply proportional discount if sale had discount
            $saleRec=$db->findOne('sales',fn($s)=>$s['id']===$saleId&&($s['customer_id']??0)===$cid);
            $saleDiscAmt=(float)($saleRec['discount_amount']??0);
            if($saleDiscAmt>0&&$saleRec){
                $allSaleItems=$db->find('sales_items',fn($si)=>($si['sale_id']??0)===$saleId);
                $saleTotalBeforeDisc=array_sum(array_column($allSaleItems,'price'));
                if($saleTotalBeforeDisc>0){
                    $discRatio=$saleDiscAmt/$saleTotalBeforeDisc;
                    $refund=round($retItemsTotal-round($retItemsTotal*$discRatio,2),2);
                } else { $refund=round($retItemsTotal,2); }
            } else { $refund=round($retItemsTotal,2); }
            $retId=$db->insert('returns',['sale_id'=>$saleId,'reason'=>$reason.' — '.$details,'refund_amount'=>$refund,'status'=>'pending','requested_by'=>'customer','customer_id'=>$cid,'created_at'=>date('Y-m-d H:i:s')]);
            // Insert return_items so we can track which items were returned
            foreach($retItems as $ri){
                $db->insert('return_items',['return_id'=>$retId,'sale_item_id'=>$ri['id'],'quantity'=>$ri['quantity'],'price'=>$ri['price']]);
            }
            $store=storeName(); $storeEmail=storeEmail();
            $cust=$db->findOne('customers',fn($c)=>$c['id']===$cid);
            $body=mailWrap("Customer Return Request — RET-".str_pad($retId,4,'0',STR_PAD_LEFT),"<p>A customer has requested a return.</p><p><strong>Customer:</strong> ".e($cust['name']??'')."<br><strong>Invoice:</strong> ".invNo($saleId)."<br><strong>Reason:</strong> ".e($reason)."<br><strong>Details:</strong> ".e($details)."<br><strong>Refund Estimate:</strong> ".money($refund)."</p><p>Please review and process in the admin panel.</p>");
            sendMail($storeEmail,"Return Request — ".e($cust['name']??'Customer')." | RET-".str_pad($retId,4,'0',STR_PAD_LEFT),$body);
            setFlash('success','Return request submitted! Refund estimate: '.money($refund).'. We will contact you shortly.');
            header('Location: index.php?p=cust_return&submitted=1'); exit;
        }
    }
}

$navItems=['cust_dash'=>['icon'=>'grid','label'=>'My Dashboard'],'cust_orders'=>['icon'=>'orders','label'=>'My Orders'],'cust_messages'=>['icon'=>'mail','label'=>'Messages'],'cust_return'=>['icon'=>'return','label'=>'Return Request'],'cust_profile'=>['icon'=>'user','label'=>'My Profile']];
portalHeader('Return Request','customer','cust_return',$navItems,['name'=>'customer_name']);
?>
<?php if($submitted): ?>
<div class="alert alert-success" style="margin-bottom:16px;border-radius:var(--rl)">
  <span class="alert-body"><strong>Return Request Submitted Successfully!</strong><br>Your return request has been received. Our team will review it and contact you within 24 hours.</span>
</div>
<?php endif; ?>
<div class="page-hdr"><div><div class="page-title"> Return Request</div><div class="page-sub">Request a return or refund</div></div></div>
<?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>

<div class="dash-grid">
<div class="card"><div class="card-hdr"><div class="card-title">New Return Request</div></div><div class="card-body">
  <form method="POST"><?=csrfField()?>
    <div class="form-group"><label class="form-label">Select Order <span class="req">*</span></label>
      <select class="form-control" name="sale_id" id="retSaleId" onchange="loadCustReturnItems(this.value)" required>
        <option value="">— Select Order —</option>
        <?php foreach($myOrders as $s):
          // Check if all items of this order are already returned
          $sItems=$db->find('sales_items',fn($si)=>($si['sale_id']??0)===$s['id']);
          $allItemIds=array_map(fn($si)=>(int)($si['id']??0),$sItems);
          $hasReturnable=!empty(array_diff($allItemIds,$returnedItemIds));
        ?>
        <?php if($hasReturnable):?>
        <option value="<?=$s['id']?>" <?=$preselect==$s['id']?'selected':''?>><?=invNo($s['id'])?> — <?=dateF($s['sale_date']??'')?> — <?=money($s['grand_total']??0)?></option>
        <?php endif;?>
        <?php endforeach;?>
      </select>
    </div>
    <div id="retItems" style="display:<?=$preselect?'block':'none'?>">
      <div class="form-section">Select Items to Return</div>
      <div id="retItemsList" style="background:var(--g1);border-radius:var(--rl);padding:12px;margin-bottom:12px"></div>
    </div>
    <div class="form-group"><label class="form-label">Reason <span class="req">*</span></label>
      <select class="form-control" name="reason" required><option value="">— Select reason —</option>
        <?php foreach(['Wrong medicine','Damaged / Defective product','Expired product','Allergic reaction','Doctor changed prescription','Excess quantity','Other'] as $r):?><option value="<?=e($r)?>"><?=e($r)?></option><?php endforeach;?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Additional Details</label><textarea class="form-control" name="details" rows="3" placeholder="Please describe the issue…"></textarea></div>
    <div class="alert alert-info"><span class="alert-body">After submitting, our team will contact you within 24 hours. Contact: <a href="mailto:<?=e(storeEmail())?>"><?=e(storeEmail())?></a></span></div>
    <button type="submit" class="btn btn-primary">Submit Return Request</button>
  </form>
</div></div>

<div class="card"><div class="card-hdr"><div class="card-title">My Return History</div></div><div class="card-body p0">
  <?php if(empty($myReturns)):?><div class="empty-state" style="padding:24px"><p>No returns yet.</p></div><?php else:?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>Ref</th><th>Date</th><th>Items Returned</th><th class="tr">Refund</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach($myReturns as $r):$status=$r['status']??'pending';
      // Get returned item names for this return
      $retItemNames=[];
      $retItemRows=$db->find('return_items',fn($ri)=>($ri['return_id']??0)===$r['id']);
      foreach($retItemRows as $ri){
          $si=$db->findOne('sales_items',fn($s)=>$s['id']==($ri['sale_item_id']??0));
          if($si){ $med=$medMap[$si['medicine_id']??0]??null; if($med) $retItemNames[]=e($med['name']); }
      }
      if(empty($retItemNames)){
          // Fallback: show reason snippet
          $retItemNames=[e(substr($r['reason']??'—',0,40))];
      }
    ?>
    <tr>
      <td><span class="chip chip-purple">RET-<?=str_pad($r['id'],4,'0',STR_PAD_LEFT)?></span></td>
      <td class="text-sm"><?=dateF(substr($r['created_at']??'',0,10))?></td>
      <td class="text-sm"><?=implode(', ',$retItemNames)?></td>
      <td class="tr fw-600"><?=money($r['refund_amount']??0)?></td>
      <td><span class="chip <?=$status==='processed'?'chip-green':($status==='rejected'?'chip-red':'chip-orange')?>"><?=ucfirst($status)?></span></td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
  <?php endif;?>
</div></div>
</div>

<script>
var custItems=<?=json_encode($allItems)?>;
var custMeds=<?=json_encode(array_column($db->table('medicines'),null,'id'))?>;
var returnedItemIds=<?=json_encode(array_values($returnedItemIds))?>;
function loadCustReturnItems(sid){
  var wrap=document.getElementById('retItems');
  var list=document.getElementById('retItemsList');
  if(!sid){wrap.style.display='none';return;}
  var items=custItems.filter(function(i){return String(i.sale_id)===String(sid);});
  // Filter out already returned items
  var returnable=items.filter(function(i){return returnedItemIds.indexOf(parseInt(i.id))<0;});
  if(!returnable.length){
    list.innerHTML='<p class="text-sm" style="color:var(--green);font-weight:600">All items from this order have already been returned.</p>';
    wrap.style.display='block';return;
  }
  list.innerHTML=returnable.map(function(i){
    var m=custMeds[i.medicine_id]||{};
    return '<label style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid var(--g3);cursor:pointer"><input type="checkbox" name="item_ids[]" value="'+i.id+'" style="width:14px;height:14px;accent-color:#7b2d8b"><span><strong>'+(m.name||'Unknown')+'</strong> &mdash; Qty: '+i.quantity+' &mdash; <span style="color:#7b2d8b;font-weight:600">'+parseFloat(i.price||0).toFixed(2)+'</span></span></label>';
  }).join('');
  wrap.style.display='block';
}
<?php if($preselect):?>document.addEventListener('DOMContentLoaded',function(){loadCustReturnItems('<?=$preselect?>');});<?php endif;?>
</script>
<?php portalFooter();?>
