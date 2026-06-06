<?php
/**
 * Portal RRHH - Vista para clientes
 * v2.0 - Con alertas visuales y filtros
 */

require('../config.php');
require_login();

$user_email = $USER->email;
$empresa_usuario = null;
$filtro_empresa = null;
$es_admin = false;

$empresas_disponibles = [
    'todas' => ['nombre' => 'Todas las empresas', 'filtro' => null],
    'e2y' => ['nombre' => 'e2y Commerce', 'filtro' => '%@e2ycommerce.com'],
    'cesce' => ['nombre' => 'CESCE', 'filtro' => '%@cesce.es'],
    'gkn' => ['nombre' => 'GKN', 'filtro' => '%@gkn%'],
    'tekia' => ['nombre' => 'Tekia', 'filtro' => '%@tekia%'],
    'capitole' => ['nombre' => 'Capitole Consulting', 'filtro' => '%@capitole-consulting.com'],
    'otros' => ['nombre' => 'Otros', 'filtro' => 'OTROS']
];

if (strpos($user_email, '@e2ycommerce.com') !== false) {
    $empresa_usuario = 'e2y Commerce';
    $filtro_empresa = '%@e2ycommerce.com';
} elseif (strpos($user_email, '@cesce.es') !== false) {
    $empresa_usuario = 'CESCE';
    $filtro_empresa = '%@cesce.es';
} elseif (strpos($user_email, '@gkn') !== false) {
    $empresa_usuario = 'GKN';
    $filtro_empresa = '%@gkn%';
} elseif (strpos($user_email, '@tuspeaking.com') !== false) {
    $empresa_usuario = 'TuSpeaking (Admin)';
    $filtro_empresa = null;
    $es_admin = true;
} else {
    echo $OUTPUT->header();
    echo '<div style="text-align:center;padding:50px;"><h2>Acceso no autorizado</h2>';
    echo '<p>Tu cuenta no tiene acceso a este portal.</p></div>';
    echo $OUTPUT->footer();
    exit;
}

$empresa_seleccionada = 'todas';
if ($es_admin && isset($_GET['empresa']) && array_key_exists($_GET['empresa'], $empresas_disponibles)) {
    $empresa_seleccionada = $_GET['empresa'];
    if ($empresa_seleccionada !== 'todas') {
        $filtro_empresa = $empresas_disponibles[$empresa_seleccionada]['filtro'];
        $empresa_usuario = $empresas_disponibles[$empresa_seleccionada]['nombre'];
    }
}

// Filtros adicionales
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$solo_problemas = isset($_GET['problemas']) && $_GET['problemas'] == '1';

$dsn = "mysql:host=localhost;dbname=aulatuspeaking35;charset=utf8mb4";
$pdo = new PDO($dsn, 'moodle35', 'TuspeakingFix2025!', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$where_empresa = "";
$params = [];
if ($filtro_empresa) {
    if ($filtro_empresa === 'OTROS') {
        $where_empresa = "AND u.email NOT LIKE '%@e2ycommerce.com' AND u.email NOT LIKE '%@cesce.es' AND u.email NOT LIKE '%@gkn%' AND u.email NOT LIKE '%@tekia%' AND u.email NOT LIKE '%@capitole-consulting.com' AND u.email NOT LIKE '%@tuspeaking.com'";
    } else {
        $where_empresa = "AND u.email LIKE ?";
        $params[] = $filtro_empresa;
    }
}

// Filtro de búsqueda por nombre
$where_buscar = "";
if ($buscar !== '') {
    $where_buscar = "AND (u.firstname LIKE ? OR u.lastname LIKE ? OR CONCAT(u.firstname, ' ', u.lastname) LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$sql_alumnos = "SELECT 
    u.id as userid, 
    u.firstname, 
    u.lastname, 
    u.email,
    c.id as courseid,
    c.fullname as curso,
    COALESCE(oac.classnmbr, 0) as contratadas,
    ee.fecha_inicio,
    ee.fecha_fin,
    (SELECT COUNT(*) FROM mdl_i3code_acuityZoom az 
     WHERE az.studentid = u.id AND az.courseid = c.id AND az.acuity_canceled = 0) as total_agendadas,
    (SELECT SUM(CASE WHEN az2.zoom_clasecompletada = 1 THEN 1 ELSE 0 END) 
     FROM mdl_i3code_acuityZoom az2 
     WHERE az2.studentid = u.id AND az2.courseid = c.id AND az2.acuity_canceled = 0) as asistidas,
    (SELECT SUM(CASE WHEN az3.zoom_clasecompletada = 2 THEN 1 ELSE 0 END) 
     FROM mdl_i3code_acuityZoom az3 
     WHERE az3.studentid = u.id AND az3.courseid = c.id AND az3.acuity_canceled = 0) as ausencias,
    (SELECT SUM(CASE WHEN az4.zoom_clasecompletada = 3 THEN 1 ELSE 0 END) 
     FROM mdl_i3code_acuityZoom az4 
     WHERE az4.studentid = u.id AND az4.courseid = c.id AND az4.acuity_canceled = 0) as pendientes
FROM mdl_user u
INNER JOIN mdl_user_enrolments ue ON ue.userid = u.id
INNER JOIN mdl_enrol e ON e.id = ue.enrolid
INNER JOIN mdl_course c ON c.id = e.courseid
LEFT JOIN own_acuity_course oac ON oac.courseid = c.id
LEFT JOIN own_empresa_ediciones ee ON ee.categoria_id = c.category
WHERE c.fullname LIKE '%2026%'
$where_empresa
$where_buscar
ORDER BY u.lastname, u.firstname, c.fullname";

$stmt = $pdo->prepare($sql_alumnos);
$stmt->execute($params);
$alumnos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar alertas y filtrar problemas
$alumnos = [];
$alertas_count = 0;
$hoy = new DateTime();

foreach ($alumnos_raw as $a) {
    $sin_agendar = max(0, $a['contratadas'] - $a['total_agendadas']);
    $p = ($a['asistidas'] + $a['ausencias']) > 0 ? round($a['asistidas'] / ($a['asistidas'] + $a['ausencias']) * 100) : -1;
    
    // Detectar alertas
    $alertas = [];
    if ($p >= 0 && $p < 50) {
        $alertas[] = 'baja_asistencia';
    }
    if ($sin_agendar > 10) {
        $alertas[] = 'sin_agendar';
    }
    if ($a['fecha_fin']) {
        $fecha_fin = new DateTime($a['fecha_fin']);
        $dias_restantes = $hoy->diff($fecha_fin)->days;
        $es_pasado = $fecha_fin < $hoy;
        if (!$es_pasado && $dias_restantes <= 30) {
            $alertas[] = 'por_vencer';
        }
    }
    
    $a['sin_agendar'] = $sin_agendar;
    $a['porcentaje'] = $p;
    $a['alertas'] = $alertas;
    
    if (count($alertas) > 0) {
        $alertas_count++;
    }
    
    // Filtrar solo problemas si está activo
    if ($solo_problemas && count($alertas) == 0) {
        continue;
    }
    
    $alumnos[] = $a;
}

// Estadísticas
$total_alumnos = count(array_unique(array_column($alumnos_raw, 'userid')));
$total_cursos = count(array_unique(array_column($alumnos_raw, 'courseid')));
$total_contratadas = array_sum(array_column($alumnos_raw, 'contratadas'));
$total_asistidas = array_sum(array_column($alumnos_raw, 'asistidas'));
$total_ausencias = array_sum(array_column($alumnos_raw, 'ausencias'));
$total_pendientes = array_sum(array_column($alumnos_raw, 'pendientes'));
$total_sin_agendar = $total_contratadas - array_sum(array_column($alumnos_raw, 'total_agendadas'));
$pct = ($total_asistidas + $total_ausencias) > 0 
    ? round($total_asistidas / ($total_asistidas + $total_ausencias) * 100) : 0;

// Export Excel
if (isset($_GET['export'])) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="Asistencia_' . preg_replace('/[^a-zA-Z0-9]/', '_', $empresa_usuario) . '_' . date('Y-m-d') . '.xls"');
    echo "\xEF\xBB\xBF";
    echo "<table border='1'><tr><th>Alumno</th><th>Email</th><th>Curso</th><th>Contratadas</th><th>Agendadas</th><th>Asistidas</th><th>Ausencias</th><th>Pendientes</th><th>Sin Agendar</th><th>% Asist.</th><th>Fecha Inicio</th><th>Fecha Fin</th><th>Alertas</th></tr>";
    foreach ($alumnos as $a) {
        $f_ini = $a['fecha_inicio'] ? date('d/m/Y', strtotime($a['fecha_inicio'])) : '-';
        $f_fin = $a['fecha_fin'] ? date('d/m/Y', strtotime($a['fecha_fin'])) : '-';
        $alertas_txt = implode(', ', $a['alertas']);
        echo "<tr><td>{$a['lastname']}, {$a['firstname']}</td><td>{$a['email']}</td><td>{$a['curso']}</td><td>{$a['contratadas']}</td><td>{$a['total_agendadas']}</td><td>{$a['asistidas']}</td><td>{$a['ausencias']}</td><td>{$a['pendientes']}</td><td>{$a['sin_agendar']}</td><td>{$a['porcentaje']}%</td><td>{$f_ini}</td><td>{$f_fin}</td><td>{$alertas_txt}</td></tr>";
    }
    echo "</table>";
    exit;
}

$PAGE->set_url(new moodle_url('/portal/rrhh.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Portal RRHH - ' . $empresa_usuario);
$PAGE->set_heading('Portal RRHH');
echo $OUTPUT->header();
?>
<style>
*{box-sizing:border-box}
.portal-container{max-width:1400px;margin:0 auto;padding:20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
.portal-header{background:linear-gradient(135deg,#008ba3,#00bcd4);color:#fff;padding:24px;border-radius:12px;margin-bottom:24px}
.portal-header h1{margin:0 0 8px 0;font-size:1.6em;color:#ffffff;text-shadow:1px 1px 2px rgba(0,0,0,0.2)}
.portal-header .empresa{background:rgba(255,255,255,0.2);padding:4px 12px;border-radius:20px;font-size:0.9em}
.portal-header .user-info{margin-top:8px;opacity:0.9;font-size:0.9em}
.filters{background:#fff;padding:16px;border-radius:8px;margin-bottom:20px;display:flex;gap:12px;align-items:end;flex-wrap:wrap;box-shadow:0 2px 4px rgba(0,0,0,0.05)}
.filters label{font-size:0.85em;color:#64748b;display:block;margin-bottom:4px}
.filters input[type="text"],.filters input[type="date"],.filters select{padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:0.95em}
.filters select{min-width:160px;background:#fff}
.filters input[type="text"]{min-width:180px}
.btn{padding:8px 14px;border-radius:6px;text-decoration:none;font-size:0.85em;display:inline-flex;align-items:center;gap:4px;border:none;cursor:pointer}
.btn-primary{background:#008ba3;color:#fff}
.btn-primary:hover{background:#007a91}
.btn-success{background:#27ae60;color:#fff;white-space:nowrap;min-width:100px}
.btn-success:hover{background:#219a52}
.btn-warning{background:#f39c12;color:#fff}
.btn-warning:hover{background:#e67e22}
.btn-outline{background:#fff;color:#64748b;border:1px solid #e2e8f0;white-space:nowrap;min-width:90px}
.btn-outline:hover{background:#f8fafc}
.btn-outline.active{background:#008ba3;color:#fff;border-color:#008ba3}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:10px;margin-bottom:20px}
.stat{background:#fff;padding:12px;border-radius:8px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05)}
.stat .num{font-size:1.5em;font-weight:700;color:#008ba3}
.stat.green .num{color:#27ae60}
.stat.red .num{color:#e74c3c}
.stat.orange .num{color:#f39c12}
.stat.blue .num{color:#3498db}
.stat .lbl{color:#64748b;font-size:0.75em;margin-top:2px}
.table-container{background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,0.05)}
.table-header{padding:14px 16px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.table-header h3{margin:0;font-size:1em;color:#334155}
.table-scroll{overflow-x:auto}
table{width:100%;min-width:900px;border-collapse:collapse}
th,td{padding:10px 12px;text-align:left;border-bottom:1px solid #f1f5f9;font-size:0.85em}
th{background:#f8fafc;font-size:0.7em;color:#64748b;text-transform:uppercase;font-weight:600}
tr:hover{background:#fafbfc}
.pct{padding:3px 8px;border-radius:10px;font-size:0.8em;font-weight:500;display:inline-block;min-width:45px;text-align:center}
.pct.green{background:#dcfce7;color:#166534}
.pct.orange{background:#fef3c7;color:#92400e}
.pct.red{background:#fee2e2;color:#991b1b}
.pct.gray{background:#f1f5f9;color:#64748b}
.btn-ver{background:#008ba3;color:#ffffff !important;padding:6px 12px;border-radius:4px;font-size:0.85em;text-decoration:none;display:inline-block;font-weight:500;text-align:center;min-width:40px}
.btn-ver:hover{background:#007a91;color:#fff}
.fecha{font-size:0.8em;color:#64748b}
.alerta-badge{display:inline-block;padding:2px 6px;border-radius:4px;font-size:0.7em;margin-left:4px;font-weight:500}
.alerta-badge.rojo{background:#fee2e2;color:#991b1b}
.alerta-badge.naranja{background:#fef3c7;color:#92400e}
.alerta-badge.azul{background:#dbeafe;color:#1e40af}
.row-alerta{background:#fffbeb}
.row-alerta-grave{background:#fef2f2}
.alumno-name{font-weight:600;color:#334155}
.alumno-email{font-size:0.8em;color:#94a3b8}
.alertas-resumen{background:#fef3c7;border:1px solid #fcd34d;padding:12px 16px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px}
.alertas-resumen.limpio{background:#dcfce7;border-color:#86efac}
@media(max-width:768px){
    .filters{flex-direction:column;align-items:stretch}
    .filters>div{width:100%}
    .stats{grid-template-columns:repeat(4,1fr)}
}
</style>
<div class="portal-container">
    <div class="portal-header">
        <h1>Portal RRHH - Control de Asistencia</h1>
        <span class="empresa"><?php echo htmlspecialchars($empresa_usuario); ?></span>
        <div class="user-info"><?php echo htmlspecialchars($USER->firstname . ' ' . $USER->lastname); ?></div>
    </div>
    
    <?php if ($alertas_count > 0): ?>
    <div class="alertas-resumen">
        <strong><?php echo $alertas_count; ?> alumnos requieren atención</strong>
        <span style="color:#92400e;font-size:0.9em">(baja asistencia, clases sin agendar o curso por vencer)</span>
    </div>
    <?php else: ?>
    <div class="alertas-resumen limpio">
        <strong>Todo en orden</strong> - No hay alertas activas
    </div>
    <?php endif; ?>
    
    <form class="filters" method="get">
        <?php if ($es_admin): ?>
        <div>
            <label>Empresa</label>
            <select name="empresa" onchange="this.form.submit()">
                <?php foreach ($empresas_disponibles as $key => $emp): ?>
                <option value="<?php echo $key; ?>" <?php echo $empresa_seleccionada === $key ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($emp['nombre']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label>Buscar alumno</label>
            <input type="text" name="buscar" value="<?php echo htmlspecialchars($buscar); ?>" placeholder="Nombre...">
        </div>
        <div>
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
        <div>
            <label>&nbsp;</label>
            <a href="?<?php echo $es_admin ? 'empresa=' . $empresa_seleccionada . '&' : ''; ?>problemas=1" 
               class="btn btn-outline <?php echo $solo_problemas ? 'active' : ''; ?>">
                Solo alertas
            </a>
        </div>
        <div style="margin-left:auto">
            <label>&nbsp;</label>
            <a href="?<?php echo $es_admin ? "empresa=" . $empresa_seleccionada . "&" : ""; ?>export=1" class="btn btn-success" style="white-space:nowrap;">Exportar Excel</a>
        </div>
    </form>

    <div class="stats">
        <div class="stat"><div class="num"><?php echo $total_alumnos; ?></div><div class="lbl">Alumnos</div></div>
        <div class="stat"><div class="num"><?php echo $total_cursos; ?></div><div class="lbl">Cursos</div></div>
        <div class="stat blue"><div class="num"><?php echo $total_contratadas; ?></div><div class="lbl">Contratadas</div></div>
        <div class="stat green"><div class="num"><?php echo $total_asistidas; ?></div><div class="lbl">Asistidas</div></div>
        <div class="stat red"><div class="num"><?php echo $total_ausencias; ?></div><div class="lbl">Ausencias</div></div>
        <div class="stat orange"><div class="num"><?php echo $total_pendientes; ?></div><div class="lbl">Pendientes</div></div>
        <div class="stat <?php echo $pct >= 75 ? 'green' : ($pct >= 50 ? 'orange' : 'red'); ?>"><div class="num"><?php echo $pct; ?>%</div><div class="lbl">Asistencia</div></div>
    </div>

    <div class="table-container">
        <div class="table-header">
            <h3>Detalle por Alumno (<?php echo count($alumnos); ?><?php echo $solo_problemas ? ' con alertas' : ''; ?>)</h3>
        </div>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Curso</th>
                    <th>Período</th>
                    <th>Contrat.</th>
                    <th>Agend.</th>
                    <th>Asist.</th>
                    <th>Ausenc.</th>
                    <th>% Asist.</th>
                    <th>Alertas</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($alumnos)): ?>
                <tr><td colspan="10" style="text-align:center;padding:40px;color:#64748b;">No hay registros</td></tr>
            <?php else: ?>
                <?php foreach ($alumnos as $a): 
                    $cls_pct = $a['porcentaje'] < 0 ? 'gray' : ($a['porcentaje'] >= 75 ? 'green' : ($a['porcentaje'] >= 50 ? 'orange' : 'red'));
                    $f_ini = $a['fecha_inicio'] ? date('d/m/Y', strtotime($a['fecha_inicio'])) : '-';
                    $f_fin = $a['fecha_fin'] ? date('d/m/Y', strtotime($a['fecha_fin'])) : '-';
                    $row_class = in_array('baja_asistencia', $a['alertas']) ? 'row-alerta-grave' : (count($a['alertas']) > 0 ? 'row-alerta' : '');
                ?>
                <tr class="<?php echo $row_class; ?>">
                    <td>
                        <span class="alumno-name"><?php echo htmlspecialchars($a['lastname'] . ', ' . $a['firstname']); ?></span><br>
                        <span class="alumno-email"><?php echo htmlspecialchars($a['email']); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($a['curso']); ?></td>
                    <td class="fecha"><?php echo $f_ini; ?> - <?php echo $f_fin; ?></td>
                    <td style="font-weight:600;color:#3498db"><?php echo $a['contratadas'] ?: '-'; ?></td>
                    <td><?php echo $a['total_agendadas']; ?></td>
                    <td style="color:#27ae60;font-weight:600"><?php echo $a['asistidas'] ?: 0; ?></td>
                    <td style="color:#e74c3c;font-weight:600"><?php echo $a['ausencias'] ?: 0; ?></td>
                    <td><span class="pct <?php echo $cls_pct; ?>"><?php echo $a['porcentaje'] >= 0 ? $a['porcentaje'] . '%' : '-'; ?></span></td>
                    <td>
                        <?php if (in_array('baja_asistencia', $a['alertas'])): ?>
                            <span class="alerta-badge rojo">Baja asist.</span>
                        <?php endif; ?>
                        <?php if (in_array('sin_agendar', $a['alertas'])): ?>
                            <span class="alerta-badge naranja"><?php echo $a['sin_agendar']; ?> sin agend.</span>
                        <?php endif; ?>
                        <?php if (in_array('por_vencer', $a['alertas'])): ?>
                            <span class="alerta-badge azul">Por vencer</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="../misclases.php?userid=<?php echo $a['userid']; ?>&courseid=<?php echo $a['courseid']; ?>" class="btn-ver" target="_blank">Ver</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php echo $OUTPUT->footer(); ?>
