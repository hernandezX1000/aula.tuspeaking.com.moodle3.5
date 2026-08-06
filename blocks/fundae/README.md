# Block FUNDAE Dashboard

## Descripción
Bloque personalizado para Moodle 3.5+ que muestra un dashboard consolidado de cursos FUNDAE 2026.

## Características
- ✅ Tabla consolidada de cursos FUNDAE
- ✅ Filtro por empresa, modalidad, nivel
- ✅ Solo visible para rol Supervisor FUNDAE
- ✅ Soporte multiidioma (EN/ES)
- ✅ Vinculado a tabla mdl_fundae
- ✅ Acceso rápido a cursos 2026
- ✅ Información de participantes

## Instalación
1. Copiar carpeta fundae a /blocks/
2. Ir a Admin → Notificaciones
3. Moodle detectará e instalará automáticamente
4. Agregar bloque al dashboard del Inspector FUNDAE

## Configuración
- Acceso: Solo rol Supervisor (ID 10)
- Datos: Consulta tabla mdl_fundae
- Cursos: Filtrados por fullname LIKE 2026%

## Estructura de carpetas
fundae/
├── version.php           (información del plugin)
├── block_fundae.php      (clase principal)
├── README.md             (este archivo)
├── db/
│   └── install.php       (instalación)
├── lang/
│   ├── en/
│   │   └── block_fundae.php
│   └── es/
│       └── block_fundae.php
└── templates/
    └── (plantillas HTML si se necesitan)

## Requisitos
- Moodle 3.5 o superior
- Tabla mdl_fundae creada en BD
- Rol Supervisor asignado (ID 10)
- Usuario Inspector FUNDAE activo

## Funcionalidades
1. Visualizar tabla FUNDAE consolidada
2. Links directos a cursos
3. Información de participantes
4. Datos: C/Fundae, Empresa, Modalidad, Nivel, Horas
5. Soporte multiidioma automático

## Seguridad
- Solo accesible para rol Supervisor
- Valida permisos antes de mostrar datos
- No expone información sensible a otros roles

## Versión
1.0.0 - 13 de Junio de 2026

## Autor
Equipo Desarrollo Aula TuSpeaking
Email: admin@tuspeaking.com

## Licencia
GNU General Public License v3.0

## Historial de cambios
v1.0.0 - Versión inicial
  - Creación del bloque FUNDAE
  - Tabla consolidada de cursos
  - Soporte EN/ES
  - Integración con mdl_fundae

## Próximas características (v2.0)
- Reportes descargables
- Filtros avanzados
- Gráficos de progreso
- Exportar a PDF
- Notificaciones automáticas

## Contacto
Para reportar bugs o sugerencias:
Email: admin@tuspeaking.com
