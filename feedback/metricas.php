<?php
/**
 * Panel de Métricas de Conversión - TuSpeaking
 */
$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset('utf8mb4');

$desde = isset($_GET['desde']) ? $_GET['desde'] : date('Y-m-01');
$hasta = isset($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-d');

$total_clases = $conn->query("SELECT COUNT(*) as t FROM mdl_i3code_acuityZoom WHERE acuity_datetime >= '$desde' AND acuity_datetime <= '$hasta 23:59:59' AND acuity_email IS NOT NULL")->fetch_assoc()['t'];
$total_enviados = $conn->query("SELECT COUNT(*) as t FROM own_feedback_envios WHERE enviado_at >= '$desde' AND enviado_at <= '$hasta 23:59:59'")->fetch_assoc()['t'];
$total_abiertos = $conn->query("SELECT COUNT(*) as t FROM own_feedback_envios WHERE enviado_at >= '$desde' AND enviado_at <= '$hasta 23:59:59' AND abierto_at IS NOT NULL")->fetch_assoc()['t'];
$total_respondidos = $conn->query("SELECT COUNT(*) as t FROM own_feedback_envios WHERE enviado_at >= '$desde' AND enviado_at <= '$hasta 23:59:59' AND respondido_at IS NOT NULL")->fetch_assoc()['t'];
$total_antiguos = $conn->query("SELECT COUNT(*) as t FROM own_feedback_nps WHERE submission_date >= '$desde' AND submission_date <= '$hasta 23:59:59' AND (enviado_auto IS NULL OR enviado_auto = 0)")->fetch_assoc()['t'];

$tasa_apertura = $total_enviados > 0 ? round($total_abiertos * 100 / $total_enviados, 1) : 0;
$tasa_nuevo = $total_enviados > 0 ? round($total_respondidos * 100 / $total_enviados, 1) : 0;
$tasa_antiguo = $total_clases > 0 ? round($total_antiguos * 100 / $total_clases, 1) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Métricas Feedback - tuSpeaking</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f5f5;padding:20px}
        .container{max-width:1000px;margin:0 auto}
        h1{color:#008ba3;margin-bottom:20px}
        .filters{background:#fff;padding:16px;border-radius:8px;margin-bottom:20px;display:flex;gap:16px;align-items:center;flex-wrap:wrap}
        .filters input{padding:8px 12px;border:1px solid #ddd;border-radius:4px}
        .filters button{background:#008ba3;color:#fff;border:none;padding:8px 20px;border-radius:4px;cursor:pointer}
        .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px}
        .kpi{background:#fff;padding:20px;border-radius:12px;text-align:center}
        .kpi-value{font-size:32px;font-weight:700;color:#008ba3}
        .kpi-label{font-size:13px;color:#666;margin-top:4px}
        .kpi-sub{font-size:11px;color:#999;margin-top:8px}
        .kpi.success .kpi-value{color:#27ae60}
        .kpi.warning .kpi-value{color:#f39c12}
        .kpi.danger .kpi-value{color:#e74c3c}
        .comparison{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px}
        .card{background:#fff;padding:20px;border-radius:12px}
        .card h3{color:#333;margin-bottom:16px;font-size:16px}
        .row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee}
        .row:last-child{border-bottom:none}
        .badge{display:inline-block;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600}
        .badge-success{background:#e8f5e9;color:#27ae60}
        .badge-warning{background:#fff8e1;color:#f39c12}
        .badge-danger{background:#ffebee;color:#e74c3c}
        .objetivo{background:#e3f2fd;padding:20px;border-radius:12px}
        .objetivo h3{color:#1976d2;margin-bottom:8px}
        @media(max-width:768px){.comparison{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="container">
    <h1>📊 Métricas de Conversión</h1>
    <form class="filters" method="GET">
        <label>Desde: <input type="date" name="desde" value="<?=$desde?>"></label>
        <label>Hasta: <input type="date" name="hasta" value="<?=$hasta?>"></label>
        <button type="submit">Filtrar</button>
    </form>
    <div class="kpis">
        <div class="kpi"><div class="kpi-value"><?=number_format($total_clases)?></div><div class="kpi-label">Clases Realizadas</div></div>
        <div class="kpi"><div class="kpi-value"><?=number_format($total_enviados)?></div><div class="kpi-label">Emails Enviados</div></div>
        <div class="kpi <?=$tasa_apertura>=50?'success':($tasa_apertura>=30?'warning':'danger')?>"><div class="kpi-value"><?=$tasa_apertura?>%</div><div class="kpi-label">Tasa Apertura</div><div class="kpi-sub"><?=$total_abiertos?> abiertos</div></div>
        <div class="kpi <?=$tasa_nuevo>=20?'success':($tasa_nuevo>=10?'warning':'danger')?>"><div class="kpi-value"><?=$tasa_nuevo?>%</div><div class="kpi-label">Tasa Respuesta (Nuevo)</div><div class="kpi-sub"><?=$total_respondidos?> feedbacks</div></div>
    </div>
    <div class="comparison">
        <div class="card">
            <h3>🆕 Sistema Nuevo (30 min)</h3>
            <div class="row"><span>Enviados</span><strong><?=number_format($total_enviados)?></strong></div>
            <div class="row"><span>Abiertos</span><strong><?=number_format($total_abiertos)?></strong></div>
            <div class="row"><span>Respondidos</span><strong><?=number_format($total_respondidos)?></strong></div>
            <div class="row"><span>Tasa</span><span class="badge <?=$tasa_nuevo>=15?'badge-success':'badge-warning'?>"><?=$tasa_nuevo?>%</span></div>
        </div>
        <div class="card">
            <h3>📧 Sistema Antiguo (24h Acuity)</h3>
            <div class="row"><span>Total Clases</span><strong><?=number_format($total_clases)?></strong></div>
            <div class="row"><span>Feedbacks</span><strong><?=number_format($total_antiguos)?></strong></div>
            <div class="row"><span>Tasa</span><span class="badge <?=$tasa_antiguo>=15?'badge-success':($tasa_antiguo>=8?'badge-warning':'badge-danger')?>"><?=$tasa_antiguo?>%</span></div>
        </div>
    </div>
    <div class="objetivo">
        <h3>🎯 Objetivo</h3>
        <p>Conseguir tasa de respuesta del <strong>15-20%</strong> para muestra representativa (~100 feedbacks/mes).</p>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>
