
## 2026-07-10 — Corregir writings subidos como fichero (rama dev)

### hansel_autocorrect.py — leer adjuntos, no solo texto pegado
- **Problema:** `fetch_pending_writings` solo leía `mdl_assignsubmission_onlinetext`
  (texto pegado en la cajita). Los alumnos que **suben el writing como fichero**
  (.docx/.pdf/.odt/.txt/.rtf) quedaban con `onlinetext` vacío → el corrector no los
  veía → sin corregir. Era ~24 entregas de 35 pendientes (jul-2026). No es regresión:
  es una funcionalidad que nunca existió (el sistema "probado y funcionando" solo
  cubría texto pegado y los audios de tareas "AUDIO DELIVERY").
- **Fix:** nueva ruta `fetch_pending_file_writings` + `extract_text_from_file`
  (docx→python-docx, pdf→pypdf, odt→odfpy, txt→plano, rtf→striprtf) + pipeline
  `process_file_writings`, que reutiliza `call_claude_writing`/`moodle_save_grade`
  y respeta `--dry-run`, `EXCLUDED_SUBMISSIONS`, detección de IA y nota_max.
- Excluye tareas de audio (no solapa con `fetch_pending_audio`) y filtra por fecha
  ≥2026-01-01, DEMO/prueba/italiano igual que los writings de texto.
- **Deps nuevas:** `pip3 install --user python-docx pypdf odfpy striprtf`.
- Flujo: desarrollado en rama **dev**, probado con `--dry-run` en server, PR→main→deploy.

## 2026-07-10 — Fix ingesta Zoom + crons caídos

### i3code_download_zoomdata.php (desplegado a mano; fichero en .gitignore por API key Acuity)
- Bug: getZoomAPIParticipants(string $meetingID) recibía null (reunión sin ID) → TypeError línea 368 → abortaba TODA la ingesta desde 2026-06-24.
- Efecto: participantes Zoom sin sincronizar → panel marcaba ausencias en clases sí dadas (Barrena, Nieves, Carlos).
- Fix: parámetro nullable + `if (empty($meetingID)) return '{}';`. Backup: ~/i3code_download_zoomdata.php.bak_20260710.

### Crons caídos por permisos de logs
- /home/aulatuspeaking/logs era de root (sin escritura) → redirect fallido → autocorrect/digest/quiz/moodle_cron/asistencia_zoom no corrían.
- Fix (sin root): logs movidos a ~/hansel_logs (crontab reapuntado con sed).
- Pendiente: dead-man's-switch para avisar si un cron clave no corre en 24h.
