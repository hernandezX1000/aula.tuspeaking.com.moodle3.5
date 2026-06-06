<?php
/**
 * PANEL DE EVALUACIONES TUSPEAKING v2
 * Integra CESCE + Serviguide
 */
$DB_CONFIG = ['host'=>'localhost','database'=>'aulatuspeaking35','user'=>'moodle35','password'=>'TuspeakingFix2025!'];
try { $pdo = new PDO("mysql:host={$DB_CONFIG['host']};dbname={$DB_CONFIG['database']};charset=utf8mb4",$DB_CONFIG['user'],$DB_CONFIG['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); } catch(PDOException $e) { die("Error: ".$e->getMessage()); }

$mensaje = ''; $error = '';
$empresas = $pdo->query("SELECT * FROM mdl_coding_empresas WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$emp_filtro = $_GET['empresa'] ?? ($empresas[0]['codigo'] ?? '');
$es_cesce = (strtoupper($emp_filtro) === 'CESCE');

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $action = $_POST['action'] ?? '';
    if($action === 'guardar_evaluacion'){
        $id = $_POST['id'] ?? 0;
        if($es_cesce){
            $stmt = $pdo->prepare("UPDATE mdl_cesce_calificaciones SET nota_oral=?, nota_clase=?, comentario_profesor=?, evaluador=?, fecha_evaluacion=CURDATE(), fecha_modificacion=NOW() WHERE id=?");
            $stmt->execute([$_POST['nota_oral']?:null, $_POST['nota_clase']?:null, $_POST['comentario_profesor']??'', $_POST['evaluador']??'', $id]);
            $pdo->exec("UPDATE mdl_cesce_calificaciones SET nota_final=ROUND(COALESCE(nota_plataforma,0)*0.30+COALESCE(nota_oral,0)*0.40+COALESCE(nota_clase,0)*0.30,2), pass_yn=CASE WHEN ROUND(COALESCE(nota_plataforma,0)*0.30+COALESCE(nota_oral,0)*0.40+COALESCE(nota_clase,0)*0.30,2)>=5 AND asistencia_porcentaje>=75 THEN 'Y' ELSE 'N' END, diploma=CASE WHEN ROUND(COALESCE(nota_plataforma,0)*0.30+COALESCE(nota_oral,0)*0.40+COALESCE(nota_clase,0)*0.30,2)>=5 AND asistencia_porcentaje>=75 THEN 'Superacion' WHEN asistencia_porcentaje>=75 THEN 'Participacion' ELSE 'Sin certificado' END WHERE id=$id");
        } else {
            $stmt = $pdo->prepare("UPDATE mdl_coding_evaluaciones SET certificado_tipo=?, fecha_inicio_curso=?, fecha_fin_curso=?, horas_curso=?, fecha_modificacion=NOW() WHERE id=?");
            $stmt->execute([$_POST['certificado_tipo'], $_POST['fecha_inicio_curso'], $_POST['fecha_fin_curso'], $_POST['horas_curso'], $id]);
        }
        $mensaje = "✓ Evaluacion actualizada";
    }
    if($action === 'recalcular_tipos'){
        if($es_cesce){
            $pdo->exec("UPDATE mdl_cesce_calificaciones SET nota_final=ROUND(COALESCE(nota_plataforma,0)*0.30+COALESCE(nota_oral,0)*0.40+COALESCE(nota_clase,0)*0.30,2) WHERE edicion='25.2' AND (nota_plataforma IS NOT NULL OR nota_oral IS NOT NULL OR nota_clase IS NOT NULL)");
            $pdo->exec("UPDATE mdl_cesce_calificaciones SET pass_yn=CASE WHEN nota_final>=5 AND asistencia_porcentaje>=75 THEN 'Y' ELSE 'N' END, diploma=CASE WHEN nota_final>=5 AND asistencia_porcentaje>=75 THEN 'Superacion' WHEN asistencia_porcentaje>=75 THEN 'Participacion' ELSE 'Sin certificado' END WHERE edicion='25.2' AND nota_final IS NOT NULL");
            $mensaje = "✓ Notas recalculadas para CESCE";
        } else {
            $pdo->exec("UPDATE mdl_coding_evaluaciones SET certificado_tipo=CASE WHEN nota_final>=7.5 THEN 'superacion' ELSE 'participacion' END");
            $mensaje = "✓ Tipos recalculados";
        }
    }
    if($action === 'actualizar_asistencia' && $es_cesce){
        $pdo->exec("UPDATE mdl_cesce_calificaciones cal SET clases_previstas=(SELECT COUNT(*) FROM mdl_cesce_acuityZoom az WHERE LOWER(az.acuity_email)=LOWER(cal.empleado_email) AND az.acuity_datetime>='2025-09-01' AND az.acuity_datetime<='2025-12-19' AND az.acuity_firstname!='Booking') WHERE edicion='25.2'");
        $pdo->exec("UPDATE mdl_cesce_calificaciones cal SET clases_asistidas=(SELECT COUNT(DISTINCT a.zoom_meetingid) FROM mdl_cesce_asistencia a WHERE LOWER(a.empleado_email)=LOWER(cal.empleado_email) AND a.join_time>='2025-09-01' AND a.join_time<='2025-12-19 23:59:59' AND a.duration_minutes>=30) WHERE edicion='25.2'");
        $pdo->exec("UPDATE mdl_cesce_calificaciones SET asistencia_porcentaje=CASE WHEN clases_previstas>0 THEN ROUND((clases_asistidas/clases_previstas)*100,2) ELSE 0 END WHERE edicion='25.2'");
        $mensaje = "✓ Asistencia actualizada desde Zoom";
    }
}

if($es_cesce){
    $evaluaciones = $pdo->query("SELECT c.*, e.firstname, e.lastname, e.codigo_empleado, 'CESCE' as empresa_nombre FROM mdl_cesce_calificaciones c LEFT JOIN mdl_cesce_empleados e ON LOWER(e.email)=LOWER(c.empleado_email) WHERE c.edicion='25.2' ORDER BY c.grupo, e.lastname")->fetchAll(PDO::FETCH_ASSOC);
    $stats = ['total'=>0,'superacion'=>0,'participacion'=>0,'sin_tipo'=>0,'con_oral'=>0];
    foreach($evaluaciones as $ev){ $stats['total']++; if($ev['diploma']==='Superacion') $stats['superacion']++; elseif($ev['diploma']==='Participacion') $stats['participacion']++; else $stats['sin_tipo']++; if($ev['nota_oral']) $stats['con_oral']++; }
} else {
    $stmt = $pdo->prepare("SELECT e.*, emp.nombre as empresa_nombre FROM mdl_coding_evaluaciones e JOIN mdl_coding_empresas emp ON e.empresa_id=emp.id WHERE emp.codigo=? ORDER BY e.nivel_evaluado, e.alumno_nombre");
    $stmt->execute([$emp_filtro]);
    $evaluaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats = ['total'=>0,'superacion'=>0,'participacion'=>0,'sin_tipo'=>0];
    foreach($evaluaciones as $ev){ $stats['total']++; if($ev['certificado_tipo']==='superacion') $stats['superacion']++; elseif($ev['certificado_tipo']==='participacion') $stats['participacion']++; else $stats['sin_tipo']++; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Panel de Evaluaciones - TuSpeaking</title>
<style>
*{box-sizing:border-box}body{font-family:'Segoe UI',Arial,sans-serif;margin:0;padding:20px;background:#f0f2f5}.container{max-width:1400px;margin:0 auto}h1{color:#1a1a2e;margin-bottom:5px}.subtitle{color:#666;margin-bottom:20px}.card{background:#fff;border-radius:12px;padding:25px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.08)}.card h2{margin-top:0;color:#1a1a2e;font-size:1.3em}.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:15px;margin-bottom:25px}.stat{padding:18px;border-radius:12px;text-align:center;color:#fff}.stat.purple{background:linear-gradient(135deg,#667eea,#764ba2)}.stat.green{background:linear-gradient(135deg,#11998e,#38ef7d)}.stat.yellow{background:linear-gradient(135deg,#f093fb,#f5576c)}.stat.gray{background:linear-gradient(135deg,#434343,#000)}.stat.blue{background:linear-gradient(135deg,#2193b0,#6dd5ed)}.stat h3{margin:0;font-size:2em}.stat p{margin:5px 0 0;opacity:.9;font-size:.8em}table{width:100%;border-collapse:collapse;font-size:14px}th,td{padding:10px 8px;text-align:left;border-bottom:1px solid #eee}th{background:#f8f9fa;color:#333;font-weight:600;font-size:.75em;text-transform:uppercase;position:sticky;top:0}tr:hover{background:#f8f9fa}.btn{padding:8px 16px;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:500;text-decoration:none;display:inline-flex;align-items:center;gap:5px}.btn-primary{background:#667eea;color:#fff}.btn-success{background:#10b981;color:#fff}.btn-warning{background:#f59e0b;color:#fff}.btn-info{background:#3b82f6;color:#fff}.btn-sm{padding:5px 10px;font-size:12px}.btn:hover{opacity:.9}.alert{padding:12px 18px;border-radius:8px;margin-bottom:20px}.alert-success{background:#d1fae5;color:#065f46}.badge{padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600}.badge-superacion,.badge-Y{background:#d1fae5;color:#065f46}.badge-participacion{background:#fef3c7;color:#92400e}.badge-ninguno,.badge-N{background:#fee2e2;color:#991b1b}.badge-pending{background:#e5e7eb;color:#374151}.nivel-badge{padding:3px 8px;border-radius:4px;font-size:11px;font-weight:600}.nivel-B1{background:#dbeafe;color:#1e40af}.nivel-B2{background:#fef3c7;color:#92400e}.nivel-C1{background:#d1fae5;color:#065f46}.nivel-A1,.nivel-A2{background:#fce7f3;color:#9d174d}select.form-select{padding:10px 14px;font-size:14px;border:1px solid #d1d5db;border-radius:8px;min-width:200px}.actions-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;padding:15px;background:#f8f9fa;border-radius:8px}.nav-links{margin-bottom:20px}.nav-links a{color:#667eea;text-decoration:none;margin-right:20px}.empresa-cesce{border-left:4px solid #667eea}.nota-alta{color:#065f46}.nota-media{color:#92400e}.nota-baja{color:#991b1b}.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;overflow-y:auto}.modal-content{background:#fff;max-width:600px;margin:50px auto;padding:30px;border-radius:12px}.form-group{margin-bottom:15px}.form-group label{display:block;margin-bottom:5px;font-weight:600}.form-control{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px}footer{text-align:center;padding:20px;color:#9ca3af;font-size:.85em}
</style>
</head>
<body>
<div class="container">
<div class="nav-links"><a href="../certificados/">← Certificados</a> <a href="formulario.php">📝 Formulario</a><?php if($es_cesce):?> <a href="../reporte_cesce.php">📋 Reporte Asistencia</a><?php endif;?></div>
<h1>📊 Panel de Evaluaciones</h1>
<p class="subtitle">TuSpeaking - Gestion de evaluaciones</p>
<?php if($mensaje):?><div class="alert alert-success"><?=$mensaje?></div><?php endif;?>
<div class="stats">
    <div class="stat purple"><h3><?=$stats['total']?></h3><p>Evaluaciones</p></div>
    <div class="stat green"><h3><?=$stats['superacion']?></h3><p>Superacion</p></div>
    <div class="stat yellow"><h3><?=$stats['participacion']?></h3><p>Participacion</p></div>
    <?php if($es_cesce):?><div class="stat blue"><h3><?=$stats['con_oral']?></h3><p>Con Nota Oral</p></div><?php endif;?>
    <div class="stat gray"><h3><?=$stats['sin_tipo']?></h3><p>Sin asignar</p></div>
</div>
<div class="card <?=$es_cesce?'empresa-cesce':''?>">
    <h2>🏢 Seleccionar Empresa</h2>
    <form method="get"><select name="empresa" class="form-select" onchange="this.form.submit()"><?php foreach($empresas as $emp):?><option value="<?=$emp['codigo']?>" <?=$emp['codigo']==$emp_filtro?'selected':''?>><?=htmlspecialchars($emp['nombre'])?></option><?php endforeach;?></select></form>
</div>
<div class="card">
    <h2>📋 Evaluaciones - <?=htmlspecialchars($emp_filtro)?></h2>
    <div class="actions-bar">
        <?php if($es_cesce):?><form method="post" style="display:inline"><input type="hidden" name="action" value="actualizar_asistencia"><button type="submit" class="btn btn-info" onclick="return confirm('Actualizar asistencia?')">📊 Actualizar Asistencia</button></form><?php endif;?>
        <form method="post" style="display:inline"><input type="hidden" name="action" value="recalcular_tipos"><button type="submit" class="btn btn-warning">🔄 Recalcular</button></form>
        <a href="../certificados/?empresa=<?=$emp_filtro?>" class="btn btn-primary">🎓 Certificados</a>
    </div>
    <?php if(count($evaluaciones)>0):?>
    <div style="overflow-x:auto;max-height:600px"><table><thead><tr><th>Alumno</th><?php if($es_cesce):?><th>Grupo</th><th>Nivel</th><th>Plat.</th><th>Oral</th><th>Clase</th><th>Final</th><th>Asist%</th><th>Pass</th><th>Diploma</th><?php else:?><th>Profesor</th><th>Nivel</th><th>Nota</th><th>Tipo</th><th>Horas</th><th>F.Inicio</th><th>F.Fin</th><?php endif;?><th>Acc.</th></tr></thead><tbody>
    <?php foreach($evaluaciones as $ev):
        if($es_cesce){ $nombre=trim(($ev['firstname']??'').' '.($ev['lastname']??'')); if(!$nombre) $nombre=explode('@',$ev['empleado_email'])[0]; $nc=''; if($ev['nota_final']!==null){if($ev['nota_final']>=7)$nc='nota-alta';elseif($ev['nota_final']>=5)$nc='nota-media';else $nc='nota-baja';} }
    ?><tr><?php if($es_cesce):?>
        <td><strong><?=htmlspecialchars($nombre)?></strong><br><small style="color:#999"><?=htmlspecialchars($ev['empleado_email'])?></small></td>
        <td><small><?=htmlspecialchars(substr($ev['grupo'],0,30))?></small></td>
        <td><span class="nivel-badge nivel-<?=$ev['nivel']?>"><?=$ev['nivel']?></span></td>
        <td><?=$ev['nota_plataforma']?number_format($ev['nota_plataforma'],1):'-'?></td>
        <td><?=$ev['nota_oral']?number_format($ev['nota_oral'],1):'<span style="color:#f59e0b">⚠️</span>'?></td>
        <td><?=$ev['nota_clase']?number_format($ev['nota_clase'],1):'<span style="color:#f59e0b">⚠️</span>'?></td>
        <td class="<?=$nc?>"><?=$ev['nota_final']?number_format($ev['nota_final'],2):'-'?></td>
        <td><?=$ev['asistencia_porcentaje']?number_format($ev['asistencia_porcentaje'],0).'%':'-'?></td>
        <td><?=$ev['pass_yn']?'<span class="badge badge-'.$ev['pass_yn'].'">'.($ev['pass_yn']=='Y'?'✓':'✗').'</span>':'<span class="badge badge-pending">-</span>'?></td>
        <td><?=htmlspecialchars($ev['diploma']??'-')?></td>
    <?php else:?>
        <td><strong><?=htmlspecialchars($ev['alumno_nombre'])?></strong></td>
        <td><?=htmlspecialchars($ev['profesor'])?></td>
        <td><span class="nivel-badge nivel-<?=$ev['nivel_evaluado']?>"><?=$ev['nivel_evaluado']?></span></td>
        <td><?=$ev['nota_final']?number_format($ev['nota_final'],2):'-'?></td>
        <td><span class="badge badge-<?=$ev['certificado_tipo']?>"><?=ucfirst($ev['certificado_tipo']??'ninguno')?></span></td>
        <td><?=$ev['horas_curso']?>h</td>
        <td><?=htmlspecialchars($ev['fecha_inicio_curso']??'-')?></td>
        <td><?=htmlspecialchars($ev['fecha_fin_curso']??'-')?></td>
    <?php endif;?>
        <td><button class="btn btn-sm btn-primary" onclick="editarEval(<?=htmlspecialchars(json_encode($ev))?>)">✏️</button></td>
    </tr><?php endforeach;?></tbody></table></div>
    <?php else:?><p style="text-align:center;color:#999;padding:40px">No hay evaluaciones</p><?php endif;?>
</div>
<div id="modalEditar" class="modal"><div class="modal-content"><h3>✏️ Editar</h3><form method="post"><input type="hidden" name="action" value="guardar_evaluacion"><input type="hidden" name="id" id="edit_id">
<?php if($es_cesce):?>
<div style="background:#f8f9fa;padding:15px;border-radius:8px;margin-bottom:20px"><strong id="edit_nombre"></strong><br><small id="edit_email" style="color:#666"></small></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:15px"><div class="form-group"><label>Nota Oral (40%)</label><input type="number" name="nota_oral" id="edit_oral" class="form-control" min="0" max="10" step="0.1"></div><div class="form-group"><label>Nota Clase (30%)</label><input type="number" name="nota_clase" id="edit_clase" class="form-control" min="0" max="10" step="0.1"></div></div>
<div class="form-group"><label>Evaluador</label><input type="text" name="evaluador" id="edit_evaluador" class="form-control"></div>
<div class="form-group"><label>Comentarios</label><textarea name="comentario_profesor" id="edit_comentario" class="form-control" rows="3"></textarea></div>
<?php else:?>
<div class="form-group"><label>Tipo</label><select name="certificado_tipo" id="edit_tipo" class="form-control"><option value="superacion">Superacion</option><option value="participacion">Participacion</option><option value="ninguno">Ninguno</option></select></div>
<div class="form-group"><label>Horas</label><input type="number" name="horas_curso" id="edit_horas" class="form-control"></div>
<div class="form-group"><label>Fecha Inicio</label><input type="text" name="fecha_inicio_curso" id="edit_fecha_inicio" class="form-control"></div>
<div class="form-group"><label>Fecha Fin</label><input type="text" name="fecha_fin_curso" id="edit_fecha_fin" class="form-control"></div>
<?php endif;?>
<div style="display:flex;gap:10px;margin-top:20px"><button type="submit" class="btn btn-success" style="flex:1">💾 Guardar</button><button type="button" class="btn" onclick="cerrarModal()" style="background:#6b7280;color:#fff">Cancelar</button></div>
</form></div></div>
<footer>TuSpeaking © 2026 - Panel Evaluaciones v2</footer>
</div>
<script>
const esCesce=<?=$es_cesce?'true':'false'?>;
function editarEval(ev){document.getElementById('edit_id').value=ev.id;if(esCesce){let n=(ev.firstname||'')+' '+(ev.lastname||'');if(!n.trim())n=ev.empleado_email.split('@')[0];document.getElementById('edit_nombre').textContent=n;document.getElementById('edit_email').textContent=ev.empleado_email;document.getElementById('edit_oral').value=ev.nota_oral||'';document.getElementById('edit_clase').value=ev.nota_clase||'';document.getElementById('edit_evaluador').value=ev.evaluador||'';document.getElementById('edit_comentario').value=ev.comentario_profesor||'';}else{document.getElementById('edit_tipo').value=ev.certificado_tipo||'participacion';document.getElementById('edit_horas').value=ev.horas_curso||12;document.getElementById('edit_fecha_inicio').value=ev.fecha_inicio_curso||'';document.getElementById('edit_fecha_fin').value=ev.fecha_fin_curso||'';}document.getElementById('modalEditar').style.display='block';}
function cerrarModal(){document.getElementById('modalEditar').style.display='none';}
document.getElementById('modalEditar').onclick=function(e){if(e.target===this)cerrarModal();};
</script>
</body></html>
