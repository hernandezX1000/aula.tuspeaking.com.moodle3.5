# BACKLOG — aula.tuspeaking.com

**Fuente única de tareas, bugs y desarrollos del Moodle.** Todo lo abierto vive aquí.
Los detalles largos van en `docs/tickets/`, `docs/incidents/` y `docs/reference/`, referenciados desde la tabla.

- Convención de IDs: `AREA-nn` (SEC, ING, AC, COMP, NOT, SUC, REPO, MON, MIG, OPS).
- Estados: 🔴 abierto · 🟡 en curso · 🟢 resuelto.
- Prioridad: **Alta** / Media / Baja.
- Última revisión: 2026-07-10.

---

## Abierto (por prioridad)

| ID | Título | Área | Prio | Estado | Detalle |
|----|--------|------|------|--------|---------|
| SEC-1 | Rotar token GitHub expuesto (en `~/.git-credentials`) | Seguridad | **Alta** | 🔴 | Antiguo TICKET #3 |
| SEC-2 | Sacar credenciales BD en duro (~51 ficheros propios) + rotar contraseña | Seguridad | **Alta** | 🔴 | TICKET #1 / BUG-002 |
| COMP-1 | Verificar que las actividades finalizadas muestran el completion correcto | Completion | **Alta** | 🔴 | Caso Enrique Saña (Tekia B2): 66% vs 75% necesario |
| ING-2 | Prevenir clases atascadas en "Verificando asistencia" (reproceso ya hecho) | Ingesta | **Alta** | 🔴 | TICKET #8 · `docs/2026-07-09-reproceso-verificando.md` |
| SUC-1 | Toggle "finalizado" no detecta a Eduardo (Senator) | Success | **Alta** | 🔴 | BUG-001 · cross-ref tuspeaking-platform |
| MIG-1 | Migración a Hetzner (aula + cesce + baiwingin) | Migración | **Alta** | 🟡 | ROADMAP §6 · offsite ya siembra la BD; PHP resuelto por Docker · **cutover aula HECHO 26-jul** (ver tuspeaking-lms/docs/CUTOVER-AULA-2026-07-26.md) |
| MIG-2 | Rutas `/app/moodle/` hardcodeadas rompen en Hetzner (42 ficheros) | Migración | **Alta** | 🟡 | Hetzner sirve Moodle en RAÍZ. Dos tipos: **URLs** `aula.tuspeaking.com/app/moodle/`→`/` y `/app/moodle/`→`/`; **rutas fs** `/home/aulatuspeaking/www/app/moodle/`→`/var/www/html/app/moodle/`. Fix aplicado en dev (revisar diff) · desplegar con deploy-aula.sh |
| MIG-3 | Activar deploy en el server: checkout `/home/coreadmin/aula-repo` + probar `deploy-aula.sh` | Migración | **Alta** | 🔴 | 1ª vez del nuevo flujo de deploy (creado, no probado) |
| SEC-3 | Contraseñas BD en texto plano en el crontab → `~/.my.cnf` (600) | Seguridad | Media | 🔴 | TICKET #4 |
| SEC-4 | Rotar API key Acuity + secret Zoom (llevan tiempo en el server) | Seguridad | Media | 🔴 | CLAUDE.md pendientes |
| SEC-5 | Swapfile 2-4 GB en el Hetzner (prep migración) | Seguridad | Media | 🔴 | 3.7 GB RAM sin swap |
| ING-1 | Feeder `own_acuity` no importa reservas nuevas/reprogramadas | Ingesta | Media | 🔴 | TICKET #7 |
| AC-3 | Endurecer parseo JSON en `call_claude_writing` (fallo transitorio) | Autocorrector | Media | 🔴 | Sesión 10-jul |
| REPO-1 | `.pptx` grandes en `contenido/business_english/` → Git LFS o excluir | Infra repo | Media | 🔴 | TICKET #2 |
| REPO-2 | Ignorar caché FUNDAE + log de limpieza (dejan sucio el árbol de prod) | Infra repo | Baja | 🔴 | Sesión 17-jul |
| OPS-1 | Recuperación Linda Mohnssen (E2Y, ~5 clases) | Docente | Media | 🔴 | Sesión 10-jul |
| OPS-2 | Recuperación + migración FUNDAE Adrian Barrena (E2Y) | Docente | Media | 🔴 | Sesión 10-jul |
| AC-2 | Writings con texto pegado corto + adjunto (41657, 41728) caen entre pipelines | Autocorrector | Baja | 🔴 | Sesión 10-jul |
| ING-3 | Fijar `$log_path` absoluto en `i3code_download_zoomdata.php` (línea 21) | Ingesta | Baja | 🔴 | Sesión 10-jul |
| NOT-3 | Retirar cron `monitor_carga.sh` (redundante: el digest ya vigila disco/swap) | Monitor | Baja | 🔴 | Sesión 10-jul |
| OPS-3 | Samsung survey (cron 8:00): decidir si pausar/retirar | Ops | Baja | 🔴 | Sesión 10-jul |

---

## Notas por ítem

**SEC-1 — Token GitHub.** Un PAT con permiso `repo` quedó expuesto en texto plano; sigue activo en `~/.git-credentials`. Crear token nuevo, actualizar `~/.git-credentials`, revocar el viejo en GitHub → Settings → Tokens. *Rotar es lo que cierra el riesgo.*

**SEC-2 — Credenciales BD en duro.** ~51 ficheros propios (`feedback/*`, `empresas/*`, `evaluaciones/*`, varios `reportes_cesce/*`, etc.) llevan la contraseña en duro. Plan: `secrets.php` fuera del repo → `require` en cada fichero → probar → **rotar** la contraseña en MySQL. Gitignored, pero existe en el server sin control.

**ING-2 — Verificando.** El reproceso puntual está hecho; falta la **prevención** para que no se vuelvan a atascar. Ligado a que la ingesta y el sync corran sanos (ya arreglado el bug del null 10-jul) y al feeder `own_acuity` (ING-1).

**NOT-1 / NOT-2 — Notificaciones.** Diseño acordado: un solo digest a las 8:00 y 20:00 con todos los procesos (qué hace, por qué importa, estado ✅/⚠️/❌) + alerta inmediata solo para fallos críticos (ingesta caída, backup fallido, cron clave >24h sin correr). Todo por Gmail SMTP (`smtp.gmail.com:465`, el que ya usa Moodle). Reemplaza el digest de las 7 y calla los correos sueltos. Ver ROADMAP.

**COMP-1 — Estado de finalización (completion).** Varios alumnos ven un % de progreso por debajo del real porque actividades que **sí completaron** no quedan marcadas como realizadas en el completion tracking de Moodle (`mdl_course_modules_completion`). Ejemplo: Enrique Saña (esana@tekia.es, "2026.2 - Tekia - Ingles B2"), en `/my/` ve **66%** y necesita **75%** antes de que acabe el curso la semana que viene. Objetivo del ticket: un check que detecte actividades con criterio de finalización cumplido (entrega calificada, quiz aprobado, recurso visto) pero sin marcar como completadas, y las corrija/avise. Acción inmediata aparte: revisar y arreglar el caso de Enrique antes del cierre.

**AC-2 / AC-3 — Autocorrector.** AC-2: entregas con `onlinetext` corto (>10 y <80) **más** un adjunto: el pipeline de texto las salta por cortas y el de fichero las excluye por tener algo de texto → cambiar el filtro a `<MIN_WRITING_CHARS`. AC-3: Claude devuelve a veces JSON malformado (una comilla sin escapar) → reintento/reparación en el parseo.

---

## Resueltos recientes (2026-07)

| ID | Título | Fecha | Nota |
|----|--------|-------|------|
| NOT-1/2 | Digest de estado 8:00 y 20:00 + `send_alert.py` (Gmail SMTP) | 2026-07-10 | Desplegado + cron `0 8,20`. Vigila crons, backups, disco/swap/SSL y offsite |
| SEC-off | Backup offsite de la BD al Hetzner (rsync nocturno + alerta si falla) | 2026-07-10 | `tuspeaking-lms`; 1ª copia hecha; `backup_offsite.sh` + cron 3:00 |
| MON-1 | Vigilancia de disco / swap (+ caducidad SSL) | 2026-07-10 | Integrado en el digest (sustituye a `monitor_carga`) |
| AC-0 | Corregir writings subidos como fichero (.docx/.pdf/.odt/.txt/.rtf) | 2026-07-10 | Desplegado; 19 corregidos. `tus-tools/autocorrect/CHANGELOG.md` |
| ING-0 | Bug ingesta Zoom: `getZoomAPIParticipants(null)` tumbaba toda la ingesta | 2026-07-10 | `?string` + guard. Verificada sana |
| INF-0 | Crons caídos por permisos de logs (root) + heartbeat instalado | 2026-07-10 | Logs → `~/hansel_logs`; dead-man's-switch cada hora |
| TICKET#6 | Charset utf8mb4 en ingesta Zoom/Acuity | 2026-07-09 | `docs/2026-07-09-fix-sync-ingesta.md` |

**REPO-2 — Ignorar caché FUNDAE y log de limpieza.** `admin-panel/cache_fundae.json` y `reportes_cesce/limpieza.log` están **trackeados** pero se reescriben en runtime → ensucian siempre el árbol de prod y pueden bloquear `git pull` en deploy. Hacer **desde el Mac** (rama `dev`): añadir ambos a `.gitignore` + `git rm --cached` → commit → merge a `main` → deploy. En el deploy, en prod: `git checkout -- <ficheros>` antes del pull. *(Detectado 17-jul revisando estado git; NO hacerlo en la sesión SSH del server.)*
