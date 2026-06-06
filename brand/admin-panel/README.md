# tS Admin Panel - Sistema de Diseño

## Descripción
Sistema de diseño para paneles de administración de tuSpeaking. Proporciona componentes reutilizables con estilo consistente.

**Versión:** 1.0  
**Fecha:** 6 enero 2026  
**Proyecto original:** Sistema Feedback NPS

## Archivos

| Archivo | Descripción |
|---------|-------------|
| `ts-admin-panel.css` | Estilos CSS completos |
| `ts-admin-panel.html` | Plantilla de ejemplo |
| `README.md` | Esta documentación |

## Cómo usar

### 1. Incluir dependencias
```html
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link href="/app/moodle/brand/admin-panel/ts-admin-panel.css" rel="stylesheet">
```

### 2. Estructura básica
```html
<div class="ts-layout">
    <nav class="ts-sidebar">...</nav>
    <main class="ts-main">...</main>
</div>
```

## Componentes disponibles

### Layout
- `.ts-layout` - Contenedor principal (flex)
- `.ts-sidebar` - Barra lateral
- `.ts-main` - Contenido principal
- `.ts-header` - Cabecera de página

### Navegación
- `.ts-nav` - Lista de navegación
- `.ts-nav-section` - Separador de sección
- `.ts-sidebar-logo` - Logo en sidebar

### KPIs
- `.ts-kpis` - Grid de KPIs
- `.ts-kpi` - Tarjeta KPI individual
- `.ts-kpi.success/warning/danger` - Variantes de color

### Cards
- `.ts-card` - Tarjeta contenedora
- `.ts-card-highlight` - Card destacada

### Tablas
- `.ts-table` - Tabla con estilos

### Badges
- `.ts-badge` - Badge base
- `.ts-badge-success/warning/danger/info/primary` - Variantes

### Botones
- `.ts-btn` - Botón base
- `.ts-btn-primary/success/danger/warning` - Variantes

### Formularios
- `.ts-form-group` - Grupo de formulario
- `.ts-input` - Input/select estilizado
- `.ts-filters` - Barra de filtros

### Utilidades
- `.ts-progress` - Barra de progreso
- `.ts-alert` - Alertas
- `.ts-grid-2/3/4` - Grids responsive

## Colores corporativos

| Variable | Color | Uso |
|----------|-------|-----|
| `--ts-primary` | #008ba3 | Principal (turquesa) |
| `--ts-secondary` | #00bcd4 | Secundario |
| `--ts-success` | #27ae60 | Éxito (verde) |
| `--ts-warning` | #f39c12 | Advertencia (amarillo) |
| `--ts-danger` | #e74c3c | Error (rojo) |
| `--ts-info` | #1976d2 | Info (azul) |
| `--ts-dark` | #1a1a2e | Sidebar oscuro |

## Responsive

- **> 1024px**: Layout completo
- **768-1024px**: Grids reducidos
- **< 768px**: Sidebar colapsada (solo iconos)
- **< 480px**: Sidebar oculta

## Ejemplos de uso

Ver `ts-admin-panel.html` para ejemplo completo.

## Proyectos que usan este sistema

1. **Feedback NPS** - `/feedback/admin.php`

---
*tuSpeaking - Enero 2026*
