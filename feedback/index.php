<?php
$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset("utf8mb4");
$mensaje = ''; $tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idioma = $conn->real_escape_string($_POST['idioma'] ?? '');
    $profesor = $conn->real_escape_string($_POST['profesor'] ?? '');
    if ($profesor === '__otro__') {
        $profesor = $conn->real_escape_string($_POST['profesor_otro'] ?? '');
    }
    $valoracion = intval($_POST['valoracion'] ?? 0);
    $problema = $conn->real_escape_string($_POST['problema_conexion'] ?? '');
    $feedback = $conn->real_escape_string($_POST['recibio_feedback'] ?? '');
    $comentarios = $conn->real_escape_string($_POST['comentarios'] ?? '');
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    
    if ($idioma && $profesor && $valoracion && $email) {
        if ($conn->query("INSERT INTO own_feedback_nps (submission_date,idioma,profesor,valoracion,problema_conexion,recibio_feedback,comentarios,email) VALUES (NOW(),'$idioma','$profesor',$valoracion,'$problema','$feedback','$comentarios','$email')")) {
            $tipo = 'success';
            if ($valoracion <= 3) @mail("notificaciones@tuspeaking.com", "Alerta NPS: $valoracion/10 - $profesor", "Profesor: $profesor\nIdioma: $idioma\nValoracion: $valoracion\nEmail: $email\nComentarios: $comentarios", "From: noreply@tuspeaking.com");
        } else { $mensaje = 'Error al guardar'; $tipo = 'error'; }
    } else { $mensaje = 'Completa todos los campos obligatorios'; $tipo = 'error'; }
}

// Obtener profesores activos con sus idiomas
$profesores_por_idioma = [];
$r = $conn->query("
    SELECT CONCAT(u.firstname, ' ', u.lastname) as nombre, ti.idioma
    FROM teacher_zoom_map t 
    JOIN mdl_user u ON t.teacher_id = u.id 
    JOIN teacher_idiomas ti ON t.teacher_id = ti.teacher_id
    WHERE t.is_active = 1 
    ORDER BY u.firstname, u.lastname
");
if($r) {
    while($row = $r->fetch_assoc()) {
        $profesores_por_idioma[$row['idioma']][] = $row['nombre'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<link rel="icon" type="image/svg+xml" href="/app/moodle/brand/icons/favicon.svg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Valoración | tuSpeaking</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Segoe UI',Arial,sans-serif;background:#f5f5f5;min-height:100vh;padding:20px}.container{max-width:500px;margin:0 auto}.card{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);overflow:hidden}.header{background:linear-gradient(135deg,#008ba3,#00bcd4);padding:25px;text-align:center;color:#fff}.header h1{font-size:20px;font-weight:400}.logo{font-size:28px;margin-bottom:10px;font-weight:300}.logo span{font-weight:600}.form-content{padding:25px}.form-group{margin-bottom:20px}.form-group label{display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px}.form-group select,.form-group input,.form-group textarea{width:100%;padding:12px;border:1px solid #ddd;border-radius:6px;font-size:15px}.form-group select:focus,.form-group input:focus,.form-group textarea:focus{outline:none;border-color:#00bcd4}.rating-labels{display:flex;justify-content:space-between;font-size:12px;color:#999;margin-bottom:8px}.rating-buttons{display:flex;gap:6px;flex-wrap:wrap;justify-content:center}.rating-btn{width:36px;height:36px;border:2px solid #ddd;border-radius:50%;background:#fff;cursor:pointer;font-weight:600;font-size:13px;transition:all .2s}.rating-btn:hover{border-color:#00bcd4}.rating-btn.selected.low{background:#e74c3c;color:#fff;border-color:#e74c3c}.rating-btn.selected.med{background:#f39c12;color:#fff;border-color:#f39c12}.rating-btn.selected.high{background:#27ae60;color:#fff;border-color:#27ae60}.radio-group{display:flex;gap:20px}.radio-option{display:flex;align-items:center;gap:8px}.btn-submit{width:100%;padding:15px;background:#008ba3;color:#fff;border:none;border-radius:6px;font-size:16px;font-weight:600;cursor:pointer}.btn-submit:hover{background:#00bcd4}.gracias{text-align:center;padding:40px}.gracias h2{color:#27ae60;margin-bottom:15px}.mensaje{padding:15px;border-radius:6px;margin-bottom:20px;text-align:center;background:#f8d7da;color:#721c24}.req{color:#e74c3c}.otro-input{display:none;margin-top:10px}
</style>
</head>
<body>
<div class="container"><div class="card">
<div class="header"><div class="logo">tu<span>Speaking</span></div><h1>¿Qué tal ha ido tu sesión?</h1></div>
<div class="form-content">
<?php if($tipo==='success'): ?>
<div class="gracias"><h2>✓ ¡Gracias!</h2><p>Tu valoración ha sido enviada.</p></div>
<?php else: ?>
<?php if($mensaje): ?><div class="mensaje"><?=$mensaje?></div><?php endif; ?>
<form method="POST" id="formNPS">
<div class="form-group"><label>Idioma <span class="req">*</span></label>
<select name="idioma" id="selectIdioma" required>
<option value="">Selecciona...</option>
<option value="Inglés">Inglés</option>
<option value="Francés">Francés</option>
<option value="Portugués">Portugués</option>
<option value="Alemán">Alemán</option>
<option value="Español">Español</option>
<option value="Italiano">Italiano</option>
<option value="Catalán">Catalán</option>
<option value="Catalán">Catalán</option>
</select></div>

<div class="form-group"><label>Profesor/a <span class="req">*</span></label>
<select name="profesor" id="selectProfesor" required>
<option value="">Primero selecciona un idioma...</option>
</select>
<input type="text" name="profesor_otro" id="inputOtro" class="otro-input" placeholder="Escribe el nombre del profesor/a">
</div>

<div class="form-group"><label>¿Qué tal la clase? <span class="req">*</span></label>
<div class="rating-labels"><span>Fatal</span><span>Fenomenal</span></div>
<div class="rating-buttons"><?php for($i=1;$i<=10;$i++):$c=$i<=3?'low':($i<=6?'med':'high');?><button type="button" class="rating-btn <?=$c?>" data-v="<?=$i?>"><?=$i?></button><?php endfor;?></div>
<input type="hidden" name="valoracion" id="val" required></div>

<div class="form-group"><label>¿Problema con la conexión?</label><div class="radio-group"><label class="radio-option"><input type="radio" name="problema_conexion" value="Sí"> Sí</label><label class="radio-option"><input type="radio" name="problema_conexion" value="No" checked> No</label></div></div>

<div class="form-group"><label>¿Recibiste el feedback de la clase?</label><div class="radio-group"><label class="radio-option"><input type="radio" name="recibio_feedback" value="Sí"> Sí</label><label class="radio-option"><input type="radio" name="recibio_feedback" value="No"> No</label></div></div>

<div class="form-group"><label>Comentarios</label><textarea name="comentarios" rows="3" placeholder="Cuéntanos tu experiencia..."></textarea></div>

<div class="form-group"><label>Tu email <span class="req">*</span></label><input type="email" name="email" required placeholder="tu@email.com"></div>

<button type="submit" class="btn-submit">Enviar valoración</button>
</form>
<?php endif; ?>
</div></div></div>
<script>
var profesoresPorIdioma = <?=json_encode($profesores_por_idioma)?>;

document.getElementById('selectIdioma').addEventListener('change', function(){
    var idioma = this.value;
    var select = document.getElementById('selectProfesor');
    var inputOtro = document.getElementById('inputOtro');
    
    select.innerHTML = '<option value="">Selecciona profesor/a...</option>';
    inputOtro.style.display = 'none';
    inputOtro.required = false;
    
    if(idioma && profesoresPorIdioma[idioma]){
        profesoresPorIdioma[idioma].forEach(function(nombre){
            var opt = document.createElement('option');
            opt.value = nombre;
            opt.textContent = nombre;
            select.appendChild(opt);
        });
    }
    // Siempre añadir opción "Otro"
    var optOtro = document.createElement('option');
    optOtro.value = '__otro__';
    optOtro.textContent = '✏️ Otro (escribir nombre)';
    select.appendChild(optOtro);
});

document.getElementById('selectProfesor').addEventListener('change', function(){
    var inputOtro = document.getElementById('inputOtro');
    if(this.value === '__otro__'){
        inputOtro.style.display = 'block';
        inputOtro.required = true;
    } else {
        inputOtro.style.display = 'none';
        inputOtro.required = false;
        inputOtro.value = '';
    }
});

document.querySelectorAll('.rating-btn').forEach(b=>{
    b.addEventListener('click',function(){
        document.querySelectorAll('.rating-btn').forEach(x=>x.classList.remove('selected'));
        this.classList.add('selected');
        document.getElementById('val').value=this.dataset.v;
    });
});
</script>
</body>
</html>
