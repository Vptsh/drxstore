<?php
/**
 * DRXStore - Returns Management
 * Admin processes returns; customer requests are auto-populated
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php';
require_once ROOT.'/views/layout_admin.php';
requireStaff();

$errors = [];

// ── Reject a pending customer return request ──
if (getInt('reject_pending')) {
    $rid = getInt('reject_pending');
    $ret = $db->findOne('returns', fn($r) => (int)$r['id'] === (int)$rid && ($r['status']??'') === 'pending');
    if ($ret) {
        $db->update('returns', fn($r) => (int)$r['id'] === (int)$rid, [
            'status'     => 'rejected',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        setFlash('success', 'Return request rejected.');
    }
    header('Location: index.php?p=returns'); exit;
}

// ── Process a return (new manual OR from pending customer request) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $saleId   = postInt('sale_id');
    $reason   = post('reason');
    $itemIds  = array_map('intval', $_POST['item_ids'] ?? []);
    $restock  = post('restock', 'yes');
    $existRid = postInt('existing_return_id'); // if processing a pending customer request

    if (!$saleId)        $errors[] = 'Select a sale invoice.';
    if (empty($itemIds)) $errors[] = 'Select at least one item to return.';
    if (!$reason)        $errors[] = 'Provide a reason.';

    // ── Per-item already-returned guard ──
    // Run before any DB writes. Uses find() to check ALL return_items, not just the first.
    if (empty($errors)) {
        foreach ($itemIds as $_checkId) {
            $_checkId = (int)$_checkId;
            // Get ALL return_items for this sale_item_id (excluding current pending if re-processing)
            $_matched = $db->find('return_items', fn($ri) =>
                (int)($ri['sale_item_id']??0) === $_checkId &&
                (!$existRid || (int)($ri['return_id']??0) !== (int)$existRid)
            );
            foreach ($_matched as $_mri) {
                // Block only if parent return is 'processed' (not pending/rejected)
                $_parent = $db->findOne('returns', fn($r) =>
                    (int)$r['id'] === (int)($_mri['return_id']??0) &&
                    ($r['status']??'') === 'processed'
                );
                if ($_parent) {
                    $_si  = $db->findOne('sales_items', fn($s) => (int)$s['id'] === $_checkId);
                    $_med = $_si ? $db->findOne('medicines', fn($m) => (int)$m['id'] === (int)($_si['medicine_id']??0)) : null;
                    $errors[] = ($_med['name'] ?? 'Item #'.$_checkId) . ' has already been returned and cannot be returned again.';
                    break;
                }
            }
        }
    }


    if (empty($errors)) {
        $retItems = $db->find('sales_items',
            fn($si) => (int)($si['sale_id']??0) === (int)$saleId && in_array((int)($si['id']??0), $itemIds));
        // Refund = what customer actually paid (item prices minus proportional discount)
        $retItemsTotal = round(array_sum(array_column($retItems, 'price')), 2);
        if ($retItemsTotal <= 0 && !empty($retItems)) {
            $retItemsTotal = round(array_sum(array_map(fn($si) => (float)($si['mrp']??0) * (int)($si['quantity']??0), $retItems)), 2);
        }
        // Apply proportional discount: if sale had discount, reduce refund proportionally
        $saleRec = $db->findOne('sales', fn($s) => (int)$s['id'] === (int)$saleId);
        $saleDiscAmt = (float)($saleRec['discount_amount'] ?? 0);
        if ($saleDiscAmt > 0 && $saleRec) {
            $allSaleItems = $db->find('sales_items', fn($si) => (int)($si['sale_id']??0) === (int)$saleId);
            $saleTotalBeforeDisc = round(array_sum(array_column($allSaleItems, 'price')), 2);
            if ($saleTotalBeforeDisc > 0) {
                $discRatio = $saleDiscAmt / $saleTotalBeforeDisc;
                $proportionalDisc = round($retItemsTotal * $discRatio, 2);
                $refund = round($retItemsTotal - $proportionalDisc, 2);
            } else {
                $refund = $retItemsTotal;
            }
        } else {
            $refund = $retItemsTotal;
        }

        // Check if any selected item has already been returned (applies to both new and pending)
        $alreadyReturnedItems = [];
        foreach ($itemIds as $checkItemId) {
            // Look for this sale_item_id in any processed return_item
            $alreadyReturned = $db->findOne('return_items', function($ri) use ($checkItemId, $existRid) {
                if ((int)($ri['sale_item_id']??0) !== (int)$checkItemId) return false;
                // Skip items from the current pending return being re-processed
                if ($existRid && (int)($ri['return_id']??0) === (int)$existRid) return false;
                // Check the parent return is processed (not just pending)
                return true; // will verify return status below
            });
            if ($alreadyReturned) {
                // Verify the parent return is actually processed
                $parentReturn = $db->findOne('returns', fn($r) => (int)$r['id'] === (int)($alreadyReturned['return_id']??0) && ($r['status']??'') === 'processed');
                if ($parentReturn) {
                    $si = $db->findOne('sales_items', fn($si) => (int)$si['id'] === (int)$checkItemId);
                    $med = $si ? ($db->findOne('medicines', fn($m) => (int)$m['id'] === (int)($si['medicine_id']??0))) : null;
                    $alreadyReturnedItems[] = $med['name'] ?? 'Item #'.$checkItemId;
                }
            }
        }
        if (!empty($alreadyReturnedItems)) {
            $errors[] = 'Already returned: ' . implode(', ', $alreadyReturnedItems) . '. Cannot return an item twice.';
        }

        if ($existRid && empty($errors)) {
            // Update existing pending return to processed
            $db->update('returns', fn($r) => (int)$r['id'] === (int)$existRid, [
                'status'         => 'processed',
                'reason'         => $reason,
                'refund_amount'  => $refund,
                'stock_adjusted' => ($restock === 'yes') ? 1 : 0,
                'created_by'     => $_SESSION['admin_id'] ?? 0,
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
            $retId = $existRid;
            // Remove old return_items and re-insert with selected items
            $db->delete('return_items', fn($ri) => (int)($ri['return_id']??0) === (int)$retId);
        } elseif (!$existRid && empty($errors)) {
            // Check for duplicate return on this entire sale (belt-and-suspenders)
            $existing = $db->findOne('returns',
                fn($r) => (int)($r['sale_id']??0) === (int)$saleId && ($r['status']??'') === 'processed');
            if ($existing && count($itemIds) === $db->count('sales_items', fn($si) => (int)($si['sale_id']??0) === (int)$saleId)) {
                $errors[] = 'All items from invoice ' . invNo($saleId) . ' have already been returned.';
            }
        }

        if (empty($errors)) {
            if (!$existRid) {
                $retId = $db->insert('returns', [
                    'sale_id'        => $saleId,
                    'reason'         => $reason,
                    'refund_amount'  => $refund,
                    'status'         => 'processed',
                    'stock_adjusted' => ($restock === 'yes') ? 1 : 0,
                    'created_by'     => $_SESSION['admin_id'] ?? 0,
                    'created_at'     => date('Y-m-d H:i:s'),
                ]);
            }

            foreach ($retItems as $ri) {
                $db->insert('return_items', [
                    'return_id'    => $retId,
                    'sale_item_id' => $ri['id'],
                    'quantity'     => $ri['quantity'],
                    'price'        => $ri['price'],
                ]);
                // Only adjust stock if item is not damaged/expired
                if ($restock === 'yes') {
                    $batch  = $db->findOne('batches', fn($b) => (int)$b['id'] === (int)($ri['batch_id'] ?? 0));
                    if ($batch) {
                        $medId_r  = (int)($ri['medicine_id'] ?? 0);
                        $retQty   = (int)($ri['quantity'] ?? 0);
                        // Capture total medicine stock BEFORE update
                        $totalBefore_r = (int)array_sum(array_column(
                            $db->find('batches', fn($bx) => (int)($bx['medicine_id']??0) === $medId_r), 'quantity'
                        ));
                        $totalAfter_r  = $totalBefore_r + $retQty;
                        $newQty = (int)($batch['quantity'] ?? 0) + $retQty;
                        $db->update('batches', fn($b) => (int)$b['id'] === (int)$ri['batch_id'], [
                            'quantity'   => $newQty,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                        $db->insert('stock_adjustments', [
                            'batch_id'    => (int)($ri['batch_id'] ?? 0),
                            'medicine_id' => $medId_r,
                            'type'        => 'add',
                            'quantity'    => $retQty,
                            'reason'      => 'Return RET-' . str_pad($retId,4,'0',STR_PAD_LEFT) . ': ' . $reason,
                            'old_qty'     => $totalBefore_r,
                            'new_qty'     => $totalAfter_r,
                            'user_id'     => $_SESSION['admin_id'] ?? 0,
                            'created_at'  => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }

            $stockNote = $restock === 'yes' ? ' Stock restored.' : ' Stock NOT restored (damaged/expired/written off).';
            setFlash('success', 'Return processed. Refund: ₹' . number_format((float)$refund, 2) . $stockNote);
            header('Location: index.php?p=returns'); exit;
        }
    }
}

// ── Data ──
$allReturns = $db->table('returns');
usort($allReturns, fn($a,$b) => ($b['id']??0) <=> ($a['id']??0));
$pendingRet = array_values(array_filter($allReturns, fn($r) => ($r['status']??'') === 'pending'));
$processed  = array_values(array_filter($allReturns, fn($r) => ($r['status']??'') !== 'pending'));

$custMap = []; foreach($db->table('customers') as $cu) $custMap[$cu['id']] = $cu;
$allSales     = $db->table('sales');
usort($allSales, fn($a,$b) => ($b['id']??0) <=> ($a['id']??0));
$allSalesItems = $db->table('sales_items');
$medMap = []; foreach($db->table('medicines') as $m) $medMap[$m['id']] = $m;
$batMap = []; foreach($db->table('batches')   as $b) $batMap[$b['id']] = $b;

// Pre-select pending return if ?process_pending=ID
$preFill  = null;
$preItems = [];
$customerRequestedIds = []; // sale_item IDs the customer specifically requested
if (getInt('process_pending')) {
    $preFill = $db->findOne('returns', fn($r) => (int)$r['id'] === getInt('process_pending'));
    if ($preFill) {
        // Load which items customer specifically requested from return_items table
        $custRetItems = $db->find('return_items', fn($ri) => (int)($ri['return_id']??0) === (int)($preFill['id']??0));
        $customerRequestedIds = array_map('intval', array_column($custRetItems, 'sale_item_id'));
        // Load all sale items for this sale
        $preItems = $db->find('sales_items', fn($si) => (int)($si['sale_id']??0) === (int)($preFill['sale_id']??0));
        // Mark each item whether customer requested it
        foreach ($preItems as &$pi) {
            $pi['_checked'] = empty($customerRequestedIds) || in_array((int)($pi['id']??0), $customerRequestedIds);
        }
        unset($pi);
    }
}

adminHeader('Returns', 'returns');
?>
<div class="page-hdr">
  <div><div class="page-title">Returns</div>
    <div class="page-sub"><?=count($processed)?> processed</div>
  </div>
  <button class="btn btn-primary" onclick="openModal('retModal')">+ Process Return</button>
</div>

<!-- Pending customer requests -->
<?php if (!empty($pendingRet)): ?>
<div class="alert alert-warning" style="margin-bottom:12px">
  <span class="alert-body"><strong><?=count($pendingRet)?> pending</strong> customer return request(s) awaiting review.</span>
</div>
<div class="card mb-2">
  <div class="card-hdr"><div class="card-title">Pending Customer Requests</div></div>
  <div class="card-body p0">
    <div class="table-wrap"><table class="tbl">
      <thead><tr><th>Return ID</th><th>Customer</th><th>Invoice</th><th>Date</th><th>Reason</th><th class="tr">Est. Refund</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($pendingRet as $r):
        $sale = $db->findOne('sales', fn($s) => (int)$s['id'] === (int)($r['sale_id']??0));
        $cu   = $custMap[$r['customer_id']??0] ?? ($sale ? ($custMap[$s['customer_id']??0]??null) : null);
      ?>
      <tr style="background:#fffbeb">
        <td><span class="chip chip-orange">RET-<?=str_pad($r['id'],4,'0',STR_PAD_LEFT)?></span></td>
        <td class="fw-600"><?=e($cu['name']??'Walk-in')?></td>
        <td><a href="index.php?p=view_inv&sale_id=<?=$r['sale_id']?>"><?=invNo($r['sale_id']??0)?></a></td>
        <td class="text-sm"><?=dateTimeF($r['created_at']??'')?></td>
        <td class="text-sm"><?=e(substr($r['reason']??'',0,50))?></td>
        <td class="tr fw-600"><?=money($r['refund_amount']??0)?></td>
        <td>
          <div class="flex gap-1">
            <a href="index.php?p=returns&process_pending=<?=$r['id']?>" class="btn btn-success btn-sm"
               onclick="openModal('retModal');return false;"
               data-retid="<?=$r['id']?>" data-saleid="<?=$r['sale_id']?>" data-reason="<?=e($r['reason']??'')?>">
              Process
            </a>
            <a href="index.php?p=returns&reject_pending=<?=$r['id']?>" class="btn btn-ghost btn-sm"
               style="color:var(--red)" data-confirm="Reject this return request?">Reject</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>

<!-- Processed returns table -->
<div class="card"><div class="card-body p0">
  <?php if (empty($processed)): ?>
  <div class="empty-state"><p>No returns processed yet.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>Return ID</th><th>Invoice</th><th>Date</th><th>Items Returned</th><th>Reason</th><th class="tr">Refund</th><th>Stock</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($processed as $r):
      // Get returned medicine names from return_items
      $retMedNames = [];
      $retItemRows = $db->find('return_items', fn($ri) => (int)($ri['return_id']??0) === (int)$r['id']);
      foreach ($retItemRows as $ri2) {
          $si2 = $db->findOne('sales_items', fn($s) => (int)$s['id'] === (int)($ri2['sale_item_id']??0));
          if ($si2) { $m2 = $medMap[$si2['medicine_id']??0] ?? null; if ($m2) $retMedNames[] = e($m2['name']); }
      }
      if (empty($retMedNames)) $retMedNames = [e(substr($r['reason']??'—', 0, 35))];
    ?>
    <tr>
      <td><span class="chip chip-purple">RET-<?=str_pad($r['id'],4,'0',STR_PAD_LEFT)?></span></td>
      <td><a href="index.php?p=view_inv&sale_id=<?=$r['sale_id']?>"><?=invNo($r['sale_id']??0)?></a></td>
      <td class="text-sm"><?=dateTimeF($r['updated_at']??$r['created_at']??'')?></td>
      <td class="text-sm fw-600"><?=implode(', ', $retMedNames)?></td>
      <td class="text-sm text-muted"><?=e(substr($r['reason']??'—', 0, 40))?></td>
      <td class="tr fw-600"><?=money($r['refund_amount']??0)?></td>
      <td><?php if(($r['status']??'')==='rejected'):?><span class="chip chip-gray">&mdash;</span><?php elseif(($r['stock_adjusted']??0)):?><span class="chip chip-green">Restocked</span><?php else:?><span class="chip chip-red">Discarded</span><?php endif;?></td>
      <td><span class="chip <?=($r['status']??'')==='rejected'?'chip-red':'chip-green'?>"><?=ucfirst($r['status']??'processed')?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div></div>

<!-- Return Modal -->
<div class="modal-overlay <?=(!empty($errors)||$preFill)?'open':''?>" id="retModal">
  <div class="modal modal-lg">
    <div class="modal-hdr">
      <span class="modal-title"><?=$preFill?'Process Customer Return Request':'Process Return'?></span>
      <button class="modal-x" onclick="closeModal('retModal')">&#x2715;</button>
    </div>
    <form method="POST" id="retForm"><div class="modal-body">
      <?=csrfField()?>
      <input type="hidden" name="existing_return_id" value="<?=$preFill?$preFill['id']:''?>">

      <?php foreach ($errors as $er): ?>
      <div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div>
      <?php endforeach; ?>

      <?php if ($preFill): ?>
      <div class="alert alert-info"><span class="alert-body">
        Processing customer return request for <strong><?=invNo($preFill['sale_id']??0)?></strong>.
        Customer reason: <em><?=e($preFill['reason']??'')?></em>
      </span></div>
      <?php endif; ?>

      <!-- Sale selector (pre-populated if from customer request) -->
      <div class="form-group">
        <label class="form-label">Sale Invoice <span class="req">*</span></label>
        <?php if ($preFill): ?>
        <input class="form-control" type="text" value="<?=invNo($preFill['sale_id']??0)?> — <?=dateF($db->findOne('sales',fn($s)=>$s['id']==($preFill['sale_id']??0))['sale_date']??'')?>" readonly style="background:var(--g1)">
        <input type="hidden" name="sale_id" value="<?=$preFill['sale_id']?>">
        <?php else: ?>
        <select class="form-control" name="sale_id" id="retSaleId" onchange="loadReturnItems(this.value)" required data-searchable data-placeholder="— Search Invoice No. or Customer —">
          <option value="">— Select Sale —</option>
          <?php foreach (array_slice($allSales, 0, 100) as $s): ?>
          <option value="<?=$s['id']?>"><?=invNo($s['id'])?> &mdash; <?=dateF($s['sale_date']??'')?> &mdash; <?=money($s['grand_total']??0)?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
      </div>

      <!-- Items (pre-loaded if from customer request) -->
      <div id="retItems" style="display:<?=$preFill?'block':'none'?>">
        <div class="form-section">Select Items to Return</div>
        <div id="retItemsList" style="background:var(--g1);border-radius:var(--rl);padding:12px;border:1px solid var(--g3)">
          <?php if ($preFill && !empty($preItems)): foreach ($preItems as $pi):
            $m = $medMap[$pi['medicine_id']??0] ?? null;
            $b = $batMap[$pi['batch_id']??0] ?? null;
            $isChecked = $pi['_checked'] ?? true;
          ?>
          <?php $isAlreadyReturned = $pi['_already_returned'] ?? false; ?>
          <label style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--g3);cursor:<?=$isAlreadyReturned?'not-allowed':'pointer'?>;<?=$isChecked&&!$isAlreadyReturned?'':'opacity:.55'?>">
            <input type="checkbox" name="item_ids[]" value="<?=$pi['id']?>" <?=($isChecked&&!$isAlreadyReturned)?'checked':''?> <?=$isAlreadyReturned?'disabled':''?> style="width:16px;height:16px;accent-color:var(--navy)">
            <span class="fw-600"><?=e($m['name']??'Unknown')?></span>
            <?php if($b):?><span class="text-sm text-muted">Batch: <?=e($b['batch_no']??'')?></span><?php endif;?>
            <span class="text-muted text-sm">Qty: <?=$pi['quantity']?></span>
            <span class="fw-600" style="margin-left:auto;color:var(--navy)"><?=money($pi['price']??0)?></span>
            <?php if($isAlreadyReturned):?><span class="chip chip-red" style="font-size:.65rem">Already returned</span>
            <?php elseif(!$isChecked):?><span class="chip chip-gray" style="font-size:.65rem">Not requested</span><?php endif;?>
          </label>
          <?php endforeach; else: ?>
          <p class="text-sm text-muted">No items found.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Reason -->
      <div class="form-group">
        <label class="form-label">Return Reason <span class="req">*</span></label>
        <select class="form-control" name="reason" id="retReason" onchange="updateRestockSuggestion()" required>
          <option value="">— Select Reason —</option>
          <?php $reasons=['Wrong medicine dispensed','Damaged packaging','Expired product','Allergic reaction','Doctor changed prescription','Excess quantity purchased','Quality issue','Other'];
          foreach ($reasons as $rv): ?>
          <option value="<?=e($rv)?>" <?=$preFill&&($preFill['reason']??'')===$rv?'selected':''?>><?=e($rv)?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($preFill && !empty($preFill['reason'])): ?>
        <div class="form-hint">Customer stated: <em><?=e($preFill['reason']??'')?></em></div>
        <?php endif; ?>
      </div>

      <!-- Stock decision -->
      <div class="card" style="border:2px solid var(--navy-dim);margin-bottom:4px">
        <div class="card-hdr" style="background:var(--navy-lt)"><div class="card-title">Stock Adjustment Decision</div></div>
        <div class="card-body">
          <div style="display:flex;gap:14px;flex-wrap:wrap">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 16px;border-radius:var(--r);border:2px solid var(--g3);flex:1" id="restockYesLabel">
              <input type="radio" name="restock" value="yes" checked onchange="highlightRestock()" style="accent-color:var(--green)">
              <span><strong style="color:var(--green)">Restore to stock</strong><br><span class="text-xs text-muted">Item is in good condition</span></span>
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 16px;border-radius:var(--r);border:2px solid var(--g3);flex:1" id="restockNoLabel">
              <input type="radio" name="restock" value="no" onchange="highlightRestock()" style="accent-color:var(--red)">
              <span><strong style="color:var(--red)">Discard (write off)</strong><br><span class="text-xs text-muted">Damaged, expired or unusable</span></span>
            </label>
          </div>
          <div id="restockWarning" style="display:none;margin-top:10px" class="alert alert-warning">
            <span class="alert-body">Stock will <strong>NOT</strong> be restored. Item will be written off.</span>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('retModal')">Cancel</button>
      <button type="button" class="btn btn-danger" onclick="confirmReturn()">Process Return</button>
    </div>
    </form>
  </div>
</div>

<!-- Confirmation overlay -->
<div class="modal-overlay" id="retConfirm">
  <div class="modal"><div class="modal-hdr"><span class="modal-title">Confirm Return</span><button class="modal-x" onclick="closeModal('retConfirm')">&#x2715;</button></div>
    <div class="modal-body">
      <p class="fw-600" style="margin-bottom:8px">Please confirm:</p>
      <div id="confirmDetails" style="background:var(--g1);border-radius:var(--r);padding:12px;font-size:.85rem"></div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('retConfirm')">Cancel</button>
      <button type="button" class="btn btn-danger" onclick="document.getElementById('retForm').submit()">Confirm &amp; Process</button>
    </div>
  </div>
</div>

<?php if (!empty($errors) || $preFill): ?><script>openModal('retModal');</script><?php endif; ?>

<?php
// Build map of already-returned sale_item_ids per sale for JS
$returnedItemsMap = [];
foreach ($db->table('return_items') as $ri2) {
    $parentRet = $db->findOne('returns', fn($r) => (int)$r['id'] === (int)($ri2['return_id']??0) && ($r['status']??'') === 'processed');
    if ($parentRet) {
        $sid = (int)($parentRet['sale_id']??0);
        if (!isset($returnedItemsMap[$sid])) $returnedItemsMap[$sid] = [];
        $returnedItemsMap[$sid][] = (int)($ri2['sale_item_id']??0);
    }
}
?>

<script>
var allItems = <?=json_encode($allSalesItems)?>;
var returnedItemsMap = <?=json_encode($returnedItemsMap??[])?>;
var medMap   = <?=json_encode(array_column($db->table('medicines'), null, 'id'))?>;

function loadReturnItems(saleId) {
    var wrap = document.getElementById('retItems');
    var list = document.getElementById('retItemsList');
    if (!saleId) { wrap.style.display='none'; return; }
    var items = allItems.filter(function(si){ return String(si.sale_id) === String(saleId); });
    if (!items.length) { list.innerHTML='<p class="text-sm text-muted">No items for this sale.</p>'; wrap.style.display='block'; return; }
    var retIds = returnedItemsMap[saleId] || [];
    list.innerHTML = items.map(function(si) {
        var med = medMap[si.medicine_id] || {};
        var isReturned = retIds.indexOf(parseInt(si.id)) >= 0;
        var chip = isReturned ? '<span class="chip chip-red" style="font-size:.65rem">Already returned</span>' : '';
        return '<label style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--g3);cursor:' + (isReturned?'not-allowed':'pointer') + ';' + (isReturned?'opacity:.5':'') + '">' +
            '<input type="checkbox" name="item_ids[]" value="' + si.id + '" ' + (isReturned?'disabled':'') + ' style="width:16px;height:16px;accent-color:var(--navy)">' +
            '<span class="fw-600">' + (med.name||'Unknown') + '</span>' +
            '<span class="text-muted text-sm">Qty: ' + si.quantity + '</span>' +
            '<span class="fw-600" style="margin-left:auto;color:var(--navy)">' + parseFloat(si.price||0).toFixed(2) + '</span>' +
            chip + '</label>';
    }).join('');
    wrap.style.display = 'block';
}

function updateRestockSuggestion() {
    var reason = document.getElementById('retReason').value;
    var noRestock = ['Damaged packaging','Expired product','Quality issue'];
    var isNo = noRestock.indexOf(reason) >= 0;
    document.querySelector('input[name=restock][value=' + (isNo?'no':'yes') + ']').checked = true;
    highlightRestock();
}

function highlightRestock() {
    var yes = document.querySelector('input[name=restock][value=yes]').checked;
    document.getElementById('restockYesLabel').style.borderColor = yes ? 'var(--green)' : 'var(--g3)';
    document.getElementById('restockNoLabel').style.borderColor  = !yes ? 'var(--red)' : 'var(--g3)';
    document.getElementById('restockWarning').style.display      = !yes ? 'flex' : 'none';
}

function confirmReturn() {
    var saleEl   = document.querySelector('input[name=sale_id]') || document.getElementById('retSaleId');
    var saleId   = saleEl ? saleEl.value : '';
    var reason   = document.getElementById('retReason').value;
    var restock  = document.querySelector('input[name=restock]:checked')?.value;
    var checked  = document.querySelectorAll('input[name="item_ids[]"]:checked');
    if (!saleId)        { alert('Please select a sale invoice.'); return; }
    if (!checked.length){ alert('Please select at least one item to return.'); return; }
    if (!reason)        { alert('Please select a reason.'); return; }
    var names = [];
    checked.forEach(function(cb) {
        var lbl = cb.closest('label');
        if (lbl) names.push(lbl.querySelector('.fw-600').textContent.trim());
    });
    var stockTxt = restock === 'yes'
        ? '<span style="color:var(--green);font-weight:600">Stock WILL be restored</span>'
        : '<span style="color:var(--red);font-weight:600">Stock will NOT be restored (item discarded)</span>';
    document.getElementById('confirmDetails').innerHTML =
        '<p><strong>Items:</strong> ' + names.join(', ') + '</p>' +
        '<p><strong>Reason:</strong> ' + reason + '</p>' +
        '<p><strong>Stock:</strong> ' + stockTxt + '</p>';
    openModal('retConfirm');
}

// Auto-open Process modal for pending requests via Process button
document.querySelectorAll('[data-retid]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        window.location.href = 'index.php?p=returns&process_pending=' + this.dataset.retid;
    });
});
</script>
<?php adminFooter(); ?>
