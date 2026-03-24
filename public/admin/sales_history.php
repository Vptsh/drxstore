<?php
/**
 * DRXStore - Sales History v2.0
 * SQL-powered search, filter, count — no full table loads
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_admin.php'; requireStaff();

$q      = get('q','');
$fd     = get('fd','');
$td     = get('td','');
$fcust  = getInt('customer_id');
$page   = max(1, getInt('page', 1));
$limit  = PER_PAGE;
$offset = ($page - 1) * $limit;

if ($db instanceof MySQLDB) {
    $pdo = $db->pdo();
    $where = ["1=1"];
    $vals  = [];

    if ($q) {
        // Search by invoice number or customer name
        $qlike = '%' . $q . '%';
        $where[] = "(LPAD(s.id,8,'0') LIKE ? OR c.name LIKE ? OR CONCAT('DRX-',YEAR(s.sale_date),'-',LPAD(s.id,8,'0')) LIKE ?)";
        array_push($vals, $qlike, $qlike, $qlike);
    }
    if ($fd) { $where[] = "s.sale_date >= ?"; $vals[] = $fd; }
    if ($td) { $where[] = "s.sale_date <= ?"; $vals[] = $td; }
    if ($fcust) { $where[] = "s.customer_id = ?"; $vals[] = $fcust; }

    $whereSQL = implode(' AND ', $where);

    // Total count and revenue
    $cntStmt = $pdo->prepare("SELECT COUNT(*) cnt, COALESCE(SUM(s.grand_total),0) rev FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE {$whereSQL}");
    $cntStmt->execute($vals);
    $agg = $cntStmt->fetch();
    $totalCount = (int)$agg['cnt'];
    $totalRev   = (float)$agg['rev'];
    $totalPages = (int)ceil($totalCount / $limit);

    // Paginated rows
    $stmt = $pdo->prepare("SELECT s.*, COALESCE(c.name,'Walk-in') cname,
        (SELECT COUNT(*) FROM sales_items si WHERE si.sale_id=s.id) item_count
        FROM sales s LEFT JOIN customers c ON c.id=s.customer_id
        WHERE {$whereSQL} ORDER BY s.id DESC LIMIT {$limit} OFFSET {$offset}");
    $stmt->execute($vals);
    $sales = $stmt->fetchAll();

    $pag = ['items'=>$sales,'total'=>$totalCount,'pages'=>$totalPages,'page'=>$page,'per_page'=>$limit];

} else {
    // JSON fallback
    $custMap=[]; foreach($db->table('customers') as $c) $custMap[$c['id']]=$c;
    $allSales=$db->table('sales'); usort($allSales,fn($a,$b)=>($b['id']??0)<=>($a['id']??0));
    $filtered=$allSales;
    if($q){$ql=strtolower($q);$filtered=array_values(array_filter($filtered,fn($s)=>(strpos(strtolower(invNo($s['id'])),$ql)!==false)||(strpos(strtolower($custMap[$s['customer_id']??0]['name']??'walk-in'),$ql)!==false)));}
    if($fd)$filtered=array_values(array_filter($filtered,fn($s)=>($s['sale_date']??'')>=$fd));
    if($td)$filtered=array_values(array_filter($filtered,fn($s)=>($s['sale_date']??'')<=$td));
    if($fcust)$filtered=array_values(array_filter($filtered,fn($s)=>($s['customer_id']??0)==$fcust));
    $totalRev=array_sum(array_column($filtered,'grand_total'));
    $pag=paginate($filtered,$page,$limit);
    // Enrich with cname and item_count
    foreach($pag['items'] as &$s){
        $s['cname']=$custMap[$s['customer_id']??0]['name']??'Walk-in';
        $s['item_count']=$db->count('sales_items',fn($si)=>($si['sale_id']??0)==$s['id']);
    } unset($s);
}

adminHeader('Sales History','sales_hist');
?>
<div class="page-hdr">
  <div>
    <div class="page-title"> Sales History</div>
    <div class="page-sub"><?= number_format($pag['total'] ?? count($pag['items'])) ?> record(s) | Total: <?= money($totalRev) ?></div>
  </div>
  <a href="index.php?p=sales" class="btn btn-primary">+ New Sale</a>
</div>
<div class="card mb-2"><div class="card-body" style="padding:10px 16px">
  <form method="GET" class="flex gap-2 flex-wrap items-end">
    <input type="hidden" name="p" value="sales_hist">
    <div class="search-bar"><input type="text" name="q" value="<?=e($q)?>" placeholder="Invoice or customer…"></div>
    <div><label class="form-label" style="font-size:.7rem">From</label><input class="form-control" type="date" name="fd" value="<?=e($fd)?>" style="border-radius:6px;padding:6px 10px"></div>
    <div><label class="form-label" style="font-size:.7rem">To</label><input class="form-control" type="date" name="td" value="<?=e($td)?>" style="border-radius:6px;padding:6px 10px"></div>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="index.php?p=sales_hist" class="btn btn-ghost btn-sm">Reset</a>
  </form>
</div></div>
<div class="card"><div class="card-body p0">
  <?php if(empty($pag['items'])):?><div class="empty-state"><p>No sales.</p></div><?php else:?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Payment</th><th class="tc">Items</th><th class="tr">Subtotal</th><th class="tr">GST</th><th class="tr">Total</th><th></th></tr></thead>
    <tbody>
    <?php foreach($pag['items'] as $s):
      $grand=(float)($s['grand_total']??(($s['total_amount']??0)+($s['gst_amount']??0)));
    ?>
    <tr>
      <td><span class="chip chip-blue"><?=invNo($s['id'])?></span></td>
      <td class="text-sm"><?=dateF($s['sale_date']??'')?></td>
      <td class="fw-600"><?=e($s['cname']??'Walk-in')?></td>
      <td><span class="chip chip-gray"><?=ucfirst($s['payment_method']??'Cash')?></span></td>
      <td class="tc"><span class="chip chip-gray"><?=$s['item_count']??0?></span></td>
      <td class="tr text-sm"><?=money($s['total_amount']??0)?></td>
      <td class="tr text-sm text-muted"><?=money($s['gst_amount']??0)?></td>
      <td class="tr fw-600 text-blue"><?=money($grand)?></td>
      <td><a href="index.php?p=view_inv&sale_id=<?=$s['id']?>" class="btn btn-ghost btn-sm">View</a></td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
  <?=pagerHtml($pag,'index.php?p=sales_hist&q='.urlencode($q).'&fd='.e($fd).'&td='.e($td).'&customer_id='.$fcust)?>
  <?php endif;?>
</div></div>
<?php adminFooter();?>
