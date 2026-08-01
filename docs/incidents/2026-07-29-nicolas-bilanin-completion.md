# Incidencia — Nicolás Bilanin: actividades no se marcan como completadas

- **Fecha:** 2026-07-29
- **Área:** Completion (Moodle) · **ID BACKLOG:** COMP-2
- **Estado:** 🔴 abierto
- **Reportado por (2 veces, mismo curso):**
  - Daniela Arteaga (Responsable de Formación, GDES — `d.arteaga@gdes.com`), 29-jul 13:20 → alumno **Nicolás Bilanin** (`n.bilanin@gdes.com`, uid 5822).
  - Guillaume Fournier (Docente Francés — `francais_en_ligne@outlook.com`), 29-jul 16:35 → alumno **Luis Miguel Alonso Porro** (`l.alonso@gdes.com`, uid 5823): "no pueden culminar las lecciones de **Gramática (u otras teóricas)** y la barra de avances se queda en rojo".
- Que lo reporte el **profesor** para varios alumnos confirma que es **de todo el curso** "2026.2 - GDES - Frances B2", no de un alumno. Las "lecciones teóricas/Gramática" = las **Páginas** del bloque con `completionview=0`.

## Síntoma (según la alumna/HR)

> "El alumno Nicolás Bilanin Artigado (`n.bilanin@gdes.com`) indica que **realiza las actividades pero no se le marcan como completadas**. ¿Podéis contactar con él para ver dónde radica el fallo?"

## Datos del alumno

- Nombre: **Nicolás Bilanin Artigado**
- Email / usuario Moodle: `n.bilanin@gdes.com` — **`mdl_user.id = 5822`**
- Edición: **2026 - GDES (2026.2)** — categoría Moodle **539** (Francés B2).

## Hipótesis a descartar (de más probable a menos)

1. **Completion no configurada / criterio no cumplido** en esas actividades: el módulo tiene "Completion tracking = None", o el criterio exige algo que él no dispara (nota mínima, "ver actividad", enviar intento, marcar manualmente). → revisar `completion` y `completionview/completionusegrade` de los `mdl_course_modules` de su curso.
2. **Completion no se registra para su usuario**: mirar `mdl_course_modules_completion` (y `mdl_course_completion_crit_compl`) para `userid=5822` — ¿hay filas? ¿`completionstate=0`?
3. **Actividad concreta** (SCORM/H5P/quiz) cuyo evento de finalización no llega (típico en H5P/SCORM). → identificar si es TODO el curso o módulos concretos.
4. **Cron de completion parado** (`\core\task\completion_regular_task` / `completion_daily_task`): si el cron de Moodle no corre, el estado no se recalcula. → revisar crons en el contenedor tras la migración a Hetzner.

## CAUSA RAÍZ (diagnosticada 29-jul)

**No es de Nicolás ni del cron: es configuración del curso "2026.2 - GDES - Frances B2" (course 3242, `enablecompletion=1`).** El curso tiene DOS bloques de actividades con distinta config de completion:

- **Bloque inicial** (cmid 524234-524243, 16-mar): Páginas con `completion=2` **y `completionview=1`** → `completionstate` 1/2 → se completaron bien (Nicolás incluido).
- **Bloque posterior** (cmid **530420+**, la mayor parte): `completion=2` (automática) **pero `completionview=0`** (Páginas) → **sin criterio que cumplir** → `completionstate=NULL`, nunca se marcan por más que el alumno las haga.

O sea, al bloque nuevo se le puso "finalización automática" sin activar ningún criterio (al duplicar/importar el contenido se perdió el "requiere ver"). Afecta a **todos los alumnos** del curso, no solo a Nicolás.

## Arreglo

En el curso → **Editar en bloque la finalización de actividad** (Bulk edit activity completion) → seleccionar el bloque cmid 530420+ → activar el criterio que falta (Páginas: "el estudiante debe ver"; H5P/Tareas: el que corresponda) → guardar (Moodle recalcula para quien ya las vio). Alternativa: SQL acotado (`UPDATE mdl_course_modules SET completionview=1 WHERE ...`) + recálculo de completion — menos recomendable porque hay que forzar el recálculo a mano.

Verificar además si otros cursos/ediciones tienen el mismo bloque mal configurado (posible relación con **COMP-1** Tekia).

## Siguientes pasos

1. Confirmar en la BD (aula, contenedor `moodle35-db`) qué actividades ha "hecho" y su `completionstate` real para `userid=5822`.
2. Ver la configuración de completion de esas actividades (criterio) y si el cron de completion está activo tras el cutover.
3. Según el hallazgo: reconfigurar el criterio, forzar el recálculo, o corregir el cron.
4. Responder a Daniela con el diagnóstico y, si procede, contactar al alumno.

## Notas

- Ojo cruce con **COMP-1** (Enrique Saña / Tekia: % de completion vs umbral) — puede ser el mismo tipo de causa (criterio/umbral) o distinto (aquí "no se marca nada"). Contrastar.
- Tras la migración a Hetzner conviene verificar que los **crons de Moodle** (incluido completion) corren en el contenedor.
