<?php
/**
 * Visor de Logs - TuSpeaking (PHP 7.1 compatible)
 */

$logs = array(
    'Limpieza CESCE' => '/home/aulatuspeaking/www/app/moodle/reportes_cesce/limpieza.log',
    'Panel Admin' => '/home/aulatuspeaking/www/app/moodle/admin-panel/panel.log',
);

$selected = isset($_GET['log']) ? $_GET['log'] : 'Limpieza CESCE';
$lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 100;
$search = isset($_GET['search']) ? $_GET['search'] : '';

$logFile = isset($logs[$selected]) ? $logs[$selected] : '';
$content = '';
$error = '';
$fileInfo = '';

if ($logFile && file_exists($logFile)) {
    if (is_readable($logFile)) {
        $allLines = file($logFile, FILE_IGNORE_NEW_LINES);
        $allLines = array_reverse($allLines);
        
        if ($search) {
            $filtered = array();
            foreach ($allLines as $l) {
                if (stripos($l, $search) !== false) {
                    $filtered[] = $l;
                }
            }
            $allLines = $filtered;
        }
        
        $allLines = array_slice($allLines, 0, $lines);
        $content = count($allLines) > 0 ? implode("\n", $allLines) : '(vacío)';
        
        $size = filesize($logFile);
        $modified = date('d/m/Y H:i:s', filemtime($logFile));
        $fileInfo = "Tamaño: " . round($size/1024, 2) . " KB | Modificado: $modified";
    } else {
        $error = "Sin permisos de lectura: $logFile";
    }
} else {
    // Crear log si no existe
    @file_put_contents($logFile, date('Y-m-d H:i:s') . " - Log iniciado\n");
    $content = '(Log recién creado)';
}

// Log de acceso
$panelLog = '/home/aulatuspeaking/www/app/moodle/admin-panel/panel.log';
@file_put_contents($panelLog, date('Y-m-d H:i:s') . " - Vista: $selected\n", FILE_APPEND);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Visor Logs - TuSpeaking</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,sans-serif;background:#1a1a2e;min-height:100vh;padding:20px;color:#fff}
.container{max-width:1400px;margin:0 auto}
header{display:flex;justify-content:space-between;align-items:center;padding:20px;margin-bottom:20px;background:rgba(255,255,255,.05);border-radius:12px}
header h1{font-size:1.5em}
header a{color:#a5d6ff;text-decoration:none}
.controls{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;padding:15px;background:rgba(255,255,255,.05);border-radius:12px}
select,input,button{padding:10px 15px;border-radius:6px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.1);color:#fff;font-size:14px}
button{background:#4299e1;border:none;cursor:pointer}
button:hover{background:#3182ce}
.log-box{background:#0d1117;border-radius:12px;padding:20px;font-family:monospace;font-size:12px;line-height:1.6;white-space:pre-wrap;word-break:break-all;max-height:60vh;overflow-y:auto}
.error{color:#fc8181}
.warning{color:#fbd38d}
.info{color:#68d391}
.err-msg{background:rgba(252,129,129,.2);padding:15px;border-radius:8px;margin-bottom:20px;color:#fc8181}
.file-info{font-size:12px;opacity:.6;margin-bottom:10px}
</style>
</head>
<body>
<div class="container">
<header>
<h1>📜 Visor de Logs</h1>
<a href="index.php">← Volver al Panel</a>
</header>

<?php if($error): ?><div class="err-msg">⚠️ <?php echo $error; ?></div><?php endif; ?>

<form class="controls" method="GET">
<select name="log">
<?php foreach($logs as $name => $path): ?>
<option value="<?php echo $name; ?>" <?php echo ($selected==$name)?'selected':''; ?>><?php echo $name; ?></option>
<?php endforeach; ?>
</select>
<input type="number" name="lines" value="<?php echo $lines; ?>" min="10" max="1000" style="width:80px">
<input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar...">
<button type="submit">🔍 Filtrar</button>
</form>

<?php if($fileInfo): ?><div class="file-info">📁 <?php echo $fileInfo; ?></div><?php endif; ?>

<div class="log-box"><?php
$outputLines = explode("\n", htmlspecialchars($content));
foreach($outputLines as $line) {
    if (stripos($line, 'error') !== false || stripos($line, 'fatal') !== false) {
        echo "<span class='error'>$line</span>\n";
    } elseif (stripos($line, 'warning') !== false) {
        echo "<span class='warning'>$line</span>\n";
    } elseif (stripos($line, 'success') !== false || stripos($line, 'completado') !== false) {
        echo "<span class='info'>$line</span>\n";
    } else {
        echo "$line\n";
    }
}
?></div>
</div>
</body>
</html>
