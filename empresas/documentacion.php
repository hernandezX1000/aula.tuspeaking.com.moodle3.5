<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Documentación - Panel Empresas | tuSpeaking</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f5f5f5;display:flex;min-height:100vh}
.sidebar{width:260px;background:#1a1a2e;color:white;padding:20px 0;position:fixed;height:100vh;overflow-y:auto}
.sidebar-header{padding:15px 20px;border-bottom:1px solid rgba(255,255,255,0.1)}
.sidebar-header h1{font-size:18px;font-weight:300;color:#00bcd4}
.sidebar-header small{color:#666;font-size:11px}
.sidebar-section{padding:15px 20px 5px;color:#00bcd4;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:1px}
.sidebar nav a{display:block;padding:10px 20px;color:rgba(255,255,255,0.7);text-decoration:none;font-size:14px;transition:all 0.2s}
.sidebar nav a:hover{background:rgba(255,255,255,0.05);color:white}
.sidebar nav a.active{color:#00bcd4;border-left:3px solid #00bcd4;background:rgba(0,188,212,0.1)}
.main{margin-left:260px;flex:1;padding:30px 40px;max-width:900px}
.back-link{color:#00bcd4;text-decoration:none;display:flex;align-items:center;gap:5px;font-size:14px;margin-bottom:20px}
.back-link:hover{text-decoration:underline}
h1.title{color:#00bcd4;font-size:28px;font-weight:400;margin-bottom:5px}
.subtitle{color:#666;font-size:14px;margin-bottom:30px}
h2{color:#333;font-size:20px;margin:30px 0 15px;padding-bottom:10px;border-bottom:2px solid #eee}
h3{color:#555;font-size:16px;margin:20px 0 10px}
.card{background:white;border-radius:8px;padding:25px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.1)}
.card h3{margin-top:0;color:#333;font-size:18px}
.card p{color:#666;line-height:1.6}
table{width:100%;border-collapse:collapse;margin:15px 0}
th,td{padding:12px 15px;text-align:left;border-bottom:1px solid #eee}
th{background:#f8f9fa;font-weight:600;color:#333;font-size:13px}
td{color:#555;font-size:14px}
.kpis{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;margin:20px 0}
.kpi{background:white;border-radius:8px;padding:25px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.1)}
.kpi-label{color:#666;font-size:14px;margin-bottom:10px}
.kpi-value{font-size:32px;font-weight:700;color:#00bcd4}
.kpi-desc{color:#999;font-size:12px;margin-top:5px}
ul{margin:10px 0;padding-left:20px}
li{color:#555;margin:8px 0;line-height:1.5}
code{background:#f5f5f5;padding:2px 6px;border-radius:3px;font-size:13px;color:#e91e63}
.badge{display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600}
.badge-success{background:#d4edda;color:#155724}
.section{scroll-margin-top:20px}
</style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-header">
        <h1>tuSpeaking</h1>
        <small>Panel Empresas v1.0</small>
    </div>
    
    <div class="sidebar-section">General</div>
    <nav>
        <a href="#resumen" class="active">Resumen Ejecutivo</a>
        <a href="#arquitectura">Arquitectura</a>
        <a href="#urls">URLs y Accesos</a>
    </nav>
    
    <div class="sidebar-section">Base de Datos</div>
    <nav>
        <a href="#tablas">Tablas</a>
        <a href="#relaciones">Relaciones</a>
    </nav>
    
    <div class="sidebar-section">Funcionalidades</div>
    <nav>
        <a href="#dashboard">Dashboard</a>
        <a href="#empresas">Gestión Empresas</a>
        <a href="#ediciones">Gestión Ediciones</a>
        <a href="#cursos">Creación Cursos</a>
    </nav>
    
    <div class="sidebar-section">Configuración</div>
    <nav>
        <a href="#nomenclatura">Nomenclaturas</a>
        <a href="#moodle">Integración Moodle</a>
    </nav>
</aside>

<main class="main">
    <a href="admin.php" class="back-link">← Volver al Panel</a>
    
    <h1 class="title">Documentación Técnica</h1>
    <p class="subtitle">Panel de Gestión de Empresas - tuSpeaking | Actualizado: 07/01/2026</p>
    
    <!-- RESUMEN -->
    <section id="resumen" class="section">
        <h2>1. Resumen Ejecutivo</h2>
        <div class="card">
            <h3>Objetivo</h3>
            <p>Sistema completo de gestión de empresas, ediciones y cursos para la plataforma tuSpeaking. 
               Permite crear y administrar la estructura organizativa de los clientes corporativos, 
               incluyendo la creación automatizada de categorías y cursos en Moodle.</p>
        </div>
        
        <div class="kpis">
            <div class="kpi">
                <div class="kpi-label">Empresas Activas</div>
                <div class="kpi-value">10</div>
                <div class="kpi-desc">Clientes corporativos</div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Ediciones</div>
                <div class="kpi-value">17</div>
                <div class="kpi-desc">Períodos formativos</div>
            </div>
        </div>
    </section>
    
    <!-- ARQUITECTURA -->
    <section id="arquitectura" class="section">
        <h2>2. Arquitectura del Sistema</h2>
        <div class="card">
            <h3>Jerarquía de Datos</h3>
            <table>
                <tr><th>Nivel</th><th>Tabla</th><th>Descripción</th></tr>
                <tr><td>1. Empresa</td><td><code>own_empresas</code></td><td>Datos del cliente corporativo</td></tr>
                <tr><td>2. Edición</td><td><code>own_empresa_ediciones</code></td><td>Períodos formativos con fechas</td></tr>
                <tr><td>3. Categoría</td><td><code>mdl_course_categories</code></td><td>Contenedor Moodle de cursos</td></tr>
                <tr><td>4. Curso</td><td><code>mdl_course</code></td><td>Cursos individuales</td></tr>
            </table>
        </div>
    </section>
    
    <!-- URLS -->
    <section id="urls" class="section">
        <h2>3. URLs y Accesos</h2>
        <div class="card">
            <table>
                <tr><th>Función</th><th>URL</th></tr>
                <tr><td>Panel Empresas</td><td><code>https://aula.tuspeaking.com/empresas/admin.php</code></td></tr>
                <tr><td>Dashboard</td><td><code>...admin.php?s=dashboard</code></td></tr>
                <tr><td>Empresas</td><td><code>...admin.php?s=empresas</code></td></tr>
                <tr><td>Ediciones</td><td><code>...admin.php?s=ediciones</code></td></tr>
                <tr><td>Panel Feedback</td><td><code>https://aula.tuspeaking.com/feedback/admin.php</code></td></tr>
                <tr><td>Course-Acuity</td><td><code>https://aula.tuspeaking.com/courseacuity.php</code></td></tr>
            </table>
        </div>
    </section>
    
    <!-- TABLAS -->
    <section id="tablas" class="section">
        <h2>4. Tablas de Base de Datos</h2>
        
        <div class="card">
            <h3>own_empresas</h3>
            <p>Almacena los datos de las empresas cliente.</p>
            <table>
                <tr><th>Campo</th><th>Tipo</th><th>Descripción</th></tr>
                <tr><td>id</td><td>BIGINT PK</td><td>Identificador único</td></tr>
                <tr><td>nombre</td><td>VARCHAR(200)</td><td>Nombre de la empresa</td></tr>
                <tr><td>dominio</td><td>VARCHAR(100)</td><td>Dominio email (empresa.com)</td></tr>
                <tr><td>contacto_nombre</td><td>VARCHAR(200)</td><td>Nombre del contacto</td></tr>
                <tr><td>contacto_email</td><td>VARCHAR(200)</td><td>Email del contacto</td></tr>
                <tr><td>activo</td><td>TINYINT(1)</td><td>1=Activa, 0=Inactiva</td></tr>
            </table>
        </div>
        
        <div class="card">
            <h3>own_empresa_ediciones</h3>
            <p>Relaciona empresas con categorías Moodle y define períodos.</p>
            <table>
                <tr><th>Campo</th><th>Tipo</th><th>Descripción</th></tr>
                <tr><td>id</td><td>BIGINT PK</td><td>Identificador único</td></tr>
                <tr><td>empresa_id</td><td>BIGINT FK</td><td>Referencia a own_empresas</td></tr>
                <tr><td>categoria_id</td><td>BIGINT</td><td>ID categoría Moodle</td></tr>
                <tr><td>fecha_inicio</td><td>DATE</td><td>Inicio del período</td></tr>
                <tr><td>fecha_fin</td><td>DATE</td><td>Fin del período</td></tr>
                <tr><td>objetivo_cobertura</td><td>INT</td><td>% objetivo cobertura</td></tr>
            </table>
        </div>
    </section>
    
    <!-- RELACIONES -->
    <section id="relaciones" class="section">
        <h2>5. Relaciones</h2>
        <div class="card">
            <p>Diagrama de relaciones entre tablas:</p>
            <pre style="background:#f8f9fa;padding:15px;border-radius:4px;font-size:13px;overflow-x:auto">
own_empresas (Empresa)
    └── own_empresa_ediciones (Edición)
            └── mdl_course_categories (Categoría Moodle)
                    └── mdl_course (Cursos)
                            └── own_acuity_course (Config Acuity)
            </pre>
        </div>
    </section>
    
    <!-- DASHBOARD -->
    <section id="dashboard" class="section">
        <h2>6. Dashboard</h2>
        <div class="card">
            <h3>KPIs Principales</h3>
            <ul>
                <li><strong>Empresas Activas:</strong> Total de empresas con estado activo</li>
                <li><strong>Ediciones:</strong> Total de ediciones/períodos formativos</li>
                <li><strong>Cursos Configurados:</strong> Cursos vinculados con Acuity</li>
            </ul>
        </div>
    </section>
    
    <!-- EMPRESAS -->
    <section id="empresas" class="section">
        <h2>7. Gestión de Empresas</h2>
        <div class="card">
            <h3>Funcionalidades</h3>
            <ul>
                <li>Crear nueva empresa con nombre, dominio y contacto</li>
                <li>Editar datos de empresa existente</li>
                <li>Activar/Desactivar empresas</li>
            </ul>
        </div>
    </section>
    
    <!-- EDICIONES -->
    <section id="ediciones" class="section">
        <h2>8. Gestión de Ediciones</h2>
        <div class="card">
            <h3>Funcionalidades</h3>
            <ul>
                <li>Vista agrupada por empresa (acordeón expandible)</li>
                <li>Asociar categorías Moodle existentes a empresas</li>
                <li>Crear nuevas categorías en Moodle desde el panel</li>
                <li>Definir fechas inicio/fin de cada edición</li>
                <li>Botón [+] para crear cursos en cada categoría</li>
            </ul>
        </div>
    </section>
    
    <!-- CURSOS -->
    <section id="cursos" class="section">
        <h2>9. Creación de Cursos</h2>
        <div class="card">
            <h3>Configuración Automática</h3>
            <p>Al crear un curso desde el panel se configura automáticamente:</p>
            <table>
                <tr><th>Configuración</th><th>Valor</th></tr>
                <tr><td>Nomenclatura</td><td><code>[Idioma] [Nivel] - #[ID]</code></td></tr>
                <tr><td>Formato</td><td>Mosaicos (tiles)</td></tr>
                <tr><td>Color</td><td>Turquesa (#00bcd4)</td></tr>
                <tr><td>Secciones</td><td>5 (1 general + 4 mosaicos)</td></tr>
                <tr><td>Fechas</td><td>Heredadas de la edición</td></tr>
            </table>
        </div>
        
        <div class="card">
            <h3>Idiomas Disponibles</h3>
            <p>Inglés, Español, Francés, Alemán, Portugués, Italiano, Catalán</p>
            
            <h3 style="margin-top:20px">Niveles Disponibles</h3>
            <p>A1, A2, B1, B1.2, B2, B2.2, C1, C2</p>
        </div>
    </section>
    
    <!-- NOMENCLATURA -->
    <section id="nomenclatura" class="section">
        <h2>10. Nomenclaturas</h2>
        <div class="card">
            <h3>Categorías</h3>
            <p>Formato: <code>YYYY - Nombre Empresa (Edición opcional)</code></p>
            <table>
                <tr><th>Ejemplo</th><th>Descripción</th></tr>
                <tr><td>2026 - E2Y Commerce</td><td>Sin edición</td></tr>
                <tr><td>2026 - E2Y Commerce (1)</td><td>Con número</td></tr>
                <tr><td>2026 - Babel Group (Q1)</td><td>Con trimestre</td></tr>
            </table>
        </div>
        
        <div class="card">
            <h3>Cursos</h3>
            <p>Formato: <code>[Idioma] [Nivel] - #[ID Moodle]</code></p>
            <table>
                <tr><th>Ejemplo</th><th>Descripción</th></tr>
                <tr><td>Inglés B1 - #3112</td><td>Curso grupal o individual</td></tr>
                <tr><td>Español A2 - #3113</td><td>El ID es único y trazable</td></tr>
            </table>
        </div>
    </section>
    
    <!-- MOODLE -->
    <section id="moodle" class="section">
        <h2>11. Integración con Moodle</h2>
        <div class="card">
            <h3>Creación de Categoría</h3>
            <ul>
                <li>INSERT en <code>mdl_course_categories</code></li>
                <li>Actualización del path de la categoría</li>
                <li>Creación de contexto en <code>mdl_context</code> (contextlevel=40)</li>
                <li>Asociación automática en <code>own_empresa_ediciones</code></li>
            </ul>
        </div>
        
        <div class="card">
            <h3>Creación de Curso</h3>
            <ul>
                <li>INSERT en <code>mdl_course</code> con fechas de edición</li>
                <li>Actualización de fullname y shortname con ID real</li>
                <li>Creación de contexto (contextlevel=50)</li>
                <li>INSERT de 5 secciones en <code>mdl_course_sections</code></li>
                <li>Configuración tiles en <code>mdl_course_format_options</code></li>
            </ul>
        </div>
    </section>
    
    <p style="text-align:center;color:#999;margin-top:40px;padding-top:20px;border-top:1px solid #eee">
        Documento generado el 7 de enero de 2026 - Panel de Gestión de Empresas - tuSpeaking
    </p>
</main>

<script>
// Highlight active section on scroll
document.querySelectorAll('.sidebar nav a').forEach(link => {
    link.addEventListener('click', function() {
        document.querySelectorAll('.sidebar nav a').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
    });
});
</script>
</body>
</html>
