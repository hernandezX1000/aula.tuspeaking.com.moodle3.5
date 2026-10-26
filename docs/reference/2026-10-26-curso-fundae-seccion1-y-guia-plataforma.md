# Curso FUNDAE de teleformación en el aula: sección 1 mínima y guía de la plataforma

**Estado:**
- Partes 1–2: **VIGENTE** (decisión de En, 26/10/2026, T-306; piloto en Lin3s 2.ª, 4066–4070).
- Partes 3–5: **PROPUESTA**, pendiente de aprobación de En.

**Alcance:** todos los cursos FUNDAE de teleformación del aula (Moodle 3.9). Learn tiene su propia guía y no entra aquí.
**Backlog:** FUN-6, FUN-7 y FUN-8.

**Fuentes:**
- Orden TMS/369/2019, Anexo III.B «Requisitos técnicos de la guía del alumno» + indicadores de verificación. Extracto literal en `cumplimiento-sepe/00-normativa/ORDEN-TMS-369-2019-guia-del-alumno-extracto.md`.
- Actas del SEPE A1 (10/06/2026) y A2 (14/10/2026). Las actas mandan.
- `cumplimiento-sepe/04-guia-didactica/ESPECIFICACION-GUIA-DIDACTICA.md` (apartados G1–G10).
- `tus-tools/guias-didacticas/README.md`: cómo se hace y se publica la guía didáctica.

## 1. Principio
**Simplificar al máximo y cumplir solo con lo que establece FUNDAE** (En, 26/10).

Todo lo didáctico está en la guía didáctica. El curso no repite nada que pueda contradecirla.

## 2. Sección 1 «Guía de la plataforma y del Curso» (VIGENTE)
| # | Pieza | Por qué |
|---|---|---|
| 1 | Página «Guía didáctica – {denominación} (AF …)», con su PDF descargable | A1.P4: guía visible y igual a lo comunicado |
| 2 | Guía de la plataforma (hoy el PDF técnico v2; nueva en la parte 4) | Anexo III.B: acceso y funcionamiento |
| 3 | Enlace «Registro de tutorías» → `/tutorias_con_profesor.php?courseid=<curso>` | A1.P2: interacción tutor–alumno registrada |
| 4 | **Solo si los hay:** comentarios o feedback del tutor (aquí o mediante un informe) | Si no hay, no va nada |

- **Se borran** (papelera del curso, 15 días):
  - etiquetas de «información del curso» y encabezados;
  - vídeos H5P de ayuda;
  - FAQ;
  - carpeta de feedback vacía;
  - guías viejas;
  - etiquetas del móvil;
  - vídeo de YouTube de 2019.
- **Sección 0:** sin etiqueta. Lo dice la guía.
- **Columna:** se borra el bloque HTML «Cómo reservar…». El bloque Acuity se queda con el título «Tutorías» y el botón «Reservar».
- **Inspector:** solo rol `supervisor` en el curso, nunca `student`. Con los dos, `reservas_grupo.php` le negaba el permiso (T-306).
- **En el mismo paso** se actualiza la guía didáctica, para que el apartado «Recursos» no cite lo que se ha borrado (`--update`).
- **Herramientas usadas en el piloto:**
  - `registro-tutorias-todos.php` (cumplimiento-sepe, después de Q57);
  - `t306-06-seccion1-minima.php/.sh`: busca por tipo y nombre, aborta ante lo inesperado, guarda copia y tiene ensayo y `--apply`.

## 3. Qué pide la norma y dónde va (reparto sin duplicar)
| Anexo III.B / indicadores | Dónde va | Estado en el aula |
|---|---|---|
| Denominación, destinatarios, objetivos, estructura y calendario, tutorías, plan de trabajo, evaluación | Guía didáctica (G1–G8) | Hecho (herramienta base v3) |
| **Dedicación estimada** (horas por semana) y cómo organizarse (B-9) | Guía didáctica (G6) | **Falta** → FUN-8 |
| **Atención al usuario:** contacto, calendario y horario (CAU) | Guía didáctica (G9) | **Falta** → FUN-8. Con la sección 1 mínima ya no está en ninguna parte del curso |
| **Requisitos técnicos concretos:** dispositivo, navegadores, conexión, programas (Zoom) (B-4) | Guía didáctica (G10) | Solo genéricos → FUN-8 |
| **Acceso y conexión** al entorno: dirección, usuario y contraseña, recuperar contraseña | **Guía de la plataforma** | PDF v2 (antiguo) → FUN-7 |
| **Funcionamiento: botones, navegación, herramientas, recursos y utilidades** | **Guía de la plataforma** | PDF v2 (antiguo) → FUN-7 |
| Accesible siempre, también antes del inicio | Guía de la plataforma pública; guía didáctica en el curso | FUN-7 |

## 4. Guía de la plataforma nueva (PROPUESTA · FUN-7)
- **Formato:**
  - **una sola página web común del aula** (`/guia_plataforma.php`, con el estilo de `docs/ESTILO-PAGINAS-AULA.md`) y su **PDF descargable**;
  - en cada curso, el recurso «Guía de la plataforma» pasa a ser un enlace a esa página;
  - un solo sitio que mantener (hoy hay una copia del PDF en cada curso).
- **Sin sesión:** el indicador pide que esté accesible antes del inicio. No enseña datos de nadie.
- **Contenido** (solo acceso y funcionamiento, sin nada didáctico):
  1. Entrar en aula.tuspeaking.com: usuario, contraseña y «¿Olvidó su contraseña?».
  2. Área personal y «Mis cursos».
  3. Cómo está organizado un curso: la sección 1 (guía, guía de la plataforma, registro) y los temas.
  4. Abrir la teoría (PDF y PowerPoint).
  5. Hacer actividades interactivas y cuestionarios: se corrigen solos y queda el resultado.
  6. Entregar una tarea escrita o de audio.
  7. Escribir a la tutora (mensajes).
  8. Reservar una tutoría (bloque «Tutorías» → «Reservar») y entrar por Zoom.
  9. Ver el registro de tutorías, las calificaciones y el progreso.
  10. Si algo falla: remite al apartado de atención al usuario de la guía didáctica (G9).
- **Lenguaje:** «tutora/tutor» y «tutoría», nunca «profesor», «clase» ni «maestro». De tú.
- **Capturas:** del aula 3.9, con un alumno de prueba que abre En. Sin datos de alumnos reales.
- **Fuente y generación:** `tus-tools/guia-plataforma/`, con el texto en un fichero fuente y el PDF generado con la misma base que las guías didácticas (reportlab, colores del portal). Página y PDF salen de la misma fuente.
- **Despliegue:** con el flujo del repo (dev → CI → main → `t279-55`). Después, un script por curso cambia el recurso PDF por el enlace, con ensayo y `--apply`.

## 5. Herramienta de guías didácticas (PROPUESTA · FUN-8)
Campos nuevos en la ficha y en `guias_fundae.py`, que hoy no se generan en el aula (especificación, §6):
- **`atencion_usuario` (G9):** soporte@tuspeaking.com · 686 25 64 25 · de lunes a viernes, de 9:00 a 18:00 · botón de soporte de la plataforma. Son los valores de En del 16/10. Sin plazo de respuesta (principio: no se promete nada que la norma no exija).
- **`dedicacion` (G6/B-9):** horas por semana = horas de la AF ÷ semanas del curso, más una orientación breve para organizarse. Ejemplo, Lin3s: 14 h en 21 semanas ≈ 40 min a la semana.
- **`requisitos` (G10) concretos:**
  - ordenador o portátil (Windows, macOS o Linux) o tableta;
  - navegador en su versión actual: Chrome, Firefox, Edge o Safari. Se confirma con la lista de navegadores de Moodle 3.9 antes de publicar;
  - conexión estable;
  - micrófono y cámara;
  - Zoom (aplicación o navegador; en el móvil, la app).
- Las guías ya publicadas se actualizan con `--update`, con el molde del README: Q75 → `--ensayo-update` → `--update` → Q75.

## 6. Despliegue en todos los cursos (orden)
1. **Inventario de solo lectura por cliente** (t306-05 = L41 con la lista de cursos): cada plantilla es distinta. CENIEH, por ejemplo, ya está a medio camino desde el 15/10.
2. **Piloto en un curso por cliente** → captura de En → resto de cursos.
3. Antes de cada cliente, **Q57**. Después, `registro-tutorias-todos.php` y la sección 1 mínima (el script se generaliza y pasa a `tus-tools/`).
4. **En el mismo paso**, la guía de ese curso con `--update` («Recursos»; y G9, G10 y dedicación cuando esté FUN-8).
5. **Comprobación:** secuencia de la sección 1 = 3 cm (+ feedback si lo hay) y Q75 de la guía.

## 7. Abierto
- Aprobación de En de las partes 3–5.
- Alumno de prueba para las capturas (En).
- Orden de clientes: primero los activos que FUNDAE puede estar revisando.
