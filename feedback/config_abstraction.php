<?php
/**
 * Capa de Abstracción - Sistema Feedback NPS
 * ==========================================
 * 
 * Este archivo centraliza las dependencias externas para facilitar
 * la migración a la plataforma propietaria tuSpeaking.
 * 
 * MIGRACIÓN: Cuando se migre al nuevo sistema, solo hay que:
 * 1. Cambiar las queries de este archivo
 * 2. Actualizar las tablas de referencia
 * 3. El resto del código funcionará igual
 * 
 * Fecha: Enero 2026
 * Destino: 46.231.126.134 (plataforma propietaria)
 */

// ============================================
// CONFIGURACIÓN DE ENTORNO
// ============================================
define('FEEDBACK_ENV', 'moodle'); // Cambiar a 'core' en nueva plataforma

// ============================================
// TABLAS - MAPEO ACTUAL vs FUTURO
// ============================================
$TABLES = [
    'moodle' => [
        'usuarios' => 'mdl_user',
        'clases' => 'mdl_i3code_acuityZoom',
        'profesores_map' => 'teacher_zoom_map',
    ],
    'core' => [
        'usuarios' => 'core_alumnos',
        'clases' => 'core_clases', 
        'profesores_map' => 'core_profesores',
    ]
];

// ============================================
// FUNCIONES DE ABSTRACCIÓN
// ============================================

/**
 * Obtener datos de alumno por email
 */
function fb_get_alumno($conn, $email) {
    if (FEEDBACK_ENV == 'moodle') {
        $sql = "SELECT id, firstname, lastname, email FROM mdl_user WHERE email = ?";
    } else {
        $sql = "SELECT id, nombre as firstname, apellidos as lastname, email FROM core_alumnos WHERE email = ?";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Obtener datos de profesor por ID
 */
function fb_get_profesor($conn, $id) {
    if (FEEDBACK_ENV == 'moodle') {
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email 
                FROM mdl_user u 
                INNER JOIN teacher_zoom_map t ON u.id = t.moodle_user_id 
                WHERE t.id = ?";
    } else {
        $sql = "SELECT id, nombre as firstname, apellidos as lastname, email FROM core_profesores WHERE id = ?";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Obtener clases terminadas para envío de feedback
 * @param int $minutos_desde - minutos desde que terminó
 * @param int $minutos_hasta - minutos máximo desde que terminó
 */
function fb_get_clases_para_feedback($conn, $minutos_desde = 30, $minutos_hasta = 90) {
    if (FEEDBACK_ENV == 'moodle') {
        // Acuity/Zoom
        $sql = "SELECT 
                    a.id as clase_id,
                    a.acuity_email as alumno_email,
                    a.acuity_firstname as alumno_nombre,
                    a.acuity_calendar as profesor_nombre,
                    a.acuity_datetime as fecha_clase,
                    a.acuity_type as tipo_clase,
                    u.id as alumno_id,
                    t.id as profesor_id
                FROM mdl_i3code_acuityZoom a
                LEFT JOIN mdl_user u ON LOWER(u.email) = LOWER(a.acuity_email)
                LEFT JOIN teacher_zoom_map t ON LOWER(t.teacher_name) = LOWER(a.acuity_calendar)
                WHERE a.acuity_datetime >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                AND a.acuity_datetime <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                AND a.acuity_email IS NOT NULL
                AND a.acuity_email != ''
                AND a.zoom_status != 'cancelled'";
    } else {
        // Sistema Core
        $sql = "SELECT 
                    c.id as clase_id,
                    a.email as alumno_email,
                    a.nombre as alumno_nombre,
                    p.nombre as profesor_nombre,
                    c.fecha_hora as fecha_clase,
                    c.tipo as tipo_clase,
                    a.id as alumno_id,
                    p.id as profesor_id
                FROM core_clases c
                INNER JOIN core_alumnos a ON c.alumno_id = a.id
                INNER JOIN core_profesores p ON c.profesor_id = p.id
                WHERE c.fecha_hora >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                AND c.fecha_hora <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                AND c.estado = 'completada'";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $minutos_hasta, $minutos_desde);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Obtener estadísticas de profesor
 */
function fb_get_profesor_stats($conn, $profesor_nombre, $desde, $hasta) {
    // Esta query es igual en ambos entornos (usa tablas propias de feedback)
    $sql = "SELECT 
                COUNT(*) as total,
                AVG(valoracion) as media,
                SUM(CASE WHEN valoracion >= 9 THEN 1 ELSE 0 END) as excelentes,
                SUM(CASE WHEN valoracion <= 3 THEN 1 ELSE 0 END) as alertas
            FROM own_feedback_nps 
            WHERE profesor = ?
            AND submission_date >= ?
            AND submission_date <= ?";
    $stmt = $conn->prepare($sql);
    $hasta_full = $hasta . ' 23:59:59';
    $stmt->bind_param("sss", $profesor_nombre, $desde, $hasta_full);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// ============================================
// DOCUMENTACIÓN DE MIGRACIÓN
// ============================================
/*
PASOS PARA MIGRAR A PLATAFORMA CORE:

1. TABLAS PROPIAS (copiar tal cual):
   - own_feedback_nps
   - own_feedback_envios
   - own_feedback_config
   - own_feedback_optout
   - own_feedback_logs
   - own_feedback_teams_grupos
   - own_feedback_teams_alumnos

2. CAMBIAR EN ESTE ARCHIVO:
   - FEEDBACK_ENV = 'core'
   - Adaptar queries si estructura de tablas core es diferente

3. ARCHIVOS A COPIAR:
   - /feedback/*.php (todos)
   - /brand/admin-panel/* (sistema de diseño)

4. CRONS:
   - Reconfigurar en nuevo servidor
   - Mismos horarios

5. DEPENDENCIAS:
   - PHP 7.4+
   - MySQL 5.7+
   - Material Icons (CDN)
*/
?>
