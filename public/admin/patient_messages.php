<?php
/**
 * DRXStore - Patient Messages & Prescriptions (Admin/Staff)
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php';
require_once ROOT.'/views/layout_admin.php';
requireStaff();

$uploadDir = DATA_DIR . '/uploads/prescriptions/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$errors = [];

// Reply to patient
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act   = post('action');
    $cid   = postInt('customer_id');
    $msg   = post('message');

    if ($act === 'reply' && $cid) {
        if (!$msg) { $errors[] = 'Message cannot be empty.'; }
        else {
            $db->insert('patient_messages', [
                'customer_id' => $cid,
                'direction'   => 'out',   // store → patient
                'message'     => $msg,
                'is_read'     => 0,
                'created_by'  => $_SESSION['admin_id'] ?? 0,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
            // Mark all incoming messages from this patient as read
            $db->update('patient_messages',
                fn($m) => ($m['customer_id'] ?? 0) === $cid && ($m['direction'] ?? 'in') === 'in' && !($m['is_read'] ?? false),
                ['is_read' => 1]
            );
            setFlash('success', 'Message sent to patient.');
            header('Location: index.php?p=patient_messages&cid=' . $cid); exit;
        }
    }

    if ($act === 'mark_read' && $cid) {
        $db->update('patient_messages',
            fn($m) => ($m['customer_id'] ?? 0) === $cid && !($m['is_read'] ?? false),
            ['is_read' => 1]
        );
        header('Location: index.php?p=patient_messages&cid=' . $cid); exit;
    }

    if ($act === 'delete_message') {
        $msgId = postInt('msg_id');
        if ($msgId) {
            $msg = $db->findOne('patient_messages', fn($m) => (int)$m['id'] === $msgId);
            $cid = $msg ? (int)($msg['customer_id'] ?? 0) : 0;
            $db->deleteById('patient_messages', $msgId);
            setFlash('success', 'Message deleted.');
        }
        header('Location: index.php?p=patient_messages' . ($cid ? '&cid=' . $cid : '')); exit;
    }

    if ($act === 'delete_all_messages' && $cid) {
        $db->delete('patient_messages', fn($m) => (int)($m['customer_id'] ?? 0) === $cid);
        setFlash('success', 'All messages deleted for this patient.');
        header('Location: index.php?p=patient_messages&cid=' . $cid); exit;
    }
}

// Selected patient
$selCid  = getInt('cid');
$customers = $db->table('customers');
usort($customers, fn($a,$b) => strcasecmp($a['name']??'', $b['name']??''));

// Count unread per customer
$allMsgs   = $db->table('patient_messages');
$unreadMap = [];
$latestMap = [];
foreach ($allMsgs as $m) {
    $c = $m['customer_id'] ?? 0;
    if (!isset($latestMap[$c]) || $m['id'] > $latestMap[$c]['id']) $latestMap[$c] = $m;
    if (($m['direction']??'in') === 'in' && !($m['is_read']??false)) {
        $unreadMap[$c] = ($unreadMap[$c] ?? 0) + 1;
    }
}

$conversation = [];
$selCustomer  = null;
if ($selCid) {
    $selCustomer  = $db->findOne('customers', fn($c) => $c['id'] === $selCid);
    $conversation = $db->find('patient_messages', fn($m) => ($m['customer_id']??0) === $selCid);
    usort($conversation, fn($a,$b) => ($a['id']??0) <=> ($b['id']??0));
    // Auto-mark incoming as read when viewing
    $db->update('patient_messages',
        fn($m) => ($m['customer_id']??0) === $selCid && ($m['direction']??'in') === 'in' && !($m['is_read']??false),
        ['is_read' => 1]
    );
}

$totalUnread = array_sum($unreadMap);
adminHeader('Patient Messages', 'patient_messages');
?>
<div class="page-hdr">
  <div><div class="page-title">Patient Messages &amp; Prescriptions</div>
    <div class="page-sub"><?=$totalUnread?> unread message<?=$totalUnread!=1?'s':''?></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:280px 1fr;gap:16px;min-height:520px">

  <!-- Patient list -->
  <div class="card" style="overflow:hidden">
    <div class="card-hdr"><div class="card-title">Patients</div></div>
    <div style="overflow-y:auto;max-height:600px">
      <?php if(empty($customers)): ?>
      <div style="padding:20px;text-align:center;color:var(--g5);font-size:.82rem">No patients yet.</div>
      <?php else: foreach($customers as $cu):
        $unread  = $unreadMap[$cu['id']] ?? 0;
        $latest  = $latestMap[$cu['id']] ?? null;
        $isActive= $selCid === $cu['id'];
      ?>
      <a href="index.php?p=patient_messages&cid=<?=$cu['id']?>"
         style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--g3);text-decoration:none;background:<?=$isActive?'var(--navy-lt)':'#fff'?>;transition:background .15s"
         onmouseover="this.style.background='var(--g1)'"
         onmouseout="this.style.background='<?=$isActive?'var(--navy-lt)':'#fff'?>'">
        <div style="width:34px;height:34px;border-radius:50%;background:<?=$isActive?'var(--navy)':'var(--g3)'?>;color:<?=$isActive?'#fff':'var(--g7)'?>;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex-shrink:0">
          <?=strtoupper(substr($cu['name']??'?',0,1))?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-size:.82rem;font-weight:600;color:<?=$isActive?'var(--navy)':'var(--g8)'?>"><?=e($cu['name']??'')?></div>
          <?php if($latest): ?>
          <div style="font-size:.7rem;color:var(--g5);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?=e(substr($latest['message']??'',0,32))?><?=strlen($latest['message']??'')>32?'…':''?>
          </div>
          <?php endif; ?>
        </div>
        <?php if($unread > 0): ?>
        <span style="background:var(--red);color:#fff;font-size:.6rem;font-weight:700;padding:2px 6px;border-radius:9999px;flex-shrink:0"><?=$unread?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Conversation panel -->
  <div class="card" style="display:flex;flex-direction:column;overflow:hidden">
    <?php if($selCustomer): ?>
    <div class="card-hdr">
      <div>
        <div class="card-title"><?=e($selCustomer['name']??'')?></div>
        <div style="font-size:.72rem;color:var(--g5)"><?=e($selCustomer['email']??'')?> &nbsp;|&nbsp; <?=e($selCustomer['phone']??'')?></div>
      </div>
      <div class="flex gap-1">
        <a href="index.php?p=customers&action=edit&id=<?=$selCid?>" class="btn btn-ghost btn-sm">View Profile</a>
        <form method="POST" onsubmit="return confirm('Delete ALL messages for this patient?')">
          <?=csrfField()?>
          <input type="hidden" name="action" value="delete_all_messages">
          <input type="hidden" name="customer_id" value="<?=$selCid?>">
          <button type="submit" class="btn btn-danger btn-sm">
            <svg viewBox="0 0 16 16" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" style="vertical-align:middle;margin-right:2px"><polyline points="2 4 14 4"/><path d="M5 4V2h6v2M6 7v5M10 7v5M3 4l1 10h8l1-10"/></svg>Clear All
          </button>
        </form>
      </div>
    </div>

    <!-- Messages -->
    <div style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;min-height:300px;max-height:400px" id="msgBox">
      <?php if(empty($conversation)): ?>
        <div style="text-align:center;color:var(--g5);padding:40px;font-size:.85rem">No messages yet. Start the conversation below.</div>
      <?php else: foreach($conversation as $m):
        $isOut  = ($m['direction']??'in') === 'out';
        $hasFile= !empty($m['file_path']);
      ?>
      <div style="display:flex;flex-direction:column;<?=$isOut?'align-items:flex-end':''?>">
        <div style="max-width:70%;background:<?=$isOut?'var(--navy)':'var(--g1)'?>;color:<?=$isOut?'#fff':'var(--g8)'?>;padding:10px 14px;border-radius:<?=$isOut?'14px 14px 2px 14px':'14px 14px 14px 2px'?>;border:1px solid <?=$isOut?'var(--navy)':'var(--g3)'?>">
          <?php if($hasFile): ?>
          <div style="margin-bottom:6px;padding:6px 10px;background:<?=$isOut?'rgba(255,255,255,.15)':'var(--accent-lt)'?>;border-radius:6px;font-size:.78rem">
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <strong>Prescription:</strong>
            <a href="<?=url('serve_file') . '&f=' . urlencode(basename($m['file_path']??''))?>" target="_blank" style="color:<?=$isOut?'#fff':'var(--navy)'?>"><?=e($m['file_name']??'File')?></a>
          </div>
          <?php endif; ?>
          <?php if($m['message']??''): ?>
          <div style="font-size:.83rem;line-height:1.5"><?=nl2br(e($m['message']??''))?></div>
          <?php endif; ?>
          <div style="font-size:.65rem;opacity:.65;margin-top:4px;text-align:right"><?=dateTimeF($m['created_at']??'')?> <?=$isOut?'&middot; Staff':''?></div>
        </div>
        <form method="POST" style="margin-top:2px" onsubmit="return confirm('Delete this message?')">
          <?=csrfField()?>
          <input type="hidden" name="action" value="delete_message">
          <input type="hidden" name="msg_id" value="<?=$m['id']?>">
          <input type="hidden" name="customer_id" value="<?=$selCid?>">
          <button type="submit" style="background:none;border:none;font-size:.65rem;color:var(--red);opacity:.6;cursor:pointer;padding:0;display:inline-flex;align-items:center;gap:2px"><svg viewBox="0 0 16 16" width="11" height="11" fill="none" stroke="currentColor" stroke-width="1.8" style="vertical-align:middle"><polyline points="2 4 14 4"/><path d="M5 4V2h6v2M6 7v5M10 7v5M3 4l1 10h8l1-10"/></svg> Delete</button>
        </form>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Reply form -->
    <div style="padding:12px 16px;border-top:1px solid var(--g3)">
      <?php foreach($errors as $er): ?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach; ?>
      <form method="POST" style="display:flex;gap:8px;align-items:flex-end">
        <?=csrfField()?>
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="customer_id" value="<?=$selCid?>">
        <textarea name="message" class="form-control" rows="2" placeholder="Type a message to <?=e($selCustomer['name']??'patient')?> ..." style="flex:1;resize:none" required></textarea>
        <button type="submit" class="btn btn-primary" style="height:56px;white-space:nowrap">Send</button>
      </form>
    </div>

    <?php else: ?>
    <div style="flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:10px;color:var(--g5);padding:40px">
      <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      <p style="font-size:.88rem">Select a patient to view messages</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
// Scroll to bottom of message box
var msgBox = document.getElementById('msgBox');
if (msgBox) msgBox.scrollTop = msgBox.scrollHeight;
</script>
<?php adminFooter(); ?>
