<?php
/**
 * DRXStore - Software Update System
 * Safely applies a new version ZIP over the current installation
 * Developed by Vineet
 */
if (!defined('ROOT')) define('ROOT', dirname(dirname(dirname(__FILE__))));
require_once ROOT.'/config/app.php';
require_once ROOT.'/views/layout_admin.php';
requireAdmin(); // Admin only

$errors   = [];
$warnings = [];
$result   = null;

// ── Helper: recursively list PHP files in a directory ──
function listPhpFiles(string $dir, string $base=''): array {
    $files = []; $base = $base ?: $dir;
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        if($f->isFile()) $files[] = str_replace('\\','/',substr($f->getPathname(), strlen($base)+1));
    }
    sort($files); return $files;
}

// ── Helper: extract ZIP to temp dir ──
function extractUpdateZip(string $zipPath): array {
    if (!class_exists('ZipArchive')) return ['ok'=>false,'msg'=>'ZipArchive PHP extension not available.'];
    $zip = new ZipArchive;
    if ($zip->open($zipPath) !== true) return ['ok'=>false,'msg'=>'Cannot open ZIP file. File may be corrupt.'];
    $tmpDir = sys_get_temp_dir().'/drxupdate_'.time();
    @mkdir($tmpDir, 0755, true);
    $zip->extractTo($tmpDir);
    $zip->close();
    // Find the root folder inside the zip (may be drxstore_fixed/ or similar)
    $items = glob($tmpDir.'/*');
    $root  = null;
    foreach($items as $i) {
        if(is_dir($i) && file_exists($i.'/index.php') && file_exists($i.'/config/app.php')) {
            $root = $i; break;
        }
    }
    if(!$root) {
        // Maybe index.php is directly inside tmpDir
        if(file_exists($tmpDir.'/index.php') && file_exists($tmpDir.'/config/app.php')) {
            $root = $tmpDir;
        } else {
            return ['ok'=>false,'msg'=>'Could not find a valid DRXStore installation inside the ZIP. Expected index.php + config/app.php.'];
        }
    }
    return ['ok'=>true,'root'=>$root,'tmp'=>$tmpDir];
}

// ── Helper: read APP_VERSION from a config/app.php ──
function readVersionFromConfig(string $configPath): string {
    if(!file_exists($configPath)) return 'unknown';
    $content = file_get_contents($configPath);
    if(preg_match("/APP_VERSION['\s,)=]+['\"]([^'\"]+)['\"]/", $content, $m)) return $m[1];
    return 'unknown';
}

// ── Helper: read table schema from MySQLDB.php ──
function readSchemaFromMySQLDB(string $path): array {
    if(!file_exists($path)) return [];
    $content = file_get_contents($path);
    // Extract tableSchema array content
    if(!preg_match('/private static function tableSchema\(\): array \{.*?return \[(.*?)\];\s*\}/s', $content, $m)) return [];
    preg_match_all("/'([a-z_]+)'\s*=>\s*\[([^\]]+)\]/s", $m[1], $tables, PREG_SET_ORDER);
    $schema = [];
    foreach($tables as $t) {
        $tname = $t[1];
        preg_match_all("/'([a-z_]+)'\s*=>\s*'([^']+)'/", $t[2], $cols, PREG_SET_ORDER);
        $schema[$tname] = [];
        foreach($cols as $c) $schema[$tname][$c[1]] = $c[2];
    }
    return $schema;
}

// ── Process uploaded update ──
if($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='upload_update') {
    verifyCsrf();
    if(!isset($_FILES['update_zip']) || $_FILES['update_zip']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'No file uploaded or upload error: '.($_FILES['update_zip']['error']??'unknown');
    } else {
        $file = $_FILES['update_zip'];
        // Validate it's a ZIP
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if($ext !== 'zip') $errors[] = 'Only .zip files are accepted.';
        elseif($file['size'] < 50000) $errors[] = 'File too small to be a valid DRXStore package.';
        elseif($file['size'] > 20*1024*1024) $errors[] = 'File too large (max 20 MB).';
        else {
            // Save to temp
            $tmpZip = sys_get_temp_dir().'/drxupdate_upload_'.time().'.zip';
            if(!move_uploaded_file($file['tmp_name'], $tmpZip)) {
                $errors[] = 'Failed to save uploaded file. Check server permissions.';
            } else {
                // Extract and analyse
                $extract = extractUpdateZip($tmpZip);
                if(!$extract['ok']) {
                    $errors[] = $extract['msg'];
                } else {
                    $newRoot    = $extract['root'];
                    $newVersion = readVersionFromConfig($newRoot.'/config/app.php');
                    $curVersion = APP_VERSION;

                    // ── Version check ──
                    if($newVersion === 'unknown') $warnings[] = 'Could not detect version in new package. Proceeding with caution.';
                    if(version_compare($newVersion, $curVersion, '<=') && $newVersion !== 'unknown') {
                        $warnings[] = "New package version ({$newVersion}) is same as or older than current ({$curVersion}). Are you sure you want to continue?";
                    }

                    // ── Schema conflict check ──
                    $newSchema = readSchemaFromMySQLDB($newRoot.'/helpers/MySQLDB.php');
                    $curSchema = readSchemaFromMySQLDB(ROOT.'/helpers/MySQLDB.php');
                    $schemaChanges = [];
                    foreach($newSchema as $table => $cols) {
                        if(!isset($curSchema[$table])) {
                            $schemaChanges[] = ['type'=>'new_table','table'=>$table,'msg'=>"New table '{$table}' will be added (auto-migrated on boot)"];
                        } else {
                            foreach($cols as $col => $def) {
                                if(!isset($curSchema[$table][$col])) {
                                    $schemaChanges[] = ['type'=>'new_col','table'=>$table,'col'=>$col,'msg'=>"New column '{$table}.{$col}' will be added (auto-migrated on boot)"];
                                } elseif($curSchema[$table][$col] !== $def) {
                                    $schemaChanges[] = ['type'=>'col_change','table'=>$table,'col'=>$col,'msg'=>"Column '{$table}.{$col}' definition changed — auto-migration will handle it"];
                                }
                            }
                        }
                    }

                    // ── File inventory ──
                    $newFiles = listPhpFiles($newRoot);
                    $curFiles = listPhpFiles(ROOT);
                    $addedFiles   = array_values(array_diff($newFiles, $curFiles));
                    $removedFiles = array_values(array_diff($curFiles, $newFiles));

                    // ── Protect data/ folder — never touch it ──
                    $newFiles     = array_filter($newFiles, fn($f) => strpos($f,'data/') !== 0);
                    $removedFiles = array_values(array_filter($removedFiles, fn($f) => strpos($f,'data/') !== 0));

                    // ── Store analysis result in session for apply step ──
                    $_SESSION['_upd_zip']    = $tmpZip;
                    $_SESSION['_upd_root']   = $newRoot;
                    $_SESSION['_upd_tmp']    = $extract['tmp'];
                    $_SESSION['_upd_newver'] = $newVersion;
                    $_SESSION['_upd_curver'] = $curVersion;

                    $result = [
                        'step'         => 'confirm',
                        'newVersion'   => $newVersion,
                        'curVersion'   => $curVersion,
                        'newFiles'     => $newFiles,
                        'addedFiles'   => $addedFiles,
                        'removedFiles' => $removedFiles,
                        'schemaChanges'=> $schemaChanges,
                        'fileCount'    => count($newFiles),
                    ];
                }
            }
        }
    }
}

// ── Apply update ──
if($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='apply_update') {
    verifyCsrf();
    $newRoot = $_SESSION['_upd_root'] ?? '';
    $newVer  = $_SESSION['_upd_newver'] ?? '';

    if(!$newRoot || !is_dir($newRoot) || !file_exists($newRoot.'/config/app.php')) {
        $errors[] = 'Update session expired or invalid. Please upload the ZIP again.';
    } else {
        $applied = 0; $failed = []; $skipped = 0; $deleted = 0;

        // ── STEP 1: Copy all new files over current install ──
        $newFilesList = []; // track all files in new package
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($newRoot, FilesystemIterator::SKIP_DOTS));
        foreach($iter as $src) {
            if(!$src->isFile()) continue;
            $rel = str_replace('\\','/',substr($src->getPathname(), strlen($newRoot)+1));

            // NEVER overwrite data/ — user data, DB config, uploads live here
            if(strpos($rel,'data/') === 0) { $skipped++; continue; }

            $newFilesList[] = $rel;
            $dest = ROOT.'/'.$rel;
            $destDir = dirname($dest);
            if(!is_dir($destDir)) @mkdir($destDir, 0755, true);

            if(!@copy($src->getPathname(), $dest)) {
                $failed[] = $rel;
            } else {
                @chmod($dest, 0644);
                $applied++;
            }
        }

        // ── STEP 2: Remove files that exist in OLD version but NOT in new package ──
        // This ensures removed features (portal files etc) don't linger
        $protectedPrefixes = ['data/']; // never delete data/ contents
        $protectedFiles    = ['.htaccess']; // keep root htaccess

        $curIter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT, FilesystemIterator::SKIP_DOTS));
        foreach($curIter as $curFile) {
            if(!$curFile->isFile()) continue;
            $rel = str_replace('\\','/',substr($curFile->getPathname(), strlen(ROOT)+1));

            // Skip protected paths
            $skip = false;
            foreach($protectedPrefixes as $pp) {
                if(strpos($rel, $pp) === 0) { $skip = true; break; }
            }
            if($skip) continue;
            if(in_array(basename($rel), $protectedFiles)) continue;

            // If this file is NOT in the new package, remove it
            if(!in_array($rel, $newFilesList)) {
                if(@unlink(ROOT.'/'.$rel)) {
                    $deleted++;
                }
                // Clean up empty directories
                $dir = dirname(ROOT.'/'.$rel);
                if(is_dir($dir) && count(glob($dir.'/*')) === 0 && $dir !== ROOT) {
                    @rmdir($dir);
                }
            }
        }

        // Bust settings cache
        $GLOBALS['_settings_cache'] = null;

        // Clean up temp
        $tmpZip = $_SESSION['_upd_zip'] ?? '';
        if($tmpZip && file_exists($tmpZip)) @unlink($tmpZip);
        // Clean up extracted temp dir
        $tmpDir = $_SESSION['_upd_tmp'] ?? '';
        if($tmpDir && is_dir($tmpDir)) {
            $di = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach($di as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
            @rmdir($tmpDir);
        }
        unset($_SESSION['_upd_zip'],$_SESSION['_upd_root'],$_SESSION['_upd_newver'],$_SESSION['_upd_curver'],$_SESSION['_upd_tmp']);

        if(!empty($failed)) {
            $result = ['step'=>'done','ok'=>false,'applied'=>$applied,'failed'=>$failed,'newVersion'=>$newVer,'skipped'=>$skipped,'deleted'=>$deleted];
        } else {
            $delNote = $deleted > 0 ? " {$deleted} obsolete files removed." : '';
            setFlash('success',"DRXStore updated to v{$newVer} successfully! {$applied} files updated. {$skipped} protected files skipped.{$delNote} Please clear your browser cache.");
            header('Location: index.php?p=settings'); exit;
        }
    }
}

adminHeader('Software Update','settings');
?>
<div class="page-hdr">
  <div><div class="page-title">Software Update</div><div class="page-sub">Current version: <?=APP_NAME?> v<?=APP_VERSION?></div></div>
  <a href="index.php?p=settings" class="btn btn-ghost">← Back to Settings</a>
</div>

<?php foreach($errors as $er):?><div class="alert alert-danger"><span class="alert-body"><?=e($er)?></span></div><?php endforeach;?>
<?php foreach($warnings as $w):?><div class="alert alert-warning"><span class="alert-body"><?=e($w)?></span></div><?php endforeach;?>

<?php if($result && $result['step']==='confirm'): ?>
<!-- ── STEP 2: Confirm ── -->
<div class="card mb-2">
  <div class="card-hdr" style="background:<?=version_compare($result['newVersion'],$result['curVersion'],'>')? 'var(--green-lt)':'#fffbeb'?>">
    <div class="card-title" style="color:<?=version_compare($result['newVersion'],$result['curVersion'],'>')? 'var(--green)':'#7a6020'?>">
      <?=version_compare($result['newVersion'],$result['curVersion'],'>')? 'Newer version detected':'Version check'?>
    </div>
  </div>
  <div class="card-body">
    <div class="flex gap-2" style="flex-wrap:wrap;margin-bottom:16px">
      <div style="background:var(--g1);border-radius:10px;padding:14px 20px;text-align:center;min-width:120px">
        <div style="font-size:.7rem;color:var(--g5);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Current</div>
        <div style="font-size:1.3rem;font-weight:800;color:var(--navy);font-family:monospace">v<?=e($result['curVersion'])?></div>
      </div>
      <div style="display:flex;align-items:center;font-size:1.5rem;color:var(--g4);padding:0 8px">→</div>
      <div style="background:var(--green-lt);border-radius:10px;padding:14px 20px;text-align:center;min-width:120px;border:2px solid var(--green)">
        <div style="font-size:.7rem;color:var(--green);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">New</div>
        <div style="font-size:1.3rem;font-weight:800;color:var(--green);font-family:monospace">v<?=e($result['newVersion'])?></div>
      </div>
      <div style="display:flex;align-items:center;margin-left:12px">
        <span class="chip chip-blue"><?=$result['fileCount']?> files</span>
      </div>
    </div>
    <div style="background:var(--g1);border-radius:10px;padding:14px;font-size:.83rem;color:var(--g6);margin-bottom:16px">
      <strong style="color:var(--navy)">What will happen:</strong><br>
      • All PHP, CSS, JS and view files will be replaced with the new version<br>
      • Your <code>data/</code> folder (all JSON data, uploaded files) is <strong style="color:var(--green)">completely protected</strong><br>
      • Your database is untouched — schema changes are auto-applied on next page load<br>
      • Your settings, medicines, sales, batches, customers are all preserved
    </div>
  </div>
</div>

<?php if(!empty($result['schemaChanges'])): ?>
<div class="card mb-2">
  <div class="card-hdr"><div class="card-title">Database Schema Changes (<?=count($result['schemaChanges'])?>)</div></div>
  <div class="card-body p0">
    <div class="table-wrap"><table class="tbl">
      <thead><tr><th>Type</th><th>Details</th></tr></thead>
      <tbody>
      <?php foreach($result['schemaChanges'] as $sc): ?>
      <tr>
        <td><span class="chip <?=$sc['type']==='new_table'?'chip-blue':($sc['type']==='new_col'?'chip-green':'chip-yellow')?>"><?=$sc['type']==='new_table'?'New Table':($sc['type']==='new_col'?'New Column':'Changed')?></span></td>
        <td class="text-sm"><?=e($sc['msg'])?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>

<?php if(!empty($result['addedFiles'])): ?>
<div class="card mb-2">
  <div class="card-hdr"><div class="card-title">New Files in Update (<?=count($result['addedFiles'])?>)</div></div>
  <div class="card-body">
    <?php foreach($result['addedFiles'] as $f): ?>
    <span class="chip chip-green" style="margin:2px;font-size:.72rem"><?=e($f)?></span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if(!empty($result['removedFiles'])): ?>
<div class="card mb-2">
  <div class="card-hdr"><div class="card-title">Files to be Removed (<?=count($result['removedFiles'])?>)</div></div>
  <div class="card-body">
    <p class="text-sm text-muted" style="margin-bottom:8px">These files exist in your current install but not in the update. They will be <strong>deleted</strong> during update (data/ folder is always protected).</p>
    <?php foreach($result['removedFiles'] as $f): ?>
    <span class="chip chip-gray" style="margin:2px;font-size:.72rem"><?=e($f)?></span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <div class="alert alert-warning"><span class="alert-body"><strong>Recommended:</strong> Take a database backup before applying the update. <a href="index.php?p=settings&action=backup_mysql" style="font-weight:700;color:inherit">→ Download Backup</a></span></div>
    <form method="POST" id="applyForm">
      <?=csrfField()?>
      <input type="hidden" name="action" value="apply_update">
      <div class="flex gap-2" style="margin-top:16px">
        <button type="submit" class="btn btn-success" onclick="return confirm('Apply update to v<?=e($result['newVersion'])?>? Make sure you have a backup.')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><polyline points="20 6 9 17 4 12"/></svg>
          Apply Update to v<?=e($result['newVersion'])?>
        </button>
        <a href="index.php?p=sw_update" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php elseif($result && $result['step']==='done' && !$result['ok']): ?>
<!-- ── Partial failure ── -->
<div class="alert alert-warning"><span class="alert-body"><strong><?=$result['applied']?> files updated</strong>, but <?=count($result['failed'])?> file(s) could not be written (permission issue). The update may be incomplete.</span></div>
<div class="card">
  <div class="card-hdr"><div class="card-title">Files That Failed to Update</div></div>
  <div class="card-body">
    <?php foreach($result['failed'] as $f): ?>
    <div class="chip chip-red" style="margin:2px;font-size:.72rem"><?=e($f)?></div>
    <?php endforeach; ?>
    <p class="text-sm text-muted" style="margin-top:12px">Fix file permissions on these paths and try again, or update manually via FTP.</p>
  </div>
</div>

<?php else: ?>
<!-- ── STEP 1: Upload ── -->
<div class="card mb-2">
  <div class="card-hdr"><div class="card-title">How Software Update Works</div></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:0">
      <div style="background:var(--g1);border-radius:10px;padding:14px">
        <div style="width:32px;height:32px;border-radius:8px;background:var(--navy-dim);display:flex;align-items:center;justify-content:center;margin-bottom:10px">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="var(--navy)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </div>
        <div style="font-weight:700;font-size:.87rem;color:var(--navy);margin-bottom:3px">Upload ZIP</div>
        <div style="font-size:.79rem;color:var(--g6);line-height:1.5">Upload the new DRXStore release ZIP from GitHub</div>
      </div>
      <div style="background:var(--g1);border-radius:10px;padding:14px">
        <div style="width:32px;height:32px;border-radius:8px;background:var(--navy-dim);display:flex;align-items:center;justify-content:center;margin-bottom:10px">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="var(--navy)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </div>
        <div style="font-weight:700;font-size:.87rem;color:var(--navy);margin-bottom:3px">Auto Analysis</div>
        <div style="font-size:.79rem;color:var(--g6);line-height:1.5">Version detected, schema changes checked, file diff shown</div>
      </div>
      <div style="background:var(--g1);border-radius:10px;padding:14px">
        <div style="width:32px;height:32px;border-radius:8px;background:var(--green-lt);display:flex;align-items:center;justify-content:center;margin-bottom:10px">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="var(--green)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div style="font-weight:700;font-size:.87rem;color:var(--navy);margin-bottom:3px">You Confirm</div>
        <div style="font-size:.79rem;color:var(--g6);line-height:1.5">Review changes and approve before anything is applied</div>
      </div>
      <div style="background:var(--g1);border-radius:10px;padding:14px">
        <div style="width:32px;height:32px;border-radius:8px;background:var(--gold-dim);display:flex;align-items:center;justify-content:center;margin-bottom:10px">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#7a6020" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div style="font-weight:700;font-size:.87rem;color:var(--navy);margin-bottom:3px">Data Safe</div>
        <div style="font-size:.79rem;color:var(--g6);line-height:1.5">data/ folder, DB and credentials are never touched</div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-hdr"><div class="card-title">Upload Update Package</div></div>
  <div class="card-body">
    <div class="alert alert-info" style="margin-bottom:16px"><span class="alert-body">
      <strong>Current version:</strong> <?=APP_NAME?> v<?=APP_VERSION?><br>
      Download the latest release from <a href="https://github.com/Vptsh/drxstore/releases" target="_blank" rel="noopener" style="font-weight:700">github.com/Vptsh/drxstore/releases</a>
    </span></div>
    <form method="POST" enctype="multipart/form-data" id="uploadForm">
      <?=csrfField()?>
      <input type="hidden" name="action" value="upload_update">
      <div class="form-group">
        <label class="form-label">DRXStore Release ZIP <span class="req">*</span></label>
        <input class="form-control" type="file" name="update_zip" accept=".zip" required id="zipInput">
        <div class="form-hint">Upload the .zip file downloaded from GitHub releases. Max 20 MB.</div>
      </div>
      <div id="fileInfo" style="display:none;background:var(--g1);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:.83rem"></div>
      <button type="submit" class="btn btn-primary" id="analyseBtn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Analyse Update Package
      </button>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
document.getElementById('zipInput') && document.getElementById('zipInput').addEventListener('change', function(){
    var fi = document.getElementById('fileInfo');
    if(this.files[0]){
        var mb = (this.files[0].size/1024/1024).toFixed(2);
        fi.style.display='block';
        fi.innerHTML = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline></svg><strong>' + this.files[0].name + '</strong> — ' + mb + ' MB';
    }
});
document.getElementById('uploadForm') && document.getElementById('uploadForm').addEventListener('submit', function(){
    var btn = document.getElementById('analyseBtn');
    btn.disabled = true; btn.textContent = 'Analysing…';
});
</script>
<?php adminFooter();?>
