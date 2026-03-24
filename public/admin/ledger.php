<?php
/**
 * DRXStore - Account Ledger v2.0
 * SQL-powered — paginated, fast at 100k+ entries
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_admin.php'; requireAdmin();
$fd=get('fd'); $td=get('td',date('Y-m-d')); $ftype=get('type','all');

// ── Build entries — SQL if MySQL, PHP arrays if JSON ──
function buildLedgerEntries($db, $fd, $td, $ftype): array {
    $entries = [];
    if ($db instanceof MySQLDB) {
        $pdo = $db->pdo();
        $dateWhere = "AND sale_date BETWEEN ? AND ?";
        $dateVals  = [$fd ?: '2000-01-01', $td];

        if ($ftype === 'all' || $ftype === 'sale') {
            $rows = $pdo->prepare("SELECT s.id, s.sale_date, s.grand_total, s.payment_method,
                COALESCE(c.name,'Walk-in') cname
                FROM sales s LEFT JOIN customers c ON c.id=s.customer_id
                WHERE 1=1 {$dateWhere} ORDER BY s.sale_date DESC, s.id DESC");
            $rows->execute($dateVals);
            foreach ($rows->fetchAll() as $s)
                $entries[] = ['date'=>$s['sale_date'],'type'=>'sale','ref'=>invNo($s['id']),'party'=>$s['cname'],'debit'=>0,'credit'=>(float)$s['grand_total'],'note'=>'Sale | '.ucfirst($s['payment_method']??'cash')];
        }
        if ($ftype === 'all' || $ftype === 'purchase') {
            $rows = $pdo->prepare("SELECT po.id, po.po_date, po.total, COALESCE(sup.name,'—') sname
                FROM purchase_orders po LEFT JOIN suppliers sup ON sup.id=po.supplier_id
                WHERE po.status='received' AND po.po_date BETWEEN ? AND ? ORDER BY po.po_date DESC, po.id DESC");
            $rows->execute($dateVals);
            foreach ($rows->fetchAll() as $p)
                $entries[] = ['date'=>$p['po_date'],'type'=>'purchase','ref'=>poNo($p['id']),'party'=>$p['sname'],'debit'=>(float)$p['total'],'credit'=>0,'note'=>'Purchase Order'];
        }
        if ($ftype === 'all' || $ftype === 'return') {
            $rows = $pdo->prepare("SELECT r.id, DATE(r.created_at) rdate, r.refund_amount, r.reason,
                COALESCE(c.name,'—') cname
                FROM returns r
                LEFT JOIN sales s ON s.id=r.sale_id
                LEFT JOIN customers c ON c.id=s.customer_id
                WHERE DATE(r.created_at) BETWEEN ? AND ? ORDER BY r.created_at DESC");
            $rows->execute($dateVals);
            foreach ($rows->fetchAll() as $r)
                $entries[] = ['date'=>$r['rdate'],'type'=>'return','ref'=>'RET-'.str_pad($r['id'],4,'0',STR_PAD_LEFT),'party'=>$r['cname'],'debit'=>(float)$r['refund_amount'],'credit'=>0,'note'=>'Return: '.($r['reason']??'')];
        }
    } else {
        // JSON fallback — PHP aggregation
        $custMap=[]; foreach($db->table('customers') as $c_) $custMap[$c_['id']]=$c_;
        $supMap=[];  foreach($db->table('suppliers')  as $s) $supMap[$s['id']]=$s;
        foreach($db->find('sales',function($s)use($fd,$td){$d=$s['sale_date']??'';return($fd?$d>=$fd:true)&&$d<=$td;}) as $s){
            $cust=$custMap[$s['customer_id']??0]??null;
            $entries[]=['date'=>$s['sale_date']??'','type'=>'sale','ref'=>invNo($s['id']),'party'=>$cust['name']??'Walk-in','debit'=>0,'credit'=>(float)($s['grand_total']??0),'note'=>'Sale | '.ucfirst($s['payment_method']??'cash')];
        }
        foreach($db->find('purchase_orders',function($p)use($fd,$td){$d=$p['po_date']??'';return($fd?$d>=$fd:true)&&$d<=$td&&($p['status']??'')==='received';}) as $p){
            $sup=$supMap[$p['supplier_id']??0]??null;
            $entries[]=['date'=>$p['po_date']??'','type'=>'purchase','ref'=>poNo($p['id']),'party'=>$sup['name']??'—','debit'=>(float)($p['total']??0),'credit'=>0,'note'=>'Purchase Order'];
        }
        foreach($db->find('returns',function($r)use($fd,$td){$d=substr($r['created_at']??'',0,10);return($fd?$d>=$fd:true)&&$d<=$td;}) as $r){
            $s=$db->findOne('sales',fn($s)=>$s['id']==($r['sale_id']??0));
            $cust=$s?($custMap[$s['customer_id']??0]??null):null;
            $entries[]=['date'=>substr($r['created_at']??'',0,10),'type'=>'return','ref'=>'RET-'.str_pad($r['id'],4,'0',STR_PAD_LEFT),'party'=>$cust['name']??'—','debit'=>(float)($r['refund_amount']??0),'credit'=>0,'note'=>'Return: '.($r['reason']??'')];
        }
        if($ftype!=='all')$entries=array_values(array_filter($entries,fn($e)=>$e['type']===$ftype));
        usort($entries,fn($a,$b)=>strcmp($b['date'],$a['date']));
    }
    return $entries;
}

$entries = buildLedgerEntries($db, $fd, $td, $ftype);
// Sort all entries by date desc (for mixed SQL results)
usort($entries, fn($a,$b)=>strcmp($b['date'],$a['date']));

$tCr=array_sum(array_column($entries,'credit'));
$tDb=array_sum(array_column($entries,'debit'));
$net=$tCr-$tDb;

// ── Exports ──
if(get('export')==='csv'){
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="DRXStore_Ledger_'.date('Y-m-d').'.csv"');
    $fp=fopen('php://output','w');
    fputcsv($fp,['DRXStore Account Ledger — '.storeName()]);
    fputcsv($fp,['Export Date',date('d M Y H:i')]);
    fputcsv($fp,['Period',$fd?:'-','to',$td,'Type',$ftype]);
    fputcsv($fp,[]);
    fputcsv($fp,['Date','Reference','Type','Party','Note','Debit','Credit']);
    foreach($entries as $e) fputcsv($fp,[dateF($e['date']),$e['ref'],ucfirst($e['type']),$e['party'],$e['note'],$e['debit']>0?number_format($e['debit'],2):'',$e['credit']>0?number_format($e['credit'],2):'']);
    fputcsv($fp,['','','','','TOTALS',number_format($tDb,2),number_format($tCr,2)]);
    fputcsv($fp,['','','','','NET',number_format($net,2),'']);
    fclose($fp); exit;
}
if(get('export')==='excel'){
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="DRXStore_Ledger_'.date('Y-m-d').'.xls"');
    echo "DRXStore Ledger\nExport\t".date('d M Y H:i')."\n\nDate\tRef\tType\tParty\tNote\tDebit\tCredit\n";
    foreach($entries as $e) echo dateF($e['date'])."\t".$e['ref']."\t".ucfirst($e['type'])."\t".$e['party']."\t".$e['note']."\t".($e['debit']>0?number_format($e['debit'],2):'')."\t".($e['credit']>0?number_format($e['credit'],2):'')."\n";
    echo "\t\t\t\tTOTALS\t".number_format($tDb,2)."\t".number_format($tCr,2)."\n\t\t\t\tNET\t".number_format($net,2)."\n";
    exit;
}

$pag=paginate($entries,max(1,getInt('page',1)),PER_PAGE);
adminHeader('Ledger','ledger');
?>
<div class="page-hdr"><div><div class="page-title">Account Ledger</div></div>
<div class="page-actions flex gap-2">
  <a href="index.php?p=ledger&type=<?=urlencode($ftype)?>&fd=<?=urlencode($fd)?>&td=<?=urlencode($td)?>&export=csv" class="btn btn-ghost btn-sm">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    CSV
  </a>
  <a href="index.php?p=ledger&type=<?=urlencode($ftype)?>&fd=<?=urlencode($fd)?>&td=<?=urlencode($td)?>&export=excel" class="btn btn-primary btn-sm">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Excel
  </a>
</div></div>
<div class="card mb-2"><div class="card-body" style="padding:10px 16px">
  <form method="GET" class="flex gap-2 flex-wrap items-end">
    <input type="hidden" name="p" value="ledger">
    <div><label class="form-label" style="font-size:.7rem">Type</label><select class="form-control" name="type" style="border-radius:6px"><option value="all" <?=$ftype==='all'?'selected':''?>>All</option><option value="sale" <?=$ftype==='sale'?'selected':''?>>Sales</option><option value="purchase" <?=$ftype==='purchase'?'selected':''?>>Purchases</option><option value="return" <?=$ftype==='return'?'selected':''?>>Returns</option></select></div>
    <div><label class="form-label" style="font-size:.7rem">From</label><input class="form-control" type="date" name="fd" value="<?=e($fd)?>" style="border-radius:6px;padding:6px 10px"></div>
    <div><label class="form-label" style="font-size:.7rem">To</label><input class="form-control" type="date" name="td" value="<?=e($td)?>" style="border-radius:6px;padding:6px 10px"></div>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button><a href="index.php?p=ledger" class="btn btn-ghost btn-sm">Reset</a>
  </form>
</div></div>
<div class="stats-row" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
  <div class="stat s-green"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><div class="stat-lbl">Income</div><div class="stat-val"><?=money($tCr)?></div></div>
  <div class="stat s-red"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/></svg></div><div class="stat-lbl">Expense</div><div class="stat-val"><?=money($tDb)?></div></div>
  <div class="stat <?=$net>=0?'s-blue':'s-orange'?>"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div><div class="stat-lbl">Net Balance</div><div class="stat-val"><?=money($net)?></div></div>
  <div class="stat s-teal"><div class="stat-ico"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div><div class="stat-lbl">Entries</div><div class="stat-val"><?=count($entries)?></div></div>
</div>
<div class="card"><div class="card-body p0">
  <?php if(empty($pag['items'])):?><div class="empty-state"><p>No transactions found.</p></div><?php else:?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>Date</th><th>Reference</th><th>Type</th><th>Party</th><th>Note</th><th class="tr">Debit</th><th class="tr">Credit</th></tr></thead>
    <tbody>
    <?php $chips=['sale'=>'chip-green','purchase'=>'chip-orange','return'=>'chip-red']; foreach($pag['items'] as $e):?>
    <tr>
      <td class="text-sm"><?=dateF($e['date'])?></td>
      <td><span class="chip chip-blue"><?=e($e['ref'])?></span></td>
      <td><span class="chip <?=$chips[$e['type']]??'chip-gray'?>"><?=ucfirst($e['type'])?></span></td>
      <td class="fw-600"><?=e($e['party'])?></td>
      <td class="text-sm text-muted truncate" style="max-width:180px"><?=e($e['note'])?></td>
      <td class="tr <?=$e['debit']>0?'text-red fw-600':''?>"><?=$e['debit']>0?money($e['debit']):'—'?></td>
      <td class="tr <?=$e['credit']>0?'text-green fw-600':''?>"><?=$e['credit']>0?money($e['credit']):'—'?></td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
  <?=pagerHtml($pag,'index.php?p=ledger&type='.e($ftype).'&fd='.e($fd).'&td='.e($td))?>
  <?php endif;?>
</div></div>
<?php adminFooter();?>
