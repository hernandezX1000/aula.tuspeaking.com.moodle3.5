
## 2026-07-10 — Fix ingesta Zoom + crons caídos

### i3code_download_zoomdata.php (desplegado a mano; fichero en .gitignore por API key Acuity)
- Bug: getZoomAPIParticipants(string $meetingID) recibía null (reunión sin ID) → TypeError línea 368 → abortaba TODA la ingesta desde 2026-06-24.
- Efecto: participantes Zoom sin sincronizar → panel marcaba ausencias en clases sí dadas (Barrena, Nieves, Carlos).
- Fix: parámetro nullable + `if (empty($meetingID)) return '{}';`. Backup: ~/i3code_download_zoomdata.php.bak_20260710.

### Crons caídos por permisos de logs
- /home/aulatuspeaking/logs era de root (sin escritura) → redirect fallido → autocorrect/digest/quiz/moodle_cron/asistencia_zoom no corrían.
- Fix (sin root): logs movidos a ~/hansel_logs (crontab reapuntado con sed).
- Pendiente: dead-man's-switch para avisar si un cron clave no corre en 24h.
