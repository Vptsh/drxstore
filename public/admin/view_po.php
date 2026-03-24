<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_admin.php'; requireStaff();
$id=getInt('id'); $po=$db->findOne('purchase_orders',fn($p)=>(int)$p['id']===(int)$id);
if(!$po){setFlash('danger','PO not found.');header('Location: index.php?p=purchase');exit;}
$sup=$db->findOne('suppliers',fn($s)=>$s['id']==$po['supplier_id']);
$items=$db->find('po_items',fn($i)=>(int)($i['po_id']??0)===(int)$id);
$medMap=[]; foreach($db->table('medicines') as $m) $medMap[$m['id']]=$m;
$cfg=getSettings();
$chipMap=['pending'=>'chip-orange','price_updated'=>'chip-orange','confirmed'=>'chip-blue','shipped'=>'chip-purple','received'=>'chip-green','cancelled'=>'chip-red'];
$stLabel=match($po['status']??'pending'){'confirmed'=>'Confirmed','shipped'=>'Shipped / Dispatched','received'=>'Received','price_updated'=>'Price Updated — Awaiting Confirmation','cancelled'=>'Cancelled',default=>'Pending'};
adminHeader(poNo($id),'purchase');
?>
<div class="page-hdr no-print">
  <div><div class="page-title"><?=poNo($id)?></div></div>
  <div class="page-actions">
    <button class="btn btn-primary" onclick="doPrint('purchase')">Print</button>
    <a href="index.php?p=purchase" class="btn btn-ghost">Back</a>
    <?php if(in_array($po['status']??'',['pending','confirmed','shipped'])):?>
    <button class="btn btn-success" onclick="openReceiveModalDirect()">Mark as Received</button>
    <?php /* price_updated: admin must confirm first before receiving */ ?>
    <?php endif;?>
    <?php if(in_array($po['status']??'',['pending','confirmed','price_updated'])):?>
    <form method="POST" action="index.php?p=purchase" style="display:inline" onsubmit="return confirm('Cancel this purchase order? This cannot be undone.')">
      <?=csrfField()?>
      <input type="hidden" name="action" value="cancel_po">
      <input type="hidden" name="po_id" value="<?=$id?>">
      <button type="submit" class="btn btn-danger btn-sm">Cancel PO</button>
    </form>
    <?php endif;?>
    <?php if(($po['status']??'')==='received'):?>
    <form method="POST" action="index.php?p=purchase" style="display:inline">
      <?=csrfField()?>
      <input type="hidden" name="action" value="reorder_po">
      <input type="hidden" name="po_id" value="<?=$id?>">
      <button type="submit" class="btn btn-primary btn-sm">Reorder</button>
    </form>
    <?php endif;?>
    <?php if(false):// close original if ?>
    <?php if(($po['status']??'')==='price_updated'):?>
    <form method="POST" action="index.php?p=purchase" style="display:inline">
      <?=csrfField()?>
      <input type="hidden" name="action" value="confirm_po">
      <input type="hidden" name="po_id" value="<?=$id?>">
      <button type="submit" class="btn btn-primary">Confirm Order</button>
    </form>
    <?php endif;?>
    <?php endif;?>
  </div>
</div>

<style media="print">
  /* PO Document — dedicated print styles, override all layout CSS */
  @media print {
    body > *,
    .app-layout > *,
    .main-content > *,
    .page-wrap > * { display: none !important; }

    /* Show only the PO wrap */
    body > .app-layout,
    .app-layout > .main-content,
    .main-content > .page-wrap { display: block !important; height: auto !important; min-height: 0 !important; margin: 0 !important; padding: 0 !important; overflow: visible !important; }
    .page-wrap > .inv-wrap,
    .page-wrap > #po-print-wrap { display: block !important; }

    html, body { height: auto !important; min-height: 0 !important; overflow: visible !important; background: #fff !important; }
    .sidebar, .topbar, .page-hdr, .no-print, .btn, .alert, .flash, .pager, .modal-overlay { display: none !important; }

    .inv-wrap { width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; }
    .card { box-shadow: none !important; border: none !important; margin: 0 !important; padding: 0 !important; border-radius: 0 !important; }
    .card-body { padding: 12px !important; }
    .tbl { width: 100% !important; border-collapse: collapse !important; font-size: 9pt !important; }
    .tbl th, .tbl td { padding: 4px 6px !important; border: 1px solid #ddd !important; }
    .chip { border: 1px solid #ccc !important; padding: 1px 6px !important; border-radius: 4px !important; font-size: 8pt !important; }
    @page { size: A4 portrait; margin: 12mm 10mm; }
    @page:blank { display: none !important; }
  }
</style>
<div class="inv-wrap" id="po-print-wrap" style="max-width:760px;margin:0 auto">
<div class="card" style="border:none!important;box-shadow:none!important">
<div class="card-body">
  <!-- Store + PO Header -->
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;padding-bottom:16px;border-bottom:2px solid var(--navy);margin-bottom:16px">
    <div>
      <div style="font-size:1.3rem;font-weight:800;color:var(--navy)"><?=e($cfg['store_name']??APP_NAME)?></div>
      <?php if(!empty($cfg['store_address'])):?><div style="font-size:.78rem;color:var(--g6)"><?=e($cfg['store_address'])?></div><?php endif;?>
      <?php if(!empty($cfg['store_phone'])):?><div style="font-size:.78rem;color:var(--g6)">Tel: <?=e($cfg['store_phone'])?></div><?php endif;?>
      <?php if(!empty($cfg['store_email'])):?><div style="font-size:.78rem;color:var(--g6)">Email: <?=e($cfg['store_email'])?></div><?php endif;?>
      <?php if(!empty($cfg['store_gst'])):?><div style="font-size:.8rem;font-weight:700;color:var(--navy);margin-top:4px">GSTIN: <?=e($cfg['store_gst'])?></div><?php endif;?>
    </div>
    <div style="text-align:right">
      <div style="font-size:1.1rem;font-weight:800;color:var(--navy);border:2px solid var(--navy);padding:4px 14px;border-radius:6px">PURCHASE ORDER</div>
      <div style="font-size:.82rem;margin-top:8px"><strong>PO No:</strong> <?=poNo($id)?></div>
      <div style="font-size:.82rem"><strong>Status:</strong> <span class="chip <?=$chipMap[$po['status']??'pending']??'chip-orange'?>"><?=e($stLabel)?></span></div>
      <div style="font-size:.78rem;color:var(--g6);margin-top:6px;line-height:1.8">
        <div><strong>Ordered On:</strong> <?=dateF($po['po_date']??'')?></div>
        <?php if(!empty($po['shipped_at'])): ?>
        <div><strong>Shipped On:</strong> <?=dateTimeF($po['shipped_at'])?></div>
        <?php endif; ?>
        <?php if(!empty($po['received_at'])): ?>
        <div><strong>Received On:</strong> <?=dateTimeF($po['received_at'])?></div>
        <?php endif; ?>
      </div>
      <?php if(($po['status']??'')==='price_updated'):?>
      <div style="font-size:.78rem;color:var(--orange);font-weight:600;margin-top:4px">Supplier updated prices — your confirmation required</div>
      <?php endif;?>
      <?php if(($po['status']??'')==='cancelled'):?>
      <div style="font-size:.78rem;color:var(--red);font-weight:600;margin-top:4px">This order has been cancelled</div>
      <?php endif;?>

    </div>
  </div>

  <!-- Supplier Details -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:16px;padding:12px;background:var(--g1);border-radius:var(--rl);font-size:.83rem">
    <div>
      <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--g5);margin-bottom:6px">Supplier / Vendor</div>
      <p class="fw-600"><?=e($sup['name']??'—')?></p>
      <?php if(!empty($sup['contact'])):?><p class="text-muted"><?=e($sup['contact'])?></p><?php endif;?>
      <?php if(!empty($sup['phone'])):?><p class="text-muted">Tel: <?=e($sup['phone'])?></p><?php endif;?>
      <?php if(!empty($sup['email'])):?><p class="text-muted">Email: <?=e($sup['email'])?></p><?php endif;?>
      <?php if(!empty($sup['address'])):?><p class="text-muted"><?=e($sup['address'])?></p><?php endif;?>
      <?php if(!empty($sup['gst_no'])):?><p style="font-weight:700;color:var(--navy)">GSTIN: <?=e($sup['gst_no'])?></p><?php endif;?>
      <?php if(!empty($sup['dl_no'])):?><p style="font-weight:700;color:var(--navy)">DL No: <?=e($sup['dl_no'])?></p><?php endif;?>
    </div>
    <div>
      <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--g5);margin-bottom:6px">Ship To (Buyer)</div>
      <p class="fw-600"><?=e($cfg['store_name']??APP_NAME)?></p>
      <?php if(!empty($cfg['store_address'])):?><p class="text-muted"><?=e($cfg['store_address'])?></p><?php endif;?>
      <?php if(!empty($cfg['store_phone'])):?><p class="text-muted">Tel: <?=e($cfg['store_phone'])?></p><?php endif;?>
      <?php if(!empty($cfg['store_gst'])):?><p style="font-weight:700;color:var(--navy)">GSTIN: <?=e($cfg['store_gst'])?></p><?php endif;?>
      <?php if(!empty($cfg['store_dl'])):?><p style="font-weight:700;color:var(--navy)">DL No: <?=e($cfg['store_dl'])?></p><?php endif;?>
    </div>
  </div>

  <?php if(!empty($po['notes'])):?>
  <div class="no-print" style="padding:8px 12px;background:#fffbeb;border-left:3px solid #f59e0b;border-radius:4px;margin-bottom:14px;font-size:.83rem">
    <strong>Notes / Special Instructions:</strong> <?=e($po['notes'])?>
  </div>
  <?php endif;?>

  <!-- Items Table -->
  <div class="table-wrap"><table class="tbl" style="font-size:.83rem">
    <thead>
      <tr>
        <th>#</th>
        <th>Medicine / Product</th>
        <th>HSN</th>
        <th class="tc">Qty</th>
        <th class="tr">Unit Price</th>
        <th class="tr">GST%</th>
        <th class="tr">Taxable Amt</th>
        <th class="tr">GST Amt</th>
        <th class="tr">Total</th>
      </tr>
    </thead>
    <tbody>
    <?php $tot=0; $totalTaxablePO=0; $totalGstPO=0;
    foreach($items as $i=>$it):
      $med=$medMap[$it['medicine_id']??0]??null;
      $qty=(int)($it['quantity']??0);
      $unitPrice=(float)($it['price']??0);
      $gstPctPO=(float)($med['gst_percent']??12);
      // Purchase price is GST-INCLUSIVE (as per Indian pharmacy practice)
      // Back-calculate: taxable = (qty × price) / (1 + gst/100)
      $lineInclGST = round($qty * $unitPrice, 2);
      $taxablePO   = round($lineInclGST / (1 + $gstPctPO / 100), 2);
      $gstAmtPO    = round($lineInclGST - $taxablePO, 2);
      $lineTotalPO = $lineInclGST; // Total = inclusive amount (same as paid)
      $tot         += $lineTotalPO;
      $totalTaxablePO += $taxablePO;
      $totalGstPO     += $gstAmtPO;
    ?>
    <tr>
      <td><?=$i+1?></td>
      <td>
        <div class="fw-600"><?=e($med['name']??'—')?></div>
        <?php if($med&&!empty($med['generic_name'])):?><div class="text-xs text-muted"><?=e($med['generic_name'])?></div><?php endif;?>
      </td>
      <td class="text-sm"><?=e($med['hsn_code']??'—')?></td>
      <td class="tc"><?=$qty?></td>
      <td class="tr"><?=money($unitPrice)?></td>
      <td class="tr"><?=$gstPctPO?>%</td>
      <td class="tr"><?=money($taxablePO)?></td>
      <td class="tr"><?=money($gstAmtPO)?></td>
      <td class="tr fw-600"><?=money($lineTotalPO)?></td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>

  <!-- TOTALS SUMMARY -->
  <div style="text-align:right;margin-top:14px">
    <div style="display:inline-block;padding:12px 18px;background:var(--g1);border-radius:var(--rl);font-size:.85rem;min-width:260px;border:1px solid var(--g3)">
      <div style="display:flex;justify-content:space-between;gap:24px;margin-bottom:6px"><span class="text-muted">Taxable Amount</span><span><?=money($totalTaxablePO)?></span></div>
      <div style="display:flex;justify-content:space-between;gap:24px;margin-bottom:6px"><span class="text-muted">Total GST</span><span><?=money($totalGstPO)?></span></div>
      <div style="display:flex;justify-content:space-between;gap:24px;font-weight:800;font-size:1.05rem;border-top:2px solid var(--navy);padding-top:8px;margin-top:6px;color:var(--navy)"><span>Grand Total (incl. GST)</span><span><?=money($tot)?></span></div>
    </div>
  </div>

  <!-- Amount in words -->
  <div style="margin-top:10px;font-size:.78rem;color:var(--g6);text-align:right">
    Amount in words: <strong><?=amountInWords($tot)?> only</strong>
  </div>

  <!-- Terms -->
  <div style="margin-top:16px;padding:12px 14px;background:var(--g1);border-radius:var(--r);border:1px solid var(--g3);font-size:.72rem;color:var(--g6);line-height:1.7">
    <strong style="color:var(--g7)">Terms &amp; Conditions:</strong><br>
    1. Please supply the items as per specification above within the agreed timeframe.<br>
    2. All goods must be accompanied by a proper tax invoice and delivery challan.<br>
    3. Batch number, manufacturing date, and expiry date must be clearly marked on each item.<br>
    4. Goods not conforming to specifications may be returned at supplier's cost.<br>
    5. Payment will be made as per agreed terms upon receipt and verification of goods.<br>
    6. This is a computer-generated purchase order and is valid without physical signature.<br>
    <?php if(!empty($cfg['store_email'])):?>7. For queries: <?=e($cfg['store_email'])?><?php if(!empty($cfg['store_phone'])):?> | <?=e($cfg['store_phone'])?><?php endif;?><br><?php endif;?>
    8. Subject to GST regulations and local jurisdiction.
  </div>

  <!-- Signature -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-top:30px;font-size:.8rem">
    <div style="border-top:1px solid var(--g4);padding-top:8px;text-align:center">
      <div class="text-muted">Authorized Signatory</div>
      <div class="fw-600"><?=e($cfg['store_name']??APP_NAME)?></div>
    </div>
    <div style="border-top:1px solid var(--g4);padding-top:8px;text-align:center">
      <div class="text-muted">Supplier Acknowledgement</div>
      <div class="fw-600"><?=e($sup['name']??'—')?></div>
    </div>
  </div>

  
</div></div></div>

<script>
function openReceiveModalDirect(){
    window.location.href='index.php?p=purchase&receive_modal=<?=$id?>';
}
</script>




<script>
/* Print layout + back navigation */
(function(){
  function fixForPrint(){
    var d=document,q=function(s){return d.querySelector(s)};
    var sb=q('.sidebar'),tb=q('.topbar'),al=q('.app-layout'),mc=q('.main-content'),pw=q('.page-wrap');
    if(d.documentElement) d.documentElement.style.cssText='min-height:0!important;height:auto!important;overflow:hidden!important';
    if(d.body) d.body.style.cssText='min-height:0!important;height:auto!important;overflow:hidden!important;background:#fff!important';
    if(sb) sb.style.cssText='display:none!important;width:0!important;height:0!important;position:absolute!important;overflow:hidden!important';
    if(tb) tb.style.cssText='display:none!important;height:0!important;overflow:hidden!important';
    if(al) al.style.cssText='display:block!important;min-height:0!important;height:auto!important;overflow:hidden!important';
    if(mc) mc.style.cssText='margin:0!important;padding:0!important;min-height:0!important;height:auto!important;display:block!important;overflow:hidden!important';
    if(pw) pw.style.cssText='padding:0!important;margin:0!important;min-height:0!important;height:auto!important;overflow:hidden!important';
    d.querySelectorAll('.no-print,.btn,.alert,.pager,.search-bar,.topbar-new,.user-chip,.page-hdr,.flash').forEach(function(el){el.style.display='none';});
  }
  window.addEventListener('beforeprint', fixForPrint);
  if(window.matchMedia) window.matchMedia('print').addListener(function(mq){if(mq.matches) fixForPrint();});
})();
function doPrint(target) {
  // Direct print without navigation away - prevents printing PO list instead of invoice
  window.print();
}
</script>
<?php adminFooter();?>
