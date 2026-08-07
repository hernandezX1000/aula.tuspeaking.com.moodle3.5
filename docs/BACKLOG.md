# BACKLOG — aula.tuspeaking.com

**Fuente única de tareas, bugs y desarrollos del Moodle.** Todo lo abierto vive aquí.
Los detalles largos van en `docs/tickets/`, `docs/incidents/` y `docs/reference/`, referenciados desde la tabla.

- Convención de IDs: `AREA-nn` (SEC, ING, AC, COMP, NOT, SUC, REPO, MON, MIG, OPS).
- Estados: 🔴 abierto · 🟡 en curso · 🟢 resuelto.
- Prioridad: **Alta** / Media / Baja.
- Última revisión: 2026-08-07.

---

## Abierto (por prioridad)

| ID | Título | Área | Prio | Estado | Detalle |
|----|--------|------|------|--------|---------|
| SEC-1 | Rotar token GitHub expuesto (en `~/.git-credentials`) | Seguridad | **Alta** | 🔴 | Antiguo TICKET #3 |
| SEC-2 | Sacar credenciales BD en duro (~51 ficheros propios) + rotar contraseña | Seguridad | **Alta** | 🔴 | TICKET #1 / BUG-002 |
| COMP-1 | Verificar que las actividades finalizadas muestran el completion correcto | Completion | **Alta** | 🔴 | Caso Enrique Saña (Tekia B2): 66% vs 75% necesario |
| COMP-2 | Frances B2 (GDES 2026.2, course 3242): 42 Páginas con `completion=2` sin `completionview` → no se marcaban | Completion | **Alta** | 🟡 | Reportado por Daniela (Nicolás 5822) y Guillaume (Luis Miguel 5823). **FIX aplicado 29-jul**: `completionview=1` en las 42 Páginas + backfill de 29 vistas ya hechas. Verificado en Success (Nicolás 11%→31%, Luis Miguel 16%→33%). **PENDIENTE**: barrido de prevención (otros cursos / curso maestro). `docs/incidents/2026-07-29-nicolas-bilanin-completion.md` |
| COMP-3 | HVP con grade_items duplicados en course 3242 (GDES Frances B2) — completion nunca se activa aunque el alumno tenga nota | Completion | **Alta** | 🔴 | 03-ago-2026. Instancias 275096–275101 (cmids 530444/530445/530454/530455/530464/530465): cada una tiene 2 grade_items (313xxx vacíos + 320xxx donde H5P escribe la nota). Completion lee 313xxx → ve NULL → nunca completa. Fix: borrar los grade_items 313307/313308/313311/313312/313315/313316 (vacíos) y purgar cachés. Verificar que no hay grade_grades apuntando a ellos antes de borrar. Afecta a todos los alumnos del curso. |
| ING-2 | Prevenir clases atascadas en "Verificando asistencia" (reproceso ya hecho) | Ingesta | **Alta** | 🔴 | TICKET #8 · `docs/2026-07-09-reproceso-verificando.md` |
| SUC-1 | Toggle "finalizado" no detecta a Eduardo (Senator) | Success | **Alta** | 🔴 | BUG-001 · cross-ref tuspeaking-platform |
| MIG-1 | Migración a Hetzner (aula + cesce + baiwingin) | Migración | **Alta** | 🟡 | ROADMAP §6 · offsite ya siembra la BD; PHP resuelto por Docker · **cutover aula HECHO 26-jul** (ver tuspeaking-lms/docs/CUTOVER-AULA-2026-07-26.md) |
| MIG-2 | Rutas `/app/moodle/` hardcodeadas rompen en Hetzner (42 ficheros) | Migración | **Alta** | 🟡 | Hetzner sirve Moodle en RAÍZ. Dos tipos: **URLs** `aula.tuspeaking.com/app/moodle/`→`/` y `/app/moodle/`→`/`; **rutas fs** `/home/aulatuspeaking/www/app/moodle/`→`/var/www/html/app/moodle/`. Fix aplicado en dev (revisar diff) · desplegar con deploy-aula.sh |
| MIG-3 | Activar deploy en el server: checkout `/home/coreadmin/aula-repo` + probar `deploy-aula.sh` | Migración | **Alta** | 🔴 | 1ª vez del nuevo flujo de deploy (creado, no probado) |
| BK-1 | **Backup de CESCE vacío (4 KB) al menos 4-6 ago informando "OK"** | Backups | **Alta** | 🟡 | 07-ago-2026. Causa: `mysqldump \| gzip` sin `set -o pipefail` → `$?` es el de gzip, que siempre triunfa; el guard `-s` no filtra 4 KB. Mismo patrón en el offsite: `echo "Offsite sync OK"` incondicional tras `rclone --quiet`. Script v2 escrito en `tuspeaking-lms/scripts/backup-moodle.sh` (pipefail + umbral de tamaño + rclone comprobado + saca la contraseña del fichero). **Pendiente**: desplegar, y averiguar por qué CESCE falló esos días y volvió solo el 7-ago |
| MON-4 | Cron de **feedback** (cada 30m) no existe en el Hetzner: ¿reactivar o retirar del digest? | Monitor | Media | 🔴 | 07-ago-2026. Es el único ⚠️ que queda en el digest. No hay script ni cron en el server. **Decisión de Hansel pendiente** |
| NOT-4 | `hansel_digest.py` envía por `sendmail`, no por Gmail SMTP | Monitor | Baja | 🔴 | 07-ago-2026. El status digest y el heartbeat ya usan `send_alert.py`; este no. `sendmail` llega tarde y con remitente que cae en spam |
| SEC-3 | Contraseñas BD en texto plano en el crontab → `~/.my.cnf` (600) | Seguridad | Media | 🔴 | TICKET #4 |
| SEC-4 | Rotar API key Acuity + secret Zoom (llevan tiempo en el server) | Seguridad | Media | 🔴 | CLAUDE.md pendientes |
| SEC-5 | Swapfile 2-4 GB en el Hetzner (prep migración) | Seguridad | Media | 🔴 | 3.7 GB RAM sin swap |
| ING-1 | Feeder `own_acuity` no importa reservas nuevas/reprogramadas | Ingesta | Media | 🔴 | TICKET #7 |
| AC-3 | Endurecer parseo JSON en `call_claude_writing` (fallo transitorio) | Autocorrector | Media | 🔴 | Sesión 10-jul |
| REPO-1 | `.pptx` grandes en `contenido/business_english/` → Git LFS o excluir | Infra repo | Media | 🔴 | TICKET #2 |
| REPO-2 | Ignorar caché FUNDAE + log de limpieza (dejan sucio el árbol de prod) | Infra repo | Baja | 🔴 | Sesión 17-jul |
| OPS-1 | Recuperación Linda Mohnssen (E2Y, ~5 clases) | Docente | Media | 🔴 | Sesión 10-jul |
| OPS-2 | Recuperación + migración FUNDAE Adrian Barrena (E2Y) | Docente | Media | 🔴 | Sesión 10-jul |
| OPS-4 | Juan Antonio Muñoz (Hyatt) — 2 clases en "Verificando asistencia" + reprogramar | Ingesta/Ops | Media | 🟡 | 03-ago-2026; instancia de ING-2; borrador enviado pidiendo fecha. **Fix parcial 03-ago**: id 111009 (30/07 16:00 Dehlia) cerrado con acuity_canceled=1+manual_override. Pendiente: confirmar fecha reprogramación |
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

**MON-2 / MON-3 / AC-4 / REPO-3 — Monitorización post-Hetzner (07-ago).** Los cuatro salen de
revisar el digest del 07/08 08:00. Ninguno rompe el servicio hoy; los cuatro hacen que un
fallo futuro pase en silencio. **Orden obligatorio: REPO-3 → AC-4 → MON-3** (reconciliar la
deriva antes de tocar nada, o el deploy pisa la versión buena de producción). **MON-2** es
crontab en el servidor y va en paralelo. Diagnóstico SSH pendiente y detalle completo en
`docs/tickets/2026-08-07-monitorizacion-crons-post-hetzner.md`. De fondo: el digest vigila
*frescura de logs*, no *resultado* — un cron que corre y falla sale ✅.

**AC-2 / AC-3 — Autocorrector.** AC-2: entregas con `onlinetext` corto (>10 y <80) **más** un adjunto: el pipeline de texto las salta por cortas y el de fichero las excluye por tener algo de texto → cambiar el filtro a `<MIN_WRITING_CHARS`. AC-3: Claude devuelve a veces JSON malformado (una comilla sin escapar) → reintento/reparación en el parseo.

---

## Resueltos recientes (2026-08)

| ID | Título | Fecha | Nota |
|----|--------|-------|------|
| MON-2 | Heartbeat sin cron en el Hetzner (sin dead-man's-switch desde el cutover) | 2026-08-07 | `heartbeat_crons.sh` **v2** reescrito (la v1 apuntaba a rutas de Dinahosting: habría mandado 4 falsas alertas/hora). Cron horario activo + canal Gmail SMTP verificado de punta a punta. Ahora también vigila el backup automático del día |
| MON-3 | Check de backup daba ✅ con un volcado manual | 2026-08-07 | `check_file()`: solo cuenta el automático (`_YYYYMMDD_HHMM.sql.gz` sin sufijo) y ordena por `mtime`. Probado contra el escenario real del 07/08 |
| AC-4 | Scripts conectaban a `localhost` → error 1698 | 2026-08-07 | Solo `hansel_digest.py` seguía roto (los otros ya estaban bien en prod). Desplegado y verificado: digest con `0 errores` |
| REPO-3 | Deriva prod→repo en 3 scripts Python | 2026-08-07 | Producción iba **por delante** (check del feeder de reservas, `.env` multi-ruta, `MOODLE_WS_URL` en raíz). Rescatado a `_prod_reconcile/` y reconciliado ANTES de desplegar. Regla añadida a `CLAUDE.md`: `md5sum` antes de todo deploy |
| DOC-1 | Los 3 `CLAUDE.md` desalineados (y `tuspeaking-lms` sin ninguno) | 2026-08-07 | Criterio único de reparto; infra de CESCE reescrita (describía Dinahosting, cancelado el 4-ago); histórico de Dinaserver del aula movido a `<details>` |

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
