# TICKET COMP-3 — H5P registra el resultado pero la nota no llega al libro de calificaciones

**Repo:** `aula.tuspeaking.com.moodle3.5` · **Prioridad:** Alta · **Estado:** 🔴 causa sin determinar
**Fecha:** 2026-08-07 · **Origen:** incidencia de Luis Miguel Alonso Porro (GDES)

---

## 1. Qué pasa

Un alumno completa un ejercicio H5P, **H5P guarda su resultado**, pero la nota **no se
escribe en el libro de calificaciones**. Como el criterio de finalización de esos
ejercicios es "obtener una nota" (`completion = 2`), la actividad **nunca se marca como
completada** por mucho que el alumno la repita.

Desde fuera parece que "la plataforma no registra lo que hago". Desde dentro:

```
mdl_hvp_xapi_results   → 275094: 5/5 · 275095: 3/4      ✅ el resultado SÍ está
mdl_grade_grades       → finalgrade NULL en ambos        ❌ la nota NO se escribió
mdl_course_modules_completion → completionstate 0        ❌ consecuencia de lo anterior
```

**Impacto directo en FUNDAE:** el alumno no alcanza el 75% de progreso exigido para
bonificar aunque haya hecho el trabajo.

---

## 2. El diagnóstico anterior era INCORRECTO

Este ticket decía que la causa eran los `grade_items` duplicados: que completion leía el
`313xxx` vacío en lugar del `320xxx` con la nota. **Eso es falso.** Comprobado el 07-ago:

- Los duplicados tienen `itemnumber = NULL` y **0 notas**; el ítem activo tiene
  `itemnumber = 0`, que es exactamente el que busca completion (`completiongradeitemnumber = 0`).
- **Prueba definitiva:** cuatro ejercicios de Luis Miguel **con el duplicado presente**
  (cmids 530484, 530485, 530424, 530425) están **completados correctamente**.

⚠️ **Se estuvo a punto de borrar 34 `grade_items` en producción, en 10 cursos, siguiendo
ese diagnóstico erróneo.** No habría arreglado nada.

---

## 3. Lo que sí se observa

| instancia | ítem activo (`itemnumber=0`) | serie | nota escrita | ¿completa? |
|---|---|---|---|---|
| 275092 | 313401 | **313xxx** | 10,00 — 11-may | ✅ |
| 275093 | 313402 | **313xxx** | 10,00 — 11-may | ✅ |
| 275104 | 313372 | **313xxx** | 10,00 — 6-may | ✅ |
| 275105 | 313373 | **313xxx** | 0,00 — 7-may | ✅ |
| **275094** | **320304** | **320xxx** | **NULL** | ❌ |
| **275095** | **320305** | **320xxx** | **NULL** | ❌ |

**Correlación:** funciona cuando el ítem activo es de la serie `313xxx` (todas las notas
escritas en **mayo**) y falla cuando es de la serie `320xxx` (creados **después**).

**Lo que NO sabemos:** por qué. Hipótesis a comprobar:

1. Los `320xxx` se crearon en alguna intervención de julio/agosto y el módulo H5P quedó
   apuntando al ítem antiguo al llamar a `grade_update()`.
2. `hvp_update_grades()` resuelve mal el ítem cuando existen dos para la misma instancia,
   aunque uno tenga `itemnumber` NULL.
3. Algo cambió entre mayo y julio en el flujo de calificación (¿el cutover del 26-jul?).

**Dato en contra de la hipótesis 1:** `320304` tiene 1 nota de **otro alumno**, así que
el ítem sí recibe escrituras. Habría que ver **cuándo** se escribió esa nota.

---

## 4. Arreglo aplicado (paliativo, 07-ago-2026)

Se escribió a mano la nota que corresponde al resultado real ya registrado por H5P, y se
marcó la finalización:

```sql
-- Notas (aula-sql --write mdl_grade_grades)
UPDATE mdl_grade_grades SET rawgrade=10.00, finalgrade=10.00, timemodified=UNIX_TIMESTAMP()
WHERE userid=5823 AND itemid=320304;   -- Exercice 1: 5/5
UPDATE mdl_grade_grades SET rawgrade=7.50,  finalgrade=7.50,  timemodified=UNIX_TIMESTAMP()
WHERE userid=5823 AND itemid=320305;   -- Exercice 2: 3/4

-- Finalización (aula-sql --write mdl_course_modules_completion)
INSERT INTO mdl_course_modules_completion (coursemoduleid, userid, completionstate, viewed, timemodified)
VALUES (530434, 5823, 1, 1, UNIX_TIMESTAMP()), (530435, 5823, 1, 1, UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE completionstate=1, timemodified=UNIX_TIMESTAMP();
```

Después: `aula-php admin/cli/purge_caches.php`. **Verificado en pantalla**: los dos
ejercicios de "4. Loisirs" aparecen en verde. Progreso 55/80 → 57/80.

⚠️ **Es un parche de datos, no un arreglo.** Si alguien recalcula el libro de
calificaciones del curso, Moodle podría recomputar desde H5P y volver a dejarlas vacías.

⚠️ **Escribir el completion a mano solo es aceptable como paliativo puntual.** No
convertirlo en costumbre: enmascara el fallo real.

---

## 5. Qué falta por investigar

1. **¿Cuándo se escribió la nota de "el otro alumno" en `320304`?** Si es de mayo, los
   `320xxx` dejaron de funcionar en algún momento posterior — y hay que buscar qué cambió.
2. **¿Cuántos alumnos están afectados hoy?** Barrido: alumnos con resultado en
   `mdl_hvp_xapi_results` y `finalgrade` NULL en el ítem correspondiente.
   **Este es el dato que dice si es un caso o una epidemia.**
3. **¿Se limita al curso 3242 o afecta a los 10 cursos con duplicados?**
4. Revisar `mod/hvp` y su `hvp_update_grades()` / `grade_update()`.

### Consulta para el punto 2 (barrido de afectados)

```sql
SELECT x.user_id, u.firstname, u.lastname, cm.course, x.content_id,
       x.raw_score, x.max_score, gi.id AS grade_item, gg.finalgrade
FROM mdl_hvp_xapi_results x
JOIN mdl_user u          ON u.id = x.user_id
JOIN mdl_course_modules cm ON cm.instance = x.content_id
JOIN mdl_modules m       ON m.id = cm.module AND m.name = 'hvp'
JOIN mdl_grade_items gi  ON gi.iteminstance = x.content_id AND gi.itemmodule='hvp' AND gi.itemnumber = 0
LEFT JOIN mdl_grade_grades gg ON gg.itemid = gi.id AND gg.userid = x.user_id
WHERE gg.finalgrade IS NULL AND x.raw_score IS NOT NULL
ORDER BY cm.course, x.user_id;
```

---

## 6. Historial de la incidencia (para no repetir el patrón)

| Fecha | Qué pasó |
|---|---|
| 30-jul | Luis Miguel avisa: completa ejercicios y no se registran |
| 31-jul | Insiste con capturas. Entra 10 veces ese día |
| ~1-ago | Se corrige COMP-2 (42 Páginas sin `completionview`) — **eso sí era real y sí se arregló** |
| 4-ago | Se le responde que **está resuelto** y que basta con reabrir y pulsar "Comprobar" |
| 6-ago | Vuelve a escribir: sigue igual. Entra 5 veces |
| 7-ago | Diagnóstico correcto y arreglo paliativo |

**Lección:** el 4-ago se dio por resuelto sin verificar el estado real del alumno en la
base de datos. **Antes de decirle a un cliente que algo está arreglado, comprobar su caso
concreto**, no el fix genérico.

---

## 7. Detalle que costó tiempo

El alumno llama "tema 4" a la sección titulada **"4. Loisirs"**, que en la base de datos
es `section = 5` (el título va desfasado respecto al número interno). Buscar por el número
de sección llevó a mirar los ejercicios equivocados y a concluir, erróneamente, que "los
del tema 4 ya estaban bien".

**Al diagnosticar, pedir la captura o el enlace**: el `id` de la URL
(`/mod/hvp/view.php?id=530434`) es el `cmid` exacto y no admite ambigüedad.
