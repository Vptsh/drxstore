<?php
/**
 * DRXStore - Invoice (Legal Format)
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php';
require_once ROOT.'/views/layout_admin.php';
requireStaff();

if (empty($_SESSION['last_invoice'])) {
    setFlash('danger', 'No invoice data found.');
    header('Location: index.php?p=sales'); exit;
}
$inv  = $_SESSION['last_invoice'];
$cust = !empty($inv['customer_id']) ? $db->findOne('customers', fn($c) => $c['id'] == $inv['customer_id']) : null;
$cfg  = getSettings();
$currency = currencySymbol(); // charset-safe

// Calculate item-wise GST for proper display
$medMapI = []; foreach($db->table('medicines') as $m) $medMapI[$m['id']] = $m;

adminHeader('Invoice ' . invNo($inv['sale_id']), 'sales');
?>
<div class="page-hdr no-print">
  <div><div class="page-title">Invoice <?=invNo($inv['sale_id'])?></div></div>
  <div class="page-actions">
    <button class="btn btn-primary" onclick="window.print()">Print / Save PDF</button>
    <a href="index.php?p=sales" class="btn btn-ghost">+ New Sale</a>
    <a href="index.php?p=sales_hist" class="btn btn-ghost">Sales History</a>
  </div>
</div>

<div class="inv-wrap">
<style media="print">
  /* Auto-scale invoice to fit one page regardless of item count */
  @media print {
    .inv-wrap { transform-origin: top left; }
    .tbl { font-size: 8pt !important; }
    .tbl th, .tbl td { padding: 3px 4px !important; }
    .inv-body { padding: 10px !important; }
    .inv-hdr { padding: 12px 16px !important; }
    .inv-tot-box { padding: 6px 10px !important; margin-top: 8px !important; }
    .inv-tot-row { padding: 2px 0 !important; font-size: 9pt !important; }
    .inv-tot-row.total-row { font-size: 10pt !important; }
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
      <div style="font-size:.82rem;opacity:.8">Invoice No: <strong><?=invNo($inv['sale_id'])?></strong></div>
      <div style="font-size:.82rem;opacity:.8">Date: <strong><?=dateF($inv['date']??'')?></strong></div>
      <div style="font-size:.82rem;opacity:.8">Time: <strong><?=e($inv['time']??date('h:i A'))?></strong></div>
    </div>
  </div>

  <div class="inv-body">
    <!-- PATIENT / BILLING DETAILS -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid var(--g3)">
      <div>
        <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--g5);margin-bottom:6px">Bill To</div>
        <?php if($cust): ?>
        <p class="fw-600" style="font-size:.9rem"><?=e($cust['name']??'')?></p>
        <?php if(!empty($cust['phone'])):?><p class="text-sm text-muted">Phone: <?=e($cust['phone'])?></p><?php endif;?>
        <?php if(!empty($cust['email'])):?><p class="text-sm text-muted">Email: <?=e($cust['email'])?></p><?php endif;?>
        <?php if(!empty($cust['address'])):?><p class="text-sm text-muted"><?=e($cust['address'])?></p><?php endif;?>
        <?php else: ?>
        <p class="fw-600">Walk-in Customer</p>
        <p class="text-sm text-muted">Retail / OTC Sale</p>
        <?php endif; ?>
      </div>
      <div style="text-align:right">
        <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--g5);margin-bottom:6px">Payment Details</div>
        <p class="fw-600 text-sm">Method: <?=ucfirst($inv['payment']??'Cash')?></p>
        <?php if(($inv['payment']??'')==='upi'&&!empty($inv['upi_ref'])):?>
        <p class="text-sm text-muted">UPI Ref: <span class="mono"><?=e($inv['upi_ref'])?></span></p>
        <?php endif;?>
        <?php if(($inv['payment']??'')==='cheque'&&!empty($inv['cheque_no'])):?>
        <p class="text-sm text-muted">Cheque: <?=e($inv['cheque_no'])?></p>
        <p class="text-sm text-muted">Bank: <?=e($inv['cheque_bank']??'')?></p>
        <p class="text-sm text-muted">Date: <?=dateF($inv['cheque_date']??'')?></p>
        <?php endif;?>
        <p class="text-sm text-muted" style="margin-top:4px">Status: <span style="color:var(--green);font-weight:700">PAID</span></p>
      </div>
    </div>

    <!-- ITEMS TABLE -->
    <div class="table-wrap"><table class="tbl" style="font-size:.82rem">
      <thead>
        <tr>
          <th style="width:28px">#</th>
          <th>Medicine</th>
          <th style="width:52px">Batch</th>
          <th class="tc" style="width:36px">Qty</th>
          <th class="tr" style="width:72px">MRP/Unit</th>
          <th class="tr" style="width:42px">GST%</th>
          <th class="tr" style="width:72px">Taxable</th>
          <th class="tr" style="width:72px">GST Amt</th>
          <th class="tr" style="width:80px">Amount</th>
        </tr>
      </thead>
      <tbody>
      <?php
      $totalTaxable = 0; $totalGstAmt = 0;
      foreach($inv['cart'] as $i => $item):
          $med     = $medMapI[$item['medicine_id']??0] ?? null;
          $gstPct  = (float)($item['gst_pct'] ?? $med['gst_percent'] ?? 18);
          $baseAmt = round($item['price'] / (1 + $gstPct/100), 2);
          $gstAmt  = round($item['price'] - $baseAmt, 2);
          $totalTaxable += $baseAmt;
          $totalGstAmt  += $gstAmt;
      ?>
      <tr>
        <td><?=$i+1?></td>
        <td>
          <div class="fw-600" style="font-size:.82rem"><?=e($item['name']??'')?></div>
          <?php if($med&&!empty($med['generic_name'])):?><div style="font-size:.7rem;color:var(--g5)"><?=e($med['generic_name'])?></div><?php endif;?>
          <?php if($med&&!empty($med['hsn_code'])):?><div style="font-size:.7rem;color:var(--g5)">HSN: <?=e($med['hsn_code'])?></div><?php endif;?>
        </td>
        <td style="font-size:.75rem;font-family:monospace"><?=e($item['batch']??'')?></td>
        <td class="tc"><?=e($item['qty']??0)?></td>
        <td class="tr"><?=$currency?><?=number_format($item['mrp']??0,2)?></td>
        <td class="tr"><?=$gstPct?>%</td>
        <td class="tr"><?=$currency?><?=number_format($baseAmt,2)?></td>
        <td class="tr"><?=$currency?><?=number_format($gstAmt,2)?></td>
        <td class="tr fw-600"><?=$currency?><?=number_format($item['price']??0,2)?></td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table></div>

    <!-- TOTALS -->
    <div style="text-align:right;margin-top:12px">
      <div style="display:inline-block;padding:10px 16px;background:var(--g1);border-radius:var(--rl);font-size:.85rem;min-width:220px">
        <div style="display:flex;justify-content:space-between;gap:20px;margin-bottom:4px"><span class="text-muted">Taxable Amount</span><span><?=$currency?><?=number_format($totalTaxable,2)?></span></div>
        <div style="display:flex;justify-content:space-between;gap:20px;margin-bottom:4px"><span class="text-muted">Total GST</span><span><?=$currency?><?=number_format($totalGstAmt,2)?></span></div>
        <?php if(($inv['disc']??0)>0):?>
        <div style="display:flex;justify-content:space-between;gap:20px;margin-bottom:4px;color:var(--green)"><span>Discount<?=!empty($inv['disc_name'])?' ('.$inv['disc_name'].')':''?></span><span>- <?=$currency?><?=number_format($inv['disc'],2)?></span></div>
        <?php endif;?>
        <div style="display:flex;justify-content:space-between;gap:20px;font-weight:700;font-size:1rem;border-top:1px solid var(--g3);padding-top:6px;margin-top:4px;color:var(--navy)"><span>Grand Total</span><span><?=$currency?><?=number_format($inv['grand'],2)?></span></div>
        <div style="font-size:.72rem;color:var(--g5);margin-top:3px"><?=amountInWords($inv['grand'])?> only</div>
      </div>
    </div>

    <!-- LEGAL DISCLAIMER -->
    <div style="margin-top:18px;padding:12px 14px;background:var(--g1);border-radius:var(--r);border:1px solid var(--g3);font-size:.72rem;color:var(--g6);line-height:1.6">
      <strong style="color:var(--g7)">Terms &amp; Conditions:</strong><br>
      1. Goods once sold will not be taken back except as per applicable laws and store policy.<br>
      2. Please check the expiry date and batch number before purchase.<br>
      3. This is a computer-generated invoice and does not require a physical signature.<br>
      4. For any queries, contact us at <?=e(storeEmail())?> or <?=e($cfg['store_phone']??'')?><br>
      5. Subject to <?=e($cfg['store_gst']?'GST regulations and ':'')?> local jurisdiction.
    </div>

    <div class="inv-foot">
      <div style="margin-bottom:4px">Thank you for your purchase! We wish you good health.</div>

    </div>
  </div>
</div>





<?php adminFooter();?>
