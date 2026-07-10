# ROADMAP — aula.tuspeaking.com

Desarrollos previstos, agrupados por tema. Esto es el "qué queremos construir";
las tareas concretas y su estado viven en `BACKLOG.md`.

Última revisión: 2026-07-10.

---

## 1. Notificaciones y observabilidad

**Objetivo:** enterarse del estado de todo de un vistazo, sin ruido.

- **Digest de estado unificado** (8:00 y 20:00): un email con todos los procesos por cron, cada uno con qué hace, por qué importa y estado (✅/⚠️/❌ + última ejecución). Agrupado por bloques (datos/asistencia, backups/seguridad, reportes, monitor). → BACKLOG NOT-1.
- **Alerta inmediata crítica:** email en el momento del fallo solo para lo grave (ingesta caída, backup BD fallido, cron clave >24h sin correr). → BACKLOG NOT-2.
- **Canal de envío:** helper `send_alert.py` por Gmail SMTP (el mismo que ya usa Moodle), credenciales en `~/.env`. Sustituye el `mail` local (lento, va a spam).
- **Futuro:** heartbeat que además vigile disco/swap (MON-1) y verificación de backup (que ya alerta) integrada en el digest.

## 2. Autocorrector — Fase 2 (FUNDAE)

**Objetivo:** que en empresas FUNDAE la nota la valide un tutor (con su IP registrada) antes de publicarse. Detalle completo en `docs/reference/PENDIENTES_TECNICOS.md`.

Componentes:
1. **Config por empresa** (`mdl_ts_autocorrect_config` o `config_empresas.json`): modo `auto` vs `fundae`.
2. **Modo validación** en `hansel_autocorrect.py`: guardar en `inmarking` (no visible al alumno) en vez de publicar.
3. **Mini-panel de validación** para tutores (PHP, patrón `reportes_cesce/`): ve propuesta IA, edita, valida.
4. **Tokens WS por tutor** (crítico para que la IP FUNDAE sea la del tutor).
5. **Flag FUNDAE automático** desde Success.

Estado actual: **Fase 1 en producción** (corrección automática de writings —texto y fichero— y audios). Fase 2 sin empezar.

## 3. Ingesta y asistencia — robustez

**Objetivo:** que las asistencias cuadren solas y no haya "verificando" crónico.

- **Feeder `own_acuity`** que importe reservas nuevas/reprogramadas (ING-1) — condición para cerrar el problema de fondo de "verificando".
- **Prevención de clases atascadas** (ING-2): reglas para auto-resolver o marcar según señales Zoom/Acuity.
- **Higiene:** `$log_path` absoluto (ING-3), monitor de la propia ingesta ya existe (`verificar_ingesta_zoom.php` 9:30).

## 4. Seguridad e higiene de infra

**Objetivo:** quitar secretos del código y del crontab.

- Rotar token GitHub (SEC-1), API key Acuity + secret Zoom (SEC-4).
- `secrets.php` para los ~51 ficheros con contraseña en duro y **rotar** la BD (SEC-2).
- Contraseñas del crontab → `~/.my.cnf` 600 (SEC-3).
- `.pptx` grandes fuera del repo o a Git LFS (REPO-1).

## 5. Success / paneles

- Arreglar toggle "finalizado" (SUC-1, cross-ref tuspeaking-platform).
- Integración FUNDAE ↔ autocorrector (ver Fase 2, punto 5).

---

### Horizonte sugerido

- **Ahora (esta semana):** NOT-1/NOT-2 (notificaciones), SEC-1 (token), OPS-1/OPS-2 (recuperaciones E2Y).
- **Corto plazo:** SEC-2/SEC-3, ING-1, AC-3.
- **Medio plazo:** Autocorrector Fase 2, ING-2 prevención, REPO-1.
