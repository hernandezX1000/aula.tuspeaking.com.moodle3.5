<?php
/**
 * ============================================================
 * CODING - Visor Logs JotForm Calificaciones CESCE
 * ============================================================
 * Archivo: ver_logs_jotform.php
 * Ruta: /home/aulatuspeaking/www/app/moodle/reportes_cesce/
 * URL: https://aula.tuspeaking.com/app/moodle/reportes_cesce/ver_logs_jotform.php
 * Fecha: 2025-12-28
 * ============================================================
 */

$log_dir = __DIR__ . '/logs';
$dbhost='localhost'; $dbname='aulatuspeaking35'; $dbuser='moodle35'; $dbpass='TuspeakingFix2025!';

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Logs JotForm CESCE</title>
<style>body{font-family:monospace;background:#1a1a2e;color:#eee;padding:20px}
h2{color:#4ade80}.log{background:#16213e;padding:15px;margin:10px 0;border-radius:8px;white-space:pre-wrap;overflow-x:auto}
.ok{color:#4ade80}.err{color:#f87171}.info{color:#60a5fa}.stats{background:#0f3460;padding:15px;border-radius:8px;margin-bottom:20px}
select,button{padding:8px 15px;margin:5px;background:#0f3460;color:#eee;border:1px solid #4ade80;border-radius:4px}
table{width:100%;border-collapse:collapse}th,td{padding:8px;text-align:left;border-bottom:1px solid #333}</style></head><body>';

echo '<h2>📋 CODING - Logs Webhook JotForm</h2>';
echo '<p>URL Webhook: <code>https://aula.tuspeaking.com/app/moodle/reportes_cesce/webhook_jotform_notas.php</code></p>';

try {
    $pdo = new PDO("mysql:host=$dbhost;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);
    $st = $pdo->query("SELECT COUNT(*) t, SUM(pass_yn='Y') ok, SUM(pass_yn='N') no, MAX(fecha_modificacion) ult FROM mdl_cesce_calificaciones");
    $s = $st->fetch(PDO::FETCH_ASSOC);
    echo "<div class='stats'><b>📊 BD:</b> Total: <span class='ok'>{$s['t']}</span> | Pass: <span class='ok'>{$s['ok']}</span> | Fail: <span class='err'>{$s['no']}</span> | Último: {$s['ult']}</div>";
} catch(Exception $e) { echo "<div class='stats err'>Error BD: {$e->getMessage()}</div>"; }

if (!is_dir($log_dir)) { echo '<p class="err">No hay logs aún.</p></body></html>'; exit; }
$logs = glob($log_dir.'/jotform_*.log'); rsort($logs);
if (empty($logs)) { echo '<p class="err">No hay logs aún.</p></body></html>'; exit; }

$sel = $_GET['log'] ?? $logs[0];
echo '<form method="get"><select name="log" onchange="this.form.submit()">';
foreach ($logs as $l) { $n=basename($l); $s=($l===$sel)?'selected':''; echo "<option value='$l' $s>$n</option>"; }
echo '</select> <button>Ver</button></form>';

if (file_exists($sel)) {
    $c = htmlspecialchars(file_get_contents($sel));
    $c = preg_replace('/\[([\d-]+ [\d:]+)\]/','<span class="info">[$1]</span>',$c);
    $c = preg_replace('/(INSERTADO|ACTUALIZADO|success)/','<span class="ok">$1</span>',$c);
    $c = preg_replace('/(ERROR|error)/','<span class="err">$1</span>',$c);
    echo "<div class='log'>$c</div>";
}

echo '<h3>📝 Últimos 10 registros:</h3><div class="log"><table><tr><th>Edición</th><th>Email</th><th>Nivel</th><th>Nota</th><th>Pass</th><th>Fecha</th></tr>';
try {
    $st = $pdo->query("SELECT edicion,empleado_email,nivel,nota_final,pass_yn,fecha_modificacion FROM mdl_cesce_calificaciones ORDER BY fecha_modificacion DESC LIMIT 10");
    while ($r=$st->fetch(PDO::FETCH_ASSOC)) {
        $pc = $r['pass_yn']==='Y'?'ok':'err';
        echo "<tr><td>{$r['edicion']}</td><td>{$r['empleado_email']}</td><td>{$r['nivel']}</td><td>{$r['nota_final']}</td><td class='$pc'>{$r['pass_yn']}</td><td>{$r['fecha_modificacion']}</td></tr>";
    }
} catch(Exception $e) { echo "<tr><td colspan='6' class='err'>{$e->getMessage()}</td></tr>"; }
echo '</table></div></body></html>';
?>
