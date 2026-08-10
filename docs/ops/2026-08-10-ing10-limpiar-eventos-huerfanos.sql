-- =====================================================================
-- ING-10 · Limpieza de eventos de calendario huerfanos
-- Fecha: 2026-08-10
-- Contexto: B · aula Moodle 3.5 (aula.tuspeaking.com)
--
-- POR QUE
-- Entre el 08 y el 10-ago-2026 el webhook newAcuity.php creaba los 3 eventos
-- de calendario (alumno, profesor, Hansel) y despues fallaba al insertar en
-- own_acuity (ver docs/incidents/2026-08-10-ing10-webhook-acuity-caido.md).
-- Acuity reintenta, asi que cada reserva dejo varios trios de eventos sin
-- reserva asociada. El alumno ve la clase duplicada en su calendario.
--
-- ALCANCE MEDIDO (10-ago-2026, 11:30 CEST)
--   804 eventos · 7 usuarios · 3 patrones, todos de Acuity:
--     567  "2026  English Class (30 min) Clara Blomfield"
--     210  "2026  English Class (30 min) Candice Laz..."
--      27  "2026  English Class (30 min) Daniette Ho..."
--
-- SEGURIDAD
-- Ninguna clase real pierde su evento: toda reserva valida esta referenciada
-- en own_acuity (studenteventid, teachereventid, heventid, ceventid, geventid)
-- y queda excluida por el NOT EXISTS.
-- Desde ING-10 ceventid/geventid valen 0, que nunca coincide con un id real.
--
-- EJECUCION
--   aula-sql --write mdl_event      (copia la tabla antes de abrir)
--   ...pegar los bloques 1 y 2...
--   aula-php admin/cli/purge_caches.php
--
-- ROLLBACK
--   restaurar mdl_event desde la copia que crea aula-sql --write
-- =====================================================================


-- --- BLOQUE 1 · COMPROBACION (debe devolver 804) ---------------------

SELECT COUNT(*) AS a_borrar
FROM mdl_event
WHERE timemodified >= UNIX_TIMESTAMP('2026-08-08')
  AND eventtype = 'user'
  AND NOT EXISTS (
        SELECT 1 FROM own_acuity o
        WHERE mdl_event.id IN (o.studenteventid, o.teachereventid,
                               o.heventid, o.ceventid, o.geventid));


-- --- BLOQUE 2 · BORRADO (mismo WHERE, sin tocar) ---------------------

DELETE FROM mdl_event
WHERE timemodified >= UNIX_TIMESTAMP('2026-08-08')
  AND eventtype = 'user'
  AND NOT EXISTS (
        SELECT 1 FROM own_acuity o
        WHERE mdl_event.id IN (o.studenteventid, o.teachereventid,
                               o.heventid, o.ceventid, o.geventid));


-- --- BLOQUE 3 · VERIFICACION (debe devolver 0) -----------------------

SELECT COUNT(*) AS quedan
FROM mdl_event
WHERE timemodified >= UNIX_TIMESTAMP('2026-08-08')
  AND eventtype = 'user'
  AND NOT EXISTS (
        SELECT 1 FROM own_acuity o
        WHERE mdl_event.id IN (o.studenteventid, o.teachereventid,
                               o.heventid, o.ceventid, o.geventid));
