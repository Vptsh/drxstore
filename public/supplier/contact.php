<?php
/**
 * DRXStore - Supplier Portal: Contact Store
 * Shows sent messages AND admin replies in a thread view
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php';
require_once ROOT.'/views/layout_portal.php';
requireSupplier();

$sid = $_SESSION['supplier_id'] ?? 0;
$su  = $db->findOne('supplier_users', fn($u) => $u['id'] === $sid);
$sup = $db->findOne('suppliers',      fn($s) => $s['id'] === ($su['supplier_id'] ?? 0));
$supId = $su['supplier_id'] ?? 0;

$sent   = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $subject = post('subject');
    $message = post('message');
    if (!$subject) $errors[] = 'Subject required.';
    if (!$message) $errors[] = 'Message required.';
    if (empty($errors)) {
        $db->insert('supplier_messages', [
            'supplier_id'   => $supId,
            'supplier_name' => $sup['name'] ?? '',
            'sender_email'  => $su['email'] ?? '',
            'subject'       => $subject,
            'message'       => $message,
            'direction'     => 'in',
            'status'        => 'unread',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        // Email the store
        $storeEmail = storeEmail();
        $storeName  = storeName();
        $body = mailWrap(
            'Supplier Inquiry — ' . e($sup['name'] ?? 'Supplier'),
            '<p><strong>From:</strong> ' . e($sup['name'] ?? '') . ' (Supplier)</p>'
            . '<p><strong>Subject:</strong> ' . e($subject) . '</p>'
            . '<p><strong>Email:</strong> ' . e($su['email'] ?? '') . '</p>'
            . '<hr><p>' . nl2br(e($message)) . '</p>'
        );
        sendMail($storeEmail, '[Supplier Inquiry] ' . e($subject) . ' — ' . e($sup['name'] ?? ''), $body);
        $sent = true;
    }
}

// Load all messages for this supplier (both sent by supplier and replied by store)
$messages = $db->find('supplier_messages', fn($m) => ($m['supplier_id'] ?? 0) === $supId);
usort($messages, fn($a, $b) => ($a['id'] ?? 0) <=> ($b['id'] ?? 0));

$navItems = [
    'sup_dash'    => ['icon' => 'grid',   'label' => 'Dashboard'],
    'sup_orders'  => ['icon' => 'orders', 'label' => 'Purchase Orders'],
    'sup_profile' => ['icon' => 'user',   'label' => 'My Profile'],
    'sup_contact' => ['icon' => 'mail',   'label' => 'Contact Store'],
];
portalHeader('Contact Store', 'supplier', 'sup_contact', $navItems, ['name' => 'supplier_company']);
?>
<div class="page-hdr">
  <div><div class="page-title">Contact Store</div>
    <div class="page-sub">Send messages and view replies from <?=e(storeName())?></div>
  </div>
</div>

<?php if ($sent): ?>
<div class="alert alert-success"><span class="alert-body">Your message has been sent successfully! The pharmacy will reply here.</span></div>
<?php endif; ?>
<?php foreach ($errors as $er): ?>
<div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div>
<?php endforeach; ?>

<!-- Message Thread -->
<?php if (!empty($messages)): ?>
<div class="card mb-2">
  <div class="card-hdr"><div class="card-title">Message History</div>
    <span class="chip chip-blue"><?=count($messages)?> message<?=count($messages)!=1?'s':''?></span>
  </div>
  <div style="padding:16px;display:flex;flex-direction:column;gap:14px;max-height:480px;overflow-y:auto" id="msgThread">
    <?php foreach ($messages as $msg):
      $isOut = ($msg['direction'] ?? 'in') === 'out'; // 'out' = store replied
    ?>
    <!-- Supplier sent message -->
    <div style="display:flex;<?=$isOut?'justify-content:flex-end':''?>">
      <div style="max-width:78%;background:<?=$isOut?'var(--navy)':'var(--g1)'?>;color:<?=$isOut?'#fff':'var(--g9)'?>;padding:12px 16px;border-radius:<?=$isOut?'14px 14px 2px 14px':'14px 14px 14px 2px'?>;border:1px solid <?=$isOut?'var(--navy)':'var(--g3)'?>">
        <?php if (!$isOut): ?>
        <div style="font-size:.7rem;font-weight:700;color:var(--navy);margin-bottom:4px;text-transform:uppercase;letter-spacing:.04em">
          You &mdash; <?=e($msg['subject'] ?? '')?>
        </div>
        <?php else: ?>
        <div style="font-size:.7rem;font-weight:700;color:rgba(255,255,255,.6);margin-bottom:4px;text-transform:uppercase;letter-spacing:.04em">
          <?=e(storeName())?> replied
        </div>
        <?php endif; ?>
        <div style="font-size:.85rem;line-height:1.55"><?=nl2br(e($msg['message'] ?? ''))?></div>
        <div style="font-size:.66rem;opacity:.55;margin-top:6px;text-align:right">
          <?=dateTimeF($msg['created_at'] ?? '')?>
        </div>
      </div>
    </div>

    <?php endforeach; ?>
  </div>
</div>
<script>var t=document.getElementById('msgThread');if(t)t.scrollTop=t.scrollHeight;</script>
<?php endif; ?>

<!-- Send New Message -->
<div style="max-width:640px">
  <div class="card"><div class="card-hdr"><div class="card-title">Send New Message</div></div>
    <div class="card-body">
      <form method="POST"><?=csrfField()?>
        <div class="form-group"><label class="form-label">Subject <span class="req">*</span></label>
          <select class="form-control" name="subject">
            <option value="Order Inquiry">Order Inquiry</option>
            <option value="Payment Query">Payment Query</option>
            <option value="Delivery Update">Delivery Update</option>
            <option value="Product Availability">Product Availability</option>
            <option value="Request New Order">Request New Order</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Message <span class="req">*</span></label>
          <textarea class="form-control" name="message" rows="4" required placeholder="Type your message here…"></textarea>
        </div>
        <button type="submit" class="btn btn-success">Send Message</button>
      </form>
      <div style="margin-top:14px;padding:10px 14px;background:var(--g1);border-radius:var(--rl);font-size:.82rem">
        <strong>Direct Email:</strong>
        <a href="mailto:<?=e(storeEmail())?>"><?=e(storeEmail())?></a>
      </div>
    </div>
  </div>
</div>
<?php portalFooter(); ?>
