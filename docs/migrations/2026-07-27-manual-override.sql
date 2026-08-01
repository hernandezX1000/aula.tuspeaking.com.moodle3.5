-- Migración 2026-07-27 — columna manual_override en mdl_i3code_acuityZoom
--
-- Objetivo: que la ingesta (admin/cli/i3code_download_zoomdata.php) RESPETE las
-- correcciones de asistencia hechas a mano desde el panel (coding_zoom_audit.php)
-- y no las revierta en su vuelta de las 04:05.
--
-- - manual_override = 1  → la ingesta NO toca el estado de esa clase.
-- - manual_motivo/fecha  → auditoría (quién/por qué; el "quién" también queda en coding_zoom_audit_log).
--
-- Idempotente (MariaDB soporta ADD COLUMN IF NOT EXISTS). Ejecutar una vez en prod.
-- Tras ejecutar: purgar cachés de Moodle.

ALTER TABLE mdl_i3code_acuityZoom
  ADD COLUMN IF NOT EXISTS manual_override TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS manual_motivo   VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS manual_user     BIGINT NULL,
  ADD COLUMN IF NOT EXISTS manual_fecha    DATETIME NULL;
