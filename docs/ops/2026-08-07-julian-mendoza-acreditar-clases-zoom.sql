-- ═══════════════════════════════════════════════════════════════════════════
-- Julián Mendoza (GDES · Italiano Principiante · Paola Demarchi)
-- Acreditar las clases REALMENTE impartidas, según el registro de Zoom
-- ═══════════════════════════════════════════════════════════════════════════
--
-- SITUACIÓN (07-ago-2026)
--   El panel del alumno muestra 1 clase asistida de 48 contratadas → 2% de
--   asistencia, en un curso FUNDAE que termina el 29-ago-2026.
--
--   Motivo: las 34 reservas de Acuity tienen `acuity_location = "The Zoom meeting
--   was cancelled."` y `zoom_meetingid` NULL — nunca hubo sala en esas citas.
--   Las clases SÍ se impartieron, pero en reuniones creadas por la profesora
--   FUERA del horario de Acuity (las citas eran 18:00/18:30; las clases reales
--   se dieron por la mañana o media tarde).
--
-- EVIDENCIA
--   Zoom API, cuenta corporativa, usuario paola@live.tuspeaking.com:
--     GET /report/users/paola@live.tuspeaking.com/meetings?from=&to=
--   29 reuniones con "Curso de Italiano. (Julián Mendoz…" en el topic, 2
--   participantes y ~30 min. Se listan abajo con su Meeting ID.
--   (Se DESCARTA la del 04-jun 15:04 — 2 min y 1 solo participante: no fue clase.)
--
--   Contraste: las otras alumnas de Paola sí tienen sus clases correctas
--   (Catherine Salas 10/10, Martín Specht 9/13). El problema es de ESTA serie
--   de reservas, no de la profesora ni del sistema.
--
-- ⚠️ ANTES DE EJECUTAR
--   1. `aula-sql --write mdl_i3code_acuityZoom` (deja copia de la tabla).
--   2. Revisar que los IDs de alumno/curso/profesora que resuelven las
--      subconsultas son los correctos (bloque 0).
--   3. Ejecutar el bloque 1 (SELECT) y comprobar que salen 29 filas.
--
-- Horas: Zoom devuelve UTC; aquí van ya convertidas a hora española (+02:00).
-- acuityid: rango 99000000xx, ficticio, para no chocar con los IDs de Acuity.
-- ═══════════════════════════════════════════════════════════════════════════


-- ─── BLOQUE 0 · Comprobar a quién apuntan las subconsultas ──────────────────
SELECT
  (SELECT id FROM mdl_user WHERE email = 'paola@live.tuspeaking.com')            AS teacherid_paola,
  (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id = 107194)                AS studentid_julian,
  (SELECT courseid  FROM mdl_i3code_acuityZoom WHERE id = 107194)                AS courseid_italiano;


-- ─── BLOQUE 1 · Estado actual (para comparar después) ──────────────────────
SELECT zoom_clasecompletada AS estado, acuity_canceled AS cancel, COUNT(*) AS clases
FROM mdl_i3code_acuityZoom
WHERE studentid = (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id = 107194)
GROUP BY estado, cancel;


-- ─── BLOQUE 2 · Insertar las 29 clases reales ──────────────────────────────
-- Cada fila = una reunión de Zoom verificada.
-- zoom_clasecompletada = 1 (asistida) · manual_override = 1 (el sync no las toca)

INSERT INTO mdl_i3code_acuityZoom
  (acuityid, courseid, studentid, teacherid,
   acuity_datetime, acuity_duration, acuity_type, acuity_location,
   zoom_meetingid, zoom_starttime, zoom_duration,
   zoom_clasecompletada, acuity_canceled, acuity_rescheduled,
   manual_override, manual_motivo, manual_fecha)
SELECT * FROM (
  SELECT 9900000001 AS acuityid, c.courseid, c.studentid, c.teacherid, '2026-05-08T10:58:00+0200' AS dt, 30 AS dur, 'Curso de Italiano.' AS tp, 'Zoom directo (fuera de Acuity)' AS loc, 84554055747 AS mid, '2026-05-08 10:58:00' AS st, 29 AS zdur, 1 AS comp, 0 AS can, 0 AS resch, 1 AS ovr, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 84554055747, 29 min, 2 participantes). La cita de Acuity no tenia sala.' AS mot, NOW() AS mfec FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000002, c.courseid, c.studentid, c.teacherid, '2026-05-15T15:23:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 88459167793, '2026-05-15 15:23:00', 41, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 88459167793, 41 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000003, c.courseid, c.studentid, c.teacherid, '2026-05-15T16:04:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 88569814525, '2026-05-15 16:04:00', 23, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 88569814525, 23 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000004, c.courseid, c.studentid, c.teacherid, '2026-05-22T12:56:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 86489201155, '2026-05-22 12:56:00', 34, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 86489201155, 34 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000005, c.courseid, c.studentid, c.teacherid, '2026-05-22T13:30:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 82750102525, '2026-05-22 13:30:00', 28, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 82750102525, 28 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000006, c.courseid, c.studentid, c.teacherid, '2026-05-25T16:56:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 89821739613, '2026-05-25 16:56:00', 33, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 89821739613, 33 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000007, c.courseid, c.studentid, c.teacherid, '2026-05-26T17:59:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 81716374287, '2026-05-26 17:59:00', 26, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 81716374287, 26 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000008, c.courseid, c.studentid, c.teacherid, '2026-06-01T12:54:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 83672910375, '2026-06-01 12:54:00', 37, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 83672910375, 37 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000009, c.courseid, c.studentid, c.teacherid, '2026-06-01T13:32:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 83060936895, '2026-06-01 13:32:00', 24, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 83060936895, 24 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000010, c.courseid, c.studentid, c.teacherid, '2026-06-03T15:50:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 82099993577, '2026-06-03 15:50:00', 36, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 82099993577, 36 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000011, c.courseid, c.studentid, c.teacherid, '2026-06-03T16:27:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 89108471499, '2026-06-03 16:27:00', 31, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 89108471499, 31 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000012, c.courseid, c.studentid, c.teacherid, '2026-06-04T17:28:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 87930684410, '2026-06-04 17:28:00', 26, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 87930684410, 26 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000013, c.courseid, c.studentid, c.teacherid, '2026-06-04T17:54:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 84059686322, '2026-06-04 17:54:00', 33, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 84059686322, 33 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000014, c.courseid, c.studentid, c.teacherid, '2026-06-08T15:04:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 81098423465, '2026-06-08 15:04:00', 25, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 81098423465, 25 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000015, c.courseid, c.studentid, c.teacherid, '2026-06-08T15:29:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 82244513969, '2026-06-08 15:29:00', 30, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 82244513969, 30 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000016, c.courseid, c.studentid, c.teacherid, '2026-06-18T17:20:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 85997843339, '2026-06-18 17:20:00', 40, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 85997843339, 40 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000017, c.courseid, c.studentid, c.teacherid, '2026-06-18T18:00:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 86702791041, '2026-06-18 18:00:00', 33, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 86702791041, 33 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000018, c.courseid, c.studentid, c.teacherid, '2026-07-14T14:58:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 89297084574, '2026-07-14 14:58:00', 38, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 89297084574, 38 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000019, c.courseid, c.studentid, c.teacherid, '2026-07-14T15:36:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 89625610408, '2026-07-14 15:36:00', 22, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 89625610408, 22 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000020, c.courseid, c.studentid, c.teacherid, '2026-07-20T15:27:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 88168348190, '2026-07-20 15:27:00', 31, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 88168348190, 31 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000021, c.courseid, c.studentid, c.teacherid, '2026-07-20T15:58:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 89754055408, '2026-07-20 15:58:00', 31, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 89754055408, 31 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000022, c.courseid, c.studentid, c.teacherid, '2026-07-22T14:57:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 81045351211, '2026-07-22 14:57:00', 38, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 81045351211, 38 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000023, c.courseid, c.studentid, c.teacherid, '2026-07-22T15:34:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 85763214165, '2026-07-22 15:34:00', 23, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 85763214165, 23 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000024, c.courseid, c.studentid, c.teacherid, '2026-07-27T12:56:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 89784704146, '2026-07-27 12:56:00', 33, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 89784704146, 33 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000025, c.courseid, c.studentid, c.teacherid, '2026-07-27T13:29:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 82533435731, '2026-07-27 13:29:00', 29, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 82533435731, 29 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000026, c.courseid, c.studentid, c.teacherid, '2026-07-28T12:59:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 81797023451, '2026-07-28 12:59:00', 27, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 81797023451, 27 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000027, c.courseid, c.studentid, c.teacherid, '2026-07-28T13:25:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 88071252483, '2026-07-28 13:25:00', 28, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 88071252483, 28 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000028, c.courseid, c.studentid, c.teacherid, '2026-07-29T09:57:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 84003007593, '2026-07-29 09:57:00', 30, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 84003007593, 30 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900000029, c.courseid, c.studentid, c.teacherid, '2026-07-29T10:26:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (fuera de Acuity)', 85724842711, '2026-07-29 10:26:00', 32, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 85724842711, 32 min).', NOW() FROM (SELECT (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id=107194) studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=107194) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
) AS nuevas;


-- ─── BLOQUE 3 · Verificar ──────────────────────────────────────────────────
SELECT zoom_clasecompletada AS estado, acuity_canceled AS cancel, COUNT(*) AS clases
FROM mdl_i3code_acuityZoom
WHERE studentid = (SELECT studentid FROM mdl_i3code_acuityZoom WHERE id = 107194)
GROUP BY estado, cancel;
-- Esperado: 30 filas con estado 1 (las 29 nuevas + la que ya estaba acreditada).

SELECT id, acuity_datetime, zoom_meetingid, zoom_duration, zoom_clasecompletada, manual_override
FROM mdl_i3code_acuityZoom
WHERE acuityid BETWEEN 9900000001 AND 9900000029
ORDER BY acuity_datetime;


-- ─── DESHACER, si hiciera falta ────────────────────────────────────────────
-- DELETE FROM mdl_i3code_acuityZoom WHERE acuityid BETWEEN 9900000001 AND 9900000029;


-- ═══════════════════════════════════════════════════════════════════════════
-- DESPUÉS DE ESTO — no queda cerrado con el INSERT
--
-- 1. AGOSTO ESTÁ A CERO. Ninguna clase impartida este mes y el curso acaba el
--    29-ago. Quedan 6 citas (10, 12, 17, 19, 24 y 26) y ninguna tiene sala:
--    `acuity_location = "The Zoom meeting was cancelled."`. La del lunes 10 a
--    las 18:30 hay que resolverla ANTES del lunes.
--
-- 2. LA CAUSA SIGUE VIVA. Mientras las clases se den en salas creadas a mano
--    fuera de Acuity, el aula no las verá y habrá que repetir esto cada mes.
--    Hablar con Paola: las clases deben darse en la sala de la cita.
--
-- 3. REVISAR SI HAY MÁS ALUMNOS ASÍ:
--      SELECT CONCAT(a.firstname,' ',a.lastname) alumno, COUNT(*) clases,
--             SUM(z.zoom_meetingid IS NULL) sin_sala, SUM(z.zoom_clasecompletada=1) acreditadas
--      FROM mdl_i3code_acuityZoom z JOIN mdl_user a ON a.id=z.studentid
--      WHERE z.acuity_location = 'The Zoom meeting was cancelled.'
--      GROUP BY alumno ORDER BY clases DESC;
--
-- 4. FUNDAE: con 30 de 48 el alumno queda en el 62%, por debajo del 75%.
--    Hay margen hasta el 29-ago para recuperar, pero hay que planificarlo ya.
-- ═══════════════════════════════════════════════════════════════════════════
