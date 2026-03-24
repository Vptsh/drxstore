<?php
/**
 * DRXStore - Unified User Management
 * Manages: Admin/Staff users, Supplier portal users, Patient/Customer accounts
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php';
require_once ROOT.'/views/layout_admin.php';
requireAdmin();

$errors = [];
$tab = get('tab', 'staff'); // staff | suppliers | patients

/* ══════════════════════════════════════════
   STAFF / ADMIN USERS
══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = post('action');

    // ── Add/Edit staff user ──────────────────
    if (in_array($act, ['add_staff','edit_staff'])) {
        $id    = postInt('id');
        $name  = post('name');
        $uname = post('username');
        $email = post('email');
        $pw    = post('password');
        $role  = post('role','staff');

        if (!$name)  $errors[] = 'Name required.';
        if (!$email) $errors[] = 'Email required.';
        if ($act === 'add_staff' && !$pw) $errors[] = 'Password required.';
        if ($pw && strlen($pw) < 6) $errors[] = 'Password min 6 characters.';
        if ($email && $db->findOne('users', fn($u) => strtolower($u['email']??'') === strtolower($email) && ($u['id']??0) != $id))
            $errors[] = 'Email already in use.';

        if (empty($errors)) {
            $d = ['name'=>$name,'username'=>$uname,'email'=>$email,'role'=>$role];
            if ($pw) $d['password'] = password_hash($pw, PASSWORD_BCRYPT);
            if ($act === 'edit_staff' && $id) {
                $d['updated_at'] = date('Y-m-d H:i:s');
                // Prevent demoting last admin
                if ($role !== 'admin') {
                    $adminCount = $db->count('users', fn($u) => ($u['role']??'') === 'admin' && ($u['id']??0) != $id && ($u['active']??true));
                    if ($adminCount < 1) { $errors[] = 'Cannot change role — must have at least one admin.'; goto render; }
                }
                $db->update('users', fn($u) => $u['id'] === $id, $d);
                setFlash('success', 'User updated.');
            } else {
                $d['active'] = 1; $d['created_at'] = date('Y-m-d H:i:s');
                $db->insert('users', $d);

                // ── Send credentials email to new staff member ──
                if (!empty($email) && $pw) {
                    $cfg = $db->findOne('settings', fn($x) => true) ?? [];
                    $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].str_replace('index.php','',($_SERVER['SCRIPT_NAME']??'/'));
                    $subject = storeName() . ' — Your Staff Account Credentials';
                    $body  = "Hello {$name},\n\n";
                    $body .= "Your staff account has been created at " . storeName() . ".\n\n";
                    $body .= "Login Details:\n";
                    $body .= "  URL      : {$loginUrl}\n";
                    $body .= "  Username : {$uname}\n";
                    $body .= "  Password : {$pw}\n";
                    $body .= "  Role     : " . ucfirst($role) . "\n\n";
                    $body .= "Please log in and change your password from Settings.\n\n";
                    $body .= "— " . storeName() . " Admin Team";
                    try {
                        $htmlBody = mailTemplate("Your Staff Account — " . storeName(),
                            "<p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>" .
                            "<p>Your staff account has been created at <strong>" . storeName() . "</strong>.</p>" .
                            "<table style='border-collapse:collapse;margin:16px 0'>" .
                            "<tr><td style='padding:6px 14px 6px 0;color:#666;font-size:.88rem'>URL</td><td style='padding:6px 0;font-weight:600'>" . htmlspecialchars($loginUrl) . "</td></tr>" .
                            "<tr><td style='padding:6px 14px 6px 0;color:#666;font-size:.88rem'>Username</td><td style='padding:6px 0;font-weight:600;font-family:monospace'>" . htmlspecialchars($uname) . "</td></tr>" .
                            "<tr><td style='padding:6px 14px 6px 0;color:#666;font-size:.88rem'>Password</td><td style='padding:6px 0;font-weight:600;font-family:monospace'>" . htmlspecialchars($pw) . "</td></tr>" .
                            "<tr><td style='padding:6px 14px 6px 0;color:#666;font-size:.88rem'>Role</td><td style='padding:6px 0;font-weight:600'>" . htmlspecialchars(ucfirst($role)) . "</td></tr>" .
                            "</table>" .
                            "<p style='color:#666;font-size:.88rem'>Please log in and change your password from Settings immediately.</p>"
                        );
                        $ok = sendMail($email, $subject, $htmlBody);
                        if ($ok) setFlash('success', "User added. Credentials sent to {$email}.");
                        else setFlash('success', "User added. (Email could not be sent — check SMTP settings.)");
                    } catch (Exception $e) {
                        setFlash('success', "User added. (Email not sent: " . $e->getMessage() . ")");
                    }
                } else {
                    setFlash('success', 'User added.');
                }
            }
            header('Location: index.php?p=users&tab=staff'); exit;
        }
        $tab = 'staff';
    }

    // ── Add/Edit supplier portal user ────────
    if (in_array($act, ['add_sup','edit_sup'])) {
        $id      = postInt('id');
        $sup_id  = postInt('supplier_id');
        $uname   = post('sup_username');
        $email   = post('sup_email');
        $pw      = post('sup_password');
        $active  = (bool)postInt('sup_active', 1);

        if (!$sup_id) $errors[] = 'Select a supplier.';
        if (!$uname)  $errors[] = 'Username required.';
        if ($act === 'add_sup' && !$pw) $errors[] = 'Password required.';
        if ($pw && strlen($pw) < 6)     $errors[] = 'Password min 6 characters.';

        if (empty($errors)) {
            $d = ['supplier_id'=>$sup_id,'username'=>$uname,'email'=>$email,'active'=>(int)$active];
            if ($pw) $d['password'] = password_hash($pw, PASSWORD_BCRYPT);
            if ($act === 'edit_sup' && $id) {
                $d['updated_at'] = date('Y-m-d H:i:s');
                $db->update('supplier_users', fn($u) => $u['id'] === $id, $d);
                setFlash('success', 'Supplier user updated.');
            } else {
                $d['created_at'] = date('Y-m-d H:i:s');
                $db->insert('supplier_users', $d);
                setFlash('success', 'Supplier portal user created.');
            }
            header('Location: index.php?p=users&tab=suppliers'); exit;
        }
        $tab = 'suppliers';
    }

    // ── Edit patient/customer account ────────
    if ($act === 'edit_patient') {
        $id    = postInt('id');
        $name  = post('p_name');
        $phone = post('p_phone');
        $email = post('p_email');
        $pw    = post('p_password');
        $active= (bool)postInt('p_active',1);
        $verified = (bool)postInt('p_verified',0);

        if (!$name)  $errors[] = 'Name required.';
        if (!$phone) $errors[] = 'Phone required.';
        if ($pw && strlen($pw) < 6) $errors[] = 'Password min 6 characters.';

        if (empty($errors)) {
            $d = ['name'=>$name,'phone'=>$phone,'email'=>$email,'active'=>(int)$active,'verified'=>(int)$verified,'updated_at'=>date('Y-m-d H:i:s')];
            if ($pw) $d['password'] = password_hash($pw, PASSWORD_BCRYPT);
            $db->update('customers', fn($c) => $c['id'] === $id, $d);
            setFlash('success', 'Patient updated.');
            header('Location: index.php?p=users&tab=patients'); exit;
        }
        $tab = 'patients';
    }
}

// ── GET actions ──────────────────────────────
// Toggle staff active
if (get('action') === 'toggle_staff' && getInt('id')) {
    $uid = getInt('id');
    if ($uid !== (int)$_SESSION['admin_id']) {
        $u = $db->findOne('users', fn($u) => $u['id'] === $uid);
        if ($u) {
            // Prevent disabling last admin
            if (($u['role']??'') === 'admin' && ($u['active']??true)) {
                $activeAdmins = $db->count('users', fn($u2) => ($u2['role']??'') === 'admin' && ($u2['active']??true));
                if ($activeAdmins <= 1) { setFlash('danger', 'Cannot disable the last admin account.'); header('Location: index.php?p=users&tab=staff'); exit; }
            }
            $db->update('users', fn($u2) => $u2['id'] === $uid, ['active' => !($u['active']??true)]);
            setFlash('success', 'Status updated.');
        }
    }
    header('Location: index.php?p=users&tab=staff'); exit;
}
// Delete staff (never delete yourself or last admin)
if (get('action') === 'del_staff' && getInt('id')) {
    $uid = getInt('id');
    if ($uid !== (int)$_SESSION['admin_id']) {
        $u = $db->findOne('users', fn($u) => $u['id'] === $uid);
        if ($u && ($u['role']??'') === 'admin') {
            $adminCount = $db->count('users', fn($u2) => ($u2['role']??'') === 'admin');
            if ($adminCount <= 1) { setFlash('danger', 'Cannot delete the last admin account.'); header('Location: index.php?p=users&tab=staff'); exit; }
        }
        $db->delete('users', fn($u) => $u['id'] === $uid);
        setFlash('success', 'User deleted.');
    }
    header('Location: index.php?p=users&tab=staff'); exit;
}
// Toggle/delete supplier user
if (get('action') === 'toggle_sup' && getInt('id')) {
    $sid = getInt('id');
    $su = $db->findOne('supplier_users', fn($u) => $u['id'] === $sid);
    if ($su) $db->update('supplier_users', fn($u) => $u['id'] === $sid, ['active' => !($su['active']??true)]);
    setFlash('success', 'Status updated.'); header('Location: index.php?p=users&tab=suppliers'); exit;
}
if (get('action') === 'del_sup' && getInt('id')) {
    $db->delete('supplier_users', fn($u) => $u['id'] === getInt('id'));
    setFlash('success', 'Deleted.'); header('Location: index.php?p=users&tab=suppliers'); exit;
}
// Toggle/delete patient
if (get('action') === 'toggle_patient' && getInt('id')) {
    $cid = getInt('id'); $cu = $db->findOne('customers', fn($c) => $c['id'] === $cid);
    if ($cu) $db->update('customers', fn($c) => $c['id'] === $cid, ['active' => !($cu['active']??true)]);
    setFlash('success', 'Status updated.'); header('Location: index.php?p=users&tab=patients'); exit;
}
if (get('action') === 'del_patient' && getInt('id')) {
    $db->delete('customers', fn($c) => $c['id'] === getInt('id'));
    setFlash('success', 'Deleted.'); header('Location: index.php?p=users&tab=patients'); exit;
}

// Load data
$staffUsers    = $db->table('users');
$supplierUsers = $db->table('supplier_users');
$patients      = $db->table('customers');
$suppliers     = $db->table('suppliers');
$supMap        = array_column($suppliers, 'name', 'id');

$editStaff   = null; $editSup = null; $editPatient = null;
if (get('action') === 'edit_staff'   && getInt('id')) $editStaff   = $db->findOne('users',          fn($u) => $u['id'] === getInt('id'));
if (get('action') === 'edit_sup'     && getInt('id')) $editSup     = $db->findOne('supplier_users',  fn($u) => $u['id'] === getInt('id'));
if (get('action') === 'edit_patient' && getInt('id')) $editPatient = $db->findOne('customers',       fn($c) => $c['id'] === getInt('id'));

render:
adminHeader('Users & Accounts', 'users');
?>
<div class="page-hdr">
  <div><div class="page-title">Users &amp; Accounts</div>
    <div class="page-sub"><?=count($staffUsers)?> staff &nbsp;&bull;&nbsp; <?=count($supplierUsers)?> supplier portals &nbsp;&bull;&nbsp; <?=count($patients)?> patients</div>
  </div>
  <div class="page-actions">
    <?php if($tab==='staff'):?>
    <button class="btn btn-primary" onclick="openModal('uModal')">+ Add Staff / Admin</button>
    <?php elseif($tab==='suppliers'):?>
    <button class="btn btn-primary" onclick="openModal('supModal')">+ Add Supplier Login</button>
    <?php elseif($tab==='patients'):?>
    <a href="index.php?p=customers" class="btn btn-ghost">Manage in Customers</a>
    <?php endif;?>
  </div>
</div>

<!-- Tab bar -->
<div style="display:flex;gap:0;border-bottom:2px solid var(--g3);margin-bottom:16px">
  <?php foreach(['staff'=>'Staff &amp; Admins','suppliers'=>'Supplier Portals','patients'=>'Patients'] as $t=>$lbl):?>
  <a href="index.php?p=users&tab=<?=$t?>" style="padding:9px 18px;font-size:.82rem;font-weight:600;border-bottom:2px solid <?=$tab===$t?'var(--navy)':'transparent'?>;margin-bottom:-2px;color:<?=$tab===$t?'var(--navy)':'var(--g6)'?>;text-decoration:none"><?=$lbl?></a>
  <?php endforeach;?>
</div>

<?php if($tab === 'staff'): ?>
<!-- ─── STAFF/ADMIN TAB ─── -->
<div class="card"><div class="card-body p0">
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Last Login</th><th>Status</th><th style="width:180px"></th></tr></thead>
    <tbody>
    <?php foreach($staffUsers as $u): $isMe=($u['id']==(int)$_SESSION['admin_id']); $isAdmin=($u['role']??'')==='admin'; ?>
    <tr>
      <td class="fw-600"><?=e($u['name']??'')?><?php if($isMe):?> <span class="chip chip-blue" style="font-size:.6rem">You</span><?php endif;?></td>
      <td class="mono"><?=e($u['username']??'—')?></td>
      <td class="text-sm"><?=e($u['email']??'—')?></td>
      <td><span class="chip <?=$isAdmin?'chip-purple':'chip-blue'?>"><?=e($u['role']??'staff')?></span></td>
      <td class="text-sm text-muted"><?=dateTimeF($u['last_login']??'')?></td>
      <td><?=($u['active']??true)?'<span class="chip chip-green">Active</span>':'<span class="chip chip-red">Inactive</span>'?></td>
      <td>
        <div class="flex gap-1" style="flex-wrap:nowrap">
          <a href="index.php?p=users&action=edit_staff&id=<?=$u['id']?>&tab=staff" class="btn btn-ghost btn-sm">Edit</a>
          <?php if(!$isMe): ?>
          <a href="index.php?p=users&action=toggle_staff&id=<?=$u['id']?>" class="btn btn-ghost btn-sm" style="min-width:62px;text-align:center"><?=($u['active']??true)?'Disable':'Enable'?></a>
          <a href="index.php?p=users&action=del_staff&id=<?=$u['id']?>" class="btn btn-danger btn-sm" data-confirm="Delete &quot;<?=e($u['name'])?>?&quot;">Delete</a>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
</div></div>

<?php elseif($tab === 'suppliers'): ?>
<!-- ─── SUPPLIER PORTALS TAB ─── -->
<div class="alert alert-info"><span class="alert-body">These are login accounts for the <strong>Supplier Portal</strong>. Each supplier company can have one portal login. Create a supplier first in <a href="index.php?p=suppliers">Suppliers page</a>, then add their portal login here.</span></div>
<div class="card"><div class="card-body p0">
  <?php if(empty($supplierUsers)): ?>
  <div class="empty-state"><p>No supplier portal accounts yet. Add one to let suppliers track orders.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>Username</th><th>Supplier Company</th><th>Email</th><th>Last Login</th><th>Status</th><th style="width:180px"></th></tr></thead>
    <tbody>
    <?php foreach($supplierUsers as $su): ?>
    <tr>
      <td class="fw-600 mono"><?=e($su['username']??'—')?></td>
      <td><?=e($supMap[$su['supplier_id']??0] ?? 'Unknown')?></td>
      <td class="text-sm"><?=e($su['email']??'—')?></td>
      <td class="text-sm text-muted"><?=dateTimeF($su['last_login']??'')?></td>
      <td><?=($su['active']??true)?'<span class="chip chip-green">Active</span>':'<span class="chip chip-red">Inactive</span>'?></td>
      <td>
        <div class="flex gap-1" style="flex-wrap:nowrap">
          <a href="index.php?p=users&action=edit_sup&id=<?=$su['id']?>&tab=suppliers" class="btn btn-ghost btn-sm">Edit</a>
          <a href="index.php?p=users&action=toggle_sup&id=<?=$su['id']?>" class="btn btn-ghost btn-sm" style="min-width:62px;text-align:center"><?=($su['active']??true)?'Disable':'Enable'?></a>
          <a href="index.php?p=users&action=del_sup&id=<?=$su['id']?>" class="btn btn-danger btn-sm" data-confirm="Delete this supplier login?">Delete</a>
        </div>
      </td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
  <?php endif;?>
</div></div>

<?php elseif($tab === 'patients'): ?>
<!-- ─── PATIENTS TAB ─── -->
<div class="card"><div class="card-body p0">
  <?php if(empty($patients)): ?>
  <div class="empty-state"><p>No patient accounts yet.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Verified</th><th>Status</th><th>Registered</th><th style="width:180px"></th></tr></thead>
    <tbody>
    <?php foreach($patients as $pt): ?>
    <tr>
      <td class="fw-600"><?=e($pt['name']??'')?></td>
      <td><?=e($pt['phone']??'—')?></td>
      <td class="text-sm"><?=e($pt['email']??'—')?></td>
      <td><?=($pt['verified']??false)?'<span class="chip chip-green">Verified</span>':'<span class="chip chip-orange">Pending</span>'?></td>
      <td><?=($pt['active']??true)?'<span class="chip chip-green">Active</span>':'<span class="chip chip-red">Inactive</span>'?></td>
      <td class="text-sm text-muted"><?=dateF($pt['created_at']??'')?></td>
      <td>
        <div class="flex gap-1" style="flex-wrap:nowrap">
          <a href="index.php?p=users&action=edit_patient&id=<?=$pt['id']?>&tab=patients" class="btn btn-ghost btn-sm">Edit</a>
          <a href="index.php?p=users&action=toggle_patient&id=<?=$pt['id']?>" class="btn btn-ghost btn-sm" style="min-width:62px;text-align:center"><?=($pt['active']??true)?'Disable':'Enable'?></a>
          <a href="index.php?p=users&action=del_patient&id=<?=$pt['id']?>" class="btn btn-danger btn-sm" data-confirm="Delete patient &quot;<?=e($pt['name'])?>?&quot;">Delete</a>
        </div>
      </td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
  <?php endif;?>
</div></div>
<?php endif;?>

<!-- ═══ STAFF MODAL ═══ -->
<div class="modal-overlay <?=($editStaff||($tab==='staff'&&!empty($errors)))?'open':''?>" id="uModal">
  <div class="modal"><div class="modal-hdr">
    <span class="modal-title"><?=$editStaff?'Edit Staff User':'Add Staff / Admin'?></span>
    <button class="modal-x" onclick="closeModal('uModal')">&#x2715;</button>
  </div>
  <form method="POST"><div class="modal-body">
    <?=csrfField()?>
    <input type="hidden" name="action" value="<?=$editStaff?'edit_staff':'add_staff'?>">
    <?php if($editStaff):?><input type="hidden" name="id" value="<?=$editStaff['id']?>"><?php endif;?>
    <?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>
    <div class="form-row-2">
      <div class="form-group"><label class="form-label">Full Name <span class="req">*</span></label><input class="form-control" type="text" name="name" value="<?=e($editStaff['name']??post('name'))?>" required autofocus></div>
      <div class="form-group"><label class="form-label">Username</label><input class="form-control" type="text" name="username" value="<?=e($editStaff['username']??post('username'))?>"></div>
    </div>
    <div class="form-row-2">
      <div class="form-group"><label class="form-label">Email <span class="req">*</span></label><input class="form-control" type="email" name="email" value="<?=e($editStaff['email']??post('email'))?>" required></div>
      <div class="form-group"><label class="form-label">Role</label>
        <select class="form-control" name="role">
          <option value="staff" <?=($editStaff['role']??'staff')==='staff'?'selected':''?>>Staff &mdash; Limited access</option>
          <option value="admin" <?=($editStaff['role']??'')==='admin'?'selected':''?>>Admin &mdash; Full access</option>
        </select>
      </div>
    </div>
    <div class="form-group"><label class="form-label">Password <?=$editStaff?'(leave blank to keep)':'<span class="req">*</span>'?></label>
      <input class="form-control" type="password" name="password" placeholder="<?=$editStaff?'Leave blank to keep current':'Min 6 characters'?>" <?=$editStaff?'':'required'?>>
    </div>
    <?php if(!$editStaff):?><div class="alert alert-info"><span class="alert-body"><strong>Staff</strong> can access: Sales, Batches, Medicines, Customers, Expiry &amp; Returns.<br><strong>Admin</strong> has full access to all sections.</span></div><?php endif;?>
  </div>
  <div class="modal-foot">
    <button type="button" class="btn btn-ghost" onclick="closeModal('uModal')">Cancel</button>
    <button type="submit" class="btn btn-primary"><?=$editStaff?'Save Changes':'Add User'?></button>
  </div>
  </form></div>
</div>

<!-- ═══ SUPPLIER PORTAL MODAL ═══ -->
<div class="modal-overlay <?=($editSup||($tab==='suppliers'&&!empty($errors)))?'open':''?>" id="supModal">
  <div class="modal"><div class="modal-hdr">
    <span class="modal-title"><?=$editSup?'Edit Supplier Login':'Add Supplier Portal Login'?></span>
    <button class="modal-x" onclick="closeModal('supModal')">&#x2715;</button>
  </div>
  <form method="POST"><div class="modal-body">
    <?=csrfField()?>
    <input type="hidden" name="action" value="<?=$editSup?'edit_sup':'add_sup'?>">
    <?php if($editSup):?><input type="hidden" name="id" value="<?=$editSup['id']?>"><?php endif;?>
    <?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>
    <div class="form-group"><label class="form-label">Supplier Company <span class="req">*</span></label>
      <select class="form-control" name="supplier_id" required>
        <option value="">— Select Supplier —</option>
        <?php foreach($suppliers as $s):?>
        <option value="<?=$s['id']?>" <?=($editSup['supplier_id']??postInt('supplier_id'))==$s['id']?'selected':''?>><?=e($s['name'])?></option>
        <?php endforeach;?>
      </select>
    </div>
    <div class="form-row-2">
      <div class="form-group"><label class="form-label">Username <span class="req">*</span></label><input class="form-control" type="text" name="sup_username" value="<?=e($editSup['username']??post('sup_username'))?>" required></div>
      <div class="form-group"><label class="form-label">Email</label><input class="form-control" type="email" name="sup_email" value="<?=e($editSup['email']??post('sup_email'))?>"></div>
    </div>
    <div class="form-row-2">
      <div class="form-group"><label class="form-label">Password <?=$editSup?'(blank = keep)':'<span class="req">*</span>'?></label>
        <input class="form-control" type="password" name="sup_password" placeholder="<?=$editSup?'Leave blank to keep':'Set password'?>" <?=$editSup?'':'required'?>>
      </div>
      <div class="form-group"><label class="form-label">Status</label>
        <select class="form-control" name="sup_active">
          <option value="1" <?=($editSup['active']??1)?'selected':''?>>Active</option>
          <option value="0" <?=!($editSup['active']??1)?'selected':''?>>Inactive</option>
        </select>
      </div>
    </div>
  </div>
  <div class="modal-foot">
    <button type="button" class="btn btn-ghost" onclick="closeModal('supModal')">Cancel</button>
    <button type="submit" class="btn btn-primary"><?=$editSup?'Save Changes':'Create Login'?></button>
  </div>
  </form></div>
</div>

<!-- ═══ PATIENT EDIT MODAL ═══ -->
<?php if($editPatient): ?>
<div class="modal-overlay open" id="ptModal">
  <div class="modal"><div class="modal-hdr">
    <span class="modal-title">Edit Patient Account</span>
    <button class="modal-x" onclick="closeModal('ptModal')">&#x2715;</button>
  </div>
  <form method="POST"><div class="modal-body">
    <?=csrfField()?>
    <input type="hidden" name="action" value="edit_patient">
    <input type="hidden" name="id" value="<?=$editPatient['id']?>">
    <?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>
    <div class="form-row-2">
      <div class="form-group"><label class="form-label">Full Name <span class="req">*</span></label><input class="form-control" type="text" name="p_name" value="<?=e($editPatient['name']??'')?>" required></div>
      <div class="form-group"><label class="form-label">Phone <span class="req">*</span></label><input class="form-control" type="tel" name="p_phone" value="<?=e($editPatient['phone']??'')?>" required></div>
    </div>
    <div class="form-group"><label class="form-label">Email</label><input class="form-control" type="email" name="p_email" value="<?=e($editPatient['email']??'')?>"></div>
    <div class="form-row-2">
      <div class="form-group"><label class="form-label">Email Verified</label>
        <select class="form-control" name="p_verified">
          <option value="1" <?=($editPatient['verified']??false)?'selected':''?>>Verified</option>
          <option value="0" <?=!($editPatient['verified']??false)?'selected':''?>>Not Verified</option>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Account Status</label>
        <select class="form-control" name="p_active">
          <option value="1" <?=($editPatient['active']??true)?'selected':''?>>Active</option>
          <option value="0" <?=!($editPatient['active']??true)?'selected':''?>>Inactive</option>
        </select>
      </div>
    </div>
    <div class="form-group"><label class="form-label">Reset Password (leave blank to keep)</label>
      <input class="form-control" type="password" name="p_password" placeholder="Leave blank to keep current">
    </div>
  </div>
  <div class="modal-foot">
    <button type="button" class="btn btn-ghost" onclick="closeModal('ptModal')">Cancel</button>
    <button type="submit" class="btn btn-primary">Save Changes</button>
  </div>
  </form></div>
</div>
<?php endif;?>

<script>
// Open correct modal if edit action
<?php if($editStaff):?>openModal('uModal');<?php endif;?>
<?php if($editSup):?>openModal('supModal');<?php endif;?>
</script>
<?php adminFooter();?>
