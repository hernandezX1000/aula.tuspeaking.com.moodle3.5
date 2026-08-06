# TICKET — Reactivar encuestas NPS al alumno (post-clase)

**Estado:** [ ] Pendiente · decisión tomada 6-ago-2026 (Hansel: reactivar)
**Prioridad:** Media

## Qué es

Encuesta NPS que se enviaba al alumno **30-90 min después de cada clase** para que
puntuara la sesión (0-10). Recoge en `own_feedback_nps` (+ `own_feedback_envios`,
`own_feedback_optout`, `own_feedback_config`, `own_feedback_logs`, `own_feedback_teams_*`).
Da stats por profesor. **NO** es el feedback pedagógico del profesor (eso ya vive en
Learn/`ai_db` → `tutor_feedback`, `/learn/feedback`).

## Estado actual (por qué no funciona)

Muerta desde la migración: última respuesta **31-jul-2026** (15.332 históricas). El
**script sender se perdió** en el borrado del 1-ago y NO se restauró (ni en el servidor ni
en los tgz de rescate). Sobreviven solo:
- `feedback/config_abstraction.php` — capa de queries (en git + servidor). Tiene
  `fb_get_clases_para_feedback()` (busca clases terminadas hace 30-90 min en
  `mdl_i3code_acuityZoom`), `fb_get_alumno`, `fb_get_profesor`, `fb_get_profesor_stats`.
- `formFeedback.php` — formulario que rellena el alumno. **Recuperable** de
  `_rescate_dinahosting_20260803/aula-root-php-rescue.tgz`.
- `feedback/historico.csv` + tablas `own_feedback_*` en BD (datos intactos).

## Qué falta (rebuild del sender)

1. **Restaurar `formFeedback.php`** desde el tgz de rescate al servidor + versionarlo en git
   (ojo `.gitignore /*` de raíz → añadir `!/formFeedback.php`).
2. **Rehacer el sender** (`feedback/enviar_nps.php` o similar): usa
   `fb_get_clases_para_feedback()`, arma el email con el enlace a `formFeedback.php?...`,
   registra en `own_feedback_envios`, respeta `own_feedback_optout`, y **envía por Resend**
   (mismo método que el resto: HTML con enlaces envueltos — ver runbook envío-mail).
3. **Cron cada 30 min** en Hetzner (wrapper como `run_ingesta.sh`, sin `%` sin escapar):
   `*/30 * * * * /home/coreadmin/scripts/run_nps.sh`
4. **Probar** con un alumno de test y confirmar fila en `own_feedback_envios` + email recibido.
5. **Actualizar el digest**: cambiar la nota "Feedback (cada 30m) — pendiente configurar"
   por un check real (log fresco del cron NPS).

## Inventario del sistema NPS original (según admin-panel/tools.json del rescate)

Ficheros que tenía el sistema (todos PERDIDOS salvo los marcados):
- `feedback/config_abstraction.php` — capa de queries. **SOBREVIVE** (git + servidor).
- `formFeedback.php` — formulario público que rellena el alumno. **RECUPERABLE** de
  `_rescate_dinahosting_20260803/aula-root-php-rescue.tgz`.
- `feedback/admin.php` — panel admin NPS. PERDIDO.
- `feedback/admin_nuevo.php` — dashboard NPS (`?s=dashboard`). PERDIDO.
- `feedback/profesores.php` — gestión/stats por profesor. PERDIDO.
- El **sender** (cron 30 min, envía la encuesta). PERDIDO — no está en ningún backup
  (verificado por CONTENIDO en los 5 tgz de rescate, el tar de código del 1-ago, el
  código vivo del servidor y el Object Storage). Hay que **rehacerlo**.

Verificado 6-ago-2026: `grep -rlE 'own_feedback_nps|fb_get_clases_para_feedback' ` sobre
todo lo anterior → solo `config_abstraction.php`.

## Estado del enlace al alumno (verificado 6-ago-2026)

- **Ningún email automático se envía** (sender apagado) → no se manda ningún enlace roto.
- El **botón "Feedback"** que `newAcuity.php` añade a cada evento de clase apunta a
  `formFeedback.php` (existe, 12.5 KB). Con id real devuelve **302 → `/app/moodle`** (rebota a
  la home, NO da error): el flujo NPS está incompleto, así que el formulario no renderiza.
  El alumno que lo pulse aterriza en su home, sin ver error. Al rehacer el NPS hay que
  **arreglar `formFeedback.php`** (que renderice + guarde en `own_feedback_nps`). Opcional
  mientras tanto: quitar el botón de feedback de `newAcuity.php` (línea ~182) para no llevar
  a un callejón — pero es inocuo (no es error), se puede dejar para el rebuild.

## Notas

- Las queries del sender ya están resueltas en `config_abstraction.php` (FEEDBACK_ENV='moodle').
- Credenciales/plantillas de Resend: ver memoria "envio-mail-procedimiento".
- Riesgo si se reactiva sin cuidado: reenviar encuestas de clases viejas → filtrar por
  `own_feedback_envios` (no reenviar) y por ventana de tiempo estricta (30-90 min).
