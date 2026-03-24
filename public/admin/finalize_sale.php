<?php
/**
 * DRXStore - Finalize Sale
 * Uses per-medicine GST rate. Default 18% if not set.
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php';
requireStaff();
verifyCsrf();

if (empty($_SESSION['cart'])) {
    setFlash('danger', 'Cart is empty.');
    header('Location: index.php?p=sales'); exit;
}

$cart  = $_SESSION['cart'];
$today = date('Y-m-d');

// Validate stock and expiry
foreach ($cart as $item) {
    $b = $db->findOne('batches', fn($b) => (int)$b['id'] === (int)$item['batch_id']);
    if (!$b) { setFlash('danger', $item['name'] . ' batch not found.'); header('Location: index.php?p=sales'); exit; }
    if (($b['expiry_date'] ?? '') < $today) { setFlash('danger', $item['name'] . ' is expired.'); header('Location: index.php?p=sales'); exit; }
    if (($b['quantity'] ?? 0) < $item['qty']) { setFlash('danger', 'Insufficient stock for ' . $item['name']); header('Location: index.php?p=sales'); exit; }
}

// Calculate totals — MRP and price are GST-INCLUSIVE (as per Indian law)
// Back-calculate: base = price_incl / (1 + gst_rate)  |  gst = price_incl - base
$sub    = 0;
$gstAmt = 0;
foreach ($cart as &$item) {
    $med      = $db->findOne('medicines', fn($m) => (int)$m['id'] === (int)$item['medicine_id']);
    $gstPct   = (float)($med['gst_percent'] ?? 18);
    $inclAmt  = (float)$item['price']; // MRP × qty (inclusive of GST)
    $baseAmt  = round($inclAmt / (1 + $gstPct / 100), 2); // taxable value
    $itemGst  = round($inclAmt - $baseAmt, 2);             // GST component
    $sub    += $baseAmt;
    $gstAmt += $itemGst;
    $item['gst_pct'] = $gstPct;
    $item['gst_amt'] = $itemGst;
}
unset($item);
$sub    = round($sub, 2);   // total taxable (excl. GST)
$gstAmt = round($gstAmt, 2);

// Discount
$discAmt = (float)post('discount_amt', 0);
$discId  = postInt('discount_id') ?: null;
// Also handle custom percentage discount
$discPct = (float)post('discount_pct', 0);
if ($discPct > 0 && !$discId) {
    // Apply discount on inclusive total (what the customer actually pays)
    $discAmt = round($inclTotal * ($discPct / 100), 2);
}
// grand_total = sum of all inclusive prices - discount
// (sub + gstAmt = sum of all inclusive prices, same as summing item['price'])
$inclTotal = 0; foreach ($cart as $ci) $inclTotal += (float)$ci['price'];
$inclTotal = round($inclTotal, 2);
$grand     = max(0, round($inclTotal - $discAmt, 2));

$custId    = $_SESSION['cart_cust'] ?? null;
$pmeth     = post('payment_method', $cart[0]['payment_method'] ?? 'cash');
$upiRef    = post('upi_ref', $cart[0]['upi_ref'] ?? '');
$chequeNo  = post('cheque_no', $cart[0]['cheque_no'] ?? '');
$chequeBank= post('cheque_bank', $cart[0]['cheque_bank'] ?? '');
$chequeDate= post('cheque_date', $cart[0]['cheque_date'] ?? '') ?: null;

// Insert sale
$saleId = $db->insert('sales', [
    'customer_id'     => $custId,
    'sale_date'       => $today,
    'total_amount'    => $sub,
    'gst_amount'      => $gstAmt,
    'discount_amount' => $discAmt,
    'discount_id'     => $discId,
    'grand_total'     => $grand,
    'payment_method'  => $pmeth,
    'upi_ref'         => $upiRef,
    'cheque_no'       => $chequeNo,
    'cheque_bank'     => $chequeBank,
    'cheque_date'     => $chequeDate,
    'created_by'      => $_SESSION['admin_id'] ?? 0,
    'created_at'      => date('Y-m-d H:i:s'),
]);

// Insert items and reduce stock
foreach ($cart as $item) {
    $db->insert('sales_items', [
        'sale_id'     => $saleId,
        'medicine_id' => $item['medicine_id'],
        'batch_id'    => $item['batch_id'],
        'quantity'    => $item['qty'],
        'mrp'         => $item['mrp'],
        'price'       => $item['price'],
    ]);
    $batch = $db->findOne('batches', fn($b) => (int)$b['id'] === (int)$item['batch_id']);
    if ($batch) {
        $batchQtyOld = (int)($batch['quantity'] ?? 0);
        $batchQtyNew = max(0, $batchQtyOld - (int)$item['qty']);
        $medId_s = (int)$item['medicine_id'];
        // Capture total BEFORE update
        $totalMedBefore = (int)array_sum(array_column($db->find('batches', fn($b) => (int)($b['medicine_id']??0) === $medId_s), 'quantity'));
        $totalMedAfter  = max(0, $totalMedBefore - (int)$item['qty']);
        $db->update('batches', fn($b) => (int)$b['id'] === (int)$item['batch_id'], [
            'quantity'   => $batchQtyNew,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $db->insert('stock_adjustments', [
            'batch_id'   => (int)$item['batch_id'],
            'medicine_id'=> $medId_s,
            'type'       => 'remove',
            'quantity'   => (int)$item['qty'],
            'reason'     => 'Sale: ' . invNo($saleId),
            'old_qty'    => $totalMedBefore,
            'new_qty'    => max(0, $totalMedAfter),
            'user_id'    => $_SESSION['admin_id'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

// Customer log + email receipt
if ($custId) {
    $db->insert('customer_purchase_log', ['customer_id'=>$custId,'sale_id'=>$saleId,'amount'=>$grand,'date'=>$today]);
    $cust = $db->findOne('customers', fn($c) => $c['id'] === $custId);
    if ($cust && !empty($cust['email'])) {
        $store    = storeName();
        $itemRows = '';
        foreach ($cart as $ci) {
            $itemRows .= "<tr><td style='padding:6px 10px;border-bottom:1px solid #e4e7ed'>" . e($ci['name']) . "</td><td style='padding:6px 10px;border-bottom:1px solid #e4e7ed;text-align:center'>" . $ci['qty'] . "</td><td style='padding:6px 10px;border-bottom:1px solid #e4e7ed;text-align:right'>" . money($ci['price']) . "</td></tr>";
        }
        $body = mailTemplate("Purchase Receipt", "
            <p>Dear <strong>" . e($cust['name']) . "</strong>,</p>
            <p>Thank you for your purchase at <strong>{$store}</strong>.</p>
            <table style='width:100%;border-collapse:collapse;margin:12px 0;font-size:13px'>
              <thead><tr style='background:#f1f3f6'><th style='padding:6px 10px;text-align:left'>Medicine</th><th style='padding:6px 10px'>Qty</th><th style='padding:6px 10px;text-align:right'>Amount</th></tr></thead>
              <tbody>{$itemRows}</tbody>
            </table>
            <p>Subtotal: <strong>" . money($sub) . "</strong><br>GST: <strong>" . money($gstAmt) . "</strong>" . ($discAmt > 0 ? "<br>Discount: <strong>-" . money($discAmt) . "</strong>" : "") . "<br><strong style='font-size:15px'>Grand Total: " . money($grand) . "</strong></p>
            <p>Invoice: <strong>" . invNo($saleId) . "</strong> | Payment: " . ucfirst($pmeth) . "</p>
            <p>Visit us again soon!</p>");
        sendMail($cust['email'], "Purchase Receipt — " . invNo($saleId) . " | {$store}", $body);
    }
}

// Get discount name for invoice
$discName = '';
if ($discId) {
    $disc = $db->findOne('discounts', fn($d) => $d['id'] === $discId);
    $discName = $disc ? $disc['name'] . ' (' . ($disc['type']==='percent' ? $disc['value'].'%' : money($disc['value'])) . ')' : '';
}

$_SESSION['last_invoice'] = [
    'sale_id'    => $saleId,
    'cart'       => $cart,
    'sub'        => $sub,
    'gst'        => $gstAmt,
    'disc'       => $discAmt,
    'disc_name'  => $discName,
    'disc_pct'   => $discPct,
    'grand'      => $grand,
    'customer_id'=> $custId,
    'payment'    => $pmeth,
    'upi_ref'    => $upiRef,
    'cheque_no'  => $chequeNo,
    'cheque_bank'=> $chequeBank,
    'cheque_date'=> $chequeDate ?: '',
    'date'       => $today,
    'time'       => date('h:i A'),
    'datetime'   => date('Y-m-d H:i:s'),
];
$_SESSION['cart'] = [];
unset($_SESSION['cart_cust']);

header('Location: index.php?p=invoice'); exit;
