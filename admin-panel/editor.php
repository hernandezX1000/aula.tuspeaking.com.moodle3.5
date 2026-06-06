<?php
/**
 * Editor de Herramientas - Panel TuSpeaking
 * PROTEGIDO: Solo administradores de Moodle
 */

// === AUTENTICACIÓN MOODLE ===
require_once(__DIR__ . '/../config.php');
require_login();
$context = context_system::instance();
if (!has_capability('moodle/site:config', $context)) {
    die('<h1>⛔ Acceso denegado</h1><p>Solo administradores pueden acceder al editor.</p><a href="index.php">Volver al panel</a>');
}

require_once __DIR__ . '/functions.php';

$mensaje = '';
$error = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $cat = trim($_POST['categoria']);
        $name = trim($_POST['nombre']);
        $url = trim($_POST['url']);
        $icon = trim($_POST['icon']) ?: '🔗';
        $opts = array(
            'external' => !empty($_POST['external']),
            'new' => !empty($_POST['new']),
            'pending' => !empty($_POST['pending'])
        );
        
        if ($cat && $name && $url) {
            if (addTool($cat, $name, $url, $icon, $opts)) {
                $mensaje = "✅ Enlace añadido correctamente";
            } else {
                $error = "Error al guardar";
            }
        } else {
            $error = "Completa todos los campos obligatorios";
        }
    }
    
    if (isset($_POST['delete'])) {
        $cat = $_POST['del_cat'];
        $idx = (int)$_POST['del_idx'];
        if (removeTool($cat, $idx)) {
            $mensaje = "✅ Enlace eliminado";
        }
    }
    
    if (isset($_POST['new_category'])) {
        $catKey = preg_replace('/[^a-z0-9]/', '', strtolower($_POST['cat_key']));
        $catTitle = trim($_POST['cat_title']);
        $catOrder = (int)$_POST['cat_order'] ?: 99;
        
        if ($catKey && $catTitle) {
            $tools = loadTools();
            $tools[$catKey] = array('title' => $catTitle, 'order' => $catOrder, 'items' => array());
            if (saveTools($tools)) {
                $mensaje = "✅ Categoría '$catTitle' creada";
            }
        }
    }
}

$tools = loadTools();
uasort($tools, function($a, $b) {
    return (isset($a['order']) ? $a['order'] : 99) - (isset($b['order']) ? $b['order'] : 99);
});

$iconos = array('🔗','📊','📋','📁','📝','📅','🎥','💼','📧','🔧','✅','⚙️','📚','🎓','👤','👥','📥','🔍','🏷️','ℹ️','🌐','💬','📢','📱','🎯','🏆','➕','📜','✏️');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Editor Panel - TuSpeaking</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,sans-serif;background:#1a1a2e;min-height:100vh;padding:20px;color:#fff}
.container{max-width:1200px;margin:0 auto}
header{display:flex;justify-content:space-between;align-items:center;padding:20px;margin-bottom:20px;background:rgba(255,255,255,.05);border-radius:12px}
h1{font-size:1.5em}
a{color:#a5d6ff;text-decoration:none}
.user-info{font-size:.85em;opacity:.7}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.card{background:rgba(255,255,255,.08);border-radius:12px;padding:20px;border:1px solid rgba(255,255,255,.1)}
.card h2{font-size:1.1em;margin-bottom:15px;padding-bottom:10px;border-bottom:1px solid rgba(255,255,255,.1)}
.form-group{margin-bottom:15px}
.form-group label{display:block;margin-bottom:5px;font-size:.9em;opacity:.8}
.form-group input,.form-group select{width:100%;padding:10px;border-radius:6px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.1);color:#fff}
.form-group input:focus,.form-group select:focus{outline:none;border-color:#4299e1}
.checkbox-group{display:flex;gap:15px;margin:15px 0}
.checkbox-group label{display:flex;align-items:center;gap:5px;font-size:.9em}
.btn{padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-weight:500;width:100%}
.btn-primary{background:#4299e1;color:#fff}
.btn-danger{background:#e53e3e;color:#fff;padding:5px 10px;font-size:.8em;width:auto}
.btn-success{background:#48bb78;color:#fff}
.msg{padding:15px;border-radius:8px;margin-bottom:20px}
.msg.ok{background:rgba(72,187,120,.2);border:1px solid #48bb78}
.msg.err{background:rgba(229,62,62,.2);border:1px solid #e53e3e}
.tools-list{max-height:400px;overflow-y:auto}
.tool-item{display:flex;align-items:center;padding:8px;border-bottom:1px solid rgba(255,255,255,.05);gap:10px}
.tool-item:hover{background:rgba(255,255,255,.05)}
.tool-item .icon{font-size:1.2em}
.tool-item .name{flex:1}
.tool-item .url{font-size:.75em;opacity:.5;max-width:200px;overflow:hidden;text-overflow:ellipsis}
.badge{font-size:.65em;padding:2px 6px;border-radius:4px;margin-left:5px}
.badge.ext{background:#805ad5}
.badge.new{background:#48bb78}
.badge.pending{background:#718096}
.cat-header{background:rgba(0,0,0,.2);padding:10px;margin:10px 0 5px;border-radius:6px;font-weight:bold}
.icons-grid{display:flex;flex-wrap:wrap;gap:5px;margin:10px 0}
.icons-grid span{cursor:pointer;padding:5px;border-radius:4px}
.icons-grid span:hover{background:rgba(255,255,255,.2)}
@media(max-width:800px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">
<header>
    <div>
        <h1>✏️ Editor de Panel</h1>
        <div class="user-info">🔐 Conectado como: <?php echo fullname($USER); ?></div>
    </div>
    <a href="index.php">← Volver al Panel</a>
</header>

<?php if($mensaje): ?><div class="msg ok"><?php echo $mensaje; ?></div><?php endif; ?>
<?php if($error): ?><div class="msg err"><?php echo $error; ?></div><?php endif; ?>

<div class="grid">
    <div class="card">
        <h2>➕ Añadir Nuevo Enlace</h2>
        <form method="POST">
            <div class="form-group">
                <label>Categoría *</label>
                <select name="categoria" required>
                    <?php foreach($tools as $key => $cat): ?>
                    <option value="<?php echo $key; ?>"><?php echo $cat['title']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Nombre *</label>
                <input type="text" name="nombre" placeholder="Ej: Google Analytics" required>
            </div>
            <div class="form-group">
                <label>URL *</label>
                <input type="text" name="url" placeholder="https://..." required>
            </div>
            <div class="form-group">
                <label>Icono</label>
                <input type="text" name="icon" id="iconInput" placeholder="🔗" maxlength="4">
                <div class="icons-grid">
                    <?php foreach($iconos as $ic): ?>
                    <span onclick="document.getElementById('iconInput').value='<?php echo $ic; ?>'"><?php echo $ic; ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="checkbox-group">
                <label><input type="checkbox" name="external"> Externo ↗</label>
                <label><input type="checkbox" name="new"> Nuevo</label>
                <label><input type="checkbox" name="pending"> Pendiente</label>
            </div>
            <button type="submit" name="add" class="btn btn-primary">Añadir Enlace</button>
        </form>
        
        <hr style="margin:20px 0;border-color:rgba(255,255,255,.1)">
        
        <h2>📁 Nueva Categoría</h2>
        <form method="POST">
            <div class="form-group">
                <label>ID (sin espacios) *</label>
                <input type="text" name="cat_key" placeholder="mi_categoria" required>
            </div>
            <div class="form-group">
                <label>Título *</label>
                <input type="text" name="cat_title" placeholder="🆕 Mi Categoría" required>
            </div>
            <div class="form-group">
                <label>Orden (1-99)</label>
                <input type="number" name="cat_order" value="50" min="1" max="99">
            </div>
            <button type="submit" name="new_category" class="btn btn-success">Crear Categoría</button>
        </form>
    </div>
    
    <div class="card">
        <h2>📋 Herramientas Actuales</h2>
        <div class="tools-list">
            <?php foreach($tools as $catKey => $cat): ?>
            <div class="cat-header"><?php echo $cat['title']; ?> (<?php echo count($cat['items']); ?>)</div>
            <?php foreach($cat['items'] as $idx => $item): ?>
            <div class="tool-item">
                <span class="icon"><?php echo $item['icon']; ?></span>
                <span class="name">
                    <?php echo $item['name']; ?>
                    <?php if(!empty($item['external'])): ?><span class="badge ext">↗</span><?php endif; ?>
                    <?php if(!empty($item['new'])): ?><span class="badge new">Nuevo</span><?php endif; ?>
                    <?php if(!empty($item['pending'])): ?><span class="badge pending">Pendiente</span><?php endif; ?>
                </span>
                <span class="url"><?php echo $item['url']; ?></span>
                <form method="POST" style="margin:0" onsubmit="return confirm('¿Eliminar este enlace?')">
                    <input type="hidden" name="del_cat" value="<?php echo $catKey; ?>">
                    <input type="hidden" name="del_idx" value="<?php echo $idx; ?>">
                    <button type="submit" name="delete" class="btn btn-danger">✕</button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card" style="margin-top:20px">
    <h2>📂 Info del Sistema</h2>
    <p style="font-size:.9em;opacity:.8">
        <strong>Archivo de configuración:</strong> <code><?php echo TOOLS_FILE; ?></code><br>
        <strong>Total categorías:</strong> <?php echo count($tools); ?><br>
        <strong>Total enlaces:</strong> <?php echo array_sum(array_map(function($c) { return count($c['items']); }, $tools)); ?>
    </p>
</div>
</div>
</body>
</html>
