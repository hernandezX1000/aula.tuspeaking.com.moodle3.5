<?php
$s = $_GET['s'] ?? 'overview';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Documentación - Panel Reportes</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--primary:#008ba3;--light:#f8f9fa;--dark:#2c3e50;--gray:#6c757d;--border:#dee2e6}
body{font-family:-apple-system,sans-serif;background:var(--light);display:flex}
.sidebar{width:250px;background:#fff;border-right:1px solid var(--border);min-height:100vh;position:fixed}
.sidebar h2{padding:20px;color:var(--primary);font-size:1.1rem;border-bottom:1px solid var(--border)}
.nav a{display:block;padding:10px 20px;color:var(--dark);text-decoration:none}
.nav a:hover,.nav a.active{background:#e3f2fd;color:var(--primary)}
.nav-title{padding:10px 20px;font-size:.7rem;color:var(--gray);text-transform:uppercase}
.main{margin-left:250px;padding:40px;max-width:900px}
h1{color:var(--dark);margin-bottom:10px}
h2{color:var(--dark);margin:25px 0 15px;border-bottom:2px solid var(--primary);padding-bottom:8px}
p{line-height:1.7;margin-bottom:15px;color:#444}
table{width:100%;border-collapse:collapse;margin:15px 0}
th,td{padding:10px;border-bottom:1px solid var(--border);text-align:left}
th{background:var(--light)}
code{background:#f1f1f1;padding:2px 6px;border-radius:3px}
pre{background:#2d3748;color:#e2e8f0;padding:15px;border-radius:6px;overflow-x:auto;margin:15px 0}
.badge{display:inline-block;padding:3px 8px;border-radius:10px;font-size:.75rem}
.badge-ok{background:#d4edda;color:#155724}
.badge-warn{background:#fff3cd;color:#856404}
.btn{display:inline-block;padding:8px 16px;background:var(--primary);color:#fff;text-decoration:none;border-radius:5px;margin-top:20px}
</style>
</head>
<body>
<nav class="sidebar">
<h2><i class="fas fa-book"></i> Documentación</h2>
<div class="nav">
<div class="nav-title">General</div>
<a href="?s=overview" class="<?=$s=='overview'?'active':''?>">📋 Resumen</a>
<a href="?s=arquitectura" class="<?=$s=='arquitectura'?'active':''?>">🏗️ Arquitectura</a>
<div class="nav-title">Reportes</div>
<a href="?s=finalizacion" class="<?=$s=='finalizacion'?'active':''?>">✅ Estado Finalización</a>
<a href="?s=zoom" class="<?=$s=='zoom'?'active':''?>">📹 Clases Zoom</a>
<a href="?s=progreso" class="<?=$s=='progreso'?'active':''?>">📊 Progreso Combinado</a>
<a href="?s=dedicacion" class="<?=$s=='dedicacion'?'active':''?>">⏱️ Dedicación</a>
<a href="?s=empresa" class="<?=$s=='empresa'?'active':''?>">🏢 Resumen Empresa</a>
<div class="nav-title">Otros</div>
<a href="?s=legacy" class="<?=$s=='legacy'?'active':''?>">📦 Legacy</a>
<a href="?s=colores" class="<?=$s=='colores'?'active':''?>">🎨 Colores</a>
<a href="?s=changelog" class="<?=$s=='changelog'?'active':''?>">📝 Changelog</a>
<a href="admin.php" style="margin-top:20px;color:var(--primary)">← Volver al Panel</a>
</div>
</nav>
<main class="main">
<?php if($s=='overview'): ?>
<h1>📋 Panel de Reportes</h1>
<p>Sistema centralizado de generación de reportes empresariales - tuSpeaking 2026</p>
<h2>Tipos de Reportes</h2>
<table>
<tr><th>Tipo</th><th>Descripción</th><th>Estado</th></tr>
<tr><td>✅ Estado Finalización</td><td>Progreso actividades plataforma</td><td><span class="badge badge-ok">OK</span></td></tr>
<tr><td>📹 Clases Zoom</td><td>Asistencia clases conversación</td><td><span class="badge badge-ok">OK</span></td></tr>
<tr><td>📊 Progreso Combinado</td><td>Plataforma + Clases + Bonificación</td><td><span class="badge badge-ok">OK</span></td></tr>
<tr><td>⏱️ Dedicación Total</td><td>Tiempo plataforma + Zoom</td><td><span class="badge badge-ok">OK</span></td></tr>
<tr><td>🏢 Resumen Empresa</td><td>Consolidado todos los alumnos</td><td><span class="badge badge-ok">OK</span></td></tr>
<tr><td>👨‍🏫 Asistencia Profesores</td><td>Control profesores</td><td><span class="badge badge-warn">Parcial</span></td></tr>
<tr><td>📋 FUNDAE</td><td>Formato bonificación</td><td><span class="badge badge-warn">Parcial</span></td></tr>
</table>

<?php elseif($s=='arquitectura'): ?>
<h1>🏗️ Arquitectura</h1>
<pre>
Panel Empresas (/empresas/admin.php)
    │
    ├── Panel Feedback (/feedback/admin.php)
    │
    └── Panel Reportes (/reportes/admin.php)  ← ACTUAL
        ├── admin.php
        └── documentacion.php
</pre>
<h2>Flujo de Datos</h2>
<pre>CSV / BD → Procesar → Generar Excel → Descargar</pre>

<?php elseif($s=='finalizacion'): ?>
<h1>✅ Estado de Finalización</h1>
<p>Progreso de actividades en plataforma por alumno.</p>
<h2>Entrada (CSV Moodle)</h2>
<p>Exportar: <code>Curso → Calificaciones → Exportar → CSV</code></p>
<h2>Salida (Excel)</h2>
<table>
<tr><th>Columna</th><th>Descripción</th></tr>
<tr><td>Actividad</td><td>Nombre de la actividad</td></tr>
<tr><td>Estado</td><td>Finalizado / No finalizado</td></tr>
<tr><td>Resumen</td><td>Total, Finalizadas, % Finalización</td></tr>
</table>

<?php elseif($s=='zoom'): ?>
<h1>📹 Clases Zoom</h1>
<p>Asistencia a clases de conversación.</p>
<h2>Entrada (CSV Zoom)</h2>
<p>Descargar: <code>Zoom Admin → Reports → Meeting List Details</code></p>
<h2>Lógica</h2>
<p><strong>Clase válida:</strong> Participantes >= 2 (profesor + alumno)</p>
<h2>Salida</h2>
<p>Hoja 1: Resumen (clases, minutos, horas por profesor)<br>
Hoja 2: Detalle (fecha, hora, profesor, duración)</p>

<?php elseif($s=='progreso'): ?>
<h1>📊 Progreso Combinado</h1>
<p>Combina plataforma + clases + bonificación.</p>
<h2>Fórmulas</h2>
<pre>
% Asistencia = Clases Realizadas / Clases Previstas × 100
% Progreso = Actividades Completadas / Total × 100
% Global = (% Asistencia + % Progreso) / 2
Bonifica = Sí si % Global >= 75%
</pre>

<?php elseif($s=='dedicacion'): ?>
<h1>⏱️ Dedicación Total</h1>
<p>Tiempo total dedicado al curso.</p>
<h2>Componentes</h2>
<table>
<tr><td>Tiempo Plataforma</td><td>Moodle logs o manual</td></tr>
<tr><td>Tiempo Zoom</td><td>CSV reuniones</td></tr>
<tr><td>Total</td><td>Plataforma + Zoom</td></tr>
</table>
<pre>% Dedicación = Tiempo Total / Duración Estimada × 100</pre>

<?php elseif($s=='empresa'): ?>
<h1>🏢 Resumen Empresa</h1>
<p>Consolidado de todos los alumnos.</p>
<h2>Contenido</h2>
<ul>
<li>Tabla con métricas por alumno</li>
<li>Fila de totales/promedios</li>
<li>Estadísticas globales</li>
<li>Tasa de bonificación</li>
</ul>

<?php elseif($s=='legacy'): ?>
<h1>📦 Reportes Legacy</h1>
<table>
<tr><th>Nombre</th><th>URL</th></tr>
<tr><td>Asistencia Alumnos CESCE</td><td><code>/reporte_cesce.php</code></td></tr>
<tr><td>Asistencia Profesores CESCE</td><td><code>/reporte_profesores_cesce.php</code></td></tr>
<tr><td>FUNDAE CESCE</td><td><code>/reporte_fundae_cesce.php</code></td></tr>
<tr><td>Reporte 1 a 1</td><td><code>/reporte_1to1.php</code></td></tr>
<tr><td>Reporte Empresas</td><td><code>/reporte_empresas.php</code></td></tr>
</table>

<?php elseif($s=='colores'): ?>
<h1>🎨 Código de Colores</h1>
<table>
<tr><td style="background:#C6EFCE">Verde #C6EFCE</td><td>>= 75%</td><td>Bueno</td></tr>
<tr><td style="background:#FFEB9C">Amarillo #FFEB9C</td><td>50-74%</td><td>Regular</td></tr>
<tr><td style="background:#FFC7CE">Rojo #FFC7CE</td><td>< 50%</td><td>Bajo</td></tr>
</table>

<?php elseif($s=='changelog'): ?>
<h1>📝 Changelog</h1>
<h2>8 Enero 2026 - v1.0</h2>
<ul>
<li>✅ Panel de Reportes creado</li>
<li>✅ 7 tipos de reportes definidos</li>
<li>✅ Reportes Hyatt generados (Agustina, Luisa)</li>
<li>✅ Documentación inicial</li>
</ul>
<h2>Pendientes</h2>
<ul>
<li>Lógica generación desde CSV</li>
<li>Histórico de reportes</li>
<li>Auditoría CSV vs BD</li>
</ul>

<?php else: ?>
<h1>Sección no encontrada</h1>
<a href="?s=overview" class="btn">Ir al Resumen</a>
<?php endif; ?>
</main>
</body>
</html>
