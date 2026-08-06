<?php
/**
 * Script para descargar datos de Zoom y guardarlos en BD
 * Versión corregida
 * 
 * Ejecución: /usr/local/bin/php73 -d disable_functions= -d "memory_limit=4096M" i3code_download_zoomdata.php
 */

define('CLI_SCRIPT', true);
require(__DIR__.'/../../config.php');
require_once($CFG->dirroot.'/secrets.php');   // Credenciales Acuity externalizadas (fuera de git)
require_once($CFG->libdir.'/clilib.php');
require_once(__DIR__.'/../../blocks/completion_progress/lib.php');

global $DB;

// ============================================
// CONFIGURACIÓN Y FUNCIONES AUXILIARES
// ============================================

// Ruta del log — /tmp del contenedor (ING-3, fix 04-ago-2026)
// El volumen de código es read-only, no se puede escribir en __DIR__.
// /tmp del contenedor es writable y persiste mientras corre el contenedor.
$log_path = "/tmp/i3code_download_zoomdata_" . date("Ymd") . ".log";

// Logger
if (!function_exists("cli_i3code_log")) {
    function cli_i3code_log($texto) {
        global $log_path;
        @file_put_contents($log_path, "\n" . date("Y-m-d H:i:s") . " - " . $texto, FILE_APPEND);
    }
}

// Normalizar registro antes de guardar
function i3_fix_record_before_save($rec) {
    foreach (['zoom_starttime','zoom_endtime'] as $k) {
        if (!isset($rec->$k) || $rec->$k === '' || $rec->$k === '0000-00-00 00:00:00') {
            $rec->$k = null;
        }
    }
    foreach (['zoom_participants','zoom_duration','zoom_duration_total'] as $k) {
        if (!isset($rec->$k) || $rec->$k === '') {
            $rec->$k = null;
        }
    }
    foreach (['zoom_topic','zoom_username','zoom_email','zoom_dept'] as $k) {
        if (!isset($rec->$k) || $rec->$k === '') {
            $rec->$k = null;
        }
    }
    return $rec;
}

// ============================================
// FUNCIONES API
// ============================================

function getAcuityAPI($acuityid) {
    $userID = ACUITY_USER_ID;
    $apiKey = ACUITY_API_KEY;
    $baseUrl = "https://acuityscheduling.com/api/v1/appointments/";

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $baseUrl.$acuityid);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERPWD, "$userID:$apiKey");
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($curl);
    if (curl_error($curl)) {
        $response = curl_error($curl);
    }
    curl_close($curl);
    return $response;
}

function generateZoomTokenOAuth() {
    // Post-migracion Hetzner (27-jul-2026): las creds del S2S OAuth se leen de la
    // config del plugin Zoom de Moodle (get_config), NO del fichero de Dina
    // '/home/aulatuspeaking/secrets/zoom_s2s.env' que ya no existe en el Hetzner.
    $zoom_config = get_config('zoom');

    if (empty($zoom_config->accountid) || empty($zoom_config->clientid) || empty($zoom_config->clientsecret)) {
        throw new Exception("Zoom S2S config missing keys (get_config 'zoom')");
    }

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . $zoom_config->accountid,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Basic '. base64_encode($zoom_config->clientid.":".$zoom_config->clientsecret)
        ),
    ));

    $response = curl_exec($curl);
    if(curl_error($curl)){
        throw new Exception("Curl error: " . curl_error($curl));
    }
    curl_close($curl);

    $zoom_token = json_decode($response);
    if (empty($zoom_token) || empty($zoom_token->access_token)) {
        throw new Exception("Zoom token missing. Response: " . substr($response, 0, 500));
    }

    return $zoom_token->access_token;
}

function getZoomAPI(string $meetingID) : string {
    $token = generateZoomTokenOAuth();

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.zoom.us/v2/past_meetings/".$meetingID,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "authorization: Bearer $token",
            "content-type: application/json"
        ),
    ));

    $response = curl_exec($curl);
    if(curl_error($curl)){
        $response = curl_error($curl);
    }
    curl_close($curl);
    return $response;
}

function getZoomAPIParticipants(?string $meetingID) : string {
    if (empty($meetingID)) { return "{}"; }
    $token = generateZoomTokenOAuth();

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.zoom.us/v2/past_meetings/".$meetingID."/participants",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "authorization: Bearer $token",
            "content-type: application/json"
        ),
    ));

    $response = curl_exec($curl);
    if(curl_error($curl)){
        $response = curl_error($curl);
    }
    curl_close($curl);
    return $response;
}

// ============================================
// SCRIPT PRINCIPAL
// ============================================

try {
    cli_i3code_log("========== Inicio descarga de datos ==========");
    
    date_default_timezone_set('Europe/Madrid');
    $current_datetime = date("Y-m-d\TH:i:s.000\Z", time());
    
    // Obtener lista de Acuity
    $sqlGetAllAcuity = "
        SELECT 
            acuity.id,
            acuity.acuityid,
            acuity.courseid,
            acuity.studentid,
            acuity.teacherid,
            i3code.zoom_meetingid
        FROM own_acuity acuity
        LEFT JOIN mdl_i3code_acuityZoom i3code
            ON acuity.acuityid = i3code.acuityid
        WHERE acuity.lastmodified >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
          AND i3code.zoom_starttime IS NULL
          AND (
                i3code.acuity_location != 'The Zoom meeting was cancelled.'
                OR i3code.acuity_location IS NULL
              )
        ORDER BY acuity.id DESC
    ";

    $acuityList = $DB->get_records_sql($sqlGetAllAcuity);
    cli_i3code_log("Total Acuity encontrados: " . count($acuityList));

    // Procesar cada Acuity
    foreach ($acuityList as $acuityItem) {
        cli_i3code_log("Procesando Acuity ID: " . $acuityItem->acuityid);
        
        $i3code_acuityZoom = new \stdClass();
        $i3code_acuityZoom->acuityid = $acuityItem->acuityid;
        $i3code_acuityZoom->courseid = $acuityItem->courseid;
        $i3code_acuityZoom->studentid = $acuityItem->studentid;
        $i3code_acuityZoom->teacherid = $acuityItem->teacherid;

        $llamar_a_zoom = false;

        // Llamar a API de Acuity
        $acuityResponse = getAcuityAPI($acuityItem->acuityid);
        $acuityResponseItem = json_decode($acuityResponse);

        if (!$acuityResponseItem) {
            cli_i3code_log("Error: respuesta inválida de Acuity para ID " . $acuityItem->acuityid);
            continue;
        }

        $acuityResponseItemDatetime = date($acuityResponseItem->datetime ?? '');

        // Guardar información de Acuity
        $i3code_acuityZoom->acuity_firstname = $acuityResponseItem->firstName ?? null;
        $i3code_acuityZoom->acuity_lastname = $acuityResponseItem->lastName ?? null;
        $i3code_acuityZoom->acuity_phone = $acuityResponseItem->phone ?? null;
        $i3code_acuityZoom->acuity_email = $acuityResponseItem->email ?? null;
        $i3code_acuityZoom->acuity_datetime = $acuityResponseItem->datetime ?? null;
        $i3code_acuityZoom->acuity_starttime = $acuityResponseItem->time ?? null;
        $i3code_acuityZoom->acuity_endtime = $acuityResponseItem->endTime ?? null;
        $i3code_acuityZoom->acuity_type = $acuityResponseItem->type ?? null;
        $i3code_acuityZoom->acuity_duration = $acuityResponseItem->duration ?? null;
        $i3code_acuityZoom->acuity_location = $acuityResponseItem->location ?? null;

        // Obtener meetingID de Zoom
        $zoomMeetingID = null;
        if (!empty($acuityResponseItem->location)) {
            if (preg_match('/Meeting ID:\s*([0-9]{9,11})/i', $acuityResponseItem->location, $m)) {
                $zoomMeetingID = $m[1];
            } else if (preg_match('~/j/([0-9]{9,11})~', $acuityResponseItem->location, $m)) {
                $zoomMeetingID = $m[1];
            }
        }
        $i3code_acuityZoom->zoom_meetingid = $zoomMeetingID;

        // Verificar si está para reprogramar
        $reschedule = false;
        if (!empty($acuityResponseItem->labels) && is_array($acuityResponseItem->labels)) {
            foreach ($acuityResponseItem->labels as $label) {
                if (!empty($label->name) && $label->name === "To reschedule") {
                    $reschedule = true;
                    break;
                }
            }
        }

        if ($reschedule) {
            $i3code_acuityZoom->acuity_location = "To reschedule";
            $i3code_acuityZoom->zoom_meetingid = null;
        }

        // Determinar si llamar a Zoom
        $llamar_a_zoom = $acuityResponseItemDatetime <= $current_datetime;

        // Llamar a API de Zoom si procede
        if ($llamar_a_zoom && $i3code_acuityZoom->zoom_meetingid != null) {
            cli_i3code_log("Llamando a Zoom para meeting ID: " . $i3code_acuityZoom->zoom_meetingid);
            
            try {
                $zoomResponse = getZoomAPI($i3code_acuityZoom->zoom_meetingid);
                $zoomResponseItem = json_decode($zoomResponse);

                if ($zoomResponseItem && !isset($zoomResponseItem->code)) {
                    // Guardar datos de Zoom
                    $i3code_acuityZoom->zoom_topic = $zoomResponseItem->topic ?? null;
                    $i3code_acuityZoom->zoom_username = $zoomResponseItem->user_name ?? null;
                    $i3code_acuityZoom->zoom_email = $zoomResponseItem->user_email ?? null;
                    $i3code_acuityZoom->zoom_participants = $zoomResponseItem->participants_count ?? null;
                    $i3code_acuityZoom->zoom_duration = isset($zoomResponseItem->duration) ? (int)$zoomResponseItem->duration : null;
                    $i3code_acuityZoom->zoom_duration_total = isset($zoomResponseItem->total_minutes) ? (int)$zoomResponseItem->total_minutes : null;
                    $i3code_acuityZoom->zoom_dept = $zoomResponseItem->dept ?? null;

                    // Procesar start_time
                    $st = $zoomResponseItem->start_time ?? null;
                    if (!empty($st)) {
                        try {
                            $dt = new DateTime($st);
                            $dt->setTimezone(new DateTimeZone('Europe/Madrid'));
                            $i3code_acuityZoom->zoom_starttime = $dt->format('Y-m-d H:i:s');
                        } catch (Exception $e) {
                            $i3code_acuityZoom->zoom_starttime = null;
                        }
                    } else {
                        $i3code_acuityZoom->zoom_starttime = null;
                    }

                    // Procesar end_time
                    $et = $zoomResponseItem->end_time ?? null;
                    if (!empty($et)) {
                        try {
                            $dt = new DateTime($et);
                            $dt->setTimezone(new DateTimeZone('Europe/Madrid'));
                            $i3code_acuityZoom->zoom_endtime = $dt->format('Y-m-d H:i:s');
                        } catch (Exception $e) {
                            $i3code_acuityZoom->zoom_endtime = null;
                        }
                    } else if (!empty($i3code_acuityZoom->zoom_starttime) && !empty($i3code_acuityZoom->zoom_duration)) {
                        $dt2 = new DateTime($i3code_acuityZoom->zoom_starttime, new DateTimeZone('Europe/Madrid'));
                        $dt2->modify('+' . (int)$i3code_acuityZoom->zoom_duration . ' minutes');
                        $i3code_acuityZoom->zoom_endtime = $dt2->format('Y-m-d H:i:s');
                    } else {
                        $i3code_acuityZoom->zoom_endtime = null;
                    }
                }
            } catch (Exception $e) {
                cli_i3code_log("Error al llamar Zoom API: " . $e->getMessage());
            }
        }

        // Guardar en base de datos
        try {
            $i3code_acuityZoom = i3_fix_record_before_save($i3code_acuityZoom);
            
            global $CFG;
            $tbl = $CFG->prefix . "i3code_acuityZoom";
            $existe = $DB->get_record_sql("SELECT * FROM $tbl WHERE acuityid = ?", [$acuityItem->acuityid]);

            if (!$existe) {
                $i3code_acuityZoom->id = $DB->insert_record('i3code_acuityZoom', $i3code_acuityZoom);
                cli_i3code_log("Insertado nuevo registro: " . $i3code_acuityZoom->id);
            } else {
                $i3code_acuityZoom->id = $existe->id;
                $DB->update_record('i3code_acuityZoom', $i3code_acuityZoom);
                cli_i3code_log("Actualizado registro: " . $i3code_acuityZoom->id);
            }
        } catch (Exception $e) {
            cli_i3code_log("Error guardando i3code_acuityZoom (acuityid {$acuityItem->acuityid}): ".$e->getMessage());
        }
    } // fin foreach acuityList

    // ============================================
    // PROCESAR PARTICIPANTES DE ZOOM
    // ============================================
    
    cli_i3code_log("Procesando participantes de Zoom...");
    
    $sqlGetAllZoomClases = "
        SELECT DISTINCT z.zoom_meetingid 
        FROM mdl_i3code_acuityZoom z
        LEFT JOIN mdl_i3code_acuityZoom_participants p ON z.zoom_meetingid = p.zoom_meetingid
        WHERE z.zoom_meetingid IS NOT NULL AND z.zoom_starttime IS NOT NULL AND p.id IS NULL AND z.zoom_starttime >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY z.zoom_starttime DESC LIMIT 500
    ";

    $zoomList = $DB->get_records_sql($sqlGetAllZoomClases);
    cli_i3code_log("Total meetings para procesar participantes: " . count($zoomList));

    foreach ($zoomList as $zoomItem) {
        try {
            $zoomResponse = getZoomAPIParticipants($zoomItem->zoom_meetingid);
            $zoomResponseItem = json_decode($zoomResponse);

            if (isset($zoomResponseItem->participants)) {
                foreach ($zoomResponseItem->participants as $participant) {
                    $i3code_acuityZoom_participant = new \stdClass();
                    $i3code_acuityZoom_participant->zoom_meetingid = $zoomItem->zoom_meetingid;
                    $i3code_acuityZoom_participant->zoom_userid = $participant->user_id ?? null;
                    $i3code_acuityZoom_participant->zoom_name = $participant->name ?? null;
                    $i3code_acuityZoom_participant->zoom_email = $participant->user_email ?? null;
                    $i3code_acuityZoom_participant->zoom_duration = $participant->duration ?? null;

                    $jointime = $participant->join_time ?? null;
                    if ($jointime) {
                        $i3code_acuityZoom_participant->zoom_jointime = str_replace(["T", "Z"], [" ", ""], $jointime);
                    }

                    $leavetime = $participant->leave_time ?? null;
                    if ($leavetime) {
                        $i3code_acuityZoom_participant->zoom_leavetime = str_replace(["T", "Z"], [" ", ""], $leavetime);
                    }

                    $DB->insert_record('i3code_acuityZoom_participants', $i3code_acuityZoom_participant);
                }
                cli_i3code_log("Guardados participantes para meeting: " . $zoomItem->zoom_meetingid);
            }
        } catch (\Throwable $e) {
            cli_i3code_log("Error guardando participantes Zoom (meetingid {$zoomItem->zoom_meetingid}): " . $e->getMessage());
        }
    }

    // ============================================
    // ACTUALIZAR CLASES COMPLETADAS
    // ============================================
    
    cli_i3code_log("Actualizando clases completadas...");
    
    $sqlUpdateClases = "
        UPDATE mdl_i3code_acuityZoom z 
        INNER JOIN (
            SELECT zoom_meetingid, sum(zoom_duration) as total_duration
            FROM mdl_i3code_acuityZoom_participants
            WHERE zoom_email NOT LIKE '%tuspeaking%'
            GROUP BY zoom_meetingid
            HAVING sum(zoom_duration) > 600
        ) m ON m.zoom_meetingid = z.zoom_meetingid
        SET z.zoom_clasecompletada = 1
        WHERE z.manual_override = 0
    ";

    $DB->execute($sqlUpdateClases);
    cli_i3code_log("Clases completadas actualizadas");

    // ============================================
    // GENERAR INFORME
    // ============================================
    
    cli_i3code_log("Generando informe...");
    
    $sqlGetInformeLineas = "
        SELECT DISTINCT 
            enrolments.id enrolmentid, 
            categories.id categoryid, 
            course.id courseid, 
            user.id userid, 
            enrolments.timestart fecha_inicio, 
            categories.name curso, 
            course.fullname nivel, 
            user.firstname nombre, 
            user.lastname apellido, 
            user.email email,
            IFNULL(clases_asistidas.total, 0) clases_completadas, 
            IFNULL(clases_no_asistidas.total, 0) clases_no_asistidas,
            (IFNULL(acuity_course.classnmbr, 0) - (IFNULL(clases_asistidas.total, 0) + IFNULL(clases_no_asistidas.total, 0))) clases_pendientes,
            IFNULL(acuity_course.classnmbr, 0) clases_total,
            ROUND(IFNULL(((IFNULL(clases_asistidas.total, 0) * 100) / IFNULL(acuity_course.classnmbr, 0)), 0), 2) porcentaje_clases,
            CASE
                WHEN lastaccess.UltimaConexion is null THEN 'No se ha conectado'
                ELSE lastaccess.UltimaConexion
            END ultima_conexion,
            IFNULL(participant_total_duration.total_duration, 0) tiempo_sesiones_segundos
        FROM mdl_course course 
        JOIN mdl_course_categories categories ON course.category = categories.id
        LEFT JOIN own_acuity_course acuity_course ON acuity_course.courseid = course.id
        JOIN mdl_enrol enrol ON enrol.courseid = course.id
        JOIN mdl_user_enrolments enrolments ON enrol.id = enrolments.enrolid
        JOIN mdl_user user ON enrolments.userid = user.id
        LEFT JOIN (
            SELECT userid, courseid, FROM_UNIXTIME(MAX(timecreated)) as UltimaConexion  
            FROM mdl_logstore_standard_log 
            WHERE action = 'viewed' AND target = 'course'
            GROUP BY userid, courseid
        ) lastaccess ON lastaccess.userid = user.id AND lastaccess.courseid = course.id
        LEFT JOIN (
            SELECT courseid, studentid, count(id) total 
            FROM mdl_i3code_acuityZoom
            WHERE zoom_clasecompletada = 1 
            GROUP BY courseid, studentid
        ) clases_asistidas ON clases_asistidas.studentid = user.id AND clases_asistidas.courseid = course.id
        LEFT JOIN (
            SELECT courseid, studentid, count(id) total 
            FROM mdl_i3code_acuityZoom
            WHERE zoom_clasecompletada = 0 AND zoom_meetingid IS NOT NULL
            GROUP BY courseid, studentid
        ) clases_no_asistidas ON clases_no_asistidas.studentid = user.id AND clases_no_asistidas.courseid = course.id
        LEFT JOIN (
            SELECT courseid, studentid, sum(p.zoom_duration) total_duration 
            FROM mdl_i3code_acuityZoom az 
            LEFT JOIN mdl_i3code_acuityZoom_participants p ON az.zoom_meetingid = p.zoom_meetingid
            WHERE p.zoom_email NOT LIKE '%tuspeaking%'
            GROUP BY courseid, studentid
        ) participant_total_duration ON participant_total_duration.studentid = user.id AND participant_total_duration.courseid = course.id
        ORDER BY categories.name
    ";

    $informeLineas = $DB->get_records_sql($sqlGetInformeLineas);
    
    $DB->execute('DELETE FROM mdl_i3code_acuityZoom_informe');
    
foreach ($informeLineas as $informeLinea) {
    try {
        // TEMPORALMENTE DESHABILITADO - muy pesado para 18k líneas
        // $informeLinea->tiempo_plataforma = get_students_dedication($informeLinea->userid, $informeLinea->courseid, $informeLinea->fecha_inicio);
        $informeLinea->tiempo_plataforma = 0;
        
        $informeLinea->tiempo_sesiones = format_dedication($informeLinea->tiempo_sesiones_segundos);
        if ($informeLinea->tiempo_sesiones == "Ninguno") {
            $informeLinea->tiempo_sesiones = 0;
        }

        // TEMPORALMENTE DESHABILITADO - funciones pesadas de completion_progress
        // $exclusions = block_completion_progress_exclusions($informeLinea->courseid, $informeLinea->userid);
        // $activities = block_completion_progress_get_activities($informeLinea->courseid);
        // $activities = block_completion_progress_filter_visibility($activities, $informeLinea->userid, $informeLinea->courseid, $exclusions);
       
        $porcentaje_plataforma = "0.00";
        
        /* COMENTADO - MUY PESADO PARA 18K LÍNEAS
        if (!empty($activities)) {
            $course = $DB->get_record('course', array('id' => $informeLinea->courseid));
            $submissions = block_completion_progress_student_submissions($informeLinea->courseid, $informeLinea->userid);
            $completions = block_completion_progress_completions($activities, $informeLinea->userid, $course, $submissions);
            $porcentaje_plataforma = block_completion_progress_percentage($activities, $completions);
            if ($porcentaje_plataforma == 0) {
                $porcentaje_plataforma = "0.00";
            }
        }
        */

        $informeLinea->porcentaje_plataforma = $porcentaje_plataforma;
        $informeLinea->porcentaje_total = $informeLinea->porcentaje_clases; // Solo basado en clases Zoom
        if ($informeLinea->porcentaje_total == 0) {
            $informeLinea->porcentaje_total = "0.00";
        }

        $DB->insert_record('i3code_acuityZoom_informe', $informeLinea);
    } catch (Exception $e) {
        cli_i3code_log(json_encode($informeLinea));
        cli_i3code_log("Error al guardar línea del informe: " . $e->getMessage());
    }
}
    cli_i3code_log("========== Fin descarga de datos ==========");
    
} catch (Exception $e) {
    cli_i3code_log("ERROR FATAL: " . $e->getMessage());
    cli_i3code_log("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

// ============================================
// FUNCIONES INFORME
// ============================================

function get_students_dedication($userid, $courseid, $mintime) {
    global $CFG, $DB;
    $threshold = 60 * MINSECS;
    $lastpingcredit = 15 * MINSECS;
    $where = 'courseid = :courseid AND userid = :userid AND timecreated >= :mintime';
    $params = array('courseid' => $courseid, 'userid' => $userid, 'mintime' => $mintime);
    $logs = get_log_records($where, $params);
    $data = array();
    $sessionid = 0;
    
    if ($logs) {
        $memlap = 0;
        $logs = array_values($logs);
        $logsize = count($logs);

        for ($i = 0; $i < $logsize; $i = $nexti) {
            $log = $logs[$i];
            $nexti = $i + 1;
            $lognext = false;
            
            if (isset($logs[$i + 1])) {
                list($lognext, $lap, $nexti) = get_next_log($logs, $i);
            } else {
                $lap = $lastpingcredit;
            }

            get_context_info($log);
            $sessionpunch = false;
            
            if ($lap > $threshold) {
                $lap = $lastpingcredit;
                if ($lognext && !is_login_event($lognext->action)) {
                    $sessionpunch = true;
                }
            }

            if (is_logout_event($log->action)) {
                @$data['sessions'][$sessionid]->elapsed += $memlap;
                @$data['sessions'][$sessionid]->sessionend = $log->time;
                $memlap = 0;
                continue;
            }

            if ($log->module == 'system' && $log->action == 'failed') {
                $memlap = 0;
                continue;
            }

            $lap = $lap + $memlap;
            $memlap = 0;

            if (!isset($log->module)) {
                continue;
            }

            $preinit = false;
            if ($sessionid == 0) {
                if (!isset($data['sessions'][0]->sessionstart)) {
                    @$data['sessions'][0]->sessionstart = $logs[0]->time;
                    $preinit = true;
                }
            }

            @$data['sessions'][$sessionid]->courses[$log->course] = $log->course;

            if (!is_login_event($log->action) && is_login_event(@$lognext->action)) {
                @$data['sessions'][$sessionid]->elapsed += $lap;
                @$data['sessions'][$sessionid]->sessionend = $log->time + $lap;
            } else {
                if (is_login_event($log->action)) {
                    if ($lognext && !is_login_event($lognext->action)) {
                        if (!$preinit || $sessionid) {
                            $preinit = false;
                            $sessionid++;
                        }
                        @$data['sessions'][$sessionid]->elapsed = $lap;
                        @$data['sessions'][$sessionid]->sessionstart = $log->time;
                    } else {
                        continue;
                    }
                } else {
                    if ($sessionpunch || !$lognext || is_login_event($lognext->action)) {
                        @$data['sessions'][$sessionid]->sessionend = $log->time + $lap;
                        @$data['sessions'][$sessionid]->elapsed += $lap;
                        if ($sessionpunch && (!is_login_event(@$lognext->action) && (@$lognext->action != 'failed'))) {
                            $sessionid++;
                            @$data['sessions'][$sessionid]->sessionstart = $lognext->time;
                            @$data['sessions'][$sessionid]->elapsed = 0;
                        }
                    } else {
                        if (!isset($data['sessions'][$sessionid])) {
                            @$data['sessions'][$sessionid]->sessionstart = $log->time;
                            @$data['sessions'][$sessionid]->elapsed = $lap;
                        } else {
                            @$data['sessions'][$sessionid]->elapsed += $lap;
                        }
                    }
                    @$data['sessions'][$sessionid]->courses[$log->course] = 1;
                }
            }
        }

        if (!empty($data)) {
            $data['elapsed'] = new StdClass();
            $data['elapsed']->elapsed = 0;
            foreach ($data['sessions'] as $sessionid => $stat) {
                $data['elapsed']->elapsed += $data['sessions'][$sessionid]->elapsed;
            }
            $t = format_dedication($data['elapsed']->elapsed);
            $data['elapsed']->elapsedhtml = $t;
        }
    }

    $groups = groups_get_user_groups($courseid, $userid);
    $group = !empty($groups) && !empty($groups[0]) ? $groups[0][0] : 0;

    if ($data) {
        $dedicationtime = $data['elapsed']->elapsedhtml;
    } else {
        $dedicationtime = 0;
    }
        
    return $dedicationtime;
}

function format_dedication($totalsecs) {
    $totalsecs = abs($totalsecs);

    $str = new stdClass();
    $str->hour = get_string('hour');
    $str->hours = get_string('hours');
    $str->min = get_string('min');
    $str->mins = get_string('mins');
    $str->sec = get_string('sec');
    $str->secs = get_string('secs');

    $hours = floor($totalsecs / HOURSECS);
    $remainder = $totalsecs - ($hours * HOURSECS);
    $mins = floor($remainder / MINSECS);
    $secs = round($remainder - ($mins * MINSECS), 2);

    $ss = ($secs == 1) ? $str->sec : $str->secs;
    $sm = ($mins == 1) ? $str->min : $str->mins;
    $sh = ($hours == 1) ? $str->hour : $str->hours;

    $ohours = '';
    $omins = '';
    $osecs = '';

    if ($hours) {
        $ohours = $hours . ' ' . $sh;
    }
    if ($mins) {
        $omins = $mins . ' ' . $sm;
    }
    if ($secs) {
        $osecs = $secs . ' ' . $ss;
    }

    if ($hours) {
        return trim($ohours . ' ' . $omins);
    }
    if ($mins) {
        return trim($omins . ' ' . $osecs);
    }
    if ($secs) {
        return $osecs;
    }

    return get_string('none');
}

function get_log_records($selectwhere, array $params) {
    $return = array();
    static $allreaders = null;

    if (is_null($allreaders)) {
        $allreaders = get_log_manager()->get_readers();
    }

    $processedreaders = 0;
    $logstores = array('logstore_standard', 'logstore_legacy');
    
    foreach ($logstores as $name) {
        if (isset($allreaders[$name])) {
            $reader = $allreaders[$name];
            $events = $reader->get_events_select($selectwhere, $params, 'timecreated ASC', 0, 0);
            
            foreach ($events as $eventId => $event) {
                $obj = new stdClass();
                $obj->id = $eventId;
                $obj->course = $event->courseid;
                $obj->action = $event->action;
                $obj->target = $event->target;
                $obj->time = $event->timecreated;
                $obj->userid = $event->userid;
                $obj->contextid = $event->contextid;
                $obj->contextinstanceid = $event->contextinstanceid;
                $obj->contextlevel = $event->contextlevel;
                $return[] = $obj;
            }
            
            if (!empty($events)) {
                $processedreaders++;
            }
        }
    }

    if ($processedreaders > 1) {
        usort($return, function($a, $b) {
            return $a->time > $b->time;
        });
    }

    return $return;
}

function get_context_info(&$log) {
    global $DB;
    static $cmnames = array();

    $log->module = 'undefined';
    switch ($log->contextlevel) {
        case CONTEXT_SYSTEM:
            if ($log->action == 'loggedin') {
                $log->module = 'user';
                $log->action = 'login';
            } else {
                $log->module = 'system';
            }
            $log->cmid = 0;
            break;
        case CONTEXT_USER:
            $log->module = 'user';
            $log->cmid = 0;
            break;
        case CONTEXT_MODULE:
            $cmid = $DB->get_field('context', 'instanceid', array('id' => $log->contextid));
            if (!array_key_exists($log->contextid, $cmnames)) {
                $moduleid = $DB->get_field('course_modules', 'module', array('id' => $cmid));
                $cmnames[$log->contextid] = $DB->get_field('modules', 'name', array('id' => $moduleid));
            }
            $log->module = $cmnames[$log->contextid];
            $log->cmid = 0 + @$cmid;
            break;
        default:
            $log->cmid = 0;
            $log->module = 'course';
            break;
    }
}

function is_login_event($action) {
    return (($action == 'login') || ($action == 'loggedin'));
}

function is_logout_event($action) {
    return (($action == 'logout') || ($action == 'loggedout'));
}

function get_next_log(&$logs, $i) {
    $log = $logs[$i];
    $lastlog = $logs[$i + 1];
    $lognext = @$logs[$i + 2];
    $j = $i + 1;

    while (isset($lognext) && ($lastlog->action == 'graded') && ($lastlog->target == 'user')) {
        $j++;
        $lastlog = $logs[$j];
        $lognext = @$logs[$j + 1];
    }

    $lap = $lastlog->time - $log->time;
    return array($lastlog, $lap, $j);
}
?>
