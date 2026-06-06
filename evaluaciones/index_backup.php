<?php
/**
 * PANEL DE EVALUACIONES TUSPEAKING v1
 * Sistema independiente de gestión de evaluaciones
 */

$DB_CONFIG = ['host'=>'localhost','database'=>'aulatuspeaking35','user'=>'moodle35','password'=>'TuspeakingFix2025!'];
try { $pdo = new PDO("mysql:host={$DB_CONFIG['host']};dbname={$DB_CONFIG['database']};charset=utf8mb4",$DB_CONFIG['user'],$DB_CONFIG['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); } catch(PDOException $e) { die("Error: ".$e->getMessage()); }

$mensaje = ''; $error = '';

// Procesar acciones
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $action = $_POST['action'] ?? '';
    
    if($action === 'guardar_evaluacion'){
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("UPDATE mdl_coding_evaluaciones SET 
            certificado_tipo = ?,
            fecha_inicio_curso = ?,
            fecha_fin_curso = ?,
            horas_curso = ?,
            fecha_modificacion = NOW()
            WHERE id = ?");
        $stmt->execute([
            $_POST['certificado_tipo'],
            $_POST['fecha_inicio_curso'],
            $_POST['fecha_fin_curso'],
            $_POST['horas_curso'],
            $id
        ]);
        $mensaje = "✓ Evaluación actualizada";
    }
    
    if($action === 'recalcular_tipos'){
        $stmt = $pdo->query("UPDATE mdl_coding_evaluaciones SET certificado_tipo = CASE WHEN nota_final >= 7.5 THEN 'superacion' ELSE 'participacion' END");
        $mensaje = "✓ Tipos recalculados según nota ≥ 7.5";
    }
}

// Cargar datos
$empresas = $pdo->query("SELECT * FROM mdl_coding_empresas WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$emp_filtro = $_GET['empresa'] ?? ($empresas[0]['codigo'] ?? '');

$stmt = $pdo->prepare("SELECT e.*, emp.nombre as empresa_nombre 
    FROM mdl_coding_evaluaciones e 
    JOIN mdl_coding_empresas emp ON e.empresa_id = emp.id 
    WHERE emp.codigo = ? 
    ORDER BY e.nivel_evaluado, e.alumno_nombre");
$stmt->execute([$emp_filtro]);
$evaluaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$stats = ['total'=>0,'superacion'=>0,'participacion'=>0,'sin_tipo'=>0];
foreach($evaluaciones as $ev){
    $stats['total']++;
    if($ev['certificado_tipo']==='superacion') $stats['superacion']++;
    elseif($ev['certificado_tipo']==='participacion') $stats['participacion']++;
    else $stats['sin_tipo']++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Panel de Evaluaciones - TuSpeaking</title>
<style>
*{box-sizing:border-box}
body{font-family:'Segoe UI',Arial,sans-serif;margin:0;padding:20px;background:#f0f2f5}
.container{max-width:1400px;margin:0 auto}
h1{color:#1a1a2e;margin-bottom:5px}
.subtitle{color:#666;margin-bottom:20px}
.card{background:#fff;border-radius:12px;padding:25px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.card h2{margin-top:0;color:#1a1a2e;font-size:1.3em}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:15px;margin-bottom:25px}
.stat{padding:20px;border-radius:12px;text-align:center;color:#fff}
.stat.purple{background:linear-gradient(135deg,#667eea,#764ba2)}
.stat.green{background:linear-gradient(135deg,#11998e,#38ef7d)}
.stat.yellow{background:linear-gradient(135deg,#f093fb,#f5576c)}
.stat.gray{background:linear-gradient(135deg,#434343,#000)}
.stat h3{margin:0;font-size:2.2em}
.stat p{margin:5px 0 0;opacity:.9;font-size:.85em}
table{width:100%;border-collapse:collapse}
th,td{padding:10px 12px;text-align:left;border-bottom:1px solid #eee}
th{background:#f8f9fa;color:#333;font-weight:600;font-size:.8em;text-transform:uppercase}
tr:hover{background:#f8f9fa}
.btn{padding:8px 16px;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:500;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:#667eea;color:#fff}
.btn-success{background:#10b981;color:#fff}
.btn-warning{background:#f59e0b;color:#fff}
.btn-sm{padding:5px 10px;font-size:12px}
.btn:hover{opacity:.9}
.alert{padding:12px 18px;border-radius:8px;margin-bottom:20px}
.alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
.badge{padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600}
.badge-superacion{background:#d1fae5;color:#065f46}
.badge-participacion{background:#fef3c7;color:#92400e}
.badge-ninguno{background:#e5e7eb;color:#374151}
.nivel-badge{padding:3px 8px;border-radius:4px;font-size:11px;font-weight:600}
.nivel-B1{background:#dbeafe;color:#1e40af}
.nivel-B2{background:#fef3c7;color:#92400e}
.nivel-C1{background:#d1fae5;color:#065f46}
.nivel-A1,.nivel-A2{background:#fce7f3;color:#9d174d}
select.form-select{padding:10px 14px;font-size:14px;border:1px solid #d1d5db;border-radius:8px;min-width:200px}
input.input-sm{padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px}
select.select-sm{padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px}
.editable{background:#fffbeb;border-color:#fcd34d}
.actions-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;padding:15px;background:#f8f9fa;border-radius:8px}
footer{text-align:center;padding:20px;color:#9ca3af;font-size:.85em}
.nav-links{margin-bottom:20px}
.nav-links a{color:#667eea;text-decoration:none;margin-right:20px}
.nav-links a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="container">

<div class="nav-links">
    <a href="../certificados/">← Volver a Certificados</a>
    <a href="formulario.php">📝 Formulario Profesor</a>
</div>

<h1>📊 Panel de Evaluaciones</h1>
<p class="subtitle">TuSpeaking - Gestión de evaluaciones de profesores</p>

<?php if($mensaje):?><div class="alert alert-success"><?=$mensaje?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error"><?=$error?></div><?php endif;?>

<div class="stats">
    <div class="stat purple"><h3><?=$stats['total']?></h3><p>Evaluaciones</p></div>
    <div class="stat green"><h3><?=$stats['superacion']?></h3><p>Superación</p></div>
    <div class="stat yellow"><h3><?=$stats['participacion']?></h3><p>Participación</p></div>
    <div class="stat gray"><h3><?=$stats['sin_tipo']?></h3><p>Sin asignar</p></div>
</div>

<div class="card">
    <h2>🏢 Seleccionar Empresa</h2>
    <form method="get">
        <select name="empresa" class="form-select" onchange="this.form.submit()">
            <?php foreach($empresas as $emp):?>
            <option value="<?=$emp['codigo']?>" <?=$emp['codigo']==$emp_filtro?'selected':''?>><?=htmlspecialchars($emp['nombre'])?></option>
            <?php endforeach;?>
        </select>
    </form>
</div>

<div class="card">
    <h2>📋 Evaluaciones - <?=htmlspecialchars($emp_filtro)?></h2>
    
    <div class="actions-bar">
        <form method="post" style="display:inline">
            <input type="hidden" name="action" value="recalcular_tipos">
            <button type="submit" class="btn btn-warning" onclick="return confirm('¿Recalcular tipos según nota ≥ 7.5?')">🔄 Recalcular Tipos</button>
        </form>
        <a href="../certificados/?modo=evaluaciones&empresa=<?=$emp_filtro?>" class="btn btn-primary">🎓 Ir a Certificados</a>
    </div>
    
    <?php if(count($evaluaciones)>0):?>
    <table>
        <thead>
            <tr>
                <th>Alumno</th>
                <th>Profesor</th>
                <th>Nivel</th>
                <th>Nota</th>
                <th>Tipo</th>
                <th>Horas</th>
                <th>Fecha Inicio</th>
                <th>Fecha Fin</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($evaluaciones as $ev):?>
            <tr>
                <td><strong><?=htmlspecialchars($ev['alumno_nombre'])?></strong></td>
                <td><?=htmlspecialchars($ev['profesor'])?></td>
                <td><span class="nivel-badge nivel-<?=$ev['nivel_evaluado']?>"><?=$ev['nivel_evaluado']?></span></td>
                <td><?=$ev['nota_final']?number_format($ev['nota_final'],2):'-'?></td>
                <td><span class="badge badge-<?=$ev['certificado_tipo']?>"><?=ucfirst($ev['certificado_tipo'])?></span></td>
                <td><?=$ev['horas_curso']?>h</td>
                <td><?=htmlspecialchars($ev['fecha_inicio_curso'] ?? '-')?></td>
                <td><?=htmlspecialchars($ev['fecha_fin_curso'] ?? '-')?></td>
                <td><button class="btn btn-sm btn-primary" onclick="editarEval(<?=$ev['id']?>)">✏️</button></td>
            </tr>
        <?php endforeach;?>
        </tbody>
    </table>
    <?php else:?>
    <p style="text-align:center;color:#999;padding:40px">No hay evaluaciones para esta empresa</p>
    <?php endif;?>
</div>

<!-- Modal edición -->
<div id="modalEditar" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000">
    <div style="background:#fff;max-width:500px;margin:100px auto;padding:30px;border-radius:12px">
        <h3 style="margin-top:0">✏️ Editar Evaluación</h3>
        <form method="post" id="formEditar">
            <input type="hidden" name="action" value="guardar_evaluacion">
            <input type="hidden" name="id" id="edit_id">
            <div style="margin-bottom:15px">
                <label style="display:block;margin-bottom:5px;font-weight:600">Tipo Certificado</label>
                <select name="certificado_tipo" id="edit_tipo" class="form-select" style="width:100%">
                    <option value="superacion">Superación</option>
                    <option value="participacion">Participación</option>
                    <option value="ninguno">Ninguno</option>
                </select>
            </div>
            <div style="margin-bottom:15px">
                <label style="display:block;margin-bottom:5px;font-weight:600">Horas Curso</label>
                <input type="number" name="horas_curso" id="edit_horas" class="input-sm" style="width:100%">
            </div>
            <div style="margin-bottom:15px">
                <label style="display:block;margin-bottom:5px;font-weight:600">Fecha Inicio</label>
                <input type="text" name="fecha_inicio_curso" id="edit_fecha_inicio" class="input-sm" style="width:100%" placeholder="Ej: 2 de Octubre de 2025">
            </div>
            <div style="margin-bottom:15px">
                <label style="display:block;margin-bottom:5px;font-weight:600">Fecha Fin</label>
                <input type="text" name="fecha_fin_curso" id="edit_fecha_fin" class="input-sm" style="width:100%" placeholder="Ej: 18 de Diciembre de 2025">
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-success">💾 Guardar</button>
                <button type="button" class="btn btn-secondary" onclick="cerrarModal()" style="background:#6b7280;color:#fff">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<footer>TuSpeaking © 2025 - Panel de Evaluaciones v1</footer>
</div>

<script>
const evaluaciones = <?=json_encode($evaluaciones)?>;

function editarEval(id) {
    const ev = evaluaciones.find(e => e.id == id);
    if(!ev) return;
    document.getElementById('edit_id').value = ev.id;
    document.getElementById('edit_tipo').value = ev.certificado_tipo || 'participacion';
    document.getElementById('edit_horas').value = ev.horas_curso || 12;
    document.getElementById('edit_fecha_inicio').value = ev.fecha_inicio_curso || '';
    document.getElementById('edit_fecha_fin').value = ev.fecha_fin_curso || '';
    document.getElementById('modalEditar').style.display = 'block';
}

function cerrarModal() {
    document.getElementById('modalEditar').style.display = 'none';
}

document.getElementById('modalEditar').addEventListener('click', function(e) {
    if(e.target === this) cerrarModal();
});
</script>
</body>
</html>
