<?php
/**
 * Panel Gestión Empresas - TuSpeaking
 */
$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset("utf8mb4");

$seccion = $_GET['s'] ?? 'dashboard';

// Procesar formularios POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Añadir empresa
    if (isset($_POST['add_empresa'])) {
        $nombre = $conn->real_escape_string($_POST['nombre']);
        $dominio = $conn->real_escape_string($_POST['dominio']);
        $contacto_nombre = $conn->real_escape_string($_POST['contacto_nombre'] ?? '');
        $contacto_email = $conn->real_escape_string($_POST['contacto_email'] ?? '');
        $conn->query("INSERT INTO own_empresas (nombre, dominio, contacto_nombre, contacto_email) 
                      VALUES ('$nombre', '$dominio', '$contacto_nombre', '$contacto_email')");
        header("Location: admin.php?s=empresas&msg=added");
        exit;
    }
    
    // Actualizar empresa
    if (isset($_POST['update_empresa'])) {
        $id = (int)$_POST['id'];
        $nombre = $conn->real_escape_string($_POST['nombre']);
        $dominio = $conn->real_escape_string($_POST['dominio']);
        $contacto_nombre = $conn->real_escape_string($_POST['contacto_nombre'] ?? '');
        $contacto_email = $conn->real_escape_string($_POST['contacto_email'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;
        $conn->query("UPDATE own_empresas SET nombre='$nombre', dominio='$dominio', 
                      contacto_nombre='$contacto_nombre', contacto_email='$contacto_email', 
                      activo=$activo WHERE id=$id");
        header("Location: admin.php?s=empresas&msg=updated");
        exit;
    }
    
    // Añadir edición (asociar categoría existente)
    if (isset($_POST['add_edicion'])) {
        $empresa_id = (int)$_POST['empresa_id'];
        $categoria_id = (int)$_POST['categoria_id'];
        $fecha_inicio = $conn->real_escape_string($_POST['fecha_inicio'] ?? '');
        $fecha_fin = $conn->real_escape_string($_POST['fecha_fin'] ?? '');
        $conn->query("INSERT INTO own_empresa_ediciones (empresa_id, categoria_id, fecha_inicio, fecha_fin) 
                      VALUES ($empresa_id, $categoria_id, NULLIF('$fecha_inicio',''), NULLIF('$fecha_fin',''))
                      ON DUPLICATE KEY UPDATE fecha_inicio=VALUES(fecha_inicio), fecha_fin=VALUES(fecha_fin)");
        header("Location: admin.php?s=ediciones&msg=added");
        exit;
    }
    
    // Actualizar edición
    if (isset($_POST['update_edicion'])) {
        $id = (int)$_POST['id'];
        $fecha_inicio = $conn->real_escape_string($_POST['fecha_inicio'] ?? '');
        $fecha_fin = $conn->real_escape_string($_POST['fecha_fin'] ?? '');
        $conn->query("UPDATE own_empresa_ediciones SET 
                      fecha_inicio=NULLIF('$fecha_inicio',''), fecha_fin=NULLIF('$fecha_fin','') 
                      WHERE id=$id");
        header("Location: admin.php?s=ediciones&msg=updated");
        exit;
    }
    
    // Eliminar edición
    if (isset($_POST['delete_edicion'])) {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM own_empresa_ediciones WHERE id=$id");
        header("Location: admin.php?s=ediciones&msg=deleted");
        exit;
    }
    
    // Crear nueva categoría en Moodle
    if (isset($_POST['crear_categoria'])) {
        $empresa_id = (int)$_POST['empresa_id'];
        $nombre_empresa = $conn->real_escape_string($_POST['nombre_empresa']);
        $anio = (int)$_POST['anio'];
        $num_edicion = $conn->real_escape_string($_POST['num_edicion'] ?? '');
        $fecha_inicio = $conn->real_escape_string($_POST['fecha_inicio'] ?? '');
        $fecha_fin = $conn->real_escape_string($_POST['fecha_fin'] ?? '');
        
        $cat_name = $anio . ' - ' . $nombre_empresa;
        if (!empty($num_edicion)) {
            $cat_name .= ' (' . $num_edicion . ')';
        }
        
        $max_sort = $conn->query("SELECT MAX(sortorder) as m FROM mdl_course_categories")->fetch_assoc()['m'];
        $sortorder = $max_sort + 1;
        
        $conn->query("INSERT INTO mdl_course_categories (name, parent, sortorder, visible, depth, path, timemodified) 
                      VALUES ('$cat_name', 0, $sortorder, 1, 1, '', UNIX_TIMESTAMP())");
        $categoria_id = $conn->insert_id;
        
        $conn->query("UPDATE mdl_course_categories SET path = '/$categoria_id' WHERE id = $categoria_id");
        
        $conn->query("INSERT INTO mdl_context (contextlevel, instanceid, depth) VALUES (40, $categoria_id, 2)");
        $context_id = $conn->insert_id;
        $conn->query("UPDATE mdl_context SET path = '/1/$context_id' WHERE id = $context_id");
        
        $conn->query("INSERT INTO own_empresa_ediciones (empresa_id, categoria_id, fecha_inicio, fecha_fin) 
                      VALUES ($empresa_id, $categoria_id, NULLIF('$fecha_inicio',''), NULLIF('$fecha_fin',''))");
        
        header("Location: admin.php?s=ediciones&msg=categoria_created");
        exit;
    }
    
    // Crear nuevo curso en Moodle
    if (isset($_POST['crear_curso'])) {
        $categoria_id = (int)$_POST['categoria_id'];
        $idioma = $conn->real_escape_string($_POST['idioma']);
        $nivel = $conn->real_escape_string($_POST['nivel']);
        
        // Obtener fechas de la edición
        $edicion = $conn->query("SELECT fecha_inicio, fecha_fin FROM own_empresa_ediciones WHERE categoria_id = $categoria_id")->fetch_assoc();
        $fecha_inicio = $edicion && $edicion['fecha_inicio'] ? strtotime($edicion['fecha_inicio']) : time();
        $fecha_fin = $edicion && $edicion['fecha_fin'] ? strtotime($edicion['fecha_fin']) : 0;
        
        $nombre_temp = $idioma . ' ' . $nivel . ' - #TEMP';
        
        $sort = $conn->query("SELECT MAX(sortorder) as m FROM mdl_course WHERE category = $categoria_id");
        $row = $sort ? $sort->fetch_assoc() : null;
        $sortorder = ($row && $row['m']) ? $row['m'] + 1 : 1;
        
        $now = time();
        $shortname_temp = 'TEMP_' . $now;
        
        $conn->query("INSERT INTO mdl_course (category, fullname, shortname, idnumber, summary, summaryformat, format, startdate, enddate, visible, timecreated, timemodified, sortorder, cacherev) 
                      VALUES ($categoria_id, '$nombre_temp', '$shortname_temp', '', '', 0, 'tiles', $fecha_inicio, $fecha_fin, 1, $now, $now, $sortorder, $now)");
        
        $course_id = $conn->insert_id;
        
        if ($course_id) {
            $nombre_final = $idioma . ' ' . $nivel . ' - #' . $course_id;
            $shortname = $idioma . '_' . $nivel . '_' . $course_id;
            $conn->query("UPDATE mdl_course SET fullname = '$nombre_final', shortname = '$shortname' WHERE id = $course_id");
            
            // Crear contexto
            $conn->query("INSERT INTO mdl_context (contextlevel, instanceid, depth) VALUES (50, $course_id, 3)");
            $context_id = $conn->insert_id;
            
            $cat_ctx = $conn->query("SELECT id, path FROM mdl_context WHERE contextlevel = 40 AND instanceid = $categoria_id");
            if ($cat_ctx && $r = $cat_ctx->fetch_assoc()) {
                $conn->query("UPDATE mdl_context SET path = '{$r['path']}/$context_id' WHERE id = $context_id");
            }
            
            // Configurar formato tiles con color turquesa (#00bcd4)
            $conn->query("INSERT INTO mdl_course_format_options (courseid, format, sectionid, name, value) VALUES 
                ($course_id, 'tiles', 0, 'basecolour', '#00bcd4'),
                ($course_id, 'tiles', 0, 'coursedisplay', '1'),
                ($course_id, 'tiles', 0, 'defaulttileicon', 'pie-chart'),
                ($course_id, 'tiles', 0, 'courseshowtileprogress', '0'),
                ($course_id, 'tiles', 0, 'displayfilterbar', '0'),
                ($course_id, 'tiles', 0, 'hiddensections', '1'),
                ($course_id, 'tiles', 0, 'usesubtilesseczero', '0'),
                ($course_id, 'tiles', 0, 'courseusebarforheadings', '1') ON DUPLICATE KEY UPDATE value=VALUES(value)");
            
            // Crear 5 secciones (0 = general, 1-4 = mosaicos)
            for ($i = 0; $i <= 4; $i++) {
                $conn->query("INSERT INTO mdl_course_sections (course, section, name, summary, summaryformat, visible, timemodified) VALUES ($course_id, $i, NULL, '', 0, 1, $now)");
            }
            
            // Actualizar contador categoría
            $conn->query("UPDATE mdl_course_categories SET coursecount = coursecount + 1 WHERE id = $categoria_id");
            
            header("Location: admin.php?s=ediciones&msg=curso_created&curso=$course_id");
        } else {
            header("Location: admin.php?s=ediciones&msg=error&err=" . urlencode($conn->error));
        }
        exit;
    }
}

// Datos para dashboard
$total_empresas = $conn->query("SELECT COUNT(*) as c FROM own_empresas WHERE activo=1")->fetch_assoc()['c'];
$total_ediciones = $conn->query("SELECT COUNT(*) as c FROM own_empresa_ediciones WHERE activo=1")->fetch_assoc()['c'];
$total_cursos = $conn->query("SELECT COUNT(DISTINCT courseid) as c FROM own_acuity_course WHERE acuityid IS NOT NULL")->fetch_assoc()['c'];

$categorias = $conn->query("SELECT id, name FROM mdl_course_categories WHERE id >= 400 AND visible = 1 ORDER BY name");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión Empresas | tuSpeaking</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f5f5f5;display:flex;min-height:100vh}
.sidebar{width:240px;background:linear-gradient(180deg,#008ba3 0%,#006d80 100%);color:white;padding:20px 0;position:fixed;height:100vh}
.sidebar h1{font-size:22px;padding:0 20px 20px;border-bottom:1px solid rgba(255,255,255,0.1);margin-bottom:10px;font-weight:300}
.sidebar h1 span{font-weight:600}
.sidebar nav a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:rgba(255,255,255,0.8);text-decoration:none;transition:all 0.2s}
.sidebar nav a:hover{background:rgba(255,255,255,0.1);color:white}
.sidebar nav a.active{background:rgba(255,255,255,0.2);color:white;border-left:3px solid white}
.main{margin-left:240px;flex:1;padding:20px 30px}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.header h1{font-size:24px;color:#333;font-weight:400;display:flex;align-items:center;gap:10px}
.card{background:white;border-radius:8px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.1)}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:20px}
.kpi{background:white;border-radius:8px;padding:20px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.1)}
.kpi-value{font-size:36px;font-weight:700;color:#008ba3}
.kpi-label{color:#666;font-size:13px}
table{width:100%;border-collapse:collapse}
th,td{padding:12px;text-align:left;border-bottom:1px solid #eee}
th{background:#f8f9fa;font-weight:600;color:#555;font-size:13px}
.btn{padding:8px 16px;border:none;border-radius:4px;cursor:pointer;font-size:13px;display:inline-flex;align-items:center;gap:5px;text-decoration:none}
.btn-primary{background:#008ba3;color:white}
.btn-sm{padding:5px 10px;font-size:12px}
.btn-danger{background:#e74c3c;color:white}
.btn-success{background:#27ae60;color:white}
.badge{padding:3px 8px;border-radius:12px;font-size:11px}
.badge-success{background:#d4edda;color:#155724}
input,select{padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px}
.form-row{display:flex;gap:15px;margin-bottom:15px}
.form-group{flex:1;min-width:200px}
.form-group label{display:block;margin-bottom:5px;color:#555;font-size:13px}
.msg{padding:10px 15px;border-radius:4px;margin-bottom:15px;background:#d4edda;color:#155724}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
.modal.active{display:flex}
.modal-content{background:white;border-radius:8px;padding:25px;max-width:500px;width:90%}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.close-modal{cursor:pointer;color:#666}
.empresa-card{border:1px solid #e0e0e0;border-radius:8px;margin-bottom:15px}
.empresa-header{background:#f8f9fa;padding:15px;display:flex;justify-content:space-between;align-items:center;cursor:pointer}
.empresa-body{padding:15px;display:none}
.empresa-body.active{display:block}
.edicion-row{display:flex;align-items:center;gap:15px;padding:10px;background:#f8f9fa;border-radius:4px;margin-bottom:8px}
.back-link{color:rgba(255,255,255,0.8);text-decoration:none;display:flex;align-items:center;gap:8px;padding:15px 20px;margin-top:auto;border-top:1px solid rgba(255,255,255,0.1)}
</style>
</head>
<body>
<aside class="sidebar">
    <h1>tu<span>Speaking</span></h1>
    <nav>
        <a href="?s=dashboard" class="<?=$seccion=='dashboard'?'active':''?>"><span class="material-icons">dashboard</span>Dashboard</a>
        <a href="?s=empresas" class="<?=$seccion=='empresas'?'active':''?>"><span class="material-icons">business</span>Empresas</a>
        <a href="?s=ediciones" class="<?=$seccion=='ediciones'?'active':''?>"><span class="material-icons">date_range</span>Ediciones</a>
        <a href="?s=cursos" class="<?=$seccion=='cursos'?'active':''?>"><span class="material-icons">school</span>Cursos</a>
        <a href="?s=alumnos" class="<?=$seccion=='alumnos'?'active':''?>"><span class="material-icons">people</span>Alumnos</a>
        <a href="?s=grupos" class="<?=$seccion=='grupos'?'active':''?>"><span class="material-icons">groups</span>Grupos/FUNDAE</a>
        <a href="/app/moodle/contenido/" target="_blank"><span class="material-icons">inventory_2</span>Gestión Contenido</a>
        <a href="documentacion.php" target="_blank"><span class="material-icons">description</span>Documentación</a>
        <a href="acuity_zoom.php" target="_blank"><span class="material-icons">video_call</span>URLs Zoom</a>
        <a href="fichas_fundae.php" target="_blank"><span class="material-icons">description</span>Fichas FUNDAE</a>
        <a href="prueba_nivel.php" target="_blank"><span class="material-icons">assignment</span>Alta Prueba Nivel</a>
        <a href="prueba_nivel_resultados.php" target="_blank"><span class="material-icons">analytics</span>Resultados Nivel</a>
    </nav>
    <a href="/app/moodle/feedback/admin.php" class="back-link"><span class="material-icons">arrow_back</span>Panel Feedback</a>
    <a href="/app/moodle/reportes/admin.php" class="back-link"><span class="material-icons">assessment</span>Panel Reportes</a>
</aside>

<main class="main">
<?php if(isset($_GET['msg'])): ?>
<div class="msg"><?php 
$msgs = ['added'=>'Añadido correctamente','updated'=>'Actualizado','deleted'=>'Eliminado','categoria_created'=>'Categoría creada','curso_created'=>'Curso #'.$_GET['curso'].' creado','error'=>'Error: '.$_GET['err']];
echo $msgs[$_GET['msg']] ?? 'OK'; ?></div>
<?php endif; ?>

<?php if($seccion == 'dashboard'): ?>
<div class="header"><h1><span class="material-icons">dashboard</span> Dashboard</h1></div>
<div class="kpis">
    <div class="kpi"><div class="kpi-value"><?=$total_empresas?></div><div class="kpi-label">Empresas Activas</div></div>
    <div class="kpi"><div class="kpi-value"><?=$total_ediciones?></div><div class="kpi-label">Ediciones</div></div>
    <div class="kpi"><div class="kpi-value"><?=$total_cursos?></div><div class="kpi-label">Cursos Configurados</div></div>
</div>
<div class="card">
    <h3>Empresas Activas</h3>
    <table>
        <tr><th>Empresa</th><th>Dominio</th><th>Ediciones</th><th>Estado</th></tr>
        <?php $q = $conn->query("SELECT e.*, COUNT(ed.id) as num FROM own_empresas e LEFT JOIN own_empresa_ediciones ed ON ed.empresa_id=e.id WHERE e.activo=1 GROUP BY e.id ORDER BY e.nombre");
        while($r = $q->fetch_assoc()): ?>
        <tr><td><strong><?=htmlspecialchars($r['nombre'])?></strong></td><td><?=$r['dominio']?></td><td><?=$r['num']?></td><td><span class="badge badge-success">Activa</span></td></tr>
        <?php endwhile; ?>
    </table>
</div>

<?php elseif($seccion == 'empresas'): ?>
<div class="header">
    <h1><span class="material-icons">business</span> Empresas</h1>
    <button class="btn btn-primary" onclick="document.getElementById('modal-empresa').classList.add('active')"><span class="material-icons">add</span> Nueva</button>
</div>
<div class="card">
    <table>
        <tr><th>Nombre</th><th>Dominio</th><th>Contacto</th><th>Estado</th><th>Acciones</th></tr>
        <?php $q = $conn->query("SELECT * FROM own_empresas ORDER BY nombre");
        while($r = $q->fetch_assoc()): ?>
        <tr>
            <td><strong><?=htmlspecialchars($r['nombre'])?></strong></td>
            <td><?=$r['dominio']?></td>
            <td><?=$r['contacto_nombre']?></td>
            <td><span class="badge <?=$r['activo']?'badge-success':'badge-danger'?>"><?=$r['activo']?'Activa':'Inactiva'?></span></td>
            <td><button class="btn btn-sm btn-primary" onclick='editEmpresa(<?=json_encode($r)?>)'><span class="material-icons" style="font-size:16px">edit</span></button></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<!-- Modal Nueva Empresa -->
<div id="modal-empresa" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Nueva Empresa</h3><span class="material-icons close-modal" onclick="this.closest('.modal').classList.remove('active')">close</span></div>
        <form method="POST">
            <input type="hidden" name="add_empresa" value="1">
            <div class="form-group"><label>Nombre *</label><input type="text" name="nombre" required style="width:100%"></div>
            <div class="form-group"><label>Dominio *</label><input type="text" name="dominio" placeholder="empresa.com" required style="width:100%"></div>
            <div class="form-group"><label>Contacto</label><input type="text" name="contacto_nombre" style="width:100%"></div>
            <div class="form-group"><label>Email</label><input type="email" name="contacto_email" style="width:100%"></div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px">Guardar</button>
        </form>
    </div>
</div>

<!-- Modal Editar Empresa -->
<div id="modal-edit-empresa" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Editar Empresa</h3><span class="material-icons close-modal" onclick="this.closest('.modal').classList.remove('active')">close</span></div>
        <form method="POST">
            <input type="hidden" name="update_empresa" value="1">
            <input type="hidden" name="id" id="edit-id">
            <div class="form-group"><label>Nombre *</label><input type="text" name="nombre" id="edit-nombre" required style="width:100%"></div>
            <div class="form-group"><label>Dominio *</label><input type="text" name="dominio" id="edit-dominio" required style="width:100%"></div>
            <div class="form-group"><label>Contacto</label><input type="text" name="contacto_nombre" id="edit-contacto" style="width:100%"></div>
            <div class="form-group"><label>Email</label><input type="email" name="contacto_email" id="edit-email" style="width:100%"></div>
            <div class="form-group"><label><input type="checkbox" name="activo" id="edit-activo"> Activa</label></div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px">Actualizar</button>
        </form>
    </div>
</div>

<?php elseif($seccion == 'ediciones'): ?>
<div class="header">
    <h1><span class="material-icons">date_range</span> Ediciones</h1>
    <div style="display:flex;gap:10px">
        <button class="btn btn-primary" onclick="document.getElementById('modal-crear-cat').classList.add('active')"><span class="material-icons">create_new_folder</span> Nueva Categoría</button>
        <button class="btn btn-primary" onclick="document.getElementById('modal-edicion').classList.add('active')"><span class="material-icons">add</span> Asociar Existente</button>
    </div>
</div>

<?php $empresas = $conn->query("SELECT * FROM own_empresas WHERE activo = 1 ORDER BY nombre");
while($emp = $empresas->fetch_assoc()): 
    $ediciones = $conn->query("SELECT ed.*, c.name as categoria_nombre FROM own_empresa_ediciones ed 
                               INNER JOIN mdl_course_categories c ON c.id = ed.categoria_id 
                               WHERE ed.empresa_id = {$emp['id']} ORDER BY c.name");
?>
<div class="empresa-card">
    <div class="empresa-header" onclick="this.nextElementSibling.classList.toggle('active')">
        <div><strong><?=htmlspecialchars($emp['nombre'])?></strong> <span style="color:#666"><?=$ediciones->num_rows?> ediciones</span></div>
        <span class="material-icons">expand_more</span>
    </div>
    <div class="empresa-body">
        <?php while($ed = $ediciones->fetch_assoc()): ?>
        <div class="edicion-row">
            <div style="flex:2"><strong><?=htmlspecialchars($ed['categoria_nombre'])?></strong></div>
            <div style="flex:1">
                <form method="POST" style="display:inline">
                    <input type="hidden" name="update_edicion" value="1">
                    <input type="hidden" name="id" value="<?=$ed['id']?>">
                    <input type="date" name="fecha_inicio" value="<?=$ed['fecha_inicio']?>" style="padding:4px" onchange="this.form.submit()">
                </form>
            </div>
            <div style="flex:1">
                <form method="POST" style="display:inline">
                    <input type="hidden" name="update_edicion" value="1">
                    <input type="hidden" name="id" value="<?=$ed['id']?>">
                    <input type="hidden" name="fecha_inicio" value="<?=$ed['fecha_inicio']?>">
                    <input type="date" name="fecha_fin" value="<?=$ed['fecha_fin']?>" style="padding:4px" onchange="this.form.submit()">
                </form>
            </div>
            <div>
                <button class="btn btn-sm btn-success" onclick="abrirCrearCurso(<?=$ed['categoria_id']?>, '<?=htmlspecialchars($ed['categoria_nombre'])?>')" title="Añadir curso"><span class="material-icons" style="font-size:14px">add</span></button>
                <a href="/app/moodle/courseacuity.php?cat=<?=$ed['categoria_id']?>" class="btn btn-sm btn-primary" target="_blank"><span class="material-icons" style="font-size:14px">settings</span></a>
                <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar?')">
                    <input type="hidden" name="delete_edicion" value="1">
                    <input type="hidden" name="id" value="<?=$ed['id']?>">
                    <button class="btn btn-sm btn-danger"><span class="material-icons" style="font-size:14px">delete</span></button>
                </form>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>
<?php endwhile; ?>

<!-- Modal Asociar Edición -->
<div id="modal-edicion" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Asociar Categoría Existente</h3><span class="material-icons close-modal" onclick="this.closest('.modal').classList.remove('active')">close</span></div>
        <form method="POST">
            <input type="hidden" name="add_edicion" value="1">
            <div class="form-group"><label>Empresa *</label>
                <select name="empresa_id" required style="width:100%">
                    <option value="">Seleccionar...</option>
                    <?php $e = $conn->query("SELECT id, nombre FROM own_empresas WHERE activo=1 ORDER BY nombre"); while($r = $e->fetch_assoc()): ?>
                    <option value="<?=$r['id']?>"><?=htmlspecialchars($r['nombre'])?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group"><label>Categoría Moodle *</label>
                <select name="categoria_id" required style="width:100%">
                    <option value="">Seleccionar...</option>
                    <?php $categorias->data_seek(0); while($c = $categorias->fetch_assoc()): ?>
                    <option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Fecha Inicio</label><input type="date" name="fecha_inicio" style="width:100%"></div>
                <div class="form-group"><label>Fecha Fin</label><input type="date" name="fecha_fin" style="width:100%"></div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Guardar</button>
        </form>
    </div>
</div>

<!-- Modal Crear Categoría -->
<div id="modal-crear-cat" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Nueva Categoría</h3><span class="material-icons close-modal" onclick="this.closest('.modal').classList.remove('active')">close</span></div>
        <form method="POST">
            <input type="hidden" name="crear_categoria" value="1">
            <div class="form-group"><label>Empresa *</label>
                <select name="empresa_id" id="cat-empresa" required style="width:100%" onchange="updateCatPreview()">
                    <option value="">Seleccionar...</option>
                    <?php $e = $conn->query("SELECT id, nombre FROM own_empresas WHERE activo=1 ORDER BY nombre"); while($r = $e->fetch_assoc()): ?>
                    <option value="<?=$r['id']?>" data-nombre="<?=htmlspecialchars($r['nombre'])?>"><?=htmlspecialchars($r['nombre'])?></option>
                    <?php endwhile; ?>
                </select>
                <input type="hidden" name="nombre_empresa" id="cat-nombre-empresa">
            </div>
            <div class="form-row">
                <div class="form-group"><label>Año *</label>
                    <select name="anio" id="cat-anio" required style="width:100%" onchange="updateCatPreview()">
                        <option value="2026">2026</option><option value="2025">2025</option><option value="2027">2027</option>
                    </select>
                </div>
                <div class="form-group"><label>Edición</label><input type="text" name="num_edicion" id="cat-edicion" placeholder="ej: 1, Q1" style="width:100%" oninput="updateCatPreview()"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Fecha Inicio</label><input type="date" name="fecha_inicio" style="width:100%"></div>
                <div class="form-group"><label>Fecha Fin</label><input type="date" name="fecha_fin" style="width:100%"></div>
            </div>
            <p style="background:#f8f9fa;padding:10px;border-radius:4px;font-size:13px"><strong>Vista previa:</strong> <span id="cat-preview">2026 - [Empresa]</span></p>
            <button type="submit" class="btn btn-primary" style="width:100%">Crear Categoría</button>
        </form>
    </div>
</div>

<!-- Modal Crear Curso -->
<div id="modal-crear-curso" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Crear Nuevo Curso</h3><span class="material-icons close-modal" onclick="this.closest('.modal').classList.remove('active')">close</span></div>
        <form method="POST">
            <input type="hidden" name="crear_curso" value="1">
            <input type="hidden" name="categoria_id" id="curso-cat-id">
            <div class="form-group"><label>Categoría</label><input type="text" id="curso-cat-nombre" disabled style="width:100%;background:#f5f5f5"></div>
            <div class="form-row">
                <div class="form-group"><label>Idioma *</label>
                    <select name="idioma" id="curso-idioma" required style="width:100%" onchange="updateCursoPreview()">
                        <option value="">Seleccionar...</option>
                        <option value="Inglés">Inglés</option><option value="Español">Español</option><option value="Francés">Francés</option>
                        <option value="Alemán">Alemán</option><option value="Portugués">Portugués</option><option value="Italiano">Italiano</option>
                    </select>
                </div>
                <div class="form-group"><label>Nivel *</label>
                    <select name="nivel" id="curso-nivel" required style="width:100%" onchange="updateCursoPreview()">
                        <option value="">Seleccionar...</option>
                        <option value="A1">A1</option><option value="A2">A2</option><option value="B1">B1</option>
                        <option value="B1.2">B1.2</option><option value="B2">B2</option><option value="B2.2">B2.2</option>
                        <option value="C1">C1</option><option value="C2">C2</option>
                    </select>
                </div>
            </div>
            <p style="background:#f8f9fa;padding:10px;border-radius:4px;font-size:13px"><strong>Vista previa:</strong> <span id="curso-preview">[Idioma] [Nivel] - #[ID]</span></p>
            <button type="submit" class="btn btn-primary" style="width:100%">Crear Curso</button>
        </form>
    </div>
</div>

<?php elseif($seccion == 'cursos'): ?>
<div class="header"><h1><span class="material-icons">school</span> Cursos</h1></div>
<div class="card">
    <p>Para crear cursos, ve a <strong>Ediciones</strong> y usa el botón [+] en cada categoría.</p>
    <p>Para configurar tipo de clase y Acuity, usa el botón de configuración o ve a <a href="/app/moodle/courseacuity.php" target="_blank">Course-Appointment</a>.</p>
</div>
<?php endif; ?>

</main>

<script>
function editEmpresa(e) {
    document.getElementById('edit-id').value = e.id;
    document.getElementById('edit-nombre').value = e.nombre;
    document.getElementById('edit-dominio').value = e.dominio;
    document.getElementById('edit-contacto').value = e.contacto_nombre || '';
    document.getElementById('edit-email').value = e.contacto_email || '';
    document.getElementById('edit-activo').checked = e.activo == 1;
    document.getElementById('modal-edit-empresa').classList.add('active');
}
function updateCatPreview() {
    var sel = document.getElementById('cat-empresa');
    var nombre = sel.options[sel.selectedIndex]?.dataset.nombre || '[Empresa]';
    document.getElementById('cat-nombre-empresa').value = nombre;
    var anio = document.getElementById('cat-anio').value;
    var ed = document.getElementById('cat-edicion').value;
    var preview = anio + ' - ' + nombre;
    if (ed) preview += ' (' + ed + ')';
    document.getElementById('cat-preview').textContent = preview;
}
function abrirCrearCurso(catId, catNombre) {
    document.getElementById('curso-cat-id').value = catId;
    document.getElementById('curso-cat-nombre').value = catNombre;
    document.getElementById('curso-idioma').value = '';
    document.getElementById('curso-nivel').value = '';
    updateCursoPreview();
    document.getElementById('modal-crear-curso').classList.add('active');
}
function updateCursoPreview() {
    var idioma = document.getElementById('curso-idioma').value || '[Idioma]';
    var nivel = document.getElementById('curso-nivel').value || '[Nivel]';
    document.getElementById('curso-preview').textContent = idioma + ' ' + nivel + ' - #[auto]';
}
</script>
</body>
</html>
