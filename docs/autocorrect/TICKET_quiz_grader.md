# TICKET — Automatización de calificación de quizzes con preguntas essay

**Sistema:** Sistema de Corrección Automática de Entregables compatible con FUNDAE  
**Componente nuevo:** `hansel_quiz_grader.py`  
**Prioridad:** Alta  
**Estado:** Pendiente de desarrollo  
**Fecha análisis:** 27/06/2026  

---

## Problema

Los cursos de tuSpeaking contienen quizzes de Moodle con preguntas de tipo **essay** (respuesta libre). Una vez que el alumno completa el quiz, Moodle pone la pregunta en estado `needsgrading` y bloquea la nota final hasta que un tutor la califique manualmente. Esto genera un cuello de botella administrativo idéntico al que existía con los writings y audios (módulo `assign`), ya resuelto por `hansel_autocorrect.py`.

### Tipos de quizzes afectados

Hay **dos patrones** claramente identificados en el diagnóstico de BD:

| Patrón | Ejemplo | N.º preguntas essay | Descripción |
|---|---|---|---|
| **Translations** | "Activity 4: Coming of age - Translations" | 5–10 | El alumno traduce frases del español al inglés |
| **Writing/description** | "Interior design", "Daily routine" | 1–3 | El alumno escribe una descripción o texto libre |

---

## Datos del diagnóstico (27/06/2026)

### Backlog pendiente (quiz attempts en `needsgrading`)

| Empresa | Alumno | Actividades pendientes | Última entrega |
|---|---|---|---|
| GKN | Javier Lopez Nogales | 1 quiz (5 preguntas) | 22/02/2026 |
| Alua Hotels | Manuel Acevedo | 7 quizzes (~31 preguntas) | 23/03/2026 |
| Nettrim | Maria Florencia Perez | 8 quizzes (~39 preguntas) | 18/04/2026 |
| CENIEH | María Concepción Moreno-Torres | 1 quiz (5 preguntas) | 09/05/2026 |
| Lin3s | Amaia Moracho | 8 quizzes (~38 preguntas) | 04/06/2026 |
| E2Y | Andrea Landa | 3 quizzes (~11 preguntas) | 10/06/2026 |
| Lactalis | Ceferino Rivadeneira | 1 quiz (1 pregunta) | 16/06/2026 |
| Velcro | Berta Rodriguez | 4 quizzes (~25 preguntas) | 22/06/2026 |
| GR (curso genérico) | Joan Gomez | 1 quiz (10 preguntas) | 23/06/2026 |

**Total estimado: ~39 quiz attempts · ~160 respuestas essay individuales pendientes**

### Estructura total de quizzes con essay (activos 2026)

- **~130 quiz instances** con preguntas essay en cursos activos
- Empresas: Velcro, Lin3s, E2Y, Nettrim, Alua Hotels, GKN, CENIEH, Lactalis, Tekia, GDES, Hyatt, Capitole, Equivalenza, Iberassekuranz, Senator, Torres y Carrera
- El problema escala: cada alumno que complete un quiz genera entre 1 y 10 respuestas essay que alguien tiene que calificar

---

## Diseño técnico

### Tablas de Moodle involucradas

```
mdl_quiz                        → quiz instance (nombre, curso, configuración)
mdl_quiz_attempts               → intento del alumno (state: 'finished')
mdl_question_attempts           → intento por pregunta (questionusageid = quiz_attempt.uniqueid)
mdl_question                    → la pregunta (qtype = 'essay', questiontext = enunciado)
mdl_question_attempt_steps      → estados del intento de pregunta
                                   state: 'needsgrading' = pendiente manual
                                   state: 'manuallygraded' = calificado
mdl_question_attempt_step_data  → datos del intento (respuesta del alumno, mark, comment)
mdl_quiz_grades                 → nota final del quiz por alumno
```

### Cómo leer la respuesta del alumno

```sql
SELECT
    qa.id AS quiz_attempt_id,
    qat.id AS question_attempt_id,
    q.questiontext AS enunciado,
    q.qtype,
    qas.id AS last_step_id,
    qas.state AS estado,
    qasd.value AS respuesta_alumno
FROM mdl_quiz_attempts qa
JOIN mdl_question_attempts qat ON qat.questionusageid = qa.uniqueid
JOIN mdl_question q ON q.id = qat.questionid AND q.qtype = 'essay'
JOIN mdl_question_attempt_steps qas ON qas.questionattemptid = qat.id
    AND qas.sequencenumber = (
        SELECT MAX(s2.sequencenumber)
        FROM mdl_question_attempt_steps s2
        WHERE s2.questionattemptid = qat.id
    )
JOIN mdl_question_attempt_step_data qasd ON qasd.attemptstepid = qas.id
    AND qasd.name = 'answer'
WHERE qa.state = 'finished'
  AND qas.state = 'needsgrading'
```

### Cómo calificar (escritura directa a BD — sin REST API disponible)

Moodle no tiene un endpoint REST API para calificar preguntas essay de quiz. La calificación se hace insertando un nuevo step en `mdl_question_attempt_steps`.

**⚠ Verificado en BD real (27/06/2026):** El estado a insertar NO es `manuallygraded` sino uno de estos tres según la fracción:

| Fracción | Estado a insertar |
|---|---|
| 1.0 (correcto) | `mangrright` |
| 0.0 < f < 1.0 (parcial) | `mangrpartial` |
| 0.0 (incorrecto) | `mangrwrong` |

Campos de `mdl_question_attempt_step_data` confirmados: `-comment`, `-commentformat`, `-mark`, `-maxmark`.

```sql
-- 1. Insertar step de calificación manual
-- @state = 'mangrright' | 'mangrpartial' | 'mangrwrong' según fracción
INSERT INTO mdl_question_attempt_steps
    (questionattemptid, sequencenumber, state, fraction, timecreated, userid)
VALUES
    (@qat_id,
     (SELECT MAX(sequencenumber)+1 FROM mdl_question_attempt_steps WHERE questionattemptid=@qat_id),
     @state,      -- 'mangrright' / 'mangrpartial' / 'mangrwrong'
     @fraction,   -- 0.0 a 1.0 (fracción de la nota máxima)
     UNIX_TIMESTAMP(),
     14);         -- grader = Hansel uid 14

-- 2. Insertar datos del step (comentario y nota)
INSERT INTO mdl_question_attempt_step_data (attemptstepid, name, value)
VALUES
    (@new_step_id, '-comment', @feedback),
    (@new_step_id, '-commentformat', '1'),
    (@new_step_id, '-mark', @mark),      -- nota numérica (ej. 0.8 de maxmark 1.0)
    (@new_step_id, '-maxmark', @maxmark);

-- 3. Actualizar nota del intento de pregunta
UPDATE mdl_question_attempts
SET maxmark = @maxmark
WHERE id = @qat_id;

-- 4. Recalcular nota total del quiz attempt
UPDATE mdl_quiz_attempts
SET sumgrades = (
    SELECT SUM(qat2.maxmark * qas2.fraction)
    FROM mdl_question_attempts qat2
    JOIN mdl_question_attempt_steps qas2 ON qas2.questionattemptid = qat2.id
        AND qas2.sequencenumber = (
            SELECT MAX(s3.sequencenumber)
            FROM mdl_question_attempt_steps s3
            WHERE s3.questionattemptid = qat2.id
        )
    WHERE qat2.questionusageid = @usage_id
)
WHERE id = @quiz_attempt_id;

-- 5. Actualizar mdl_quiz_grades (nota final del quiz para el alumno)
INSERT INTO mdl_quiz_grades (quiz, userid, grade, timemodified)
VALUES (@quiz_id, @userid, @final_grade, UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE grade = @final_grade, timemodified = UNIX_TIMESTAMP();
```

### ⚠ Diferencia con hansel_autocorrect.py

`hansel_autocorrect.py` usa REST API (`mod_assign_save_grade`) → IP registrada en logstore.  
`hansel_quiz_grader.py` necesita SQL directo → **IP NO queda registrada en logstore**.

Esto es aceptable para los quizzes porque:
1. Son ejercicios de vocabulario/traducción dentro de la plataforma (no entregables FUNDAE críticos)
2. La nota final del curso para FUNDAE viene del `mdl_assign` (writings/audio), no de los quizzes
3. Si en el futuro Moodle añade un endpoint REST para esto, se migra igual que se migró `assign`

### Evaluación con Claude por tipo de pregunta

**Translations (5-10 preguntas por quiz):**
```
Sistema: Eres un profesor de inglés. El alumno ha traducido una frase del español al inglés.
Evalúa si la traducción es correcta y natural. Devuelve JSON:
{"fraction": 0.8, "feedback": "Good translation, but 'town hall' is more natural than 'council house'."}
fraction: 0.0 (incorrecta) / 0.5 (parcialmente correcta) / 1.0 (correcta)
```

**Writing/description (1-3 preguntas):**
Mismo sistema que writings del `assign`, adaptado a respuesta más corta.

---

## Arquitectura del script `hansel_quiz_grader.py`

```
hansel_quiz_grader.py
  │
  ├── fetch_pending_quiz_essays(conn)
  │     → SELECT de quiz attempts con needsgrading
  │     → Agrupa por quiz_attempt (un alumno puede tener 5 preguntas en un quiz)
  │
  ├── Para cada quiz_attempt:
  │     ├── fetch_question_text(conn, question_id) → enunciado
  │     ├── fetch_student_answer(conn, step_id)    → respuesta del alumno
  │     ├── detect_question_type()                 → 'translation' | 'writing'
  │     ├── call_claude_quiz(enunciado, respuesta, tipo, nivel)
  │     │     → fraction (0-1), feedback
  │     └── save_quiz_grade(conn, qat_id, fraction, feedback, maxmark)
  │           → INSERT en mdl_question_attempt_steps
  │           → INSERT en mdl_question_attempt_step_data
  │           → UPDATE mdl_quiz_attempts.sumgrades
  │           → UPDATE mdl_quiz_grades
  │
  ├── Filtros (igual que autocorrect):
  │     · Solo cursos desde 2026-01-01
  │     · Excluir DEMO, Prueba de nivel, Italiano
  │     · Respetar EXCLUDED_SUBMISSIONS para alumnos con IA confirmada
  │
  └── Log + dry-run igual que hansel_autocorrect.py
```

**Cron sugerido:** mismo ciclo que autocorrect, o separado cada 4h:
```
0 */4 * * * /usr/bin/python3 /home/aulatuspeaking/scripts/hansel_quiz_grader.py >> /home/aulatuspeaking/logs/hansel_quiz_grader.log 2>&1
```

**Integración en hansel_digest.py:** añadir una tercera sección al email diario con los quizzes calificados en 24h.

---

## Estimación de trabajo y ROI

### Coste de desarrollo
| Tarea | Horas estimadas |
|---|---|
| Verificar SQL de lectura y escritura en BD test | 2h |
| Escribir `hansel_quiz_grader.py` | 4h |
| Ajustar prompts por tipo (translation vs writing) | 1h |
| Test en seco (--dry-run) + corrección | 1h |
| Deploy y primera ejecución real | 1h |
| Integrar en hansel_digest.py | 1h |
| **Total** | **~10h desarrollo** |

### ROI
- Backlog actual: ~160 respuestas essay × 3 min calificación manual = **~8h acumuladas**
- Volumen mensual estimado: ~40 respuestas/mes × 3 min = **~2h/mes**
- A €100/h: **€200/mes ahorro recurrente = €2.400/año**
- Coste desarrollo: ~10h × €100 = €1.000 (una sola vez)
- **Payback: 5 meses**

---

## Riesgos y consideraciones

| Riesgo | Impacto | Mitigación |
|---|---|---|
| SQL directo no registra IP en logstore | Bajo (quizzes no son FUNDAE críticos) | Documentado; migrar a REST API si Moodle lo implementa |
| Recálculo de nota quiz incorrecto | Alto | Verificar con `--dry-run` y comparar sumgrades antes/después |
| Respuestas essay muy cortas o vacías | Bajo | Filtrar respuestas < 5 chars → skip |
| Alumno con IA en quiz | Bajo | Añadir ai_score al prompt de translations; umbral más alto (90) porque traducciones cortas puntúan alto artificialmente |
| Nota máxima por pregunta variable | Medio | Leer `maxmark` de `mdl_question_attempts` en lugar de asumir 1.0 |

---

## Siguiente paso

~~Verificar estructura SQL~~ — **Completado 27/06/2026.** Hallazgos clave:

- Estado para essays pendientes: `needsgrading` ✅
- Estado tras calificación manual: **`mangrright` / `mangrpartial` / `mangrwrong`** (no `manuallygraded`)
- Campos confirmados: `-comment`, `-commentformat`, `-mark`, `-maxmark` ✅
- Respuesta del alumno se lee desde el step con estado `complete` y `name = 'answer'`

**Siguiente acción:** Desarrollar `hansel_quiz_grader.py` con el diseño técnico corregido arriba.
