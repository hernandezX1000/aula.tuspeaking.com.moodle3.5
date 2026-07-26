<?php
/**
 * Panel de Administración - TuSpeaking
 * Vista principal
 */

require_once __DIR__ . '/functions.php';

// Datos FUNDAE
$fundae = getFundaeActivity();
$bot = analyzeBotBehavior($fundae['data']);
$disk = getDiskInfo();

// CAPTCHA
if (isset($_POST['activate_captcha'])) setCaptcha(true);
if (isset($_POST['deactivate_captcha'])) setCaptcha(false);
$captchaActive = isCaptchaActive();

// Herramientas desde JSON
$tools = loadTools();
uasort($tools, function($a, $b) {
    return (isset($a['order']) ? $a['order'] : 99) - (isset($b['order']) ? $b['order'] : 99);
});
?>
<!DOCTYPE html>
<html lang="es">
<head>
<link rel="icon" type="image/svg+xml" href="/brand/icons/favicon.svg">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Panel Admin - TuSpeaking</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,sans-serif;background:linear-gradient(135deg,#1a1a2e,#16213e);min-height:100vh;padding:20px;color:#fff}
.container{max-width:1400px;margin:0 auto}
header{text-align:center;padding:30px;margin-bottom:30px;background:rgba(255,255,255,.05);border-radius:16px}
header h1{font-size:2em;margin-bottom:5px}
header p{opacity:.7}
.cache-info{font-size:.75em;opacity:.5;margin-top:5px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px}
.card{background:rgba(255,255,255,.08);border-radius:12px;padding:20px;border:1px solid rgba(255,255,255,.1)}
.card:hover{transform:translateY(-3px);box-shadow:0 10px 30px rgba(0,0,0,.3);transition:.2s}
.card h2{font-size:1.1em;margin-bottom:15px;padding-bottom:10px;border-bottom:1px solid rgba(255,255,255,.1)}
.card ul{list-style:none}
.card li{margin:8px 0}
.card a{color:#a5d6ff;text-decoration:none;display:flex;align-items:center;padding:8px 10px;border-radius:6px}
.card a:hover{background:rgba(255,255,255,.1)}
.card a.pending{opacity:.4;cursor:not-allowed}
.card a .icon{margin-right:10px}
.card a .name{flex:1}
.badge-new{background:#48bb78;color:#fff;font-size:.65em;padding:2px 6px;border-radius:4px;margin-left:8px}
.badge-ext{background:#805ad5;color:#fff;font-size:.65em;padding:2px 6px;border-radius:4px;margin-left:8px}
.badge-pending{background:#718096;color:#fff;font-size:.65em;padding:2px 6px;border-radius:4px;margin-left:8px}
.fundae-widget{grid-column:1/-1;background:linear-gradient(135deg,rgba(237,100,166,.15),rgba(159,122,234,.15));border:1px solid rgba(237,100,166,.3);padding:20px;border-radius:12px;margin-bottom:20px}
.fundae-widget h2{color:#ed64a6;border-bottom:1px solid rgba(237,100,166,.3);padding-bottom:10px;margin-bottom:15px}
.fundae-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.fundae-status{display:flex;align-items:center;gap:15px;padding:15px;background:rgba(0,0,0,.2);border-radius:8px}
.status-dot{width:12px;height:12px;border-radius:50%;animation:pulse 2s infinite}
.status-dot.online{background:#48bb78}
.status-dot.offline{background:#718096}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
.fundae-info{flex:1}
.fundae-info .label{font-size:.85em;opacity:.7}
.fundae-info .value{font-size:1.1em;font-weight:500}
.fundae-curso{color:#fbd38d}
.bot-analysis{background:rgba(0,0,0,.3);border-radius:8px;padding:15px}
.bot-score{display:flex;align-items:center;gap:15px;margin-bottom:15px}
.score-bar{flex:1;height:20px;background:rgba(255,255,255,.1);border-radius:10px;overflow:hidden}
.score-fill{height:100%;transition:width .5s}
.score-value{font-size:1.5em;font-weight:bold;min-width:60px}
.bot-reasons{font-size:.85em;margin-bottom:15px;list-style:none}
.bot-reasons li{padding:4px 0;color:#fbd38d}
.btn{padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-weight:500}
.btn-danger{background:#e53e3e;color:#fff}
.btn-success{background:#48bb78;color:#fff}
.captcha-status{padding:10px;border-radius:6px;margin-top:10px;font-size:.9em}
.captcha-active{background:rgba(229,62,62,.2);border:1px solid #e53e3e}
.captcha-inactive{background:rgba(72,187,120,.2);border:1px solid #48bb78}
.fundae-table{width:100%;font-size:.85em;margin-top:15px}
.fundae-table th{text-align:left;padding:8px;border-bottom:1px solid rgba(255,255,255,.1);opacity:.7}
.fundae-table td{padding:8px;border-bottom:1px solid rgba(255,255,255,.05)}
.action-viewed{color:#68d391}
.action-reviewed{color:#fbd38d}
.disk-widget,.tech-widget{background:rgba(0,0,0,.3);padding:15px;border-radius:8px;margin-top:15px}
.disk-bar{height:24px;background:rgba(255,255,255,.1);border-radius:12px;overflow:hidden;margin:10px 0}
.disk-fill{height:100%;border-radius:12px}
.disk-info{display:flex;justify-content:space-between;font-size:.85em;opacity:.8}
.tech-widget code{background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;font-size:.75em}
.tech-widget p{margin:5px 0;font-size:.85em;opacity:.8}
footer{text-align:center;padding:20px;opacity:.5}
@media(max-width:900px){.fundae-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">
<header>
    <h1>🔧 Panel de Administración</h1>
    <p>TuSpeaking - Herramientas de Gestión</p>
    <div class="cache-info">
        <?php if($fundae['fromCache']): ?>📦 Caché (<?php echo $fundae['cacheAge']; ?>s) | <a href="?refresh=1" style="color:#a5d6ff">Actualizar</a>
        <?php else: ?>✨ Datos frescos<?php endif; ?>
    </div>
</header>

<div class="fundae-widget">
    <h2>🔍 Monitor FUNDAE</h2>
    <div class="fundae-grid">
        <div>
            <div class="fundae-status">
                <span class="status-dot <?php echo $bot['isOnline'] ? 'online' : 'offline'; ?>"></span>
                <div class="fundae-info">
                    <div class="label">Estado</div>
                    <div class="value"><?php echo $bot['isOnline'] ? '🟢 CONECTADO' : '⚪ Offline'; ?></div>
                </div>
                <?php if ($bot['lastActivity']): ?>
                <div class="fundae-info">
                    <div class="label">Último</div>
                    <div class="value"><?php echo date('d/m H:i', strtotime($bot['lastActivity']['fecha'])); ?></div>
                </div>
                <div class="fundae-info">
                    <div class="label">Curso</div>
                    <div class="value fundae-curso"><?php echo htmlspecialchars(mb_substr($bot['lastActivity']['curso'], 0, 30)) ?: '-'; ?></div>
                </div>
                <?php endif; ?>
            </div>
            <table class="fundae-table">
                <tr><th>Hora</th><th>Acción</th><th>Curso</th></tr>
                <?php foreach(array_slice($fundae['data'], 0, 6) as $act): ?>
                <tr>
                    <td><?php echo date('H:i:s', strtotime($act['fecha'])); ?></td>
                    <td class="action-<?php echo $act['action']; ?>"><?php echo $act['action']; ?></td>
                    <td><?php echo htmlspecialchars(mb_substr($act['curso'] ?: '-', 0, 35)); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <div class="bot-analysis">
            <h3 style="margin-bottom:15px">🤖 Análisis Bot</h3>
            <div class="bot-score">
                <div class="score-bar"><div class="score-fill" style="width:<?php echo $bot['score']; ?>%;background:<?php echo $bot['color']; ?>"></div></div>
                <div class="score-value" style="color:<?php echo $bot['color']; ?>"><?php echo $bot['score']; ?>%</div>
            </div>
            <div style="font-size:1.2em;margin-bottom:15px;color:<?php echo $bot['color']; ?>"><?php echo $bot['label']; ?></div>
            <?php if (count($bot['reasons']) > 0): ?>
            <ul class="bot-reasons"><?php foreach($bot['reasons'] as $r): ?><li>⚠️ <?php echo $r; ?></li><?php endforeach; ?></ul>
            <?php else: ?><p style="opacity:.7;margin-bottom:15px">✓ Sin alertas</p><?php endif; ?>
            <?php if ($bot['score'] >= 30): ?>
            <form method="POST">
                <?php if ($captchaActive): ?>
                <button type="submit" name="deactivate_captcha" class="btn btn-success">✓ Desactivar CAPTCHA</button>
                <?php else: ?>
                <button type="submit" name="activate_captcha" class="btn btn-danger">🛡️ Activar CAPTCHA</button>
                <?php endif; ?>
            </form>
            <?php endif; ?>
            <div class="captcha-status <?php echo $captchaActive ? 'captcha-active' : 'captcha-inactive'; ?>">
                <?php echo $captchaActive ? '🛡️ CAPTCHA ACTIVO' : '✓ CAPTCHA off'; ?>
            </div>
        </div>
    </div>
</div>

<div class="grid">
<?php foreach($tools as $key => $cat): ?>
<div class="card">
    <h2><?php echo $cat['title']; ?></h2>
    <ul>
    <?php foreach($cat['items'] as $item): ?>
    <li><a href="<?php echo $item['url']; ?>" target="_blank" class="<?php echo !empty($item['pending']) ? 'pending' : ''; ?>">
        <span class="icon"><?php echo $item['icon']; ?></span>
        <span class="name"><?php echo $item['name']; ?></span>
        <?php if(!empty($item['new'])): ?><span class="badge-new">Nuevo</span><?php endif; ?>
        <?php if(!empty($item['external'])): ?><span class="badge-ext">↗</span><?php endif; ?>
        <?php if(!empty($item['pending'])): ?><span class="badge-pending">Pendiente</span><?php endif; ?>
    </a></li>
    <?php endforeach; ?>
    </ul>
    
    <?php if($key === 'dev'): ?>
    <div class="disk-widget">
        <strong>💾 Disco</strong>
        <div class="disk-bar"><div class="disk-fill" style="width:<?php echo $disk['percent']; ?>%;background:<?php echo $disk['percent'] > 90 ? '#fc8181' : ($disk['percent'] > 75 ? '#fbd38d' : '#68d391'); ?>"></div></div>
        <div class="disk-info">
            <span><?php echo formatBytes($disk['used']); ?></span>
            <span><?php echo $disk['percent']; ?>%</span>
            <span><?php echo formatBytes($disk['free']); ?> libre</span>
        </div>
    </div>
    <div class="tech-widget">
        <strong>📂 Archivos</strong>
        <p><code>config.php</code> <code>functions.php</code> <code>tools.json</code></p>
        <p>📍 <?php echo PANEL_PATH; ?></p>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<footer>TuSpeaking © <?php echo date('Y'); ?> | <?php echo date('H:i:s'); ?></footer>
</div>
</body>
</html>
