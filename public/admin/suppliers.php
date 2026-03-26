<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
/**
 * DRXStore - Suppliers | Developed by Vineet | psvineet@zohomail.in
 */
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_admin.php'; requireAdmin();
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf();
    $act=post('action');
    $id=postInt('id');

    if(in_array($act,['add','edit'],true)){
        $name=post('name'); $contact=post('contact'); $phone=post('phone'); $email=post('email'); $addr=post('address'); $gst=post('gst_no'); $dl=post('dl_no');
        $sup_user=post('sup_username'); $sup_pass=post('sup_password');
        if(!$name)$errors[]='Name required.';
        if(!$phone)$errors[]='Phone required.';
        if(empty($errors)){
            $d=['name'=>$name,'contact'=>$contact,'phone'=>$phone,'email'=>$email,'address'=>$addr,'gst_no'=>$gst,'dl_no'=>$dl,'updated_at'=>date('Y-m-d H:i:s')];
            if($act==='edit'&&$id){
                $db->update('suppliers',fn($s)=>$s['id']===$id,$d);
                setFlash('success','Updated.');
            } else {
                $d['created_at']=date('Y-m-d H:i:s');
                $sid=$db->insert('suppliers',$d);
                if($sup_user&&$sup_pass){
                    $db->insert('supplier_users',[
                        'supplier_id'=>$sid,
                        'username'=>$sup_user,
                        'password'=>password_hash($sup_pass,PASSWORD_BCRYPT),
                        'email'=>$email,
                        'active'=>1,
                        'created_at'=>date('Y-m-d H:i:s'),
                    ]);
                    if($email){
                        $store=storeName(); $storeEmail=storeEmail();
                        $body=mailWrap("Supplier Account Created","<p>Dear {$name},</p><p>Your supplier portal account has been created for <strong>{$store}</strong>.</p><p><strong>Login URL:</strong> <code>".($_SERVER['HTTP_HOST']??'')."/index.php?p=sup_login</code><br><strong>Username:</strong> <code>{$sup_user}</code><br><strong>Password:</strong> <code>{$sup_pass}</code></p><p>Please change your password after first login.</p><p>Contact us: <a href='mailto:".$storeEmail."'>".$storeEmail."</a></p>");
                        sendMail($email,"Your Supplier Portal Account — {$store}",$body);
                    }
                }
                setFlash('success','"'.$name.'" added'.($sup_user?' with portal account':'').'.');
            }
            header('Location: index.php?p=suppliers');exit;
        }
    } elseif($act==='delete_supplier' && $id){
        $db->delete('suppliers',fn($s)=>$s['id']===$id);
        $db->delete('supplier_users',fn($u)=>($u['supplier_id']??0)===$id);
        setFlash('success','Deleted.');
        header('Location: index.php?p=suppliers');exit;
    } elseif($act==='read_msg' && ($msgId=postInt('msg_id'))){
        $db->update('supplier_messages', fn($m) => $m['id'] === $msgId, ['status' => 'read']);
        setFlash('success', 'Marked as read.');
        header('Location: index.php?p=suppliers'); exit;
    } elseif($act==='del_msg' && ($msgId=postInt('msg_id'))){
        $db->delete('supplier_messages', fn($m) => $m['id'] === $msgId);
        setFlash('success', 'Message deleted.');
        header('Location: index.php?p=suppliers'); exit;
    } elseif($act==='reply_sup' && ($msgId=postInt('msg_id'))){
        $reply = trim(post('reply_text'));
        $supMsg = $db->findOne('supplier_messages', fn($m) => $m['id'] === $msgId);
        if ($supMsg && $reply) {
            $supUser = $db->findOne('supplier_users', fn($u) => ($u['supplier_id'] ?? 0) === ($supMsg['supplier_id'] ?? 0));
            $toEmail = $supMsg['sender_email'] ?? ($supUser['email'] ?? null);
            if ($toEmail) {
                $bqStyle = 'border-left:3px solid #0a2342;padding:8px 14px;margin:10px 0;background:#f8f9fb';
                $body = mailWrap(
                    'Reply from ' . storeName(),
                    '<p>Dear ' . e($supMsg['supplier_name'] ?? 'Supplier') . ',</p>'
                    . '<p>You have a reply to your message: <em>' . e($supMsg['subject'] ?? '') . '</em></p>'
                    . '<blockquote style="' . $bqStyle . '">' . nl2br(e($reply)) . '</blockquote>'
                    . '<p>Original message:<br>' . nl2br(e($supMsg['message'] ?? '')) . '</p>'
                );
                sendMail($toEmail, "Reply: " . ($supMsg['subject'] ?? 'Your message'), $body);
            }
            $db->update('supplier_messages', fn($m) => $m['id'] === $msgId, [
                'status'     => 'replied',
                'reply'      => $reply,
                'replied_at' => date('Y-m-d H:i:s'),
            ]);
            $db->insert('supplier_messages', [
                'supplier_id'   => $supMsg['supplier_id'] ?? 0,
                'supplier_name' => $supMsg['supplier_name'] ?? '',
                'sender_email'  => storeEmail(),
                'subject'       => 'Re: ' . ($supMsg['subject'] ?? ''),
                'message'       => $reply,
                'direction'     => 'out',
                'status'        => 'read',
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
            setFlash('success', 'Reply sent to supplier.');
        }
        header('Location: index.php?p=suppliers'); exit;
    } elseif($act==='reply_supplier'){
        $sup_id  = postInt('reply_supplier_id');
        $reply   = post('reply_message');
        $sup_u   = $db->findOne('supplier_users', fn($u) => ($u['supplier_id']??0) === $sup_id);
        $sup_c   = $db->findOne('suppliers', fn($s) => $s['id'] === $sup_id);
        if ($reply && $sup_c) {
            $db->insert('supplier_messages', [
                'supplier_id'   => $sup_id,
                'supplier_name' => $sup_c['name'] ?? '',
                'sender_email'  => storeEmail(),
                'subject'       => 'Reply from ' . storeName(),
                'message'       => $reply,
                'direction'     => 'out',
                'status'        => 'read',
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
            $email = $sup_u['email'] ?? ($sup_c['email'] ?? '');
            if ($email) {
                $body = mailWrap('Reply from ' . storeName(), '<p>Dear ' . e($sup_c['name']??'Supplier') . ',</p><p>' . nl2br(e($reply)) . '</p><p>To reply, visit your supplier portal and use Contact Store.</p>');
                sendMail($email, 'Message from ' . storeName(), $body);
            }
            setFlash('success', 'Reply sent to supplier.');
        }
        header('Location: index.php?p=suppliers'); exit;
    }
}
$sups=$db->table('suppliers'); usort($sups,fn($a,$b)=>strcasecmp($a['name'],$b['name']));
$edit=null; if(get('action')==='edit'&&getInt('id')) $edit=$db->findOne('suppliers',fn($s)=>$s['id']===getInt('id'));
adminHeader('Suppliers','suppliers');
?>
<div class="page-hdr">
  <div><div class="page-title">Suppliers</div><div class="page-sub"><?=count($sups)?> suppliers</div></div>
  <button class="btn btn-primary" onclick="openModal('sModal')">+ Add Supplier</button>
</div>
<div class="card"><div class="card-body p0">
  <?php if(empty($sups)):?><div class="empty-state"><p>No suppliers.</p></div><?php else:?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>#</th><th>Company</th><th>Contact</th><th>Phone</th><th>Email</th><th>GST</th><th>DL No.</th><th>Portal</th><th>Batches</th><th></th></tr></thead>
    <tbody>
    <?php foreach($sups as $s):
      $bc=$db->count('batches',fn($b)=>($b['supplier_id']??null)==$s['id']);
      $hasPortal=$db->count('supplier_users',fn($u)=>($u['supplier_id']??0)==$s['id'])>0;
    ?>
    <tr>
      <td class="text-muted text-sm"><?=e($s['id'])?></td>
      <td><div class="fw-600"><?=e($s['name'])?></div><?php if(!empty($s['address'])):?><div class="text-xs text-muted"><?=e($s['address'])?></div><?php endif;?></td>
      <td class="text-sm"><?=e($s['contact']??'—')?></td>
      <td><?=e($s['phone'])?></td>
      <td class="text-sm"><?=e($s['email']??'—')?></td>
      <td><code class="mono"><?=e($s['gst_no']??'—')?></code></td>
      <td class="text-sm"><?=e($s['dl_no']??'—')?></td>
      <td><?=$hasPortal?'<span class="chip chip-green">Active</span>':'<span class="chip chip-gray">None</span>'?></td>
      <td class="tc"><span class="chip chip-blue"><?=$bc?></span></td>
      <td><div class="flex gap-1">
        <a href="index.php?p=suppliers&action=edit&id=<?=$s['id']?>" class="btn btn-ghost btn-sm">Edit</a>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete supplier?')">
          <?=csrfField()?>
          <input type="hidden" name="action" value="delete_supplier">
          <input type="hidden" name="id" value="<?=$s['id']?>">
          <button type="submit" class="btn btn-danger btn-sm">Delete</button>
        </form>
      </div></td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
  <?php endif;?>
</div></div>
<div class="modal-overlay <?=($edit||!empty($errors))?'open':''?>" id="sModal">
  <div class="modal"><div class="modal-hdr"><span class="modal-title"><?=$edit?'Edit':'Add'?> Supplier</span><button class="modal-x" onclick="closeModal('sModal')">x</button></div>
    <form method="POST"><div class="modal-body">
      <?=csrfField()?><input type="hidden" name="action" value="<?=$edit?'edit':'add'?>">
      <?php if($edit):?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif;?>
      <?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Company Name <span class="req">*</span></label><input class="form-control" type="text" name="name" value="<?=e($edit['name']??post('name'))?>" required autofocus></div>
        <div class="form-group"><label class="form-label">Contact Person</label><input class="form-control" type="text" name="contact" value="<?=e($edit['contact']??post('contact'))?>"></div>
      </div>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Phone <span class="req">*</span></label><input class="form-control" type="tel" name="phone" value="<?=e($edit['phone']??post('phone'))?>" required></div>
        <div class="form-group"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?=e($edit['email']??post('email'))?>"></div>
      </div>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">GST Number</label><input class="form-control" type="text" name="gst_no" value="<?=e($edit['gst_no']??post('gst_no'))?>" placeholder="15-digit GSTIN"></div>
        <div class="form-group"><label class="form-label">Drug Licence (DL) Number</label><input class="form-control" type="text" name="dl_no" value="<?=e($edit['dl_no']??post('dl_no'))?>" placeholder="e.g. MH-XX-12345"><div class="form-hint">Not required but recommended for invoices.</div></div>
      </div>
      <div class="form-group"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"><?=e($edit['address']??post('address'))?></textarea></div>
      <?php if(!$edit):?>
      <div class="form-section">Supplier Portal Account (Optional)</div>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Username</label><input class="form-control" type="text" name="sup_username" value="<?=e(post('sup_username'))?>"><div class="form-hint">Credentials will be emailed</div></div>
        <div class="form-group"><label class="form-label">Password</label><input class="form-control" type="password" name="sup_password"></div>
      </div>
      <?php endif;?>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('sModal')">Cancel</button><button type="submit" class="btn btn-primary"><?=$edit?'Save':'Add'?></button></div>
    </form></div>
</div>
<?php if($edit):?><script>openModal('sModal');</script><?php endif;?>

<!-- Supplier Messages Inbox -->
<?php
$messages = $db->table('supplier_messages');
usort($messages, fn($a,$b) => ($b['id']??0) <=> ($a['id']??0));
$unread = count(array_filter($messages, fn($m) => ($m['status']??'') === 'unread'));

// message actions handled above
?>
<div class="card" style="margin-top:16px">
  <div class="card-hdr">
    <div class="card-title">Supplier Messages<?php if($unread>0):?> <span class="sb-badge" style="background:var(--red);color:#fff;padding:2px 8px;border-radius:9999px;font-size:.7rem;margin-left:6px"><?=$unread?> new</span><?php endif;?></div>
  </div>
  <div class="card-body p0">
    <?php if (empty($messages)): ?>
      <div class="empty-state"><p>No messages from suppliers yet.</p></div>
    <?php else: foreach ($messages as $msg): $isUnread = ($msg['status']??'') === 'unread'; ?>
    <div style="padding:14px 16px;border-bottom:1px solid var(--g3);background:<?=$isUnread?'#fffbeb':'#fff'?>">
      <div class="flex justify-between items-center" style="margin-bottom:4px">
        <div>
          <span class="fw-600" style="font-size:.85rem"><?=e($msg['supplier_name']??'Unknown')?></span>
          <?php if($isUnread):?><span class="chip chip-orange" style="margin-left:6px">New</span><?php endif;?>
          <span class="chip chip-gray" style="margin-left:4px"><?=e($msg['subject']??'')?></span>
        </div>
        <span class="text-xs text-muted"><?=dateTimeF($msg['created_at']??'')?></span>
      </div>
      <p style="font-size:.82rem;color:var(--g7);margin:4px 0;line-height:1.5"><?=nl2br(e($msg['message']??''))?></p>
      <div class="flex gap-1" style="margin-top:6px">
        <span class="text-xs text-muted"><?=e($msg['sender_email']??'')?></span>
        <div class="ml-auto flex gap-1">
          <?php if($isUnread):?><form method="POST" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="read_msg"><input type="hidden" name="msg_id" value="<?=$msg['id']?>"><button type="submit" class="btn btn-ghost btn-sm">Mark Read</button></form><?php endif;?>
          <button type="button" class="btn btn-ghost btn-sm" onclick="toggleReply('reply_<?=$msg['id']?>')">Reply</button>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete message?')"><?=csrfField()?><input type="hidden" name="action" value="del_msg"><input type="hidden" name="msg_id" value="<?=$msg['id']?>"><button type="submit" class="btn btn-danger btn-sm">Delete</button></form>
        </div>
      </div>
    </div>
    <div id="reply_<?=$msg['id']?>" style="display:none;padding:10px 16px;background:var(--navy-lt);border-top:1px solid var(--g3)">
      <form method="POST" class="flex gap-2 items-end">
        <?=csrfField()?>
        <input type="hidden" name="action" value="reply_sup">
        <input type="hidden" name="msg_id" value="<?=$msg['id']?>">
        <div style="flex:1"><textarea name="reply_text" class="form-control" rows="2" placeholder="Type reply to supplier..."></textarea></div>
        <button type="submit" class="btn btn-primary">Send</button>
      </form>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<script>
function toggleReply(id){var el=document.getElementById(id);el.style.display=el.style.display==='none'?'block':'none';}
</script>
<!-- Reply Modal -->
<div class="modal-overlay" id="replyModal">
  <div class="modal"><div class="modal-hdr"><span class="modal-title">Reply to Supplier</span><button class="modal-x" onclick="closeModal('replyModal')">&#x2715;</button></div>
    <form method="POST"><div class="modal-body">
      <?=csrfField()?>
      <input type="hidden" name="action" value="reply_supplier">
      <input type="hidden" name="reply_supplier_id" id="replySupId" value="">
      <div class="form-group"><label class="form-label">Your Reply</label>
        <textarea class="form-control" name="reply_message" rows="4" required placeholder="Type your reply to the supplier…"></textarea>
      </div>
      <div class="alert alert-info"><span class="alert-body">This message will be saved and the supplier will be notified by email if their email is set.</span></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('replyModal')">Cancel</button><button type="submit" class="btn btn-primary">Send Reply</button></div>
    </form>
  </div>
</div>
<script>
function openReply(supId) {
    document.getElementById('replySupId').value = supId;
    openModal('replyModal');
}
</script>
<?php adminFooter();?>
