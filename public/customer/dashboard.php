<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_portal.php'; requireCustomer();
$cid=$_SESSION['cust_id']??0;
$cust=$db->findOne('customers',fn($c)=>$c['id']===$cid);
$myOrders=$db->find('sales',fn($s)=>($s['customer_id']??0)===$cid);
usort($myOrders,fn($a,$b)=>($b['id']??0)<=>($a['id']??0));
$totalSpent=(float)array_sum(array_column($myOrders,'grand_total'));
$myReturns=$db->find('returns',function($r)use($db,$cid){$s=$db->findOne('sales',fn($s)=>$s['id']==($r['sale_id']??0));return $s&&($s['customer_id']??0)===$cid;});
$myActiveReturns=array_filter($myReturns,fn($r)=>in_array($r['status']??'',['pending','processed']));
$myRejectedReturns=array_filter($myReturns,fn($r)=>($r['status']??'')==='rejected');
$myProcessedReturns=array_filter($myReturns,fn($r)=>($r['status']??'')==='processed');
$totalRefundReceived=(float)array_sum(array_column(array_values($myProcessedReturns),'refund_amount'));
$navItems=['cust_dash'=>['icon'=>'grid','label'=>'My Dashboard'],'cust_orders'=>['icon'=>'orders','label'=>'My Orders'],'cust_messages'=>['icon'=>'mail','label'=>'Messages'],'cust_return'=>['icon'=>'return','label'=>'Return Request'],'cust_profile'=>['icon'=>'user','label'=>'My Profile']];
portalHeader('My Dashboard','customer','cust_dash',$navItems,['name'=>'customer_name']);
?>
<?php if (!($_SESSION['cust_verified'] ?? true)): ?>
<div class="alert alert-warning" style="border-radius:var(--rl);margin-bottom:16px;align-items:flex-start">
  <div style="flex:1">
    <strong>Email Not Verified</strong><br>
    <span style="font-size:.82rem">Please verify your email address to access all features. Check your inbox for the verification link.
    <?php $cust_obj=$db->findOne('customers',fn($c)=>$c['id']===$_SESSION['cust_id']);
    if(!empty($cust_obj['email'])):?>
    Sent to: <strong><?=e($cust_obj['email']??'')?></strong><?php endif;?>
    </span>
  </div>
</div>
<?php endif; ?>
<div class="page-hdr"><div><div class="page-title">Hello, <?=e(explode(' ',$cust['name']??'Patient')[0])?> </div><div class="page-sub"><?=dateF(date('Y-m-d'))?></div></div></div>
<div class="stats-row">
  <div class="stat s-purple"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div><div class="stat-lbl">Total Orders</div><div class="stat-val"><?=count($myOrders)?></div></div>
  <div class="stat s-blue"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><div class="stat-lbl">Total Spent</div><div class="stat-val"><?=money($totalSpent)?></div></div>
  <div class="stat s-green"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg></div><div class="stat-lbl">Returns</div><div class="stat-val"><?=count($myActiveReturns)?></div></div>
  <div class="stat s-red"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><div class="stat-lbl">Rejected Returns</div><div class="stat-val"><?=count($myRejectedReturns)?></div></div>
  <div class="stat s-teal"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><div class="stat-lbl">Refund Received</div><div class="stat-val"><?=money($totalRefundReceived)?></div></div>
</div>
<div class="card"><div class="card-hdr"><div class="card-title">Recent Purchases</div><a href="index.php?p=cust_orders" class="btn btn-ghost btn-sm">View All </a></div>
<div class="card-body p0">
  <?php if(empty($myOrders)):?><div class="empty-state"><p>No purchases yet.</p></div><?php else:?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>Invoice</th><th>Date</th><th>Payment</th><th class="tr">Total</th><th></th></tr></thead>
    <tbody>
    <?php foreach(array_slice($myOrders,0,8) as $s):?>
    <tr>
      <td><span class="chip chip-purple"><?=invNo($s['id'])?></span></td>
      <td class="text-sm"><?=dateF($s['sale_date']??'')?></td>
      <td><span class="chip chip-gray"><?=ucfirst($s['payment_method']??'cash')?></span></td>
      <td class="tr fw-600"><?=money($s['grand_total']??0)?></td>
      <td><a href="index.php?p=cust_orders&view=<?=$s['id']?>" class="btn btn-ghost btn-sm">View</a></td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
  <?php endif;?>
</div></div>
<?php portalFooter();?>
