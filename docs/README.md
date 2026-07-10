# docs/ — Guía de la documentación

Dónde va cada cosa. El objetivo es tener **una sola fuente** para cada tipo de información.

| Fichero / carpeta | Para qué | Cuándo escribir |
|-------------------|----------|-----------------|
| **BACKLOG.md** | Fuente única de tareas, bugs y desarrollos abiertos. Tabla priorizada con IDs. | Cada vez que surge o se cierra algo. |
| **ROADMAP.md** | Desarrollos previstos, agrupados por tema (el "qué queremos construir"). | Al planificar features nuevas. |
| **sessions/** | Bitácora por sesión de trabajo (`YYYY-MM-DD.md`): qué se hizo, qué se decidió, qué quedó. | Al final de cada sesión relevante. |
| **tickets/** | Detalle largo de un ticket concreto (SQL de diagnóstico, hipótesis…). Referenciado desde BACKLOG. | Cuando un ítem del backlog necesita más que una fila. |
| **incidents/** | Partes de incidencia (qué pasó, causa raíz, fix). | Tras una caída o incidente. |
| **reference/** | Documentación estable: auditoría, FUNDAE, sistema de corrección, pendientes técnicos Fase 2. | Documentación que no cambia a diario. |

## Flujo de trabajo (Moodle)

Desarrollo en local sobre rama **`dev`** → probar en server (p. ej. `--dry-run`) → merge **`dev→main`** → desplegar en server (`git pull` + `tus-tools/autocorrect/deploy.sh`).
Los ficheros con secretos (config.php, scripts con API keys) están en `.gitignore` y se despliegan a mano.

## IDs del backlog

`AREA-nn`: **SEC** (seguridad), **ING** (ingesta/asistencia), **AC** (autocorrector), **NOT** (notificaciones), **SUC** (Success/paneles), **REPO** (infra del repo), **MON** (monitor), **OPS** (operación/docente).

## Histórico

Los dos `TICKETS.md` (raíz y `docs/`) quedan como histórico; **la fuente viva es `BACKLOG.md`**.
