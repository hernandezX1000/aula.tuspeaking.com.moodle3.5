# FUN-4 — Auditoría automática de cumplimiento FUNDAE de los cursos del aula

- **Área:** FUNDAE · **Prioridad:** Alta · **Estado:** 🔴 abierto
- **Abierto:** 22-sep-2026 · **Pedido por:** Hansel
- **Relacionado:** FUN-2 (gestión de requerimientos), COMP-3 (H5P no escribe nota), ING-2 (clases sin resolver)
- **Contexto de negocio:** `~/Proyectos/Hansel/fundae/requerimientos/` (índice, procedimiento y las 51 notificaciones del SEPE ya parseadas)

## Por qué

Hoy nos enteramos de que un curso no cumple **cuando llega el requerimiento**, con 10 días hábiles
para responder y el curso ya terminado: si faltan horas de conexión o no hay seguimiento tutorial,
ya no hay nada que hacer.

Dos datos de lo trabajado el 22-sep-2026:

- **AF 061-01 (e2y, Francisco Sánchez, 90 h)**: solo se acreditan unas 31 h de conexión de las 90
  de la acción formativa. Se detectó al preparar la respuesta, no antes.
- **AF 026-01 (Salvi, francés)**: el grupo estaba **vacío** — 0 participantes y 0 costes — y aun así
  generó requerimiento. Nadie lo vio hasta que llegó la carta.

Y el patrón de llegada está medido: en el ejercicio 2025, **18 de los 22 requerimientos llegaron en
dos días** (17 y 19 de noviembre), barriendo de la AF-006 a la AF-127. En 2026 vamos por la AF-061,
así que la tanda de cierre está por llegar y cubrirá los cursos de la segunda mitad del año.

## Qué hay que construir

Un script que recorra los **129 cursos bonificables** (los que tiene asignados el usuario inspector
5676, y/o los de `mdl_fundae`) y por cada curso/grupo compruebe lo que el SEPE pide de verdad. La
lista no es inventada: sale de las 51 notificaciones DEHú ya parseadas en
`Hansel/fundae/requerimientos/_notificaciones_DEHu/NOTIFICACIONES.md`.

| # | Comprobación | Semáforo en rojo si… | Fuente |
|---|---|---|---|
| 1 | Horas de conexión del participante frente a las horas de la AF | acreditado < 75 % de las horas | logstore + Zoom/Teams |
| 2 | Registro de conexiones exportable (fecha, entrada/salida, duración, IP) | no hay registros o hay huecos | `mdl_logstore_standard_log` |
| 3 | Comunicación sincronizada formador–participante por sesión | sesiones sin el formador conectado | `mdl_i3code_acuityZoom` |
| 4 | Seguimiento tutorial: mensajes tutor–alumno y correcciones | 0 mensajes o 0 correcciones | mensajería + foros + entregas |
| 5 | Pruebas evaluables y libro de calificaciones con notas | actividades sin `finalgrade` | `mdl_grade_grades` — **enlaza con COMP-3** |
| 6 | Guía didáctica publicada en el curso | no existe | recursos del curso |
| 7 | Material didáctico entregado (muestra) | no hay recurso descargable | recursos del curso |
| 8 | Acceso del usuario inspector + enlace directo al curso | inspector no matriculado | `mdl_role_assignments` |
| 9 | Nombre del curso con el código AF-grupo | el nombre no lleva `NNN-NN` | `mdl_course.fullname` |
| 10 | Grupo con participantes y con costes | 0 participantes | `mdl_fundae` / ficha del grupo |

Lo que **no** puede comprobar el aula (contrato de encomienda del año, CV del docente, diplomas
firmados, justificante de entrega) se queda fuera: eso vive en el lado de negocio y lo cubre el
README de cada expediente en Hansel.

## Salida

- Una tabla por curso con semáforo y el detalle de lo que falla.
- Un **ranking de riesgo**: los cursos que, si mañana llega el requerimiento, no podríamos defender.
- Idealmente dentro de `block_fundae`, que ya está en producción, en vez de otro panel suelto.

## Programación

Semanal. Y **un barrido completo a primeros de octubre**, antes de la tanda de noviembre, que es
cuando todavía da tiempo a corregir: subir horas, abrir mensajería, publicar la guía.

## Primer paso sugerido

Antes de programar nada, pasar la comprobación 1 (horas acreditadas frente a horas de la AF) a los
129 cursos. Es una sola consulta y ya dice cuántos cursos están en la situación de la AF 061-01.

## Fila para `docs/BACKLOG.md`

| FUN-4 | **Auditoría automática de cumplimiento FUNDAE de los 129 cursos bonificables** | FUNDAE | **Alta** | 🔴 | 22-sep-2026. Hoy se detecta el incumplimiento cuando llega el requerimiento y ya no hay margen (AF 061-01: 31 h acreditadas de 90; AF 026-01: grupo vacío). 10 comprobaciones sobre logstore, Zoom, calificaciones y matriculación del inspector; semáforo por curso dentro de `block_fundae`; barrido semanal y uno completo en octubre, antes de la tanda de cierre (en 2025 llegaron 18 requerimientos en 2 días, el 17 y 19 de noviembre). `docs/tickets/2026-09-22-fun4-auditoria-cumplimiento-fundae.md` |
