<?php
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php'; require_once ROOT.'/views/layout_admin.php'; requireAdmin();
$preview=[]; $errors=[];
if(isset($_GET['sample'])){header('Content-Type: text/csv');header('Content-Disposition: attachment; filename="sample_medicines.csv"');echo "name,generic,company,category,hsn,gst,rack\nParacetamol 500mg,Acetaminophen,Sun Pharma,Tablet,30049099,12,A-1\nAmoxicillin 250mg,Amoxicillin,Cipla,Capsule,30041099,12,B-3\nOmeprazole 20mg,Omeprazole,Mankind,Capsule,30049099,12,C-2\nCetirizine 10mg,Cetirizine HCL,Abbott,Tablet,30049099,12,A-5\n";exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $act=post('action');
    if($act==='preview'&&isset($_FILES['csv_file'])){
        $f=$_FILES['csv_file'];
        if($f['error']!==UPLOAD_ERR_OK){$errors[]='Upload failed.';}
        elseif(!in_array(strtolower(pathinfo($f['name'],PATHINFO_EXTENSION)),['csv','txt'])){$errors[]='CSV files only.';}
        else{
            $handle=fopen($f['tmp_name'],'r'); $header=fgetcsv($handle);
            $header=array_map(fn($h)=>strtolower(trim($h??'')),$header);
            while(($row=fgetcsv($handle))!==false){
                if(count($row)<2)continue;
                $mapped=[]; foreach($header as $i=>$col) $mapped[$col]=trim($row[$i]??'');
                $name=$mapped['name']??$mapped['medicine_name']??$mapped['medicine']??($row[0]??'');
                if(!trim($name))continue;
                $preview[]=['name'=>trim($name),'generic'=>trim($mapped['generic']??$mapped['generic_name']??($row[1]??'')),'company'=>trim($mapped['company']??$mapped['manufacturer']??($row[2]??'')),'category'=>trim($mapped['category']??($row[3]??'')),'hsn'=>trim($mapped['hsn']??$mapped['hsn_code']??($row[4]??'')),'gst'=>is_numeric($v=($mapped['gst']??$mapped['gst_percent']??'12'))?(float)$v:12,'rack'=>trim($mapped['rack']??$mapped['rack_location']??($row[6]??''))];
            }
            fclose($handle);
            if(empty($preview)){$errors[]='No valid rows found.';}else{$_SESSION['csv_prev']=$preview;}
        }
    }
    if($act==='confirm'&&!empty($_SESSION['csv_prev'])){
        $rows=$_SESSION['csv_prev']; $imp=0; $dup=0;
        foreach($rows as $r){
            if($db->findOne('medicines',fn($m)=>strtolower($m['name']??'')===strtolower($r['name']))){$dup++;continue;}
            $db->insert('medicines',['name'=>$r['name'],'generic_name'=>$r['generic'],'company'=>$r['company'],'category'=>$r['category'],'hsn_code'=>$r['hsn'],'gst_percent'=>$r['gst'],'rack_location'=>$r['rack'],'created_at'=>date('Y-m-d H:i:s')]);
            $imp++;
        }
        unset($_SESSION['csv_prev']);
        setFlash('success',"Import done: {$imp} added, {$dup} skipped (duplicates).");
        header('Location: index.php?p=medicines'); exit;
    }
    if($act==='cancel'){unset($_SESSION['csv_prev']);header('Location: index.php?p=medicines');exit;}
}
if(!empty($_SESSION['csv_prev'])&&empty($preview))$preview=$_SESSION['csv_prev'];
adminHeader('Import Medicines','import_med');
?>
<div class="page-hdr"><div><div class="page-title"> Import Medicines</div><div class="page-sub">Bulk import from CSV file</div></div><a href="index.php?p=medicines" class="btn btn-ghost">Back</a></div>
<?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>
<?php if(empty($preview)):?>
<div class="dash-grid">
  <div class="card"><div class="card-hdr"><div class="card-title">Upload CSV</div></div><div class="card-body">
    <form method="POST" enctype="multipart/form-data"><?=csrfField()?><input type="hidden" name="action" value="preview">
      <div class="form-group"><label class="form-label">Select CSV File <span class="req">*</span></label><input class="form-control" type="file" name="csv_file" accept=".csv,.txt" required><div class="form-hint">Max 2MB. CSV or TXT format.</div></div>
      <button type="submit" class="btn btn-primary">Preview Import </button>
    </form>
  </div></div>
  <div class="card"><div class="card-hdr"><div class="card-title">CSV Format Guide</div></div><div class="card-body">
    <div style="background:var(--g1);padding:12px;border-radius:var(--rl);font-family:monospace;font-size:.76rem;line-height:1.8;overflow-x:auto;white-space:pre">name,generic,company,category,hsn,gst,rack
Paracetamol 500mg,Acetaminophen,Sun Pharma,Tablet,30049099,12,A-1
Amoxicillin 250mg,Amoxicillin,Cipla,Capsule,30041099,12,B-3</div>
    <div style="margin-top:12px;font-size:.78rem"><strong>Accepted column names:</strong><br>name, generic/generic_name, company/manufacturer, category, hsn/hsn_code, gst/gst_percent, rack/rack_location</div>
    <a href="index.php?p=import_med&sample=1" class="btn btn-ghost btn-sm mt-2">Download Sample CSV</a>
  </div></div>
</div>
<?php else:?>
<div class="card">
  <div class="card-hdr">
    <div><div class="card-title">Preview — <?=count($preview)?> row(s) ready to import</div><div style="font-size:.76rem;color:var(--g5)">Duplicates will be skipped automatically.</div></div>
    <form method="POST"><?=csrfField()?><input type="hidden" name="action" value="cancel"><button type="submit" class="btn btn-ghost btn-sm">Cancel</button></form>
  </div>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>Name</th><th>Generic</th><th>Company</th><th>Form</th><th>GST%</th><th>Rack</th></tr></thead>
    <tbody>
    <?php foreach(array_slice($preview,0,50) as $r):?>
    <tr><td class="fw-600"><?=e($r['name'])?></td><td class="text-sm"><?=e($r['generic']?:'—')?></td><td class="text-sm"><?=e($r['company']?:'—')?></td><td><?=$r['category']?'<span class="chip chip-teal">'.e($r['category']).'</span>':'—'?></td><td class="text-sm"><?=e($r['gst'])?>%</td><td class="text-sm"><?=e($r['rack']?:'—')?></td></tr>
    <?php endforeach; if(count($preview)>50):?><tr><td colspan="6" class="tc text-sm text-muted" style="padding:10px">... and <?=count($preview)-50?> more rows</td></tr><?php endif;?>
    </tbody>
  </table></div>
  <div style="padding:12px 16px;border-top:1px solid var(--g3);display:flex;gap:8px;flex-wrap:wrap">
    <form method="POST"><?=csrfField()?><input type="hidden" name="action" value="confirm"><button type="submit" class="btn btn-success"> Confirm Import (<?=count($preview)?> medicines)</button></form>
    <form method="POST"><?=csrfField()?><input type="hidden" name="action" value="cancel"><button type="submit" class="btn btn-ghost">Cancel</button></form>
  </div>
</div>
<?php endif;?>
<?php adminFooter();?>
