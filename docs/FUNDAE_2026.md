# FUNDAE 2026 - Sistema de Gestión

## Resumen
Sistema para gestión de bonificaciones FUNDAE en plataforma aula.tuspeaking.com.

## Componentes

### 1. Tabla BD: `mdl_fundae`
Tabla personalizada con datos FUNDAE de los cursos bonificables.
- 16 campos (courseid, c_fundae, empresa, modalidad, horas, fechas, etc.)
- Índices: courseid, c_fundae

### 2. Bloque: block_fundae
Plugin de Moodle 3.5 para visualizar el dashboard FUNDAE.
- **Repo:** https://github.com/hernandezX1000/Moodle-3.5-block-for-FUNDAE-2026-dashboard
- **Versión actual:** v1.0.1
- **Estado:** En producción

### 3. Usuario Inspector FUNDAE
- ID: 5676 (Fundae Micro2026)
- Email: fundaemicro2026@tuspeaking.com
- Rol: Supervisor (roleid=10)
- Cursos asignados: 129 cursos bonificables 2026

## Tickets cerrados
- TICKET-001: Tabla mdl_fundae creada
- TICKET-002: Datos test Equivalenza
- TICKET-003: Estructura Moodle 3.5
- TICKET-004: Documentación base
- TICKET-005: Bloque FUNDAE desarrollado (v1.0.0)
- TICKET-006: Bloque FUNDAE instalado en producción (v1.0.1)

## Tickets pendientes
- TICKET-007: Insertar 128 cursos en mdl_fundae
- TICKET-008: Mejorar bloque con tabla de datos
- TICKET-009: Limpiar perfil Inspector FUNDAE
- TICKET-010: Testing y documentación final

## Capabilities del bloque
- block/fundae:myaddinstance (contextlevel 10)
- block/fundae:addinstance (contextlevel 80)
- block/fundae:view (contextlevel 80)

## Instancia activa
- block_instances ID: 60789
- parentcontextid: 563849 (usuario 5676)
- region: content
- pagetypepattern: my-index
