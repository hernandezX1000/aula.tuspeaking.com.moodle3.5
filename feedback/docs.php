<?php
/**
 * Documentación Técnica - Sistema Feedback NPS
 * tuSpeaking - Enero 2026
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="/app/moodle/brand/icons/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentación - Sistema Feedback NPS</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f5f5;line-height:1.6}
        .sidebar{width:260px;background:#1a1a2e;position:fixed;height:100vh;overflow-y:auto;padding:20px 0}
        .sidebar-logo{color:#fff;font-size:20px;font-weight:700;padding:0 20px 20px;border-bottom:1px solid rgba(255,255,255,.1)}
        .sidebar-logo small{display:block;font-size:11px;color:#888;font-weight:400;margin-top:4px}
        .nav{list-style:none;margin-top:20px}
        .nav a{display:block;padding:10px 20px;color:rgba(255,255,255,.7);text-decoration:none;font-size:14px;border-left:3px solid transparent}
        .nav a:hover,.nav a.active{background:rgba(255,255,255,.05);color:#fff;border-left-color:#008ba3}
        .nav-section{color:#008ba3;font-size:11px;font-weight:600;text-transform:uppercase;padding:20px 20px 8px;letter-spacing:1px}
        .main{margin-left:260px;padding:40px;max-width:900px}
        h1{color:#008ba3;font-size:28px;margin-bottom:8px}
        h2{color:#333;font-size:22px;margin:40px 0 16px;padding-bottom:8px;border-bottom:2px solid #008ba3}
        h3{color:#444;font-size:18px;margin:24px 0 12px}
        h4{color:#555;font-size:15px;margin:16px 0 8px}
        p{color:#555;margin-bottom:12px}
        table{width:100%;border-collapse:collapse;margin:16px 0;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.1)}
        th,td{padding:12px 16px;text-align:left;border-bottom:1px solid #eee}
        th{background:#f8f9fa;color:#333;font-weight:600;font-size:13px}
        td{font-size:14px;color:#555}
        code{background:#f5f5f5;padding:2px 6px;border-radius:4px;font-size:13px;color:#e74c3c}
        pre{background:#1a1a2e;color:#fff;padding:16px;border-radius:8px;overflow-x:auto;font-size:13px;margin:16px 0}
        pre code{background:none;color:#fff;padding:0}
        .card{background:#fff;border-radius:12px;padding:20px;margin:16px 0;box-shadow:0 2px 8px rgba(0,0,0,.08)}
        .badge{display:inline-block;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600}
        .badge-success{background:#e8f5e9;color:#27ae60}
        .badge-warning{background:#fff8e1;color:#f39c12}
        .badge-info{background:#e3f2fd;color:#1976d2}
        .badge-danger{background:#ffebee;color:#e74c3c}
        .alert{padding:16px;border-radius:8px;margin:16px 0;display:flex;align-items:flex-start;gap:12px}
        .alert-info{background:#e3f2fd;color:#1565c0}
        .alert-warning{background:#fff8e1;color:#f57c00}
        .alert-success{background:#e8f5e9;color:#2e7d32}
        .alert .material-icons{font-size:20px;margin-top:2px}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        @media(max-width:900px){.sidebar{width:200px}.main{margin-left:200px;padding:20px}}
        @media(max-width:600px){.sidebar{display:none}.main{margin-left:0}}
        .back{display:inline-flex;align-items:center;gap:4px;color:#008ba3;text-decoration:none;margin-bottom:20px}
        .toc{background:#fff;padding:20px;border-radius:8px;margin-bottom:24px}
        .toc ul{list-style:none;padding-left:20px}
        .toc li{margin:6px 0}
        .toc a{color:#008ba3;text-decoration:none}
        .toc a:hover{text-decoration:underline}
    </style>
</head>
<body>
    <nav class="sidebar">
        <div class="sidebar-logo">
            tuSpeaking
            <small>Sistema Feedback NPS v2.0</small>
        </div>
        <ul class="nav">
            <li class="nav-section">General</li>
            <li><a href="#resumen">Resumen Ejecutivo</a></li>
            <li><a href="#arquitectura">Arquitectura</a></li>
            <li><a href="#urls">URLs y Accesos</a></li>
            
            <li class="nav-section">Base de Datos</li>
            <li><a href="#tablas">Tablas</a></li>
            <li><a href="#configuracion">Configuración</a></li>
            
            <li class="nav-section">Componentes</li>
            <li><a href="#panel">Panel Admin</a></li>
            <li><a href="#formularios">Formularios</a></li>
            <li><a href="#crons">Crons</a></li>
            <li><a href="#teams">Sistema Teams</a></li>
            
            <li class="nav-section">Operación</li>
            <li><a href="#flujo">Flujo de Trabajo</a></li>
            <li><a href="#logica">Lógica de Envíos</a></li>
            <li><a href="#troubleshooting">Troubleshooting</a></li>
            <li><a href="#mantenimiento">Mantenimiento</a></li>
            <li><a href="#diseno">Sistema de Diseño</a></li>
            <li><a href="#migracion">Migración Core</a></li>
        </ul>
    </nav>
    
    <main class="main">
        <a href="admin.php" class="back"><span class="material-icons">arrow_back</span> Volver al Panel</a>
        
        <h1>Documentación Técnica</h1>
        <p style="color:#888;margin-bottom:30px;">Sistema Feedback NPS - tuSpeaking | Actualizado: <?=date('d/m/Y')?></p>
        
        <!-- RESUMEN -->
        <section id="resumen">
            <h2>1. Resumen Ejecutivo</h2>
            
            <div class="card">
                <h4>Objetivo</h4>
                <p>Sistema automatizado de recolección de feedback de alumnos para medir satisfacción con las clases y profesores.</p>
            </div>
            
            <h3>Características Principales</h3>
            <table>
                <tr><td><strong>Email automático</strong></td><td>30 minutos después de cada clase</td></tr>
                <tr><td><strong>Recordatorio</strong></td><td>24 horas si no responde</td></tr>
                <tr><td><strong>Límite anti-spam</strong></td><td>Máximo 2 feedbacks/semana por alumno</td></tr>
                <tr><td><strong>Opt-out</strong></td><td>Posibilidad de darse de baja</td></tr>
                <tr><td><strong>Alertas</strong></td><td>Email automático si valoración ≤3</td></tr>
                <tr><td><strong>Multiidioma</strong></td><td>Español e Inglés</td></tr>
                <tr><td><strong>Multiplataforma</strong></td><td>Zoom (Acuity) y Microsoft Teams</td></tr>
            </table>
            
            <h3>Métricas Objetivo</h3>
            <div class="grid-2">
                <div class="card">
                    <h4>Tasa de Respuesta</h4>
                    <p style="font-size:32px;color:#008ba3;font-weight:700;">15-20%</p>
                    <p>~100 feedbacks/mes para relevancia estadística</p>
                </div>
                <div class="card">
                    <h4>Histórico</h4>
                    <p style="font-size:32px;color:#27ae60;font-weight:700;">13,954</p>
                    <p>Feedbacks desde julio 2018</p>
                </div>
            </div>
        </section>
        
        <!-- ARQUITECTURA -->
        <section id="arquitectura">
            <h2>2. Arquitectura del Sistema</h2>
            
            <pre><code>┌─────────────────────────────────────────────────────────────────┐
│                    SISTEMA FEEDBACK NPS                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   ┌──────────────┐         ┌──────────────┐                     │
│   │    Acuity    │         │    Teams     │                     │
│   │  (Zoom)      │         │  (Manual)    │                     │
│   └──────┬───────┘         └──────┬───────┘                     │
│          │                        │                              │
│          ▼                        ▼                              │
│   ┌──────────────────────────────────────────┐                  │
│   │           CRONS (cada 30 min)            │                  │
│   │  • cron_enviar_feedback.php              │                  │
│   │  • cron_enviar_feedback_teams.php        │                  │
│   └──────────────────┬───────────────────────┘                  │
│                      │                                           │
│                      ▼                                           │
│   ┌──────────────────────────────────────────┐                  │
│   │              EMAIL                        │                  │
│   │         (30 min post-clase)              │                  │
│   └──────────────────┬───────────────────────┘                  │
│                      │                                           │
│                      ▼                                           │
│   ┌──────────────────────────────────────────┐                  │
│   │           FORMULARIO                      │                  │
│   │  • quick.php (Zoom)                      │                  │
│   │  • quick_teams.php (Teams - ES/EN)       │                  │
│   └──────────────────┬───────────────────────┘                  │
│                      │                                           │
│                      ▼                                           │
│   ┌──────────────────────────────────────────┐                  │
│   │         BASE DE DATOS                     │                  │
│   │  • own_feedback_nps                      │                  │
│   │  • own_feedback_envios                   │                  │
│   │  • own_feedback_config                   │                  │
│   └──────────────────────────────────────────┘                  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘</code></pre>
            
            <h3>Estructura de Archivos</h3>
            <pre><code>/home/aulatuspeaking/www/app/moodle/feedback/
├── admin.php                    # Panel de administración
├── index.php                    # Formulario público (legacy)
├── quick.php                    # Formulario rápido Zoom
├── quick_teams.php              # Formulario rápido Teams (ES/EN)
├── optout.php                   # Página opt-out
├── importar_teams.php           # Importador Excel Teams
├── docs.php                     # Esta documentación
├── cron_enviar_feedback.php     # Cron Zoom (cada 30 min)
├── cron_enviar_feedback_teams.php # Cron Teams (cada 30 min)
├── cron_recordatorio_24h.php    # Recordatorio (9:00 AM)
└── cron_resumen_diario.php      # Resumen diario (8:00 AM)</code></pre>
        </section>
        
        <!-- URLs -->
        <section id="urls">
            <h2>3. URLs y Accesos</h2>
            
            <h3>Panel de Administración</h3>
            <table>
                <tr><th>Sección</th><th>URL</th></tr>
                <tr><td>Dashboard</td><td><code>admin.php?s=dashboard</code></td></tr>
                <tr><td>Feedbacks</td><td><code>admin.php?s=feedbacks</code></td></tr>
                <tr><td>Profesores</td><td><code>admin.php?s=profesores</code></td></tr>
                <tr><td>Exportar</td><td><code>admin.php?s=exportar</code></td></tr>
                <tr><td>Teams</td><td><code>admin.php?s=teams</code></td></tr>
                <tr><td>Enlaces</td><td><code>admin.php?s=enlaces</code></td></tr>
                <tr><td>Conversión</td><td><code>admin.php?s=conversion</code></td></tr>
                <tr><td>Auditoría</td><td><code>admin.php?s=auditoria</code></td></tr>
                <tr><td>Configuración</td><td><code>admin.php?s=config</code></td></tr>
            </table>
            
            <h3>Formularios Públicos</h3>
            <table>
                <tr><th>Formulario</th><th>URL</th><th>Uso</th></tr>
                <tr><td>General (legacy)</td><td><code>/feedback/</code></td><td>Acceso manual</td></tr>
                <tr><td>Rápido Zoom</td><td><code>/feedback/quick.php?a=X&t=TOKEN</code></td><td>Vía email automático</td></tr>
                <tr><td>Rápido Teams</td><td><code>/feedback/quick_teams.php?g=X&a=X&t=TOKEN&lang=en</code></td><td>Vía email automático</td></tr>
                <tr><td>Opt-out</td><td><code>/feedback/optout.php?t=TOKEN</code></td><td>Post-feedback</td></tr>
            </table>
        </section>
        
        <!-- TABLAS -->
        <section id="tablas">
            <h2>4. Base de Datos - Tablas</h2>
            
            <h3>own_feedback_nps</h3>
            <p>Almacena todos los feedbacks recibidos (históricos + nuevos).</p>
            <table>
                <tr><th>Campo</th><th>Tipo</th><th>Descripción</th></tr>
                <tr><td>id</td><td>INT PK</td><td>ID único</td></tr>
                <tr><td>submission_date</td><td>DATETIME</td><td>Fecha/hora del feedback</td></tr>
                <tr><td>idioma</td><td>VARCHAR</td><td>Idioma de la clase</td></tr>
                <tr><td>profesor</td><td>VARCHAR</td><td>Nombre del profesor</td></tr>
                <tr><td>valoracion</td><td>INT</td><td>Valoración 1-10</td></tr>
                <tr><td>problema_conexion</td><td>VARCHAR</td><td>Si hubo problemas técnicos</td></tr>
                <tr><td>recibio_feedback</td><td>VARCHAR</td><td>Si recibió feedback del profesor</td></tr>
                <tr><td>comentarios</td><td>TEXT</td><td>Comentarios opcionales</td></tr>
                <tr><td>email</td><td>VARCHAR</td><td>Email del alumno</td></tr>
                <tr><td>enviado_auto</td><td>TINYINT</td><td>1=Sistema nuevo, 0/NULL=Legacy</td></tr>
                <tr><td>acuityid</td><td>BIGINT</td><td>ID cita Acuity (negativo=Teams)</td></tr>
                <tr><td>studentid</td><td>INT</td><td>ID alumno</td></tr>
                <tr><td>teacherid</td><td>INT</td><td>ID profesor</td></tr>
                <tr><td>token</td><td>VARCHAR</td><td>Token de seguridad</td></tr>
            </table>
            
            <h3>own_feedback_envios</h3>
            <p>Control de emails enviados y su estado.</p>
            <table>
                <tr><th>Campo</th><th>Tipo</th><th>Descripción</th></tr>
                <tr><td>id</td><td>INT PK</td><td>ID único</td></tr>
                <tr><td>acuityid</td><td>BIGINT UK</td><td>ID cita (negativo=Teams)</td></tr>
                <tr><td>studentid</td><td>INT</td><td>ID alumno</td></tr>
                <tr><td>teacherid</td><td>INT</td><td>ID profesor</td></tr>
                <tr><td>student_email</td><td>VARCHAR</td><td>Email alumno</td></tr>
                <tr><td>teacher_name</td><td>VARCHAR</td><td>Nombre profesor</td></tr>
                <tr><td>clase_fecha</td><td>DATETIME</td><td>Fecha de la clase</td></tr>
                <tr><td>token</td><td>VARCHAR</td><td>Token SHA256</td></tr>
                <tr><td>enviado_at</td><td>DATETIME</td><td>Fecha envío email</td></tr>
                <tr><td>abierto_at</td><td>DATETIME</td><td>Fecha apertura enlace</td></tr>
                <tr><td>respondido_at</td><td>DATETIME</td><td>Fecha respuesta</td></tr>
                <tr><td>feedback_id</td><td>INT FK</td><td>ID del feedback generado</td></tr>
                <tr><td>recordatorio_enviado</td><td>DATETIME</td><td>Fecha recordatorio 24h</td></tr>
            </table>
            
            <h3>own_feedback_config</h3>
            <p>Configuración editable del sistema.</p>
            <table>
                <tr><th>Campo</th><th>Tipo</th><th>Descripción</th></tr>
                <tr><td>id</td><td>INT PK</td><td>ID único</td></tr>
                <tr><td>clave</td><td>VARCHAR UK</td><td>Nombre del parámetro</td></tr>
                <tr><td>valor</td><td>VARCHAR</td><td>Valor actual</td></tr>
                <tr><td>descripcion</td><td>VARCHAR</td><td>Descripción</td></tr>
                <tr><td>updated_at</td><td>TIMESTAMP</td><td>Última modificación</td></tr>
            </table>
            
            <h3>own_feedback_optout</h3>
            <p>Alumnos que no desean recibir emails.</p>
            <table>
                <tr><th>Campo</th><th>Tipo</th><th>Descripción</th></tr>
                <tr><td>id</td><td>INT PK</td><td>ID único</td></tr>
                <tr><td>email</td><td>VARCHAR UK</td><td>Email del alumno</td></tr>
                <tr><td>nombre</td><td>VARCHAR</td><td>Nombre del alumno</td></tr>
                <tr><td>motivo</td><td>VARCHAR</td><td>Motivo de baja</td></tr>
                <tr><td>created_at</td><td>TIMESTAMP</td><td>Fecha de baja</td></tr>
            </table>
            
            <h3>own_feedback_logs</h3>
            <p>Logs de auditoría.</p>
            <table>
                <tr><th>Campo</th><th>Tipo</th><th>Descripción</th></tr>
                <tr><td>id</td><td>BIGINT PK</td><td>ID único</td></tr>
                <tr><td>tipo</td><td>ENUM</td><td>envio/apertura/respuesta/error/optout</td></tr>
                <tr><td>mensaje</td><td>VARCHAR</td><td>Detalle</td></tr>
                <tr><td>email</td><td>VARCHAR</td><td>Email relacionado</td></tr>
                <tr><td>acuityid</td><td>BIGINT</td><td>ID cita</td></tr>
                <tr><td>created_at</td><td>TIMESTAMP</td><td>Fecha/hora</td></tr>
            </table>
            
            <h3>own_feedback_teams_grupos</h3>
            <p>Grupos de clases Teams.</p>
            <table>
                <tr><th>Campo</th><th>Tipo</th><th>Descripción</th></tr>
                <tr><td>id</td><td>INT PK</td><td>ID único</td></tr>
                <tr><td>empresa</td><td>VARCHAR</td><td>Nombre empresa</td></tr>
                <tr><td>nombre_grupo</td><td>VARCHAR</td><td>Nombre del grupo</td></tr>
                <tr><td>profesor</td><td>VARCHAR</td><td>Profesor asignado</td></tr>
                <tr><td>dia_semana</td><td>ENUM</td><td>lunes/martes/miercoles/jueves/viernes</td></tr>
                <tr><td>hora_inicio</td><td>TIME</td><td>Hora de la clase</td></tr>
                <tr><td>idioma_formulario</td><td>ENUM</td><td>es/en</td></tr>
                <tr><td>fecha_inicio</td><td>DATE</td><td>Inicio del curso</td></tr>
                <tr><td>fecha_fin</td><td>DATE</td><td>Fin del curso</td></tr>
                <tr><td>activo</td><td>TINYINT</td><td>1=Activo, 0=Inactivo</td></tr>
            </table>
            
            <h3>own_feedback_teams_alumnos</h3>
            <p>Alumnos de grupos Teams.</p>
            <table>
                <tr><th>Campo</th><th>Tipo</th><th>Descripción</th></tr>
                <tr><td>id</td><td>INT PK</td><td>ID único</td></tr>
                <tr><td>grupo_id</td><td>INT FK</td><td>ID del grupo</td></tr>
                <tr><td>nombre</td><td>VARCHAR</td><td>Nombre alumno</td></tr>
                <tr><td>email</td><td>VARCHAR</td><td>Email alumno</td></tr>
                <tr><td>activo</td><td>TINYINT</td><td>1=Activo</td></tr>
            </table>
        </section>
        
        <!-- CONFIGURACIÓN -->
        <section id="configuracion">
            <h2>5. Configuración</h2>
            
            <p>Parámetros editables desde el panel (Configuración):</p>
            
            <table>
                <tr><th>Clave</th><th>Valor Default</th><th>Descripción</th></tr>
                <tr><td><code>max_feedbacks_semana</code></td><td>2</td><td>Máximo emails por alumno por semana</td></tr>
                <tr><td><code>minutos_espera_envio</code></td><td>30</td><td>Minutos después de clase para enviar</td></tr>
                <tr><td><code>minutos_limite_envio</code></td><td>90</td><td>Máximo minutos para considerar envío</td></tr>
                <tr><td><code>habilitar_recordatorio_24h</code></td><td>1</td><td>Activar recordatorio (1=Sí)</td></tr>
                <tr><td><code>email_alertas</code></td><td>notificaciones@tuspeaking.com</td><td>Email para alertas ≤3</td></tr>
                <tr><td><code>email_alertas_activo</code></td><td>1</td><td>Activar alertas (1=Sí)</td></tr>
                <tr><td><code>email_resumen_activo</code></td><td>1</td><td>Activar resumen diario (1=Sí)</td></tr>
                <tr><td><code>email_resumen_destino</code></td><td>notificaciones@tuspeaking.com</td><td>Email para resumen</td></tr>
                <tr><td><code>dias_resumen_restantes</code></td><td>60</td><td>Días restantes de resumen</td></tr>
                <tr><td><code>objetivo_feedbacks_mes</code></td><td>100</td><td>Objetivo mensual</td></tr>
                <tr><td><code>objetivo_tasa_respuesta</code></td><td>15</td><td>Objetivo tasa %</td></tr>
                <tr><td><code>confianza_estadistica</code></td><td>95</td><td>Nivel confianza %</td></tr>
                <tr><td><code>margen_error</code></td><td>10</td><td>Margen error %</td></tr>
            </table>
        </section>
        
        <!-- PANEL -->
        <section id="panel">
            <h2>6. Panel de Administración</h2>
            
            <h3>Secciones</h3>
            
            <div class="card">
                <h4>📊 Dashboard</h4>
                <p>Vista general con KPIs: total feedbacks, valoración media, alertas, tasa de respuesta. Comparativa sistema nuevo vs antiguo.</p>
            </div>
            
            <div class="card">
                <h4>📋 Feedbacks</h4>
                <p>Lista de todos los feedbacks con filtros por fecha, profesor, email y valoración. Muestra últimos 100 registros.</p>
            </div>
            
            <div class="card">
                <h4>👨‍🏫 Profesores</h4>
                <p>Ranking de profesores con: total feedbacks, media, excelentes (≥9), alertas (≤3), tendencia (▲●▼), relevancia estadística.</p>
                <ul>
                    <li><strong>Relevancia Alta:</strong> ≥100 feedbacks</li>
                    <li><strong>Relevancia Media:</strong> 30-99 feedbacks</li>
                    <li><strong>Relevancia Baja:</strong> <30 feedbacks</li>
                </ul>
            </div>
            
            <div class="card">
                <h4>📥 Exportar</h4>
                <p>Exportación de datos en 3 formatos: CSV, Excel, PDF. Con filtros por fecha, email y profesor.</p>
            </div>
            
            <div class="card">
                <h4>👥 Teams</h4>
                <p>Gestión de grupos Microsoft Teams: crear grupos, añadir alumnos, importar desde Excel.</p>
            </div>
            
            <div class="card">
                <h4>🔗 Enlaces</h4>
                <p>URLs de formularios, panel y documentación de crons.</p>
            </div>
            
            <div class="card">
                <h4>📈 Conversión</h4>
                <p>Funnel de conversión: clases → emails → abiertos → respondidos. Relevancia estadística con cálculo automático.</p>
            </div>
            
            <div class="card">
                <h4>🔍 Auditoría</h4>
                <p>Health check del sistema, últimos envíos, logs de errores.</p>
            </div>
            
            <div class="card">
                <h4>⚙️ Configuración</h4>
                <p>Edición de todos los parámetros del sistema.</p>
            </div>
        </section>
        
        <!-- FORMULARIOS -->
        <section id="formularios">
            <h2>7. Formularios</h2>
            
            <h3>Formulario Rápido (quick.php / quick_teams.php)</h3>
            <p>Formulario pre-llenado enviado por email. Características:</p>
            <ul>
                <li>Datos pre-cargados (profesor, fecha, grupo)</li>
                <li>Valoración 1-10 con colores</li>
                <li>Comentarios opcionales</li>
                <li>Pregunta "¿Recibiste feedback?" solo en recordatorio 24h</li>
                <li>Enlace opt-out al finalizar</li>
                <li>Soporte español/inglés (Teams)</li>
            </ul>
            
            <h3>Validación de Token</h3>
            <p>Cada enlace incluye un token SHA256 generado con:</p>
            <pre><code>token = SHA256(acuityid + '-' + studentid + '-tS2026!')</code></pre>
            <p>Para Teams:</p>
            <pre><code>token = SHA256(grupo_id + '-' + alumno_id + '-' + fecha + '-tS2026!')</code></pre>
        </section>
        
        <!-- CRONS -->
        <section id="crons">
            <h2>8. Crons</h2>
            
            <h3>Configuración Crontab</h3>
            <pre><code># Feedback Zoom (cada 30 min)
*/30 * * * * /usr/bin/php /home/aulatuspeaking/www/app/moodle/feedback/cron_enviar_feedback.php >> /home/aulatuspeaking/feedback_cron.log 2>&1

# Feedback Teams (cada 30 min)
*/30 * * * * /usr/bin/php /home/aulatuspeaking/www/app/moodle/feedback/cron_enviar_feedback_teams.php >> /home/aulatuspeaking/feedback_cron.log 2>&1

# Recordatorio 24h (9:00 AM)
0 9 * * * /usr/bin/php /home/aulatuspeaking/www/app/moodle/feedback/cron_recordatorio_24h.php >> /home/aulatuspeaking/feedback_cron.log 2>&1

# Resumen diario (8:00 AM)
0 8 * * * /usr/bin/php /home/aulatuspeaking/www/app/moodle/feedback/cron_resumen_diario.php >> /home/aulatuspeaking/feedback_cron.log 2>&1</code></pre>
            
            <h3>cron_enviar_feedback.php (Zoom)</h3>
            <p>Busca clases en <code>own_acuity</code> (JOINs con <code>mdl_event</code> y <code>mdl_user</code>) que terminaron hace 30-90 minutos y envía email de feedback.</p>
            <ul>
                <li>Lee configuración de <code>own_feedback_config</code></li>
                <li>Verifica límite semanal por alumno</li>
                <li>Excluye opt-outs</li>
                <li>Genera token y registra en <code>own_feedback_envios</code></li>
            </ul>
            
            <h3>cron_enviar_feedback_teams.php</h3>
            <p>Similar al anterior pero para grupos Teams basado en programación (día/hora).</p>
            
            <h3>cron_recordatorio_24h.php</h3>
            <p>Busca envíos de hace 20-28 horas sin respuesta y envía recordatorio con parámetro <code>?r=1</code>.</p>
            
            <h3>cron_resumen_diario.php</h3>
            <p>Envía resumen del día anterior con métricas y detalle por profesor. Countdown de 60 días.</p>
        </section>
        
        <!-- TEAMS -->
        <section id="teams">
            <h2>9. Sistema Teams</h2>
            
            <h3>Importación desde Excel</h3>
            <p>Archivo CSV con columnas:</p>
            <table>
                <tr><th>Columna</th><th>Obligatorio</th><th>Ejemplo</th></tr>
                <tr><td>Empresa</td><td>✅</td><td>Samsung</td></tr>
                <tr><td>Grupo</td><td>✅</td><td>Samsung Grupo 1</td></tr>
                <tr><td>Profesor</td><td>✅</td><td>Kate Klopper</td></tr>
                <tr><td>Dia</td><td>✅</td><td>Martes</td></tr>
                <tr><td>Hora</td><td>✅</td><td>10:00</td></tr>
                <tr><td>Idioma</td><td>✅</td><td>EN</td></tr>
                <tr><td>FechaInicio</td><td>✅</td><td>2026-01-15</td></tr>
                <tr><td>FechaFin</td><td>✅</td><td>2026-06-30</td></tr>
                <tr><td>NombreAlumno</td><td>⚠️</td><td>John Smith</td></tr>
                <tr><td>EmailAlumno</td><td>✅</td><td>john@samsung.com</td></tr>
            </table>
            
            <div class="alert alert-info">
                <span class="material-icons">info</span>
                <div>Cada fila representa un alumno. Si un grupo tiene 5 alumnos, habrá 5 filas con los mismos datos de grupo.</div>
            </div>
        </section>
        
        <!-- FLUJO -->
        <section id="flujo">
            <h2>10. Flujo de Trabajo</h2>
            
            <h3>Flujo Zoom (Acuity)</h3>
            <pre><code>1. Alumno tiene clase vía Zoom
2. Clase registrada en own_acuity + mdl_event
3. Cron detecta clase terminada (30 min después)
4. Verifica: no opt-out, no excede límite semanal, no cancelada
5. Genera token y registra en own_feedback_envios
6. Envía email con enlace a quick.php
7. Alumno abre enlace → marca abierto_at
8. Alumno valora → guarda en own_feedback_nps, marca respondido_at
9. Si valoración ≤3 → envía alerta
10. Si no responde en 24h → cron_recordatorio envía segundo email</code></pre>
            
            <h3>Flujo Teams</h3>
            <pre><code>1. Admin importa grupos/alumnos desde Excel
2. Grupos tienen día/hora de clase
3. Cron detecta grupos cuya clase terminó (30 min después)
4. Mismas verificaciones que Zoom
5. Envía email con enlace a quick_teams.php (ES o EN)
6. Resto del flujo igual</code></pre>
        </section>
        
        <!-- LOGICA ENVIOS -->
        <section id="logica">
            <h2>10.1 Lógica de Envíos Detallada</h2>
            
            <div class="card">
                <h3>⏱️ Ventana de Envío</h3>
                <table>
                    <tr><th>Parámetro</th><th>Valor</th><th>Descripción</th></tr>
                    <tr><td>minutos_espera_envio</td><td><strong>30 min</strong></td><td>Tiempo mínimo después de terminar la clase</td></tr>
                    <tr><td>minutos_limite_envio</td><td><strong>90 min</strong></td><td>Tiempo máximo para considerar envío</td></tr>
                </table>
                <p style="margin-top:12px;color:#666;">📌 El cron busca clases que terminaron hace 30-90 minutos. Si una clase termina a las 10:00, el email se envía entre 10:30 y 11:30.</p>
            </div>
            
            <div class="card">
                <h3>🔒 Filtros de Exclusión</h3>
                <table>
                    <tr><th>Filtro</th><th>Condición</th></tr>
                    <tr><td>Clase cancelada</td><td><code>iscancelled = t</code> → No se envía</td></tr>
                    <tr><td>Opt-out</td><td>Email en <code>own_feedback_optout</code> → No se envía</td></tr>
                    <tr><td>Límite semanal</td><td>Máx 2 feedbacks por alumno/semana</td></tr>
                    <tr><td>Ya enviado</td><td>Existe en <code>own_feedback_envios</code> → No duplica</td></tr>
                    <tr><td>Email inválido</td><td>NULL, vacío o sin @ → No se envía</td></tr>
                </table>
            </div>
            
            <div class="card">
                <h3>🔄 Cancelaciones y Reprogramaciones</h3>
                <table>
                    <tr><th>Escenario</th><th>Comportamiento</th></tr>
                    <tr><td><span class="badge badge-danger">Clase cancelada</span></td><td><code>iscancelled = t</code> → No se envía feedback</td></tr>
                    <tr><td><span class="badge badge-warning">Clase reprogramada</span></td><td>El evento se actualiza con nueva fecha → Envía feedback en la nueva fecha</td></tr>
                    <tr><td><span class="badge badge-info">Ya enviado + reprogramada</span></td><td>Mismo <code>acuityid</code> → No duplica envío</td></tr>
                </table>
                <div class="alert alert-info" style="margin-top:12px;">
                    <span class="material-icons">info</span>
                    <div>El cron usa <code>mdl_event.timestart</code> que siempre tiene la fecha actual/reprogramada. Si una clase se reprograma antes de enviar feedback, se enviará en la nueva fecha automáticamente.</div>
                </div>
            </div>
            
            <div class="card">
                <h3>📧 Tipos de Email</h3>
                <table>
                    <tr><th>Tipo</th><th>Cuándo</th><th>Contenido</th></tr>
                    <tr><td><span class="badge badge-info">Inicial</span></td><td>30-90 min post-clase</td><td>Valoración + Problemas conexión</td></tr>
                    <tr><td><span class="badge badge-warning">Recordatorio</span></td><td>24h sin respuesta</td><td>+ Pregunta "¿Recibí feedback?"</td></tr>
                    <tr><td><span class="badge badge-danger">Alerta</span></td><td>Valoración ≤3</td><td>Email a notificaciones@tuspeaking.com</td></tr>
                </table>
            </div>
            
            <div class="card">
                <h3>📊 Tracking del Email</h3>
                <table>
                    <tr><th>Campo</th><th>Se actualiza cuando...</th></tr>
                    <tr><td><code>enviado_at</code></td><td>Se envía el email inicial</td></tr>
                    <tr><td><code>abierto_at</code></td><td>El alumno abre el enlace</td></tr>
                    <tr><td><code>respondido_at</code></td><td>El alumno envía la valoración</td></tr>
                    <tr><td><code>recordatorio_enviado</code></td><td>Se envía el recordatorio 24h</td></tr>
                </table>
            </div>
            
            <div class="card">
                <h3>🔐 Seguridad del Token</h3>
                <pre><code>token = SHA256(acuityid + studentid + "tS2026!")[:32]</code></pre>
                <p>El token es único por clase+alumno y no puede ser adivinado. Se valida con <code>WHERE acuityid = ? AND token = ?</code></p>
            </div>
            
            <div class="card">
                <h3>📅 Horarios de Crons</h3>
                <table>
                    <tr><th>Cron</th><th>Frecuencia</th><th>Función</th></tr>
                    <tr><td>cron_enviar_feedback.php</td><td>Cada 30 min</td><td>Envía emails post-clase (Zoom)</td></tr>
                    <tr><td>cron_enviar_feedback_teams.php</td><td>Cada 30 min</td><td>Envía emails post-clase (Teams)</td></tr>
                    <tr><td>cron_recordatorio_24h.php</td><td>9:00 AM</td><td>Recordatorio a no respondidos</td></tr>
                    <tr><td>cron_resumen_diario.php</td><td>8:00 AM</td><td>Resumen diario a admin</td></tr>
                </table>
            </div>
        </section>

        <!-- TROUBLESHOOTING -->
        <section id="troubleshooting">
            <h2>11. Troubleshooting</h2>
            
            <h3>El cron no envía emails</h3>
            <div class="card">
                <ol>
                    <li>Verificar que hay clases terminadas hace 30-90 min</li>
                    <li>Revisar log: <code>tail -100 /home/aulatuspeaking/feedback_cron.log</code></li>
                    <li>Probar manual: <code>php cron_enviar_feedback.php</code></li>
                    <li>Verificar crontab: <code>crontab -l | grep feedback</code></li>
                </ol>
            </div>
            
            <h3>Enlace "inválido o expirado"</h3>
            <div class="card">
                <ul>
                    <li>Token debe coincidir exactamente</li>
                    <li>Para Teams, el token incluye la fecha del día</li>
                    <li>Verificar que existe en <code>own_feedback_envios</code></li>
                </ul>
            </div>
            
            <h3>Emails van a spam</h3>
            <div class="card">
                <ul>
                    <li>Verificar SPF/DKIM de tuspeaking.com</li>
                    <li>Considerar servicio transaccional (SendGrid, Mailgun)</li>
                </ul>
            </div>
            
            <h3>Error "Configuración cargada" en Auditoría</h3>
            <div class="card">
                <p>Verificar que la tabla <code>own_feedback_config</code> tiene registros:</p>
                <pre><code>SELECT COUNT(*) FROM own_feedback_config;</code></pre>
            </div>
        </section>
        
        <!-- MANTENIMIENTO -->
        <section id="mantenimiento">
            <h2>12. Mantenimiento</h2>
            
            <h3>Monitoreo Diario (Primera Semana)</h3>
            <pre><code># Ver log del cron
tail -100 /home/aulatuspeaking/feedback_cron.log

# Ver envíos de hoy
mysql -u moodle35 -p'TuspeakingFix2025!' aulatuspeaking35 -e "
SELECT COUNT(*) as enviados_hoy FROM own_feedback_envios WHERE DATE(enviado_at) = CURDATE();"</code></pre>
            
            <h3>Backups</h3>
            <pre><code># Backup de archivos
cp /home/aulatuspeaking/www/app/moodle/feedback/*.php ~/backups/feedback/

# Backup de datos
mysqldump -u moodle35 -p'TuspeakingFix2025!' aulatuspeaking35 \
  own_feedback_nps own_feedback_envios own_feedback_config \
  own_feedback_optout own_feedback_logs own_feedback_teams_grupos \
  own_feedback_teams_alumnos > ~/backups/feedback_data.sql</code></pre>
            
            <h3>Desactivar Acuity (Opcional)</h3>
            <p>Una vez confirmado que el sistema nuevo funciona (1-2 semanas), puedes desactivar el email de 24h desde Acuity para evitar duplicados.</p>
            
            <div class="alert alert-warning">
                <span class="material-icons">warning</span>
                <div>Espera al menos 2 semanas monitoreando antes de desactivar Acuity.</div>
            </div>
        </section>
        
        <hr style="margin:40px 0;border:none;border-top:1px solid #eee">
        <p style="color:#999;text-align:center;font-size:13px;">
            Documentación generada el <?=date('d/m/Y H:i')?><br>
            Sistema Feedback NPS v2.0 - tuSpeaking<br><br><strong>Chat de desarrollo:</strong> "Integración de sistema de feedback de profesores" (6 enero 2026)
        </p>

        <!-- SISTEMA DE DISEÑO -->
        <section id="diseno">
            <h2>13. Sistema de Diseño Reutilizable</h2>
            
            <div class="alert alert-info">
                <span class="material-icons">palette</span>
                <div>El diseño de este panel está disponible como sistema reutilizable para otros proyectos.</div>
            </div>
            
            <h3>Nombre del Sistema</h3>
            <p><strong>tS Admin Panel</strong> - Sistema de diseño para paneles de administración tuSpeaking</p>
            
            <h3>Ubicación de Archivos</h3>
            <table>
                <tr><th>Archivo</th><th>Ruta</th><th>Descripción</th></tr>
                <tr><td>CSS</td><td><code>/app/moodle/brand/admin-panel/ts-admin-panel.css</code></td><td>Estilos completos</td></tr>
                <tr><td>HTML</td><td><code>/app/moodle/brand/admin-panel/ts-admin-panel.html</code></td><td>Plantilla de ejemplo</td></tr>
                <tr><td>README</td><td><code>/app/moodle/brand/admin-panel/README.md</code></td><td>Documentación</td></tr>
            </table>
            
            <h3>Vista Previa</h3>
            <p><a href="https://aula.tuspeaking.com/app/moodle/brand/admin-panel/ts-admin-panel.html" target="_blank" style="color:#008ba3;">Ver plantilla de ejemplo ↗</a></p>
            
            <h3>Cómo Usar en Otro Proyecto</h3>
            <pre><code>&lt;!-- Incluir en el &lt;head&gt; --&gt;
&lt;link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"&gt;
&lt;link href="/app/moodle/brand/admin-panel/ts-admin-panel.css" rel="stylesheet"&gt;</code></pre>
            
            <h3>Estructura HTML Básica</h3>
            <pre><code>&lt;div class="ts-layout"&gt;
    &lt;nav class="ts-sidebar"&gt;
        &lt;div class="ts-sidebar-logo"&gt;
            tuSpeaking
            &lt;small&gt;Nombre del Sistema&lt;/small&gt;
        &lt;/div&gt;
        &lt;ul class="ts-nav"&gt;
            &lt;li&gt;&lt;a href="#" class="active"&gt;
                &lt;span class="material-icons"&gt;dashboard&lt;/span&gt;
                &lt;span&gt;Dashboard&lt;/span&gt;
            &lt;/a&gt;&lt;/li&gt;
        &lt;/ul&gt;
    &lt;/nav&gt;
    &lt;main class="ts-main"&gt;
        &lt;!-- Contenido aquí --&gt;
    &lt;/main&gt;
&lt;/div&gt;</code></pre>
            
            <h3>Componentes Disponibles</h3>
            <div class="grid-2">
                <div class="card">
                    <h4>Layout</h4>
                    <ul style="margin-left:20px;color:#666;">
                        <li><code>.ts-layout</code> - Contenedor principal</li>
                        <li><code>.ts-sidebar</code> - Barra lateral</li>
                        <li><code>.ts-main</code> - Contenido principal</li>
                        <li><code>.ts-header</code> - Cabecera</li>
                    </ul>
                </div>
                <div class="card">
                    <h4>KPIs</h4>
                    <ul style="margin-left:20px;color:#666;">
                        <li><code>.ts-kpis</code> - Grid de KPIs</li>
                        <li><code>.ts-kpi</code> - Tarjeta KPI</li>
                        <li><code>.ts-kpi.success</code> - KPI verde</li>
                        <li><code>.ts-kpi.danger</code> - KPI rojo</li>
                    </ul>
                </div>
                <div class="card">
                    <h4>Cards y Tablas</h4>
                    <ul style="margin-left:20px;color:#666;">
                        <li><code>.ts-card</code> - Tarjeta contenedora</li>
                        <li><code>.ts-table</code> - Tabla estilizada</li>
                        <li><code>.ts-badge-*</code> - Badges colores</li>
                    </ul>
                </div>
                <div class="card">
                    <h4>Formularios</h4>
                    <ul style="margin-left:20px;color:#666;">
                        <li><code>.ts-form-group</code> - Grupo form</li>
                        <li><code>.ts-input</code> - Input estilizado</li>
                        <li><code>.ts-btn-*</code> - Botones</li>
                        <li><code>.ts-filters</code> - Barra filtros</li>
                    </ul>
                </div>
            </div>
            
            <h3>Colores Corporativos (Variables CSS)</h3>
            <table>
                <tr><th>Variable</th><th>Color</th><th>Uso</th></tr>
                <tr><td><code>--ts-primary</code></td><td><span style="background:#008ba3;color:white;padding:2px 8px;border-radius:4px;">#008ba3</span></td><td>Principal (turquesa)</td></tr>
                <tr><td><code>--ts-success</code></td><td><span style="background:#27ae60;color:white;padding:2px 8px;border-radius:4px;">#27ae60</span></td><td>Éxito (verde)</td></tr>
                <tr><td><code>--ts-warning</code></td><td><span style="background:#f39c12;color:white;padding:2px 8px;border-radius:4px;">#f39c12</span></td><td>Advertencia (amarillo)</td></tr>
                <tr><td><code>--ts-danger</code></td><td><span style="background:#e74c3c;color:white;padding:2px 8px;border-radius:4px;">#e74c3c</span></td><td>Error (rojo)</td></tr>
                <tr><td><code>--ts-dark</code></td><td><span style="background:#1a1a2e;color:white;padding:2px 8px;border-radius:4px;">#1a1a2e</span></td><td>Sidebar oscuro</td></tr>
            </table>
            
            <h3>Proyectos que Usan Este Sistema</h3>
            <table>
                <tr><th>Proyecto</th><th>URL</th><th>Fecha</th></tr>
                <tr><td>Feedback NPS</td><td><code>/feedback/admin.php</code></td><td>Enero 2026</td></tr>
            </table>
        </section>

        <!-- MIGRACIÓN -->
        <section id="migracion">
            <h2>14. Migración a Plataforma Core</h2>
            
            <div class="alert alert-warning">
                <span class="material-icons">info</span>
                <div><strong>Destino:</strong> 46.231.126.134 - Plataforma propietaria tuSpeaking</div>
            </div>
            
            <h3>Capa de Abstracción</h3>
            <p>Se ha creado una capa de abstracción para facilitar la migración:</p>
            <table>
                <tr><th>Archivo</th><th>Descripción</th></tr>
                <tr><td><code>/feedback/config_abstraction.php</code></td><td>Centraliza todas las dependencias externas (Moodle, Acuity)</td></tr>
            </table>
            
            <h3>Estrategia de Migración</h3>
            <pre><code>ENTORNO ACTUAL (moodle):
├── mdl_user              → Alumnos
├── teacher_zoom_map      → Profesores  
├── own_acuity + mdl_event → Clases (Acuity/Zoom)

ENTORNO FUTURO (core):
├── core_alumnos          → Alumnos
├── core_profesores       → Profesores
├── core_clases           → Clases</code></pre>
            
            <h3>Pasos de Migración</h3>
            
            <div class="card">
                <h4>1. Copiar Tablas Propias (sin cambios)</h4>
                <pre><code>-- Exportar e importar tal cual:
own_feedback_nps
own_feedback_envios
own_feedback_config
own_feedback_optout
own_feedback_logs
own_feedback_teams_grupos
own_feedback_teams_alumnos</code></pre>
            </div>
            
            <div class="card">
                <h4>2. Modificar config_abstraction.php</h4>
                <pre><code>// Cambiar de:
define('FEEDBACK_ENV', 'moodle');

// A:
define('FEEDBACK_ENV', 'core');</code></pre>
                <p style="color:#666;margin-top:8px;">Las funciones fb_get_* detectarán automáticamente qué queries usar.</p>
            </div>
            
            <div class="card">
                <h4>3. Copiar Archivos</h4>
                <pre><code>/feedback/
├── admin.php
├── quick.php
├── quick_teams.php
├── optout.php
├── importar_teams.php
├── docs.php
├── config_abstraction.php
├── cron_enviar_feedback.php
├── cron_enviar_feedback_teams.php
├── cron_recordatorio_24h.php
└── cron_resumen_diario.php

/brand/admin-panel/
├── ts-admin-panel.css
├── ts-admin-panel.html
└── README.md</code></pre>
            </div>
            
            <div class="card">
                <h4>4. Configurar Crons en Nuevo Servidor</h4>
                <pre><code># Añadir a crontab
*/30 * * * * php /path/to/feedback/cron_enviar_feedback.php
*/30 * * * * php /path/to/feedback/cron_enviar_feedback_teams.php
0 9 * * * php /path/to/feedback/cron_recordatorio_24h.php
0 8 * * * php /path/to/feedback/cron_resumen_diario.php</code></pre>
            </div>
            
            <h3>Funciones de Abstracción Disponibles</h3>
            <table>
                <tr><th>Función</th><th>Descripción</th><th>Uso</th></tr>
                <tr><td><code>fb_get_alumno($conn, $email)</code></td><td>Obtener datos de alumno</td><td>Devuelve id, firstname, lastname, email</td></tr>
                <tr><td><code>fb_get_profesor($conn, $id)</code></td><td>Obtener datos de profesor</td><td>Devuelve id, firstname, lastname, email</td></tr>
                <tr><td><code>fb_get_clases_para_feedback($conn, $min_desde, $min_hasta)</code></td><td>Clases terminadas para enviar feedback</td><td>Usado por crons</td></tr>
                <tr><td><code>fb_get_profesor_stats($conn, $nombre, $desde, $hasta)</code></td><td>Estadísticas de profesor</td><td>Total, media, excelentes, alertas</td></tr>
            </table>
            
            <h3>Dependencias del Sistema</h3>
            <table>
                <tr><th>Requisito</th><th>Versión</th><th>Notas</th></tr>
                <tr><td>PHP</td><td>7.4+</td><td>Funciones usadas compatibles</td></tr>
                <tr><td>MySQL</td><td>5.7+</td><td>SQL estándar</td></tr>
                <tr><td>Material Icons</td><td>CDN</td><td>Google Fonts</td></tr>
                <tr><td>TCPDF</td><td>Incluido</td><td>Para exportación PDF</td></tr>
            </table>
            
            <h3>Estimación de Esfuerzo</h3>
            <table>
                <tr><th>Tarea</th><th>Tiempo estimado</th></tr>
                <tr><td>Copiar archivos y tablas</td><td>30 min</td></tr>
                <tr><td>Adaptar config_abstraction.php</td><td>1-2 horas</td></tr>
                <tr><td>Configurar crons</td><td>15 min</td></tr>
                <tr><td>Pruebas</td><td>2-3 horas</td></tr>
                <tr><td><strong>Total</strong></td><td><strong>4-6 horas</strong></td></tr>
            </table>
        </section>
        
        <hr style="margin:40px 0;border:none;border-top:1px solid #eee">
        <p style="color:#999;text-align:center;font-size:13px;">
            Documentación generada el <?=date('d/m/Y H:i')?><br>
            Sistema Feedback NPS v2.0 - tuSpeaking<br><br>
            <strong>Chat de desarrollo:</strong> "Integración de sistema de feedback de profesores" (6 enero 2026)
        </p>
    </main>
</body>
</html>
