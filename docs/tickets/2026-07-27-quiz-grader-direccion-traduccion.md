# TICKET — Quiz grader: respetar la dirección de la traducción (EN→ES vs ES→EN)

**Fecha:** 2026-07-27
**Prioridad:** Alta (afecta a notas de alumnos; cron PAUSADO mientras tanto)
**Fichero:** `tus-tools/autocorrect/hansel_quiz_grader.py` (repo aula) → despliega a `/home/coreadmin/scripts/` con `deploy.sh`
**Entorno:** Hetzner `tuspeaking-lms`, venv `/home/coreadmin/venv-autocorrect`

## Contexto
El 27-jul se detectó que el quiz grader llevaba días **caído** (`ModuleNotFoundError: No module named 'pymysql'` tras el cutover del 26-jul). Se instaló `pymysql` en el venv y volvió a correr.

Al correr se destapó un **fallo de lógica**: para preguntas *"Translate: English to Spanish"* (respuesta correcta = en español), el grader marca las respuestas en español a **0.0** diciendo *"this is in Spanish, you should answer in English"*. Es el fallo que reportó el alumno **Enrique Saña** (Tekia B2) y también afecta a **Diego Sans** (Hyatt B2) y a cualquier curso con actividades de traducción EN→ES.

- Las preguntas **ES→EN** (Town hall, Wedding…) se califican bien (respuesta en inglés → 1.0).
- Las **EN→ES** (Coming of age, Getting married…) salen mal e incoherentes.

## Causa
El script ya detecta que la pregunta es de traducción (`detect_question_type` → 'translation'), pero **no detecta la dirección** ni se la pasa al prompt de Claude. El prompt asume que la respuesta debe estar en inglés y penaliza el español.

## Fix propuesto (~20-30 min)
1. Añadir `detect_translation_direction(name, questiontext)` → devuelve `en_to_es` / `es_to_en` / `None` leyendo el nombre de la pregunta (`q.name`) y el enunciado ("english to spanish" / "spanish to english", "traducir al español/inglés").
2. En el builder del prompt: si es **EN→ES**, instruir a Claude que la respuesta **debe estar en español** y que califique la calidad de la traducción al español; si es **ES→EN**, al revés.
3. **Re-calificar** las EN→ES ya mal calificadas (Enrique, Diego Sans y demás): resetear a `needsgrading` las afectadas y volver a lanzar el grader, o forzar re-grade.

## Estado
- [x] Cron pausado: línea `hansel_quiz_grader` comentada en el crontab de `coreadmin` (backup en `/tmp/crontab.bak`).
- [ ] Fix de dirección en el script (dev→main + deploy.sh).
- [ ] Re-calificar afectadas.
- [ ] Reactivar el cron.

## Flujo
Editar en local (`tus-tools/autocorrect/`), probar, `dev`→`main`, desplegar con `bash tus-tools/autocorrect/deploy.sh`, verificar con una corrida a mano, reactivar cron.
