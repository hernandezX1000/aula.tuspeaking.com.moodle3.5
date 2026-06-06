<?php
/* =========================================
   Helpers
========================================= */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function get_data_file($rel) {
  return __DIR__ . '/data/' . $rel;
}

/* =========================================
   Modo
========================================= */
$isCli = (php_sapi_name() === 'cli');

/* =========================================
   Seguridad (solo web): token obligatorio
========================================= */
$cfg = array();
if (!$isCli) {
  $cfgFile = get_data_file('config.php');
  $cfg = file_exists($cfgFile) ? include($cfgFile) : array();

  $expectedToken = isset($cfg['token']) ? (string)$cfg['token'] : '';
  $providedToken = isset($_GET['token']) ? (string)$_GET['token'] : '';

  if ($expectedToken === '' || $providedToken !== $expectedToken) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden";
    exit;
  }
}

/* =========================================
   Params (GET)
========================================= */
$params = $isCli ? array() : $_GET;

$courseid = isset($params['courseid']) ? intval($params['courseid']) : 0; // opcional
$lang     = isset($params['lang']) ? strtolower(trim($params['lang'])) : '';
$level    = isset($params['level']) ? trim($params['level']) : '';
$start    = isset($params['start']) ? trim($params['start']) : '';
$end      = isset($params['end']) ? trim($params['end']) : '';

$render   = isset($params['render']) ? trim((string)$params['render']) : '';
$raw      = isset($params['raw']) ? trim((string)$params['raw']) : '';
if ($raw === '1') $render = '1'; // raw implica render

/* Defaults */
if ($lang === '')  $lang = 'en';
if ($level === '') $level = 'A1-A2';
if ($start === '') $start = '15 de septiembre';
if ($end === '')   $end = '19 de diciembre';

/* =========================================
   Datos: planes lectivos + teachers
========================================= */
$plansFile = get_data_file('plans_lectivos.php');

$planUrl = '#';

// Normaliza idioma (clave en minúsculas)
$langKey = strtolower(trim($lang));
if ($langKey === '') $langKey = 'en';

// Normaliza nivel para que coincida con tus claves reales
$levelKey = strtoupper(trim($level));
$levelKey = str_replace([' ', '_'], ['','-'], $levelKey); // "B1 +" -> "B1+"
$levelKey = str_replace(['PLUS'], ['+'], $levelKey);      // por si llega "B1PLUS"
$levelKey = preg_replace('/-+/', '-', $levelKey);         // colapsa "--"
$levelKey = trim($levelKey, '-');

// Intentos de fallback típicos (por si el PDF/usuario mete variantes)
$levelCandidates = array_values(array_unique([
  $levelKey,
  str_replace('B1+', 'B1 PLUS', $levelKey),
  str_replace('B1 PLUS', 'B1+', $levelKey),
  str_replace('C1-C2', 'C1–C2', $levelKey),
  str_replace('C1–C2', 'C1-C2', $levelKey),
]));

// 1) Lookup con idioma
if (isset($plansAll[$langKey]) && is_array($plansAll[$langKey])) {
  foreach ($levelCandidates as $cand) {
    if (isset($plansAll[$langKey][$cand])) {
      $planUrl = $plansAll[$langKey][$cand];
      break;
    }
  }
}

// 2) Fallback: si tu data file NO está por idioma (array plano)
if ($planUrl === '#' && is_array($plansAll)) {
  foreach ($levelCandidates as $cand) {
    if (isset($plansAll[$cand])) {
      $planUrl = $plansAll[$cand];
      break;
    }
  }
}
// Fuerza render si viene raw=1 (para permitir llamadas directas por URL)
if (!isset($params)) { $params = $_GET; } // por si acaso (web mode)
$raw = isset($params['raw']) ? trim((string)$params['raw']) : '';
if ($raw === '1') {
  $params['render'] = '1';
}
$render = isset($params['render']) ? trim((string)$params['render']) : '';

/* =========================================
   Si no viene "render=1", mostramos FORM
========================================= */
$render = isset($params['render']) ? intval($params['render']) : 0;

if (!$isCli && $render !== 1) {
  header('Content-Type: text/html; charset=utf-8');

  $token = isset($_GET['token']) ? h($_GET['token']) : '';
  echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '<title>Generador – Información del curso</title></head><body style="font-family:Arial,sans-serif;background:#f7fbfc;padding:18px;color:#454545;">';

  echo '<div style="max-width:840px;margin:0 auto;background:#fff;border:1px solid rgba(0,139,163,.16);border-radius:12px;padding:16px;">';
  echo '<div style="background:#008ba3;color:#fff;padding:14px 16px;border-radius:10px;font-weight:700;">Generador – Información del curso</div>';

  echo '<form method="get" style="margin-top:14px;">';
  echo '<input type="hidden" name="token" value="'.$token.'">';
  echo '<input type="hidden" name="render" value="1">';

  echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';

  echo '<div><label style="font-weight:700;">Idioma</label><br>';
  echo '<select name="lang" style="width:100%;padding:10px;border-radius:10px;border:1px solid rgba(0,139,163,.25);">
          <option value="en"'.($lang==='en'?' selected':'').'>Inglés</option>
          <option value="fr"'.($lang==='fr'?' selected':'').'>Francés</option>
          <option value="de"'.($lang==='de'?' selected':'').'>Alemán</option>
        </select></div>';

  echo '<div><label style="font-weight:700;">Nivel</label><br>';
  echo '<select name="level" style="width:100%;padding:10px;border-radius:10px;border:1px solid rgba(0,139,163,.25);">
          <option'.($level==='A1-A2'?' selected':'').'>A1-A2</option>
          <option'.($level==='A2'?' selected':'').'>A2</option>
          <option'.($level==='B1'?' selected':'').'>B1</option>
          <option'.($level==='B1+'?' selected':'').'>B1+</option>
          <option'.($level==='B2'?' selected':'').'>B2</option>
          <option'.($level==='B2-C1'?' selected':'').'>B2-C1</option>
          <option'.($level==='C1-C2'?' selected':'').'>C1-C2</option>
        </select></div>';

  echo '<div><label style="font-weight:700;">Fecha inicio</label><br>';
  echo '<input name="start" value="'.h($start).'" style="width:100%;padding:10px;border-radius:10px;border:1px solid rgba(0,139,163,.25);"></div>';

  echo '<div><label style="font-weight:700;">Fecha fin</label><br>';
  echo '<input name="end" value="'.h($end).'" style="width:100%;padding:10px;border-radius:10px;border:1px solid rgba(0,139,163,.25);"></div>';

  echo '</div>';

  echo '<div style="margin-top:12px;">
          <label style="font-weight:700;">Course ID (opcional, solo si quieres links a reportes)</label><br>
          <input name="courseid" value="'.h($courseid).'" style="width:100%;padding:10px;border-radius:10px;border:1px solid rgba(0,139,163,.25);">
        </div>';

  echo '<button type="submit" style="margin-top:14px;background:#008ba3;color:#fff;border:0;padding:10px 14px;border-radius:10px;font-weight:700;cursor:pointer;">
          Generar HTML
        </button>';

  echo '</form>';

  echo '<div style="margin-top:10px;font-size:12px;color:#666;">
          Consejo: añade esta URL como enlace en Moodle. Se abrirá el formulario y podrás generar el HTML para cualquier curso.
        </div>';

  echo '</div></body></html>';
  exit;
}

/* =========================================
   HTML FINAL (resultado)
========================================= */
$html = '
<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;color:#454545;line-height:1.6;background:#ffffff;padding:16px;border:1px solid rgba(0,139,163,.16);border-radius:12px;">

  <div style="background:#008ba3;color:#ffffff;padding:14px 16px;border-radius:10px;">
    <div style="font-size:16px;font-weight:700;">Información del curso</div>
    <div style="font-size:13px;opacity:.95;">Idioma: <strong>' . h(strtoupper($lang)) . '</strong> · Nivel: <strong>' . h($level) . '</strong></div>
  </div>

  <div style="margin-top:14px;">
    <div style="font-weight:700;margin:10px 0 6px;">Duración del curso</div>
    <div>El curso inicia el día <strong>' . h($start) . '</strong> y finaliza el día <strong>' . h($end) . '</strong>.</div>
  </div>

  <div style="margin-top:12px;">
    <div style="font-weight:700;margin:10px 0 6px;">Trabajo en plataforma</div>
    <div>En la plataforma encontraréis cuatro tipos de contenidos. En este curso trabajaréis la <strong>gramática</strong> y el <strong>vocabulario</strong> correspondientes al nivel, mediante <strong>ejercicios autocorregibles</strong>, así como actividades de <strong>listening</strong>, <strong>reading</strong> y <strong>writing</strong>.</div>
  </div>

  <div style="margin-top:12px;">
    <div style="font-weight:700;margin:10px 0 6px;">Contacto con tuSpeaking</div>
    <div>Para cualquier duda o problema, podéis contactar mediante el <strong>botón de soporte técnico</strong> de la plataforma o escribiendo a 
    <a href="mailto:soporte@tuspeaking.com" style="color:#00bcd4;text-decoration:none;">soporte@tuspeaking.com</a>.</div>
  </div>

  <div style="margin-top:14px;padding:12px;border:1px solid rgba(0,139,163,.16);border-radius:10px;background:rgba(98,238,255,.10);">
    <div style="font-weight:700;margin-bottom:6px;">Plan de trabajo</div>
    <ul style="margin:0;padding-left:18px;">
      <li><a href="' . h($planUrl) . '" target="_blank" rel="noopener" style="color:#008ba3;text-decoration:none;font-weight:600;">
      Guía Didáctica y Plan Lectivo – ' . h($level) . '</a></li>
    </ul>
  </div>

  <div style="margin-top:14px;">
    <div style="font-weight:700;margin:10px 0 6px;">Tutores – CVs y experiencia docente</div>
    <ul style="margin:0;padding-left:18px;">
      ' . $teachersHtml . '
    </ul>
  </div>

  <div style="margin-top:14px;padding:12px;border:1px solid rgba(0,139,163,.16);border-radius:10px;">
    <div style="font-weight:700;margin-bottom:6px;">Interacción tutor/alumno</div>
    <div style="margin-bottom:8px;">Puedes visualizar un listado de tutorías/clases consumidas (pasadas) y reservadas (futuras).</div>
    <ul style="margin:0;padding-left:18px;">
      <li><a href="' . h($registroTutoriasUrl) . '" target="_blank" rel="noopener" style="color:#008ba3;text-decoration:none;font-weight:600;">Registro de tutorías / clases</a></li>
      <li><a href="' . h($reporteFeedbackUrl) . '" target="_blank" rel="noopener" style="color:#008ba3;text-decoration:none;font-weight:600;">Reporte de feedback de las tutorías / clases</a></li>
    </ul>
  </div>

  <div style="margin-top:14px;padding:12px;border:1px solid rgba(0,139,163,.16);border-radius:10px;">
    <div style="font-weight:700;margin-bottom:6px;">Realizar las sesiones desde el móvil</div>
    <div>Descarga la app de Zoom en PlayStore o AppleStore e ingresa el ID de la clase que recibes junto a la notificación de la sesión.</div>
  </div>

  <div style="margin-top:14px;font-size:13px;color:#666;">¡Que disfrutes del curso!</div>

</div>
';

header('Content-Type: text/html; charset=utf-8');
echo $html;
