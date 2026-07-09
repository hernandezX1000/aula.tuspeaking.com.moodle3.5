# Reproceso de clases atascadas en "Verificando asistencia" · 2026-07-09

## Síntoma

Alumnos con clases que **sí dieron** pero que figuran como pendientes
("Verificando asistencia", `zoom_clasecompletada=3`) en el panel. Las "pendientes"
salían **infladas** (caso detonante: e2y Commerce, edición abril–junio 2026.2 —
Francisco Sánchez figuraba 20/24 cuando en realidad hizo 25).

## Causa

Dos cosas se juntan:
1. **Ventana del sync por fecha de reserva, no de clase.** El sync selecciona de
   `own_acuity WHERE lastmodified >= hace 60 días`. Clases reservadas hace meses
   pero **dadas hace poco** quedan fuera de la ventana y no se reprocesan.
2. **El sync solo re-baja participantes de un puñado de reuniones por corrida**
   ("Total meetings para procesar participantes: 4" en el log). No re-verifica las
   reuniones antiguas aunque las metas en la ventana → la clase se queda en estado 3.

Resultado: la asistencia **existe en Zoom** pero nunca se importó a la BBDD.
(Agravado por el bug de charset, ya resuelto — ver TICKET #6.)

## Diagnóstico y reproceso aplicado (2026-07-09)

1. Contar atascadas (estado 3, pasadas, no canceladas, últimos 90 días): **252**.
2. Reverificar cada una contra la Zoom API (`docs/ops/reverify_stuck_classes.py`):
   - **48** con conexión real del alumno → **acreditadas** (estado=1).
   - **104** no-show reales (alumno no se conectó) → sin cambios.
   - **79** con **404** en Zoom (reunión inexistente: no se celebró / purgada) → irrecuperables.
3. Aplicar (ver `apply_credit.sql` abajo): estado=1 en las 48 + **proteger de la
   reversión nocturna** (poner su `own_acuity.lastmodified` a una fecha antigua para
   que el sync no las vuelva a tocar) + recalcular el panel de los alumnos afectados.

### apply_credit.sql (plantilla)
```sql
-- IDS = lista de /tmp/credit_ids.txt
UPDATE mdl_i3code_acuityZoom SET zoom_clasecompletada=1 WHERE id IN (:IDS);
UPDATE own_acuity oa JOIN mdl_i3code_acuityZoom az ON az.acuityid=oa.acuityid
  SET oa.lastmodified='2024-01-01 00:00:00' WHERE az.id IN (:IDS);
UPDATE mdl_i3code_acuityZoom_informe inf
JOIN (SELECT studentid, courseid, SUM(zoom_clasecompletada=1) comp,
        SUM(zoom_clasecompletada=0 AND zoom_meetingid IS NOT NULL) noasist
      FROM mdl_i3code_acuityZoom
      WHERE studentid IN (SELECT DISTINCT studentid FROM mdl_i3code_acuityZoom WHERE id IN (:IDS))
      GROUP BY studentid, courseid) s
  ON s.courseid=inf.courseid AND s.studentid=inf.userid
SET inf.clases_completadas=s.comp, inf.clases_no_asistidas=s.noasist,
    inf.clases_pendientes=inf.clases_total-(s.comp+s.noasist),
    inf.porcentaje_clases=ROUND(s.comp/NULLIF(inf.clases_total,0)*100,2),
    inf.porcentaje_total=ROUND(s.comp/NULLIF(inf.clases_total,0)*100,2)
WHERE inf.userid IN (SELECT DISTINCT studentid FROM mdl_i3code_acuityZoom WHERE id IN (:IDS));
```

## Cómo evitarlo (prevención)

En orden de robustez:

1. **Programar la reverificación (rápido, recomendado).** Añadir un cron que corra
   `reverify_stuck_classes.py` con `APPLY=1` + el `apply_credit.sql` cada noche
   **después** del sync (p. ej. 05:30). Auto-sana lo que el sync deje atascado.
2. **Corregir la ventana del sync.** Que la selección incluya también las clases
   **pasadas en estado 3** (por `acuity_datetime`), no solo por `own_acuity.lastmodified`.
   Y que el paso de participantes re-baje TODAS las reuniones pasadas sin participantes,
   no solo un puñado. (Cambio en `i3code_download_zoomdata.php` — TICKET #10.)
3. **Monitor.** Ampliar `check_zoom_sync.sh` para alertar si el nº de clases en estado 3
   con fecha pasada supera un umbral (detecta el problema antes de que un cliente lo note).
4. **Charset utf8mb4** (ya hecho, TICKET #6): evita las escrituras fallidas que dejaban
   datos a medias.

## Notas

- Las reuniones **404** en Zoom son clases que no se celebraron (nadie entró a la sala,
  Zoom no crea "past meeting") o purgadas. No reverificables → criterio manual/negocio.
- Criterio "asistió": participante no-@tuspeaking con conexión total > 5 min.
