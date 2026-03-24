<?php
/**
 * DRXStore - View Invoice from History (Legal Format — matches invoice.php)
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php';
require_once ROOT.'/views/layout_admin.php';
requireStaff();

$sid  = getInt('sale_id');
$sale = $db->findOne('sales', fn($s) => $s['id'] === $sid);
if (!$sale) { setFlash('danger','Invoice not found.'); header('Location: index.php?p=sales_hist'); exit; }

$cust  = !empty($sale['customer_id']) ? $db->findOne('customers', fn($c) => $c['id'] == $sale['customer_id']) : null;
$items = $db->find('sales_items', fn($si) => ($si['sale_id']??0) === $sid);
$medMap = []; foreach($db->table('medicines') as $m) $medMap[$m['id']] = $m;
$batMap = []; foreach($db->table('batches')   as $b) $batMap[$b['id']] = $b;
$cfg   = getSettings();
$currency = currencySymbol(); // charset-safe

// Re-calculate GST per item (stored gst_amount is aggregate, need per-item breakdown)
$totalTaxable = 0; $totalGst = 0;
foreach ($items as &$si) {
    $med    = $medMap[$si['medicine_id']??0] ?? null;
    $gstPct = (float)($med['gst_percent'] ?? 18);
    $base   = round((float)($si['price']??0) / (1 + $gstPct/100), 2);
    $gst    = round((float)($si['price']??0) - $base, 2);
    $si['_gst_pct']  = $gstPct;
    $si['_base']     = $base;
    $si['_gst_amt']  = $gst;
    $totalTaxable   += $base;
    $totalGst       += $gst;
}
unset($si);

$discAmt = (float)($sale['discount_amount'] ?? 0);
$grand   = (float)($sale['grand_total'] ?? ($totalTaxable + $totalGst - $discAmt));

// Get discount name from discount_id
$discName = '';
if (!empty($sale['discount_id'])) {
    $disc = $db->findOne('discounts', fn($d) => $d['id'] == $sale['discount_id']);
    if ($disc) $discName = $disc['name'] . ' (' . ($disc['type']==='percent' ? $disc['value'].'%' : money($disc['value'])) . ')';
}

adminHeader('Invoice ' . invNo($sid), 'sales_hist');
?>
<div class="page-hdr no-print">
  <div><div class="page-title">Invoice <?=invNo($sid)?></div></div>
  <div class="page-actions">
    <button class="btn btn-primary" onclick="window.print()">Print / Save PDF</button>
    <a href="index.php?p=sales_hist" class="btn btn-ghost">Back to History</a>
  </div>
</div>

<div class="inv-wrap">
<style media="print">
  @media print {
    .tbl { font-size: 8pt !important; }
    .tbl th, .tbl td { padding: 3px 4px !important; }
    .inv-body { padding: 10px !important; }
    .inv-hdr { padding: 12px 16px !important; }
    .inv-tot-box { padding: 6px 10px !important; margin-top: 8px !important; }
    .inv-tot-row { padding: 2px 0 !important; font-size: 9pt !important; }
    div[style*="margin-top:18px"] { margin-top: 10px !important; padding: 8px 10px !important; font-size: 7pt !important; }
    .inv-foot { padding: 6px 10px !important; font-size: 8pt !important; }
    @page { size: A4 portrait; margin: 10mm 8mm; }
  }
</style>
  <!-- STORE HEADER -->
  <div class="inv-hdr" style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px">
    <div>
      <h1 style="font-size:1.4rem;font-weight:800;letter-spacing:-.01em;margin-bottom:4px"><?=e($cfg['store_name']??APP_NAME)?></h1>
      <?php if(!empty($cfg['store_address'])):?><p style="font-size:.78rem;opacity:.82;margin-bottom:2px"><?=e($cfg['store_address'])?></p><?php endif;?>
      <?php if(!empty($cfg['store_phone'])):?><p style="font-size:.78rem;opacity:.82;margin-bottom:2px">Tel: <?=e($cfg['store_phone'])?></p><?php endif;?>
      <?php if(!empty($cfg['store_email'])):?><p style="font-size:.78rem;opacity:.82;margin-bottom:2px">Email: <?=e($cfg['store_email'])?></p><?php endif;?>
      <?php if(!empty($cfg['store_gst'])):?><p style="font-size:.82rem;font-weight:700;margin-top:6px">GSTIN: <?=e($cfg['store_gst'])?></p><?php endif;?>
      <?php if(!empty($cfg['store_dl'])):?><p style="font-size:.82rem;font-weight:700;margin-top:2px">DL No: <?=e($cfg['store_dl'])?></p><?php endif;?>
    </div>
    <div style="text-align:right">
      <div style="font-size:1.1rem;font-weight:700;margin-bottom:4px">TAX INVOICE</div>
      <div style="font-size:.82rem;opacity:.8">Invoice No: <strong><?=invNo($sid)?></strong></div>
      <div style="font-size:.82rem;opacity:.8">Date: <strong><?=dateF($sale['sale_date']??'')?></strong></div>
      <div style="font-size:.82rem;opacity:.8">Time: <strong><?=!empty($sale['created_at'])?date('h:i A',strtotime($sale['created_at'])):date('h:i A')?></strong></div>
    </div>
  </div>

  <div class="inv-body">
    <!-- BILLING + PAYMENT -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid var(--g3)">
      <div>
        <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--g5);margin-bottom:6px">Bill To</div>
        <?php if ($cust): ?>
        <p class="fw-600" style="font-size:.9rem"><?=e($cust['name']??'')?></p>
        <?php if(!empty($cust['phone'])):?><p class="text-sm text-muted">Phone: <?=e($cust['phone'])?></p><?php endif;?>
        <?php if(!empty($cust['email'])):?><p class="text-sm text-muted">Email: <?=e($cust['email'])?></p><?php endif;?>
        <?php if(!empty($cust['address'])):?><p class="text-sm text-muted"><?=e($cust['address'])?></p><?php endif;?>
        <?php else: ?>
        <p class="fw-600">Walk-in Customer</p><p class="text-sm text-muted">Retail / OTC Sale</p>
        <?php endif; ?>
      </div>
      <div style="text-align:right">
        <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--g5);margin-bottom:6px">Payment Details</div>
        <p class="fw-600 text-sm">Method: <?=ucfirst($sale['payment_method']??'Cash')?></p>
        <?php if(($sale['payment_method']??'')==='upi'&&!empty($sale['upi_ref'])):?>
        <p class="text-sm text-muted">UPI Ref: <span class="mono"><?=e($sale['upi_ref'])?></span></p>
        <?php endif;?>
        <?php if(($sale['payment_method']??'')==='cheque'&&!empty($sale['cheque_no'])):?>
        <p class="text-sm text-muted">Cheque: <?=e($sale['cheque_no'])?> &mdash; <?=e($sale['cheque_bank']??'')?></p>
        <p class="text-sm text-muted">Date: <?=dateF($sale['cheque_date']??'')?></p>
        <?php endif;?>
        <p class="text-sm text-muted" style="margin-top:4px">Status: <span style="color:var(--green);font-weight:700">PAID</span></p>
      </div>
    </div>

    <!-- ITEMS TABLE -->
    <div class="table-wrap"><table class="tbl" style="font-size:.82rem">
      <thead>
        <tr>
          <th style="width:26px">#</th><th>Medicine</th><th style="width:52px">Batch</th>
          <th class="tc" style="width:36px">Qty</th><th class="tr" style="width:72px">MRP/Unit</th>
          <th class="tr" style="width:42px">GST%</th>
          <th class="tr" style="width:70px">Taxable</th>
          <th class="tr" style="width:70px">GST Amt</th>
          <th class="tr" style="width:78px">Amount</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $i => $si):
        $med = $medMap[$si['medicine_id']??0] ?? null;
        $bat = $batMap[$si['batch_id']??0] ?? null;
      ?>
      <tr>
        <td><?=$i+1?></td>
        <td>
          <div class="fw-600"><?=e($med['name']??'—')?></div>
          <?php if($med&&!empty($med['generic_name'])):?><div class="text-xs text-muted"><?=e($med['generic_name'])?></div><?php endif;?>
          <?php if($med&&!empty($med['hsn_code'])):?><div class="text-xs text-muted">HSN: <?=e($med['hsn_code'])?></div><?php endif;?>
        </td>
        <td><span class="mono"><?=e($bat['batch_no']??'—')?></span></td>
        <td class="tc"><?=$si['quantity']?></td>
        <td class="tr"><?=$currency?><?=number_format($si['mrp']??0,2)?></td>
        <td class="tr"><?=$si['_gst_pct']?>%</td>
        <td class="tr"><?=$currency?><?=number_format($si['_base']??0,2)?></td>
        <td class="tr"><?=$currency?><?=number_format($si['_gst_amt']??0,2)?></td>
        <td class="tr fw-600"><?=$currency?><?=number_format($si['price']??0,2)?></td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table></div>

    <!-- TOTALS -->
    <div style="text-align:right;margin-top:12px">
      <div style="display:inline-block;padding:10px 16px;background:var(--g1);border-radius:var(--rl);font-size:.85rem;min-width:220px">
        <div style="display:flex;justify-content:space-between;gap:20px;margin-bottom:4px"><span class="text-muted">Taxable Amount</span><span><?=$currency?><?=number_format($totalTaxable,2)?></span></div>
        <div style="display:flex;justify-content:space-between;gap:20px;margin-bottom:4px"><span class="text-muted">Total GST</span><span><?=$currency?><?=number_format($totalGst,2)?></span></div>
        <?php if ($discAmt > 0): ?>
        <div style="display:flex;justify-content:space-between;gap:20px;margin-bottom:4px;color:var(--green)"><span>Discount<?=$discName?' ('.$discName.')':''?></span><span>-<?=$currency?><?=number_format($discAmt,2)?></span></div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;gap:20px;font-weight:700;font-size:1rem;border-top:1px solid var(--g3);padding-top:6px;margin-top:4px;color:var(--navy)"><span>Grand Total</span><span><?=$currency?><?=number_format($grand,2)?></span></div>
        <div style="font-size:.72rem;color:var(--g5);margin-top:3px"><?=amountInWords($grand)?> only</div>
      </div>
    </div>

    <!-- LEGAL DISCLAIMER -->
    <div style="margin-top:18px;padding:12px 14px;background:var(--g1);border-radius:var(--r);border:1px solid var(--g3);font-size:.72rem;color:var(--g6);line-height:1.6">
      <strong style="color:var(--g7)">Terms &amp; Conditions:</strong><br>
      1. Goods once sold will not be taken back except as per applicable laws and store policy.<br>
      2. Please check the expiry date and batch number before purchase.<br>
      3. This is a computer-generated invoice and does not require a physical signature.<br>
      4. For queries, contact us at <?=e(storeEmail())?> or <?=e($cfg['store_phone']??'')?><br>
      5. Subject to <?=e(!empty($cfg['store_gst'])?'GST regulations and ':'')?> local jurisdiction.
    </div>

    <div class="inv-foot">
      <div style="margin-bottom:4px">Thank you for your purchase! We wish you good health.</div>

    </div>
  </div>
</div>

<?php adminFooter(); ?>
