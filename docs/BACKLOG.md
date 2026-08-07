# BACKLOG — aula.tuspeaking.com

**Fuente única de tareas, bugs y desarrollos del Moodle.** Todo lo abierto vive aquí.
Los detalles largos van en `docs/tickets/`, `docs/incidents/` y `docs/reference/`, referenciados desde la tabla.

- Convención de IDs: `AREA-nn` (SEC, ING, AC, COMP, NOT, SUC, REPO, MON, MIG, OPS).
- Estados: 🔴 abierto · 🟡 en curso · 🟢 resuelto.
- Prioridad: **Alta** / Media / Baja.
- Última revisión: 2026-08-07 (añadidos SEC-6/7, MON-5, NOT-5, ING-4/5/6, OPS-5, MIG-4, COMP-4 tras la caída del 07-ago y la incidencia de Sonia Funes).

---

## Abierto (por prioridad)

| ID | Título | Área | Prio | Estado | Detalle |
|----|--------|------|------|--------|---------|
| SEC-1 | Rotar token GitHub expuesto (en `~/.git-credentials`) | Seguridad | **Alta** | 🔴 | Antiguo TICKET #3 |
| SEC-2 | Sacar credenciales BD en duro + rotar contraseña | Seguridad | **Alta** | 🔴 | Ampliado por auditoría 6-ago → `docs/tickets/TICKET-SEGURIDAD-rotar-credenciales.md`. Password BD `TuspeakingFix2025!` está en el histórico de git (`misclases.php`) |
| COMP-1 | Verificar que las actividades finalizadas muestran el completion correcto | Completion | **Alta** | 🔴 | Caso Enrique Saña (Tekia B2): 66% vs 75% necesario |
| COMP-2 | Frances B2 (GDES 2026.2, course 3242): 42 Páginas con `completion=2` sin `completionview` → no se marcaban | Completion | **Alta** | 🟡 | Reportado por Daniela (Nicolás 5822) y Guillaume (Luis Miguel 5823). **FIX aplicado 29-jul**: `completionview=1` en las 42 Páginas + backfill de 29 vistas ya hechas. Verificado en Success (Nicolás 11%→31%, Luis Miguel 16%→33%). **PENDIENTE**: barrido de prevención (otros cursos / curso maestro). `docs/incidents/2026-07-29-nicolas-bilanin-completion.md` |
| COMP-3 | **H5P registra el resultado del alumno pero NO escribe la nota en el libro de calificaciones** | Completion | **Alta** | 🔴 | **RE-DIAGNOSTICADO 07-ago-2026.** El mecanismo NO es el que decía este ticket (no es que completion lea el `grade_item` equivocado; los duplicados con `itemnumber=NULL` son **inocuos**, hay ejercicios con duplicado perfectamente completados). Lo real: `mdl_hvp_xapi_results` guarda el resultado (Luis Miguel: 5/5 y 3/4) pero `mdl_grade_grades.finalgrade` queda **NULL** → sin nota, el criterio "completar con nota" nunca se cumple. **Correlación observada:** funciona cuando el ítem activo es de la serie `313xxx` (notas escritas en mayo) y falla cuando es de la serie `320xxx` (creados después). **Causa del porqué: SIN DETERMINAR.** Afecta potencialmente a 10 cursos. Ver `docs/tickets/2026-08-07-comp3-hvp-nota-no-llega.md` |
| AC-7 | **PROYECTO: corrección de entregas + portal de tutorización docente** | Autocorrect | **Alta** | 🟡 | 07-ago-2026. Ticket paraguas del tema completo. **Cron del autocorrector PAUSADO** (`/tmp/crontab.bak-20260807`). Fases: (1) auditoría de correcciones **pre-2026** — quién figura como corrector y si hay lotes con la misma marca de tiempo; (2) foto global 2026; (3) **portal para que las tutoras revisen y publiquen** — decidir plugin Moodle 3.5 vs integrarlo en Teach, depende de cuándo se apague el aula; (4) cron pausado hasta cerrar 1-3. Agrupa AC-2, AC-5, AC-6 y COMP-3. `docs/tickets/2026-08-07-ac7-proyecto-correcciones-y-portal-docente.md` |
| COMP-4 | Limpiar los 34 `grade_items` duplicados con `itemnumber=NULL` (10 cursos) | Completion | Baja | 🔴 | 07-ago-2026. Cosméticos: no impiden el completion (comprobado). **NO borrarlos con prisa**: el 07-ago se estuvo a punto de eliminar 34 en producción creyendo que eran la causa, y no lo eran |
| ING-2 | Prevenir clases atascadas en "Verificando asistencia" (reproceso ya hecho) | Ingesta | **Alta** | 🔴 | TICKET #8 · `docs/2026-07-09-reproceso-verificando.md`. **07-ago-2026: la acumulación ha crecido en agosto** — 27-jul: 2/17 · 29-jul: 2/16 · 30-jul: 3/16 · **3-ago: 6/16** · 4-ago: 3/7 · 5-ago: 3/10 · 6-ago: 4/15. El alumno ve "Verificando asistencia" durante días y no sabe si su clase cuenta (caso Jaume Alsina, OPS-6). Investigar qué cambió a principios de agosto |
| OPS-6 | Jaume Alsina (Velcro) — clase 05/08 perdida por discrepancia de horario con la profesora | Ops | Media | 🟢 | **RESUELTO 07-ago-2026.** Reserva correcta a las 12:00 (`acuityid 1715054126`), ambos eventos de calendario a las 12:00, sin reprogramar, `modifiedtimes=0`. Dehlia Williams la tenía anotada a las 11:30. Reunión Zoom creada pero **0 participantes**: no conectó ninguno. Marcada `manual_override=1` + `acuity_canceled=1` para que no compute al alumno ni la revierta el sync. **Pendiente decisión de negocio:** si se le devuelve el derecho a recuperación (el UPDATE no lo hace) y si se comenta con la profesora |
| SUC-1 | Toggle "finalizado" no detecta a Eduardo (Senator) | Success | **Alta** | 🔴 | BUG-001 · cross-ref tuspeaking-platform |
| MIG-1 | Migración a Hetzner (aula + cesce + baiwingin) | Migración | **Alta** | 🟡 | ROADMAP §6 · offsite ya siembra la BD; PHP resuelto por Docker · **cutover aula HECHO 26-jul** (ver tuspeaking-lms/docs/CUTOVER-AULA-2026-07-26.md) |
| MIG-2 | Rutas `/app/moodle/` hardcodeadas rompen en Hetzner (42 ficheros) | Migración | **Alta** | 🟡 | Hetzner sirve Moodle en RAÍZ. Dos tipos: **URLs** `aula.tuspeaking.com/app/moodle/`→`/` y `/app/moodle/`→`/`; **rutas fs** `/home/aulatuspeaking/www/app/moodle/`→`/var/www/html/app/moodle/`. **Fix parcial 03-ago**: `MOODLE_WS_URL` y `MOODLE_DATAROOT` en autocorrector corregidos; token WS creado (id 28, "autocorrect-script"); coreadmin añadido a www-data. Pendiente: resto de 42 ficheros. |
| MIG-3 | Activar deploy en el server: checkout `/home/coreadmin/aula-repo` + probar `deploy-aula.sh` | Migración | **Alta** | 🔴 | 1ª vez del nuevo flujo de deploy (creado, no probado) |
| BK-1 | **Backup de CESCE vacío (4 KB) al menos 4-6 ago informando "OK"** | Backups | **Alta** | 🟡 | 07-ago-2026. Causa: `mysqldump \| gzip` sin `set -o pipefail` → `$?` es el de gzip, que siempre triunfa; el guard `-s` no filtra 4 KB. Mismo patrón en el offsite: `echo "Offsite sync OK"` incondicional tras `rclone --quiet`. Script v2 escrito en `tuspeaking-lms/scripts/backup-moodle.sh` (pipefail + umbral de tamaño + rclone comprobado + saca la contraseña del fichero). **Pendiente**: desplegar, y averiguar por qué CESCE falló esos días y volvió solo el 7-ago |
| SEC-6 | Blindaje PHP-como-root + consultas diarias a la BD | Seguridad | **Alta** | 🔴 | 07-ago-2026. **➡️ El trabajo vive en `tuspeaking-lms/docs/tickets/2026-08-07-blindaje-php-root-y-consultas-bd.md`** (es infraestructura: wrapper en `/usr/local/bin`, `GRANT` de MariaDB, healthcheck). Aquí solo la referencia. Incluye `aula-sql` (usuario solo lectura + copia de tabla antes de escribir) y `docker restart moodle35-app` como reparación de la caída |
| ~~MON-5~~ | *Movido* — el chequeo de propiedad de moodledata es infraestructura | Monitor | — | ➡️ | Fusionado en el ticket de `tuspeaking-lms` (arriba). Corregido: `find` **horario y con `-mmin -70`**, no cada 5 min sin acotar — moodledata son 252 GiB / 196.939 objetos y un barrido completo cada 5 minutos es carga sostenida sobre el disco de producción |
| NOT-5 | La mensajería interna no genera notificación al profesor | Notificaciones | **Alta** | 🔴 | 07-ago-2026. Sonia Funes (4827) envió 5 mensajes a Rachel McCobb (4276) el 05/08; `mdl_notifications` no tiene ninguna notificación de mensajería para 4276 entre el 05 y el 07. Además, 4 de los 5 mensajes tienen `smallmessage` **vacío**. Rompe el único canal alumno→profesor y genera no-shows injustos. `docs/incidents/2026-08-07-sonia-funes-no-show.md` |
| ING-4 | El enlace de clase falla y el alumno entra sin sesión → **no-show falso** | Ingesta/Acceso | **Alta** | 🔴 | 07-ago-2026. Participante Zoom con **nombre numérico y sin email** = entró sin sesión de Moodle. Caso 06/08 meeting 88609489017: la alumna entró 18 min tarde y quedó como ausencia. Hipótesis: el control de cupo del bono bloquea también el **acceso** a una clase ya reservada, no solo la reserva. Barrido pendiente para ver cuántos casos más hay sin reclamar |
| ING-5 | El informe cuenta las **canceladas** como ausencia; y un alumno puede acumular más citas que su bono | Ingesta | **Alta** | 🔴 | 07-ago-2026. `acuity_canceled=1` con `zoom_clasecompletada=0` → el panel la pinta "Cancelada" pero el informe la suma a `clases_no_asistidas`, y Success (RRHH de la empresa) ve una ausencia que el alumno no ve. Fix: filtrar `acuity_canceled=0` en los `SUM()` del sync. Relacionado: una fila pasada en `estado 3` no la cuenta ninguna rama → se llegó a 9 citas sobre un bono de 8 |
| ING-6 | `zoom_clasecompletada = 2` **sin documentar** (~4.910 filas) | Ingesta | Media | 🔴 | 07-ago-2026. Los runbooks solo documentan `0`/`1`/`3`. El valor `2` afecta a ~5% de los registros y casi todos tienen `zoom_meetingid`. Cualquier cálculo de asistencia que asuma tres estados lo está ignorando. Buscar el valor en el plugin i3code y en `i3code_download_zoomdata.php`, y documentarlo |
| OPS-5 | Alumnos con **cuentas duplicadas** (mismo nombre, dominios distintos) | Ops | Media | 🔴 | 07-ago-2026. Sonia Funes tiene `3201` (`@hyattic.eu`, datos de 2023) y `4827` (`@hyatt.com`, activa). Un `LIMIT 1` a ciegas hizo diagnosticar la cuenta equivocada. Riesgos: soporte sobre datos falsos, alumno que "no ve nada", y ruido en el registro de conexiones FUNDAE. ⚠️ No borrar sin comprobar si la cuenta vieja sostiene evidencia de un expediente ya presentado |
| MIG-4 | La imagen `moodle35-staging-moodle` dice "Staging / aula-test" **siendo producción** | Migración | Media | 🔴 | 07-ago-2026. El banner de arranque de `moodle35-app` induce a creer que no es producción; costó una hipótesis errónea en plena caída. Renombrar la imagen o cambiar el banner |
| COMP-4 | Bloque roto del Área personal: `blocks/html/dashboard_welcome.php not found` | Completion/UI | Media | 🔴 | 07-ago-2026, visible en `moodle-error.log` con referer `/my/`. **Anterior** a la caída del mismo día, problema distinto |
| SEC-7 | Bot escaneando **webshells** en aula.tuspeaking.com | Seguridad | Media | 🔴 | 07-ago-2026 09:36, ráfaga desde un mismo cliente: `wp-conflg.php`, `123.php`, `yanz.php`, `system_log.php`, `wp-cron.php`… Todos 404, no encontró nada. Conviven 3 WordPress en el mismo host. Propuesta: fail2ban sobre 404 en ráfaga + revisar si algún intento devolvió 200. Registrado también un `Suspended Login: tautvydas.bagocius@autentia.com` vía `Go-http-client/1.1` |
| ING-7 | **Los recordatorios de clase a los alumnos se cortan cada noche** por dos cuentas inactivas | Ingesta/Notif | **Alta** | 🟡 | 07-ago-2026. `newAcuity.php` creaba un evento de calendario por reserva para **Guillermo (48, borrado en 2018)** y **Carmen (2, suspendida)**; el cron de recordatorios abortaba al llegar al de Guillermo → alumnos sin aviso desde el **26-jul**. **Fix hecho en `dev`, PENDIENTE DE DESPLEGAR** — plan paso a paso, validación y rollback en `docs/tickets/2026-08-07-ing7-recordatorios-usuario-borrado.md` |
| MON-4 | Cron de **feedback** (cada 30m) no existe en el Hetzner: ¿reactivar o retirar del digest? | Monitor | Media | 🔴 | 07-ago-2026. Es el único ⚠️ que queda en el digest. No hay script ni cron en el server. **Decisión de Hansel pendiente** |
| NOT-4 | `hansel_digest.py` envía por `sendmail`, no por Gmail SMTP | Monitor | Baja | 🔴 | 07-ago-2026. El status digest y el heartbeat ya usan `send_alert.py`; este no. `sendmail` llega tarde y con remitente que cae en spam |
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

**ING-7 — Recordatorios de clase rotos desde el 26-jul-2026.** *(fix en `dev`, falta desplegar → ver `docs/tickets/2026-08-07-ing7-recordatorios-usuario-borrado.md`)*

**Síntoma:** cada noche el cron de Moodle procesa los recordatorios en orden, envía unos
cuantos y **aborta la tarea entera** al llegar a un evento concreto. Todos los eventos
posteriores se quedan **sin recordatorio**. En el log:

```
[Local Reminder] All reminders was sent successfully for event#636059 !
[Local Reminder] All reminders was sent successfully for event#636060 !
[Local Reminder] Starting sending reminders for 636061 [type: user]
Scheduled task failed: legacy_plugin_cron_task, Usuario no válido
```

**Causa raíz:** cada clase reservada crea el evento de calendario para el alumno **y una
copia para tres cuentas de seguimiento**. Se ve clarísimo en los eventos futuros:

| userid | eventos | quién |
|---|---|---|
| 14 | 192 | hfernandez |
| 2 | 192 | admin |
| **48** | **192** | **Guillermo Bethencourt — cuenta BORRADA (`deleted=1`), último acceso oct-2018** |
| 4276 · 2771 · … | 51 · 36 · … | alumnas reales (correcto) |

Nadie retiró al usuario 48 de esa lista al borrarle la cuenta. Ocho años después, **cada
clase sigue generando un evento para un usuario inexistente**, y el plugin de recordatorios
revienta al intentar avisarle.

**Impacto:** alumnos que no reciben el aviso de su clase → **no-shows injustificados**.
Encaja con los desajustes de asistencia que se arrastran.

**Fix (2 partes):**
1. Quitar el `48` de la lista de destinatarios. **Ficheros localizados** (usan SQL crudo
   sobre `mdl_event`, no la API de Moodle, por eso no aparecían buscando `calendar_event`):

   ```
   newAcuity.php      ← crea la reserva. PRINCIPAL SOSPECHOSO
   eventupdater.php
   reminder.php
   modifyAcuity.php · cancelAcuity.php · cancelbymail.php
   formClass.php · formFeedback.php
   /home/coreadmin/scripts/sincronizar_acuityZoom.php
   ```

   Buscar el `48` junto al `14` y el `2` (van los tres en la misma lista de destinatarios).

   ⚠️ **Ninguno está versionado** — `.gitignore` de allow-list. `newAcuity.php` es
   **el mismo fichero que se perdió el 1-ago-2026** por esa regla (ver
   [[pipeline-reservas-acuity-aula]]). **Al arreglarlo, versionarlo**: añadirlo a la
   allow-list del `.gitignore` en el mismo commit, o se volverá a perder.
2. Borrar sus 192 eventos futuros (los de 14 y 2 son legítimos, se quedan):
   `DELETE e FROM mdl_event e JOIN mdl_user u ON u.id=e.userid
    WHERE e.eventtype='user' AND u.deleted=1 AND e.timestart > UNIX_TIMESTAMP()`
   Después, `UPDATE mdl_task_scheduled SET faildelay=0` en las dos tareas y relanzar.

**Aparte (no urgente):** hay 977 eventos huérfanos de 2019-2020 (6 usuarios borrados, todos
pasados). Son polvo, **no** la causa. Limpiar cuando haya ocasión.

**Cómo se encontró:** `audit-estado.sh`, en su primera ejecución, avisó de *"2 tareas
fallando"*. Antes, esas dos tareas llevaban 12 días en rojo sin que nada lo dijera.

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
| ING-1 | Feeder `own_acuity` caído (webhook Acuity mal apuntado) | 2026-08-06 | Repuntado a `newAcuity.php`, probado E2E. 16 reservas caídas recuperadas. `docs/2026-08-06-incidente-feeder-own-acuity.md` |
| FUN-3 | Bug FUNDAE: `patchZoom`/`generateTokenOAuth` rompía TODA reserva FUNDAE | 2026-08-06 | Grabación cloud eliminada (no se usa); `fundae_id` se mantiene |
| ING-5 | Cron de la ingesta no corría (`%` sin escapar en el crontab) | 2026-08-06 | Wrapper `docs/ops/run_ingesta.sh`; crontab corregido. Llevaba ~2 días sin correr |
| MON-2 | Digest: añadido check de **feeder** `own_acuity` + rutas de backup corregidas | 2026-08-06 | Cazaría este incidente el día 1. `tus-tools/autocorrect/hansel_status_digest.py` |
| SEC-bkp | Backup BD **CESCE** roto (dumpeaba el contenedor equivocado, 407 bytes) | 2026-08-06 | Corregido a `moodle35-db-cesce` → 130 MB |
| FUN-blocks | Bloques FUNDAE (dashboard + inspector) recuperados (perdidos 1-ago) | 2026-08-06 | Redesplegados de su repo a `blocks/`, cachés purgadas, funcionando |
| NPS-link | Enlace roto de la encuesta NPS (Acuity → `/feedback/` muerto) | 2026-08-06 | Botón de Acuity redirigido a `mailto:soporte@tuspeaking.com` (interino hasta FUN-1) |
| REPO-git | Código custom seguro versionado (local/theme/blocks + php raíz) | 2026-08-06 | 730 ficheros; `newAcuity.php` en git; guardrail `/*` en CLAUDE.md. `docs/AUDITORIA-COBERTURA-GIT.md` |
| MON-2b | Heartbeat sin cron en el Hetzner (sin dead-man's-switch desde el cutover) | 2026-08-07 | `heartbeat_crons.sh` **v2** reescrito (la v1 apuntaba a rutas de Dinahosting: habría mandado 4 falsas alertas/hora). Cron horario activo + canal Gmail SMTP verificado de punta a punta. Ahora también vigila el backup automático del día |
| MON-3 | Check de backup daba ✅ con un volcado manual | 2026-08-07 | `check_file()`: solo cuenta el automático (`_YYYYMMDD_HHMM.sql.gz` sin sufijo) y ordena por `mtime`. Probado contra el escenario real del 07/08 |
| AC-4 | Scripts conectaban a `localhost` → error 1698 | 2026-08-07 | Solo `hansel_digest.py` seguía roto (los otros ya estaban bien en prod). Desplegado y verificado: digest con `0 errores` |
| REPO-3 | Deriva prod→repo en 3 scripts Python | 2026-08-07 | Producción iba **por delante** (check del feeder de reservas, `.env` multi-ruta, `MOODLE_WS_URL` en raíz). Rescatado a `_prod_reconcile/` y reconciliado ANTES de desplegar. Regla añadida a `CLAUDE.md`: `md5sum` antes de todo deploy |
| DOC-1 | Los 3 `CLAUDE.md` desalineados (y `tuspeaking-lms` sin ninguno) | 2026-08-07 | Criterio único de reparto; infra de CESCE reescrita (describía Dinahosting, cancelado el 4-ago); histórico de Dinaserver del aula movido a `<details>` |
| COMP-3 | Ejercicios H5P con resultado y sin nota → nunca se completaban | 2026-08-07 | Paliado en 3 alumnos de GDES (Luis Miguel 5823, Nicolás 5822, Eduardo 5821) + barrido GDES limpio. **Causa raíz sin determinar.** `docs/tickets/2026-08-07-comp3-hvp-nota-no-llega.md` |
| AC-5 | El autocorrector escribía el feedback en inglés en cursos de otros idiomas | 2026-08-07 | Prompts parametrizados por idioma del curso + tono humano con tuteo. Detecta 6 idiomas (antes 3). `docs/tickets/2026-08-07-autocorrect-idioma-feedback.md` |
| AC-6 | Whisper (`base`) inventaba palabras en francés → notas de audio injustas (4,19/10 vs 6,98 en inglés) | 2026-08-07 | Los audios pasan a finalizar **por entrega**, sin nota. Fuera del inglés no se publica comentario automático hasta tener panel de revisión docente. `docs/tickets/2026-08-07-ac6-audios-finalizan-por-entrega.md` |

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
