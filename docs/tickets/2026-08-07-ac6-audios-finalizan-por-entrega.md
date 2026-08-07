# TICKET AC-6 — Los audios finalizan al entregar; se retira lo que calificó Whisper

**Repo:** `aula.tuspeaking.com.moodle3.5` · **Prioridad:** Alta · **Estado:** 🟡 listo para ejecutar
**Fecha:** 2026-08-07 · **Decisión de:** Hansel · **Carácter:** permanente

---

## 1. Por qué

El autocorrector transcribe los audios con **`faster-whisper-base`**. En inglés pasa; en el
resto de idiomas inventa. Transcripción real de un audio de 78 s de Nicolás Bilanin
(GDES Francés B2), por el que se le puso **35/100**:

> *"nous proviennent de contrôler les lumines. Les chauffeurs les salarent plus facilement…
> les tégems managers. Sepantane, il y a aussi des inconveniences… certains sequimènent
> prévenant de venir inutiles."*

El alumno dijo *"nous pouvons contrôler les lumières"*, *"cependant"*, *"des inconvénients"*.
**Le calificamos por errores que cometió el modelo, no él.**

Medido sobre todo 2026, nota media normalizada sobre 10:

| | Audio | Texto |
|---|---|---|
| **Francés** | **4,19** (n=8) | 8,13 (n=80) |
| Inglés | 6,98 (n=95) | 7,09 (n=198) |
| Portugués | 8,33 (n=3) | 9,00 (n=6) |

El texto en francés está por encima del inglés. **La anomalía es solo el audio**, y la causa
es la transcripción.

### Comparativa de modelos (mismo audio, 78 s)

| Modelo | Tiempo | Resultado |
|---|---|---|
| `base` (actual) | 6 s | inservible: *"lumines"*, *"tégems managers"*, *"Sepantane"* |
| `small` | 22 s | mejora, pero oye **"l'Amazon"** donde dice *"la maison"*, dos veces |
| `medium` | 42 s | usable: acierta *lumières*, *maison*, *cependant*, *en outre*. Aún falla en *"les solaires"*, *"devant les secrets"* |

Ni siquiera `medium` es fiable para poner una nota.

---

## 2. Decisión

**Los audios dejan de calificarse: cuentan por entregarlos.** Permanente.

Es coherente con FUNDAE, donde el criterio es la **realización** (Sent. 117/2018): entregar
el audio ya demuestra que se hizo la actividad. Y deja de depender de que una máquina acierte.

| Qué | A quién |
|---|---|
| Finalizar al entregar | **Todas** las actividades de audio, todos los idiomas |
| Borrar comentario y nota automáticos | Solo cursos **no ingleses** — donde está el destrozo |
| Marcar finalizadas las entregas existentes | Todas |

---

## 3. Antes de ejecutar

- Los cursos **antiguos ya finalizan por entrega** (`completionsubmit = 1`,
  `completiongradeitemnumber` NULL). Los que exigen nota son los de **2026** con "Entrega: Audio"
  (3199, 3200, 3201, 3242, 3241, 3245, 3246, 3271, 4028…). Por eso borrar notas viejas
  **no descompleta a nadie**.
- ⚠️ **No usar `franc` en el filtro de idioma**: hay cursos de inglés con alumnos llamados
  **Francisco** ("Inglés B1.2 - Francisco Sánchez"). Usar `frances|francés|french|français`.
- ⚠️ **No usar `bydemes`/`salvi` como marcador de idioma.** `FRENCH_KEYWORDS` los trata como
  francés y esas cuentas tienen **también cursos de inglés** ("Bydemes - A2 Level - Studying
  English"). Aquí se detecta por el nombre del idioma en el título.
- No activar seguimiento donde no lo hay: **no tocar** las actividades con `cm.completion = 0`.
- Todo por base de datos, **no por la API de Moodle**: así el alumno no recibe una avalancha
  de notificaciones.

---

## 4. Ejecución

### Paso 1 — Criterio: basta con entregar (todos los idiomas)

```bash
aula-sql --write mdl_assign
```
```sql
-- Comprobar primero cuántas se van a tocar
SELECT COUNT(*) FROM mdl_assign a
JOIN mdl_course_modules cm ON cm.instance = a.id
JOIN mdl_modules m ON m.id = cm.module AND m.name = 'assign'
WHERE LOWER(a.name) LIKE '%audio%' AND cm.completion <> 0 AND a.completionsubmit <> 1;

UPDATE mdl_assign a
JOIN mdl_course_modules cm ON cm.instance = a.id
JOIN mdl_modules m ON m.id = cm.module AND m.name = 'assign'
SET a.completionsubmit = 1
WHERE LOWER(a.name) LIKE '%audio%' AND cm.completion <> 0;
```

```bash
aula-sql --write mdl_course_modules
```
```sql
-- Quitar el requisito de nota
UPDATE mdl_course_modules cm
JOIN mdl_modules m ON m.id = cm.module AND m.name = 'assign'
JOIN mdl_assign a  ON a.id = cm.instance
SET cm.completiongradeitemnumber = NULL
WHERE LOWER(a.name) LIKE '%audio%' AND cm.completion <> 0;

-- Verificación: debe devolver 0
SELECT COUNT(*) FROM mdl_course_modules cm
JOIN mdl_modules m ON m.id = cm.module AND m.name = 'assign'
JOIN mdl_assign a  ON a.id = cm.instance
WHERE LOWER(a.name) LIKE '%audio%' AND cm.completion <> 0
  AND cm.completiongradeitemnumber IS NOT NULL;
```

### Paso 2 — Retirar comentario y nota en cursos no ingleses

```bash
aula-sql --write mdl_assignfeedback_comments
```
```sql
-- Ver qué se va a borrar (revisar la lista antes)
SELECT c.fullname, a.name, COUNT(*) AS comentarios
FROM mdl_assignfeedback_comments f
JOIN mdl_assign_grades ag ON ag.id = f.grade
JOIN mdl_assign a ON a.id = ag.assignment
JOIN mdl_course c ON c.id = a.course
WHERE LOWER(a.name) LIKE '%audio%'
  AND ag.grader IN (14, 4414)
  AND LOWER(c.fullname) REGEXP 'frances|francés|french|français|portug|aleman|alemán|german|italian|castellano|español|espanol'
GROUP BY c.fullname, a.name;

DELETE f FROM mdl_assignfeedback_comments f
JOIN mdl_assign_grades ag ON ag.id = f.grade
JOIN mdl_assign a ON a.id = ag.assignment
JOIN mdl_course c ON c.id = a.course
WHERE LOWER(a.name) LIKE '%audio%'
  AND ag.grader IN (14, 4414)
  AND LOWER(c.fullname) REGEXP 'frances|francés|french|français|portug|aleman|alemán|german|italian|castellano|español|espanol';
```

```bash
aula-sql --write mdl_assign_grades
```
```sql
DELETE ag FROM mdl_assign_grades ag
JOIN mdl_assign a ON a.id = ag.assignment
JOIN mdl_course c ON c.id = a.course
WHERE LOWER(a.name) LIKE '%audio%'
  AND ag.grader IN (14, 4414)
  AND LOWER(c.fullname) REGEXP 'frances|francés|french|français|portug|aleman|alemán|german|italian|castellano|español|espanol';
```

⚠️ Solo se borra lo puesto por el **autocorrector** (`grader IN (14, 4414)`). Si un profesor
corrigió un audio a mano, **se respeta**.

### Paso 3 — Marcar finalizadas las entregas existentes

```bash
aula-sql --write mdl_course_modules_completion
```
```sql
INSERT INTO mdl_course_modules_completion (coursemoduleid, userid, completionstate, viewed, timemodified)
SELECT cm.id, asub.userid, 1, 1, UNIX_TIMESTAMP()
FROM mdl_assign_submission asub
JOIN mdl_assign a ON a.id = asub.assignment
JOIN mdl_course_modules cm ON cm.instance = a.id
JOIN mdl_modules m ON m.id = cm.module AND m.name = 'assign'
JOIN mdl_user u ON u.id = asub.userid AND u.deleted = 0
WHERE LOWER(a.name) LIKE '%audio%'
  AND asub.latest = 1 AND asub.status = 'submitted'
  AND cm.completion <> 0
ON DUPLICATE KEY UPDATE completionstate = 1, timemodified = UNIX_TIMESTAMP();
```

### Paso 4 — Cachés y comprobación

```bash
aula-php admin/cli/purge_caches.php
```

Entrar como un alumno de 3242 y comprobar que las entregas de audio salen **en verde, sin
nota y sin comentario**.

---

## 5. Vuelta atrás

`aula-sql --write` deja copia de cada tabla como `<tabla>_bak_YYYYMMDD_HHMM`. Para deshacer:

```sql
-- ejemplo con las notas
INSERT INTO mdl_assign_grades SELECT * FROM mdl_assign_grades_bak_YYYYMMDD_HHMM b
WHERE NOT EXISTS (SELECT 1 FROM mdl_assign_grades t WHERE t.id = b.id);
```

---

## 6. Después

- [ ] **Subir el modelo Whisper a `medium`** para cualquier idioma que no sea inglés
      (`WHISPER_MODEL_SIZE`). Aunque ya no se califique el audio, la transcripción se sigue
      usando y no puede seguir inventando.
- [ ] Probar `initial_prompt` con el título de la entrega: al decirle a Whisper que el tema
      es *"La maison et la technologie"*, deja de oír *"l'Amazon"*. Suele dar más mejora que
      subir de modelo y no cuesta tiempo.
- [ ] Que el autocorrector **deje de calificar audios** de cursos no ingleses, o el cron
      volverá a ponerles nota. Es el paso que cierra este ticket de verdad.
- [ ] Revisar `FRENCH_KEYWORDS`: incluye `bydemes` y `salvi`, que tienen **cursos de inglés**.
      Ahora mismo se les aplica escala 100 y se les trata como francés.
