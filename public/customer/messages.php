<?php
/**
 * DRXStore - Patient Portal: Messages & Prescriptions
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php';
require_once ROOT.'/views/layout_portal.php';
requireCustomer();

$cid = $_SESSION['cust_id'] ?? 0;
$uploadDir = DATA_DIR . '/uploads/prescriptions/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $msg     = post('message');
    $b64data = post('file_b64');     // base64 file data (from JS FileReader)
    $b64name = post('file_b64_name'); // original filename
    $hasFile = !empty($b64data) && !empty($b64name);

    // Also support normal file upload as fallback
    $normalUpload = isset($_FILES['prescription']) && $_FILES['prescription']['error'] === UPLOAD_ERR_OK;

    if (!$msg && !$hasFile && !$normalUpload) {
        $errors[] = 'Please write a message or upload a prescription.';
    }

    $filePath = ''; $fileName = ''; $fileType = '';

    if (($hasFile || $normalUpload) && empty($errors)) {
        $allowed = ['jpg','jpeg','png','pdf','heic','webp'];

        if ($hasFile) {
            // Base64 path — file was read into memory by JS, safe from ERR_UPLOAD_FILE_CHANGED
            $ext = strtolower(pathinfo($b64name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $errors[] = 'Only JPG, PNG, PDF files allowed.';
            } else {
                // Strip data URI prefix if present (data:image/jpeg;base64,...)
                $raw = preg_replace('/^data:[^;]+;base64,/', '', $b64data);
                $decoded = base64_decode($raw, true);
                if (!$decoded) {
                    $errors[] = 'File data is corrupt. Please try again.';
                } elseif (strlen($decoded) > 10 * 1024 * 1024) {
                    $errors[] = 'File too large. Max 10 MB.';
                } else {
                    $newName  = 'rx_' . $cid . '_' . time() . '.' . $ext;
                    $destPath = $uploadDir . $newName;
                    if (file_put_contents($destPath, $decoded) !== false) {
                        $filePath = $newName;
                        $fileName = basename($b64name);
                        $fileType = $ext;
                    } else {
                        $errors[] = 'File save failed. Check server permissions.';
                    }
                }
            }
        } else {
            // Normal upload fallback
            $file = $_FILES['prescription'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $errors[] = 'Only JPG, PNG, PDF files allowed.';
            } elseif ($file['size'] > 10 * 1024 * 1024) {
                $errors[] = 'File too large. Max 10 MB.';
            } else {
                $newName  = 'rx_' . $cid . '_' . time() . '.' . $ext;
                $destPath = $uploadDir . $newName;
                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $filePath = $newName;
                    $fileName = $file['name'];
                    $fileType = $ext;
                } else {
                    $errors[] = 'File upload failed. Check server permissions.';
                }
            }
        }
    }

    if (empty($errors)) {
        $db->insert('patient_messages', [
            'customer_id' => $cid,
            'direction'   => 'in',
            'message'     => $msg,
            'file_path'   => $filePath,
            'file_name'   => $fileName,
            'file_type'   => $fileType,
            'is_read'     => 0,
            'created_by'  => 0,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        setFlash('success', 'Message sent successfully!');
        header('Location: index.php?p=cust_messages'); exit;
    }
}

$messages = $db->find('patient_messages', fn($m) => ($m['customer_id']??0) === $cid);
usort($messages, fn($a,$b) => ($a['id']??0) <=> ($b['id']??0));
$db->update('patient_messages',
    fn($m) => ($m['customer_id']??0) === $cid && ($m['direction']??'in') === 'out' && !($m['is_read']??true),
    ['is_read' => 1]
);

$navItems = [
    'cust_dash'     => ['icon' => 'grid',    'label' => 'My Dashboard'],
    'cust_orders'   => ['icon' => 'orders',  'label' => 'My Orders'],
    'cust_messages' => ['icon' => 'mail',    'label' => 'Messages'],
    'cust_return'   => ['icon' => 'return',  'label' => 'Return Request'],
    'cust_profile'  => ['icon' => 'user',    'label' => 'My Profile'],
];
portalHeader('Messages & Prescriptions', 'customer', 'cust_messages', $navItems, ['name' => 'customer_name']);
?>
<div class="page-hdr">
  <div><div class="page-title">Messages &amp; Prescriptions</div>
    <div class="page-sub">Send prescriptions and messages to the pharmacy</div>
  </div>
</div>

<!-- Messages thread -->
<div class="card" style="margin-bottom:16px">
  <div class="card-hdr"><div class="card-title">Conversation with <?=e(storeName())?></div></div>
  <div style="padding:16px;display:flex;flex-direction:column;gap:10px;min-height:200px;max-height:460px;overflow-y:auto" id="msgThread">
    <?php if(empty($messages)): ?>
      <div style="text-align:center;padding:40px;color:var(--g5);font-size:.85rem">
        No messages yet. Send a message or upload a prescription below.
      </div>
    <?php else: foreach($messages as $m):
      $isOut  = ($m['direction']??'in') === 'out';
      $hasFile= !empty($m['file_path']);
    ?>
    <div style="display:flex;<?=$isOut?'justify-content:flex-end':''?>">
      <div style="max-width:75%;background:<?=$isOut?'#581c87':'var(--g1)'?>;color:<?=$isOut?'#fff':'var(--g8)'?>;padding:10px 14px;border-radius:<?=$isOut?'14px 14px 2px 14px':'14px 14px 14px 2px'?>;border:1px solid <?=$isOut?'#581c87':'var(--g3)'?>">
        <?php if(!$isOut): ?><div style="font-size:.7rem;font-weight:700;color:#581c87;margin-bottom:4px">You</div><?php endif; ?>
        <?php if($isOut):  ?><div style="font-size:.7rem;font-weight:700;color:rgba(255,255,255,.7);margin-bottom:4px"><?=e(storeName())?></div><?php endif; ?>
        <?php if($hasFile): ?>
        <div style="margin-bottom:6px;padding:6px 10px;background:<?=$isOut?'rgba(255,255,255,.15)':'var(--pur-lt)'?>;border-radius:6px;font-size:.78rem">
          <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;vertical-align:middle"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <a href="<?=url('serve_file') . '&f=' . urlencode(basename($m['file_path']??''))?>" target="_blank" style="color:<?=$isOut?'#fff':'#581c87'?>;font-weight:600"><?=e($m['file_name']??'Prescription')?></a>
        </div>
        <?php endif; ?>
        <?php if($m['message']??''): ?>
        <div style="font-size:.84rem;line-height:1.5"><?=nl2br(e($m['message']??''))?></div>
        <?php endif; ?>
        <div style="font-size:.66rem;opacity:.6;margin-top:4px;text-align:right"><?=dateTimeF($m['created_at']??'')?></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- Send message / upload prescription -->
<div class="card">
  <div class="card-hdr"><div class="card-title">Send Message or Upload Prescription</div></div>
  <div class="card-body">
    <?php foreach($errors as $er): ?>
      <div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div>
    <?php endforeach; ?>
    <form method="POST" enctype="multipart/form-data" id="msgForm">
      <?=csrfField()?>
      <!-- Hidden fields for base64 file data (populated by JS to avoid ERR_UPLOAD_FILE_CHANGED on mobile) -->
      <input type="hidden" name="file_b64" id="file_b64">
      <input type="hidden" name="file_b64_name" id="file_b64_name">
      <div class="form-group">
        <label class="form-label">Message <span class="text-muted" style="font-weight:400">(optional if uploading prescription)</span></label>
        <textarea class="form-control" name="message" rows="3" placeholder="Ask about a medicine, request a refill, or add notes with your prescription…"><?=e(post('message'))?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Upload Prescription <span class="text-muted" style="font-weight:400">(optional)</span></label>
        <input class="form-control" type="file" name="prescription" id="prescriptionFile" accept="image/*,.pdf,.heic">
        <div class="form-hint" id="fileHint">Accepted: JPG, PNG, PDF, HEIC &mdash; Max 10 MB</div>
        <!-- Preview for images -->
        <div id="filePreview" style="display:none;margin-top:8px">
          <img id="previewImg" src="" alt="Preview" style="max-width:100%;max-height:160px;border-radius:8px;border:1px solid var(--g3)">
          <div id="previewName" style="font-size:.8rem;color:var(--g5);margin-top:4px"></div>
          <button type="button" onclick="clearFile()" class="btn btn-ghost btn-sm" style="margin-top:4px">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Remove
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" id="sendBtn">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:5px;vertical-align:middle"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Send Message
      </button>
    </form>
  </div>
</div>

<script>
var thread = document.getElementById('msgThread');
if (thread) thread.scrollTop = thread.scrollHeight;

var fileInput   = document.getElementById('prescriptionFile');
var b64Input    = document.getElementById('file_b64');
var b64NameInput= document.getElementById('file_b64_name');
var fileHint    = document.getElementById('fileHint');
var filePreview = document.getElementById('filePreview');
var previewImg  = document.getElementById('previewImg');
var previewName = document.getElementById('previewName');

// Read file into base64 immediately on selection — prevents ERR_UPLOAD_FILE_CHANGED
// which happens on Android/Chrome when camera moves the temp file before form submits
fileInput.addEventListener('change', function() {
  var file = this.files[0];
  if (!file) return;
  var reader = new FileReader();
  fileHint.textContent = 'Reading file…';
  reader.onload = function(e) {
    b64Input.value     = e.target.result; // full data URI with base64
    b64NameInput.value = file.name;
    fileHint.textContent = 'File ready: ' + file.name + ' (' + (file.size/1024).toFixed(1) + ' KB)';
    // Show preview for images
    if (file.type.startsWith('image/')) {
      previewImg.src = e.target.result;
      previewName.textContent = file.name;
      filePreview.style.display = 'block';
    } else {
      previewImg.src = '';
      previewName.textContent = file.name + ' (PDF/Document)';
      previewImg.style.display = 'none';
      filePreview.style.display = 'block';
    }
  };
  reader.onerror = function() {
    fileHint.textContent = 'Could not read file. Please try again.';
  };
  reader.readAsDataURL(file);
});

function clearFile() {
  fileInput.value     = '';
  b64Input.value      = '';
  b64NameInput.value  = '';
  fileHint.textContent= 'Accepted: JPG, PNG, PDF, HEIC — Max 10 MB';
  filePreview.style.display = 'none';
  previewImg.src = '';
}

// Show loading state on submit
document.getElementById('msgForm').addEventListener('submit', function() {
  var btn = document.getElementById('sendBtn');
  btn.disabled = true;
  btn.textContent = 'Sending…';
});
</script>

<?php portalFooter(); ?>
