<?php
/**
 * Encuesta de valoración de la clase (NPS del alumno) — reactivación.
 * TICKET-CESCE-2026-020 · repo aula.tuspeaking.com.moodle3.5 · /valoracion.php
 *
 * Sustituye al JotForm que enlazaba el correo de seguimiento de Acuity, que apuntaba
 * a una URL rota (`/app/moodle/feedback/`). Guarda en la MISMA tabla del histórico:
 * `own_feedback_nps` (15.332 respuestas migradas), así siguen valiendo las estadísticas
 * de `feedback/config_abstraction.php`.
 *
 * Público: NO exige login (el alumno llega desde su correo).
 * Acuity hace de «sender»: la pieza que se perdió el 1-ago.
 *
 * URL para el botón del correo de Acuity:
 *   https://aula.tuspeaking.com/valoracion.php?e=%email%&i=Ingl%C3%A9s
 *   (e = correo del alumno · i = idioma, opcional · a = acuityid, opcional)
 */

require_once './config.php';   // $CFG con las credenciales de la BD del aula

$ok = false;
$error = '';

function v($k, $max = 200) {
    $s = isset($_REQUEST[$k]) ? trim((string)$_REQUEST[$k]) : '';
    return mb_substr($s, 0, $max);
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$email   = v('e', 200);
$idioma  = v('i', 50);
$acuityid = ctype_digit(v('a', 20)) ? (int)v('a', 20) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim((string)($_POST['website'] ?? '')) !== '') {   // honeypot
        $ok = true;
    } else {
        $profesor    = v('profesor', 100);
        $valoracion  = (int)v('valoracion', 3);
        $comentarios = v('comentarios', 2000);
        $email       = v('email', 200) ?: $email;
        $idioma      = v('idioma', 50) ?: $idioma;
        $acuityid    = ctype_digit(v('acuityid', 20)) ? (int)v('acuityid', 20) : $acuityid;

        if ($profesor === '' || $valoracion < 1 || $valoracion > 10) {
            $error = 'Indica el nombre del profesor y una nota del 1 al 10.';
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Escribe tu correo electrónico para que sepamos de qué clase se trata.';
        } else {
            try {
                $pdo = new PDO(
                    'mysql:host=' . $CFG->dbhost . ';dbname=' . $CFG->dbname . ';charset=utf8mb4',
                    $CFG->dbuser, $CFG->dbpass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                $studentid = null;
                if ($email !== '') {
                    $q = $pdo->prepare('SELECT id FROM mdl_user WHERE LOWER(email) = LOWER(?) AND deleted = 0 LIMIT 1');
                    $q->execute([$email]);
                    $studentid = $q->fetchColumn() ?: null;
                }

                $ins = $pdo->prepare(
                    'INSERT INTO own_feedback_nps
                       (acuityid, studentid, submission_date, idioma, profesor, valoracion,
                        comentarios, email, enviado_auto)
                     VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, 1)'
                );
                $ins->execute([
                    $acuityid, $studentid, ($idioma !== '' ? $idioma : null),
                    $profesor, $valoracion, ($comentarios !== '' ? $comentarios : null),
                    ($email !== '' ? $email : null),
                ]);
                $ok = true;
            } catch (PDOException $e) {
                error_log('[valoracion] ' . $e->getMessage());
                $error = 'No hemos podido guardar tu valoración. Inténtalo de nuevo en unos minutos.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Valoración de la clase · tuSpeaking</title>
<style>
 body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f8fafc;margin:0;padding:24px;color:#1f2937}
 .card{max-width:520px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px}
 h1{font-size:1.25rem;margin:0 0 4px;color:#0e7490}
 p.sub{margin:0 0 20px;color:#6b7280;font-size:.95rem}
 label{display:block;margin:14px 0 4px;font-weight:600;font-size:.95rem}
 input,select,textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-size:1rem;font-family:inherit}
 button{margin-top:18px;width:100%;background:#10b981;color:#fff;border:0;border-radius:8px;padding:12px;font-size:1rem;font-weight:700;cursor:pointer}
 .ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;padding:14px;border-radius:8px}
 .err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:14px;border-radius:8px;margin-bottom:12px}
</style></head><body>
<div class="card">
  <h1>Valoración de la clase</h1>
  <p class="sub">Un minuto. Tu opinión nos ayuda a mejorar.</p>

<?php if ($ok): ?>
  <div class="ok">¡Gracias! Hemos recibido tu valoración.</div>
<?php else: ?>
  <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">
    <?php if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)): ?>
      <input type="hidden" name="email" value="<?= h($email) ?>">
    <?php else: ?>
      <label for="email">Tu correo electrónico *</label>
      <input id="email" name="email" type="email" required maxlength="200" value="<?= h($email) ?>">
    <?php endif; ?>
    <input type="hidden" name="idioma" value="<?= h($idioma) ?>">
    <input type="hidden" name="acuityid" value="<?= h($acuityid) ?>">

    <label for="profesor">Nombre del profesor o profesora *</label>
    <input id="profesor" name="profesor" required maxlength="100" value="<?= h(v('profesor', 100)) ?>">

    <label for="valoracion">¿Qué tal ha ido la clase? *</label>
    <select id="valoracion" name="valoracion" required>
      <option value="">Elige una nota del 1 al 10</option>
      <?php for ($n = 1; $n <= 10; $n++): ?>
        <option value="<?= $n ?>"><?= $n ?></option>
      <?php endfor; ?>
    </select>

    <label for="comentarios">Comentarios</label>
    <textarea id="comentarios" name="comentarios" rows="5" maxlength="2000"></textarea>

    <button type="submit">Enviar valoración</button>
  </form>
<?php endif; ?>
</div>
</body></html>
