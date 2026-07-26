<?php
require('config.php');
$admins = get_admins();
$isadmin = false;
foreach($admins as $admin){
    if ($admin->id == $USER->id){ $isadmin = true; break; }
}
if (!$isadmin){ header("Location: /app/moodle"); die(); }

$upload_dir = '/home/aulatuspeaking/scripts/coding_tuspeaking/uploads/';
$output_dir = '/home/aulatuspeaking/scripts/coding_tuspeaking/output/';
if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo']) && $_FILES['archivo']['error'] === 0) {
    $file = $_FILES['archivo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['xlsx','xls','csv'])) {
        $fn = 'rep_'.date('Ymd_His').'.'.$ext;
        $fp = $upload_dir.$fn;
        if (move_uploaded_file($file['tmp_name'], $fp)) {
            $msg = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> <b>Archivo subido correctamente.</b><br>El reporte se generará automáticamente en menos de 1 minuto.<br><small>Refresca la página para ver el reporte en "Reportes Anteriores".</small></div>';
        } else {
            $msg = '<div class="alert alert-danger">Error al subir el archivo</div>';
        }
    } else {
        $msg = '<div class="alert alert-warning">Formato no válido. Use .xlsx, .xls o .csv</div>';
    }
}

$reports = glob($output_dir.'FUNDAE_*.{pdf,xlsx}', GLOB_BRACE);
usort($reports, function($a,$b){ return filemtime($b)-filemtime($a); });
$reports = array_slice($reports, 0, 20);
?>
<!DOCTYPE html>
<html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>tuSpeaking - Reportes FUNDAE</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css">
<style>
body{background:#f5f5f5}
.hdr{background:linear-gradient(135deg,#01BCD4,#018CA4);color:#fff;padding:20px 0;margin-bottom:30px}
.card{border:none;box-shadow:0 2px 10px rgba(0,0,0,.1);margin-bottom:20px}
.card-header{background:#01BCD4;color:#fff}
.btn-ts{background:#01BCD4;border-color:#01BCD4;color:#fff}
.btn-ts:hover{background:#018CA4;color:#fff}
.fi{display:flex;justify-content:space-between;align-items:center;padding:12px 15px;border-bottom:1px solid #eee}
.upload-zone{border:2px dashed #01BCD4;border-radius:10px;padding:30px;text-align:center;background:#f8fdfe;cursor:pointer}
.upload-zone:hover{background:#e8f7f9}
</style>
</head><body>
<div class="hdr"><div class="container">
<a href="/my/" style="color:#fff;opacity:.8"><i class="fas fa-arrow-left"></i> Volver</a>
<h1 class="mt-2"><i class="fas fa-file-invoice"></i> Reportes FUNDAE</h1>
<p>Genera PDF/Excel con formato corporativo tuSpeaking</p>
</div></div>
<div class="container">
<?=$msg?>
<div class="row"><div class="col-md-6">
<div class="card"><div class="card-header"><i class="fas fa-upload"></i> Subir Archivo</div>
<div class="card-body">
<form method="POST" enctype="multipart/form-data" id="uploadForm">
<div class="upload-zone" onclick="document.getElementById('archivo').click()">
<i class="fas fa-cloud-upload-alt fa-3x" style="color:#01BCD4"></i>
<p class="mt-2 mb-1"><b>Clic para seleccionar archivo</b></p>
<small class="text-muted">.xlsx, .xls, .csv</small>
<p id="fileName" class="text-primary mt-2 mb-0"></p>
</div>
<input type="file" name="archivo" id="archivo" accept=".xlsx,.xls,.csv" style="display:none" required onchange="document.getElementById('fileName').textContent=this.files[0]?this.files[0].name:'';if(this.files[0])document.getElementById('uploadForm').submit();">
</form>
<div class="alert alert-info mt-3 mb-0">
<i class="fas fa-info-circle"></i> <b>Procesamiento automático:</b><br>
Al subir el archivo, el reporte se genera automáticamente en menos de 1 minuto.
</div>
</div></div>
<div class="card"><div class="card-header"><i class="fas fa-info-circle"></i> Instrucciones</div>
<div class="card-body"><ol class="mb-0">
<li>Ve a <b>Configurable Reports</b></li>
<li>Abre <b>"Vista FUNDAE - Conexiones"</b></li>
<li>Selecciona empresa y exporta XLS</li>
<li>Sube el archivo aquí</li>
<li><b>Espera ~1 minuto</b> y refresca la página</li>
</ol></div></div>
</div><div class="col-md-6">
<div class="card"><div class="card-header"><i class="fas fa-history"></i> Reportes Anteriores <button onclick="location.reload()" class="btn btn-sm btn-light float-right"><i class="fas fa-sync-alt"></i></button></div>
<div class="card-body p-0">
<?php if(!$reports): ?><p class="text-muted p-3 mb-0">No hay reportes</p>
<?php else: foreach($reports as $r): $bn=basename($r); $ex=pathinfo($r,PATHINFO_EXTENSION); $dt=date('d/m/Y H:i',filemtime($r)); ?>
<div class="fi"><div><i class="fas fa-file-<?=$ex?>" style="color:<?=$ex=='pdf'?'#e74c3c':'#27ae60'?>;margin-right:10px"></i>
<span style="font-size:13px"><?=$bn?></span><br><small class="text-muted"><?=$dt?></small></div>
<a href="coding_fundae_dl.php?f=<?=urlencode($bn)?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download"></i></a>
</div>
<?php endforeach; endif; ?>
</div></div>
</div></div>
<p class="text-center text-muted mt-4"><small>Coding v1.0.0 © 2025</small></p>
</div></body></html>
