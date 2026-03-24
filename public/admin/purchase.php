<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_admin.php'; requireStaff();
$supMap=[]; foreach($db->table('suppliers') as $s) $supMap[$s['id']]=$s;
$medicines=$db->table('medicines'); usort($medicines,fn($a,$b)=>strcasecmp($a['name'],$b['name']));
$errors=[];

// ── Create new PO ──
if($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='create_po'){
    verifyCsrf(); $sid=postInt('supplier_id'); $notes=post('notes');
    $items=array_filter($_POST['items']??[],fn($i)=>!empty($i['medicine_id'])&&($i['qty']??0)>0);
    if(!$sid) $errors[]='Select supplier.'; if(empty($items)) $errors[]='Add at least one item.';
    if(empty($errors)){
        $total=array_sum(array_map(fn($i)=>(float)($i['qty']??0)*(float)($i['price']??0),$items));
        $poId=$db->insert('purchase_orders',['supplier_id'=>$sid,'po_date'=>date('Y-m-d'),'status'=>'pending','total'=>$total,'notes'=>$notes,'created_at'=>date('Y-m-d H:i:s')]);
        foreach($items as $it) $db->insert('po_items',['po_id'=>$poId,'medicine_id'=>(int)$it['medicine_id'],'quantity'=>(int)$it['qty'],'price'=>(float)($it['price']??0),'mrp'=>(float)($it['mrp']??$it['price']??0)]);
        $sup=$supMap[$sid]??null;
        if($sup&&!empty($sup['email'])){
            $store=storeName(); $storeEmail=storeEmail(); $pn=poNo($poId);
            $body=mailWrap("New Purchase Order — {$pn}","<p>Dear {$sup['name']},</p><p>A new purchase order <strong>{$pn}</strong> (Total: ".money($total).") has been created for you by <strong>{$store}</strong>.</p><p>Please log in to your supplier portal to review and update the order status.</p><p>Notes: ".e($notes)."</p><p>Contact: <a href='mailto:".$storeEmail."'>".$storeEmail."</a></p>");
            sendMail($sup['email'],"New PO {$pn} — {$store}",$body);
        }
        setFlash('success',poNo($poId).' created'.($sup&&!empty($sup['email'])?' and supplier notified':'').'.');
        header('Location: index.php?p=purchase');exit;
    }
}

// ── Receive PO with batch details ──
if($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='receive_po'){
    verifyCsrf();
    $pid=postInt('po_id');
    $po=$db->findOne('purchase_orders',fn($p)=>(int)$p['id']===(int)$pid);
    if(!$po||($po['status']??'')==='received'){
        setFlash('danger','Order already received or not found.');
        header('Location: index.php?p=purchase');exit;
    }
    $poItemsArr=$db->find('po_items',fn($i)=>(int)($i['po_id']??0)===(int)$pid);
    $batchData=$_POST['batch']??[];
    $receiveErrors=[];
    foreach($poItemsArr as $poi){
        $iid = (int)$poi['id'];
        $bd  = $batchData[$iid] ?? $batchData[(string)$iid] ?? [];
        $bno = trim($bd['batch_no'] ?? '');
        $exp = trim($bd['expiry_date'] ?? '');
        $qty = (int)($bd['qty'] ?? $poi['quantity']);
        $mrpCheck = (float)($bd['mrp'] ?? 0);
        $medName = $poi['medicine_id'] ? ($db->findOne('medicines', fn($m) => (int)$m['id'] === (int)$poi['medicine_id'])['name'] ?? 'item') : 'item';
        if (!$bno) $receiveErrors[] = 'Batch number required for ' . $medName . '.';
        if (!$exp) $receiveErrors[] = 'Expiry date required for ' . $medName . '.';
        if ($mrpCheck <= 0) $receiveErrors[] = 'MRP required for ' . $medName . '. Enter the selling price per unit.';
    }
    if(empty($receiveErrors)){
        foreach($poItemsArr as $poi){
            $iid = (int)$poi['id'];
            $bd  = $batchData[$iid] ?? $batchData[(string)$iid] ?? [];
            $medId  = (int)($poi['medicine_id'] ?? 0);
            $med    = $db->findOne('medicines', fn($m) => (int)$m['id'] === $medId);
            $bno    = trim($bd['batch_no'] ?? '');
            if(!$bno) $bno = 'BN-'.strtoupper(substr(md5($medId.date('Ymd')),0,6));
            $exp    = trim($bd['expiry_date'] ?? '');
            $mfg    = trim($bd['mfg_date']    ?? '');
            $mrp    = (float)($bd['mrp']           > 0 ? $bd['mrp']           : ($poi['price'] ?? 0));
            $pp     = (float)($bd['purchase_price'] > 0 ? $bd['purchase_price'] : $mrp);
            $qty    = max(1,(int)($bd['qty'] ?? $poi['quantity']));
            // Use == (loose) for existBatch — MySQL may return string IDs
            $existBatch = $db->findOne('batches', fn($b) => (int)$b['medicine_id'] == $medId && trim($b['batch_no']) === $bno);
            if($existBatch){
                $newQty = (int)($existBatch['quantity'] ?? 0) + $qty;
                // Capture total BEFORE update
                $totalBeforeEx = (int)array_sum(array_column($db->find('batches', fn($b) => (int)($b['medicine_id']??0) === $medId), 'quantity'));
                $totalAfterEx  = $totalBeforeEx + $qty;
                $db->update('batches', fn($b) => (int)$b['id'] === (int)$existBatch['id'], [
                    'quantity'       => $newQty,
                    'mrp'            => $mrp,
                    'purchase_price' => $pp,
                    'expiry_date'    => $exp,
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]);
                $db->insert('stock_adjustments',['batch_id'=>(int)$existBatch['id'],'medicine_id'=>$medId,'type'=>'add','quantity'=>$qty,'reason'=>'PO Received: '.poNo($pid),'old_qty'=>$totalBeforeEx,'new_qty'=>$totalAfterEx,'user_id'=>$_SESSION['admin_id']??0,'created_at'=>date('Y-m-d H:i:s')]);
            } else {
                // Capture total BEFORE insert
                $totalBefore   = (int)array_sum(array_column($db->find('batches', fn($b) => (int)($b['medicine_id']??0) === $medId), 'quantity'));
                $totalAfterNew = $totalBefore + $qty;
                $batId = $db->insert('batches', [
                    'medicine_id'    => $medId,
                    'batch_no'       => $bno,
                    'quantity'       => $qty,
                    'mrp'            => $mrp,
                    'purchase_price' => $pp,
                    'expiry_date'    => $exp,
                    'mfg_date'       => $mfg ?: null,
                    'supplier_id'    => $po['supplier_id'] ?? null,
                    'created_at'     => date('Y-m-d H:i:s'),
                ]);
                $db->insert('stock_adjustments',['batch_id'=>$batId,'medicine_id'=>$medId,'type'=>'add','quantity'=>$qty,'reason'=>'PO Received: '.poNo($pid),'old_qty'=>$totalBefore,'new_qty'=>$totalAfterNew,'user_id'=>$_SESSION['admin_id']??0,'created_at'=>date('Y-m-d H:i:s')]);
            }
            $db->update('po_items', fn($i) => (int)$i['id'] === $iid, ['received_qty'=>$qty,'updated_at'=>date('Y-m-d H:i:s')]);
        }
        $db->update('purchase_orders',fn($p)=>(int)$p['id']===(int)$pid,['status'=>'received','received_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        // Notify supplier
        $sup=$db->findOne('suppliers',fn($s)=>$s['id']==($po['supplier_id']??0));
        $supUser=$db->findOne('supplier_users',fn($u)=>($u['supplier_id']??0)==($po['supplier_id']??0));
        $notifyEmail=$supUser['email']??($sup['email']??'');
        if($notifyEmail){
            $store=storeName(); $pn=poNo($pid);
            $body=mailWrap("PO Received — {$pn}","<p>Dear ".e($sup['name']??'Supplier').",</p><p>Your Purchase Order <strong>{$pn}</strong> has been marked as <strong>Received</strong> by <strong>{$store}</strong>. Stock has been updated.</p><p>Thank you for the timely delivery.</p>");
            sendMail($notifyEmail,"PO Received: {$pn} — {$store}",$body);
        }
        setFlash('success',poNo($pid).' received and stock updated successfully!'.($notifyEmail?' Supplier notified.':''));
        header('Location: index.php?p=purchase');exit;
    }
    // Errors in receive: re-show modal
    $_SESSION['receive_errors']=$receiveErrors;
    $_SESSION['receive_po_id']=$pid;
    header('Location: index.php?p=purchase&receive_modal='.$pid);exit;
}

// ── Cancel PO ──
if($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='cancel_po'){
    verifyCsrf();
    $pid=postInt('po_id');
    $po=$db->findOne('purchase_orders',fn($p)=>(int)$p['id']===(int)$pid);
    if(!$po){setFlash('danger','Order not found.');header('Location: index.php?p=purchase');exit;}
    if(!in_array($po['status']??'',['pending','confirmed','price_updated'])){
        setFlash('danger','Only Pending, Price-Updated, or Confirmed orders can be cancelled.');
        header('Location: index.php?p='.( $_SERVER['HTTP_REFERER']??'purchase' ? 'view_po&id='.$pid : 'purchase'));exit;
    }
    $db->update('purchase_orders',fn($p)=>(int)$p['id']===(int)$pid,['status'=>'cancelled','updated_at'=>date('Y-m-d H:i:s')]);
    // Notify supplier
    $sup=$db->findOne('suppliers',fn($s)=>$s['id']==($po['supplier_id']??0));
    $supUser=$db->findOne('supplier_users',fn($u)=>($u['supplier_id']??0)==($po['supplier_id']??0));
    $notifyEmail=$supUser['email']??($sup['email']??'');
    if($notifyEmail){
        $pn=poNo($pid);$store=storeName();
        $body=mailWrap("PO Cancelled — {$pn}","<p>Dear ".e($sup['name']??'Supplier').",</p><p>Purchase Order <strong>{$pn}</strong> has been <strong>cancelled</strong> by <strong>{$store}</strong>.</p><p>Please disregard any previous shipment instructions for this order.</p>");
        sendMail($notifyEmail,"PO Cancelled: {$pn} — {$store}",$body);
    }
    setFlash('success',poNo($pid).' cancelled'.($notifyEmail?' and supplier notified':'').'.');
    header('Location: index.php?p=purchase');exit;
}

// ── Admin Confirm PO (after supplier price update) ──
if($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='confirm_po'){
    verifyCsrf();
    requireAdmin();
    $pid=postInt('po_id');
    $po=$db->findOne('purchase_orders',fn($p)=>(int)$p['id']===(int)$pid);
    if(!$po||!in_array($po['status']??'',['pending','price_updated'])){
        setFlash('danger','Only Pending or Price-Updated orders can be confirmed.');
        header('Location: index.php?p=purchase');exit;
    }
    $db->update('purchase_orders',fn($p)=>(int)$p['id']===(int)$pid,['status'=>'confirmed','updated_at'=>date('Y-m-d H:i:s')]);
    // Notify supplier
    $sup=$db->findOne('suppliers',fn($s)=>$s['id']==($po['supplier_id']??0));
    $supUser=$db->findOne('supplier_users',fn($u)=>($u['supplier_id']??0)==($po['supplier_id']??0));
    $notifyEmail=$supUser['email']??($sup['email']??'');
    if($notifyEmail){
        $pn=poNo($pid);$store=storeName();
        $body=mailWrap("PO Confirmed — {$pn}","<p>Dear ".e($sup['name']??'Supplier').",</p><p>Purchase Order <strong>{$pn}</strong> has been <strong>confirmed</strong> by <strong>{$store}</strong>. Prices are now locked.</p><p>Please proceed with shipment preparation and mark the order as shipped when dispatched.</p>");
        sendMail($notifyEmail,"PO Confirmed: {$pn} — {$store}",$body);
    }
    setFlash('success',poNo($pid).' confirmed'.($notifyEmail?' and supplier notified':'').'.');
    header('Location: index.php?p=purchase');exit;
}

// ── Reorder PO (create new PO from received PO) ──
if($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='reorder_po'){
    verifyCsrf();
    $pid=postInt('po_id');
    $po=$db->findOne('purchase_orders',fn($p)=>(int)$p['id']===(int)$pid);
    if(!$po){setFlash('danger','Order not found.');header('Location: index.php?p=purchase');exit;}
    $oldItems=$db->find('po_items',fn($i)=>(int)($i['po_id']??0)===(int)$pid);
    if(empty($oldItems)){setFlash('danger','No items to reorder.');header('Location: index.php?p=purchase');exit;}
    $newPoId=$db->insert('purchase_orders',['supplier_id'=>$po['supplier_id'],'po_date'=>date('Y-m-d'),'status'=>'pending','total'=>$po['total']??0,'notes'=>'Reorder of '.poNo($pid),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
    foreach($oldItems as $it)
        $db->insert('po_items',['po_id'=>$newPoId,'medicine_id'=>$it['medicine_id']??0,'quantity'=>$it['quantity']??0,'price'=>$it['price']??0]);
    // Notify supplier
    $sup=$db->findOne('suppliers',fn($s)=>$s['id']==($po['supplier_id']??0));
    if($sup&&!empty($sup['email'])){
        $pn=poNo($newPoId);$store=storeName();$storeEmail=storeEmail();
        $body=mailWrap("New Reorder — {$pn}","<p>Dear ".e($sup['name']).",</p><p>A reorder <strong>{$pn}</strong> (based on ".poNo($pid).") has been created.</p><p>Please log in to review and update the order.</p><p>Contact: <a href='mailto:{$storeEmail}'>{$storeEmail}</a></p>");
        sendMail($sup['email'],"New Reorder {$pn} — {$store}",$body);
    }
    setFlash('success','Reorder '.poNo($newPoId).' created from '.poNo($pid).'.');
    header('Location: index.php?p=view_po&id='.$newPoId);exit;
}


$orders=$db->table('purchase_orders'); usort($orders,fn($a,$b)=>($b['id']??0)<=>($a['id']??0));
$pag=paginate($orders,max(1,getInt('page',1)),PER_PAGE);
$suppliers=$db->table('suppliers');
$medMap2=[]; foreach($medicines as $m) $medMap2[$m['id']]=$m;

// Build medicines list per PO
$poMedsMap=[];
foreach($db->table('po_items') as $poi){
    $pid2=(int)($poi['po_id']??0); $mid=(int)($poi['medicine_id']??0);
    $medName=$medMap2[$mid]['name']??'?';
    $poMedsMap[$pid2][]=$medName.' (×'.(int)($poi['quantity']??0).')';
}

$medOptHtml=''; foreach($medicines as $m) $medOptHtml.='<option value="'.e($m['id']).'">'.e($m['name']).'</option>';

// Receive modal state
$receivePOId=getInt('receive_modal')?:($_SESSION['receive_po_id']??0);
$receiveErrors=$_SESSION['receive_errors']??[];
unset($_SESSION['receive_errors'],$_SESSION['receive_po_id']);
$receivePO=$receivePOId?$db->findOne('purchase_orders',fn($p)=>(int)$p['id']===(int)$receivePOId):null;
$receivePOItems=$receivePO?$db->find('po_items',fn($i)=>(int)($i['po_id']??0)===(int)$receivePOId):[];

$pending=$db->count('purchase_orders',fn($p)=>in_array($p['status']??'',['pending','price_updated']));
$processing=$db->count('purchase_orders',fn($p)=>in_array($p['status']??'',['confirmed','shipped']));
$received=$db->count('purchase_orders',fn($p)=>($p['status']??'')==='received');
$cancelled=$db->count('purchase_orders',fn($p)=>($p['status']??'')==='cancelled');

adminHeader('Purchase Orders','purchase');
?>
<div class="page-hdr">
  <div><div class="page-title">Purchase Orders</div><div class="page-sub"><?=count($orders)?> orders</div></div>
  <button class="btn btn-primary" onclick="openModal('poModal')">+ New PO</button>
</div>
<div class="flex gap-2 flex-wrap mb-2">
  <span class="chip chip-orange"><?=$pending?> Pending</span>
  <span class="chip chip-blue"><?=$processing?> Processing</span>
  <span class="chip chip-green"><?=$received?> Received</span>
  <?php if($cancelled>0):?><span class="chip chip-red"><?=$cancelled?> Cancelled</span><?php endif;?>
</div>
<div class="card"><div class="card-body p0">
  <?php if(empty($pag['items'])):?><div class="empty-state"><p>No orders yet.</p></div><?php else:?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>PO No</th><th>Supplier</th><th>Date</th><th>Medicines Ordered</th><th class="tr">Total</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach($pag['items'] as $po):
      $sup=$supMap[$po['supplier_id']??0]??null;
      $st=$po['status']??'pending';
      $meds=implode(', ',array_slice($poMedsMap[$po['id']]??[],0,3));
      if(count($poMedsMap[$po['id']]??[])>3) $meds.=' +'.( count($poMedsMap[$po['id']])-3).' more';
    ?>
    <tr>
      <td><span class="chip chip-blue"><?=poNo($po['id'])?></span></td>
      <td class="fw-600"><?=e($sup['name']??'—')?></td>
      <td class="text-sm"><?=dateF($po['po_date']??'')?></td>
      <td class="text-sm text-muted" style="max-width:180px"><?=e($meds?:'—')?></td>
      <td class="tr fw-600"><?=money($po['total']??0)?></td>
      <td><?=match($st){'confirmed'=>'<span class="chip chip-blue">Confirmed</span>','shipped'=>'<span class="chip chip-purple">Shipped</span>','received'=>'<span class="chip chip-green">Received</span>','price_updated'=>'<span class="chip chip-orange">Price Updated</span>','cancelled'=>'<span class="chip chip-red">Cancelled</span>',default=>'<span class="chip chip-orange">Pending</span>'}?></td>
      <td><div class="flex gap-1 flex-wrap">
        <a href="index.php?p=view_po&id=<?=$po['id']?>" class="btn btn-ghost btn-sm">View</a>
        <?php if($st==='price_updated'):?>
        <form method="POST" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="confirm_po"><input type="hidden" name="po_id" value="<?=$po['id']?>"><button type="submit" class="btn btn-primary btn-sm" title="Supplier updated prices — review and confirm">Confirm</button></form>
        <?php endif;?>
        <?php if(in_array($st,['pending','confirmed'])):?>
        <button type="button" class="btn btn-success btn-sm" onclick="openReceiveModal(<?=$po['id']?>)">Receive</button>
        <?php endif;?>
        <?php if(in_array($st,['pending','confirmed','price_updated'])):?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Cancel this PO?')"><?=csrfField()?><input type="hidden" name="action" value="cancel_po"><input type="hidden" name="po_id" value="<?=$po['id']?>"><button type="submit" class="btn btn-danger btn-sm">Cancel</button></form>
        <?php endif;?>
        <?php if($st==='received'):?>
        <form method="POST" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="reorder_po"><input type="hidden" name="po_id" value="<?=$po['id']?>"><button type="submit" class="btn btn-ghost btn-sm" title="Create new PO with same items">Reorder</button></form>
        <?php endif;?>
      </div></td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
  <?=pagerHtml($pag,'index.php?p=purchase')?>
  <?php endif;?>
</div></div>

<!-- New PO Modal -->
<div class="modal-overlay <?=!empty($errors)?'open':''?>" id="poModal">
  <div class="modal modal-lg"><div class="modal-hdr"><span class="modal-title">New Purchase Order</span><button class="modal-x" onclick="closeModal('poModal')">&#x2715;</button></div>
    <form method="POST"><div class="modal-body">
      <?=csrfField()?>
      <input type="hidden" name="action" value="create_po">
      <?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>
      <div class="form-group"><label class="form-label">Supplier <span class="req">*</span></label>
        <select class="form-control" name="supplier_id" required data-searchable data-placeholder="— Select Supplier —"><option value="">— Select Supplier —</option>
          <?php foreach($suppliers as $s):?><option value="<?=e($s['id'])?>"><?=e($s['name'])?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-section">Items <span class="text-xs text-muted">(Add as many as needed)</span></div>
      <div id="poRows">
        <div class="po-item-row" style="display:grid;grid-template-columns:2fr 80px 90px 90px auto;gap:8px;margin-bottom:8px;align-items:end">
          <div><label class="form-label text-xs">Medicine</label><select class="form-control po-med-sel" name="items[0][medicine_id]" onchange="fillPoPrice(this,0)" data-searchable data-placeholder="— Search Medicine —"><option value="">—</option><?=$medOptHtml?></select></div>
          <div><label class="form-label text-xs">Qty</label><input class="form-control" type="number" name="items[0][qty]" min="1" placeholder="0"></div>
          <div><label class="form-label text-xs">Purchase Price</label><input class="form-control" type="number" step="0.01" name="items[0][price]" placeholder="₹ Cost" id="po-pp-0"></div>
          <div><label class="form-label text-xs">MRP / Unit</label><input class="form-control" type="number" step="0.01" name="items[0][mrp]" placeholder="₹ MRP" id="po-mrp-0"></div>
          <div style="padding-top:22px"><button type="button" class="btn btn-ghost btn-icon" style="color:var(--red)" onclick="removePORow(this)">&#x2715;</button></div>
        </div>
      </div>
      <button type="button" class="btn btn-ghost btn-sm mt-1" id="addPORowBtn">+ Add Item</button>
      <div class="form-group mt-2"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2" placeholder="Optional…"></textarea></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('poModal')">Cancel</button><button type="submit" class="btn btn-primary">Create PO</button></div>
    </form></div>
</div>

<!-- Receive PO Modal -->
<div class="modal-overlay <?=$receivePO?'open':''?>" id="receiveModal">
  <div class="modal modal-lg"><div class="modal-hdr"><span class="modal-title">Receive PO — Add to Stock</span><button class="modal-x" onclick="closeModal('receiveModal')">&#x2715;</button></div>
  <form method="POST"><div class="modal-body">
    <?=csrfField()?>
    <input type="hidden" name="action" value="receive_po">
    <input type="hidden" name="po_id" id="receivePOId" value="<?=$receivePO?$receivePO['id']:0?>">
    <?php foreach($receiveErrors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>
    <div class="alert alert-info"><span class="alert-body">Enter batch details for each item. Stock will be updated automatically after saving.</span></div>
    <div id="receiveItemsContainer">
      <?php if($receivePO&&!empty($receivePOItems)):
        foreach($receivePOItems as $poi):
          $med2=$medMap2[$poi['medicine_id']??0]??null;
      ?>
      <div style="border:1px solid var(--g3);border-radius:var(--rl);padding:14px;margin-bottom:12px;background:var(--g1)">
        <div class="fw-600" style="margin-bottom:10px;color:var(--navy)"><?=e($med2['name']??'Medicine')?> <span class="text-muted text-xs">— PO Qty: <?=(int)($poi['quantity']??0)?></span></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="form-group" style="margin:0"><label class="form-label text-xs">Batch Number <span class="req">*</span></label>
            <input class="form-control" type="text" name="batch[<?=$poi['id']?>][batch_no]" placeholder="e.g. BA2026001" required></div>
          <div class="form-group" style="margin:0"><label class="form-label text-xs">Qty Received <span class="req">*</span></label>
            <input class="form-control" type="number" name="batch[<?=$poi['id']?>][qty]" value="<?=(int)($poi['quantity']??0)?>" min="1" required></div>
          <div class="form-group" style="margin:0"><label class="form-label text-xs">Expiry Date <span class="req">*</span></label>
            <input class="form-control" type="date" name="batch[<?=$poi['id']?>][expiry_date]" required></div>
          <div class="form-group" style="margin:0"><label class="form-label text-xs">Mfg. Date</label>
            <input class="form-control" type="date" name="batch[<?=$poi['id']?>][mfg_date]"></div>
          <div class="form-group" style="margin:0"><label class="form-label text-xs">Purchase Price / Unit <span class="req">*</span></label>
            <input class="form-control" type="number" step="0.01" min="0" name="batch[<?=$poi['id']?>][purchase_price]" value="<?=number_format((float)($poi['price']??0),2,'.','')?>" placeholder="Cost incl. GST"></div>
          <div class="form-group" style="margin:0"><label class="form-label text-xs">MRP / Unit <span class="req">*</span></label>
            <input class="form-control" type="number" step="0.01" min="0" name="batch[<?=$poi['id']?>][mrp]" value="<?=number_format((float)($poi['mrp']??$poi['price']??0),2,'.','')?>" placeholder="Retail price incl. GST"></div>
        </div>
      </div>
      <?php endforeach; endif;?>
    </div>
  </div>
  <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('receiveModal')">Cancel</button><button type="submit" class="btn btn-success">Receive &amp; Add to Stock</button></div>
  </form></div>
</div>

<?php if(!empty($errors)):?><script>openModal('poModal');</script><?php endif;?>

<script>
// PO row management
var _poIdx = 1;
var _medOpts = <?=json_encode($medOptHtml)?>;
function removePORow(btn) {
    var rows = document.querySelectorAll('.po-item-row');
    if (rows.length > 1) btn.closest('.po-item-row').remove();
}
document.getElementById('addPORowBtn').addEventListener('click', function(){
    var i = _poIdx++;
    var div = document.createElement('div');
    div.className = 'po-item-row';
    div.style.cssText = 'display:grid;grid-template-columns:2fr 80px 90px 90px auto;gap:8px;margin-bottom:8px;align-items:end';
    div.innerHTML =
        '<div><select class="form-control po-med-sel" name="items['+i+'][medicine_id]" onchange="fillPoPrice(this,'+i+')" data-searchable data-placeholder="— Search Medicine —"><option value="">—</option>'+_medOpts+'</select></div>' +
        '<div><input class="form-control" type="number" name="items['+i+'][qty]" min="1" placeholder="Qty"></div>' +
        '<div><input class="form-control" type="number" step="0.01" name="items['+i+'][price]" placeholder="\u20b9 Cost" id="po-pp-'+i+'"></div>' +
        '<div><input class="form-control" type="number" step="0.01" name="items['+i+'][mrp]" placeholder="\u20b9 MRP" id="po-mrp-'+i+'"></div>' +
        '<div style="padding-top:0"><button type="button" class="btn btn-ghost btn-sm" style="color:var(--red)" onclick="removePORow(this)">\u2715</button></div>';
    document.getElementById('poRows').appendChild(div);
    if(window.initSearchableSelects) window.initSearchableSelects();
});

// Receive modal - load PO items via AJAX or pre-fill from page data
var _receiveData = <?=json_encode(array_values(array_map(fn($poi)=>['id'=>(int)$poi['id'],'po_id'=>(int)($poi['po_id']??0),'medicine_id'=>(int)($poi['medicine_id']??0),'quantity'=>(int)($poi['quantity']??0),'price'=>(float)($poi['price']??0),'mrp'=>(float)($poi['mrp']??$poi['price']??0)],$db->table('po_items'))))?>;
var _medMapData  = <?=json_encode(array_column($db->table('medicines'),null,'id'))?>;
// Latest batch MRP and purchase_price per medicine for auto-fill
var _batchMrp = <?=json_encode((function() use($db){
    $out=[];
    $batches=$db->table('batches');
    usort($batches,fn($a,$b)=>($b['id']??0)<=>($a['id']??0));
    foreach($batches as $b){
        $mid=(int)($b['medicine_id']??0);
        if($mid&&!isset($out[$mid])){
            $out[$mid]=['mrp'=>(float)($b['mrp']??0),'pp'=>(float)($b['purchase_price']??0)];
        }
    }
    return $out;
})())?>;

function fillPoPrice(sel, idx) {
    var mid = parseInt(sel.value)||0;
    if(!mid) return;
    var d = _batchMrp[mid] || {};
    var pp = document.getElementById('po-pp-'+idx);
    var mr = document.getElementById('po-mrp-'+idx);
    if(pp && d.pp > 0) pp.value = parseFloat(d.pp).toFixed(2);
    if(mr && d.mrp > 0) mr.value = parseFloat(d.mrp).toFixed(2);
}
var _poMedsMap   = <?=json_encode($poMedsMap)?>;

function openReceiveModal(poId) {
    document.getElementById('receivePOId').value = poId;
    // Build items for this PO
    buildReceiveForm(poId);
    openModal('receiveModal');
}

function buildReceiveForm(poId) {
    var poItems = _receiveData.filter(function(i){ return parseInt(i.po_id) === parseInt(poId); });
    var container = document.getElementById('receiveItemsContainer');
    if(!container) return;
    if(!poItems.length){
        container.innerHTML = '<p class="text-sm text-muted">No items found. Please refresh and try again.</p>';
        return;
    }
    var html = '';
    poItems.forEach(function(poi){
        var med = _medMapData[poi.medicine_id] || {};
        var price = parseFloat(poi.price||0).toFixed(2);
        var mrpVal = parseFloat(poi.mrp||poi.price||0).toFixed(2);
        html +=
            '<div style="border:1px solid var(--g3);border-radius:var(--rl);padding:14px;margin-bottom:12px;background:var(--g1)">' +
            '<div class="fw-600" style="margin-bottom:10px;color:var(--navy)">'+(med.name||'Medicine')+' <span style="color:var(--g5);font-size:.75rem;font-weight:400">— PO Qty: '+parseInt(poi.quantity||0)+'</span></div>' +
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">' +
            '<div class="form-group" style="margin:0"><label class="form-label text-xs">Batch Number <span class="req">*</span></label><input class="form-control" type="text" name="batch['+poi.id+'][batch_no]" placeholder="e.g. BA2026001" required></div>' +
            '<div class="form-group" style="margin:0"><label class="form-label text-xs">Qty Received <span class="req">*</span></label><input class="form-control" type="number" name="batch['+poi.id+'][qty]" value="'+parseInt(poi.quantity||0)+'" min="1" required></div>' +
            '<div class="form-group" style="margin:0"><label class="form-label text-xs">Expiry Date <span class="req">*</span></label><input class="form-control" type="date" name="batch['+poi.id+'][expiry_date]" required></div>' +
            '<div class="form-group" style="margin:0"><label class="form-label text-xs">Mfg. Date</label><input class="form-control" type="date" name="batch['+poi.id+'][mfg_date]"></div>' +
            '<div class="form-group" style="margin:0"><label class="form-label text-xs">Purchase Price / Unit <span class="req">*</span></label><input class="form-control" type="number" step="0.01" min="0" name="batch['+poi.id+'][purchase_price]" value="'+price+'" placeholder="Cost incl. GST"></div>' +
            '<div class="form-group" style="margin:0"><label class="form-label text-xs">MRP / Unit <span class="req">*</span></label><input class="form-control" type="number" step="0.01" min="0" name="batch['+poi.id+'][mrp]" value="'+mrpVal+'" placeholder="Retail price incl. GST"></div>' +
            '</div></div>';
    });
    container.innerHTML = html;
}
</script>
<?php adminFooter();?>
