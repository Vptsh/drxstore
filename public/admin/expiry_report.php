<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_admin.php'; requireStaff();
$today=date('Y-m-d'); $d30=date('Y-m-d',strtotime('+30 days')); $d90=date('Y-m-d',strtotime('+90 days'));
$medMap=[]; foreach($db->table('medicines') as $m) $medMap[$m['id']]=$m;
$supMap=[]; foreach($db->table('suppliers') as $s) $supMap[$s['id']]=$s;
$all=$db->table('batches');
$expired=array_values(array_filter($all,fn($b)=>($b['expiry_date']??'')<$today&&($b['quantity']??0)>0));
$exp30  =array_values(array_filter($all,fn($b)=>($b['expiry_date']??'')>=$today&&($b['expiry_date']??'')<=$d30&&($b['quantity']??0)>0));
$exp90  =array_values(array_filter($all,fn($b)=>($b['expiry_date']??'')>$d30&&($b['expiry_date']??'')<=$d90&&($b['quantity']??0)>0));
$good   =array_values(array_filter($all,fn($b)=>($b['expiry_date']??'')>$d90&&($b['quantity']??0)>0));

// ── CSV Export ──
if(get('export')==='csv'){
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="DRXStore_ExpiryReport_'.date('Y-m-d').'.csv"');
    $fp=fopen('php://output','w');
    fputcsv($fp,['DRXStore Expiry Report — '.storeName()]);
    fputcsv($fp,['Export Date: '.date('d M Y H:i:s')]);
    fputcsv($fp,[]);
    foreach(['Expired'=>$expired,'Expiring within 30 days'=>$exp30,'Expiring 31-90 days'=>$exp90,'Good (>90 days)'=>$good] as $lbl=>$rows){
        if(empty($rows)) continue;
        fputcsv($fp,['=== '.$lbl.' ('.count($rows).' items) ===']);
        fputcsv($fp,['Medicine','Batch No','Expiry Date','Days Left','Stock Qty','Supplier']);
        foreach($rows as $b){
            $med=$medMap[$b['medicine_id']??0]??null;
            $sup=$b['supplier_id']?($supMap[$b['supplier_id']]??null):null;
            $d=daysLeft($b['expiry_date']??'');
            fputcsv($fp,[
                $med['name']??'—', $b['batch_no']??'', $b['expiry_date']??'',
                $d<0?abs($d).'d ago':$d.'d', (int)($b['quantity']??0), $sup['name']??'—'
            ]);
        }
        fputcsv($fp,[]);
    }
    fclose($fp); exit;
}

// ── Excel Export ──
if(get('export')==='excel'){
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="DRXStore_ExpiryReport_'.date('Y-m-d').'.xls"');
    echo "DRXStore Expiry Report — ".storeName()."\n";
    echo "Export Date: ".date('d M Y H:i:s')."\n\n";
    foreach(['Expired'=>$expired,'Expiring within 30 days'=>$exp30,'Expiring 31-90 days'=>$exp90,'Good (>90 days)'=>$good] as $lbl=>$rows){
        if(empty($rows)) continue;
        echo "=== ".$lbl." (".count($rows)." items) ===\n";
        echo "Medicine\tBatch No\tExpiry Date\tDays Left\tStock Qty\tSupplier\n";
        foreach($rows as $b){
            $med=$medMap[$b['medicine_id']??0]??null;
            $sup=$b['supplier_id']?($supMap[$b['supplier_id']]??null):null;
            $d=daysLeft($b['expiry_date']??'');
            echo ($med['name']??'—')."\t".($b['batch_no']??'')."\t".($b['expiry_date']??'')."\t".($d<0?abs($d).'d ago':$d.'d')."\t".(int)($b['quantity']??0)."\t".($sup['name']??'—')."\n";
        }
        echo "\n";
    }
    exit;
}

function expTable(array $batches, array $medMap, array $supMap): void {
    if(empty($batches)){echo '<div class="empty-state" style="padding:24px"><p>None</p></div>';return;}
    echo '<div class="table-wrap"><table class="tbl"><thead><tr><th>Medicine</th><th>Batch</th><th>Expiry</th><th>Days Left</th><th>Stock</th><th>Supplier</th></tr></thead><tbody>';
    foreach($batches as $b){
        $med=$medMap[$b['medicine_id']??0]??null;
        $sup=$b['supplier_id']?($supMap[$b['supplier_id']]??null):null;
        $d=daysLeft($b['expiry_date']??'');
        echo '<tr><td class="fw-600">'.e($med['name']??'—').'</td>';
        echo '<td><code class="mono">'.e($b['batch_no']??'').'</code></td>';
        echo '<td>'.dateF($b['expiry_date']??'').'</td>';
        echo '<td><span class="chip '.($d<0?'chip-red':($d<=30?'chip-orange':'chip-yellow')).'">' .($d<0?abs($d).'d ago':$d.'d').'</span></td>';
        echo '<td>'.stockChip((int)($b['quantity']??0)).'</td>';
        echo '<td class="text-sm text-muted">'.e($sup['name']??'—').'</td></tr>';
    }
    echo '</tbody></table></div>';
}
adminHeader('Expiry Report','expiry');
?>
<div class="page-hdr">
  <div><div class="page-title"> Expiry Report</div><div class="page-sub"><?=date('d M Y')?></div></div>
  <div class="page-actions flex gap-2">
    <a href="index.php?p=expiry&export=csv" class="btn btn-ghost btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Export CSV
    </a>
    <a href="index.php?p=expiry&export=excel" class="btn btn-primary btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Export Excel
    </a>
  </div>
</div>
<div class="stats-row">
  <div class="stat s-red"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><div class="stat-lbl">Expired</div><div class="stat-val"><?=count($expired)?></div><div class="stat-note">Needs action</div></div>
  <div class="stat s-orange"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><div class="stat-lbl">Expires 30d</div><div class="stat-val"><?=count($exp30)?></div><div class="stat-note">Urgent</div></div>
  <div class="stat s-blue"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><div class="stat-lbl">Expires 90d</div><div class="stat-val"><?=count($exp90)?></div><div class="stat-note">Plan ahead</div></div>
  <div class="stat s-green"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div><div class="stat-lbl">Good Stock</div><div class="stat-val"><?=count($good)?></div><div class="stat-note">&gt;90 days</div></div>
</div>
<?php if(!empty($expired)):?><div class="card mb-2"><div class="card-hdr" style="background:var(--red-lt)"><div class="card-title" style="color:var(--red)"> Expired (<?=count($expired)?>)</div></div><?php expTable($expired,$medMap,$supMap);?></div><?php endif;?>
<?php if(!empty($exp30)):?><div class="card mb-2"><div class="card-hdr" style="background:var(--orange-lt)"><div class="card-title" style="color:var(--orange)"> Expiring 30 days (<?=count($exp30)?>)</div></div><?php expTable($exp30,$medMap,$supMap);?></div><?php endif;?>
<?php if(!empty($exp90)):?><div class="card mb-2"><div class="card-hdr"><div class="card-title"> Expiring 31–90 days (<?=count($exp90)?>)</div></div><?php expTable($exp90,$medMap,$supMap);?></div><?php endif;?>
<?php if(!empty($good)):?><div class="card"><div class="card-hdr"><div class="card-title"> Good — Beyond 90 days (<?=count($good)?>)</div></div><?php expTable($good,$medMap,$supMap);?></div><?php endif;?>
<?php if(empty($expired)&&empty($exp30)&&empty($exp90)&&empty($good)):?><div class="empty-state"><p>No batches with stock found.</p></div><?php endif;?>
<?php adminFooter();?>
