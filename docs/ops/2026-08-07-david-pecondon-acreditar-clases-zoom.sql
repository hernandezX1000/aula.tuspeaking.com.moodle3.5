-- ═══════════════════════════════════════════════════════════════════════════
-- David Pecondon (GDES · Italiano A2 · Paola Demarchi)  ·  userid 5824
-- Acreditar las 32 clases impartidas según el registro de Zoom
-- ═══════════════════════════════════════════════════════════════════════════
--
-- SITUACIÓN (07-ago-2026) — más grave que el caso de Julián Mendoza
--   En `mdl_i3code_acuityZoom` solo tiene DOS filas, ambas del 30-mar-2026:
--     106587  30/03 14:00  estado 3  meeting 83218578869
--     106588  30/03 14:30  cancelada, sin sala
--   Ninguna acreditada. **No hay ni una sola reserva de Acuity desde marzo.**
--
--   Y en Zoom constan 32 clases impartidas entre el 21-may y el 3-ago: dos por
--   semana, 2 participantes, ~30 min, con "Curso de Italiano. (David Pecondo…"
--   en el topic.
--
--   Es decir: lleva más de cuatro meses recibiendo clase semanalmente y su
--   expediente está vacío. A diferencia de Julián, aquí las clases ni siquiera
--   se agendaron en Acuity: se dan directamente en salas creadas por la
--   profesora.
--
-- EVIDENCIA
--   GET /report/users/paola@live.tuspeaking.com/meetings (mes a mes, may→ago)
--   Mayo 4 · Junio 10 · Julio 16 · Agosto 2  =  32 clases
--
-- ⚠️ ANTES DE EJECUTAR: `aula-sql --write mdl_i3code_acuityZoom` (deja copia).
--
-- Horas convertidas de UTC a hora española (+02:00).
-- acuityid ficticio: rango 99000010xx (no choca con los IDs reales de Acuity).
-- ═══════════════════════════════════════════════════════════════════════════


-- ─── BLOQUE 0 · Comprobar los IDs ──────────────────────────────────────────
SELECT
  (SELECT id FROM mdl_user WHERE email = 'paola@live.tuspeaking.com') AS teacherid_paola,
  (SELECT id FROM mdl_user WHERE email = 'd.pecondon@gdes.com')       AS studentid_david,
  (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id = 106587)      AS courseid_italiano_a2;


-- ─── BLOQUE 1 · Estado actual ──────────────────────────────────────────────
SELECT zoom_clasecompletada AS estado, acuity_canceled AS cancel, COUNT(*) AS clases
FROM mdl_i3code_acuityZoom
WHERE studentid = (SELECT id FROM mdl_user WHERE email = 'd.pecondon@gdes.com')
GROUP BY estado, cancel;


-- ─── BLOQUE 2 · Insertar las 32 clases reales ──────────────────────────────
INSERT INTO mdl_i3code_acuityZoom
  (acuityid, courseid, studentid, teacherid,
   acuity_datetime, acuity_duration, acuity_type, acuity_location,
   zoom_meetingid, zoom_starttime, zoom_duration,
   zoom_clasecompletada, acuity_canceled, acuity_rescheduled,
   manual_override, manual_motivo, manual_fecha)
SELECT * FROM (
  SELECT 9900001001 AS acuityid, c.courseid, c.studentid, c.teacherid, '2026-05-21T12:04:00+0200' AS dt, 30 AS dur, 'Curso de Italiano.' AS tp, 'Zoom directo (sin cita en Acuity)' AS loc, 85003982775 AS mid, '2026-05-21 12:04:00' AS st, 28 AS zdur, 1 AS comp, 0 AS can, 0 AS resch, 1 AS ovr, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 85003982775, 28 min, 2 participantes). Sin cita en Acuity.' AS mot, NOW() AS mfec FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001002, c.courseid, c.studentid, c.teacherid, '2026-05-21T12:32:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 88234910931, '2026-05-21 12:32:00', 25, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 88234910931, 25 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001003, c.courseid, c.studentid, c.teacherid, '2026-05-28T12:57:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 83134059158, '2026-05-28 12:57:00', 30, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 83134059158, 30 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001004, c.courseid, c.studentid, c.teacherid, '2026-05-28T13:27:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 83602566436, '2026-05-28 13:27:00', 29, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 83602566436, 29 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001005, c.courseid, c.studentid, c.teacherid, '2026-06-04T12:56:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 83001689906, '2026-06-04 12:56:00', 37, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 83001689906, 37 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001006, c.courseid, c.studentid, c.teacherid, '2026-06-04T13:34:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 89129167976, '2026-06-04 13:34:00', 26, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 89129167976, 26 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001007, c.courseid, c.studentid, c.teacherid, '2026-06-08T12:48:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 83300739673, '2026-06-08 12:48:00', 39, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 83300739673, 39 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001008, c.courseid, c.studentid, c.teacherid, '2026-06-08T13:27:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 88140964488, '2026-06-08 13:27:00', 34, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 88140964488, 34 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001009, c.courseid, c.studentid, c.teacherid, '2026-06-15T12:58:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 88468414065, '2026-06-15 12:58:00', 32, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 88468414065, 32 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001010, c.courseid, c.studentid, c.teacherid, '2026-06-15T13:30:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 82156867171, '2026-06-15 13:30:00', 25, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 82156867171, 25 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001011, c.courseid, c.studentid, c.teacherid, '2026-06-22T13:23:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 81459953381, '2026-06-22 13:23:00', 39, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 81459953381, 39 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001012, c.courseid, c.studentid, c.teacherid, '2026-06-22T14:01:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 83304631305, '2026-06-22 14:01:00', 25, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 83304631305, 25 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001013, c.courseid, c.studentid, c.teacherid, '2026-06-29T12:58:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 86888316528, '2026-06-29 12:58:00', 30, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 86888316528, 30 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001014, c.courseid, c.studentid, c.teacherid, '2026-06-29T13:28:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 81079559064, '2026-06-29 13:28:00', 28, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 81079559064, 28 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001015, c.courseid, c.studentid, c.teacherid, '2026-07-06T12:57:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 86302497265, '2026-07-06 12:57:00', 29, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 86302497265, 29 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001016, c.courseid, c.studentid, c.teacherid, '2026-07-06T13:26:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 88997653983, '2026-07-06 13:26:00', 30, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 88997653983, 30 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001017, c.courseid, c.studentid, c.teacherid, '2026-07-09T12:55:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 88108765612, '2026-07-09 12:55:00', 36, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 88108765612, 36 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001018, c.courseid, c.studentid, c.teacherid, '2026-07-09T13:31:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 82538152337, '2026-07-09 13:31:00', 23, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 82538152337, 23 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001019, c.courseid, c.studentid, c.teacherid, '2026-07-13T12:55:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 82968067883, '2026-07-13 12:55:00', 32, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 82968067883, 32 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001020, c.courseid, c.studentid, c.teacherid, '2026-07-13T13:27:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 85470418050, '2026-07-13 13:27:00', 30, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 85470418050, 30 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001021, c.courseid, c.studentid, c.teacherid, '2026-07-15T12:57:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 89623344557, '2026-07-15 12:57:00', 30, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 89623344557, 30 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001022, c.courseid, c.studentid, c.teacherid, '2026-07-15T13:27:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 85631304054, '2026-07-15 13:27:00', 28, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 85631304054, 28 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001023, c.courseid, c.studentid, c.teacherid, '2026-07-20T13:53:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 87535298944, '2026-07-20 13:53:00', 39, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 87535298944, 39 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001024, c.courseid, c.studentid, c.teacherid, '2026-07-20T14:32:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 84909379357, '2026-07-20 14:32:00', 26, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 84909379357, 26 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001025, c.courseid, c.studentid, c.teacherid, '2026-07-22T12:56:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 85818671460, '2026-07-22 12:56:00', 30, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 85818671460, 30 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001026, c.courseid, c.studentid, c.teacherid, '2026-07-22T13:26:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 89637333559, '2026-07-22 13:26:00', 29, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 89637333559, 29 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001027, c.courseid, c.studentid, c.teacherid, '2026-07-27T13:57:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 84017489621, '2026-07-27 13:57:00', 33, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 84017489621, 33 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001028, c.courseid, c.studentid, c.teacherid, '2026-07-27T14:30:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 87007872240, '2026-07-27 14:30:00', 28, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 87007872240, 28 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001029, c.courseid, c.studentid, c.teacherid, '2026-07-29T12:57:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 82199015348, '2026-07-29 12:57:00', 38, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 82199015348, 38 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001030, c.courseid, c.studentid, c.teacherid, '2026-07-29T13:34:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 82314932089, '2026-07-29 13:34:00', 26, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 82314932089, 26 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001031, c.courseid, c.studentid, c.teacherid, '2026-08-03T11:57:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 85843845132, '2026-08-03 11:57:00', 29, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 85843845132, 29 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
  UNION ALL SELECT 9900001032, c.courseid, c.studentid, c.teacherid, '2026-08-03T12:26:00+0200', 30, 'Curso de Italiano.', 'Zoom directo (sin cita en Acuity)', 83262408145, '2026-08-03 12:26:00', 32, 1, 0, 0, 1, 'ACREDITADA 07-ago-2026 con registro de Zoom (meeting 83262408145, 32 min).', NOW() FROM (SELECT (SELECT id FROM mdl_user WHERE email='d.pecondon@gdes.com') studentid, (SELECT courseid FROM mdl_i3code_acuityZoom WHERE id=106587) courseid, (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com') teacherid) c
) AS nuevas;


-- ─── BLOQUE 3 · Verificar ──────────────────────────────────────────────────
SELECT zoom_clasecompletada AS estado, acuity_canceled AS cancel, COUNT(*) AS clases
FROM mdl_i3code_acuityZoom
WHERE studentid = (SELECT id FROM mdl_user WHERE email = 'd.pecondon@gdes.com')
GROUP BY estado, cancel;
-- Esperado: 32 con estado 1, más las 2 antiguas de marzo.

SELECT id, acuity_datetime, zoom_meetingid, zoom_duration, zoom_clasecompletada
FROM mdl_i3code_acuityZoom
WHERE acuityid BETWEEN 9900001001 AND 9900001032
ORDER BY acuity_datetime;


-- ─── DESHACER ──────────────────────────────────────────────────────────────
-- DELETE FROM mdl_i3code_acuityZoom WHERE acuityid BETWEEN 9900001001 AND 9900001032;


-- ═══════════════════════════════════════════════════════════════════════════
-- LO DE FONDO — esto no lo arregla el INSERT
--
-- David lleva desde MARZO sin una sola cita en Acuity y ha recibido 32 clases.
-- Las clases se están dando **al margen del sistema**: la profesora abre una sala
-- y dan la clase. Ni se agenda, ni se registra, ni se puede justificar.
--
-- Mientras eso siga así habrá que repetir este ejercicio cada mes, y cualquier
-- expediente FUNDAE de estos alumnos está sin soporte documental.
--
-- Hablar con Paola Demarchi: las clases se reservan en Acuity y se dan en la sala
-- de la cita. Es el único modo de que consten.
--
-- Y comprobar si hay más alumnos igual:
--   SELECT CONCAT(u.firstname,' ',u.lastname) alumno, COUNT(*) citas,
--          MAX(z.acuity_datetime) ultima_cita,
--          SUM(z.zoom_clasecompletada = 1) acreditadas
--   FROM mdl_i3code_acuityZoom z JOIN mdl_user u ON u.id = z.studentid
--   WHERE z.teacherid = (SELECT id FROM mdl_user WHERE email='paola@live.tuspeaking.com')
--   GROUP BY alumno;
-- ═══════════════════════════════════════════════════════════════════════════
