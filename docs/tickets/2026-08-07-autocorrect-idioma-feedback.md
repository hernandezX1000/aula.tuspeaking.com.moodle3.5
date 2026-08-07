# TICKET AC-5 — El autocorrector escribe el feedback en inglés en cursos de francés y portugués

**Repo:** `aula.tuspeaking.com.moodle3.5` · **Prioridad:** Alta (afecta a notas y llega al cliente)
**Fecha:** 2026-08-07 · **Estado:** 🟡 fix escrito en el repo, **PENDIENTE de probar y desplegar** (ver §7)
**Fichero:** `tus-tools/autocorrect/hansel_autocorrect.py` → despliega con `deploy.sh`
**Origen:** entrega de audio de Nicolás Bilanin (GDES Francés B2, curso 3242), corregida el 3-ago 23:54

---

## 1. Síntoma

En un curso de **francés**, el alumno abre su entrega corregida y lee:

> *"While you demonstrate effort in addressing the topic of home technology, the submission
> contains numerous significant errors that…"* — **35,00 / 100,00**

Feedback **en inglés**, firmado como "Hansel Fernández", en un curso de francés B2 de un
cliente FUNDAE. El alumno no tiene por qué saber que lo escribe un corrector automático:
lo que ve es que su profesor le corrige el francés en inglés.

---

## 2. Causa

Dos fallos distintos, en dos rutas distintas.

### 2.1 Audio — no se dice en qué idioma escribir (línea ~706)

`AUDIO_GRADING_SYSTEM` **no menciona el idioma del feedback** en ninguna de sus reglas. Como
todo el prompt está en inglés, el modelo responde en inglés por defecto.

El idioma **sí** se detecta y se usa para evaluar:

```python
lang_label = {'en': 'English', 'fr': 'French', 'pt': 'Portuguese'}.get(lang, 'English')
f"Evaluate this {level} level {lang_label} spoken submission.\n"
```

Es decir: evalúa francés correctamente, pero comenta en inglés. **Falta una sola instrucción.**

### 2.2 Writing — el idioma está clavado a inglés (línea ~601 y ~669)

Aquí es peor. El prompt de sistema dice literalmente:

```python
You are an experienced English language teacher at a corporate language academy (tuSpeaking).
...
- Feedback must be 2 to 4 sentences in English.
```

Y el mensaje de usuario:

```python
f"Grade this {level} level English writing submission.\n"
```

`call_claude_writing()` **no recibe el idioma del curso**. No es solo que el comentario salga
en inglés: es que **una redacción en francés se evalúa como si fuera una redacción en inglés**.
El modelo ve texto francés donde se le ha dicho que espere inglés, y lo trata como errores.

⚠️ **Esto puede estar hundiendo las notas de todas las redacciones de francés y portugués.**

### 2.3 `FRENCH_KEYWORDS` no sirve para esto

```python
# French courses → nota_max=100 (Salvi, Bydemes, GDES Frances)
FRENCH_KEYWORDS = ['salvi', 'bydemes', 'frances', 'francés', 'french']
```

Solo se usa para **escalar la nota** a 100. El idioma del feedback no lo mira nadie.

---

## 3. Es el mismo patrón que AC-2 (quiz grader)

Ver `2026-07-27-quiz-grader-direccion-traduccion.md`: allí el grader penalizaba respuestas
en español porque el prompt asumía inglés. **Misma raíz: el prompt da por supuesto que el
idioma es inglés.** Se arregló en el quiz grader y no se revisó el resto del sistema.

Al corregir esto conviene barrer los tres graders a la vez, no solo el que ha saltado.

---

## 4. Fix propuesto

1. Extraer `detect_course_language(course_name)` a partir de `detect_audio_language()` (línea
   ~483), que ya hace justo esto, y usarla también en la ruta de writing.
2. **Audio:** añadir al system prompt una regla explícita:
   `- Write the feedback in {lang_label}.`
3. **Writing:** parametrizar `GRADING_SYSTEM_PROMPT` y `call_claude_writing(..., lang)`.
   Quitar los dos `English` clavados. El feedback, en el idioma del curso.
4. **Decisión de producto (confirmada por Hansel, 7-ago): el feedback va en el idioma
   meta del curso** — francés para los cursos de francés. No en español.
5. **Re-calificar lo afectado.** Especialmente las redacciones de francés y portugués, que
   pueden tener nota injustamente baja, no solo el comentario en el idioma equivocado.

---

## 5. Medir el alcance antes de tocar

```sql
SELECT c.id, c.fullname,
       COUNT(*) AS entregas_corregidas,
       ROUND(AVG(g.grade),1) AS nota_media,
       MIN(FROM_UNIXTIME(g.timemodified)) AS desde,
       MAX(FROM_UNIXTIME(g.timemodified)) AS hasta
FROM mdl_assign_grades g
JOIN mdl_assign a ON a.id = g.assignment
JOIN mdl_course c ON c.id = a.course
WHERE g.grader IN (14, 4414)
  AND (LOWER(c.fullname) LIKE '%frances%' OR LOWER(c.fullname) LIKE '%francés%'
       OR LOWER(c.fullname) LIKE '%french%'  OR LOWER(c.fullname) LIKE '%portug%'
       OR LOWER(c.fullname) LIKE '%salvi%'   OR LOWER(c.fullname) LIKE '%bydemes%')
GROUP BY c.id, c.fullname
ORDER BY entregas_corregidas DESC;
```

Comparar la **nota media** de esos cursos con la de los cursos de inglés. Si es
sensiblemente más baja, el punto 2.2 está confirmado y hay que re-calificar en serio.

### Resultado del barrido (07-ago-2026)

Los cursos de francés van en escala 100 → dividir entre 10 para comparar.

| Curso | Entregas | Media | Sobre 10 |
|---|---|---|---|
| **3242 · GDES Francés B2** | 14 | 50,4 | **5,0** |
| **3241 · GDES Francés A2** | 1 | 45,0 | **4,5** |
| **3271 · Francés Intermedio · Iberassekuranz** | 3 | 40,0 | **4,0** |
| **3246 · Francés Principiante** | 4 | 48,8 | **4,9** |
| 3170 · Francés Principiante (mar-jun) | 15 | 69,1 | 6,9 |
| 3200 · GDES Francés B2 · Noelia | 5 | 79,0 | 7,9 |
| 3199 · GDES Francés B1 · Raquel | 1 | 80,0 | 8,0 |
| *Cursos de inglés (rango habitual)* | — | — | *7,5 – 8,0* |

**Los cursos de francés de 2026.2 están 3 puntos por debajo de los de inglés.** Los cursos de
francés anteriores estaban en el rango normal, así que además hay un **cuándo** que investigar.

⚠️ Correlación, no prueba. **Prueba definitiva y barata:** re-pasar una entrega de francés ya
corregida por el corrector con el prompt arreglado y comparar la nota. Si sube ~3 puntos,
confirmado.

### Hallazgo adicional: no son solo francés y portugués

```python
lang_label = {'en': 'English', 'fr': 'French', 'pt': 'Portuguese'}.get(lang, 'English')
```

**Cualquier idioma fuera de esos tres cae a "English".** En el aula hay cursos de:

| Idioma | Cursos |
|---|---|
| Español / Castellano | 4041 (Capitole · Shadi Chalaki), 3125 |
| Alemán | 2960 |
| Italiano | 2172 |

A esas entregas se les está pidiendo al modelo que evalúe **una redacción en inglés**. El
diccionario debe cubrir todos los idiomas del catálogo y, ante un idioma desconocido,
**abstenerse de corregir** en lugar de caer a inglés por defecto.

---

## 7. Fix aplicado en el repo (07-ago, sin desplegar)

### 7.1 Idioma

- `detect_audio_language()` → **`detect_course_language()`**, ahora cubre `fr`, `pt`, `de`,
  `it`, `es`, `en` (antes solo los tres primeros; el resto caía a inglés). Se mantiene el
  nombre viejo como alias.
- `GRADING_SYSTEM_PROMPT` (constante en inglés) → **`writing_system(lang)`**.
- `AUDIO_GRADING_SYSTEM` (constante en inglés) → **`audio_system(lang)`**.
- `call_claude_writing()` recibe `lang` y `firstname`. Los tres puntos de llamada
  (writing, file-writing, audio) le pasan el idioma del curso.

### 7.2 Tono — bloque `TUTOR_STYLE`

Lo que hacía que sonara a máquina no era el idioma, era la plantilla. Se elimina:

- el formato `Correction: "x" → "y"` — la corrección se integra en una frase normal;
- las dos reglas `MANDATORY` que imponían la misma estructura a todos los comentarios;
- la longitud fija de 2-4 frases.

Y se añade: **tuteo**, variar el arranque y la forma de un comentario a otro, citar **una**
cosa concreta que el alumno dijo (no la lista entera de errores), usar el nombre de pila
cuando encaje, y no mencionar nunca la nota, la transcripción ni que es automático.

### 7.3 Audio: no penalizar a Whisper

El audio en francés estaba en 4,19 sobre 10 frente a 6,98 en inglés. La transcripción
automática es peor en francés que en inglés, y el modelo estaba puntuando errores que el
alumno **no cometió**: los cometió Whisper. Nueva regla en el prompt de audio: *no penalizar
lo que parezca un artefacto de transcripción; juzgar lo que el alumno claramente quiso decir*.

⚠️ **Esto es un parche sobre el síntoma.** La causa de fondo es la calidad de la
transcripción en francés — probablemente el tamaño del modelo Whisper. **Pendiente de
comprobar** y, si se confirma, subir el modelo para los idiomas que no sean inglés.

### 7.4 Cómo probarlo ANTES de desplegar

`test_feedback_style.py` genera tres comentarios de ejemplo (dos audios y una redacción de
francés, con errores típicos) sin tocar la base de datos:

```bash
source /home/coreadmin/venv-autocorrect/bin/activate
export ANTHROPIC_API_KEY='sk-ant-...'
cd /home/coreadmin/scripts && python3 test_feedback_style.py
```

Criterio de aceptación: los tres en francés, tuteando, **con aperturas distintas**, sin
flechas ni etiquetas, citando algo concreto. Y la pregunta que decide: **¿los firmarías?**

### 7.5 Pendiente

- [ ] Probar el estilo y ajustar el prompt si hace falta.
- [ ] Desplegar con `deploy.sh` y verificar con una corrida real.
- [ ] Decidir el recalificado de las **21 correcciones** de los cursos de francés de GDES
      (3199, 3200, 3241, 3242) — sobre todo las **8 de audio**, que son las castigadas.
- [ ] `AUDIO_FEEDBACK_FR` / `AUDIO_FEEDBACK_EN` (fallback de transcripción vacía) solo
      cubren francés e inglés. Alemán, italiano y español caen a inglés.

---

## 6. Riesgo si no se corrige

- **Notas injustas** en cursos de francés y portugués → reclamaciones.
- **Imagen ante el cliente:** GDES es cliente FUNDAE y ya ha reportado tres incidencias del
  mismo curso en agosto. Un comentario en inglés en un curso de francés es visible para
  cualquiera sin necesidad de mirar la base de datos.
- Si además la nota baja impide superar una actividad, **afecta al 75% de bonificación**.
