# AC-7 — PROYECTO: corrección de entregas y portal de tutorización docente

**Repo:** `aula.tuspeaking.com.moodle3.5` · **Tipo:** proyecto (paraguas) · **Prioridad:** Alta
**Fecha de apertura:** 2026-08-07 · **Estado:** 🟡 en curso · **Cron del autocorrector: PAUSADO**

> Este ticket es el **paraguas** del tema "corrección de entregas". Los tickets de detalle ya
> existen y no se duplican aquí; se referencian. Lo que aporta este documento es el **estado
> global**, las **fases pendientes** y las **decisiones tomadas**.

| Ticket | Qué cubre | Estado |
|---|---|---|
| `2026-08-07-autocorrect-idioma-feedback.md` (AC-5) | Feedback en inglés en cursos de otros idiomas; prompts por idioma y tono humano | 🟢 desplegado |
| `2026-08-07-ac6-audios-finalizan-por-entrega.md` (AC-6) | Whisper inventaba palabras; audios sin nota, finalizan al entregar | 🟢 aplicado |
| `2026-07-27-quiz-grader-direccion-traduccion.md` (AC-2) | Quiz grader penalizaba traducciones EN→ES | 🟢 resuelto |
| `2026-08-07-comp3-hvp-nota-no-llega.md` (COMP-3) | H5P con resultado y sin nota | 🔴 causa raíz abierta |
| **Este ticket (AC-7)** | Auditoría histórica + portal docente | 🟡 |

---

## 0. Por qué existe este proyecto

El 07-ago, tirando del hilo de una incidencia de un alumno, salió a la luz que:

- El **audio en francés** se calificaba con una media de **4,19/10** frente a **6,98** en inglés,
  porque `faster-whisper-base` inventa palabras en francés. Alumnos penalizados por errores
  que cometió el modelo, no ellos.
- El **feedback iba en inglés** en cursos de francés, portugués, alemán, italiano y español,
  y **firmado con el nombre de un profesor**.
- Todo ello en **cursos bonificados por FUNDAE**, donde el seguimiento tutorial es
  precisamente lo que se audita.

Lo urgente ya está contenido (AC-5 y AC-6). Lo que queda es saber **cuánto de esto hay
detrás** y construir la solución buena: que corrijan las tutoras.

---

## 1. FASE 1 — Auditoría de las correcciones anteriores a 2026

**Pregunta a responder:** ¿qué nombre y qué marca de tiempo figuran en las correcciones
históricas, y hay lotes con la misma hora exacta?

**Por qué importa:** una corrección que dice estar hecha por una persona a una hora concreta
es, ante FUNDAE, evidencia de seguimiento tutorial. Si 200 correcciones comparten el mismo
segundo, no se sostiene como trabajo docente.

### 1.1 ¿Quién figura como corrector y cuánto ha corregido cada uno?

```sql
SELECT CONCAT(u.firstname,' ',u.lastname) AS figura_como,
       g.grader AS userid,
       COUNT(*)                          AS correcciones,
       COUNT(DISTINCT DATE(FROM_UNIXTIME(g.timemodified))) AS dias_distintos,
       MIN(FROM_UNIXTIME(g.timemodified)) AS desde,
       MAX(FROM_UNIXTIME(g.timemodified)) AS hasta
FROM mdl_assign_grades g
JOIN mdl_user u ON u.id = g.grader
WHERE g.timemodified < UNIX_TIMESTAMP('2026-01-01')
GROUP BY g.grader, figura_como
ORDER BY correcciones DESC;
```

### 1.2 Lotes: correcciones que comparten el MISMO segundo

```sql
SELECT FROM_UNIXTIME(g.timemodified) AS momento,
       CONCAT(u.firstname,' ',u.lastname) AS figura_como,
       COUNT(*)                    AS correcciones_en_ese_segundo,
       COUNT(DISTINCT a.course)    AS cursos_distintos,
       COUNT(DISTINCT g.userid)    AS alumnos_distintos
FROM mdl_assign_grades g
JOIN mdl_assign a ON a.id = g.assignment
JOIN mdl_user   u ON u.id = g.grader
WHERE g.timemodified < UNIX_TIMESTAMP('2026-01-01')
GROUP BY g.timemodified, figura_como
HAVING COUNT(*) > 1
ORDER BY correcciones_en_ese_segundo DESC
LIMIT 40;
```

### 1.3 Concentración por día

```sql
SELECT DATE(FROM_UNIXTIME(g.timemodified)) AS dia,
       COUNT(*)                            AS correcciones,
       COUNT(DISTINCT g.timemodified)      AS momentos_distintos,
       ROUND(COUNT(*) / COUNT(DISTINCT g.timemodified), 1) AS correcciones_por_momento
FROM mdl_assign_grades g
WHERE g.timemodified < UNIX_TIMESTAMP('2026-01-01')
GROUP BY dia
HAVING correcciones > 5
ORDER BY correcciones_por_momento DESC, correcciones DESC
LIMIT 40;
```

**Cómo leerlo:** `correcciones_por_momento` cercano a 1 = trabajo repartido, normal.
Valores altos = lote automático.

### 1.4 ¿Cuáles llevan comentario y en qué idioma?

```sql
SELECT DATE(FROM_UNIXTIME(g.timemodified)) AS dia,
       COUNT(*) AS correcciones,
       SUM(CASE WHEN f.commenttext IS NULL OR f.commenttext = '' THEN 1 ELSE 0 END) AS sin_comentario,
       SUM(CASE WHEN f.commenttext REGEXP
           '(the submission|you demonstrate|your writing|however,|while you|the student)'
           THEN 1 ELSE 0 END) AS comentario_en_ingles
FROM mdl_assign_grades g
LEFT JOIN mdl_assignfeedback_comments f ON f.grade = g.id
WHERE g.timemodified < UNIX_TIMESTAMP('2026-01-01')
GROUP BY dia
ORDER BY dia DESC
LIMIT 40;
```

**Entregable de la fase:** tabla resumen en este mismo ticket (§5) con el veredicto:
qué volumen es automático, con qué nombre figura y con qué distribución temporal.

---

## 2. FASE 2 — Foto global de 2026

Las mismas cuatro consultas cambiando `<` por `>=`:

```sql
WHERE g.timemodified >= UNIX_TIMESTAMP('2026-01-01')
```

Añadir el corte por idioma del curso, que es donde está el daño conocido:

```sql
SELECT
  CASE WHEN LOWER(c.fullname) REGEXP 'frances|francés|french|français' THEN 'FRANCES'
       WHEN LOWER(c.fullname) REGEXP 'portug'                          THEN 'PORTUGUES'
       WHEN LOWER(c.fullname) REGEXP 'aleman|alemán|german'            THEN 'ALEMAN'
       WHEN LOWER(c.fullname) REGEXP 'italian'                         THEN 'ITALIANO'
       WHEN LOWER(c.fullname) REGEXP 'castellano|español|espanol'      THEN 'ESPANOL'
       ELSE 'INGLES' END AS idioma,
  CASE WHEN LOWER(a.name) LIKE '%audio%' THEN 'AUDIO' ELSE 'TEXTO' END AS tipo,
  COUNT(*) AS correcciones,
  ROUND(AVG(g.grade / a.grade * 10), 2) AS media_sobre_10,
  COUNT(DISTINCT g.timemodified) AS momentos_distintos
FROM mdl_assign_grades g
JOIN mdl_assign a ON a.id = g.assignment
JOIN mdl_course c ON c.id = a.course
WHERE g.grader IN (14, 4414) AND a.grade > 0 AND g.grade > 0
  AND g.timemodified >= UNIX_TIMESTAMP('2026-01-01')
GROUP BY idioma, tipo
ORDER BY idioma, tipo;
```

**Ya medido el 07-ago** (referencia para comparar):

| | Audio | Texto |
|---|---|---|
| Francés | **4,19** (n=8) | 8,13 (n=80) |
| Inglés | 6,98 (n=95) | 7,09 (n=198) |
| Portugués | 8,33 (n=3) | 9,00 (n=6) |

---

## 3. FASE 3 — Portal de corrección y tutorización **(urgente)**

**Objetivo:** que las correcciones las apliquen las tutoras, revisando un borrador que el
sistema les propone. El sistema asiste; la persona decide, edita y publica. La firma y la
fecha son entonces reales.

### 3.1 Las dos vías

| | **A · Plugin en Moodle 3.5** | **B · Integrarlo en Teach** |
|---|---|---|
| Dónde vive | `local/` en el aula | plataforma nueva (`tuspeaking-platform`) |
| Ventaja | Al lado de los datos: sin sincronización, sin API, usa los roles que ya existen | Es el futuro; el aula 3.5 se apagará |
| Inconveniente | Moodle **3.5 (2018)**, sin soporte; se tira a la basura al migrar | Requiere API contra el aula; los datos siguen en Moodle |
| Plazo | Semanas | Meses |
| Riesgo | Bajo | Medio |

**Criterio para decidir (hay que responderlo antes de elegir):** ¿cuándo se apaga
`aula.tuspeaking.com`? Si es en menos de un año, invertir en un plugin de Moodle 3.5 es
tirar el trabajo; si no hay fecha, el aula seguirá corrigiendo entregas mucho tiempo y
compensa. **La decisión no es técnica: depende del calendario de migración.**

### 3.2 Requisitos, independientemente de la vía

1. **Cola de revisión** por tutora: entregas pendientes, con el borrador ya generado.
2. **Editar antes de publicar.** El borrador es una propuesta, nunca se publica solo.
3. **Publicación individual o en tandas pequeñas**, a ritmo de la tutora.
4. **Autoría y fecha reales**: quien pulsa publicar y cuándo lo pulsa.
5. **Nunca dos correcciones con la misma marca de tiempo** por generación automática.
6. **Audio: transcripción a la vista**, para que la tutora vea si es fiable antes de fiarse.
7. Trazabilidad: qué se propuso, qué se publicó y si se editó.

### 3.3 Qué hay ya construido y se reaprovecha

- `hansel_autocorrect.py`: generación de borradores por idioma y nivel (AC-5).
- `regrade_course.py`: recalificar con `--keep-grade` (conservar nota, reescribir comentario).
- `moodle_save_grade()`: publicación vía API de Moodle, que dispara finalización y registro.
- `test_feedback_style.py`: validar el tono sin tocar la base de datos.

Lo que falta es **la interfaz de revisión**, no el motor.

---

## 4. FASE 4 — Cron pausado (hecho el 07-ago)

Pausado para que no sobrescriba lo aplicado en AC-5/AC-6 mientras dura el análisis.

```bash
crontab -l > /tmp/crontab.bak-20260807
crontab -l | grep hansel_autocorrect     # comprobar que queda comentada
```

**Para reactivarlo hay que cumplir las tres:**

1. Fases 1 y 2 cerradas, con el alcance conocido.
2. Decidida la vía del portal (§3.1).
3. Verificado que el autocorrector ya no publica comentario fuera del inglés ni califica
   audios fuera del inglés — está en el código, pero hay que volver a comprobarlo tras
   cualquier despliegue.

Mientras esté pausado, **las entregas nuevas no se corrigen**. Es aceptable en agosto (poca
actividad), pero **no puede olvidarse en septiembre**.

---

## 5. Resultados de la auditoría

> *(Rellenar al ejecutar las fases 1 y 2. Pegar aquí las tablas, no en el chat: este ticket
> es la fuente.)*

| | Pre-2026 | 2026 |
|---|---|---|
| Correcciones automáticas | | |
| Nombre con el que figuran | | |
| Días distintos | | |
| Mayor lote en un mismo segundo | | |
| Con comentario en inglés | | |

---

## 6. Qué NO se va a hacer

**No se reescriben marcas de tiempo ni se reparten fechas hacia atrás** para aparentar que
una corrección se hizo en otro momento, ni se atribuyen correcciones a una tutora que no las
ha revisado.

El motivo es práctico: son cursos bonificados y el seguimiento tutorial es lo que FUNDAE
audita. Con requerimientos ya abiertos (SEPE Navarra, alegaciones de IDI), un cruce entre las
marcas del gradebook y los logs del servidor que muestre correcciones generadas en la misma
sesión con fechas repartidas por el calendario no se defiende, y el riesgo no sería un
requerimiento más sino los expedientes completos.

**La vía buena es la Fase 3**: que las tutoras revisen y publiquen de verdad, a su ritmo.
Eso produce fechas reales, autoría real y, de paso, mejor feedback.

---

## 7. Orden de trabajo

1. ✅ Pausar el cron.
2. Fase 1 — auditoría pre-2026 (~1 h, son cuatro consultas).
3. Fase 2 — foto 2026 (~30 min).
4. **Responder a la pregunta de calendario**: ¿cuándo se apaga el aula 3.5?
5. Fase 3 — decidir vía y diseñar el portal.
6. Recalificar las redacciones de francés con `--keep-grade` (AC-5 §7.5).
7. Reactivar el cron con las tres condiciones cumplidas.
