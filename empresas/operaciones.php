<?php
/**
 * Ficha Administrativa - Gestión Operativa de Formaciones
 * tuSpeaking - Enero 2026 - v3 (con autocompletar email)
 */
$conn = new mysqli('localhost', 'moodle35', 'TuspeakingFix2025!', 'aulatuspeaking35');
$conn->set_charset("utf8mb4");

// ============================================
// AJAX: Buscar usuario por email
// ============================================
if (isset($_GET['ajax']) && $_GET['ajax'] == 'buscar_email') {
    header('Content-Type: application/json');
    $email = $conn->real_escape_string($_GET['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['found' => false]);
        exit;
    }
    
    // Buscar en mdl_user
    $result = $conn->query("SELECT id, firstname, lastname, email FROM mdl_user WHERE email = '$email' AND deleted = 0 LIMIT 1");
    
    if ($result && $row = $result->fetch_assoc()) {
        echo json_encode([
            'found' => true,
            'moodle_id' => $row['id'],
            'firstname' => $row['firstname'],
            'lastname' => $row['lastname'],
            'email' => $row['email'],
            'source' => 'moodle'
        ]);
    } else {
        // Buscar también en own_operaciones_alumnos (por si está en otra ficha)
        $result2 = $conn->query("SELECT alumno_nombre, alumno_apellidos, alumno_email FROM own_operaciones_alumnos WHERE alumno_email = '$email' LIMIT 1");
        if ($result2 && $row2 = $result2->fetch_assoc()) {
            echo json_encode([
                'found' => true,
                'moodle_id' => null,
                'firstname' => $row2['alumno_nombre'],
                'lastname' => $row2['alumno_apellidos'],
                'email' => $row2['alumno_email'],
                'source' => 'operaciones'
            ]);
        } else {
            echo json_encode(['found' => false]);
        }
    }
    exit;
}

$seccion = $_GET['s'] ?? 'fichas';
$ficha_id = isset($_GET['ficha']) ? (int)$_GET['ficha'] : 0;

// ============================================
// PROCESAR FORMULARIOS POST
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- FICHAS ---
    if (isset($_POST['add_ficha'])) {
        $empresa_id = (int)$_POST['empresa_id'];
        $edicion_id = !empty($_POST['edicion_id']) ? (int)$_POST['edicion_id'] : 'NULL';
        $codigo_edicion = $conn->real_escape_string($_POST['codigo_edicion']);
        $idiomas_array = $_POST['idiomas_check'] ?? ['Inglés'];
        $idiomas = $conn->real_escape_string(implode(', ', $idiomas_array));
        $bonificacion = $conn->real_escape_string($_POST['bonificacion']);
        $modalidad_bonificacion = $conn->real_escape_string($_POST['modalidad_bonificacion'] ?? '');
        $gestion_bonificacion = $conn->real_escape_string($_POST['gestion_bonificacion'] ?? '');
        $modalidad_clases = $conn->real_escape_string($_POST['modalidad_clases']);
        $obligatoriedad_plataforma = $conn->real_escape_string($_POST['obligatoriedad_plataforma']);
        $fecha_inicio = $conn->real_escape_string($_POST['fecha_inicio']);
        $fecha_fin = $conn->real_escape_string($_POST['fecha_fin']);
        $horas_plataforma = (int)$_POST['horas_plataforma'];
        $clases_conversacion = (int)$_POST['clases_conversacion'];
        $duracion_clase_min = (int)$_POST['duracion_clase_min'];
        $duracion_total_curso = !empty($_POST['duracion_total_curso']) ? (int)$_POST['duracion_total_curso'] : 'NULL';
        $precio_licencia = floatval($_POST['precio_licencia']);
        $precio_hora = floatval($_POST['precio_hora']);
        $aplica_a_todos = isset($_POST['aplica_a_todos']) ? 1 : 0;
        
        $conn->query("INSERT INTO own_operaciones_fichas 
            (empresa_id, edicion_id, codigo_edicion, idiomas, bonificacion, modalidad_bonificacion, gestion_bonificacion,
             modalidad_clases, obligatoriedad_plataforma, fecha_inicio, fecha_fin, 
             horas_plataforma, clases_conversacion, duracion_clase_min, duracion_total_curso, precio_licencia, precio_hora, aplica_a_todos) 
            VALUES ($empresa_id, $edicion_id, '$codigo_edicion', '$idiomas', '$bonificacion', 
                    NULLIF('$modalidad_bonificacion',''), NULLIF('$gestion_bonificacion',''),
                    '$modalidad_clases', '$obligatoriedad_plataforma',
                    '$fecha_inicio', '$fecha_fin', $horas_plataforma, $clases_conversacion, 
                    $duracion_clase_min, $duracion_total_curso, $precio_licencia, $precio_hora, $aplica_a_todos)");
        header("Location: operaciones.php?s=fichas&msg=ficha_added");
        exit;
    }
    
    if (isset($_POST['update_ficha'])) {
        $id = (int)$_POST['id'];
        $edicion_id = !empty($_POST['edicion_id']) ? (int)$_POST['edicion_id'] : 'NULL';
        $idiomas = $conn->real_escape_string($_POST['idiomas']);
        $bonificacion = $conn->real_escape_string($_POST['bonificacion']);
        $modalidad_bonificacion = $conn->real_escape_string($_POST['modalidad_bonificacion'] ?? '');
        $gestion_bonificacion = $conn->real_escape_string($_POST['gestion_bonificacion'] ?? '');
        $modalidad_clases = $conn->real_escape_string($_POST['modalidad_clases']);
        $obligatoriedad_plataforma = $conn->real_escape_string($_POST['obligatoriedad_plataforma']);
        $fecha_inicio = $conn->real_escape_string($_POST['fecha_inicio']);
        $fecha_fin = $conn->real_escape_string($_POST['fecha_fin']);
        $horas_plataforma = (int)$_POST['horas_plataforma'];
        $clases_conversacion = (int)$_POST['clases_conversacion'];
        $duracion_clase_min = (int)$_POST['duracion_clase_min'];
        $duracion_total_curso = !empty($_POST['duracion_total_curso']) ? (int)$_POST['duracion_total_curso'] : 'NULL';
        $precio_licencia = floatval($_POST['precio_licencia']);
        $precio_hora = floatval($_POST['precio_hora']);
        $aplica_a_todos = isset($_POST['aplica_a_todos']) ? 1 : 0;
        $estado = $conn->real_escape_string($_POST['estado']);
        
        $conn->query("UPDATE own_operaciones_fichas SET 
            edicion_id=$edicion_id, idiomas='$idiomas', bonificacion='$bonificacion', 
            modalidad_bonificacion=NULLIF('$modalidad_bonificacion',''),
            gestion_bonificacion=NULLIF('$gestion_bonificacion',''),
            modalidad_clases='$modalidad_clases', obligatoriedad_plataforma='$obligatoriedad_plataforma',
            fecha_inicio='$fecha_inicio', fecha_fin='$fecha_fin', horas_plataforma=$horas_plataforma,
            clases_conversacion=$clases_conversacion, duracion_clase_min=$duracion_clase_min,
            duracion_total_curso=$duracion_total_curso,
            precio_licencia=$precio_licencia, precio_hora=$precio_hora, 
            aplica_a_todos=$aplica_a_todos, estado='$estado'
            WHERE id=$id");
        header("Location: operaciones.php?s=fichas&msg=ficha_updated");
        exit;
    }
    
    // --- VINCULAR EDICIÓN RÁPIDO ---
    if (isset($_POST['vincular_edicion'])) {
        $ficha_id = (int)$_POST['ficha_id'];
        $edicion_id = (int)$_POST['edicion_id'];
        $conn->query("UPDATE own_operaciones_fichas SET edicion_id = $edicion_id WHERE id = $ficha_id");
        header("Location: operaciones.php?s=grupos&ficha=$ficha_id&msg=edicion_vinculada");
        exit;
    }
    
    // --- GRUPOS ---
    if (isset($_POST['add_grupo'])) {
        $ficha_id = (int)$_POST['ficha_id'];
        $nombre = $conn->real_escape_string($_POST['nombre']);
        $nivel = $conn->real_escape_string($_POST['nivel']);
        $codigo_fundae = $conn->real_escape_string($_POST['codigo_fundae'] ?? '');
        $profesor_nombre = $conn->real_escape_string($_POST['profesor_nombre'] ?? '');
        $dia_semana = $conn->real_escape_string($_POST['dia_semana'] ?? '');
        $hora_inicio = $conn->real_escape_string($_POST['hora_inicio'] ?? '');
        $hora_fin = $conn->real_escape_string($_POST['hora_fin'] ?? '');
        $max_alumnos = (int)($_POST['max_alumnos'] ?? 8);
        $fecha_inicio_clases = $conn->real_escape_string($_POST['fecha_inicio_clases'] ?? '');
        $fecha_fin_clases = $conn->real_escape_string($_POST['fecha_fin_clases'] ?? '');
        $url_reunion = $conn->real_escape_string($_POST['url_reunion'] ?? '');
        $es_1to1 = isset($_POST['es_1to1']) ? 1 : 0;
        $curso_moodle_id = !empty($_POST['curso_moodle_id']) ? (int)$_POST['curso_moodle_id'] : 'NULL';
        
        $conn->query("INSERT INTO own_operaciones_grupos 
            (ficha_id, nombre, nivel, codigo_fundae, profesor_nombre, dia_semana, hora_inicio, hora_fin, 
             max_alumnos, fecha_inicio_clases, fecha_fin_clases, url_reunion, es_1to1, curso_moodle_id)
            VALUES ($ficha_id, '$nombre', '$nivel', NULLIF('$codigo_fundae',''), '$profesor_nombre', 
                    NULLIF('$dia_semana',''), NULLIF('$hora_inicio',''), NULLIF('$hora_fin',''),
                    $max_alumnos, NULLIF('$fecha_inicio_clases',''), NULLIF('$fecha_fin_clases',''),
                    NULLIF('$url_reunion',''), $es_1to1, $curso_moodle_id)");
        header("Location: operaciones.php?s=grupos&ficha=$ficha_id&msg=grupo_added");
        exit;
    }
    
    if (isset($_POST['update_grupo'])) {
        $id = (int)$_POST['id'];
        $ficha_id = (int)$_POST['ficha_id'];
        $nombre = $conn->real_escape_string($_POST['nombre']);
        $nivel = $conn->real_escape_string($_POST['nivel']);
        $codigo_fundae = $conn->real_escape_string($_POST['codigo_fundae'] ?? '');
        $profesor_nombre = $conn->real_escape_string($_POST['profesor_nombre'] ?? '');
        $dia_semana = $conn->real_escape_string($_POST['dia_semana'] ?? '');
        $hora_inicio = $conn->real_escape_string($_POST['hora_inicio'] ?? '');
        $hora_fin = $conn->real_escape_string($_POST['hora_fin'] ?? '');
        $max_alumnos = (int)($_POST['max_alumnos'] ?? 8);
        $fecha_inicio_clases = $conn->real_escape_string($_POST['fecha_inicio_clases'] ?? '');
        $fecha_fin_clases = $conn->real_escape_string($_POST['fecha_fin_clases'] ?? '');
        $url_reunion = $conn->real_escape_string($_POST['url_reunion'] ?? '');
        $estado = $conn->real_escape_string($_POST['estado'] ?? 'activo');
        $curso_moodle_id = !empty($_POST['curso_moodle_id']) ? (int)$_POST['curso_moodle_id'] : 'NULL';
        
        $conn->query("UPDATE own_operaciones_grupos SET 
            nombre='$nombre', nivel='$nivel', codigo_fundae=NULLIF('$codigo_fundae',''),
            profesor_nombre='$profesor_nombre', dia_semana=NULLIF('$dia_semana',''),
            hora_inicio=NULLIF('$hora_inicio',''), hora_fin=NULLIF('$hora_fin',''),
            max_alumnos=$max_alumnos, fecha_inicio_clases=NULLIF('$fecha_inicio_clases',''),
            fecha_fin_clases=NULLIF('$fecha_fin_clases',''), url_reunion=NULLIF('$url_reunion',''),
            estado='$estado', curso_moodle_id=$curso_moodle_id WHERE id=$id");
        header("Location: operaciones.php?s=grupos&ficha=$ficha_id&msg=grupo_updated");
        exit;
    }
    
    if (isset($_POST['delete_grupo'])) {
        $id = (int)$_POST['id'];
        $ficha_id = (int)$_POST['ficha_id'];
        $conn->query("DELETE FROM own_operaciones_grupos WHERE id=$id");
        header("Location: operaciones.php?s=grupos&ficha=$ficha_id&msg=grupo_deleted");
        exit;
    }
    
    // --- ALUMNOS ---
    if (isset($_POST['add_alumno'])) {
        $ficha_id = (int)$_POST['ficha_id'];
        $grupo_id = !empty($_POST['grupo_id']) ? (int)$_POST['grupo_id'] : 'NULL';
        $moodle_userid = !empty($_POST['moodle_userid']) ? (int)$_POST['moodle_userid'] : 'NULL';
        $alumno_email = $conn->real_escape_string($_POST['alumno_email']);
        $alumno_nombre = $conn->real_escape_string($_POST['alumno_nombre'] ?? '');
        $alumno_apellidos = $conn->real_escape_string($_POST['alumno_apellidos'] ?? '');
        $idioma = $conn->real_escape_string($_POST['idioma'] ?? 'Inglés');
        $nivel_asignado = $conn->real_escape_string($_POST['nivel_asignado'] ?? '');
        
        // Obtener valores de la ficha para heredar
        $ficha_data = $conn->query("SELECT fecha_inicio, fecha_fin, clases_conversacion, duracion_clase_min, horas_plataforma, duracion_total_curso FROM own_operaciones_fichas WHERE id=$ficha_id")->fetch_assoc();
        
        $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : $ficha_data['fecha_inicio'];
        $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : $ficha_data['fecha_fin'];
        $clases_conversacion = $ficha_data['clases_conversacion'] ?: 'NULL';
        $duracion_sesion = $ficha_data['duracion_clase_min'] ?: 'NULL';
        $horas_plataforma = $ficha_data['horas_plataforma'] ?: 'NULL';
        $duracion_total_curso = $ficha_data['duracion_total_curso'] ?: 'NULL';
        
        // Si se asigna grupo, registrar fecha de matrícula
        $fecha_matricula = ($grupo_id != 'NULL') ? "CURDATE()" : "NULL";
        
        $conn->query("INSERT INTO own_operaciones_alumnos 
            (ficha_id, grupo_id, moodle_userid, alumno_email, alumno_nombre, alumno_apellidos, idioma, nivel_asignado, 
             fecha_inicio, fecha_fin, clases_conversacion, duracion_sesion, horas_plataforma, duracion_total_curso, fecha_matricula)
            VALUES ($ficha_id, $grupo_id, $moodle_userid, '$alumno_email', '$alumno_nombre', '$alumno_apellidos', 
                    '$idioma', NULLIF('$nivel_asignado',''), '$fecha_inicio', '$fecha_fin',
                    $clases_conversacion, $duracion_sesion, $horas_plataforma, $duracion_total_curso, $fecha_matricula)");
        header("Location: operaciones.php?s=alumnos&ficha=$ficha_id&msg=alumno_added");
        exit;
    }
    
    if (isset($_POST['update_alumno'])) {
        $id = (int)$_POST['id'];
        $ficha_id = (int)$_POST['ficha_id'];
        $grupo_id_nuevo = !empty($_POST['grupo_id']) ? (int)$_POST['grupo_id'] : 'NULL';
        $moodle_userid = !empty($_POST['moodle_userid']) ? (int)$_POST['moodle_userid'] : 'NULL';
        $alumno_nombre = $conn->real_escape_string($_POST['alumno_nombre'] ?? '');
        $alumno_apellidos = $conn->real_escape_string($_POST['alumno_apellidos'] ?? '');
        $idioma = $conn->real_escape_string($_POST['idioma'] ?? 'Inglés');
        $nivel_asignado = $conn->real_escape_string($_POST['nivel_asignado'] ?? '');
        $fecha_inicio = $conn->real_escape_string($_POST['fecha_inicio'] ?? '');
        $fecha_fin = $conn->real_escape_string($_POST['fecha_fin'] ?? '');
        $estado = $conn->real_escape_string($_POST['estado']);
        $envio_bienvenida = $conn->real_escape_string($_POST['envio_bienvenida']);
        
        // Nuevos campos de seguimiento
        $alta_plataforma = $conn->real_escape_string($_POST['alta_plataforma'] ?? 'pendiente');
        $curso_montado = $conn->real_escape_string($_POST['curso_montado'] ?? 'no');
        $nivel_registrado = $conn->real_escape_string($_POST['nivel_registrado'] ?? 'no');
        $prueba_nivel_enviada = $conn->real_escape_string($_POST['prueba_nivel_enviada'] ?? 'no');
        $prueba_nivel_resultado = $conn->real_escape_string($_POST['prueba_nivel_resultado'] ?? '');
        $notif_inicio_enviada = $conn->real_escape_string($_POST['notif_inicio_enviada'] ?? 'no');
        
        // Campos de formación
        $clases_conversacion = !empty($_POST['clases_conversacion']) ? (int)$_POST['clases_conversacion'] : 'NULL';
        $duracion_sesion = !empty($_POST['duracion_sesion']) ? (int)$_POST['duracion_sesion'] : 'NULL';
        $horas_plataforma = !empty($_POST['horas_plataforma']) ? (float)$_POST['horas_plataforma'] : 'NULL';
        $duracion_total_curso = !empty($_POST['duracion_total_curso']) ? (int)$_POST['duracion_total_curso'] : 'NULL';
        
        // Temas (convertir arrays a string separado por comas)
        $temas_completados = isset($_POST['temas_completados']) ? implode(',', $_POST['temas_completados']) : '';
        $temas_asignados = isset($_POST['temas_asignados']) ? implode(',', $_POST['temas_asignados']) : '';
        $temas_observaciones = $conn->real_escape_string($_POST['temas_observaciones'] ?? '');
        
        // Notas generales
        $notas = $conn->real_escape_string($_POST['notas'] ?? '');
        
        // Si el estado es 'baja', establecer fecha_baja automáticamente
        $fecha_baja_sql = "";
        if ($estado == 'baja') {
            $alumno_actual = $conn->query("SELECT fecha_baja, grupo_id FROM own_operaciones_alumnos WHERE id=$id")->fetch_assoc();
            if (empty($alumno_actual['fecha_baja'])) {
                $fecha_baja_sql = ", fecha_baja=CURDATE()";
            }
        }
        
        // Si se asigna grupo por primera vez, registrar fecha de matrícula
        $fecha_matricula_sql = "";
        if ($grupo_id_nuevo != 'NULL') {
            $alumno_actual = $conn->query("SELECT fecha_matricula FROM own_operaciones_alumnos WHERE id=$id")->fetch_assoc();
            if (empty($alumno_actual['fecha_matricula'])) {
                $fecha_matricula_sql = ", fecha_matricula=CURDATE()";
            }
        }
        
        $conn->query("UPDATE own_operaciones_alumnos SET 
            alumno_nombre='$alumno_nombre', alumno_apellidos='$alumno_apellidos', idioma='$idioma',
            moodle_userid=$moodle_userid, grupo_id=$grupo_id_nuevo, nivel_asignado=NULLIF('$nivel_asignado',''), 
            fecha_inicio=NULLIF('$fecha_inicio',''), fecha_fin=NULLIF('$fecha_fin',''),
            estado='$estado', envio_bienvenida='$envio_bienvenida',
            alta_plataforma='$alta_plataforma', curso_montado='$curso_montado', nivel_registrado='$nivel_registrado',
            prueba_nivel_enviada='$prueba_nivel_enviada', prueba_nivel_resultado=NULLIF('$prueba_nivel_resultado',''),
            notif_inicio_enviada='$notif_inicio_enviada',
            clases_conversacion=$clases_conversacion, duracion_sesion=$duracion_sesion,
            horas_plataforma=$horas_plataforma, duracion_total_curso=$duracion_total_curso,
            temas_completados=NULLIF('$temas_completados',''), temas_asignados=NULLIF('$temas_asignados',''),
            temas_observaciones=NULLIF('$temas_observaciones',''), notas=NULLIF('$notas','')
            $fecha_baja_sql $fecha_matricula_sql WHERE id=$id");
        header("Location: operaciones.php?s=alumnos&ficha=$ficha_id&msg=alumno_updated");
        exit;
    }
    
    if (isset($_POST['delete_alumno'])) {
        $id = (int)$_POST['id'];
        $ficha_id = (int)$_POST['ficha_id'];
        $conn->query("DELETE FROM own_operaciones_alumnos WHERE id=$id");
        header("Location: operaciones.php?s=alumnos&ficha=$ficha_id&msg=alumno_deleted");
        exit;
    }
    
    // --- ACCIONES MASIVAS ---
    if (isset($_POST['accion_masiva']) && !empty($_POST['alumnos_ids'])) {
        $ficha_id = (int)$_POST['ficha_id'];
        $ids = array_map('intval', $_POST['alumnos_ids']);
        $ids_str = implode(',', $ids);
        $accion = $_POST['accion_masiva'];
        
        // Si es asignar a grupo
        if (strpos($accion, 'asignar_grupo_') === 0) {
            $grupo_id = (int)str_replace('asignar_grupo_', '', $accion);
            
            // Obtener curso_moodle_id del grupo
            $grupo = $conn->query("SELECT curso_moodle_id FROM own_operaciones_grupos WHERE id = $grupo_id")->fetch_assoc();
            $curso_moodle_id = $grupo ? $grupo['curso_moodle_id'] : null;
            
            // Actualizar alumnos
            $conn->query("UPDATE own_operaciones_alumnos SET grupo_id = $grupo_id, fecha_matricula = IFNULL(fecha_matricula, CURDATE()) WHERE id IN ($ids_str)");
            
            // Si el grupo tiene curso Moodle vinculado, matricular automáticamente
            if ($curso_moodle_id) {
                // Obtener enrol id del método manual para este curso
                $enrol = $conn->query("SELECT id FROM mdl_enrol WHERE courseid = $curso_moodle_id AND enrol = 'manual' LIMIT 1")->fetch_assoc();
                
                if (!$enrol) {
                    // Crear método de inscripción manual si no existe
                    $conn->query("INSERT INTO mdl_enrol (enrol, status, courseid, sortorder, timecreated, timemodified) 
                                  VALUES ('manual', 0, $curso_moodle_id, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())");
                    $enrol_id = $conn->insert_id;
                } else {
                    $enrol_id = $enrol['id'];
                }
                
                // Obtener contexto del curso para asignar rol
                $ctx = $conn->query("SELECT id FROM mdl_context WHERE contextlevel = 50 AND instanceid = $curso_moodle_id")->fetch_assoc();
                $context_id = $ctx ? $ctx['id'] : null;
                
                // Matricular cada alumno que tenga moodle_userid
                $alumnos = $conn->query("SELECT id, moodle_userid FROM own_operaciones_alumnos WHERE id IN ($ids_str) AND moodle_userid IS NOT NULL");
                $now = time();
                $matriculados = 0;
                
                while ($a = $alumnos->fetch_assoc()) {
                    // Verificar si ya está matriculado
                    $ya_matriculado = $conn->query("SELECT id FROM mdl_user_enrolments WHERE enrolid = $enrol_id AND userid = {$a['moodle_userid']}")->num_rows;
                    
                    if (!$ya_matriculado) {
                        // Crear inscripción
                        $conn->query("INSERT INTO mdl_user_enrolments (status, enrolid, userid, timestart, timeend, timecreated, timemodified) 
                                      VALUES (0, $enrol_id, {$a['moodle_userid']}, $now, 0, $now, $now)");
                        
                        // Asignar rol de estudiante (roleid=5)
                        if ($context_id) {
                            $ya_tiene_rol = $conn->query("SELECT id FROM mdl_role_assignments WHERE roleid = 5 AND contextid = $context_id AND userid = {$a['moodle_userid']}")->num_rows;
                            if (!$ya_tiene_rol) {
                                $conn->query("INSERT INTO mdl_role_assignments (roleid, contextid, userid, timemodified, modifierid) 
                                              VALUES (5, $context_id, {$a['moodle_userid']}, $now, 2)");
                            }
                        }
                        $matriculados++;
                    }
                }
            }
            
            header("Location: operaciones.php?s=alumnos&ficha=$ficha_id&msg=asignados_grupo");
            exit;
        }
        
        $accion = $conn->real_escape_string($accion);
        switch($accion) {
            case 'alta_completada':
                $conn->query("UPDATE own_operaciones_alumnos SET alta_plataforma='completada' WHERE id IN ($ids_str)");
                break;
            case 'curso_montado':
                $conn->query("UPDATE own_operaciones_alumnos SET curso_montado='si' WHERE id IN ($ids_str)");
                break;
            case 'nivel_registrado':
                $conn->query("UPDATE own_operaciones_alumnos SET nivel_registrado='si' WHERE id IN ($ids_str)");
                break;
            case 'prueba_enviada':
                $conn->query("UPDATE own_operaciones_alumnos SET prueba_nivel_enviada='si' WHERE id IN ($ids_str)");
                break;
            case 'bienvenida_enviada':
                $conn->query("UPDATE own_operaciones_alumnos SET envio_bienvenida='enviado' WHERE id IN ($ids_str)");
                break;
            case 'notif_inicio':
                $conn->query("UPDATE own_operaciones_alumnos SET notif_inicio_enviada='si' WHERE id IN ($ids_str)");
                break;
            case 'estado_activo':
                $conn->query("UPDATE own_operaciones_alumnos SET estado='activo' WHERE id IN ($ids_str)");
                break;
        }
        header("Location: operaciones.php?s=alumnos&ficha=$ficha_id&msg=accion_masiva");
        exit;
    }
    
    if (isset($_POST['asignar_grupo'])) {
        $alumno_id = (int)$_POST['alumno_id'];
        $grupo_id = !empty($_POST['grupo_id']) ? (int)$_POST['grupo_id'] : 'NULL';
        $ficha_id = (int)$_POST['ficha_id'];
        
        // Si se asigna grupo, registrar fecha de matrícula si no existe
        $fecha_sql = "";
        if ($grupo_id != 'NULL') {
            $alumno = $conn->query("SELECT fecha_matricula, moodle_userid FROM own_operaciones_alumnos WHERE id=$alumno_id")->fetch_assoc();
            if (empty($alumno['fecha_matricula'])) {
                $fecha_sql = ", fecha_matricula=CURDATE()";
            }
            
            // Matricular en Moodle si el grupo tiene curso vinculado
            $grupo = $conn->query("SELECT curso_moodle_id FROM own_operaciones_grupos WHERE id = $grupo_id")->fetch_assoc();
            if ($grupo && $grupo['curso_moodle_id'] && $alumno['moodle_userid']) {
                $curso_moodle_id = $grupo['curso_moodle_id'];
                $enrol = $conn->query("SELECT id FROM mdl_enrol WHERE courseid = $curso_moodle_id AND enrol = 'manual' LIMIT 1")->fetch_assoc();
                
                if (!$enrol) {
                    $conn->query("INSERT INTO mdl_enrol (enrol, status, courseid, sortorder, timecreated, timemodified) 
                                  VALUES ('manual', 0, $curso_moodle_id, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())");
                    $enrol_id = $conn->insert_id;
                } else {
                    $enrol_id = $enrol['id'];
                }
                
                $ya_matriculado = $conn->query("SELECT id FROM mdl_user_enrolments WHERE enrolid = $enrol_id AND userid = {$alumno['moodle_userid']}")->num_rows;
                if (!$ya_matriculado) {
                    $now = time();
                    // Crear inscripción
                    $conn->query("INSERT INTO mdl_user_enrolments (status, enrolid, userid, timestart, timeend, timecreated, timemodified) 
                                  VALUES (0, $enrol_id, {$alumno['moodle_userid']}, $now, 0, $now, $now)");
                    
                    // Asignar rol de estudiante (roleid=5)
                    $ctx = $conn->query("SELECT id FROM mdl_context WHERE contextlevel = 50 AND instanceid = $curso_moodle_id")->fetch_assoc();
                    if ($ctx) {
                        $ya_tiene_rol = $conn->query("SELECT id FROM mdl_role_assignments WHERE roleid = 5 AND contextid = {$ctx['id']} AND userid = {$alumno['moodle_userid']}")->num_rows;
                        if (!$ya_tiene_rol) {
                            $conn->query("INSERT INTO mdl_role_assignments (roleid, contextid, userid, timemodified, modifierid) 
                                          VALUES (5, {$ctx['id']}, {$alumno['moodle_userid']}, $now, 2)");
                        }
                    }
                }
            }
        }
        
        $conn->query("UPDATE own_operaciones_alumnos SET grupo_id=$grupo_id $fecha_sql WHERE id=$alumno_id");
        header("Location: operaciones.php?s=alumnos&ficha=$ficha_id&msg=asignado");
        exit;
    }
    
    if (isset($_POST['cambiar_idioma'])) {
        $alumno_id = (int)$_POST['alumno_id'];
        $idioma = $conn->real_escape_string($_POST['idioma']);
        $ficha_id = (int)$_POST['ficha_id'];
        $conn->query("UPDATE own_operaciones_alumnos SET idioma='$idioma' WHERE id=$alumno_id");
        header("Location: operaciones.php?s=alumnos&ficha=$ficha_id&msg=idioma_actualizado");
        exit;
    }
    
    // --- FUNDAE ---
    if (isset($_POST['save_fundae'])) {
        $ficha_id = (int)$_POST['ficha_id'];
        $cif_empresa = $conn->real_escape_string($_POST['cif_empresa']);
        $razon_social = $conn->real_escape_string($_POST['razon_social'] ?? '');
        $numero_accion = $conn->real_escape_string($_POST['numero_accion'] ?? '');
        $denominacion_accion = $conn->real_escape_string($_POST['denominacion_accion']);
        $modalidad_fundae = $conn->real_escape_string($_POST['modalidad_fundae'] ?? 'teleformacion');
        $horas_totales = (int)$_POST['horas_totales'];
        $participantes_inicio = (int)$_POST['participantes_inicio'];
        
        $check_comunicacion_rlt = isset($_POST['check_comunicacion_rlt']) ? 1 : 0;
        $check_listado_participantes = isset($_POST['check_listado_participantes']) ? 1 : 0;
        $check_coste_hora = isset($_POST['check_coste_hora']) ? 1 : 0;
        $check_credito_verificado = isset($_POST['check_credito_verificado']) ? 1 : 0;
        
        $conn->query("INSERT INTO own_operaciones_fundae 
            (ficha_id, cif_empresa, razon_social, numero_accion, denominacion_accion, modalidad_fundae,
             horas_totales, participantes_inicio, check_comunicacion_rlt, check_listado_participantes,
             check_coste_hora, check_credito_verificado)
            VALUES ($ficha_id, '$cif_empresa', '$razon_social', NULLIF('$numero_accion',''), 
                    '$denominacion_accion', '$modalidad_fundae', $horas_totales, $participantes_inicio,
                    $check_comunicacion_rlt, $check_listado_participantes, $check_coste_hora, $check_credito_verificado)
            ON DUPLICATE KEY UPDATE 
                cif_empresa='$cif_empresa', razon_social='$razon_social', numero_accion=NULLIF('$numero_accion',''),
                denominacion_accion='$denominacion_accion', modalidad_fundae='$modalidad_fundae',
                horas_totales=$horas_totales, participantes_inicio=$participantes_inicio,
                check_comunicacion_rlt=$check_comunicacion_rlt, check_listado_participantes=$check_listado_participantes,
                check_coste_hora=$check_coste_hora, check_credito_verificado=$check_credito_verificado");
        header("Location: operaciones.php?s=fundae&ficha=$ficha_id&msg=fundae_saved");
        exit;
    }
    
    if (isset($_POST['completar_alta_fundae'])) {
        $ficha_id = (int)$_POST['ficha_id'];
        $conn->query("UPDATE own_operaciones_fundae SET estado_alta='completada', fecha_alta=CURDATE() WHERE ficha_id=$ficha_id");
        header("Location: operaciones.php?s=fundae&ficha=$ficha_id&msg=alta_completada");
        exit;
    }
}

// ============================================
// DATOS GENERALES
// ============================================
$empresas = $conn->query("SELECT id, nombre FROM own_empresas WHERE activo=1 ORDER BY nombre");

// Cargar ediciones para el selector (agrupadas por empresa)
$ediciones_por_empresa = [];
$ediciones_result = $conn->query("SELECT ed.id, ed.empresa_id, c.name as edicion_nombre, ed.categoria_id, 
    (SELECT COUNT(*) FROM mdl_course WHERE category = ed.categoria_id) as num_cursos
    FROM own_empresa_ediciones ed 
    JOIN mdl_course_categories c ON c.id = ed.categoria_id
    ORDER BY ed.empresa_id, c.name DESC");
if ($ediciones_result && $ediciones_result->num_rows > 0) {
    while ($ed = $ediciones_result->fetch_assoc()) {
        if (!isset($ediciones_por_empresa[$ed['empresa_id']])) {
            $ediciones_por_empresa[$ed['empresa_id']] = [];
        }
        $ediciones_por_empresa[$ed['empresa_id']][] = $ed;
    }
}

$total_fichas = $conn->query("SELECT COUNT(*) as c FROM own_operaciones_fichas")->fetch_assoc()['c'];
$fichas_activas = $conn->query("SELECT COUNT(*) as c FROM own_operaciones_fichas WHERE estado='en_curso'")->fetch_assoc()['c'];
$total_alumnos = $conn->query("SELECT COUNT(*) as c FROM own_operaciones_alumnos")->fetch_assoc()['c'];
$total_grupos = $conn->query("SELECT COUNT(*) as c FROM own_operaciones_grupos")->fetch_assoc()['c'];

$ficha = null;
if ($ficha_id > 0) {
    $ficha = $conn->query("SELECT f.*, e.nombre as empresa_nombre FROM own_operaciones_fichas f 
                           INNER JOIN own_empresas e ON e.id = f.empresa_id WHERE f.id = $ficha_id")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ficha Administrativa | tuSpeaking</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f5f5f5;display:flex;min-height:100vh}
.sidebar{width:260px;background:linear-gradient(180deg,#008ba3 0%,#006d80 100%);color:white;padding:20px 0;position:fixed;height:100vh;overflow-y:auto}
.sidebar h1{font-size:20px;padding:0 20px 15px;border-bottom:1px solid rgba(255,255,255,0.1);margin-bottom:10px;font-weight:300}
.sidebar h1 span{font-weight:600}
.sidebar .subtitle{font-size:11px;color:rgba(255,255,255,0.6);padding:0 20px;margin-bottom:15px}
.sidebar nav a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:rgba(255,255,255,0.8);text-decoration:none;transition:all 0.2s;font-size:14px}
.sidebar nav a:hover{background:rgba(255,255,255,0.1);color:white}
.sidebar nav a.active{background:rgba(255,255,255,0.2);color:white;border-left:3px solid white}
.sidebar nav a.disabled{opacity:0.5;pointer-events:none}
.sidebar .divider{height:1px;background:rgba(255,255,255,0.1);margin:15px 20px}
.sidebar .section-title{font-size:11px;color:rgba(255,255,255,0.5);padding:10px 20px 5px;text-transform:uppercase;letter-spacing:1px}
.main{margin-left:260px;flex:1;padding:20px 30px}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px}
.header h1{font-size:22px;color:#333;font-weight:400;display:flex;align-items:center;gap:10px}
.header h1 small{font-size:14px;color:#666;font-weight:normal}
.card{background:white;border-radius:8px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.1)}
.card h3{margin-bottom:15px;color:#333;font-size:16px;display:flex;align-items:center;gap:8px}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-bottom:20px}
.kpi{background:white;border-radius:8px;padding:20px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.1)}
.kpi-value{font-size:32px;font-weight:700;color:#008ba3}
.kpi-label{color:#666;font-size:12px;margin-top:5px}
table{width:100%;border-collapse:collapse}
th,td{padding:10px 12px;text-align:left;border-bottom:1px solid #eee;font-size:13px}
th{background:#f8f9fa;font-weight:600;color:#555}
tr:hover{background:#f8f9fa}
.btn{padding:8px 16px;border:none;border-radius:4px;cursor:pointer;font-size:13px;display:inline-flex;align-items:center;gap:5px;text-decoration:none;transition:opacity 0.2s}
.btn:hover{opacity:0.9}
.btn-primary{background:#008ba3;color:white}
.btn-secondary{background:#6c757d;color:white}
.btn-success{background:#27ae60;color:white}
.btn-warning{background:#f39c12;color:white}
.btn-danger{background:#e74c3c;color:white}
.btn-sm{padding:5px 10px;font-size:12px}
.badge{padding:3px 8px;border-radius:12px;font-size:11px;font-weight:500}
.badge-success{background:#d4edda;color:#155724}
.badge-warning{background:#fff3cd;color:#856404}
.badge-danger{background:#f8d7da;color:#721c24}
.badge-info{background:#d1ecf1;color:#0c5460}
.badge-secondary{background:#e9ecef;color:#495057}
input,select,textarea{padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px;width:100%}
input:focus,select:focus,textarea:focus{outline:none;border-color:#008ba3}
.form-row{display:flex;gap:15px;margin-bottom:15px}
.form-group{flex:1;min-width:0}
.form-group label{display:block;margin-bottom:5px;color:#555;font-size:13px;font-weight:500}
.form-group small{color:#999;font-size:11px}
.form-section{background:#f8f9fa;padding:15px;border-radius:6px;margin-bottom:20px}
.form-section h4{margin-bottom:15px;color:#333;font-size:14px;display:flex;align-items:center;gap:8px}
.msg{padding:12px 15px;border-radius:4px;margin-bottom:15px;display:flex;align-items:center;gap:10px}
.msg-success{background:#d4edda;color:#155724}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
.modal.active{display:flex}
.modal-content{background:white;border-radius:8px;padding:25px;max-width:700px;width:90%;max-height:90vh;overflow-y:auto}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:1px solid #eee}
.modal-header h3{font-size:18px;color:#333}
.close-modal{cursor:pointer;color:#666;font-size:24px}
.grupo-card{border:1px solid #e0e0e0;border-radius:8px;margin-bottom:15px;overflow:hidden}
.grupo-card-header{background:#f8f9fa;padding:12px 15px;display:flex;justify-content:space-between;align-items:center;cursor:pointer}
.grupo-card-header:hover{background:#f0f0f0}
.grupo-card-body{padding:15px;display:none}
.grupo-card-body.active{display:block}
.checklist{list-style:none}
.checklist li{padding:10px;border-bottom:1px solid #eee;display:flex;align-items:center;gap:10px}
.checklist li:last-child{border-bottom:none}
.checklist input[type="checkbox"]{width:18px;height:18px}
.back-link{color:rgba(255,255,255,0.8);text-decoration:none;display:flex;align-items:center;gap:8px;padding:12px 20px;font-size:13px}
.back-link:hover{background:rgba(255,255,255,0.1);color:white}
.empty-state{text-align:center;padding:40px;color:#999}
.empty-state .material-icons{font-size:48px;margin-bottom:10px;opacity:0.5}
.stats-row{display:flex;gap:20px;margin-bottom:15px}
.stat-item{display:flex;align-items:center;gap:8px;font-size:13px;color:#666}
.stat-item strong{color:#333}
.info-box{background:#e3f2fd;border:1px solid #90caf9;border-radius:6px;padding:12px 15px;margin-bottom:15px;font-size:13px;color:#1565c0}
.checkbox-inline{display:flex;align-items:center;gap:8px;padding:10px 0}
.checkbox-inline input{width:auto}
.email-status{font-size:11px;margin-top:5px;padding:5px 8px;border-radius:4px;display:none}
.email-status.found{display:block;background:#d4edda;color:#155724}
.email-status.not-found{display:block;background:#fff3cd;color:#856404}
.email-status.loading{display:block;background:#e9ecef;color:#666}
</style>
</head>
<body>

<aside class="sidebar">
    <h1>tu<span>Speaking</span></h1>
    <div class="subtitle">📋 Ficha Administrativa</div>
    
    <nav>
        <a href="?s=fichas" class="<?=$seccion=='fichas'?'active':''?>">
            <span class="material-icons">folder_open</span>Fichas Formación
        </a>
        
        <?php if($ficha_id > 0 && $ficha): ?>
        <div class="divider"></div>
        <div class="section-title"><?=htmlspecialchars($ficha['empresa_nombre'] ?? '')?> - <?=htmlspecialchars($ficha['codigo_edicion'] ?? '')?></div>
        
        <a href="?s=grupos&ficha=<?=$ficha_id?>" class="<?=$seccion=='grupos'?'active':''?>">
            <span class="material-icons">groups</span>Gestión Grupos
        </a>
        <a href="?s=alumnos&ficha=<?=$ficha_id?>" class="<?=$seccion=='alumnos'?'active':''?>">
            <span class="material-icons">people</span>Alumnos
        </a>
        <a href="?s=fundae&ficha=<?=$ficha_id?>" class="<?=$seccion=='fundae'?'active':''?>">
            <span class="material-icons">description</span>FUNDAE Alta
        </a>
        <a href="?s=fundae_cierre&ficha=<?=$ficha_id?>" class="<?=$seccion=='fundae_cierre'?'active':''?>">
            <span class="material-icons">fact_check</span>FUNDAE Cierre
        </a>
        <?php else: ?>
        <div class="divider"></div>
        <div class="section-title">Selecciona una ficha</div>
        <a href="#" class="disabled"><span class="material-icons">groups</span>Gestión Grupos</a>
        <a href="#" class="disabled"><span class="material-icons">people</span>Alumnos</a>
        <a href="#" class="disabled"><span class="material-icons">description</span>FUNDAE Alta</a>
        <a href="#" class="disabled"><span class="material-icons">fact_check</span>FUNDAE Cierre</a>
        <?php endif; ?>
    </nav>
    
    <div class="divider"></div>
    <a href="admin.php" class="back-link"><span class="material-icons">arrow_back</span>Panel Empresas</a>
    <a href="/app/moodle/admin-panel/" class="back-link"><span class="material-icons">dashboard</span>Panel Principal</a>
</aside>

<main class="main">

<?php if(isset($_GET['msg'])): ?>
<div class="msg msg-success">
    <span class="material-icons">check_circle</span>
    <?php 
    $msgs = [
        'ficha_added'=>'Ficha creada correctamente',
        'ficha_updated'=>'Ficha actualizada',
        'edicion_vinculada'=>'Edición Moodle vinculada correctamente',
        'grupo_added'=>'Grupo añadido',
        'grupo_updated'=>'Grupo actualizado',
        'grupo_deleted'=>'Grupo eliminado',
        'alumno_added'=>'Alumno añadido',
        'alumno_updated'=>'Alumno actualizado',
        'alumno_deleted'=>'Alumno eliminado',
        'asignado'=>'Alumno asignado al grupo',
        'asignados_grupo'=>'Alumnos asignados al grupo y matriculados',
        'idioma_actualizado'=>'Idioma actualizado',
        'accion_masiva'=>'Acción aplicada a los alumnos seleccionados',
        'fundae_saved'=>'Datos FUNDAE guardados',
        'alta_completada'=>'Alta FUNDAE marcada como completada'
    ];
    echo $msgs[$_GET['msg']] ?? 'Operación completada'; 
    ?>
</div>
<?php endif; ?>

<!-- ============================================ -->
<!-- SECCIÓN: FICHAS -->
<!-- ============================================ -->
<?php if($seccion == 'fichas'): ?>

<div class="header">
    <h1><span class="material-icons">folder_open</span> Fichas de Formación</h1>
    <button class="btn btn-primary" onclick="document.getElementById('modal-ficha').classList.add('active')">
        <span class="material-icons">add</span> Nueva Ficha
    </button>
</div>

<div class="kpis">
    <div class="kpi"><div class="kpi-value"><?=$total_fichas?></div><div class="kpi-label">Total Fichas</div></div>
    <div class="kpi"><div class="kpi-value"><?=$fichas_activas?></div><div class="kpi-label">En Curso</div></div>
    <div class="kpi"><div class="kpi-value"><?=$total_grupos?></div><div class="kpi-label">Grupos</div></div>
    <div class="kpi"><div class="kpi-value"><?=$total_alumnos?></div><div class="kpi-label">Alumnos</div></div>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Empresa</th>
                <th>Edición</th>
                <th>Idiomas</th>
                <th>Periodo</th>
                <th>Modalidad</th>
                <th>Bonificación</th>
                <th>Config</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $fichas = $conn->query("SELECT f.*, e.nombre as empresa_nombre,
            (SELECT COUNT(*) FROM own_operaciones_grupos WHERE ficha_id=f.id) as num_grupos,
            (SELECT COUNT(*) FROM own_operaciones_alumnos WHERE ficha_id=f.id) as num_alumnos
            FROM own_operaciones_fichas f 
            INNER JOIN own_empresas e ON e.id = f.empresa_id 
            ORDER BY f.fecha_inicio DESC");
        while($f = $fichas->fetch_assoc()): 
            $estado_class = ['config_pendiente'=>'warning','alumnos_cargados'=>'info','grupos_asignados'=>'info','en_curso'=>'success','finalizado'=>'secondary'];
            $estado_label = ['config_pendiente'=>'Config. Pendiente','alumnos_cargados'=>'Alumnos Cargados','grupos_asignados'=>'Grupos Asignados','en_curso'=>'En Curso','finalizado'=>'Finalizado'];
        ?>
        <tr>
            <td><strong><?=htmlspecialchars($f['empresa_nombre'])?></strong></td>
            <td><?=htmlspecialchars($f['codigo_edicion'])?></td>
            <td><?=htmlspecialchars($f['idiomas'])?></td>
            <td><?=date('d/m/Y', strtotime($f['fecha_inicio']))?> - <?=date('d/m/Y', strtotime($f['fecha_fin']))?></td>
            <td><?=$f['modalidad_clases']=='1to1'?'1-to-1':'Grupal'?></td>
            <td><span class="badge badge-<?=$f['bonificacion']=='bonificado'?'success':'secondary'?>"><?=ucfirst($f['bonificacion'])?></span></td>
            <td>
                <?php if($f['aplica_a_todos']): ?>
                <span class="badge badge-info" title="Aplica a todos los alumnos">General</span>
                <?php else: ?>
                <span class="badge badge-warning" title="Configuración específica por alumno/grupo">Variable</span>
                <?php endif; ?>
            </td>
            <td><span class="badge badge-<?=$estado_class[$f['estado']] ?? 'secondary'?>"><?=$estado_label[$f['estado']] ?? $f['estado']?></span></td>
            <td style="white-space:nowrap">
                <button class="btn btn-sm btn-secondary" onclick='editarFicha(<?=json_encode($f)?>)' title="Editar ficha">
                    <span class="material-icons" style="font-size:14px">settings</span>
                </button>
                <a href="?s=grupos&ficha=<?=$f['id']?>" class="btn btn-sm btn-primary" title="Gestionar grupos">
                    <span class="material-icons" style="font-size:14px">groups</span>
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    
    <?php if($total_fichas == 0): ?>
    <div class="empty-state">
        <span class="material-icons">folder_off</span>
        <p>No hay fichas creadas todavía</p>
        <button class="btn btn-primary" onclick="document.getElementById('modal-ficha').classList.add('active')" style="margin-top:10px">
            Crear primera ficha
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Nueva Ficha -->
<div id="modal-ficha" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Nueva Ficha de Formación</h3>
            <span class="material-icons close-modal" onclick="this.closest('.modal').classList.remove('active')">close</span>
        </div>
        <form method="POST">
            <input type="hidden" name="add_ficha" value="1">
            
            <!-- Datos básicos -->
            <div class="form-section">
                <h4><span class="material-icons">business</span> Datos Básicos</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Empresa *</label>
                        <select name="empresa_id" id="ficha-empresa" required onchange="cargarEdiciones(this.value)">
                            <option value="">Seleccionar...</option>
                            <?php $empresas->data_seek(0); while($e = $empresas->fetch_assoc()): ?>
                            <option value="<?=$e['id']?>"><?=htmlspecialchars($e['nombre'])?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Edición Moodle</label>
                        <select name="edicion_id" id="ficha-edicion">
                            <option value="">Sin vincular</option>
                        </select>
                        <small>Vincula esta ficha a una edición de Moodle para acceder a sus cursos</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Código Edición *</label>
                        <input type="text" name="codigo_edicion" placeholder="ej: 26.1" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Idiomas disponibles *</label>
                    <div style="display:flex;flex-wrap:wrap;gap:15px;margin-top:8px">
                        <label style="display:flex;align-items:center;gap:5px;font-weight:normal;cursor:pointer">
                            <input type="checkbox" name="idiomas_check[]" value="Inglés" checked style="width:auto"> Inglés
                        </label>
                        <label style="display:flex;align-items:center;gap:5px;font-weight:normal;cursor:pointer">
                            <input type="checkbox" name="idiomas_check[]" value="Francés" style="width:auto"> Francés
                        </label>
                        <label style="display:flex;align-items:center;gap:5px;font-weight:normal;cursor:pointer">
                            <input type="checkbox" name="idiomas_check[]" value="Alemán" style="width:auto"> Alemán
                        </label>
                        <label style="display:flex;align-items:center;gap:5px;font-weight:normal;cursor:pointer">
                            <input type="checkbox" name="idiomas_check[]" value="Portugués" style="width:auto"> Portugués
                        </label>
                        <label style="display:flex;align-items:center;gap:5px;font-weight:normal;cursor:pointer">
                            <input type="checkbox" name="idiomas_check[]" value="Italiano" style="width:auto"> Italiano
                        </label>
                        <label style="display:flex;align-items:center;gap:5px;font-weight:normal;cursor:pointer">
                            <input type="checkbox" name="idiomas_check[]" value="Español" style="width:auto"> Español
                        </label>
                        <label style="display:flex;align-items:center;gap:5px;font-weight:normal;cursor:pointer">
                            <input type="checkbox" name="idiomas_check[]" value="Catalán" style="width:auto"> Catalán
                        </label>
                    </div>
                    <small>Selecciona los idiomas que incluye esta formación</small>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha Inicio *</label>
                        <input type="date" name="fecha_inicio" required>
                    </div>
                    <div class="form-group">
                        <label>Fecha Fin *</label>
                        <input type="date" name="fecha_fin" required>
                    </div>
                </div>
            </div>
            
            <!-- Bonificación -->
            <div class="form-section">
                <h4><span class="material-icons">euro</span> Bonificación</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo *</label>
                        <select name="bonificacion" required id="sel-bonificacion" onchange="toggleBonificacion()">
                            <option value="bonificado">Bonificado</option>
                            <option value="no_bonificado">No Bonificado</option>
                        </select>
                    </div>
                    <div class="form-group" id="grp-modalidad-bonif">
                        <label>Modalidad Bonificación</label>
                        <select name="modalidad_bonificacion">
                            <option value="teleformacion">Teleformación</option>
                            <option value="aula_virtual">Aula Virtual</option>
                            <option value="mixta">Mixta</option>
                        </select>
                    </div>
                    <div class="form-group" id="grp-gestion-bonif">
                        <label>Gestión Bonificación</label>
                        <select name="gestion_bonificacion">
                            <option value="tuspeaking">tuSpeaking</option>
                            <option value="externo">Externo</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Modalidad de clases -->
            <div class="form-section">
                <h4><span class="material-icons">school</span> Modalidad de Clases</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo de Clases *</label>
                        <select name="modalidad_clases" required>
                            <option value="grupal">Grupal</option>
                            <option value="1to1">1-to-1</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Obligatoriedad Plataforma *</label>
                        <select name="obligatoriedad_plataforma" required>
                            <option value="si">Sí, obligatoria</option>
                            <option value="opcional">Opcional</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Horas y precios -->
            <div class="form-section">
                <h4><span class="material-icons">schedule</span> Horas y Precios</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Horas Plataforma</label>
                        <input type="number" name="horas_plataforma" value="48" min="0">
                    </div>
                    <div class="form-group">
                        <label>Nº Clases Conversación</label>
                        <input type="number" name="clases_conversacion" value="24" min="0">
                    </div>
                    <div class="form-group">
                        <label>Duración Clase (min)</label>
                        <input type="number" name="duracion_clase_min" value="60" min="30" step="15">
                    </div>
                    <div class="form-group">
                        <label>Duración Total (h)</label>
                        <input type="number" name="duracion_total_curso" value="" min="0" placeholder="Total horas curso">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Precio/Licencia (€)</label>
                        <input type="number" name="precio_licencia" value="0" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Precio/Hora (€)</label>
                        <input type="number" name="precio_hora" value="0" step="0.01" min="0">
                    </div>
                </div>
            </div>
            
            <!-- Configuración general -->
            <div class="info-box">
                <div class="checkbox-inline">
                    <input type="checkbox" name="aplica_a_todos" id="aplica_todos" checked>
                    <label for="aplica_todos"><strong>Esta configuración aplica a todos los alumnos</strong></label>
                </div>
                <small>Si no aplica a todos, deberás especificar los datos en cada grupo o alumno individualmente.</small>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px">Crear Ficha</button>
        </form>
    </div>
</div>

<!-- Modal Editar Ficha -->
<div id="modal-editar-ficha" class="modal">
    <div class="modal-content" style="max-width:700px">
        <div class="modal-header">
            <h3 id="editar-ficha-title">Editar Ficha</h3>
            <span class="material-icons close-modal" onclick="this.closest('.modal').classList.remove('active')">close</span>
        </div>
        <form method="POST">
            <input type="hidden" name="update_ficha" value="1">
            <input type="hidden" name="id" id="edit-ficha-id">
            
            <div class="form-section">
                <h4><span class="material-icons">link</span> Vinculación Moodle</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Empresa</label>
                        <input type="text" id="edit-ficha-empresa" readonly style="background:#f5f5f5">
                    </div>
                    <div class="form-group">
                        <label>Edición Moodle</label>
                        <select name="edicion_id" id="edit-ficha-edicion">
                            <option value="">Sin vincular</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4><span class="material-icons">event</span> Fechas y Estado</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" id="edit-ficha-fecha-inicio" required>
                    </div>
                    <div class="form-group">
                        <label>Fecha Fin</label>
                        <input type="date" name="fecha_fin" id="edit-ficha-fecha-fin" required>
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado" id="edit-ficha-estado">
                            <option value="config_pendiente">Config. Pendiente</option>
                            <option value="alumnos_cargados">Alumnos Cargados</option>
                            <option value="grupos_asignados">Grupos Asignados</option>
                            <option value="en_curso">En Curso</option>
                            <option value="finalizado">Finalizado</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4><span class="material-icons">translate</span> Idiomas</h4>
                <div class="form-group">
                    <input type="text" name="idiomas" id="edit-ficha-idiomas" placeholder="Inglés, Francés...">
                </div>
            </div>
            
            <div class="form-section">
                <h4><span class="material-icons">school</span> Configuración Formación</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Modalidad</label>
                        <select name="modalidad_clases" id="edit-ficha-modalidad">
                            <option value="1to1">1-to-1</option>
                            <option value="grupal">Grupal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Plataforma</label>
                        <select name="obligatoriedad_plataforma" id="edit-ficha-plataforma">
                            <option value="obligatoria">Obligatoria</option>
                            <option value="opcional">Opcional</option>
                            <option value="no_aplica">No aplica</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Horas Plataforma</label>
                        <input type="number" name="horas_plataforma" id="edit-ficha-horas-plat" min="0">
                    </div>
                    <div class="form-group">
                        <label>Clases Conversación</label>
                        <input type="number" name="clases_conversacion" id="edit-ficha-clases" min="0">
                    </div>
                    <div class="form-group">
                        <label>Duración Clase (min)</label>
                        <select name="duracion_clase_min" id="edit-ficha-duracion">
                            <option value="30">30 min</option>
                            <option value="45">45 min</option>
                            <option value="60">60 min</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Duración Total (h)</label>
                        <input type="number" name="duracion_total_curso" id="edit-ficha-total" min="0">
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4><span class="material-icons">euro</span> Bonificación</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="bonificacion" id="edit-ficha-bonificacion">
                            <option value="no_bonificado">No bonificado</option>
                            <option value="bonificado">Bonificado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Modalidad Bonif.</label>
                        <select name="modalidad_bonificacion" id="edit-ficha-mod-bonif">
                            <option value="">-</option>
                            <option value="presencial">Presencial</option>
                            <option value="teleformacion">Teleformación</option>
                            <option value="mixta">Mixta</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Gestión</label>
                        <select name="gestion_bonificacion" id="edit-ficha-gestion-bonif">
                            <option value="">-</option>
                            <option value="tuspeaking">TuSpeaking</option>
                            <option value="cliente">Cliente</option>
                            <option value="gestor_externo">Gestor externo</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Precio Licencia (€)</label>
                        <input type="number" name="precio_licencia" id="edit-ficha-precio-lic" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Precio Hora (€)</label>
                        <input type="number" name="precio_hora" id="edit-ficha-precio-hora" step="0.01" min="0">
                    </div>
                </div>
            </div>
            
            <div class="info-box">
                <div class="checkbox-inline">
                    <input type="checkbox" name="aplica_a_todos" id="edit-aplica-todos">
                    <label for="edit-aplica-todos"><strong>Esta configuración aplica a todos los alumnos</strong></label>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px">Guardar Cambios</button>
        </form>
    </div>
</div>

<script>
function toggleBonificacion() {
    var bonif = document.getElementById('sel-bonificacion').value;
    var show = bonif === 'bonificado';
    document.getElementById('grp-modalidad-bonif').style.display = show ? 'block' : 'none';
    document.getElementById('grp-gestion-bonif').style.display = show ? 'block' : 'none';
}

// Ediciones por empresa (cargadas desde PHP)
var edicionesPorEmpresa = <?=json_encode($ediciones_por_empresa ?? [], JSON_HEX_APOS | JSON_HEX_QUOT)?> || {};

function cargarEdiciones(empresaId) {
    var select = document.getElementById('ficha-edicion');
    select.innerHTML = '<option value="">Sin vincular</option>';
    
    if (empresaId && edicionesPorEmpresa[empresaId]) {
        edicionesPorEmpresa[empresaId].forEach(function(ed) {
            var opt = document.createElement('option');
            opt.value = ed.id;
            opt.textContent = ed.edicion_nombre + ' (' + ed.num_cursos + ' cursos)';
            select.appendChild(opt);
        });
    }
}

function editarFicha(f) {
    document.getElementById('edit-ficha-id').value = f.id;
    document.getElementById('editar-ficha-title').textContent = 'Editar: ' + f.empresa_nombre + ' - ' + f.codigo_edicion;
    document.getElementById('edit-ficha-empresa').value = f.empresa_nombre;
    
    // Cargar ediciones para esta empresa
    var selectEd = document.getElementById('edit-ficha-edicion');
    selectEd.innerHTML = '<option value="">Sin vincular</option>';
    if (edicionesPorEmpresa[f.empresa_id]) {
        edicionesPorEmpresa[f.empresa_id].forEach(function(ed) {
            var opt = document.createElement('option');
            opt.value = ed.id;
            opt.textContent = ed.edicion_nombre + ' (' + ed.num_cursos + ' cursos)';
            if (f.edicion_id == ed.id) opt.selected = true;
            selectEd.appendChild(opt);
        });
    }
    
    document.getElementById('edit-ficha-fecha-inicio').value = f.fecha_inicio || '';
    document.getElementById('edit-ficha-fecha-fin').value = f.fecha_fin || '';
    document.getElementById('edit-ficha-estado').value = f.estado || 'config_pendiente';
    document.getElementById('edit-ficha-idiomas').value = f.idiomas || '';
    document.getElementById('edit-ficha-modalidad').value = f.modalidad_clases || '1to1';
    document.getElementById('edit-ficha-plataforma').value = f.obligatoriedad_plataforma || 'obligatoria';
    document.getElementById('edit-ficha-horas-plat').value = f.horas_plataforma || 0;
    document.getElementById('edit-ficha-clases').value = f.clases_conversacion || 0;
    document.getElementById('edit-ficha-duracion').value = f.duracion_clase_min || 60;
    document.getElementById('edit-ficha-total').value = f.duracion_total_curso || '';
    document.getElementById('edit-ficha-bonificacion').value = f.bonificacion || 'no_bonificado';
    document.getElementById('edit-ficha-mod-bonif').value = f.modalidad_bonificacion || '';
    document.getElementById('edit-ficha-gestion-bonif').value = f.gestion_bonificacion || '';
    document.getElementById('edit-ficha-precio-lic').value = f.precio_licencia || 0;
    document.getElementById('edit-ficha-precio-hora').value = f.precio_hora || 0;
    document.getElementById('edit-aplica-todos').checked = f.aplica_a_todos == 1;
    
    document.getElementById('modal-editar-ficha').classList.add('active');
}
</script>

<!-- ============================================ -->
<!-- SECCIÓN: GRUPOS -->
<!-- ============================================ -->
<?php elseif($seccion == 'grupos' && $ficha): ?>

<?php
// Obtener nombre de edición si está vinculada
$edicion_vinculada = null;
if (!empty($ficha['edicion_id'])) {
    $edicion_vinculada = $conn->query("SELECT c.name as nombre 
        FROM own_empresa_ediciones ed 
        JOIN mdl_course_categories c ON c.id = ed.categoria_id 
        WHERE ed.id = {$ficha['edicion_id']}")->fetch_assoc();
}
?>

<div class="header">
    <h1>
        <span class="material-icons">groups</span> Gestión de Grupos
        <small>| <?=htmlspecialchars($ficha['empresa_nombre'])?> - <?=htmlspecialchars($ficha['codigo_edicion'])?></small>
        <?php if($edicion_vinculada): ?>
        <span class="badge badge-success" style="margin-left:10px;font-size:12px">🔗 <?=htmlspecialchars($edicion_vinculada['nombre'])?></span>
        <?php endif; ?>
    </h1>
    <button class="btn btn-primary" onclick="document.getElementById('modal-grupo').classList.add('active')">
        <span class="material-icons">add</span> Nuevo Grupo
    </button>
</div>

<?php if(empty($ficha['edicion_id'])): ?>
<div class="info-box" style="background:#fff3cd;border-left-color:#ffc107">
    <span class="material-icons" style="vertical-align:middle;color:#856404">warning</span>
    <strong>Sin edición vinculada:</strong> Esta ficha no está vinculada a una edición de Moodle. No podrás vincular grupos a cursos hasta que la vincules.
    <button class="btn btn-sm" style="margin-left:10px;background:#856404;color:white" onclick="document.getElementById('modal-vincular-edicion').classList.add('active')">Vincular ahora</button>
</div>

<!-- Modal Vincular Edición -->
<div id="modal-vincular-edicion" class="modal">
    <div class="modal-content" style="max-width:400px">
        <div class="modal-header">
            <h3>Vincular Edición Moodle</h3>
            <span class="material-icons close-modal" onclick="this.closest('.modal').classList.remove('active')">close</span>
        </div>
        <form method="POST">
            <input type="hidden" name="vincular_edicion" value="1">
            <input type="hidden" name="ficha_id" value="<?=$ficha_id?>">
            <div class="form-group">
                <label>Selecciona la edición de Moodle</label>
                <select name="edicion_id" required>
                    <option value="">Seleccionar...</option>
                    <?php 
                    $ediciones_empresa = $conn->query("SELECT ed.id, c.name as nombre 
                        FROM own_empresa_ediciones ed 
                        JOIN mdl_course_categories c ON c.id = ed.categoria_id 
                        WHERE ed.empresa_id = {$ficha['empresa_id']} 
                        ORDER BY c.name DESC");
                    while($ed = $ediciones_empresa->fetch_assoc()): 
                    ?>
                    <option value="<?=$ed['id']?>"><?=htmlspecialchars($ed['nombre'])?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px">Vincular</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if(!$ficha['aplica_a_todos']): ?>
<div class="info-box">
    <span class="material-icons" style="vertical-align:middle">info</span>
    <strong>Configuración variable:</strong> Esta ficha tiene configuración específica por alumno/grupo. Recuerda definir los datos particulares en cada grupo.
</div>
<?php endif; ?>

<?php
$grupos = $conn->query("SELECT g.*, 
    (SELECT COUNT(*) FROM own_operaciones_alumnos WHERE grupo_id=g.id) as num_alumnos
    FROM own_operaciones_grupos g WHERE g.ficha_id = $ficha_id ORDER BY g.nivel, g.nombre");
$alumnos_sin_grupo = $conn->query("SELECT COUNT(*) as c FROM own_operaciones_alumnos WHERE ficha_id=$ficha_id AND grupo_id IS NULL")->fetch_assoc()['c'];
?>

<div class="stats-row">
    <div class="stat-item"><span class="material-icons" style="color:#008ba3">groups</span> <strong><?=$grupos->num_rows?></strong> grupos</div>
    <div class="stat-item"><span class="material-icons" style="color:#f39c12">person_off</span> <strong><?=$alumnos_sin_grupo?></strong> alumnos sin asignar</div>
</div>

<?php if($grupos->num_rows == 0): ?>
<div class="card">
    <div class="empty-state">
        <span class="material-icons">group_off</span>
        <p>No hay grupos creados para esta ficha</p>
        <button class="btn btn-primary" onclick="document.getElementById('modal-grupo').classList.add('active')" style="margin-top:10px">
            Crear primer grupo
        </button>
    </div>
</div>
<?php else: ?>

<?php while($g = $grupos->fetch_assoc()): 
    // Obtener nombre del curso Moodle si está vinculado
    $curso_moodle_nombre = '';
    if ($g['curso_moodle_id']) {
        $cm = $conn->query("SELECT fullname FROM mdl_course WHERE id = {$g['curso_moodle_id']}")->fetch_assoc();
        $curso_moodle_nombre = $cm ? $cm['fullname'] : '';
    }
?>
<div class="grupo-card">
    <div class="grupo-card-header" onclick="this.nextElementSibling.classList.toggle('active')">
        <div>
            <strong><?=htmlspecialchars($g['nombre'])?></strong>
            <span class="badge badge-info" style="margin-left:10px"><?=$g['nivel']?></span>
            <?php if($g['codigo_fundae']): ?>
            <span class="badge badge-secondary" style="margin-left:5px">FUNDAE: <?=$g['codigo_fundae']?></span>
            <?php endif; ?>
            <?php if($curso_moodle_nombre): ?>
            <span class="badge badge-success" style="margin-left:5px" title="Curso Moodle vinculado">🔗 <?=htmlspecialchars($curso_moodle_nombre)?></span>
            <?php endif; ?>
        </div>
        <div style="display:flex;align-items:center;gap:15px">
            <span style="color:#666;font-size:13px">
                <?=$g['dia_semana']?> <?=$g['hora_inicio']?><?=$g['hora_fin']?' - '.$g['hora_fin']:''?>
                | <?=$g['num_alumnos']?>/<?=$g['max_alumnos']?> alumnos
                | <?=htmlspecialchars($g['profesor_nombre'] ?: 'Sin profesor')?>
            </span>
            <span class="material-icons">expand_more</span>
        </div>
    </div>
    <div class="grupo-card-body">
        <?php
        $alumnos_grupo = $conn->query("SELECT * FROM own_operaciones_alumnos WHERE grupo_id={$g['id']} ORDER BY alumno_apellidos, alumno_nombre");
        if($alumnos_grupo->num_rows > 0):
        ?>
        <table>
            <thead><tr><th>Nombre</th><th>Email</th><th>Nivel</th><th>Bienvenida</th><th>Estado</th></tr></thead>
            <tbody>
            <?php while($a = $alumnos_grupo->fetch_assoc()): ?>
            <tr>
                <td><?=htmlspecialchars($a['alumno_nombre'].' '.$a['alumno_apellidos'])?></td>
                <td><?=htmlspecialchars($a['alumno_email'])?></td>
                <td><?=$a['nivel_asignado'] ?: '-'?></td>
                <td><span class="badge badge-<?=$a['envio_bienvenida']=='enviado'?'success':'warning'?>"><?=$a['envio_bienvenida']?></span></td>
                <td><span class="badge badge-<?=$a['estado']=='activo'?'success':($a['estado']=='baja'?'danger':'warning')?>"><?=$a['estado']?></span></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#999;text-align:center;padding:20px">No hay alumnos asignados a este grupo</p>
        <?php endif; ?>
        
        <div style="margin-top:15px;padding-top:15px;border-top:1px solid #eee;display:flex;gap:10px">
            <button class="btn btn-sm btn-secondary" onclick='editGrupo(<?=json_encode($g)?>)'>
                <span class="material-icons" style="font-size:14px">edit</span> Editar
            </button>
            <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este grupo? Los alumnos quedarán sin asignar.')">
                <input type="hidden" name="delete_grupo" value="1">
                <input type="hidden" name="id" value="<?=$g['id']?>">
                <input type="hidden" name="ficha_id" value="<?=$ficha_id?>">
                <button class="btn btn-sm btn-danger"><span class="material-icons" style="font-size:14px">delete</span> Eliminar</button>
            </form>
        </div>
    </div>
</div>
<?php endwhile; ?>

<?php endif; ?>

<?php
// Obtener cursos de Moodle de la categoría vinculada a esta ficha
$cursos_moodle = [];
if ($ficha['edicion_id']) {
    $edicion = $conn->query("SELECT categoria_id FROM own_empresa_ediciones WHERE id = {$ficha['edicion_id']}")->fetch_assoc();
    if ($edicion && $edicion['categoria_id']) {
        $cursos_result = $conn->query("SELECT id, fullname FROM mdl_course WHERE category = {$edicion['categoria_id']} ORDER BY fullname");
        while ($curso = $cursos_result->fetch_assoc()) {
            $cursos_moodle[] = $curso;
        }
    }
}
?>

<!-- Modal Nuevo Grupo -->
<div id="modal-grupo" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-grupo-title">Nuevo Grupo</h3>
            <span class="material-icons close-modal" onclick="this.closest('.modal').classList.remove('active')">close</span>
        </div>
        <form method="POST" id="form-grupo">
            <input type="hidden" name="add_grupo" value="1" id="grupo-action">
            <input type="hidden" name="ficha_id" value="<?=$ficha_id?>">
            <input type="hidden" name="id" id="grupo-id">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nombre del Grupo *</label>
                    <input type="text" name="nombre" id="grupo-nombre" placeholder="ej: B1 Level - Martes 9:30" required>
                </div>
                <div class="form-group">
                    <label>Nivel *</label>
                    <select name="nivel" id="grupo-nivel" required>
                        <option value="">Seleccionar...</option>
                        <option value="A1">A1</option>
                        <option value="A2">A2</option>
                        <option value="B1">B1</option>
                        <option value="B1.2">B1.2</option>
                        <option value="B2">B2</option>
                        <option value="B2.2">B2.2</option>
                        <option value="C1">C1</option>
                        <option value="C2">C2</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Código FUNDAE</label>
                    <input type="text" name="codigo_fundae" id="grupo-codigo-fundae" placeholder="ej: 009-01">
                </div>
                <div class="form-group">
                    <label>Profesor</label>
                    <input type="text" name="profesor_nombre" id="grupo-profesor" placeholder="Nombre del profesor">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Día de la Semana</label>
                    <select name="dia_semana" id="grupo-dia">
                        <option value="">Seleccionar...</option>
                        <option value="Lunes">Lunes</option>
                        <option value="Martes">Martes</option>
                        <option value="Miércoles">Miércoles</option>
                        <option value="Jueves">Jueves</option>
                        <option value="Viernes">Viernes</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Hora Inicio</label>
                    <input type="time" name="hora_inicio" id="grupo-hora-inicio">
                </div>
                <div class="form-group">
                    <label>Hora Fin</label>
                    <input type="time" name="hora_fin" id="grupo-hora-fin">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Fecha Inicio Clases</label>
                    <input type="date" name="fecha_inicio_clases" id="grupo-fecha-inicio">
                </div>
                <div class="form-group">
                    <label>Fecha Fin Clases</label>
                    <input type="date" name="fecha_fin_clases" id="grupo-fecha-fin">
                </div>
                <div class="form-group">
                    <label>Máx. Alumnos</label>
                    <input type="number" name="max_alumnos" id="grupo-max" value="8" min="1" max="20">
                </div>
            </div>
            
            <div class="form-group">
                <label>URL Reunión (Teams/Zoom)</label>
                <input type="url" name="url_reunion" id="grupo-url" placeholder="https://...">
            </div>
            
            <div class="form-group">
                <label>Curso Moodle</label>
                <select name="curso_moodle_id" id="grupo-curso-moodle">
                    <option value="">Sin vincular</option>
                    <?php foreach($cursos_moodle as $cm): ?>
                    <option value="<?=$cm['id']?>"><?=htmlspecialchars($cm['fullname'])?></option>
                    <?php endforeach; ?>
                </select>
                <?php if(empty($cursos_moodle)): ?>
                <small style="color:#999">No hay cursos disponibles. <?=$ficha['edicion_id'] ? 'Crea cursos en Ediciones primero.' : 'Vincula esta ficha a una edición primero.'?></small>
                <?php endif; ?>
            </div>
            
            <div class="form-group" id="grupo-estado-wrapper" style="display:none">
                <label>Estado</label>
                <select name="estado" id="grupo-estado">
                    <option value="activo">Activo</option>
                    <option value="pausado">Pausado</option>
                    <option value="finalizado">Finalizado</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px" id="grupo-submit-btn">Crear Grupo</button>
        </form>
    </div>
</div>

<script>
function editGrupo(g) {
    document.getElementById('modal-grupo-title').textContent = 'Editar Grupo';
    document.getElementById('grupo-action').name = 'update_grupo';
    document.getElementById('grupo-id').value = g.id;
    document.getElementById('grupo-nombre').value = g.nombre;
    document.getElementById('grupo-nivel').value = g.nivel || '';
    document.getElementById('grupo-codigo-fundae').value = g.codigo_fundae || '';
    document.getElementById('grupo-profesor').value = g.profesor_nombre || '';
    document.getElementById('grupo-dia').value = g.dia_semana || '';
    document.getElementById('grupo-hora-inicio').value = g.hora_inicio || '';
    document.getElementById('grupo-hora-fin').value = g.hora_fin || '';
    document.getElementById('grupo-fecha-inicio').value = g.fecha_inicio_clases || '';
    document.getElementById('grupo-fecha-fin').value = g.fecha_fin_clases || '';
    document.getElementById('grupo-max').value = g.max_alumnos || 8;
    document.getElementById('grupo-url').value = g.url_reunion || '';
    document.getElementById('grupo-curso-moodle').value = g.curso_moodle_id || '';
    document.getElementById('grupo-estado').value = g.estado || 'activo';
    document.getElementById('grupo-estado-wrapper').style.display = 'block';
    document.getElementById('grupo-submit-btn').textContent = 'Actualizar Grupo';
    document.getElementById('modal-grupo').classList.add('active');
}
</script>

<!-- ============================================ -->
<!-- SECCIÓN: ALUMNOS -->
<!-- ============================================ -->
<?php elseif($seccion == 'alumnos' && $ficha): ?>

<div class="header">
    <h1>
        <span class="material-icons">people</span> Alumnos
        <small>| <?=htmlspecialchars($ficha['empresa_nombre'])?> - <?=htmlspecialchars($ficha['codigo_edicion'])?></small>
    </h1>
    <div style="display:flex;gap:10px">
        <button class="btn btn-primary" onclick="abrirModalAlumno()">
            <span class="material-icons">person_add</span> Añadir Alumno
        </button>
    </div>
</div>

<?php
$grupos_ficha = $conn->query("SELECT id, nombre, nivel, curso_moodle_id FROM own_operaciones_grupos WHERE ficha_id=$ficha_id ORDER BY nivel, nombre");
$grupos_array = [];
while($gf = $grupos_ficha->fetch_assoc()) { $grupos_array[] = $gf; }

// Obtener idiomas disponibles en la ficha
$idiomas_ficha = array_map('trim', explode(',', $ficha['idiomas'] ?? 'Inglés'));

// Filtros
$filtro_busqueda = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$filtro_idioma = isset($_GET['idioma']) ? $conn->real_escape_string($_GET['idioma']) : '';
$filtro_estado = isset($_GET['estado']) ? $conn->real_escape_string($_GET['estado']) : '';
$filtro_grupo = isset($_GET['grupo']) ? $_GET['grupo'] : '';

$where_extra = "";
if ($filtro_busqueda) {
    $where_extra .= " AND (a.alumno_nombre LIKE '%$filtro_busqueda%' OR a.alumno_apellidos LIKE '%$filtro_busqueda%' OR a.alumno_email LIKE '%$filtro_busqueda%')";
}
if ($filtro_idioma) {
    $where_extra .= " AND a.idioma = '$filtro_idioma'";
}
if ($filtro_estado) {
    $where_extra .= " AND a.estado = '$filtro_estado'";
}
if ($filtro_grupo === 'sin') {
    $where_extra .= " AND a.grupo_id IS NULL";
} elseif ($filtro_grupo !== '' && is_numeric($filtro_grupo)) {
    $where_extra .= " AND a.grupo_id = " . (int)$filtro_grupo;
}

$alumnos = $conn->query("SELECT a.*, g.nombre as grupo_nombre, g.curso_moodle_id 
    FROM own_operaciones_alumnos a 
    LEFT JOIN own_operaciones_grupos g ON g.id = a.grupo_id 
    WHERE a.ficha_id = $ficha_id $where_extra
    ORDER BY a.alumno_apellidos, a.alumno_nombre");
$num_alumnos = $alumnos->num_rows;

$total_alumnos = $conn->query("SELECT COUNT(*) as c FROM own_operaciones_alumnos WHERE ficha_id=$ficha_id")->fetch_assoc()['c'];
?>

<div class="stats-row">
    <div class="stat-item"><span class="material-icons" style="color:#008ba3">people</span> <strong><?=$total_alumnos?></strong> alumnos totales</div>
    <?php if($num_alumnos != $total_alumnos): ?>
    <div class="stat-item"><span class="material-icons" style="color:#f39c12">filter_alt</span> <strong><?=$num_alumnos?></strong> mostrados</div>
    <?php endif; ?>
</div>

<!-- Búsqueda y filtros -->
<div class="card" style="padding:15px;margin-bottom:15px">
    <form method="GET" style="display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="s" value="alumnos">
        <input type="hidden" name="ficha" value="<?=$ficha_id?>">
        
        <div class="form-group" style="flex:2;min-width:200px;margin:0">
            <label style="font-size:12px">Buscar</label>
            <input type="text" name="q" value="<?=htmlspecialchars($filtro_busqueda)?>" placeholder="Nombre, apellidos o email..." style="padding:6px 10px">
        </div>
        
        <div class="form-group" style="flex:1;min-width:100px;margin:0">
            <label style="font-size:12px">Idioma</label>
            <select name="idioma" style="padding:6px 10px">
                <option value="">Todos</option>
                <?php foreach($idiomas_ficha as $idioma_opt): ?>
                <option value="<?=htmlspecialchars($idioma_opt)?>" <?=$filtro_idioma==$idioma_opt?'selected':''?>><?=htmlspecialchars($idioma_opt)?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group" style="flex:1;min-width:100px;margin:0">
            <label style="font-size:12px">Estado</label>
            <select name="estado" style="padding:6px 10px">
                <option value="">Todos</option>
                <option value="pendiente" <?=$filtro_estado=='pendiente'?'selected':''?>>Pendiente</option>
                <option value="activo" <?=$filtro_estado=='activo'?'selected':''?>>Activo</option>
                <option value="baja" <?=$filtro_estado=='baja'?'selected':''?>>Baja</option>
            </select>
        </div>
        
        <div class="form-group" style="flex:1;min-width:130px;margin:0">
            <label style="font-size:12px">Grupo</label>
            <select name="grupo" style="padding:6px 10px">
                <option value="">Todos</option>
                <option value="sin" <?=$filtro_grupo=='sin'?'selected':''?>>Sin asignar</option>
                <?php foreach($grupos_array as $gf): ?>
                <option value="<?=$gf['id']?>" <?=$filtro_grupo==$gf['id']?'selected':''?>><?=$gf['nivel']?> - <?=htmlspecialchars($gf['nombre'])?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary" style="padding:6px 12px">
            <span class="material-icons" style="font-size:16px">search</span> Filtrar
        </button>
        
        <?php if($filtro_busqueda || $filtro_idioma || $filtro_estado || $filtro_grupo): ?>
        <a href="?s=alumnos&ficha=<?=$ficha_id?>" class="btn btn-secondary" style="padding:6px 12px">
            <span class="material-icons" style="font-size:16px">clear</span> Limpiar
        </a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <?php if($num_alumnos > 0): ?>
    
    <!-- Barra de acciones masivas -->
    <div id="barra-masiva" style="display:none;background:#008ba3;color:white;padding:12px 15px;margin:-1px -1px 0 -1px;border-radius:8px 8px 0 0;align-items:center;gap:15px;flex-wrap:wrap">
        <span><strong id="count-seleccionados">0</strong> alumnos seleccionados</span>
        <form method="POST" id="form-masivo" style="display:flex;gap:10px;flex-wrap:wrap">
            <input type="hidden" name="ficha_id" value="<?=$ficha_id?>">
            <div id="ids-container"></div>
            <select name="accion_masiva" required style="padding:6px 10px;border-radius:4px;border:none">
                <option value="">Seleccionar acción...</option>
                <optgroup label="Asignar a Grupo">
                    <?php foreach($grupos_array as $gm): ?>
                    <option value="asignar_grupo_<?=$gm['id']?>"><?=$gm['nombre']?> (<?=$gm['nivel']?>)<?=$gm['curso_moodle_id']?' 🔗':''?></option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="Seguimiento">
                    <option value="alta_completada">✓ Marcar alta plataforma completada</option>
                    <option value="curso_montado">✓ Marcar curso montado</option>
                    <option value="nivel_registrado">✓ Marcar nivel registrado</option>
                    <option value="prueba_enviada">✓ Marcar prueba nivel enviada</option>
                    <option value="bienvenida_enviada">✓ Marcar bienvenida enviada</option>
                    <option value="notif_inicio">✓ Marcar notif. inicio enviada</option>
                </optgroup>
                <optgroup label="Estado">
                    <option value="estado_activo">Cambiar estado a Activo</option>
                </optgroup>
            </select>
            <button type="submit" class="btn" style="background:white;color:#008ba3;padding:6px 12px">
                <span class="material-icons" style="font-size:16px">check</span> Aplicar
            </button>
        </form>
        <button type="button" onclick="deselectAll()" class="btn" style="background:rgba(255,255,255,0.2);color:white;padding:6px 12px;margin-left:auto">
            <span class="material-icons" style="font-size:16px">close</span> Cancelar
        </button>
    </div>
    
    <div style="overflow-x:auto">
    <table>
        <thead>
            <tr>
                <th style="width:40px"><input type="checkbox" id="select-all" onchange="toggleSelectAll(this)"></th>
                <th>ID</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Idioma</th>
                <th>Grupo</th>
                <th title="Fechas: Inicio | Fin | Matrícula">Fechas</th>
                <th title="Alta plataforma | Nivel registrado | Curso montado">Alta</th>
                <th title="Prueba enviada | Resultado">Nivel</th>
                <th title="Bienvenida | Notif. inicio">Notif.</th>
                <th title="Clases conv. | Horas plat.">Horas</th>
                <th title="Temas completados / asignados">Temas</th>
                <th>Estado</th>
                <th>Notas</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php while($a = $alumnos->fetch_assoc()): ?>
        <tr>
            <td><input type="checkbox" class="alumno-check" value="<?=$a['id']?>" onchange="updateSelection()"></td>
            <td style="font-size:11px;color:#666"><?=$a['moodle_userid'] ?: '-'?></td>
            <td><strong><?=htmlspecialchars($a['alumno_nombre'].' '.$a['alumno_apellidos'])?></strong></td>
            <td style="font-size:11px"><?=htmlspecialchars($a['alumno_email'])?></td>
            <td style="font-size:11px"><?=htmlspecialchars($a['idioma'] ?? 'Inglés')?></td>
            <td style="font-size:11px">
                <?php if($a['grupo_nombre']): ?>
                    <?=htmlspecialchars($a['grupo_nombre'])?>
                    <?php if($a['curso_moodle_id']): ?>
                    <a href="/app/moodle/course/view.php?id=<?=$a['curso_moodle_id']?>" target="_blank" title="Abrir curso en Moodle" style="margin-left:3px;color:#008ba3">
                        <span class="material-icons" style="font-size:14px;vertical-align:middle">open_in_new</span>
                    </a>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="color:#999">-</span>
                <?php endif; ?>
            </td>
            <td style="font-size:10px;white-space:nowrap" title="Inicio: <?=$a['fecha_inicio']?> | Fin: <?=$a['fecha_fin']?> | Matrícula: <?=$a['fecha_matricula']?>">
                <?=$a['fecha_inicio'] ? date('d/m', strtotime($a['fecha_inicio'])) : '-'?> | 
                <?=$a['fecha_fin'] ? date('d/m', strtotime($a['fecha_fin'])) : '-'?>
                <?php if($a['fecha_matricula']): ?><br><small style="color:#008ba3">Mat: <?=date('d/m', strtotime($a['fecha_matricula']))?></small><?php endif; ?>
            </td>
            <td style="font-size:11px" title="Alta: <?=$a['alta_plataforma']?> | Nivel reg: <?=$a['nivel_registrado']?> | Curso: <?=$a['curso_montado']?>">
                <?=$a['alta_plataforma']=='completada' ? '✓' : '○'?>
                <?=$a['nivel_registrado']=='si' ? '✓' : '○'?>
                <?=$a['curso_montado']=='si' ? '✓' : '○'?>
            </td>
            <td style="font-size:11px" title="Prueba enviada: <?=$a['prueba_nivel_enviada']?> | Resultado: <?=$a['prueba_nivel_resultado']?>">
                <?=$a['prueba_nivel_enviada']=='si' ? '✓' : '○'?>
                <strong><?=$a['prueba_nivel_resultado'] ?: ($a['nivel_asignado'] ?: '-')?></strong>
            </td>
            <td style="font-size:11px" title="Bienvenida: <?=$a['envio_bienvenida']?> | Notif inicio: <?=$a['notif_inicio_enviada']?>">
                <?=$a['envio_bienvenida']=='enviado' ? '✓' : '○'?>
                <?=$a['notif_inicio_enviada']=='si' ? '✓' : '○'?>
            </td>
            <td style="font-size:10px;white-space:nowrap" title="Clases: <?=$a['clases_conversacion']?> | Horas plat: <?=$a['horas_plataforma']?> | Duración sesión: <?=$a['duracion_sesion']?>min">
                <?=$a['clases_conversacion'] ?: '-'?>c / <?=$a['horas_plataforma'] ?: '-'?>h
            </td>
            <td style="font-size:10px" title="Completados: <?=$a['temas_completados']?> | Asignados: <?=$a['temas_asignados']?>">
                <?php 
                $t_comp = $a['temas_completados'] ? count(explode(',', $a['temas_completados'])) : 0;
                $t_asig = $a['temas_asignados'] ? count(explode(',', $a['temas_asignados'])) : 0;
                ?>
                <?=$t_comp?>/<?=$t_asig?>
            </td>
            <td>
                <span class="badge badge-<?=$a['estado']=='activo'?'success':($a['estado']=='baja'?'danger':'warning')?>" style="font-size:10px"><?=$a['estado']?></span>
                <?php if($a['estado']=='baja' && $a['fecha_baja']): ?>
                <br><small style="color:#721c24;font-size:9px"><?=date('d/m', strtotime($a['fecha_baja']))?></small>
                <?php endif; ?>
            </td>
            <td style="max-width:100px">
                <?php if($a['notas']): ?>
                <span class="material-icons" style="font-size:14px;color:#008ba3;cursor:pointer" title="<?=htmlspecialchars($a['notas'])?>" onclick="alert('<?=addslashes(htmlspecialchars($a['notas']))?>')">comment</span>
                <?php else: ?>
                <span style="color:#ccc">-</span>
                <?php endif; ?>
            </td>
            <td>
                <button class="btn btn-sm btn-secondary" onclick='editAlumno(<?=json_encode($a)?>)'>
                    <span class="material-icons" style="font-size:14px">edit</span>
                </button>
                <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este alumno?')">
                    <input type="hidden" name="delete_alumno" value="1">
                    <input type="hidden" name="id" value="<?=$a['id']?>">
                    <input type="hidden" name="ficha_id" value="<?=$ficha_id?>">
                    <button class="btn btn-sm btn-danger"><span class="material-icons" style="font-size:14px">delete</span></button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <span class="material-icons">people_outline</span>
        <?php if($filtro_busqueda || $filtro_idioma || $filtro_estado || $filtro_grupo): ?>
        <p>No hay alumnos que coincidan con los filtros</p>
        <a href="?s=alumnos&ficha=<?=$ficha_id?>" class="btn btn-secondary" style="margin-top:10px">Limpiar filtros</a>
        <?php else: ?>
        <p>No hay alumnos añadidos todavía</p>
        <button class="btn btn-primary" onclick="abrirModalAlumno()" style="margin-top:10px">
            Añadir primer alumno
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Añadir/Editar Alumno -->
<div id="modal-alumno" class="modal">
    <div class="modal-content" style="max-width:700px">
        <div class="modal-header">
            <h3 id="modal-alumno-title">Añadir Alumno</h3>
            <span class="material-icons close-modal" onclick="this.closest('.modal').classList.remove('active')">close</span>
        </div>
        <form method="POST" id="form-alumno">
            <input type="hidden" name="add_alumno" value="1" id="alumno-action">
            <input type="hidden" name="ficha_id" value="<?=$ficha_id?>">
            <input type="hidden" name="id" id="alumno-id">
            <input type="hidden" name="moodle_userid" id="alumno-moodle-id">
            
            <div class="form-section">
                <h4><span class="material-icons">person</span> Datos del Alumno</h4>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="alumno_email" id="alumno-email" required onblur="buscarEmail(this.value)">
                    <div id="email-status" class="email-status"></div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="alumno_nombre" id="alumno-nombre">
                    </div>
                    <div class="form-group">
                        <label>Apellidos</label>
                        <input type="text" name="alumno_apellidos" id="alumno-apellidos">
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4><span class="material-icons">school</span> Configuración Formación</h4>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Idioma</label>
                        <select name="idioma" id="alumno-idioma">
                            <?php foreach($idiomas_ficha as $idioma_opt): ?>
                            <option value="<?=htmlspecialchars($idioma_opt)?>"><?=htmlspecialchars($idioma_opt)?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Grupo</label>
                        <select name="grupo_id" id="alumno-grupo">
                            <option value="">Sin asignar</option>
                            <?php foreach($grupos_array as $gf): ?>
                            <option value="<?=$gf['id']?>"><?=$gf['nivel']?> - <?=htmlspecialchars($gf['nombre'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nivel Asignado</label>
                        <select name="nivel_asignado" id="alumno-nivel">
                            <option value="">-</option>
                            <option value="A1">A1</option>
                            <option value="A2">A2</option>
                            <option value="B1">B1</option>
                            <option value="B1.2">B1.2</option>
                            <option value="B2">B2</option>
                            <option value="C1">C1</option>
                            <option value="C2">C2</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" id="alumno-fecha-inicio" value="<?=$ficha['fecha_inicio']?>">
                    </div>
                    <div class="form-group">
                        <label>Fecha Fin</label>
                        <input type="date" name="fecha_fin" id="alumno-fecha-fin" value="<?=$ficha['fecha_fin']?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Clases Conversación</label>
                        <input type="number" name="clases_conversacion" id="alumno-clases-conv" value="<?=$ficha['clases_conversacion']?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Duración Sesión (min)</label>
                        <select name="duracion_sesion" id="alumno-duracion-sesion">
                            <option value="">-</option>
                            <option value="30" <?=($ficha['duracion_clase_min']??'')==30?'selected':''?>>30 min</option>
                            <option value="45" <?=($ficha['duracion_clase_min']??'')==45?'selected':''?>>45 min</option>
                            <option value="60" <?=($ficha['duracion_clase_min']??'')==60?'selected':''?>>60 min</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Horas Plataforma</label>
                        <input type="number" name="horas_plataforma" id="alumno-horas-plat" value="<?=$ficha['horas_plataforma']?>" min="0" step="0.5">
                    </div>
                    <div class="form-group">
                        <label>Duración Total (h)</label>
                        <input type="number" name="duracion_total_curso" id="alumno-duracion-total" value="<?=$ficha['duracion_total_curso']?>" min="0">
                    </div>
                </div>
            </div>
            
            <div class="form-section" id="alumno-extra-fields" style="display:none">
                <h4><span class="material-icons">checklist</span> Control y Seguimiento</h4>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Alta Plataforma</label>
                        <select name="alta_plataforma" id="alumno-alta-plataforma">
                            <option value="pendiente">Pendiente</option>
                            <option value="completada">Completada</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Curso Montado</label>
                        <select name="curso_montado" id="alumno-curso-montado">
                            <option value="no">No</option>
                            <option value="si">Sí</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nivel Registrado</label>
                        <select name="nivel_registrado" id="alumno-nivel-registrado">
                            <option value="no">No</option>
                            <option value="si">Sí</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Estado Alumno</label>
                        <select name="estado" id="alumno-estado">
                            <option value="pendiente">Pendiente</option>
                            <option value="activo">Activo</option>
                            <option value="baja">Baja</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Prueba Nivel Enviada</label>
                        <select name="prueba_nivel_enviada" id="alumno-prueba-enviada">
                            <option value="no">No</option>
                            <option value="si">Sí</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Resultado Prueba</label>
                        <select name="prueba_nivel_resultado" id="alumno-prueba-resultado">
                            <option value="">-</option>
                            <option value="A1">A1</option>
                            <option value="A2">A2</option>
                            <option value="B1">B1</option>
                            <option value="B1.2">B1.2</option>
                            <option value="B2">B2</option>
                            <option value="C1">C1</option>
                            <option value="C2">C2</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email Bienvenida</label>
                        <select name="envio_bienvenida" id="alumno-bienvenida">
                            <option value="pendiente">Pendiente</option>
                            <option value="enviado">Enviado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notif. Inicio Curso</label>
                        <select name="notif_inicio_enviada" id="alumno-notif-inicio">
                            <option value="no">No</option>
                            <option value="si">Sí</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-section" id="alumno-temas-fields" style="display:none">
                <h4><span class="material-icons">library_books</span> Temas Plataforma</h4>
                
                <div class="form-group">
                    <label>Temas completados (edición anterior)</label>
                    <div class="temas-grid" id="temas-completados-grid">
                        <?php for($i=1; $i<=20; $i++): ?>
                        <label class="tema-check"><input type="checkbox" name="temas_completados[]" value="<?=$i?>"> <?=$i?></label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Temas asignados (edición actual)</label>
                    <div class="temas-grid" id="temas-asignados-grid">
                        <?php for($i=1; $i<=20; $i++): ?>
                        <label class="tema-check"><input type="checkbox" name="temas_asignados[]" value="<?=$i?>"> <?=$i?></label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea name="temas_observaciones" id="alumno-temas-obs" rows="2" placeholder="Notas adicionales sobre temas..."></textarea>
                </div>
            </div>
            
            <div class="form-section" id="alumno-notas-fields" style="display:none">
                <h4><span class="material-icons">notes</span> Notas y Apuntes</h4>
                <div class="form-group">
                    <label>Notas del alumno</label>
                    <textarea name="notas" id="alumno-notas" rows="4" placeholder="Apuntes generales, comentarios de RRHH, seguimiento..."></textarea>
                    <small style="color:#666">Aquí puedes anotar cualquier información relevante del alumno</small>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px" id="alumno-submit-btn">Añadir Alumno</button>
        </form>
    </div>
</div>

<style>
.temas-grid { display:grid; grid-template-columns:repeat(10,1fr); gap:5px; margin-top:5px; }
.tema-check { display:flex; align-items:center; gap:3px; font-size:12px; padding:4px 6px; background:#f5f5f5; border-radius:4px; cursor:pointer; }
.tema-check:hover { background:#e0e0e0; }
.tema-check input { margin:0; }
.tema-check input:checked + span, .tema-check:has(input:checked) { background:#008ba3; color:white; }
@media(max-width:600px) { .temas-grid { grid-template-columns:repeat(5,1fr); } }
</style>

<script>
// Funciones de selección masiva
function toggleSelectAll(el) {
    var checks = document.querySelectorAll('.alumno-check');
    checks.forEach(c => c.checked = el.checked);
    updateSelection();
}

function updateSelection() {
    var checks = document.querySelectorAll('.alumno-check:checked');
    var count = checks.length;
    var barra = document.getElementById('barra-masiva');
    var container = document.getElementById('ids-container');
    
    if (count > 0) {
        barra.style.display = 'flex';
        document.getElementById('count-seleccionados').textContent = count;
        container.innerHTML = '';
        checks.forEach(c => {
            container.innerHTML += '<input type="hidden" name="alumnos_ids[]" value="'+c.value+'">';
        });
    } else {
        barra.style.display = 'none';
    }
}

function deselectAll() {
    document.getElementById('select-all').checked = false;
    document.querySelectorAll('.alumno-check').forEach(c => c.checked = false);
    updateSelection();
}

function abrirModalAlumno() {
    document.getElementById('form-alumno').reset();
    document.getElementById('modal-alumno-title').textContent = 'Añadir Alumno';
    document.getElementById('alumno-action').name = 'add_alumno';
    document.getElementById('alumno-id').value = '';
    document.getElementById('alumno-moodle-id').value = '';
    document.getElementById('alumno-email').readOnly = false;
    document.getElementById('alumno-fecha-inicio').value = '<?=$ficha['fecha_inicio']?>';
    document.getElementById('alumno-fecha-fin').value = '<?=$ficha['fecha_fin']?>';
    document.getElementById('alumno-extra-fields').style.display = 'none';
    document.getElementById('alumno-temas-fields').style.display = 'none';
    document.getElementById('alumno-notas-fields').style.display = 'none';
    document.getElementById('alumno-submit-btn').textContent = 'Añadir Alumno';
    document.getElementById('email-status').className = 'email-status';
    document.getElementById('email-status').textContent = '';
    // Limpiar checkboxes de temas
    document.querySelectorAll('#temas-completados-grid input, #temas-asignados-grid input').forEach(c => c.checked = false);
    document.getElementById('modal-alumno').classList.add('active');
}

function buscarEmail(email) {
    if (!email || email.length < 5) return;
    
    var statusEl = document.getElementById('email-status');
    statusEl.className = 'email-status loading';
    statusEl.textContent = 'Buscando...';
    
    fetch('operaciones.php?ajax=buscar_email&email=' + encodeURIComponent(email))
        .then(response => response.json())
        .then(data => {
            if (data.found) {
                statusEl.className = 'email-status found';
                var source = data.source === 'moodle' ? '✓ Usuario Moodle (ID: ' + data.moodle_id + ')' : '✓ Alumno en otra ficha';
                statusEl.innerHTML = source + ' - Datos autocompletados';
                
                document.getElementById('alumno-nombre').value = data.firstname || '';
                document.getElementById('alumno-apellidos').value = data.lastname || '';
                if (data.moodle_id) {
                    document.getElementById('alumno-moodle-id').value = data.moodle_id;
                }
            } else {
                statusEl.className = 'email-status not-found';
                statusEl.textContent = 'Nuevo alumno - Completa los datos manualmente';
            }
        })
        .catch(err => {
            statusEl.className = 'email-status';
            statusEl.textContent = '';
        });
}

function editAlumno(a) {
    document.getElementById('modal-alumno-title').textContent = 'Editar Alumno';
    document.getElementById('alumno-action').name = 'update_alumno';
    document.getElementById('alumno-id').value = a.id;
    document.getElementById('alumno-moodle-id').value = a.moodle_userid || '';
    document.getElementById('alumno-nombre').value = a.alumno_nombre || '';
    document.getElementById('alumno-apellidos').value = a.alumno_apellidos || '';
    document.getElementById('alumno-idioma').value = a.idioma || 'Inglés';
    document.getElementById('alumno-email').value = a.alumno_email;
    document.getElementById('alumno-email').readOnly = true;
    document.getElementById('alumno-grupo').value = a.grupo_id || '';
    document.getElementById('alumno-nivel').value = a.nivel_asignado || '';
    document.getElementById('alumno-fecha-inicio').value = a.fecha_inicio || '';
    document.getElementById('alumno-fecha-fin').value = a.fecha_fin || '';
    document.getElementById('alumno-estado').value = a.estado || 'pendiente';
    document.getElementById('alumno-bienvenida').value = a.envio_bienvenida || 'pendiente';
    
    // Campos de formación
    document.getElementById('alumno-clases-conv').value = a.clases_conversacion || '';
    document.getElementById('alumno-duracion-sesion').value = a.duracion_sesion || '';
    document.getElementById('alumno-horas-plat').value = a.horas_plataforma || '';
    document.getElementById('alumno-duracion-total').value = a.duracion_total_curso || '';
    
    // Nuevos campos de seguimiento
    document.getElementById('alumno-alta-plataforma').value = a.alta_plataforma || 'pendiente';
    document.getElementById('alumno-curso-montado').value = a.curso_montado || 'no';
    document.getElementById('alumno-nivel-registrado').value = a.nivel_registrado || 'no';
    document.getElementById('alumno-prueba-enviada').value = a.prueba_nivel_enviada || 'no';
    document.getElementById('alumno-prueba-resultado').value = a.prueba_nivel_resultado || '';
    document.getElementById('alumno-notif-inicio').value = a.notif_inicio_enviada || 'no';
    
    // Cargar temas completados
    document.querySelectorAll('#temas-completados-grid input').forEach(c => c.checked = false);
    if (a.temas_completados) {
        a.temas_completados.split(',').forEach(t => {
            var cb = document.querySelector('#temas-completados-grid input[value="'+t.trim()+'"]');
            if (cb) cb.checked = true;
        });
    }
    
    // Cargar temas asignados
    document.querySelectorAll('#temas-asignados-grid input').forEach(c => c.checked = false);
    if (a.temas_asignados) {
        a.temas_asignados.split(',').forEach(t => {
            var cb = document.querySelector('#temas-asignados-grid input[value="'+t.trim()+'"]');
            if (cb) cb.checked = true;
        });
    }
    
    document.getElementById('alumno-temas-obs').value = a.temas_observaciones || '';
    
    // Cargar notas
    document.getElementById('alumno-notas').value = a.notas || '';
    
    document.getElementById('alumno-extra-fields').style.display = 'block';
    document.getElementById('alumno-temas-fields').style.display = 'block';
    document.getElementById('alumno-notas-fields').style.display = 'block';
    document.getElementById('alumno-submit-btn').textContent = 'Actualizar Alumno';
    document.getElementById('email-status').className = 'email-status';
    document.getElementById('email-status').textContent = '';
    document.getElementById('modal-alumno').classList.add('active');
}
</script>

<!-- ============================================ -->
<!-- SECCIÓN: FUNDAE -->
<!-- ============================================ -->
<?php elseif($seccion == 'fundae' && $ficha): ?>

<?php
$fundae = $conn->query("SELECT * FROM own_operaciones_fundae WHERE ficha_id = $ficha_id")->fetch_assoc();
?>

<div class="header">
    <h1>
        <span class="material-icons">description</span> FUNDAE - Alta
        <small>| <?=htmlspecialchars($ficha['empresa_nombre'])?> - <?=htmlspecialchars($ficha['codigo_edicion'])?></small>
    </h1>
</div>

<div class="card">
    <form method="POST">
        <input type="hidden" name="save_fundae" value="1">
        <input type="hidden" name="ficha_id" value="<?=$ficha_id?>">
        
        <h3><span class="material-icons">business</span> Datos de la Empresa</h3>
        
        <div class="form-row">
            <div class="form-group">
                <label>CIF Empresa *</label>
                <input type="text" name="cif_empresa" value="<?=htmlspecialchars($fundae['cif_empresa'] ?? '')?>" required>
            </div>
            <div class="form-group">
                <label>Razón Social</label>
                <input type="text" name="razon_social" value="<?=htmlspecialchars($fundae['razon_social'] ?? '')?>">
            </div>
        </div>
        
        <h3 style="margin-top:25px"><span class="material-icons">assignment</span> Acción Formativa</h3>
        
        <div class="form-row">
            <div class="form-group">
                <label>Nº Acción (asignado por FUNDAE)</label>
                <input type="text" name="numero_accion" value="<?=htmlspecialchars($fundae['numero_accion'] ?? '')?>" placeholder="Se asigna tras el alta">
            </div>
            <div class="form-group">
                <label>Denominación de la Acción *</label>
                <input type="text" name="denominacion_accion" value="<?=htmlspecialchars($fundae['denominacion_accion'] ?? '')?>" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Modalidad</label>
                <select name="modalidad_fundae">
                    <option value="teleformacion" <?=($fundae['modalidad_fundae'] ?? '')=='teleformacion'?'selected':''?>>Teleformación</option>
                    <option value="presencial" <?=($fundae['modalidad_fundae'] ?? '')=='presencial'?'selected':''?>>Presencial</option>
                    <option value="mixta" <?=($fundae['modalidad_fundae'] ?? '')=='mixta'?'selected':''?>>Mixta</option>
                </select>
            </div>
            <div class="form-group">
                <label>Horas Totales *</label>
                <input type="number" name="horas_totales" value="<?=$fundae['horas_totales'] ?? ($ficha['horas_plataforma'] + $ficha['clases_conversacion'])?>" required>
                <small>Plataforma (<?=$ficha['horas_plataforma']?>h) + Conversación (<?=$ficha['clases_conversacion']?> clases)</small>
            </div>
            <div class="form-group">
                <label>Participantes Inicio *</label>
                <input type="number" name="participantes_inicio" value="<?=$fundae['participantes_inicio'] ?? 0?>" required>
            </div>
        </div>
        
        <h3 style="margin-top:25px"><span class="material-icons">checklist</span> Checklist Documentación Alta</h3>
        
        <ul class="checklist">
            <li>
                <input type="checkbox" name="check_comunicacion_rlt" id="ch1" <?=($fundae['check_comunicacion_rlt'] ?? 0)?'checked':''?>>
                <label for="ch1">Comunicación a RLT firmada</label>
            </li>
            <li>
                <input type="checkbox" name="check_listado_participantes" id="ch2" <?=($fundae['check_listado_participantes'] ?? 0)?'checked':''?>>
                <label for="ch2">Listado de participantes completo</label>
            </li>
            <li>
                <input type="checkbox" name="check_coste_hora" id="ch3" <?=($fundae['check_coste_hora'] ?? 0)?'checked':''?>>
                <label for="ch3">Coste/hora verificado</label>
            </li>
            <li>
                <input type="checkbox" name="check_credito_verificado" id="ch4" <?=($fundae['check_credito_verificado'] ?? 0)?'checked':''?>>
                <label for="ch4">Crédito disponible verificado</label>
            </li>
        </ul>
        
        <div style="margin-top:20px;display:flex;gap:10px">
            <button type="submit" class="btn btn-primary"><span class="material-icons">save</span> Guardar Datos</button>
            <?php if($fundae && $fundae['estado_alta'] == 'pendiente'): ?>
            <button type="submit" name="completar_alta_fundae" value="1" class="btn btn-success" onclick="return confirm('¿Marcar el alta como completada?')">
                <span class="material-icons">check_circle</span> Marcar Alta Completada
            </button>
            <?php elseif($fundae && $fundae['estado_alta'] == 'completada'): ?>
            <span class="badge badge-success" style="padding:10px 15px;font-size:14px">
                <span class="material-icons" style="vertical-align:middle;margin-right:5px">verified</span>
                Alta completada el <?=date('d/m/Y', strtotime($fundae['fecha_alta']))?>
            </span>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ============================================ -->
<!-- SECCIÓN: FUNDAE CIERRE -->
<!-- ============================================ -->
<?php elseif($seccion == 'fundae_cierre' && $ficha): ?>

<div class="header">
    <h1>
        <span class="material-icons">fact_check</span> FUNDAE - Cierre
        <small>| <?=htmlspecialchars($ficha['empresa_nombre'])?> - <?=htmlspecialchars($ficha['codigo_edicion'])?></small>
    </h1>
</div>

<div class="card">
    <div class="empty-state">
        <span class="material-icons">construction</span>
        <p>Sección en desarrollo</p>
        <p style="font-size:13px;margin-top:10px">El cierre FUNDAE estará disponible cuando la formación esté próxima a finalizar.</p>
    </div>
</div>

<?php else: ?>

<div class="header">
    <h1><span class="material-icons">info</span> Selecciona una ficha</h1>
</div>

<div class="card">
    <p>Para gestionar grupos, alumnos o datos FUNDAE, primero selecciona una ficha de formación desde el listado.</p>
    <a href="?s=fichas" class="btn btn-primary" style="margin-top:15px">
        <span class="material-icons">folder_open</span> Ver Fichas
    </a>
</div>

<?php endif; ?>

</main>

</body>
</html>
