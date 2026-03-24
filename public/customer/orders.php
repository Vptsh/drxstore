<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_portal.php'; requireCustomer();
$cid=$_SESSION['cust_id']??0;
$myOrders=$db->find('sales',fn($s)=>($s['customer_id']??0)===$cid);
usort($myOrders,fn($a,$b)=>($b['id']??0)<=>($a['id']??0));
$viewId=getInt('view');
$viewSale=$viewId?$db->findOne('sales',fn($s)=>$s['id']===$viewId&&($s['customer_id']??0)===$cid):null;
$viewItems=$viewSale?$db->find('sales_items',fn($si)=>($si['sale_id']??0)===$viewId):[];
$medMap=[]; foreach($db->table('medicines') as $m) $medMap[$m['id']]=$m;
$batMap=[]; foreach($db->table('batches')   as $b) $batMap[$b['id']]=$b;
$cfg=getSettings();
$currency=$cfg['currency']??'&#8377;';
$storeEmail=storeEmail();
$navItems=['cust_dash'=>['icon'=>'grid','label'=>'My Dashboard'],'cust_orders'=>['icon'=>'orders','label'=>'My Orders'],'cust_messages'=>['icon'=>'mail','label'=>'Messages'],'cust_return'=>['icon'=>'return','label'=>'Return Request'],'cust_profile'=>['icon'=>'user','label'=>'My Profile']];
portalHeader('My Orders','customer','cust_orders',$navItems,['name'=>'customer_name']);
?>
<div class="page-hdr no-print"><div><div class="page-title"> My Orders</div><div class="page-sub"><?=count($myOrders)?> purchase(s)</div></div></div>

<?php if($viewSale): ?>
<style media="print">
  @media print {
    body > *, .app-layout > *, .main-content > *, .page-wrap > * { display: none !important; }
    body > .app-layout, .app-layout > .main-content, .main-content > .page-wrap {
      display: block !important; height: auto !important; min-height: 0 !important;
      margin: 0 !important; padding: 0 !important; overflow: visible !important;
    }
    .page-wrap > .inv-wrap, .page-wrap > #cust-inv-wrap { display: block !important; }
    html, body { height: auto !important; min-height: 0 !important; overflow: visible !important; background: #fff !important; }
    .sidebar,.topbar,.page-hdr,.no-print,.btn,.alert,.flash,.pager,.modal-overlay,.card.no-print { display: none !important; }
    .inv-wrap, .card { box-shadow: none !important; border: none !important; margin: 0 !important; padding: 0 !important; border-radius: 0 !important; }
    .card-body { padding: 10px !important; }
    .tbl { width: 100% !important; border-collapse: collapse !important; font-size: 8.5pt !important; }
    .tbl th, .tbl td { padding: 3px 5px !important; border-color: #ccc !important; }
    @page { size: A4 portrait; margin: 12mm 10mm; }
    @page:blank { display: none !important; }
  }
</style>
<div class="card mb-2 inv-wrap" id="cust-inv-wrap">
  <div class="card-hdr no-print"><div class="card-title">Invoice <?=invNo($viewId)?></div>
    <div class="flex gap-1">
      <button class="btn btn-primary btn-sm" onclick="window.print()">Print</button>
      <a href="index.php?p=cust_orders" class="btn btn-ghost btn-sm">Back</a>
    </div>
  </div>
  <div class="card-body">
    <!-- Store header -->
    <div style="background:var(--navy);color:#fff;border-radius:var(--rl);padding:16px 20px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
      <div>
        <div style="font-size:1.15rem;font-weight:800;margin-bottom:4px"><?=e($cfg['store_name']??APP_NAME)?></div>
        <?php if(!empty($cfg['store_address'])): ?><div style="font-size:.75rem;opacity:.85"><?=e($cfg['store_address'])?></div><?php endif; ?>
        <?php if(!empty($cfg['store_phone'])): ?><div style="font-size:.75rem;opacity:.85">Tel: <?=e($cfg['store_phone'])?></div><?php endif; ?>
        <?php if(!empty($storeEmail)): ?><div style="font-size:.75rem;opacity:.85">Email: <?=e($storeEmail)?></div><?php endif; ?>
        <?php if(!empty($cfg['store_gst'])): ?><div style="font-size:.78rem;font-weight:700;margin-top:4px">GSTIN: <?=e($cfg['store_gst'])?></div><?php endif; ?>
        <?php if(!empty($cfg['store_dl'])): ?><div style="font-size:.78rem;font-weight:700;margin-top:2px">DL No: <?=e($cfg['store_dl'])?></div><?php endif; ?>
      </div>
      <div style="text-align:right">
        <div style="font-size:.9rem;font-weight:700">TAX INVOICE</div>
        <div style="font-size:.75rem;opacity:.85">Invoice No: <strong><?=invNo($viewId)?></strong></div>
        <div style="font-size:.75rem;opacity:.85">Date: <strong><?=dateF($viewSale['sale_date']??'')?></strong></div>
        <?php if(!empty($viewSale['created_at'])): ?>
        <div style="font-size:.75rem;opacity:.85">Time: <strong><?=date('h:i A',strtotime($viewSale['created_at']))?></strong></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Bill To / Payment -->
    <?php $cu=$db->findOne('customers',fn($c)=>$c['id']===$cid); ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--g3);font-size:.83rem">
      <div>
        <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--g5);margin-bottom:5px">Bill To</div>
        <p class="fw-600"><?=e($cu['name']??'Patient')?></p>
        <?php if(!empty($cu['phone'])): ?><p class="text-muted">Phone: <?=e($cu['phone'])?></p><?php endif; ?>
        <?php if(!empty($cu['email'])): ?><p class="text-muted">Email: <?=e($cu['email'])?></p><?php endif; ?>
      </div>
      <div style="text-align:right">
        <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--g5);margin-bottom:5px">Payment Details</div>
        <p class="fw-600">Method: <?=ucfirst($viewSale['payment_method']??'Cash')?></p>
        <p>Status: <span style="color:var(--green);font-weight:700">PAID</span></p>
      </div>
    </div>

    <!-- Items -->
    <div class="table-wrap"><table class="tbl" style="font-size:.82rem">
      <thead><tr><th>#</th><th>Medicine</th><th>Batch</th><th class="tc">Qty</th><th class="tr">MRP/Unit</th><th class="tr">GST%</th><th class="tr">Taxable</th><th class="tr">GST Amt</th><th class="tr">Amount</th></tr></thead>
      <tbody>
      <?php
      $totalTaxable=0; $totalGstAmt=0;
      foreach($viewItems as $i=>$si):
        $med=$medMap[$si['medicine_id']??0]??null;
        $bat=$batMap[$si['batch_id']??0]??null;
        $gstPct=(float)($med['gst_percent']??18);
        $itemAmt=(float)($si['price']??0);
        $baseAmt=round($itemAmt/(1+$gstPct/100),2);
        $gstAmtItem=round($itemAmt-$baseAmt,2);
        $totalTaxable+=$baseAmt; $totalGstAmt+=$gstAmtItem;
      ?>
      <tr>
        <td><?=$i+1?></td>
        <td>
          <div class="fw-600"><?=e($med['name']??'—')?></div>
          <?php if($med&&!empty($med['generic_name'])): ?><div class="text-xs text-muted"><?=e($med['generic_name'])?></div><?php endif; ?>
          <?php if($med&&!empty($med['hsn_code'])): ?><div class="text-xs text-muted">HSN: <?=e($med['hsn_code'])?></div><?php endif; ?>
        </td>
        <td><code class="mono"><?=e($bat['batch_no']??'—')?></code></td>
        <td class="tc"><?=$si['quantity']?></td>
        <td class="tr"><?=$currency?><?=number_format($si['mrp']??0,2)?></td>
        <td class="tr"><?=$gstPct?>%</td>
        <td class="tr"><?=$currency?><?=number_format($baseAmt,2)?></td>
        <td class="tr"><?=$currency?><?=number_format($gstAmtItem,2)?></td>
        <td class="tr fw-600"><?=$currency?><?=number_format($itemAmt,2)?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>

    <!-- Totals -->
    <div style="text-align:right;margin-top:12px">
      <div style="display:inline-block;padding:10px 16px;background:var(--g1);border-radius:var(--rl);font-size:.85rem;min-width:220px">
        <div style="display:flex;justify-content:space-between;gap:20px;margin-bottom:4px"><span class="text-muted">Taxable Amount</span><span><?=$currency?><?=number_format($totalTaxable,2)?></span></div>
        <div style="display:flex;justify-content:space-between;gap:20px;margin-bottom:4px"><span class="text-muted">Total GST</span><span><?=$currency?><?=number_format($totalGstAmt,2)?></span></div>
        <?php if(($viewSale['discount_amount']??0)>0): ?>
        <div style="display:flex;justify-content:space-between;gap:20px;margin-bottom:4px;color:var(--green)"><span>Discount</span><span>-<?=$currency?><?=number_format($viewSale['discount_amount'],2)?></span></div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;gap:20px;font-weight:700;font-size:1rem;border-top:1px solid var(--g3);padding-top:6px;margin-top:4px;color:var(--navy)"><span>Grand Total</span><span><?=$currency?><?=number_format($viewSale['grand_total']??0,2)?></span></div>
        <div style="font-size:.72rem;color:var(--g5);margin-top:3px"><?=amountInWords($viewSale['grand_total']??0)?> only</div>
      </div>
    </div>

    <!-- Terms -->
    <div style="margin-top:14px;padding:10px 12px;background:var(--g1);border-radius:var(--r);border:1px solid var(--g3);font-size:.72rem;color:var(--g6);line-height:1.6">
      <strong style="color:var(--g7)">Terms &amp; Conditions:</strong><br>
      1. Goods once sold will not be taken back except as per applicable laws and store policy.<br>
      2. Please check the expiry date and batch number before purchase.<br>
      3. This is a computer-generated invoice and does not require a physical signature.<br>
      <?php if(!empty($storeEmail)): ?>4. For queries contact us at <?=e($storeEmail)?><?php if(!empty($cfg['store_phone'])): ?> or <?=e($cfg['store_phone'])?><?php endif; ?><br><?php endif; ?>
      5. Subject to GST regulations and local jurisdiction.
    </div>
    <div style="text-align:center;margin-top:10px;font-size:.72rem;color:var(--g5)">Thank you for your purchase! We wish you good health.</div>
  </div>
</div>
<?php endif; ?>

<!-- Orders list — hidden on print -->
<div class="card no-print"><div class="card-body p0">
  <?php if(empty($myOrders)): ?>
  <div class="empty-state"><p>No orders yet.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>Invoice</th><th>Date</th><th>Payment</th><th class="tc">Items</th><th class="tr">Total</th><th></th></tr></thead>
    <tbody>
    <?php foreach($myOrders as $s):
      $ic=$db->count('sales_items',fn($si)=>($si['sale_id']??0)==$s['id']);
    ?>
    <tr>
      <td><span class="chip chip-purple"><?=invNo($s['id'])?></span></td>
      <td class="text-sm"><?=dateF($s['sale_date']??'')?></td>
      <td><span class="chip chip-gray"><?=ucfirst($s['payment_method']??'cash')?></span></td>
      <td class="tc"><span class="chip chip-gray"><?=$ic?></span></td>
      <td class="tr fw-600"><?=money($s['grand_total']??0)?></td>
      <td><div class="flex gap-1">
        <a href="index.php?p=cust_orders&view=<?=$s['id']?>" class="btn btn-ghost btn-sm">View</a>
        <a href="index.php?p=cust_return&sale_id=<?=$s['id']?>" class="btn btn-ghost btn-sm">Return</a>
      </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div></div>

<?php portalFooter(); ?>
