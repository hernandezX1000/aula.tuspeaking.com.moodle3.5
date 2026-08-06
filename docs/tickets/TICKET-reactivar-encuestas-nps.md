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

## Notas

- Las queries del sender ya están resueltas en `config_abstraction.php` (FEEDBACK_ENV='moodle').
- Credenciales/plantillas de Resend: ver memoria "envio-mail-procedimiento".
- Riesgo si se reactiva sin cuidado: reenviar encuestas de clases viejas → filtrar por
  `own_feedback_envios` (no reenviar) y por ventana de tiempo estricta (30-90 min).
