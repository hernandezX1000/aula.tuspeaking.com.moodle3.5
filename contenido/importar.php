<?php
/**
 * Importar contenido entre cursos - Con soporte H5P
 * TuSpeaking 2026
 */

$is_cli = (php_sapi_name() === 'cli');
if ($is_cli) {
    define('CLI_SCRIPT', true);
}

require_once('/var/www/html/app/moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/accesslib.php');
require_once(__DIR__ . '/config.php');

global $USER, $DB;
$USER = $DB->get_record('user', ['id' => 2]);

/**
 * Duplicar módulos de una sección a otro curso
 */
function duplicar_seccion_a_curso($seccion_origen_id, $curso_destino_id, $nombre_nueva_seccion = null) {
    global $DB;
    
    $resultado = [
        'exito' => true,
        'modulos_copiados' => 0,
        'errores' => [],
        'seccion_creada_id' => null,
        'log' => []
    ];
    
    $seccion_origen = $DB->get_record('course_sections', ['id' => $seccion_origen_id]);
    if (!$seccion_origen) {
        $resultado['exito'] = false;
        $resultado['errores'][] = "Sección origen no encontrada";
        return $resultado;
    }
    
    $curso_origen = $DB->get_record('course', ['id' => $seccion_origen->course]);
    $resultado['log'][] = "Origen: {$curso_origen->shortname} - " . ($seccion_origen->name ?: "Sección {$seccion_origen->section}");
    
    $curso_destino = $DB->get_record('course', ['id' => $curso_destino_id]);
    if (!$curso_destino) {
        $resultado['exito'] = false;
        $resultado['errores'][] = "Curso destino no encontrado";
        return $resultado;
    }
    $resultado['log'][] = "Destino: {$curso_destino->shortname}";
    
    // Crear nueva sección
    $ultima_seccion = $DB->get_field_sql("SELECT MAX(section) FROM {course_sections} WHERE course = ?", [$curso_destino_id]);
    
    $nueva_seccion = new stdClass();
    $nueva_seccion->course = $curso_destino_id;
    $nueva_seccion->section = ($ultima_seccion ?? 0) + 1;
    $nueva_seccion->name = $nombre_nueva_seccion ?? $seccion_origen->name ?? 'Importada';
    $nueva_seccion->summary = $seccion_origen->summary ?? '';
    $nueva_seccion->summaryformat = FORMAT_HTML;
    $nueva_seccion->visible = 1;
    $nueva_seccion->id = $DB->insert_record('course_sections', $nueva_seccion);
    
    $resultado['seccion_creada_id'] = $nueva_seccion->id;
    $resultado['log'][] = "Sección creada: {$nueva_seccion->name} (num: {$nueva_seccion->section})";
    
    // Obtener módulos
    $modulos = $DB->get_records_sql(
        "SELECT cm.*, m.name as modname 
         FROM {course_modules} cm 
         JOIN {modules} m ON m.id = cm.module
         WHERE cm.section = ? AND cm.deletioninprogress = 0
         ORDER BY cm.id",
        [$seccion_origen_id]
    );
    
    $resultado['log'][] = "Módulos encontrados: " . count($modulos);
    
    foreach ($modulos as $cm) {
        try {
            $new_cm_id = copiar_modulo_directo($cm, $curso_destino_id, $nueva_seccion->id);
            if ($new_cm_id) {
                $resultado['modulos_copiados']++;
                $resultado['log'][] = "✓ {$cm->modname}";
            } else {
                $resultado['log'][] = "⚠ {$cm->modname} - saltado";
            }
        } catch (Exception $e) {
            $resultado['errores'][] = "{$cm->modname}: " . $e->getMessage();
            $resultado['log'][] = "✗ {$cm->modname}: " . substr($e->getMessage(), 0, 50);
        }
    }
    
    rebuild_course_cache($curso_destino_id, true);
    return $resultado;
}

/**
 * Copiar un módulo
 */
function copiar_modulo_directo($cm, $curso_destino_id, $seccion_destino_id) {
    global $DB;
    
    $modname = $cm->modname;
    
    // Solo LTI y SCORM no soportados
    $no_soportados = ['lti', 'scorm'];
    if (in_array($modname, $no_soportados)) {
        return false;
    }
    
    // Copiar HVP especialmente
    if ($modname === 'hvp') {
        return copiar_hvp($cm, $curso_destino_id, $seccion_destino_id);
    }
    
    // Copiar otros módulos
    $instancia_original = $DB->get_record($modname, ['id' => $cm->instance]);
    if (!$instancia_original) {
        return false;
    }
    
    $nueva_instancia = clone $instancia_original;
    unset($nueva_instancia->id);
    $nueva_instancia->course = $curso_destino_id;
    $nueva_instancia->timemodified = time();
    
    $nueva_instancia_id = $DB->insert_record($modname, $nueva_instancia);
    if (!$nueva_instancia_id) {
        return false;
    }
    
    $nuevo_cm_id = crear_course_module($cm, $curso_destino_id, $seccion_destino_id, $nueva_instancia_id);
    
    // Copiar archivos
    if (in_array($modname, ['resource', 'folder', 'page'])) {
        copiar_archivos_modulo($cm->id, $nuevo_cm_id, $modname);
    }
    
    return $nuevo_cm_id;
}

/**
 * Copiar módulo HVP con todos sus archivos
 */
function copiar_hvp($cm, $curso_destino_id, $seccion_destino_id) {
    global $DB;
    
    // Obtener HVP original
    $hvp_original = $DB->get_record('hvp', ['id' => $cm->instance]);
    if (!$hvp_original) {
        return false;
    }
    
    // Crear copia del HVP
    $nuevo_hvp = clone $hvp_original;
    unset($nuevo_hvp->id);
    $nuevo_hvp->course = $curso_destino_id;
    $nuevo_hvp->timecreated = time();
    $nuevo_hvp->timemodified = time();
    
    $nuevo_hvp_id = $DB->insert_record('hvp', $nuevo_hvp);
    if (!$nuevo_hvp_id) {
        return false;
    }
    
    // Crear course_module
    $nuevo_cm_id = crear_course_module($cm, $curso_destino_id, $seccion_destino_id, $nuevo_hvp_id);
    
    // Copiar relaciones con librerías
    $libs = $DB->get_records('hvp_contents_libraries', ['hvp_id' => $hvp_original->id]);
    foreach ($libs as $lib) {
        $nueva_lib = clone $lib;
        unset($nueva_lib->id);
        $nueva_lib->hvp_id = $nuevo_hvp_id;
        $DB->insert_record('hvp_contents_libraries', $nueva_lib);
    }
    
    // Copiar archivos del contenido H5P
    copiar_archivos_hvp($cm->id, $nuevo_cm_id);
    
    return $nuevo_cm_id;
}

/**
 * Copiar archivos de un módulo HVP
 */
function copiar_archivos_hvp($cm_origen_id, $cm_destino_id) {
    global $DB;
    
    try {
        $context_origen = context_module::instance($cm_origen_id);
        $context_destino = context_module::instance($cm_destino_id);
        
        $fs = get_file_storage();
        
        // Copiar todas las áreas de archivos de HVP
        $areas = ['content', 'intro', 'editor'];
        
        foreach ($areas as $area) {
            $files = $fs->get_area_files($context_origen->id, 'mod_hvp', $area, false, 'sortorder', false);
            foreach ($files as $file) {
                $newfile = [
                    'contextid' => $context_destino->id,
                    'component' => 'mod_hvp',
                    'filearea' => $area,
                    'itemid' => $file->get_itemid(),
                    'filepath' => $file->get_filepath(),
                    'filename' => $file->get_filename()
                ];
                if (!$fs->file_exists($newfile['contextid'], $newfile['component'], $newfile['filearea'], $newfile['itemid'], $newfile['filepath'], $newfile['filename'])) {
                    $fs->create_file_from_storedfile($newfile, $file);
                }
            }
        }
    } catch (Exception $e) {
        // Log pero continuar
    }
}

/**
 * Crear course_module
 */
function crear_course_module($cm_original, $curso_destino_id, $seccion_destino_id, $instancia_id) {
    global $DB;
    
    $nuevo_cm = new stdClass();
    $nuevo_cm->course = $curso_destino_id;
    $nuevo_cm->module = $cm_original->module;
    $nuevo_cm->instance = $instancia_id;
    $nuevo_cm->section = $seccion_destino_id;
    $nuevo_cm->visible = $cm_original->visible;
    $nuevo_cm->visibleold = $cm_original->visibleold;
    $nuevo_cm->groupmode = $cm_original->groupmode;
    $nuevo_cm->groupingid = 0;
    $nuevo_cm->completion = $cm_original->completion;
    $nuevo_cm->completiongradeitemnumber = $cm_original->completiongradeitemnumber;
    $nuevo_cm->completionview = $cm_original->completionview;
    $nuevo_cm->completionexpected = 0;
    $nuevo_cm->added = time();
    
    $nuevo_cm_id = $DB->insert_record('course_modules', $nuevo_cm);
    
    // Actualizar sequence
    $seccion = $DB->get_record('course_sections', ['id' => $seccion_destino_id]);
    $sequence = $seccion->sequence ? $seccion->sequence . ',' . $nuevo_cm_id : $nuevo_cm_id;
    $DB->set_field('course_sections', 'sequence', $sequence, ['id' => $seccion_destino_id]);
    
    return $nuevo_cm_id;
}

/**
 * Copiar archivos de módulos estándar
 */
function copiar_archivos_modulo($cm_origen_id, $cm_destino_id, $modname) {
    global $DB;
    
    try {
        $context_origen = context_module::instance($cm_origen_id);
        $context_destino = context_module::instance($cm_destino_id);
        
        $fs = get_file_storage();
        $component = 'mod_' . $modname;
        $areas = ['content', 'intro'];
        
        foreach ($areas as $area) {
            $files = $fs->get_area_files($context_origen->id, $component, $area, false, 'sortorder', false);
            foreach ($files as $file) {
                $newfile = [
                    'contextid' => $context_destino->id,
                    'component' => $component,
                    'filearea' => $area,
                    'itemid' => 0,
                    'filepath' => $file->get_filepath(),
                    'filename' => $file->get_filename()
                ];
                if (!$fs->file_exists($newfile['contextid'], $newfile['component'], $newfile['filearea'], $newfile['itemid'], $newfile['filepath'], $newfile['filename'])) {
                    $fs->create_file_from_storedfile($newfile, $file);
                }
            }
        }
    } catch (Exception $e) {}
}

// Ejecutar
if ($is_cli) {
    $seccion_id = $argv[1] ?? 0;
    $curso_destino_id = $argv[2] ?? 0;
    
    if ($seccion_id && $curso_destino_id) {
        echo "Iniciando copia...\n";
        $resultado = duplicar_seccion_a_curso($seccion_id, $curso_destino_id);
        
        echo "\n=== RESULTADO ===\n";
        echo "Módulos copiados: {$resultado['modulos_copiados']}\n";
        foreach ($resultado['log'] as $l) echo "  $l\n";
        if (!empty($resultado['errores'])) {
            echo "\nErrores:\n";
            foreach ($resultado['errores'] as $e) echo "  - $e\n";
        }
    } else {
        echo "Uso: php importar.php <seccion_id> <curso_destino_id>\n";
    }
}
