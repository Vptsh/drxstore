<?php
/**
 * DRXStore - Admin Dashboard
 * Developed by Vineet | psvineet@zohomail.in
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT . '/config/app.php';
require_once ROOT . '/views/layout_admin.php';
requireStaff();

// Send daily stock alert email (once per day)
if (($_SESSION['admin_role']??'') === 'admin') { sendStockAlertEmails(); }

$today     = date('Y-m-d');
$thisMonth = date('Y-m');
$warnD     = date('Y-m-d', strtotime('+' . EXPIRY_DAYS . ' days'));

$allSales    = $db->table('sales');
$todaySales  = array_filter($allSales, fn($s) => ($s['sale_date']??'') === $today);
$monthSales  = array_filter($allSales, fn($s) => strpos($s['sale_date']??'', $thisMonth) === 0);
$allReturns   = $db->table('returns');
$returnedToday= array_filter($allReturns, fn($r) => substr($r['created_at']??'',0,10) === $today && in_array($r['status']??'',['processed','pending']));
$returnedMonth= array_filter($allReturns, fn($r) => strpos($r['created_at']??'',$thisMonth)===0 && in_array($r['status']??'',['processed','pending']));

$totalRetRefund= (float)array_sum(array_column(array_filter($allReturns,fn($r)=>in_array($r['status']??'',['processed'])),'refund_amount'));
$todayRetRefund= (float)array_sum(array_column(array_values($returnedToday),'refund_amount'));
$monthRetRefund= (float)array_sum(array_column(array_values($returnedMonth),'refund_amount'));

$totalPOExpense= (float)$db->sum('purchase_orders','total',fn($p)=>($p['status']??'')==='received');

$totalRev    = max(0,(float)array_sum(array_column($allSales,'grand_total')) - $totalRetRefund);
$todayRev    = max(0,(float)array_sum(array_column(array_values($todaySales),'grand_total')) - $todayRetRefund);
$monthRev    = max(0,(float)array_sum(array_column(array_values($monthSales),'grand_total')) - $monthRetRefund);
$netProfit   = $totalRev - $totalPOExpense;

$totalMeds  = $db->count('medicines');
$totalStock = (int)array_sum(array_column($db->table('batches'),'quantity'));
$totalCusts = $db->count('customers');
$totalSups  = $db->count('suppliers');

$lowStock  = $db->find('batches', fn($b)=>($b['quantity']??0)>0&&($b['quantity']??0)<LOW_QTY);
$expiring  = $db->find('batches', fn($b)=>($b['quantity']??0)>0&&($b['expiry_date']??'')>=$today&&($b['expiry_date']??'')<=$warnD);
$expired   = $db->find('batches', fn($b)=>($b['quantity']??0)>0&&($b['expiry_date']??'')<$today);

$medMap  = []; foreach($db->table('medicines') as $m) $medMap[$m['id']] = $m;
$custMap = []; foreach($db->table('customers') as $c) $custMap[$c['id']] = $c;

usort($allSales, fn($a,$b)=>($b['id']??0)<=>($a['id']??0));
$recentSales = array_slice($allSales, 0, 8);

// 7-day chart
$chart7 = []; $lbls7 = [];
for ($i=6; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $lbls7[] = date('D', strtotime($d));
    $chart7[] = (int)array_sum(array_column(array_values($db->find('sales', fn($s)=>($s['sale_date']??'')===$d)), 'grand_total'));
}

// Top 5 medicines (30d)
$since30 = date('Y-m-d', strtotime('-30 days'));
$sDateMap = []; foreach($db->table('sales') as $s) $sDateMap[$s['id']] = $s['sale_date']??'';
$medQty = [];
foreach($db->table('sales_items') as $si) {
    if (($sDateMap[$si['sale_id']??0] ?? '') >= $since30) {
        $mid = $si['medicine_id']??0;
        $medQty[$mid] = ($medQty[$mid]??0) + ($si['quantity']??0);
    }
}
arsort($medQty); $topMeds = array_slice($medQty, 0, 5, true);
$maxTop = !empty($topMeds) ? max(array_values($topMeds)) : 1;

// Aliases used in stats HTML (some added later without matching PHP vars)
$todayNet    = $todayRev;
$monthNet    = $monthRev;
$totalNet    = $totalRev;
$todayRetAmt = (float)array_sum(array_column(array_values($returnedToday),'refund_amount'));
$monthRetAmt = (float)array_sum(array_column(array_values($returnedMonth),'refund_amount'));
$totalRetAmt = $totalRetRefund;
$totalPOExp  = $totalPOExpense;
$pendingPOs     = $db->count('purchase_orders', fn($p) => in_array($p['status']??'',['pending','price_updated']));
$processingPOs  = $db->count('purchase_orders', fn($p) => in_array($p['status']??'', ['confirmed','shipped']));
$cancelledPOs   = $db->count('purchase_orders', fn($p) => ($p['status']??'') === 'cancelled');
$grossProfit = $totalNet - $totalPOExp;

adminHeader('Dashboard', 'dashboard');
?>

<div class="page-hdr">
  <div>
    <div class="page-title">Good <?= date('H')<12?'Morning':(date('H')<17?'Afternoon':'Evening') ?>, <?= e(explode(' ',$_SESSION['admin_name']??'Admin')[0]) ?></div>
    <div class="page-sub"><?= date('l, d F Y') ?> &nbsp;|&nbsp; Pharmacy overview</div>
  </div>
  <div class="page-actions">
    <a href="index.php?p=batches" class="btn btn-ghost">+ Add Stock</a>
    <a href="index.php?p=sales" class="btn btn-primary">+ New Sale</a>
  </div>
</div>

<?php if (!empty($expired)): ?>
<div class="alert alert-danger"><span class="alert-body"><strong><?= count($expired) ?> expired batch(es)</strong> still have stock. <a href="index.php?p=expiry" style="color:inherit;font-weight:700">Review </a></span><button class="alert-close" onclick="this.parentElement.remove()">x</button></div>
<?php endif; ?>
<?php if (!empty($lowStock)): ?>
<div class="alert alert-warning"><span class="alert-body"><strong><?= count($lowStock) ?> item(s)</strong> below minimum stock level. <a href="index.php?p=batches" style="color:inherit;font-weight:700">Reorder </a></span><button class="alert-close" onclick="this.parentElement.remove()">x</button></div>
<?php endif; ?>

<!-- Stats — single row, all cards, suppliers separate -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;margin-bottom:12px">
  <div class="stat s-green"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><div class="stat-lbl">Net Revenue</div><div class="stat-val"><?=money($totalRev)?></div><div class="stat-note">After <?=count(array_filter($allReturns,fn($r)=>($r['status']??'')==='processed'))?> return(s)</div></div>
  <div class="stat s-red"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/></svg></div><div class="stat-lbl">PO Expenses</div><div class="stat-val"><?=money($totalPOExpense)?></div><div class="stat-note"><?=$db->count('purchase_orders',fn($p)=>($p['status']??'')==='received')?> received POs</div></div>
  <div class="stat <?=$netProfit>=0?'s-blue':'s-orange'?>"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div><div class="stat-lbl">Net Profit</div><div class="stat-val"><?=money($netProfit)?></div><div class="stat-note"><?=$netProfit>=0?'Profit':'Loss'?></div></div>
  <div class="stat s-orange"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg></div><div class="stat-lbl">Returns</div><div class="stat-val"><?=money($totalRetRefund)?></div><div class="stat-note"><?=count(array_filter($allReturns,fn($r)=>($r['status']??'')==='pending'))?> pending</div></div>
  <div class="stat s-green"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div><div class="stat-lbl">Today Net</div><div class="stat-val"><?=money($todayNet)?></div><div class="stat-note"><?=count($todaySales)?> sale(s)</div></div>
  <div class="stat s-blue"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div><div class="stat-lbl">Month Net</div><div class="stat-val"><?=money($monthNet)?></div><div class="stat-note"><?=count($monthSales)?> sale(s)</div></div>
  <div class="stat s-teal"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 3.5a5 5 0 0 1 7.07 7.07l-7.5 7.5a5 5 0 0 1-7.07-7.07l7.5-7.5z"/></svg></div><div class="stat-lbl">Medicines</div><div class="stat-val"><?=$totalMeds?></div><div class="stat-note"><?=$db->count('batches')?> batches</div></div>
  <div class="stat s-orange"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8"/><line x1="12" y1="22" x2="12" y2="12"/></svg></div><div class="stat-lbl">Stock Units</div><div class="stat-val"><?=number_format($totalStock)?></div><div class="stat-note"><?=count($lowStock)?> low</div></div>
  <div class="stat s-purple"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div><div class="stat-lbl">Customers</div><div class="stat-val"><?=$totalCusts?></div><div class="stat-note"><?=$totalCusts?> registered</div></div>
  <div class="stat s-navy"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg></div><div class="stat-lbl">Suppliers</div><div class="stat-val"><?=$totalSups?></div><div class="stat-note"><?=$pendingPOs?> pending POs</div></div>
  <?php if($cancelledPOs>0):?><div class="stat s-red"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><div class="stat-lbl">Cancelled POs</div><div class="stat-val"><?=$cancelledPOs?></div><div class="stat-note"><a href="index.php?p=purchase" style="color:inherit;text-decoration:underline">View orders</a></div></div><?php endif;?>
</div>

<div class="dash-grid">

  <!-- Revenue chart -->
  <div class="card">
    <div class="card-hdr"><div class="card-title">Revenue — Last 7 Days</div><span class="chip chip-blue"><?= money(array_sum($chart7)) ?></span></div>
    <div class="card-body">
      <div id="revChart" class="bar-chart" style="height:90px"></div>
      <div class="bar-lbls"><?php foreach($lbls7 as $l): ?><div class="bar-lbl"><?= e($l) ?></div><?php endforeach; ?></div>
    </div>
  </div>

  <!-- Top medicines -->
  <div class="card">
    <div class="card-hdr"><div class="card-title">Top Medicines (30d)</div><a href="index.php?p=reports" class="btn btn-ghost btn-sm">Report </a></div>
    <div class="card-body p0">
      <?php if (empty($topMeds)): ?>
        <div class="empty-state"><p>No data yet</p></div>
      <?php else: $ri=1; foreach($topMeds as $mid=>$units): $med=$medMap[$mid]??null; ?>
        <div class="flex items-center gap-2" style="padding:10px 16px;border-bottom:1px solid var(--g3)">
          <div style="width:22px;height:22px;border-radius:6px;background:var(--navy-lt);color:var(--navy);font-size:.7rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= $ri++ ?></div>
          <div style="flex:1;min-width:0">
            <div class="fw-600 truncate" style="font-size:.82rem"><?= e($med['name']??'Unknown') ?></div>
            <div class="prog" style="margin-top:3px"><div class="prog-bar" style="width:<?= round($units/$maxTop*100) ?>%"></div></div>
          </div>
          <span class="chip chip-blue"><?= $units ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Low stock -->
  <div class="card">
    <div class="card-hdr"><div class="card-title"> Low / Out of Stock</div><a href="index.php?p=batches" class="btn btn-ghost btn-sm">Manage </a></div>
    <div class="card-body p0">
      <?php
      $alerts = array_merge(
        array_map(fn($b)=>array_merge($b,['_t'=>'out']),$db->find('batches',fn($b)=>($b['quantity']??0)===0)),
        array_map(fn($b)=>array_merge($b,['_t'=>'low']),$lowStock)
      );
      if (empty($alerts)): ?>
        <div class="empty-state" style="padding:24px"><p>All healthy!</p></div>
      <?php else: foreach(array_slice($alerts,0,6) as $b): $med=$medMap[$b['medicine_id']??0]??null; ?>
        <div class="flex items-center gap-2" style="padding:10px 16px;border-bottom:1px solid var(--g3)">
          <span class="status-dot <?= $b['_t']==='out'?'dot-red':'dot-orange' ?>"></span>
          <div style="flex:1;min-width:0"><div class="fw-600 truncate" style="font-size:.82rem"><?= e($med['name']??'—') ?></div><div class="text-xs text-muted">Batch <?= e($b['batch_no']??'') ?></div></div>
          <?= $b['_t']==='out' ? '<span class="chip chip-red">Out</span>' : '<span class="chip chip-orange">'.$b['quantity'].'</span>' ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Expiry alerts -->
  <div class="card">
    <div class="card-hdr"><div class="card-title">Expiry Alerts</div><a href="index.php?p=expiry" class="btn btn-ghost btn-sm">Report </a></div>
    <div class="card-body p0">
      <?php
      $allExp = array_merge(
        array_map(fn($b)=>array_merge($b,['_exp'=>true]),$expired),
        array_map(fn($b)=>array_merge($b,['_exp'=>false]),$expiring)
      );
      if (empty($allExp)): ?>
        <div class="empty-state" style="padding:24px"><p>No issues!</p></div>
      <?php else: foreach(array_slice($allExp,0,6) as $b): $med=$medMap[$b['medicine_id']??0]??null; ?>
        <div class="flex items-center gap-2" style="padding:10px 16px;border-bottom:1px solid var(--g3)">
          <span class="status-dot <?= $b['_exp']?'dot-red':'dot-orange' ?>"></span>
          <div style="flex:1;min-width:0"><div class="fw-600 truncate" style="font-size:.82rem"><?= e($med['name']??'—') ?></div><div class="text-xs text-muted">Qty: <?= $b['quantity'] ?></div></div>
          <?= expiryChip($b['expiry_date']??'') ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Recent sales -->
  <div class="card col-2">
    <div class="card-hdr"><div class="card-title">Recent Sales</div><a href="index.php?p=sales_hist" class="btn btn-ghost btn-sm">View All </a></div>
    <?php if (empty($recentSales)): ?>
      <div class="empty-state"><p>No sales yet.</p><a href="index.php?p=sales" class="btn btn-primary btn-sm">Start Billing</a></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th class="tc">Items</th><th class="tr">Total</th><th></th></tr></thead>
        <tbody>
        <?php foreach($recentSales as $s):
          $cust = $custMap[$s['customer_id']??0] ?? null;
          $ic   = $db->count('sales_items', fn($si)=>($si['sale_id']??0)==$s['id']);
          $grand= (float)($s['grand_total']??(($s['total_amount']??0)+($s['gst_amount']??0)));
        ?>
        <tr>
          <td><span class="chip chip-blue"><?= invNo($s['id']) ?></span></td>
          <td class="text-sm"><?= dateF($s['sale_date']??'') ?></td>
          <td class="fw-600"><?= e($cust['name']??'Walk-in') ?></td>
          <td class="tc"><span class="chip chip-gray"><?= $ic ?></span></td>
          <td class="tr fw-600 text-blue"><?= money($grand) ?></td>
          <td><a href="index.php?p=view_inv&sale_id=<?= (int)$s['id'] ?>" class="btn btn-ghost btn-sm">View</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php adminFooter(); ?>
<script>document.addEventListener('DOMContentLoaded',function(){drawBarChart('revChart', <?= json_encode($chart7) ?>, '#0a2342');});</script>
