<?php
/**
 * fundae_guia.php — Sirve la Guía Didáctica (PDF) de una acción formativa bajo demanda.
 *
 * Uso:  /fundae_guia.php?c_fundae=00033LIN26-01
 *
 * - Solo accesible para admin del sitio o rol 'supervisor' (inspector FUNDAE) del curso.
 * - Genera el PDF con gen_guia_didactica.py y lo transmite (application/pdf).
 * - Enlazado desde el panel del inspector del bloque block_fundae (contexto de curso).
 *
 * CONSTANTES A VERIFICAR EN SERVIDOR (marcadas con CONFIG):
 *   - GEN_SCRIPT : ruta del generador python
 *   - OUTPUT_DIR : carpeta de salida de los PDFs
 *   - rol supervisor: shortname 'supervisor'
 */

require('config.php');
require_once($CFG->libdir . '/accesslib.php');

// CONFIG — verificar en servidor.
define('GEN_SCRIPT', '/home/aulatuspeaking/gen_guia_didactica.py');
define('OUTPUT_DIR', '/home/aulatuspeaking/fundae_docs');
define('SUPERVISOR_SHORTNAME', 'supervisor');

global $DB, $USER;

// 1. Parámetro c_fundae, saneado y validado contra la BD.
$cfundae = required_param('c_fundae', PARAM_RAW_TRIMMED);
if (!preg_match('/^[A-Za-z0-9_\-]+$/', $cfundae)) {
    http_response_code(400);
    die('Código de acción formativa no válido.');
}

$fundae = $DB->get_record('fundae', ['c_fundae' => $cfundae], '*', IGNORE_MISSING);
if (!$fundae) {
    http_response_code(404);
    die('No existe la acción formativa indicada.');
}

// 2. Autenticación: admin del sitio o supervisor del curso de esa AF.
require_login($fundae->courseid, false);
$coursecontext = context_course::instance($fundae->courseid);

$autorizado = is_siteadmin();
if (!$autorizado) {
    foreach (get_user_roles($coursecontext, $USER->id, true) as $ro) {
        if ($ro->shortname === SUPERVISOR_SHORTNAME) { $autorizado = true; break; }
    }
}
if (!$autorizado) {
    http_response_code(403);
    die('No autorizado.');
}

// 3. Generar el PDF (bajo demanda).
if (!is_dir(OUTPUT_DIR)) {
    @mkdir(OUTPUT_DIR, 0775, true);
}
$cmd = 'python3 ' . escapeshellarg(GEN_SCRIPT)
     . ' --c_fundae ' . escapeshellarg($cfundae)
     . ' --output ' . escapeshellarg(OUTPUT_DIR)
     . ' 2>&1';
$salida = [];
$rc = 0;
exec($cmd, $salida, $rc);

// 4. Localizar el PDF generado (Doc4_Guia_Didactica_<idgrupo>_<slug>.pdf).
$slug = str_replace('-', '_', $cfundae);
$patron = OUTPUT_DIR . '/Doc4_Guia_Didactica_*_' . $slug . '.pdf';
$ficheros = glob($patron);
if (empty($ficheros)) {
    // Fallback: el _final.pdf comprimido con ghostscript, si existiera.
    $ficheros = glob(OUTPUT_DIR . '/Doc4_Guia_Didactica_*_' . $slug . '_final.pdf');
}
if (empty($ficheros)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "No se pudo generar la guía didáctica para $cfundae.\n";
    echo "Salida del generador (rc=$rc):\n" . implode("\n", $salida) . "\n";
    exit;
}

// El más reciente por si hay varias versiones.
usort($ficheros, function ($a, $b) { return filemtime($b) - filemtime($a); });
$pdf = $ficheros[0];

// 5. Transmitir el PDF.
$nombre = 'Guia_Didactica_' . $slug . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nombre . '"');
header('Content-Length: ' . filesize($pdf));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
readfile($pdf);
exit;
