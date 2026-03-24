<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_portal.php'; requireSupplier();
$sid=$_SESSION['supplier_id']??0;
$su=$db->findOne('supplier_users',fn($u)=>$u['id']===$sid);
$sup=$db->findOne('suppliers',fn($s)=>$s['id']==($su['supplier_id']??0));
$orders=$db->find('purchase_orders',fn($p)=>($p['supplier_id']??0)==($su['supplier_id']??0));
usort($orders,fn($a,$b)=>($b['id']??0)<=>($a['id']??0));
$pending=count(array_filter($orders,fn($o)=>($o['status']??'')==='pending'));
$processing=count(array_filter($orders,fn($o)=>in_array($o['status']??'',['confirmed','shipped'])));
$received=count(array_filter($orders,fn($o)=>($o['status']??'')==='received'));
$cancelled=count(array_filter($orders,fn($o)=>($o['status']??'')==='cancelled'));
$totalVal=array_sum(array_column($orders,'total'));
$chipMap=['pending'=>'chip-orange','price_updated'=>'chip-orange','confirmed'=>'chip-blue','shipped'=>'chip-purple','received'=>'chip-green','cancelled'=>'chip-red'];
$navItems=['sup_dash'=>['icon'=>'grid','label'=>'Dashboard'],'sup_orders'=>['icon'=>'orders','label'=>'Purchase Orders'],'sup_profile'=>['icon'=>'user','label'=>'My Profile'],'sup_contact'=>['icon'=>'mail','label'=>'Contact Store']];
portalHeader('Supplier Dashboard','supplier','sup_dash',$navItems,['name'=>'supplier_company']);
?>
<div class="page-hdr"><div><div class="page-title">Welcome, <?=e($sup['name']??'Supplier')?></div><div class="page-sub"><?=dateF(date('Y-m-d'))?></div></div></div>
<div class="stats-row">
  <div class="stat s-orange"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg></div><div class="stat-lbl">Pending Orders</div><div class="stat-val"><?=$pending?></div></div>
  <div class="stat s-blue"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div><div class="stat-lbl">Processing Orders</div><div class="stat-val"><?=$processing?></div></div>
  <div class="stat s-green"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="20 6 9 17 4 12"/></svg></div><div class="stat-lbl">Received Orders</div><div class="stat-val"><?=$received?></div></div>
  <div class="stat s-purple"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div><div class="stat-lbl">Total Value</div><div class="stat-val"><?=money($totalVal)?></div></div>
  <div class="stat s-red"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><div class="stat-lbl">Cancelled Orders</div><div class="stat-val"><?=$cancelled?></div></div>
</div>
<div class="card"><div class="card-hdr"><div class="card-title">Recent Purchase Orders</div><a href="index.php?p=sup_orders" class="btn btn-ghost btn-sm">View All </a></div>
<div class="card-body p0">
  <?php if(empty($orders)):?><div class="empty-state"><p>No orders yet.</p></div><?php else:?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>PO No</th><th>Date</th><th class="tr">Total</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach(array_slice($orders,0,10) as $o):
      $ost=$o['status']??'pending';
      $statusLabel=match($ost){'confirmed'=>'Confirmed','shipped'=>'Shipped / Dispatched','received'=>'Received','price_updated'=>'Awaiting Confirmation','cancelled'=>'Cancelled',default=>'Pending'};
    ?>
    <tr>
      <td><span class="chip chip-blue"><?=poNo($o['id'])?></span></td>
      <td class="text-sm"><?=dateF($o['po_date']??'')?></td>
      <td class="tr fw-600"><?=money($o['total']??0)?></td>
      <td><span class="chip <?=$chipMap[$ost]??'chip-gray'?>"><?=e($statusLabel)?></span></td>
      <td><a href="index.php?p=sup_orders&view=<?=$o['id']?>" class="btn btn-ghost btn-sm">View</a></td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
  <?php endif;?>
</div></div>
<?php portalFooter();?>
