<?php
/**
 * Gestor de Demos - tuSpeaking
 * Panel para crear, ver y gestionar demos de empresa
 */
require_once __DIR__ . '/config.php';

$pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$message = '';
$messageType = '';

// === CREAR DEMO ===
if (isset($_POST['action']) && $_POST['action'] === 'create_demo') {
    try {
        $empresa = trim($_POST['empresa']);
        $contacto = trim($_POST['contacto']);
        $username = trim($_POST['username']);
        $email = $username . '@tuspeaking.com';
        $password = 'Success2026!';
        $num_clases = (int)$_POST['num_clases'];
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_fin = $_POST['fecha_fin'];
        
        // Constantes
        $COURSE_ID = 1866;
        $COURSE_CONTEXT_ID = 321467;
        $CATEGORY_CONTEXT_ID = 575444;
        $TEACHER_ID = 10;
        $CONFIGDATA = 'Tzo4OiJzdGRDbGFzcyI6Mzp7czo0OiJ0ZXh0IjtzOjEyMDoiPGlmcmFtZSBzcmM9Ii9hcHAvbW9vZGxlL2Jsb2Nrcy9odG1sL2Rhc2hib2FyZF93ZWxjb21lLnBocCIgc3R5bGU9IndpZHRoOjEwMCU7IG1pbi1oZWlnaHQ6MzIwcHg7IGJvcmRlcjpub25lOyI+PC9pZnJhbWU+IjtzOjU6InRpdGxlIjtzOjA6IiI7czo2OiJmb3JtYXQiO2k6MTt9';
        $DEMO_WELCOME = '/home/aulatuspeaking/www/app/moodle/portal/demo_welcome.php';

        // 1. Verificar que no existe
        $stmt = $pdo->prepare("SELECT id FROM mdl_user WHERE username=? OR email=?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) throw new Exception("El usuario '$username' ya existe.");

        // 2. Crear usuario
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO mdl_user (auth,confirmed,mnethostid,username,password,firstname,lastname,email,timecreated,timemodified) VALUES ('manual',1,1,?,?,?,?,?,UNIX_TIMESTAMP(),UNIX_TIMESTAMP())")
            ->execute([$username, $hash, 'Demo', $empresa, $email]);
        $user_id = $pdo->lastInsertId();

        // 3. Contexto
        $pdo->exec("INSERT IGNORE INTO mdl_context (contextlevel,instanceid,depth,path) VALUES (30,$user_id,3,CONCAT('/1/2/',$user_id))");
        $user_ctx = $pdo->query("SELECT id FROM mdl_context WHERE contextlevel=30 AND instanceid=$user_id")->fetchColumn();

        // 4. Rol student
        $pdo->prepare("INSERT INTO mdl_role_assignments (roleid,contextid,userid,timemodified,modifierid) VALUES (5,?,?,UNIX_TIMESTAMP(),2)")
            ->execute([$COURSE_CONTEXT_ID, $user_id]);

        // 5. Matricular
        $pdo->prepare("INSERT INTO mdl_user_enrolments (enrolid,userid,timestart,timecreated,timemodified) SELECT e.id,?,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),UNIX_TIMESTAMP() FROM mdl_enrol e WHERE e.courseid=? AND e.enrol='manual' LIMIT 1")
            ->execute([$user_id, $COURSE_ID]);

        // 6. Rol RRHH
        $pdo->prepare("INSERT INTO mdl_role_assignments (roleid,contextid,userid,timemodified,modifierid) VALUES (11,?,?,UNIX_TIMESTAMP(),2)")
            ->execute([$CATEGORY_CONTEXT_ID, $user_id]);

        // 7. My Pages
        $pdo->exec("INSERT INTO mdl_my_pages (userid,name,private,sortorder) VALUES ($user_id,'__default',0,0),($user_id,'__default',1,0)");
        $page_id = $pdo->query("SELECT id FROM mdl_my_pages WHERE userid=$user_id AND private=1")->fetchColumn();

        // 8. Bloque dashboard
        $pdo->prepare("INSERT INTO mdl_block_instances (blockname,parentcontextid,showinsubcontexts,requiredbytheme,pagetypepattern,subpagepattern,defaultregion,defaultweight,configdata,timecreated,timemodified) VALUES ('html',?,0,0,'my-index',?,'content',-10,?,UNIX_TIMESTAMP(),UNIX_TIMESTAMP())")
            ->execute([$user_ctx, $page_id, $CONFIGDATA]);

        // 9. demo_welcome.php
        $content = file_get_contents($DEMO_WELCOME);
        if (strpos($content, "'$username'") === false) {
            $content = preg_replace(
                "/in_array\(\\\$USER->username, \[([^\]]+)\]/",
                "in_array(\$USER->username, [$1, '$username']",
                $content
            );
            file_put_contents($DEMO_WELCOME, $content);
        }

        // 10. Clases demo
        $acuityid = 960001 + $user_id;
        $stmt = $pdo->prepare("INSERT INTO mdl_i3code_acuityZoom (acuityid,courseid,studentid,teacherid,acuity_firstname,acuity_lastname,acuity_email,acuity_datetime,acuity_duration,acuity_type,zoom_clasecompletada,acuity_canceled,acuity_rescheduled) VALUES (?,?,?,?,?,?,?,?,30,'2026  English Class (30 min)',3,0,0)");
        $current = new DateTime($fecha_inicio);
        while ((int)$current->format('N') != 1) $current->modify('+1 day');
        $count = 0;
        while ($count < $num_clases) {
            if (in_array((int)$current->format('N'), [1, 4])) {
                $current->setTime(10, 0, 0);
                $stmt->execute([$acuityid++, $COURSE_ID, $user_id, $TEACHER_ID, 'Demo', $empresa, $email, $current->format('Y-m-d H:i:s')]);
                $count++;
            }
            $current->modify('+1 day');
        }

        // 11. Purgar caché
        exec('php /home/aulatuspeaking/www/app/moodle/admin/cli/purge_caches.php 2>&1');

        // 12. Registrar demo
        $pdo->exec("CREATE TABLE IF NOT EXISTS own_demos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa VARCHAR(200) NOT NULL,
            contacto VARCHAR(200),
            username VARCHAR(100) NOT NULL,
            email VARCHAR(200) NOT NULL,
            user_id INT NOT NULL,
            num_clases INT DEFAULT 8,
            fecha_inicio DATE,
            fecha_fin DATE,
            activo TINYINT(1) DEFAULT 1,
            learn_configurado TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->prepare("INSERT INTO own_demos (empresa,contacto,username,email,user_id,num_clases,fecha_inicio,fecha_fin) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$empresa, $contacto, $username, $email, $user_id, $num_clases, $fecha_inicio, $fecha_fin]);

        // 13. Learn se configura automáticamente via cron en vls18488
        $learn_ok = false; // Se marcará como configurado por el cron en ~1 min

        $message = "✅ Demo creada para <strong>$empresa</strong><br><br>";
        $message .= "<table style='border-collapse:collapse;width:100%'>";
        $message .= "<tr><td style='padding:8px;border:1px solid #444;color:#aaa'>URL</td><td style='padding:8px;border:1px solid #444'><a href='https://aula.tuspeaking.com/app/moodle/portal/demo_welcome.php' target='_blank'>aula.tuspeaking.com/.../demo_welcome.php</a></td></tr>";
        $message .= "<tr><td style='padding:8px;border:1px solid #444;color:#aaa'>Usuario</td><td style='padding:8px;border:1px solid #444'>$email</td></tr>";
        $message .= "<tr><td style='padding:8px;border:1px solid #444;color:#aaa'>Contraseña</td><td style='padding:8px;border:1px solid #444'>$password</td></tr>";
        $message .= "<tr><td style='padding:8px;border:1px solid #444;color:#aaa'>RRHH</td><td style='padding:8px;border:1px solid #444'>success.tuspeaking.com (user: $username)</td></tr>";
        $message .= "<tr><td style='padding:8px;border:1px solid #444;color:#aaa'>User ID</td><td style='padding:8px;border:1px solid #444'>$user_id</td></tr>";
        $message .= "</table>";
        if (!$learn_ok) {
            $message .= "<br><div style='background:#553;padding:10px;border-radius:8px;margin-top:10px'>⚠️ <strong>Learn no configurado automáticamente.</strong><br>Ejecutar en vls18488:<br><code>php /tmp/add_demo_learn.php $email</code></div>";
        }
        $messageType = 'success';

    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// === DESACTIVAR DEMO ===
if (isset($_POST['action']) && $_POST['action'] === 'deactivate_demo') {
    $demo_id = (int)$_POST['demo_id'];
    $pdo->prepare("UPDATE own_demos SET activo=0 WHERE id=?")->execute([$demo_id]);
    // Suspender usuario en Moodle
    $uid = $pdo->query("SELECT user_id FROM own_demos WHERE id=$demo_id")->fetchColumn();
    if ($uid) $pdo->exec("UPDATE mdl_user SET suspended=1 WHERE id=$uid");
    $message = "Demo desactivada.";
    $messageType = 'success';
}

// === CARGAR DEMOS ===
$demos = $pdo->query("SELECT * FROM own_demos ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/svg+xml" href="/app/moodle/brand/icons/favicon.svg">
<title>Gestor de Demos - tuSpeaking</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,sans-serif;background:linear-gradient(135deg,#1a1a2e,#16213e);min-height:100vh;padding:20px;color:#fff}
.container{max-width:1000px;margin:0 auto}
header{text-align:center;padding:30px;margin-bottom:30px;background:rgba(255,255,255,.05);border-radius:16px}
header h1{font-size:2em;margin-bottom:5px}
header p{opacity:.7}
a.back{color:#a5d6ff;text-decoration:none;display:inline-block;margin-bottom:20px}
a.back:hover{text-decoration:underline}

.card{background:rgba(255,255,255,.08);border-radius:12px;padding:25px;border:1px solid rgba(255,255,255,.1);margin-bottom:20px}
.card h2{font-size:1.3em;margin-bottom:20px;padding-bottom:10px;border-bottom:1px solid rgba(255,255,255,.1)}

.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}
.form-group{display:flex;flex-direction:column}
.form-group.full{grid-column:1/-1}
.form-group label{font-size:.85em;color:#aaa;margin-bottom:5px}
.form-group input,.form-group select{padding:10px 12px;border-radius:8px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.05);color:#fff;font-size:.95em}
.form-group input:focus{outline:none;border-color:#008BA3}
.form-group .hint{font-size:.75em;color:#888;margin-top:3px}

.btn{padding:12px 24px;border-radius:8px;border:none;cursor:pointer;font-size:1em;font-weight:600}
.btn-primary{background:#008BA3;color:#fff}
.btn-primary:hover{background:#00a0bb}
.btn-danger{background:#e74c3c;color:#fff;font-size:.8em;padding:6px 12px}
.btn-danger:hover{background:#c0392b}

.msg{padding:15px;border-radius:10px;margin-bottom:20px}
.msg.success{background:rgba(72,187,120,.15);border:1px solid rgba(72,187,120,.3)}
.msg.error{background:rgba(231,76,60,.15);border:1px solid rgba(231,76,60,.3)}
.msg code{background:rgba(255,255,255,.1);padding:2px 6px;border-radius:4px;font-size:.85em}

table{width:100%;border-collapse:collapse;margin-top:15px}
th{text-align:left;padding:10px;border-bottom:2px solid rgba(255,255,255,.15);color:#aaa;font-size:.85em}
td{padding:10px;border-bottom:1px solid rgba(255,255,255,.05);font-size:.9em}
tr:hover{background:rgba(255,255,255,.03)}
.badge{padding:3px 8px;border-radius:4px;font-size:.75em;font-weight:600}
.badge-active{background:rgba(72,187,120,.2);color:#48bb78}
.badge-inactive{background:rgba(231,76,60,.2);color:#e74c3c}
.badge-learn{background:rgba(0,139,163,.2);color:#00b4d8}
.badge-nlearn{background:rgba(255,165,0,.2);color:#ffa500}
</style>
</head>
<body>
<div class="container">
<a href="/app/moodle/admin-panel/" class="back">← Volver al Panel</a>
<header>
<h1>🎯 Gestor de Demos</h1>
<p>Crear y gestionar demos para empresas</p>
</header>

<?php if ($message): ?>
<div class="msg <?= $messageType ?>"><?= $message ?></div>
<?php endif; ?>

<!-- FORMULARIO CREAR -->
<div class="card">
<h2>➕ Crear Nueva Demo</h2>
<form method="post">
<input type="hidden" name="action" value="create_demo">
<div class="form-grid">
    <div class="form-group">
        <label>Nombre de la Empresa</label>
        <input type="text" name="empresa" placeholder="Victoria Shoes" required>
    </div>
    <div class="form-group">
        <label>Persona de Contacto</label>
        <input type="text" name="contacto" placeholder="Natalia Ramírez">
    </div>
    <div class="form-group">
        <label>Username Demo</label>
        <input type="text" name="username" placeholder="demo.natalia.victoria" required pattern="^demo\.[a-z\.]+$">
        <span class="hint">Formato: demo.nombre.empresa (sin espacios, minúsculas)</span>
    </div>
    <div class="form-group">
        <label>Clases Demo</label>
        <select name="num_clases">
            <option value="8" selected>8 clases</option>
            <option value="4">4 clases</option>
            <option value="12">12 clases</option>
        </select>
    </div>
    <div class="form-group">
        <label>Fecha Inicio</label>
        <input type="date" name="fecha_inicio" value="<?= date('Y-m-01', strtotime('+1 month')) ?>" required>
    </div>
    <div class="form-group">
        <label>Fecha Fin</label>
        <input type="date" name="fecha_fin" value="<?= date('Y-m-d', strtotime('+10 months')) ?>" required>
    </div>
    <div class="form-group full" style="margin-top:10px">
        <button type="submit" class="btn btn-primary">🚀 Crear Demo</button>
    </div>
</div>
</form>
</div>

<!-- DEMOS ACTIVAS -->
<div class="card">
<h2>📋 Demos Existentes</h2>
<?php if (empty($demos)): ?>
<p style="opacity:.5">No hay demos creadas todavía.</p>
<?php else: ?>
<table>
<thead>
<tr><th>Empresa</th><th>Contacto</th><th>Username</th><th>Clases</th><th>Periodo</th><th>Estado</th><th>Learn</th><th></th></tr>
</thead>
<tbody>
<?php foreach ($demos as $d): ?>
<tr>
<td><strong><?= htmlspecialchars($d['empresa']) ?></strong></td>
<td><?= htmlspecialchars($d['contacto']) ?></td>
<td><code><?= htmlspecialchars($d['username']) ?></code></td>
<td><?= $d['num_clases'] ?></td>
<td><?= date('d/m/y', strtotime($d['fecha_inicio'])) ?> → <?= date('d/m/y', strtotime($d['fecha_fin'])) ?></td>
<td><span class="badge <?= $d['activo'] ? 'badge-active' : 'badge-inactive' ?>"><?= $d['activo'] ? 'Activa' : 'Inactiva' ?></span></td>
<td><span class="badge <?= $d['learn_configurado'] ? 'badge-learn' : 'badge-nlearn' ?>"><?= $d['learn_configurado'] ? '✓' : 'Pendiente' ?></span></td>
<td>
<?php if ($d['activo']): ?>
<form method="post" style="display:inline" onsubmit="return confirm('¿Desactivar demo de <?= htmlspecialchars($d['empresa']) ?>?')">
<input type="hidden" name="action" value="deactivate_demo">
<input type="hidden" name="demo_id" value="<?= $d['id'] ?>">
<button type="submit" class="btn btn-danger">Desactivar</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>

</div>
</body>
</html>
