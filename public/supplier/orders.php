<?php
/**
 * DRXStore - Supplier Portal: Purchase Orders
 * Status flow: pending -> confirmed -> shipped (admin sets received)
 * Prices editable ONLY when pending. Locked after confirmation.
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php';
require_once ROOT.'/views/layout_portal.php';
requireSupplier();

$sid  = $_SESSION['supplier_id'] ?? 0;
$su   = $db->findOne('supplier_users', fn($u) => $u['id'] === $sid);
$supId= $su['supplier_id'] ?? 0;
$medMap = []; foreach ($db->table('medicines') as $m) $medMap[$m['id']] = $m;

// Update status + price (supplier can only go pending->confirmed->shipped, NOT received)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $pid    = postInt('po_id');
    $status = post('status');
    $po     = $db->findOne('purchase_orders', fn($p) => $p['id'] === $pid && ($p['supplier_id'] ?? 0) === $supId);
    $current = $po['status'] ?? 'pending';

    // Handle save prices — sets status to price_updated (admin must confirm)
    if (($_POST['save_action'] ?? '') === 'save_prices' && in_array($current, ['pending','price_updated'])) {
        $prices = $_POST['item_price'] ?? [];
        if (is_array($prices)) {
            foreach ($prices as $item_id => $price) {
                $item_id = (int)$item_id; $price = (float)$price;
                if ($item_id > 0 && $price >= 0) {
                    $db->update('po_items', fn($i) => $i['id'] === $item_id && ($i['po_id']??0) === $pid, ['price' => $price]);
                }
            }
            $items = $db->find('po_items', fn($i) => ($i['po_id']??0) === $pid);
            $total = array_sum(array_map(fn($i) => (float)($i['quantity']??0) * (float)($i['price']??0), $items));
            $db->update('purchase_orders', fn($p) => $p['id'] === $pid, ['status'=>'price_updated','total' => $total, 'updated_at' => date('Y-m-d H:i:s')]);
            // Notify admin store email
            $cfg = getSettings();
            $adminEmail = $cfg['store_email'] ?? storeEmail();
            if($adminEmail){
                $pn=poNo($pid); $store=storeName();
                $body=mailWrap("Supplier Updated Prices — {$pn}","<p>The supplier has updated prices for Purchase Order <strong>{$pn}</strong> (Total: ".money($total).").</p><p>Please review and confirm the order in the admin portal.</p><p><a href='".siteUrl('index.php',['p'=>'purchase'])."'>Open Purchase Orders</a></p>");
                sendMail($adminEmail,"Price Update on {$pn} — {$store}",$body);
            }
            setFlash('success', 'Prices submitted. The store admin will review and confirm the order.');
            header('Location: index.php?p=sup_orders&view=' . $pid); exit;
        }
    }

    // Supplier can ONLY mark as shipped (admin confirms separately)
    if ($po && $status === 'shipped') {
        $valid = in_array($current, ['confirmed', 'shipped']);
        if ($valid) {
            $db->update('purchase_orders', fn($p) => $p['id'] === $pid, ['status' => 'shipped', 'shipped_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            // Notify admin
            $cfg = getSettings();
            $adminEmail = $cfg['store_email'] ?? storeEmail();
            if($adminEmail){
                $pn=poNo($pid); $store=storeName();
                $body=mailWrap("PO Shipped — {$pn}","<p>The supplier has marked Purchase Order <strong>{$pn}</strong> as <strong>Shipped / Dispatched</strong>.</p><p>Please mark as Received once the goods arrive.</p><p><a href='".siteUrl('index.php',['p'=>'purchase'])."'>Open Purchase Orders</a></p>");
                sendMail($adminEmail,"PO Shipped: {$pn} — {$store}",$body);
            }
            setFlash('success', 'Order marked as Shipped. The store will be notified.');
        } else {
            setFlash('danger', 'Can only mark as Shipped after admin confirms the order.');
        }
    }
    header('Location: index.php?p=sup_orders' . ($pid ? '&view='.$pid : '')); exit;
}

$orders  = $db->find('purchase_orders', fn($p) => ($p['supplier_id'] ?? 0) === $supId);
usort($orders, fn($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));
$viewId  = getInt('view');
$viewPO  = $viewId ? $db->findOne('purchase_orders', fn($p) => $p['id'] === $viewId && ($p['supplier_id'] ?? 0) === $supId) : null;
$viewItems = $viewPO ? $db->find('po_items', fn($i) => ($i['po_id'] ?? 0) === $viewId) : [];

$navItems = [
    'sup_dash'   => ['icon' => 'grid',   'label' => 'Dashboard'],
    'sup_orders' => ['icon' => 'orders', 'label' => 'Purchase Orders'],
    'sup_profile'=> ['icon' => 'user',   'label' => 'My Profile'],
    'sup_contact'=> ['icon' => 'mail',   'label' => 'Contact Store'],
];
portalHeader('Purchase Orders', 'supplier', 'sup_orders', $navItems, ['name' => 'supplier_company']);
?>
<div class="page-hdr">
  <div><div class="page-title">Purchase Orders</div><div class="page-sub"><?=count($orders)?> orders</div></div>
</div>
<div class="alert alert-info" style="margin-bottom:16px">
  <span class="alert-body">
    <strong>Order flow:</strong>
    <span class="chip chip-orange">Pending</span> &rarr;
    <strong>Submit Prices</strong> &rarr;
    <span class="chip chip-blue">Confirmed by Store</span> &rarr;
    <span class="chip chip-purple">Shipped by You</span> &rarr;
    <span class="chip chip-green">Received</span> (set by store).<br>
    Update prices and submit — the store admin will confirm. Then mark as shipped.
  </span>
</div>

<?php if ($viewPO): ?>
<div class="card mb-2">
  <div class="card-hdr">
    <div class="card-title"><?=poNo($viewPO['id'])?> &mdash; Detail</div>
    <a href="index.php?p=sup_orders" class="btn btn-ghost btn-sm">Back to List</a>
  </div>
  <div class="card-body">
    <?php
    $st   = $viewPO['status'] ?? 'pending';
    $chips = ['pending'=>'chip-orange','price_updated'=>'chip-orange','confirmed'=>'chip-blue','shipped'=>'chip-purple','received'=>'chip-green','cancelled'=>'chip-red'];
    $stLabel = match($st){'confirmed'=>'Confirmed','shipped'=>'Shipped / Dispatched','received'=>'Received by Store','price_updated'=>'Prices Submitted — Awaiting Store Confirmation','cancelled'=>'Cancelled',default=>'Pending'};
    // Prices editable ONLY when pending
    $canEditPrices = in_array($st, ['pending','price_updated']);
    ?>
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;font-size:.84rem">
      <div>
        <p><strong>PO Number:</strong> <?=poNo($viewPO['id'])?></p>
        <p><strong>Ordered On:</strong> <?=dateF($viewPO['po_date'] ?? '')?></p>
        <?php if (!empty($viewPO['shipped_at'])): ?>
        <p><strong>Shipped On:</strong> <?=dateTimeF($viewPO['shipped_at']??'')?></p>
        <?php endif; ?>
        <?php if (!empty($viewPO['received_at'])): ?>
        <p><strong>Received On:</strong> <?=dateTimeF($viewPO['received_at']??'')?></p>
        <?php endif; ?>
      </div>
      <div>
        <p><strong>Status:</strong> <span class="chip <?=$chips[$st]??'chip-gray'?>"><?=e($stLabel)?></span></p>
        <p><strong>Total:</strong> <strong><?=money($viewPO['total'] ?? 0)?></strong></p>
      </div>
    </div>

    <?php if (!empty($viewPO['notes'])): ?>
    <div class="alert alert-info"><span class="alert-body"><strong>Notes from store:</strong> <?=e($viewPO['notes'])?></span></div>
    <?php endif; ?>
    <?php if($st==='cancelled'): ?>
    <div class="alert alert-danger"><span class="alert-body"><strong>This order has been cancelled by the store.</strong> No further action is required. Please disregard any shipment instructions for this order.</span></div>
    <?php elseif($st==='price_updated'): ?>
    <div class="alert alert-warning"><span class="alert-body"><strong>Prices submitted — awaiting store confirmation.</strong> The store admin is reviewing your updated prices. You will be notified when the order is confirmed and you can proceed with shipment.</span></div>
    <?php elseif($st==='confirmed'): ?>
    <div class="alert alert-success"><span class="alert-body"><strong>Order confirmed by store!</strong> Prices are locked. Please prepare the goods for shipment and update the status below when dispatched.</span></div>
    <?php endif; ?>

    <?php if (!$canEditPrices && $st !== 'received'): ?>
    <div class="alert alert-warning"><span class="alert-body">Prices are <strong>locked</strong> after order confirmation and cannot be changed.</span></div>
    <?php endif; ?>

    <form method="POST" id="orderForm"><?=csrfField()?>
    <input type="hidden" name="po_id" value="<?=$viewPO['id']?>">
    <input type="hidden" name="save_action" id="saveAction" value="update_status">
    <div class="table-wrap"><table class="tbl">
      <thead>
        <tr>
          <th>#</th><th>Medicine</th><th class="tc">Qty</th>
          <th class="tr">Unit Price <?=$canEditPrices?'<span class="text-xs text-muted">(editable — pending only)</span>':'<span class="text-xs text-muted">(locked)</span>'?></th>
          <th class="tr" id="rowTotHead">Row Total</th>
        </tr>
      </thead>
      <tbody>
      <?php $tot = 0; foreach ($viewItems as $i => $it):
        $med  = $medMap[$it['medicine_id'] ?? 0] ?? null;
        $line = (float)($it['quantity'] ?? 0) * (float)($it['price'] ?? 0);
        $tot += $line;
      ?>
      <tr>
        <td><?=$i+1?></td>
        <td class="fw-600"><?=e($med['name'] ?? '&mdash;')?></td>
        <td class="tc"><?=e($it['quantity']??0)?></td>
        <td class="tr">
          <?php if ($canEditPrices): ?>
          <input type="number" name="item_price[<?=$it['id']?>]" value="<?=e($it['price']??0)?>"
                 step="0.01" min="0" class="form-control row-price-input"
                 data-qty="<?=(int)($it['quantity']??0)?>"
                 style="width:100px;display:inline-block;text-align:right;padding:4px 8px">
          <?php else: ?>
          <?=money($it['price'] ?? 0)?>
          <?php endif; ?>
        </td>
        <td class="tr fw-600 row-total-cell"><?=money($line)?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div style="text-align:right;margin-top:10px;font-size:.95rem;padding:10px;background:var(--g1);border-radius:var(--r)">
      <strong>Total: <span id="liveTot"><?=money($tot)?></span></strong>
    </div>
    <?php if($canEditPrices): ?>
    <div style="margin-top:8px;text-align:right">
      <button type="button" class="btn btn-primary btn-sm" onclick="savePricesOnly()">Submit Prices for Confirmation</button>
    </div>
    <?php endif; ?>

    <?php if ($st !== 'received'): ?>
    <?php if($st==='confirmed' || $st==='shipped'): ?>
    <div class="form-section" style="margin-top:18px">Update Status</div>
    <div class="flex gap-2 flex-wrap items-center">
      <select class="form-control" name="status" id="statusSelect" style="width:auto">
        <option value="shipped" <?=$st==='shipped'?'selected':''?>>Mark as Shipped / Dispatched</option>
      </select>
      <button type="submit" class="btn btn-primary">Update Status</button>
    </div>
    <?php elseif(in_array($st,['pending','price_updated'])): ?>
    <div class="alert alert-info" style="margin-top:14px">
      <span class="alert-body">
        <?php if($st==='price_updated'):?>
        <strong>Prices submitted.</strong> Waiting for store admin to confirm this order. You will be notified when confirmed.
        <?php else:?>
        Update item prices above and click <strong>Submit Prices</strong> to notify the store admin for confirmation.
        <?php endif;?>
      </span>
    </div>
    <?php endif;?>
    <?php else: ?>
    <div class="alert alert-success" style="margin-top:14px"><span class="alert-body">This order has been marked as <strong>received</strong> by the store. No further action needed.</span></div>
    <?php endif; ?>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Order list -->
<div class="card"><div class="card-body p0">
  <?php if (empty($orders)): ?>
    <div class="empty-state"><p>No purchase orders assigned to your account yet.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>PO Number</th><th>Date</th><th class="tr">Total</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php
    $chipMap = ['pending'=>'chip-orange','price_updated'=>'chip-orange','confirmed'=>'chip-blue','shipped'=>'chip-purple','received'=>'chip-green','cancelled'=>'chip-red'];
    foreach ($orders as $o):
      $ost = $o['status'] ?? 'pending';
      $stLbl = match($ost){'confirmed'=>'Confirmed','shipped'=>'Shipped / Dispatched','received'=>'Received','price_updated'=>'Awaiting Confirmation','cancelled'=>'Cancelled',default=>'Pending'};
    ?>
    <tr>
      <td><span class="chip chip-navy"><?=poNo($o['id'])?></span></td>
      <td class="text-sm"><?=dateF($o['po_date'] ?? '')?></td>
      <td class="tr fw-600"><?=money($o['total'] ?? 0)?></td>
      <td><span class="chip <?=$chipMap[$ost]??'chip-gray'?>"><?=e($stLbl)?></span></td>
      <td><a href="index.php?p=sup_orders&view=<?=$o['id']?>" class="btn btn-ghost btn-sm">View / Update</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div></div>

<script>
function savePricesOnly() {
    var sa = document.getElementById('saveAction');
    var ss = document.getElementById('statusSelect');
    if (sa) sa.value = 'save_prices';
    if (ss) ss.disabled = true;
    var form = document.getElementById('orderForm');
    if (form) form.submit();
}

// Live total + row total recalculation as prices are typed
document.querySelectorAll('.row-price-input').forEach(function(inp) {
    inp.addEventListener('input', function() {
        var qty = parseFloat(this.dataset.qty) || 0;
        var price = parseFloat(this.value) || 0;
        var rowCell = this.closest('tr').querySelector('.row-total-cell');
        if (rowCell) rowCell.textContent = '\u20b9' + (qty * price).toFixed(2);
        // Recalc grand total
        var tot = 0;
        document.querySelectorAll('.row-price-input').forEach(function(r) {
            tot += (parseFloat(r.dataset.qty)||0) * (parseFloat(r.value)||0);
        });
        var el = document.getElementById('liveTot');
        if (el) el.textContent = '\u20b9' + tot.toFixed(2);
    });
});
</script>
<?php portalFooter(); ?>
