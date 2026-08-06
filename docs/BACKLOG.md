# BACKLOG — aula.tuspeaking.com

**Fuente única de tareas, bugs y desarrollos del Moodle.** Todo lo abierto vive aquí.
Los detalles largos van en `docs/tickets/`, `docs/incidents/` y `docs/reference/`, referenciados desde la tabla.

- Convención de IDs: `AREA-nn` (SEC, ING, AC, COMP, NOT, SUC, REPO, MON, MIG, OPS).
- Estados: 🔴 abierto · 🟡 en curso · 🟢 resuelto.
- Prioridad: **Alta** / Media / Baja.
- Última revisión: 2026-08-06.

---

## Abierto (por prioridad)

| ID | Título | Área | Prio | Estado | Detalle |
|----|--------|------|------|--------|---------|
| SEC-1 | Rotar token GitHub expuesto (en `~/.git-credentials`) | Seguridad | **Alta** | 🔴 | Antiguo TICKET #3 |
| SEC-2 | Sacar credenciales BD en duro + rotar contraseña | Seguridad | **Alta** | 🔴 | Ampliado por auditoría 6-ago → `docs/tickets/TICKET-SEGURIDAD-rotar-credenciales.md`. Password BD `TuspeakingFix2025!` está en el histórico de git (`misclases.php`) |
| COMP-1 | Verificar que las actividades finalizadas muestran el completion correcto | Completion | **Alta** | 🔴 | Caso Enrique Saña (Tekia B2): 66% vs 75% necesario |
| COMP-2 | Frances B2 (GDES 2026.2, course 3242): 42 Páginas con `completion=2` sin `completionview` → no se marcaban | Completion | **Alta** | 🟡 | Reportado por Daniela (Nicolás 5822) y Guillaume (Luis Miguel 5823). **FIX aplicado 29-jul**: `completionview=1` en las 42 Páginas + backfill de 29 vistas ya hechas. Verificado en Success (Nicolás 11%→31%, Luis Miguel 16%→33%). **PENDIENTE**: barrido de prevención (otros cursos / curso maestro). `docs/incidents/2026-07-29-nicolas-bilanin-completion.md` |
| COMP-3 | HVP con grade_items duplicados en course 3242 (GDES Frances B2) — completion nunca se activa aunque el alumno tenga nota | Completion | **Alta** | 🔴 | 03-ago-2026. Instancias 275096–275101 (cmids 530444/530445/530454/530455/530464/530465): cada una tiene 2 grade_items (313xxx vacíos + 320xxx donde H5P escribe la nota). Completion lee 313xxx → ve NULL → nunca completa. Fix: borrar los grade_items 313307/313308/313311/313312/313315/313316 (vacíos) y purgar cachés. Verificar que no hay grade_grades apuntando a ellos antes de borrar. Afecta a todos los alumnos del curso. |
| ING-2 | Prevenir clases atascadas en "Verificando asistencia" (reproceso ya hecho) | Ingesta | **Alta** | 🔴 | TICKET #8 · `docs/2026-07-09-reproceso-verificando.md` |
| SUC-1 | Toggle "finalizado" no detecta a Eduardo (Senator) | Success | **Alta** | 🔴 | BUG-001 · cross-ref tuspeaking-platform |
| MIG-1 | Migración a Hetzner (aula + cesce + baiwingin) | Migración | **Alta** | 🟡 | ROADMAP §6 · offsite ya siembra la BD; PHP resuelto por Docker · **cutover aula HECHO 26-jul** (ver tuspeaking-lms/docs/CUTOVER-AULA-2026-07-26.md) |
| MIG-2 | Rutas `/app/moodle/` hardcodeadas rompen en Hetzner (42 ficheros) | Migración | **Alta** | 🟡 | Hetzner sirve Moodle en RAÍZ. Dos tipos: **URLs** `aula.tuspeaking.com/app/moodle/`→`/` y `/app/moodle/`→`/`; **rutas fs** `/home/aulatuspeaking/www/app/moodle/`→`/var/www/html/app/moodle/`. **Fix parcial 03-ago**: `MOODLE_WS_URL` y `MOODLE_DATAROOT` en autocorrector corregidos; token WS creado (id 28, "autocorrect-script"); coreadmin añadido a www-data. Pendiente: resto de 42 ficheros. |
| MIG-3 | Activar deploy en el server: checkout `/home/coreadmin/aula-repo` + probar `deploy-aula.sh` | Migración | **Alta** | 🔴 | 1ª vez del nuevo flujo de deploy (creado, no probado) |
| SEC-3 | Contraseñas BD en texto plano en el crontab → `~/.my.cnf` (600) | Seguridad | Media | 🔴 | TICKET #4 |
| SEC-4 | Rotar API key Acuity + secret Zoom (llevan tiempo en el server) | Seguridad | Media | 🔴 | Ver `TICKET-SEGURIDAD`. Acuity key en histórico git (`newAcuity.php`, `admin/cli/i3code_download_zoomdata.php`, `empresas/acuity_zoom.php`) |
| SEC-5 | Swapfile 2-4 GB en el Hetzner (prep migración) | Seguridad | Media | 🔴 | 3.7 GB RAM sin swap |
| ING-1 | Feeder `own_acuity` no importa reservas nuevas/reprogramadas | Ingesta | Media | 🟢 | **RESUELTO 6-ago**: el webhook de Acuity apuntaba a rutas muertas (api.tuspeaking.com deshabilitado + `modifyAndCreateAcuity.php` 404). Repuntado a `newAcuity.php`, probado E2E. `docs/2026-08-06-incidente-feeder-own-acuity.md` |
| AC-4 | Pipeline audio no reconoce "Entrega: Audio" (solo "ENTREGA DE AUDIO"/"Audio Delivery") | Autocorrector | Media | 🟢 | 03-ago-2026. Fix: `OR a.name LIKE '%Entrega: Audio%'` y `'%Entrega: Pitch Audio%'` en `fetch_pending_audio`. 7 audios Nicolás procesados. |
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
| FUN-1 | Reactivar encuestas NPS (rehacer el sender perdido) | NPS/FUNDAE | Media | 🔴 | 6-ago. Sender perdido 1-ago, no recuperable; datos ok (15.332). Interino: botón NPS de Acuity → `mailto:soporte`. `docs/tickets/TICKET-reactivar-encuestas-nps.md` |
| FUN-2 | Gestión de requerimientos FUNDAE (Fase 0 MVP: CRUD + semáforo) | FUNDAE | Media | 🔴 | 6-ago. Herramienta perdida; datos ok (`mdl_fundae_requerimientos` 17 + `mdl_fundae_documentos` 58). Ticket en `tuspeaking-platform/docs/tickets/TICK-FUNDAE-REQUERIMIENTOS.md` |
| REPO-3 | Cobertura git: refactor secretos a `secrets.php` + versionar resto | Infra repo | Media | 🟡 | 6-ago. 730 ficheros seguros ya versionados (`docs/AUDITORIA-COBERTURA-GIT.md`). Falta el bloque con-secretos (=SEC-2/SEC-4) |
| REPO-4 | Versionar `cancelAcuity.php`, `formFeedback.php`, `backup-moodle.sh` + recuperar `modifyAndCreateAcuity.php` (reprogramaciones) | Infra repo | Media | 🔴 | 6-ago. `modifyAndCreateAcuity.php` está en el rescate de Dinahosting; cierra el hueco de reprogramaciones |
| ING-4 | Barrido sep-oct del backlog de reservas caídas (feeder 2-6 ago) | Ingesta | Media | 🔴 | 6-ago. 16 recuperadas (ago) con `docs/ops/reconciliar_own_acuity.py`; falta ampliar ventana a sep-oct |

---

## Notas por ítem

**SEC-1 — Token GitHub.** Un PAT con permiso `repo` quedó expuesto en texto plano; sigue activo en `~/.git-credentials`. Crear token nuevo, actualizar `~/.git-credentials`, revocar el viejo en GitHub → Settings → Tokens. *Rotar es lo que cierra el riesgo.*

**SEC-2 — Credenciales BD en duro.** ~51 ficheros propios (`feedback/*`, `empresas/*`, `evaluaciones/*`, varios `reportes_cesce/*`, etc.) llevan la contraseña en duro. Plan: `secrets.php` fuera del repo → `require` en cada fichero → probar → **rotar** la contraseña en MySQL. Gitignored, pero existe en el server sin control.

**ING-2 — Verificando.** El reproceso puntual está hecho; falta la **prevención** para que no se vuelvan a atascar. Ligado a que la ingesta y el sync corran sanos (ya arreglado el bug del null 10-jul) y al feeder `own_acuity` (ING-1).

**NOT-1 / NOT-2 — Notificaciones.** Diseño acordado: un solo digest a las 8:00 y 20:00 con todos los procesos (qué hace, por qué importa, estado ✅/⚠️/❌) + alerta inmediata solo para fallos críticos (ingesta caída, backup fallido, cron clave >24h sin correr). Todo por Gmail SMTP (`smtp.gmail.com:465`, el que ya usa Moodle). Reemplaza el digest de las 7 y calla los correos sueltos. Ver ROADMAP.

**COMP-1 — Estado de finalización (completion).** Varios alumnos ven un % de progreso por debajo del real porque actividades que **sí completaron** no quedan marcadas como realizadas en el completion tracking de Moodle (`mdl_course_modules_completion`). Ejemplo: Enrique Saña (esana@tekia.es, "2026.2 - Tekia - Ingles B2"), en `/my/` ve **66%** y necesita **75%** antes de que acabe el curso la semana que viene. Objetivo del ticket: un check que detecte actividades con criterio de finalización cumplido (entrega calificada, quiz aprobado, recurso visto) pero sin marcar como completadas, y las corrija/avise. Acción inmediata aparte: revisar y arreglar el caso de Enrique antes del cierre.

**AC-2 / AC-3 — Autocorrector.** AC-2: entregas con `onlinetext` corto (>10 y <80) **más** un adjunto: el pipeline de texto las salta por cortas y el de fichero las excluye por tener algo de texto → cambiar el filtro a `<MIN_WRITING_CHARS`. AC-3: Claude devuelve a veces JSON malformado (una comilla sin escapar) → reintento/reparación en el parseo.

---

## Resueltos recientes (2026-08)

| ID | Título | Fecha | Nota |
|----|--------|-------|------|
| ING-1 | Feeder `own_acuity` caído (webhook Acuity mal apuntado) | 2026-08-06 | Repuntado a `newAcuity.php`, probado E2E. 16 reservas caídas recuperadas. `docs/2026-08-06-incidente-feeder-own-acuity.md` |
| FUN-3 | Bug FUNDAE: `patchZoom`/`generateTokenOAuth` rompía TODA reserva FUNDAE | 2026-08-06 | Grabación cloud eliminada (no se usa); `fundae_id` se mantiene |
| ING-5 | Cron de la ingesta no corría (`%` sin escapar en el crontab) | 2026-08-06 | Wrapper `docs/ops/run_ingesta.sh`; crontab corregido. Llevaba ~2 días sin correr |
| MON-2 | Digest: añadido check de **feeder** `own_acuity` + rutas de backup corregidas | 2026-08-06 | Cazaría este incidente el día 1. `tus-tools/autocorrect/hansel_status_digest.py` |
| SEC-bkp | Backup BD **CESCE** roto (dumpeaba el contenedor equivocado, 407 bytes) | 2026-08-06 | Corregido a `moodle35-db-cesce` → 130 MB |
| FUN-blocks | Bloques FUNDAE (dashboard + inspector) recuperados (perdidos 1-ago) | 2026-08-06 | Redesplegados de su repo a `blocks/`, cachés purgadas, funcionando |
| NPS-link | Enlace roto de la encuesta NPS (Acuity → `/feedback/` muerto) | 2026-08-06 | Botón de Acuity redirigido a `mailto:soporte@tuspeaking.com` (interino hasta FUN-1) |
| REPO-git | Código custom seguro versionado (local/theme/blocks + php raíz) | 2026-08-06 | 730 ficheros; `newAcuity.php` en git; guardrail `/*` en CLAUDE.md. `docs/AUDITORIA-COBERTURA-GIT.md` |

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
