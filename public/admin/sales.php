<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_admin.php'; requireStaff();
if(!isset($_SESSION['cart'])) $_SESSION['cart']=[];
$medicines=$db->table('medicines'); usort($medicines,fn($a,$b)=>strcasecmp($a['name'],$b['name']));
$customers=$db->table('customers'); usort($customers,fn($a,$b)=>strcasecmp($a['name'],$b['name']));
$discounts=$db->find('discounts',fn($d)=>($d['active']??false));
// Calculate cart totals using per-medicine GST
$cartSub=0; $cartGst=0;
$medMapS=[]; foreach($db->table('medicines') as $m) $medMapS[$m['id']]=$m;
foreach($_SESSION['cart'] as $ci){
    $med=$medMapS[$ci['medicine_id']??0]??null;
    $gPct=(float)($med['gst_percent']??18);
    $inclAmt=(float)$ci['price']; // MRP×qty, GST inclusive
    $base=round($inclAmt/(1+$gPct/100),2);
    $cartSub += $base;
    $cartGst += round($inclAmt-$base,2);
}
$cartSub=round($cartSub,2); $cartGst=round($cartGst,2);
// cartTotal = sum of inclusive prices (what customer pays)
$cartTotal=0; foreach($_SESSION['cart'] as $ci) $cartTotal+=(float)$ci['price']; $cartTotal=round($cartTotal,2);
// Persist discount in session
if(getInt('discount_id')!==0)$_SESSION['cart_disc']=getInt('discount_id');
if(getInt('clear_disc')===1){unset($_SESSION['cart_disc']);unset($_SESSION['cart_disc_name']);}
$selDisc=$_SESSION['cart_disc']??0;
$discAmt=0;
if($selDisc){
    $disc=$db->findOne('discounts',fn($d)=>$d['id']===$selDisc&&($d['active']??false));
    if($disc){
        $discName=$disc['name'].' ('.($disc['type']==='percent'?$disc['value'].'%':money($disc['value'])).')';
        $_SESSION['cart_disc_name']=$discName;
        if(($disc['type']??'')==='percent') $discAmt=round($cartTotal*($disc['value']/100),2);
        else $discAmt=min((float)$disc['value'],$cartTotal);
    }else{unset($_SESSION['cart_disc']);}
}
$finalTotal=max(0,$cartTotal-$discAmt);
adminHeader('New Sale / POS','sales');
?>
<div class="page-hdr"><div><div class="page-title">New Sale — POS</div><div class="page-sub"><?=date('d M Y, h:i A')?></div></div>
<?php if(!empty($_SESSION['cart'])):?><span class="chip chip-blue"><?=count($_SESSION['cart'])?> item(s) in cart</span><?php endif;?></div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:16px;align-items:start">
<div>
  <!-- Customer -->
  <div class="card mb-2"><div class="card-hdr"><div class="card-title">Customer</div></div><div class="card-body">
    <form method="POST" action="index.php?p=cart_setcust" class="flex gap-2 flex-wrap items-end">
      <?=csrfField()?>
      <div class="form-group" style="flex:1;min-width:180px;margin:0">
        <select class="form-control" name="customer_id" data-searchable data-placeholder="Walk-in Customer">
          <option value="">Walk-in Customer</option>
          <?php foreach($customers as $c):?><option value="<?=e($c['id'])?>" <?=($_SESSION['cart_cust']??'')==$c['id']?'selected':''?>><?=e($c['name'])?> — <?=e($c['phone'])?></option><?php endforeach;?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Set</button>
    </form>
    <?php if(!empty($_SESSION['cart_cust'])): $cc=$db->findOne('customers',fn($c)=>$c['id']==$_SESSION['cart_cust']);?>
      <?php if($cc):?><div class="alert alert-info mt-1" style="padding:6px 10px;font-size:.8rem"><span class="alert-body">Customer: <strong><?=e($cc['name'])?></strong> — <?=e($cc['phone'])?></span></div><?php endif;?>
    <?php endif;?>
  </div></div>

  <!-- Add medicine -->
  <div class="card"><div class="card-hdr"><div class="card-title">Add Medicine to Cart</div></div><div class="card-body">
    <form method="POST" action="index.php?p=cart_add">
      <?=csrfField()?>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Medicine</label>
          <select class="form-control" name="medicine_id" id="medicine_id" onchange="loadBatches()" required data-searchable data-placeholder="— Search Medicine —">
            <option value="">— Select —</option>
            <?php foreach($medicines as $m):?><option value="<?=e($m['id'])?>"><?=e($m['name'])?></option><?php endforeach;?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Batch</label>
          <select class="form-control" name="batch_id" id="batch_id" onchange="updateBatchInfo()" required data-searchable data-placeholder="— Select Batch —">
            <option value="">Select medicine first</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:20px;padding:10px 14px;background:var(--g1);border-radius:var(--rl);margin-bottom:12px;font-size:.83rem">
        <div><span class="text-muted">Stock:</span> <strong id="stock_display">—</strong></div>
        <div><span class="text-muted">MRP:</span> <strong id="mrp_display">—</strong></div>
      </div>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Quantity</label><input class="form-control" type="number" name="qty" id="qty" min="1" value="1" required></div>
        <div class="form-group"><label class="form-label">Discount (applies to whole sale)</label>
          <div class="flex gap-1">
            <select class="form-control" name="discount_id" >
              <option value="">No Discount</option>
              <?php foreach($discounts as $d):?><option value="<?=e($d['id'])?>" <?=$selDisc==$d['id']?'selected':''?>><?=e($d['name'])?> (<?=($d['type']??'')==='percent'?$d['value'].'%':money($d['value'])?>)</option><?php endforeach;?>
            </select>
            <?php if($selDisc):?><a href="index.php?p=sales&clear_disc=1" class="btn btn-ghost btn-sm" style="white-space:nowrap">Clear</a><?php endif;?>
          </div>
          <?php if($discAmt>0):?><div class="form-hint" style="color:var(--green)">Discount applied: -<?=money($discAmt)?></div><?php endif;?>
        </div>
      </div>
      <!-- Payment method -->
      <div class="form-section">Payment Method</div>
      <div class="form-group"><label class="form-label">Payment Method</label>
        <select class="form-control" name="payment_method" id="payment_method" onchange="togglePayment()">
          <option value="cash">Cash</option>
          <option value="upi">UPI</option>
          <option value="cheque">Cheque</option>
          <option value="card">Card</option>
          <option value="credit">Credit (Pay Later)</option>
        </select>
      </div>
      <div id="pay_upi" class="pay-detail" style="display:none">
        <div class="form-group"><label class="form-label">UPI Reference Number</label><input class="form-control" type="text" name="upi_ref" placeholder="UPI transaction ID"></div>
      </div>
      <div id="pay_cheque" class="pay-detail" style="display:none">
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Cheque Number</label><input class="form-control" type="text" name="cheque_no" placeholder="Cheque No."></div>
          <div class="form-group"><label class="form-label">Bank Name</label><input class="form-control" type="text" name="cheque_bank" placeholder="Bank"></div>
        </div>
        <div class="form-group"><label class="form-label">Cheque Date</label><input class="form-control" type="date" name="cheque_date"></div>
      </div>
      <button type="submit" class="btn btn-success w-full">Add to Cart</button>
    </form>
  </div></div>
</div>

<!-- Cart -->
<div style="position:sticky;top:60px">
<div class="card"><div class="card-hdr"><div class="card-title">Cart</div>
  <?php if(!empty($_SESSION['cart'])):?><a href="index.php?p=cart_clear" class="btn btn-ghost btn-sm" style="color:var(--red)" data-confirm="Clear cart?">Clear</a><?php endif;?>
</div>
<?php if(empty($_SESSION['cart'])):?>
  <div class="empty-state" style="padding:30px"><p>Cart is empty</p></div>
<?php else:?>
  <?php foreach($_SESSION['cart'] as $i=>$item):?>
  <div class="cart-row">
    <div style="flex:1;min-width:0"><div class="cart-name truncate"><?=e($item['name'])?></div><div class="cart-sub">Batch <?=e($item['batch'])?> | Qty <?=$item['qty']?> <?=money($item['mrp']??0)?></div></div>
    <div class="fw-600 text-sm" style="white-space:nowrap"><?=money($item['price'])?></div>
    <a href="index.php?p=cart_remove&idx=<?=$i?>" class="btn btn-danger btn-sm" style="margin-left:4px">Remove</a>
  </div>
  <?php endforeach;?>
  <div class="cart-tots">
    <div class="cart-tot-row"><span>Subtotal</span><span><?=money($cartSub)?></span></div>
    <div class="cart-tot-row"><span>GST</span><span><?=money($cartGst)?></span></div>
    <?php if($discAmt>0):?><div class="cart-tot-row" style="color:var(--green)"><span>Discount</span><span>-<?=money($discAmt)?></span></div><?php endif;?>
    <div class="cart-tot-row grand"><span>Total</span><span><?=money($finalTotal)?></span></div>
  </div>
  <div style="padding:12px">
    <form method="POST" action="index.php?p=finalize">
      <?=csrfField()?>
      <input type="hidden" name="discount_id" value="<?=$selDisc?>">
      <input type="hidden" name="discount_amt" value="<?=$discAmt?>">
      <button type="submit" class="btn btn-primary btn-block btn-lg">Generate Invoice</button>
    </form>
  </div>
<?php endif;?>
</div></div>
</div>
<script>
// Discount applied via cart_add form submission
</script>
<?php adminFooter();?>
