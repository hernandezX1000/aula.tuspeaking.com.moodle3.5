-- Migración: charset utf8mb4 en el flujo de ingesta Zoom/Acuity
-- Fecha: 2026-07-09
-- Motivo: las 5 tablas del flujo estaban en latin1 -> el sync diario
--         (admin/cli/i3code_download_zoomdata.php) fallaba al escribir filas con
--         caracteres no-latin1 (p. ej. nombres chinos de Rubi: 蒋晓艳). Cada fila
--         que fallaba se saltaba, dejando a ese alumno sin datos en el panel.
--         Venía fallando ~12-142 filas/dia desde 2023 (primer log conservado).
--
-- Impacto: tras la migración, el informe (panel) se regenera sin errores.
-- Requisito: NO ejecutar mientras el sync está corriendo (ps aux | grep i3code_download).
-- BD: aulatuspeaking35 · MariaDB 10.5

ALTER TABLE mdl_i3code_acuityZoom              CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE mdl_i3code_acuityZoom_participants CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE mdl_i3code_acuityZoom_informe      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE own_acuity                         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE own_acuity_course                  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Verificación (esperado: utf8mb4 en las 5):
--   SHOW CREATE TABLE mdl_i3code_acuityZoom\G  (buscar DEFAULT CHARSET=utf8mb4)
--
-- Nota: la BD global sigue en latin1 (character_set_database=latin1). El config.php
-- de Moodle ya usa dboptions['dbcollation'] => 'utf8mb4_unicode_ci'. Con estas 5
-- tablas convertidas, el error diario del informe desaparece.
