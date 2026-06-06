<?php
$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset("utf8mb4");

$idiomas_disponibles = ['Inglés', 'Francés', 'Portugués', 'Alemán', 'Español', 'Italiano', 'Catalán'];

// ============================================================
// AJAX: Guardar campo FUNDAE (pasaporte_dni o telefono)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_campo_fundae'])) {
    header('Content-Type: application/json');
    $id = intval($_POST['teacher_id'] ?? 0);
    $campo = $_POST['campo'] ?? '';
    $valor = trim($_POST['valor'] ?? '');
    
    $campos_permitidos = ['pasaporte_dni', 'telefono'];
    if (!$id || !in_array($campo, $campos_permitidos)) {
        echo json_encode(['error' => 'Datos inválidos']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE teacher_zoom_map SET $campo = ? WHERE teacher_id = ?");
    $stmt->bind_param("si", $valor, $id);
    $ok = $stmt->execute();
    $stmt->close();
    
    echo json_encode(['ok' => $ok]);
    exit;
}

// ============================================================
// API: Obtener datos de un profesor (para fichas_fundae.php)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['api_profesor'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['api_profesor']);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requerido']);
        exit;
    }
    
    $stmt = $conn->prepare("
        SELECT t.teacher_id as id, 
               CONCAT(u.firstname, ' ', u.lastname) as nombre_completo, 
               t.zoom_email as email,
               COALESCE(t.pasaporte_dni, u.idnumber, '') as pasaporte_dni, 
               COALESCE(t.telefono, '') as telefono
        FROM teacher_zoom_map t 
        JOIN mdl_user u ON t.teacher_id = u.id 
        WHERE t.teacher_id = ? AND t.is_active = 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profesor = $result->fetch_assoc();
    $stmt->close();
    
    if (!$profesor) {
        http_response_code(404);
        echo json_encode(['error' => 'Profesor no encontrado']);
        exit;
    }
    
    echo json_encode($profesor);
    exit;
}

// Procesar cambios de estado (toggle activo/inactivo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle'])) {
    $id = intval($_POST['toggle']);
    
    // Obtener estado actual
    $r = $conn->query("SELECT is_active FROM teacher_zoom_map WHERE teacher_id = $id");
    $current = $r->fetch_assoc();
    $nuevo_estado = $current['is_active'] ? 0 : 1;
    
    // Actualizar teacher_zoom_map
    $conn->query("UPDATE teacher_zoom_map SET is_active = $nuevo_estado WHERE teacher_id = $id");
    
    // Sincronizar con Moodle: suspended es inverso de is_active
    $suspended = $nuevo_estado ? 0 : 1;
    $conn->query("UPDATE mdl_user SET suspended = $suspended WHERE id = $id");
    
    // Log de la acción
    $accion = $nuevo_estado ? 'ACTIVADO' : 'DESACTIVADO';
    error_log("Profesor ID $id $accion - Moodle suspended = $suspended");
    
    header("Location: profesores.php?updated=1&action=" . ($nuevo_estado ? 'activado' : 'desactivado'));
    exit;
}

// Procesar cambios de idiomas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_idiomas'])) {
    $id = intval($_POST['teacher_id']);
    $conn->query("DELETE FROM teacher_idiomas WHERE teacher_id = $id");
    if (!empty($_POST['idiomas'])) {
        foreach ($_POST['idiomas'] as $idioma) {
            $idioma = $conn->real_escape_string($idioma);
            $conn->query("INSERT INTO teacher_idiomas (teacher_id, idioma) VALUES ($id, '$idioma')");
        }
    }
    header("Location: profesores.php?idiomas_updated=1");
    exit;
}

// Obtener profesores (ahora incluye pasaporte_dni y telefono)
$profesores = $conn->query("
    SELECT t.teacher_id, u.firstname, u.lastname, t.zoom_email, t.is_active, t.is_staff,
           u.suspended as moodle_suspended,
           COALESCE(t.pasaporte_dni, '') as pasaporte_dni,
           COALESCE(t.telefono, '') as telefono,
           GROUP_CONCAT(ti.idioma ORDER BY ti.idioma SEPARATOR ', ') as idiomas
    FROM teacher_zoom_map t 
    JOIN mdl_user u ON t.teacher_id = u.id 
    LEFT JOIN teacher_idiomas ti ON t.teacher_id = ti.teacher_id
    GROUP BY t.teacher_id, u.firstname, u.lastname, t.zoom_email, t.is_active, t.is_staff, u.suspended, t.pasaporte_dni, t.telefono
    ORDER BY u.firstname, u.lastname
");

$total = $profesores->num_rows;
$activos = $conn->query("SELECT COUNT(*) as c FROM teacher_zoom_map WHERE is_active = 1")->fetch_assoc()['c'];
$con_idioma = $conn->query("SELECT COUNT(DISTINCT teacher_id) as c FROM teacher_idiomas")->fetch_assoc()['c'];
$staff = $conn->query("SELECT COUNT(*) as c FROM teacher_zoom_map WHERE is_staff = 1")->fetch_assoc()['c'];
$con_pasaporte = $conn->query("SELECT COUNT(*) as c FROM teacher_zoom_map WHERE pasaporte_dni IS NOT NULL AND pasaporte_dni != '' AND is_active = 1")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<link rel="icon" type="image/svg+xml" href="/app/moodle/brand/icons/favicon.svg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión Profesores | tuSpeaking</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f5f5f5;padding:20px}
.container{max-width:1400px;margin:0 auto}
.card{background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.1);overflow:hidden;margin-bottom:20px}
.header{background:linear-gradient(135deg,#008ba3,#00bcd4);padding:20px 30px;color:#fff;display:flex;justify-content:space-between;align-items:center}
.header h1{font-size:22px;font-weight:400}
.back{color:#fff;text-decoration:none;background:rgba(255,255,255,.2);padding:8px 15px;border-radius:6px}
.stats{display:flex;gap:20px;padding:20px;background:#f8f9fa;border-bottom:1px solid #eee;flex-wrap:wrap}
.stat{text-align:center;padding:10px 20px}
.stat h3{font-size:28px;color:#008ba3}
.stat.warning h3{color:#f39c12}
.stat p{color:#666;font-size:13px}
.search{padding:15px 20px;border-bottom:1px solid #eee;display:flex;gap:15px;flex-wrap:wrap;align-items:center}
.search input{flex:1;min-width:200px;padding:12px;border:1px solid #ddd;border-radius:6px;font-size:15px}
.search select{padding:12px;border:1px solid #ddd;border-radius:6px;font-size:14px}
table{width:100%;border-collapse:collapse}
th,td{padding:12px 15px;text-align:left;border-bottom:1px solid #eee}
th{background:#f8f9fa;font-weight:600;color:#333;font-size:13px;white-space:nowrap}
tr:hover{background:#f8f9fa}
tr.staff-row{background:#fff8e1}
.email{color:#666;font-size:12px}
.badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;margin:2px}
.badge.activo{background:#d4edda;color:#155724}
.badge.inactivo{background:#f8d7da;color:#721c24}
.badge.idioma{background:#e3f2fd;color:#1565c0}
.badge.sin-idioma{background:#fff3cd;color:#856404}
.badge.staff{background:#ffc107;color:#000}
.badge.moodle-ok{background:#d4edda;color:#155724}
.badge.moodle-suspended{background:#f8d7da;color:#721c24}
.btn-toggle{padding:6px 12px;border:none;border-radius:4px;cursor:pointer;font-size:11px;margin:2px}
.btn-toggle.activar{background:#28a745;color:#fff}
.btn-toggle.desactivar{background:#dc3545;color:#fff}
.btn-idiomas{background:#17a2b8;color:#fff;padding:6px 12px;border:none;border-radius:4px;cursor:pointer;font-size:11px}
.alert{padding:15px;border-radius:6px;margin:20px;text-align:center}
.alert.success{background:#d4edda;color:#155724}
.alert.warning{background:#fff3cd;color:#856404}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center}
.modal.active{display:flex}
.modal-content{background:#fff;padding:30px;border-radius:10px;max-width:400px;width:90%}
.modal-content h3{margin-bottom:20px;color:#333}
.checkbox-group{margin-bottom:20px}
.checkbox-group label{display:flex;align-items:center;gap:10px;padding:8px 0;cursor:pointer}
.checkbox-group input{width:18px;height:18px}
.modal-buttons{display:flex;gap:10px;justify-content:flex-end}
.modal-buttons button{padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-size:14px}
.btn-cancelar{background:#6c757d;color:#fff}
.btn-guardar{background:#008ba3;color:#fff}
.nombre-col{min-width:180px}
.idiomas-col{min-width:200px}
.info-box{padding:15px 20px;background:#e3f2fd;color:#1565c0;font-size:13px;border-bottom:1px solid #eee}
/* === NUEVOS ESTILOS para campos FUNDAE === */
.campo-fundae{
    width:100%;padding:6px 8px;border:1px solid #e0e0e0;border-radius:4px;font-size:12px;
    font-family:inherit;background:#fafafa;transition:all .3s
}
.campo-fundae:focus{border-color:#008ba3;background:#fff;box-shadow:0 0 0 2px rgba(0,139,163,.15);outline:none}
.campo-fundae.guardado{border-color:#28a745;background:#f0fff0}
.campo-fundae.error{border-color:#dc3545;background:#fff5f5}
.campo-fundae::placeholder{color:#bbb;font-style:italic}
.fundae-col{min-width:130px}
.fundae-status{font-size:10px;color:#888;margin-top:2px;display:block}
.badge.fundae-ok{background:#d4edda;color:#155724;font-size:10px;padding:2px 6px}
.badge.fundae-missing{background:#fff3cd;color:#856404;font-size:10px;padding:2px 6px}
</style>
</head>
<body>
<div class="container">
<div class="card">
<div class="header">
<h1>👨‍🏫 Gestión de Profesores</h1>
<a href="/app/moodle/admin-panel/" class="back">← Volver al Panel</a>
</div>

<?php if(isset($_GET['updated'])): ?>
<div class="alert success">✔ Profesor <?=$_GET['action']=='activado'?'activado (acceso Moodle restaurado)':'desactivado (acceso Moodle suspendido)'?></div>
<?php endif; ?>
<?php if(isset($_GET['idiomas_updated'])): ?>
<div class="alert success">✔ Idiomas del profesor actualizados</div>
<?php endif; ?>
<?php if(isset($_GET['fundae_updated'])): ?>
<div class="alert success">✔ Datos FUNDAE actualizados correctamente</div>
<?php endif; ?>

<div class="info-box">
ℹ️ Al <strong>desactivar</strong> un profesor, su cuenta de Moodle se suspende automáticamente (no puede acceder). 
Al <strong>activar</strong>, se restaura el acceso. Los datos se conservan siempre.
<br>📋 Los campos <strong>Pasaporte/DNI</strong> y <strong>Teléfono</strong> se usan para generar fichas FUNDAE automáticamente. Edítalos haciendo clic en la celda.
</div>

<div class="stats">
<div class="stat"><h3><?=$total?></h3><p>Total profesores</p></div>
<div class="stat"><h3><?=$activos?></h3><p>Activos</p></div>
<div class="stat"><h3><?=$total-$activos?></h3><p>Inactivos</p></div>
<div class="stat <?=$con_idioma<$activos?'warning':''?>"><h3><?=$con_idioma?></h3><p>Con idioma</p></div>
<div class="stat"><h3><?=$staff?></h3><p>Staff</p></div>
<div class="stat <?=$con_pasaporte<$activos?'warning':''?>"><h3><?=$con_pasaporte?>/<?=$activos?></h3><p>Con Pasaporte (FUNDAE)</p></div>
</div>

<div class="search">
<input type="text" id="buscar" placeholder="🔍 Buscar profesor...">
<select id="filtroIdioma" onchange="filtrar()">
<option value="">Todos los idiomas</option>
<?php foreach($idiomas_disponibles as $i): ?><option value="<?=$i?>"><?=$i?></option><?php endforeach; ?>
<option value="sin">⚠️ Sin idioma</option>
</select>
<select id="filtroEstado" onchange="filtrar()">
<option value="">Todos</option>
<option value="activo">Solo activos</option>
<option value="inactivo">Solo inactivos</option>
</select>
</div>

<table id="tabla">
<thead>
<tr>
<th class="nombre-col">Nombre</th>
<th>Email</th>
<th class="fundae-col">Pasaporte/DNI 📋</th>
<th class="fundae-col">Teléfono 📋</th>
<th class="idiomas-col">Idiomas</th>
<th>Estado</th>
<th>Moodle</th>
<th>Acciones</th>
</tr>
</thead>
<tbody>
<?php while($row = $profesores->fetch_assoc()): 
$idiomas_prof = $row['idiomas'] ? explode(', ', $row['idiomas']) : [];
?>
<tr data-idiomas="<?=htmlspecialchars($row['idiomas'])?>" data-estado="<?=$row['is_active']?'activo':'inactivo'?>" class="<?=$row['is_staff']?'staff-row':''?>">
<td class="nombre-col">
<strong><?=htmlspecialchars($row['firstname'].' '.$row['lastname'])?></strong>
<?php if($row['is_staff']): ?><span class="badge staff">STAFF</span><?php endif; ?>
</td>
<td class="email"><?=htmlspecialchars($row['zoom_email'])?></td>
<td class="fundae-col">
<input type="text" class="campo-fundae" 
    data-id="<?=$row['teacher_id']?>" 
    data-campo="pasaporte_dni" 
    value="<?=htmlspecialchars($row['pasaporte_dni'])?>" 
    placeholder="Sin registrar"
    onchange="guardarCampoFundae(this)"
    onblur="guardarCampoFundae(this)">
</td>
<td class="fundae-col">
<input type="text" class="campo-fundae" 
    data-id="<?=$row['teacher_id']?>" 
    data-campo="telefono" 
    value="<?=htmlspecialchars($row['telefono'])?>" 
    placeholder="Sin registrar"
    onchange="guardarCampoFundae(this)"
    onblur="guardarCampoFundae(this)">
</td>
<td class="idiomas-col">
<?php if($row['idiomas']): ?>
<?php foreach($idiomas_prof as $i): ?><span class="badge idioma"><?=htmlspecialchars($i)?></span><?php endforeach; ?>
<?php else: ?>
<span class="badge sin-idioma">⚠️ Sin idioma</span>
<?php endif; ?>
</td>
<td><span class="badge <?=$row['is_active']?'activo':'inactivo'?>"><?=$row['is_active']?'Activo':'Inactivo'?></span></td>
<td><span class="badge <?=$row['moodle_suspended']?'moodle-suspended':'moodle-ok'?>"><?=$row['moodle_suspended']?'Suspendido':'OK'?></span></td>
<td>
<button class="btn-idiomas" onclick="abrirModal(<?=$row['teacher_id']?>, '<?=htmlspecialchars($row['firstname'].' '.$row['lastname'])?>', '<?=htmlspecialchars($row['idiomas'])?>')">🌐 Idiomas</button>
<form method="POST" style="display:inline" onsubmit="return confirm('<?=$row['is_active']?'¿Desactivar profesor? Se suspenderá su acceso a Moodle.':'¿Activar profesor? Se restaurará su acceso a Moodle.'?>')">
<input type="hidden" name="toggle" value="<?=$row['teacher_id']?>">
<button type="submit" class="btn-toggle <?=$row['is_active']?'desactivar':'activar'?>"><?=$row['is_active']?'Desactivar':'Activar'?></button>
</form>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<!-- Modal idiomas -->
<div class="modal" id="modalIdiomas">
<div class="modal-content">
<h3>🌐 Idiomas de <span id="nombreProfesor"></span></h3>
<form method="POST">
<input type="hidden" name="teacher_id" id="teacherId">
<input type="hidden" name="guardar_idiomas" value="1">
<div class="checkbox-group">
<?php foreach($idiomas_disponibles as $i): ?>
<label><input type="checkbox" name="idiomas[]" value="<?=$i?>" class="chk-idioma"> <?=$i?></label>
<?php endforeach; ?>
</div>
<div class="modal-buttons">
<button type="button" class="btn-cancelar" onclick="cerrarModal()">Cancelar</button>
<button type="submit" class="btn-guardar">Guardar</button>
</div>
</form>
</div>
</div>

<script>
document.getElementById('buscar').addEventListener('keyup', filtrar);

function filtrar(){
    var texto = document.getElementById('buscar').value.toLowerCase();
    var idioma = document.getElementById('filtroIdioma').value;
    var estado = document.getElementById('filtroEstado').value;
    var filas = document.querySelectorAll('#tabla tbody tr');
    
    filas.forEach(function(fila){
        var nombre = fila.cells[0].textContent.toLowerCase();
        var email = fila.cells[1].textContent.toLowerCase();
        var idiomasFila = fila.dataset.idiomas || '';
        var estadoFila = fila.dataset.estado;
        
        var matchTexto = nombre.includes(texto) || email.includes(texto);
        var matchIdioma = !idioma || (idioma === 'sin' && !idiomasFila) || idiomasFila.includes(idioma);
        var matchEstado = !estado || estadoFila === estado;
        
        fila.style.display = (matchTexto && matchIdioma && matchEstado) ? '' : 'none';
    });
}

function abrirModal(id, nombre, idiomas){
    document.getElementById('teacherId').value = id;
    document.getElementById('nombreProfesor').textContent = nombre;
    document.querySelectorAll('.chk-idioma').forEach(chk => chk.checked = false);
    if(idiomas){
        idiomas.split(', ').forEach(function(i){
            var chk = document.querySelector('.chk-idioma[value="'+i+'"]');
            if(chk) chk.checked = true;
        });
    }
    document.getElementById('modalIdiomas').classList.add('active');
}

function cerrarModal(){
    document.getElementById('modalIdiomas').classList.remove('active');
}

// ============================================================
// NUEVO: Guardar campos FUNDAE por AJAX
// ============================================================
var guardandoTimeouts = {};

function guardarCampoFundae(input) {
    var id = input.dataset.id;
    var campo = input.dataset.campo;
    var valor = input.value.trim();
    var key = id + '_' + campo;
    
    // Debounce: esperar 500ms después de la última edición
    if (guardandoTimeouts[key]) clearTimeout(guardandoTimeouts[key]);
    
    guardandoTimeouts[key] = setTimeout(function() {
        var formData = new FormData();
        formData.append('guardar_campo_fundae', '1');
        formData.append('teacher_id', id);
        formData.append('campo', campo);
        formData.append('valor', valor);
        
        fetch('profesores.php', {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                input.classList.remove('error');
                input.classList.add('guardado');
                setTimeout(function() { input.classList.remove('guardado'); }, 2000);
            } else {
                input.classList.add('error');
            }
        })
        .catch(function() {
            input.classList.add('error');
        });
    }, 500);
}
</script>
</body>
</html>
