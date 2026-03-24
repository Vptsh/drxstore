<?php
/**
 * DRXStore - Analytics & Reports v2.0
 * SQL-powered — no full table loads, works fast at 100k+ records
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT . '/config/app.php';
require_once ROOT . '/views/layout_admin.php';
requireAdmin();

$useSQL = ($db instanceof MySQLDB);

// ── Helper: run SQL if MySQL, fallback to PHP if JSON ──
function sqlOrPhp(MySQLDB|JsonDB $db, string $sql, array $bind, callable $phpFallback) {
    if ($db instanceof MySQLDB) {
        try {
            $st = $db->pdo()->prepare($sql);
            $st->execute($bind);
            return $st->fetchAll();
        } catch (Exception $e) { return $phpFallback(); }
    }
    return $phpFallback();
}

// ── CSV Export ──
if (get('export') === 'csv') {
    $fname = 'DRXStore_Analytics_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['DRXStore Analytics Export — ' . storeName()]);
    fputcsv($fp, ['Export Date: ' . date('d M Y H:i:s')]);
    fputcsv($fp, []);
    fputcsv($fp, ['=== MONTHLY SUMMARY ===']);
    fputcsv($fp, ['Month', 'Sales Count', 'Revenue', 'GST Collected', 'Discount']);
    if ($db instanceof MySQLDB) {
        $rows = $db->pdo()->query("SELECT DATE_FORMAT(sale_date,'%Y-%m') m, COUNT(*) cnt,
            SUM(grand_total) rev, SUM(gst_amount) gst, SUM(discount_amount) disc
            FROM sales GROUP BY m ORDER BY m DESC LIMIT 12")->fetchAll();
        foreach ($rows as $r)
            fputcsv($fp,[date('M Y',strtotime($r['m'].'-01')),$r['cnt'],number_format($r['rev'],2),number_format($r['gst'],2),number_format($r['disc'],2)]);
    } else {
        $allS = $db->table('sales');
        for ($i=0;$i<12;$i++){$m=date('Y-m',strtotime("-{$i} months"));$ms=array_filter($allS,fn($s)=>strpos($s['sale_date']??'',$m)===0);if(empty($ms))continue;fputcsv($fp,[date('M Y',strtotime($m.'-01')),count($ms),number_format(array_sum(array_column(array_values($ms),'grand_total')),2),number_format(array_sum(array_column(array_values($ms),'gst_amount')),2),number_format(array_sum(array_column(array_values($ms),'discount_amount')),2)]);}
    }
    fputcsv($fp, []);
    fputcsv($fp, ['=== TOP MEDICINES (by units sold) ===']);
    fputcsv($fp, ['Rank', 'Medicine Name', 'Units Sold']);
    if ($db instanceof MySQLDB) {
        $topM = $db->pdo()->query("SELECT m.name, SUM(si.quantity) qty FROM sales_items si JOIN medicines m ON m.id=si.medicine_id GROUP BY si.medicine_id ORDER BY qty DESC LIMIT 20")->fetchAll();
        $rank=1; foreach($topM as $r) fputcsv($fp,[$rank++,$r['name'],$r['qty']]);
    } else {
        $allItems=$db->table('sales_items'); $medM=[]; foreach($db->table('medicines') as $m) $medM[$m['id']]=$m;
        $medQty=[]; foreach($allItems as $si){$mid=$si['medicine_id']??0;$medQty[$mid]=($medQty[$mid]??0)+($si['quantity']??0);} arsort($medQty); $rank=1;
        foreach(array_slice($medQty,0,20,true) as $mid=>$u) fputcsv($fp,[$rank++,$medM[$mid]['name']??'?',$u]);
    }
    fclose($fp); exit;
}

// ── Excel Export ──
if (get('export') === 'excel') {
    $fname = 'DRXStore_Analytics_' . date('Y-m-d') . '.xls';
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    echo "DRXStore Analytics — " . storeName() . "\nExport Date\t" . date('d M Y H:i') . "\n\n=== MONTHLY SUMMARY ===\nMonth\tSales\tRevenue\tGST\tDiscount\n";
    if ($db instanceof MySQLDB) {
        $rows=$db->pdo()->query("SELECT DATE_FORMAT(sale_date,'%Y-%m') m,COUNT(*) cnt,SUM(grand_total) rev,SUM(gst_amount) gst,SUM(discount_amount) disc FROM sales GROUP BY m ORDER BY m DESC LIMIT 12")->fetchAll();
        foreach($rows as $r) echo date('M Y',strtotime($r['m'].'-01'))."\t".$r['cnt']."\t".number_format($r['rev'],2)."\t".number_format($r['gst'],2)."\t".number_format($r['disc'],2)."\n";
    } else {
        $allS=$db->table('sales'); for($i=0;$i<12;$i++){$m=date('Y-m',strtotime("-{$i} months"));$ms=array_filter($allS,fn($s)=>strpos($s['sale_date']??'',$m)===0);if(empty($ms))continue;echo date('M Y',strtotime($m.'-01'))."\t".count($ms)."\t".number_format(array_sum(array_column(array_values($ms),'grand_total')),2)."\t".number_format(array_sum(array_column(array_values($ms),'gst_amount')),2)."\t".number_format(array_sum(array_column(array_values($ms),'discount_amount')),2)."\n";}
    }
    echo "\n=== TOP MEDICINES ===\nRank\tMedicine\tUnits Sold\n";
    if ($db instanceof MySQLDB) {
        $topM=$db->pdo()->query("SELECT m.name,SUM(si.quantity) qty FROM sales_items si JOIN medicines m ON m.id=si.medicine_id GROUP BY si.medicine_id ORDER BY qty DESC LIMIT 20")->fetchAll();
        $rank=1; foreach($topM as $r) echo $rank++."\t".$r['name']."\t".$r['qty']."\n";
    } else {
        $allItems=$db->table('sales_items');$medM=[];foreach($db->table('medicines') as $m)$medM[$m['id']]=$m;$medQty=[];foreach($allItems as $si){$mid=$si['medicine_id']??0;$medQty[$mid]=($medQty[$mid]??0)+($si['quantity']??0);}arsort($medQty);$rank=1;foreach(array_slice($medQty,0,20,true) as $mid=>$u)echo $rank++."\t".($medM[$mid]['name']??'?')."\t".$u."\n";
    }
    exit;
}

// ── Analytics data — all SQL when MySQL ──
$thisMonth = date('Y-m');
$lastMonth = date('Y-m', strtotime('first day of last month'));

if ($db instanceof MySQLDB) {
    $pdo = $db->pdo();

    // Monthly stats
    $mRow  = $pdo->prepare("SELECT COUNT(*) cnt, COALESCE(SUM(grand_total),0) rev, COALESCE(SUM(gst_amount),0) gst FROM sales WHERE DATE_FORMAT(sale_date,'%Y-%m')=?");
    $mRow->execute([$thisMonth]); $mRow = $mRow->fetch();
    $lmRow = $pdo->prepare("SELECT COALESCE(SUM(grand_total),0) rev FROM sales WHERE DATE_FORMAT(sale_date,'%Y-%m')=?");
    $lmRow->execute([$lastMonth]); $lmRow = $lmRow->fetch();
    $mRev  = (float)$mRow['rev']; $lmRev = (float)$lmRow['rev'];
    $growth = $lmRev > 0 ? round(($mRev - $lmRev) / $lmRev * 100, 1) : 0;

    // Totals
    $totRow = $pdo->query("SELECT COUNT(*) cnt, COALESCE(SUM(grand_total),0) rev, COALESCE(SUM(gst_amount),0) gst FROM sales")->fetch();
    $totalRev = (float)$totRow['rev']; $totalGst = (float)$totRow['gst']; $totalSales = (int)$totRow['cnt'];
    $dayCount = (int)($pdo->query("SELECT COUNT(DISTINCT sale_date) FROM sales")->fetchColumn() ?: 1);
    $dailyAvg = $dayCount > 0 ? round($totalRev / $dayCount, 2) : 0;

    // Monthly chart (last 12 months)
    $monthRows = $pdo->query("SELECT DATE_FORMAT(sale_date,'%Y-%m') m, SUM(grand_total) rev FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY m ORDER BY m ASC")->fetchAll();
    $monthMap = []; foreach ($monthRows as $r) $monthMap[$r['m']] = (int)$r['rev'];
    $monthly = []; $mlbls = [];
    for ($i=11;$i>=0;$i--){$m=date('Y-m',strtotime("-{$i} months"));$mlbls[]=date('M',strtotime($m.'-01'));$monthly[]=$monthMap[$m]??0;}

    // Top medicines by units sold
    $topMedRows = $pdo->query("SELECT m.id, m.name, SUM(si.quantity) qty FROM sales_items si JOIN medicines m ON m.id=si.medicine_id GROUP BY si.medicine_id ORDER BY qty DESC LIMIT 10")->fetchAll();
    $topMeds = []; $medMap = [];
    foreach ($topMedRows as $r) { $topMeds[$r['id']] = (int)$r['qty']; $medMap[$r['id']] = ['name'=>$r['name']]; }
    $maxQ = !empty($topMeds) ? max($topMeds) : 1;

    // Top customers
    $topCustRows = $pdo->query("SELECT c.id, c.name, SUM(s.grand_total) spent FROM sales s JOIN customers c ON c.id=s.customer_id WHERE s.customer_id IS NOT NULL GROUP BY s.customer_id ORDER BY spent DESC LIMIT 5")->fetchAll();
    $topCusts = []; $custMap = [];
    foreach ($topCustRows as $r) { $topCusts[$r['id']] = (float)$r['spent']; $custMap[$r['id']] = ['name'=>$r['name']]; }
    $maxCS = !empty($topCusts) ? max($topCusts) : 1;

    // Payment methods
    $pmRows = $pdo->query("SELECT payment_method, COUNT(*) cnt FROM sales GROUP BY payment_method ORDER BY cnt DESC")->fetchAll();
    $payMeth = []; foreach ($pmRows as $r) $payMeth[ucfirst($r['payment_method']??'Cash')] = (int)$r['cnt'];

    // Monthly summary table
    $mSummary = $pdo->query("SELECT DATE_FORMAT(sale_date,'%Y-%m') m, COUNT(*) cnt, SUM(grand_total) rev, SUM(gst_amount) gst, SUM(discount_amount) disc FROM sales GROUP BY m ORDER BY m DESC LIMIT 12")->fetchAll();

} else {
    // JSON fallback — PHP aggregation
    $allSales = $db->table('sales'); $allItems = $db->table('sales_items');
    $medMap=[]; foreach($db->table('medicines') as $m) $medMap[$m['id']]=$m;
    $custMap=[]; foreach($db->table('customers') as $c) $custMap[$c['id']]=$c;
    $mSales=array_filter($allSales,fn($s)=>strpos($s['sale_date']??'',$thisMonth)===0);
    $lmSales=array_filter($allSales,fn($s)=>strpos($s['sale_date']??'',$lastMonth)===0);
    $mRev=(float)array_sum(array_column(array_values($mSales),'grand_total'));
    $lmRev=(float)array_sum(array_column(array_values($lmSales),'grand_total'));
    $growth=$lmRev>0?round(($mRev-$lmRev)/$lmRev*100,1):0;
    $totalRev=(float)array_sum(array_column($allSales,'grand_total'));
    $totalGst=(float)array_sum(array_column($allSales,'gst_amount'));
    $totalSales=count($allSales);
    $dayCount=count(array_unique(array_column($allSales,'sale_date')))?:1;
    $dailyAvg=$dayCount>0?round($totalRev/$dayCount,2):0;
    $monthly=[];$mlbls=[];for($i=11;$i>=0;$i--){$m=date('Y-m',strtotime("-{$i} months"));$mlbls[]=date('M',strtotime($m.'-01'));$ms=array_filter($allSales,fn($s)=>strpos($s['sale_date']??'',$m)===0);$monthly[]=(int)array_sum(array_column(array_values($ms),'grand_total'));}
    $medQty=[];foreach($allItems as $si){$mid=$si['medicine_id']??0;$medQty[$mid]=($medQty[$mid]??0)+($si['quantity']??0);}arsort($medQty);$topMeds=array_slice($medQty,0,10,true);$maxQ=!empty($topMeds)?max($topMeds):1;
    $custSpend=[];foreach($allSales as $s){if(!empty($s['customer_id']))$custSpend[$s['customer_id']]=($custSpend[$s['customer_id']]??0)+($s['grand_total']??0);}arsort($custSpend);$topCusts=array_slice($custSpend,0,5,true);$maxCS=!empty($topCusts)?max($topCusts):1;
    $payMeth=[];foreach($allSales as $s){$pm=ucfirst($s['payment_method']??'cash');$payMeth[$pm]=($payMeth[$pm]??0)+1;}
    $mSummary=[];for($i=0;$i<12;$i++){$m=date('Y-m',strtotime("-{$i} months"));$ms=array_filter($allSales,fn($s)=>strpos($s['sale_date']??'',$m)===0);if(empty($ms))continue;$mSummary[]=['m'=>$m,'cnt'=>count($ms),'rev'=>array_sum(array_column(array_values($ms),'grand_total')),'gst'=>array_sum(array_column(array_values($ms),'gst_amount')),'disc'=>array_sum(array_column(array_values($ms),'discount_amount'))];}
}

adminHeader('Analytics', 'reports');
?>
<div class="page-hdr">
  <div><div class="page-title"> Analytics &amp; Reports</div><div class="page-sub">Business intelligence overview</div></div>
  <div class="page-actions flex gap-2">
    <a href="index.php?p=reports&export=csv" class="btn btn-ghost btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      CSV
    </a>
    <a href="index.php?p=reports&export=excel" class="btn btn-primary btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Excel
    </a>
  </div>
</div>

<div class="stats-row">
  <div class="stat s-blue"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div><div class="stat-lbl">This Month</div><div class="stat-val"><?= money($mRev) ?></div><div class="stat-note" style="color:<?= $growth >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= $growth >= 0 ? '+' : '' ?><?= abs($growth) ?>% vs last</div></div>
  <div class="stat s-green"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><div class="stat-lbl">Total Revenue</div><div class="stat-val"><?= money($totalRev) ?></div><div class="stat-note"><?= $totalSales ?> sales</div></div>
  <div class="stat s-purple"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></div><div class="stat-lbl">GST Collected</div><div class="stat-val"><?= money($totalGst) ?></div></div>
  <div class="stat s-teal"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><polyline points="8 7 3 12 8 17"/></svg></div><div class="stat-lbl">Daily Avg</div><div class="stat-val"><?= money($dailyAvg) ?></div></div>
</div>

<div class="dash-grid">
  <div class="card col-2">
    <div class="card-hdr"><div class="card-title">Monthly Revenue — Last 12 Months</div></div>
    <div class="card-body">
      <div id="mChart" class="bar-chart" style="height:100px"></div>
      <div class="bar-lbls"><?php foreach ($mlbls as $l): ?><div class="bar-lbl"><?= e($l) ?></div><?php endforeach; ?></div>
    </div>
  </div>

  <div class="card">
    <div class="card-hdr"><div class="card-title">Top Medicines</div></div>
    <div class="card-body p0">
      <?php if (empty($topMeds)): ?><div class="empty-state" style="padding:20px"><p>No data yet</p></div><?php else: $ri = 1; foreach ($topMeds as $mid => $u): $med = $medMap[$mid] ?? null; ?>
      <div class="flex items-center gap-2" style="padding:9px 14px;border-bottom:1px solid var(--g3)">
        <div style="width:20px;height:20px;border-radius:5px;background:var(--navy-lt);color:var(--navy);font-size:.68rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= $ri++ ?></div>
        <div style="flex:1;min-width:0"><div class="fw-600 truncate text-sm"><?= e($med['name'] ?? '?') ?></div><div class="prog mt-1"><div class="prog-bar" style="width:<?= round($u / $maxQ * 100) ?>%"></div></div></div>
        <span class="chip chip-blue"><?= $u ?> units</span>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-hdr"><div class="card-title">Top Customers</div></div>
    <div class="card-body p0">
      <?php if (empty($topCusts)): ?><div class="empty-state" style="padding:20px"><p>No data</p></div><?php else: $ri = 1; foreach ($topCusts as $cid => $spent): $c = $custMap[$cid] ?? null; ?>
      <div class="flex items-center gap-2" style="padding:9px 14px;border-bottom:1px solid var(--g3)">
        <div style="width:20px;height:20px;border-radius:50%;background:#f3e5f5;color:#7b2d8b;font-size:.68rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= $ri++ ?></div>
        <div style="flex:1;min-width:0"><div class="fw-600 text-sm"><?= e($c['name'] ?? '?') ?></div><div class="prog mt-1"><div class="prog-bar" style="width:<?= round($spent / $maxCS * 100) ?>%;background:#7b2d8b"></div></div></div>
        <span class="chip chip-purple"><?= money($spent) ?></span>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-hdr"><div class="card-title">Payment Methods</div></div>
    <div class="card-body p0">
      <?php $pmTotal = array_sum($payMeth); foreach ($payMeth as $pm => $cnt): $pct = $pmTotal > 0 ? round($cnt / $pmTotal * 100) : 0; ?>
      <div style="padding:9px 14px;border-bottom:1px solid var(--g3)">
        <div class="flex justify-between mb-1"><span class="fw-600 text-sm"><?= e($pm) ?></span><span class="text-sm text-muted"><?= $cnt ?> (<?= $pct ?>%)</span></div>
        <div class="prog"><div class="prog-bar" style="width:<?= $pct ?>%"></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card col-2">
    <div class="card-hdr"><div class="card-title">Monthly Summary Table</div></div>
    <div class="card-body p0">
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>Month</th><th class="tc">Sales</th><th class="tr">Revenue</th><th class="tr">GST</th><th class="tr">Discount</th></tr></thead>
        <tbody>
        <?php foreach ($mSummary as $row): ?>
        <tr>
          <td class="fw-600"><?= date('M Y', strtotime(($row['m'] ?? date('Y-m')).'-01')) ?></td>
          <td class="tc"><span class="chip chip-blue"><?= (int)($row['cnt']??0) ?></span></td>
          <td class="tr fw-600"><?= money($row['rev']??0) ?></td>
          <td class="tr text-muted"><?= money($row['gst']??0) ?></td>
          <td class="tr text-muted"><?= money($row['disc']??0) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>

<?php adminFooter(); ?>
<script>document.addEventListener('DOMContentLoaded',function(){drawBarChart('mChart', <?= json_encode($monthly) ?>, '#0a2342');});</script>
