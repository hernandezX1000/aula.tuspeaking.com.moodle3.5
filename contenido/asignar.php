<?php
require_once __DIR__ . '/config.php';
$db = getDB();

$usuarioId = $_GET['usuario'] ?? null;
$cursoId = $_GET['curso'] ?? $_GET['curso_destino'] ?? null;

$categorias = $db->query("SELECT id,name,coursecount FROM mdl_course_categories WHERE coursecount>0 ORDER BY name")->fetchAll();

$msg = '';
$msgTipo = '';
$logImport = [];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $curso_destino_id = intval($_POST['curso_destino_id'] ?? 0);
    $contenidos = $_POST['contenidos'] ?? [];
    $importar_ahora = isset($_POST['importar_ahora']);
    
    if (empty($contenidos)) {
        $msg = 'Selecciona al menos una sección';
        $msgTipo = 'error';
    } elseif (!$curso_destino_id) {
        $msg = 'Selecciona un curso destino';
        $msgTipo = 'error';
    } else {
        if ($importar_ahora) {
            // Importación real
            require_once __DIR__ . '/importar.php';
            
            $total_modulos = 0;
            $total_secciones = 0;
            
            foreach ($contenidos as $c) {
                list($tipo, $id) = explode('_', $c);
                if ($tipo === 'seccion') {
                    $resultado = duplicar_seccion_a_curso($id, $curso_destino_id);
                    $total_modulos += $resultado['modulos_copiados'];
                    $total_secciones++;
                    $logImport = array_merge($logImport, $resultado['log']);
                }
            }
            
            $msg = "✓ Importación completada: {$total_secciones} secciones, {$total_modulos} módulos copiados";
            $msgTipo = 'success';
            
            // Guardar registro
            try {
                $stmt = $db->prepare("INSERT INTO mdl_contenido_asignaciones 
                    (contenido_tipo,contenido_id,contenido_nombre,curso_origen_id,destino_tipo,destino_id,asignado_por,notas,estado,fecha_importacion)
                    VALUES ('importacion',:cid,:nombre,:origen,'curso',:did,2,:notas,'importado',NOW())");
                $stmt->execute([
                    ':cid' => $total_modulos,
                    ':nombre' => "{$total_secciones} secciones, {$total_modulos} módulos",
                    ':origen' => $_POST['curso_origen_id'] ?? 0,
                    ':did' => $curso_destino_id,
                    ':notas' => $_POST['notas'] ?? ''
                ]);
            } catch(Exception $e) {}
            
        } else {
            // Solo guardar como pendiente
            try {
                $stmt = $db->prepare("INSERT INTO mdl_contenido_asignaciones 
                    (contenido_tipo,contenido_id,contenido_nombre,curso_origen_id,destino_tipo,destino_id,asignado_por,notas,estado)
                    VALUES (:tipo,:cid,:nombre,:origen,'curso',:did,2,:notas,'pendiente')");
                
                foreach ($contenidos as $c) {
                    list($tipo,$id) = explode('_',$c);
                    $nombre = $db->query("SELECT name FROM mdl_course_sections WHERE id=$id")->fetchColumn();
                    $stmt->execute([
                        ':tipo'=>$tipo,':cid'=>$id,':nombre'=>$nombre ?: "Sección #$id",
                        ':origen'=>$_POST['curso_origen_id']??0,
                        ':did'=>$curso_destino_id,
                        ':notas'=>$_POST['notas']??''
                    ]);
                }
                $msg = '✓ Guardado como pendiente (' . count($contenidos) . ' secciones)';
                $msgTipo = 'success';
            } catch(Exception $e) {
                $msg = 'Error: '.$e->getMessage();
                $msgTipo = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignar Contenido - <?=h(APP_NAME)?></title>
    <link rel="stylesheet" href="assets/contenido.css">
    <style>
        .selector-grupo{background:#f8f9fa;padding:1rem;border-radius:8px;margin-bottom:1rem}
        .selector-grupo>label{font-weight:600;margin-bottom:.5rem;display:block}
        .filtro-input{width:100%;padding:.6rem;border:1px solid #ddd;border-radius:6px;margin-bottom:.5rem}
        .selector-doble{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .curso-seleccionado{background:#d4edda;padding:.75rem 1rem;border-radius:6px;margin-top:.5rem;display:none}
        .curso-seleccionado.visible{display:block}
        select.lista-grande{width:100%;height:180px;border:1px solid #ddd;border-radius:6px}
        .alert-error{background:#f8d7da;color:#721c24}
        .alert-success{background:#d4edda;color:#155724}
        .import-option{background:#e7f3ff;padding:1rem;border-radius:8px;margin-top:1rem;border:1px solid #b6d4fe}
        .import-log{background:#f8f9fa;padding:1rem;border-radius:8px;margin-top:1rem;max-height:200px;overflow-y:auto;font-family:monospace;font-size:.85rem}
        .import-log div{padding:2px 0}
        .warning-box{background:#fff3cd;border:1px solid #ffc107;padding:1rem;border-radius:8px;margin-bottom:1rem}
    </style>
</head>
<body>
    <header class="header">
        <h1>➕ Copiar Contenido entre Cursos</h1>
        <a href="index.php" class="btn btn-outline">← Volver</a>
    </header>
    
    <div class="container">
        <?php if($msg): ?>
        <div class="alert alert-<?=$msgTipo?>"><?=h($msg)?></div>
        <?php if(!empty($logImport)): ?>
        <div class="import-log">
            <?php foreach($logImport as $l): ?>
            <div><?=h($l)?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <div class="warning-box">
            ⚠️ <strong>Nota:</strong> Los módulos tipo LTI no se copian automáticamente por limitaciones técnicas.
        </div>
        
        <div class="form-card">
            <form method="post" id="formAsignar">
                <div class="form-section">
                    <h3>1. Curso DESTINO (donde copiar)</h3>
                    <div class="selector-grupo">
                        <div class="selector-doble">
                            <div>
                                <label>① Categoría:</label>
                                <input type="text" class="filtro-input" id="filtroCatDest" placeholder="🔍 Buscar..." onkeyup="filtrar('filtroCatDest','catDest')">
                                <select id="catDest" class="lista-grande" onchange="cargarCursos('catDest','cursosDest','infoDest')">
                                    <option value="">-- Seleccionar --</option>
                                    <?php foreach($categorias as $cat): ?>
                                    <option value="<?=$cat['id']?>"><?=h($cat['name'])?> (<?=$cat['coursecount']?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>② Curso:</label>
                                <input type="text" class="filtro-input" id="filtroCursoDest" placeholder="🔍 Buscar..." onkeyup="filtrar('filtroCursoDest','cursosDest')">
                                <select name="curso_destino_id" id="cursosDest" class="lista-grande" onchange="mostrarInfo('cursosDest','infoDest')">
                                    <option value="">-- Primero elige categoría --</option>
                                </select>
                            </div>
                        </div>
                        <div id="infoDest" class="curso-seleccionado">✓ <strong id="nombreDest"></strong></div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>2. Curso ORIGEN (de donde copiar)</h3>
                    <div class="selector-grupo">
                        <div class="selector-doble">
                            <div>
                                <label>① Categoría:</label>
                                <input type="text" class="filtro-input" id="filtroCatOrig" placeholder="🔍 Buscar..." onkeyup="filtrar('filtroCatOrig','catOrig')">
                                <select id="catOrig" class="lista-grande" onchange="cargarCursos('catOrig','cursosOrig','infoOrig')">
                                    <option value="">-- Seleccionar --</option>
                                    <?php foreach($categorias as $cat): ?>
                                    <option value="<?=$cat['id']?>"><?=h($cat['name'])?> (<?=$cat['coursecount']?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>② Curso:</label>
                                <input type="text" class="filtro-input" id="filtroCursoOrig" placeholder="🔍 Buscar..." onkeyup="filtrar('filtroCursoOrig','cursosOrig')">
                                <select name="curso_origen_id" id="cursosOrig" class="lista-grande" onchange="mostrarInfo('cursosOrig','infoOrig'); cargarSecciones();">
                                    <option value="">-- Primero elige categoría --</option>
                                </select>
                            </div>
                        </div>
                        <div id="infoOrig" class="curso-seleccionado">✓ <strong id="nombreOrig"></strong></div>
                    </div>
                    
                    <div class="form-group" style="margin-top:1rem">
                        <label><strong>Secciones a copiar:</strong></label>
                        <div id="secciones" class="content-tree">
                            <p class="text-muted">Selecciona un curso origen</p>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>3. Opciones</h3>
                    <div class="form-group">
                        <label>Notas:</label>
                        <textarea name="notas" rows="2"></textarea>
                    </div>
                    <div class="import-option">
                        <label style="cursor:pointer;display:flex;align-items:center;gap:.5rem">
                            <input type="checkbox" name="importar_ahora" value="1" checked>
                            <strong>⚡ Importar ahora</strong>
                        </label>
                        <small style="color:#666;margin-left:1.5rem">Si desmarcas, solo se guarda como pendiente</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="btn btn-outline">Cancelar</a>
                    <button type="submit" class="btn btn-success" id="btnSubmit">✓ Copiar Contenido</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    function filtrar(inpId,selId){
        const f=document.getElementById(inpId).value.toLowerCase();
        const s=document.getElementById(selId);
        for(let i=1;i<s.options.length;i++)s.options[i].style.display=s.options[i].text.toLowerCase().includes(f)?'':'none';
    }
    function cargarCursos(catId,cursosId,infoId){
        const v=document.getElementById(catId).value;
        const c=document.getElementById(cursosId);
        document.getElementById(infoId).classList.remove('visible');
        if(!v){c.innerHTML='<option value="">-- Primero elige categoría --</option>';return;}
        c.innerHTML='<option>Cargando...</option>';
        fetch('api.php?action=cursos_categoria&cat_id='+v).then(r=>r.json()).then(d=>{
            let h='<option value="">-- Seleccionar ('+d.cursos.length+') --</option>';
            d.cursos.forEach(x=>h+='<option value="'+x.id+'">'+x.fullname+'</option>');
            c.innerHTML=h;
        });
    }
    function mostrarInfo(selId,infoId){
        const s=document.getElementById(selId);
        const d=document.getElementById(infoId);
        if(s.value&&s.selectedIndex>0){
            document.getElementById(infoId.replace('info','nombre').replace('Info','Nombre')).textContent=s.options[s.selectedIndex].text;
            d.classList.add('visible');
        }else d.classList.remove('visible');
    }
    function cargarSecciones(){
        const id=document.getElementById('cursosOrig').value;
        const t=document.getElementById('secciones');
        if(!id){t.innerHTML='<p class="text-muted">Selecciona un curso origen</p>';return;}
        t.innerHTML='<p>⏳ Cargando...</p>';
        fetch('api.php?action=contenido_curso&id='+id).then(r=>r.json()).then(d=>{
            if(d.success&&d.secciones&&d.secciones.length>0){
                let h='<label class="tree-item" style="background:#e9ecef"><input type="checkbox" onchange="toggleAll(this)"> <strong>Seleccionar todas</strong></label>';
                d.secciones.forEach(s=>{
                    const n=s.name||'Sección '+s.section;
                    h+='<label class="tree-item"><input type="checkbox" name="contenidos[]" value="seccion_'+s.id+'" class="cb"> 📁 '+n+'</label>';
                });
                t.innerHTML=h;
            }else t.innerHTML='<p class="text-muted">Sin secciones</p>';
        });
    }
    function toggleAll(c){document.querySelectorAll('.cb').forEach(x=>x.checked=c.checked);}
    document.getElementById('formAsignar').onsubmit=function(){
        if(!document.getElementById('cursosDest').value){alert('Selecciona curso destino');return false;}
        if(!document.querySelectorAll('.cb:checked').length){alert('Selecciona al menos una sección');return false;}
        document.getElementById('btnSubmit').disabled=true;
        document.getElementById('btnSubmit').textContent='⏳ Importando...';
        return true;
    };
    </script>
</body>
</html>
