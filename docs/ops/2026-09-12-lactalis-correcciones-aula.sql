-- ═══════════════════════════════════════════════════════════════════════════
-- Lactalis — correcciones en el aula durante el cierre 2026.1 y el alta de I+D+i
-- ═══════════════════════════════════════════════════════════════════════════
--
-- ⚠️ ESTADO: **YA APLICADO en producción el 12-sep-2026.** Este fichero se
--    conserva como registro auditable de lo que se ejecutó y por qué. NO
--    volver a lanzarlo entero: los bloques 2, 3 y 4 son idempotentes, pero el
--    1 y el 5 NO (reescribirían datos ya correctos o duplicarían filas).
--
-- CONTEXTO
--   Cierre de la edición 2026.1 de Lactalis (categoría 524, 6 participantes)
--   solicitado por Aurelia López el 04-ago-2026, y alta en Success de la
--   edición del equipo I+D+i (categoría 552, 6 participantes, en curso hasta
--   el 18-dic-2026).
--
-- COPIAS PREVIAS (las deja `aula-sql --write`)
--   mdl_i3code_acuityZoom          → mdl_i3code_acuityZoom_bak_20260912_0757
--   own_empresa_ediciones          → own_empresa_ediciones_bak_20260912_0859
--   own_acuity_course              → own_acuity_course_bak_20260912_0926
--
-- PIEZAS
--   1. Acreditar 5 clases de Ceferino Rivadeneira (reunión multi-slot)
--   2. Recalcular su fila del panel
--   3. `isfundae` de los 6 cursos que estaban marcados como no bonificables
--   4. Rol 11 (supervisorrrhh) sobre las categorías 524 y 552
--   5. Edición de la categoría 552 en `own_empresa_ediciones`
--
-- DESPUÉS DE CADA BLOQUE: `aula-php admin/cli/purge_caches.php`
-- ═══════════════════════════════════════════════════════════════════════════


-- ───────────────────────────────────────────────────────────────────────────
-- 1 · CEFERINO RIVADENEIRA — acreditar 5 clases impartidas
-- ───────────────────────────────────────────────────────────────────────────
-- SITUACIÓN
--   Ceferino (studentid 2837, curso 3172) cerraba con 39 de 45 clases (86,7%)
--   en un expediente FUNDAE. Seis filas quedaban en estado 3.
--
-- CAUSA
--   Reserva siempre DOS slots contiguos de 20 min. La clase se imparte en UNA
--   sola reunión de Zoom de 36-42 min; Zoom la asigna al primer slot y el
--   segundo se queda con un Meeting ID fantasma que nunca arrancó. Es el caso
--   §2 de RUNBOOK_asistencias_moodle_tuspeaking.md (reuniones multi-slot).
--   De febrero al 5-may los dos slots del par salían acreditados; del 14-may
--   en adelante, no. Fuera de la ventana de 30 días de la ingesta, no se
--   arregla solo.
--
-- EVIDENCIA — Zoom API, /report/meetings/<id>/participants
--   Los 8 Meeting ID de los slots huérfanos devuelven `3001 Meeting does not
--   exist`: esas salas nunca se abrieron. Las reuniones MADRE del mismo día sí
--   tienen registro, y su duración cubre el slot contiguo:
--
--     14-may  10:44 → 11:25  (42 min)  cubre el slot de 11:10   → id 107984 ✔
--     18-may  09:40 → 10:16  (36 min)  cubre el slot de 10:00   → id 107933 ✔
--     19-may  07:59 → 08:39  (40 min)  cubre el slot de 08:20   → id  70726 ✔
--     22-may  11:09 → 11:47  (38 min)  cubre el slot de 11:30   → id 107986 ✔
--     16-jun  08:30 → 09:08  (39 min)  cubre el slot de 08:50   → id  70724 ✔
--
--   DESCARTADO — 05-jun (id 70722): la reunión fue 08:30 → 09:06 y el slot
--   empezaba a las 09:10. El alumno salió 4 minutos ANTES de que el slot
--   arrancara (participantes: Cefe 06:30:56 → 07:06:54 UTC). No llega, y
--   acreditarlo sería inventar una clase en un expediente de teleformación.
--   Las 2 del 12-may están canceladas (`acuity_canceled = 1`): no se tocan.
--
-- ⚠️ DESVIACIÓN CONSCIENTE del §4.A del runbook
--   El runbook manda copiar `zoom_duration` de la reunión madre. Eso metería
--   42 min en los DOS slots y duplicaría horas declaradas. Se pone **20**, que
--   es lo atribuible al slot; la evidencia completa va en `manual_motivo`.

-- aula-sql --write mdl_i3code_acuityZoom
UPDATE mdl_i3code_acuityZoom tgt
JOIN mdl_i3code_acuityZoom src ON src.id = CASE tgt.id
    WHEN 107984 THEN 107983 WHEN 107933 THEN 107932 WHEN 70726 THEN 70727
    WHEN 107986 THEN 107985 WHEN 70724 THEN 70725 END
SET tgt.zoom_starttime = src.zoom_starttime, tgt.zoom_endtime = src.zoom_endtime,
    tgt.zoom_meetingid = src.zoom_meetingid, tgt.zoom_topic = src.zoom_topic,
    tgt.zoom_username = src.zoom_username, tgt.zoom_email = src.zoom_email,
    tgt.zoom_participants = src.zoom_participants,
    tgt.zoom_duration = 20,
    tgt.zoom_clasecompletada = 1, tgt.manual_override = 1, tgt.manual_user = 14,
    tgt.manual_fecha = NOW(),
    tgt.manual_motivo = CONCAT('Clase impartida en la reunion del slot contiguo. Meeting ID ',
        src.zoom_meetingid,' ',src.zoom_starttime,' - ',src.zoom_endtime,' (',src.zoom_duration,' min)')
WHERE tgt.id IN (107984,107933,70726,107986,70724) AND tgt.studentid = 2837;
-- Resultado: 5 rows affected. Ceferino pasa de 39/45 (86,7%) a 44/45 (97,8%).


-- ───────────────────────────────────────────────────────────────────────────
-- 2 · RECALCULAR EL PANEL de Ceferino
-- ───────────────────────────────────────────────────────────────────────────
-- `mdl_i3code_acuityZoom_informe` no se actualiza al corregir la fuente.
--
-- ⚠️ El §4.B del runbook usa `zoom_clasecompletada = 0` para las no asistidas.
--    ES FALSO: 2 = ausencia, 0 = "sin datos" (verificado el 07-ago-2026 leyendo
--    misclases.php). La consulta de abajo va corregida.
-- ⚠️ Acotado a courseid 3172 a propósito: el histórico de este alumno tiene
--    `clases_pendientes` negativo en cursos antiguos y no se toca.

-- aula-sql --write mdl_i3code_acuityZoom_informe
UPDATE mdl_i3code_acuityZoom_informe inf
JOIN (SELECT courseid,
             SUM(zoom_clasecompletada = 1) comp,
             SUM(zoom_clasecompletada = 2 AND acuity_canceled = 0) noasist
      FROM mdl_i3code_acuityZoom WHERE studentid = 2837 GROUP BY courseid) s
  ON s.courseid = inf.courseid
SET inf.clases_completadas = s.comp,
    inf.clases_no_asistidas = s.noasist,
    inf.clases_pendientes   = inf.clases_total - (s.comp + s.noasist),
    inf.porcentaje_clases   = ROUND(s.comp / NULLIF(inf.clases_total,0) * 100, 2),
    inf.porcentaje_total    = ROUND(s.comp / NULLIF(inf.clases_total,0) * 100, 2)
WHERE inf.userid = 2837 AND inf.courseid = 3172;


-- ───────────────────────────────────────────────────────────────────────────
-- 3 · `isfundae` — 6 cursos declarados a FUNDAE que constaban NO bonificables
-- ───────────────────────────────────────────────────────────────────────────
-- SITUACIÓN
--   Los 5 cursos de la edición 2026.1 (categoría 524) y el de Luc Sourdril
--   (4055, categoría 552) tenían `own_acuity_course.isfundae = 'f'`, pese a
--   tener código de acción formativa comunicado a FUNDAE y estar en factura:
--     524 → 001-01407 (B1) · 001-01408 (B2) · 001-01409 (B2/C1) · 001-01410 (A2)
--           factura F2026/126, 26-01-2026, 2.186,25 €
--     4055 → 001-01559/00001, código facilitado por Aurelia el 02-jul-2026
--
-- EFECTOS QUE TENÍA
--   · Sin distintivo FUNDAE en Success.
--   · Sin alertas de cumplimiento (apto / en riesgo / no apto).
--   · **Sin certificado de superación**: el generador solo lo emite si el
--     alumno es bonificable. Es lo que en Tekia hubo que forzar a mano.
--
-- Confirmado por Hansel el 12-09-2026: las dos ediciones van bonificadas.

-- aula-sql --write own_acuity_course
UPDATE own_acuity_course SET isfundae = 't' WHERE courseid = 4055;
UPDATE own_acuity_course SET isfundae = 't' WHERE courseid IN (3172,3173,3174,3175,3176);


-- ───────────────────────────────────────────────────────────────────────────
-- 4 · ROL 11 (supervisorrrhh) sobre las categorías 524 y 552
-- ───────────────────────────────────────────────────────────────────────────
-- El acceso de RRHH a Success sale de `mdl_role_assignments` con roleid = 11
-- sobre contexto de categoría (contextlevel 40) — `moodle-service.ts:400`.
-- La categoría 552 no tenía ninguna asignación: nadie la veía, ni el admin.
-- La 524 solo tenía a Hansel (14) y Rosa Bergillos (5718).
--
-- Se creó además el usuario de Moodle de **Aurelia López Gallardo**
-- (aurelia.lopez@es.lactalis.com, auth manual, cambio de contraseña forzado)
-- desde la web del aula — un usuario NO se inserta por SQL.
--
-- Resultado: las tres personas de RRHH de Lactalis (Rosa 5718, Ana Alejandra
-- 2460, Aurelia) más Hansel (14), en las DOS categorías.

-- aula-sql --write mdl_role_assignments
INSERT INTO mdl_role_assignments (roleid, contextid, userid, timemodified, modifierid, component, itemid, sortorder)
SELECT 11, ctx.id, u.id, UNIX_TIMESTAMP(), 14, '', 0, 0
FROM mdl_context ctx JOIN mdl_user u ON u.id IN (14, 5718, 2460)
WHERE ctx.contextlevel = 40 AND ctx.instanceid = 552;

INSERT INTO mdl_role_assignments (roleid, contextid, userid, timemodified, modifierid, component, itemid, sortorder)
SELECT 11, ctx.id, u.id, UNIX_TIMESTAMP(), 14, '', 0, 0
FROM mdl_context ctx JOIN mdl_user u ON u.email = 'Aurelia.Lopez@es.lactalis.com' AND u.deleted = 0
WHERE ctx.contextlevel = 40 AND ctx.instanceid IN (524, 552);

INSERT INTO mdl_role_assignments (roleid, contextid, userid, timemodified, modifierid, component, itemid, sortorder)
SELECT 11, ctx.id, 2460, UNIX_TIMESTAMP(), 14, '', 0, 0
FROM mdl_context ctx WHERE ctx.contextlevel = 40 AND ctx.instanceid = 524;

-- ⚠️ Quien tenga sesión abierta en Success NO verá la categoría nueva hasta
--    salir y volver a entrar: la lista de categorías viaja dentro del JWT.
--    Costó un rato de diagnóstico creyendo que era caché o túnel.


-- ───────────────────────────────────────────────────────────────────────────
-- 5 · EDICIÓN de la categoría 552 en `own_empresa_ediciones`
-- ───────────────────────────────────────────────────────────────────────────
-- De aquí salen las fechas y la columna "Finalizado" de la ficha de Success
-- (`moodle-service.ts` hace LEFT JOIN sobre esta tabla). La 552 no tenía fila;
-- sin ella la fecha caía a la matrícula, y ninguno de esos cursos tiene
-- `enddate`. empresa_id 14 = Lactalis en `own_empresas`.

-- aula-sql --write own_empresa_ediciones
INSERT INTO own_empresa_ediciones
  (empresa_id, categoria_id, fecha_inicio, fecha_fin, objetivo_cobertura, objetivo_tasa_respuesta, activo)
VALUES (14, 552, '2026-05-19', '2026-12-18', 80, 15, 1);
-- Resultado: id 58.


-- ═══════════════════════════════════════════════════════════════════════════
-- VERIFICACIÓN (solo lectura)
-- ═══════════════════════════════════════════════════════════════════════════
SELECT id, LEFT(acuity_datetime,16) fecha, zoom_clasecompletada estado, zoom_duration, manual_override
FROM mdl_i3code_acuityZoom WHERE id IN (107984,107933,70726,107986,70724,70722);
-- esperado: cinco en estado 1 con manual_override=1; la 70722 sigue en 3

SELECT courseid, classnmbr, tipo_clase, isfundae FROM own_acuity_course
WHERE courseid IN (3172,3173,3174,3175,3176,4023,4024,4055);
-- esperado: los ocho con isfundae='t'

SELECT ctx.instanceid categoria, u.email
FROM mdl_role_assignments ra
JOIN mdl_context ctx ON ctx.id = ra.contextid
JOIN mdl_user u ON u.id = ra.userid
WHERE ra.roleid = 11 AND ctx.contextlevel = 40 AND ctx.instanceid IN (524,552)
ORDER BY ctx.instanceid, u.email;
-- esperado: 8 filas (4 personas × 2 categorías)

SELECT * FROM own_empresa_ediciones WHERE categoria_id IN (524,552);
-- esperado: 2 filas (id 24 → 524, id 58 → 552)
