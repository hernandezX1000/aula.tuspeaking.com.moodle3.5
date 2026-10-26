# Guías didácticas FUNDAE del aula — herramienta base y procedimiento

> **Fuente única** del procedimiento para hacer, publicar y cambiar la guía didáctica de un curso FUNDAE de teleformación del aula (Moodle 3.9, `aula.tuspeaking.com`).
> Vive en el **repo del aula** (`tus-tools/guias-didacticas/`): es una herramienta del aula (`cumplimiento-sepe/CLAUDE.md` §1). En `cumplimiento-sepe` quedan las evidencias (`02-clientes/…`) y el catálogo, que apunta aquí.
> - La skill `guias-didacticas-fundae` y el `RUNBOOK-MONTAJE-CURSOS-FUNDAE.md` de Hansel **enlazan aquí**; no repiten el procedimiento.
> - En Learn la guía es otra cosa («Mi plan de aprendizaje»): `tuspeaking-platform/docs/runbooks/PUBLICAR-GUIA-DIDACTICA-LEARN.md`.
> - Qué debe decir la guía: `cumplimiento-sepe/04-guia-didactica/ESPECIFICACION-GUIA-DIDACTICA.md` (Orden TMS/369/2019, Anexo III + actas A1 y A2).

## Herramientas de esta carpeta
| Fichero | Qué es | Cuándo se usa |
|---|---|---|
| `guias_fundae.py` **v4 (base)** | Herramienta base. v3 (26/10): guías de **CENIEH** + colores del portal + `--update`. **v4 (27/10, FUN-8): guía ÚNICA** (apartados 10 «Cómo usar la plataforma» y 11 «Atención al usuario», dedicación, requisitos concretos), **base común + personalización** y **ficha guardada en la base de datos** | **Toda guía nueva** y todo cambio de una guía |
| `base-aula.json` | Textos comunes de la guía única (consultas, requisitos, uso de la plataforma, atención al usuario). Se aplica sola si está junto a la herramienta | Siempre; la ficha del curso la sobrescribe |
| `guias_fundae_v2.py` | Variante de **DriiveMe**: añade el apartado «6. Cronología, temas y plazos» por curso (una ficha por curso). Importa colores y funciones de la base | Solo las guías de DriiveMe ya publicadas (`fichas-driiveme-2026-v22/`), mientras no se pasen a la base |
| `fichas-cenieh-2026/` | Fichas publicadas de CENIEH (las de W38) | Modelo de ficha |
| `fichas-driiveme-2026*/` | Fichas de DriiveMe (v1, v2.1 y v2.2 publicada) | Solo DriiveMe |
| `fichas-lin3s-2026/` | Fichas v4 de Lin3s 2.ª (00068–00072) + los textos literales de objetivos y contenidos de los que salen | Modelo de guía única |

La copia de `cumplimiento-sepe/herramientas/guias-didacticas/` (v1, v2.1–v2.3 del 6 y el 26/10) queda **obsoleta** desde que esta carpeta del repo del aula pasa a ser la única. Allí solo queda un README que apunta aquí.

## Reglas (no negociables)
1. **Objetivos y contenidos = texto LITERAL de lo comunicado a FUNDAE**, con sus saltos de línea. La herramienta lo comprueba en el PDF y falla si no coincide. No se resume, no se corrige y no se completa.
2. **Solo se escribe lo que el curso cumple de verdad.** Primero se audita el curso (`cumplimiento-sepe/herramientas/auditar-af.sh`) y la guía describe eso. No se ponen autocorregidos, test final ni horas que no existan.
3. **Controles de aprendizaje:** los que hay en el curso.
   - El autocorregido que cierra cada lección (regla FUNDAE-21d).
   - Si no hay autocorregidos, las actividades entregables que califica el tutor (decisión de En, 6/10/2026).
   - Criterio: realizar al menos el 75 % de los controles.
4. El PHP de publicación corre **siempre como www-data** (`aula-php`) y aborta si es root. No envía correos.

## Guía ÚNICA, base común y base de datos (v4, 27/10/2026, FUN-8)
- **Una sola guía por curso:** la didáctica lleva todo lo que pide el Anexo III.B, también el uso de la plataforma y la atención al usuario. No hay guía de la plataforma aparte; la sección 1 del curso es la guía y el registro de tutorías (`docs/reference/2026-10-26-curso-fundae-seccion1-y-guia-plataforma.md`).
- **Base común + personalización:**
  - `base-aula.json` lleva los textos comunes;
  - la ficha de la AF los sobrescribe: un dict como `tutoria` se mezcla clave a clave; una lista o un texto se sustituye entero; `"_quitar": ["clave"]` quita un apartado de la base en ese curso;
  - `--sin-base` genera sin la base: así salen idénticas, byte a byte, las guías anteriores a v4 (comprobado con CENIEH W38 y Lin3s v3).
- **Campos nuevos de la ficha:**
  - `dedicacion`: va al apartado 5;
  - `uso_plataforma`: `[[título, texto], …]`;
  - `atencion_usuario`: `[[etiqueta, valor], …]`.
- **Base de datos (fuente de la ficha publicada):** el PHP, al crear (`--apply`) o actualizar (`--update`) la página, guarda la **ficha completa ya mezclada** en `mdl_fundae_guia_didactica.ficha_json`, una fila por `c_fundae` = «AF-grupo» (clave única `uk_cfundae`).
  - Rellena también `objetivo_general`, `criterio_evaluacion`, `tutor_fundae_nombre`, `herramienta_sincrona`, `pdf_path` (URL de la página), `pdf_generado_at` y `estado = 'generado'`.
  - Registra la guía en `mdl_fundae_documentos` (`tipo_documento = 'guia_didactica'`); la anterior de esa AF pasa a `vigente = 0`.
  - `--ensayo-registrar` / `--registrar`: solo la base de datos, para guías que ya están publicadas.
  - `--exportar`: lee la ficha guardada.
  - La columna `ficha_json` la creó `t306-11` (con copia `mdl_fundae_guia_didactica_bak_t306_11`).

## Procedimiento

### 1. Reunir lo comunicado a FUNDAE
| Dato | De dónde sale |
|---|---|
| Objetivos y contenidos | Aplicación de FUNDAE → Formación → Acciones formativas → la AF → pestaña **Descripción** (copiar el texto tal cual y guardarlo como fuente). Si la AF salió de Learn, `gestion_fundae_acciones.objetivos_formativos` y `contenidos_formativos`. **AF externas** (Lin3s, Lactalis…): la ficha de la AF de la entidad organizadora |
| Denominación, horas, área, URL e inspector | Listado de FUNDAE → «Exportar Excel» (`AccionesFormativas.xls`) / `fundae_export_acciones` |
| Fechas, tutor, horas de tutoría y horario | Ficha del grupo en FUNDAE (pestañas Descripción y Tutores/Centros) |
| Metodología, recursos, controles, cronología | Auditoría del curso (`auditar-af.sh`: Q60–Q64, Q71, Q70) |

### 2. Ficha JSON
- Ruta: `fichas-<cliente>-<año>/afNNN-GG.json`. Modelo: `fichas-cenieh-2026/af035-01.json`.
- Obligatorios: `af`, `grupo`, `denominacion`, `horas`, `fecha_inicio`, `fecha_fin`, `empresa`, `tutor`, `objetivos`, `contenidos`, `evaluacion`, `cursos`.
- Opcionales:
  - `id_fundae`, `area`, `modalidad`, `entidad`, `plataforma`, `destinatarios[]`;
  - `metodologia_intro[]`, `metodologia[]`;
  - `tutoria{horas, sesiones, consultas, avisos, horario, seguimiento}`;
  - `recursos[]`, `requisitos[]`;
  - `nota_objetivos` (nota en cursiva bajo los objetivos);
  - `cronologia{titulo, cabecera[], anchos[], filas[[…]], notas[]}`;
  - `titulo_pagina`, `pdf`, `seccion` (número de sección del curso donde va la página);
  - `borrar_recursos{"<courseid>": ["nombre LIKE%"]}` (guía vieja que se borra después de crear la nueva);
  - `patron_objetivos`, `patron_contenidos`.
- `_fuente`: de dónde sale cada dato y qué cambia cada versión.

### 3. Generar y revisar
```bash
cd ~/Proyectos/aula.tuspeaking.com.moodle3.5/tus-tools/guias-didacticas
pip install reportlab                     # una vez; necesita las fuentes DejaVu y pdftotext (poppler)
python3 guias_fundae.py pdf    fichas-<cliente>-<año>/*.json -o salida/        # «literal: OK» en todas
python3 guias_fundae.py moodle fichas-<cliente>-<año>/*.json -o salida/publicar-guias.php
php -l salida/publicar-guias.php
```
Se le mandan los PDF a En para que los revise antes de publicar.

### 4. Publicar una guía NUEVA (crea la Página)
```bash
scp salida/publicar-guias.php coreadmin@46.225.232.27:/tmp/
ssh coreadmin@46.225.232.27 'docker cp /tmp/publicar-guias.php moodle39-app-aula:/tmp/ && docker exec moodle39-app-aula chmod 644 /tmp/publicar-guias.php && aula-php /tmp/publicar-guias.php'            # ENSAYO
ssh coreadmin@46.225.232.27 'aula-php /tmp/publicar-guias.php --apply; aula-php admin/cli/purge_caches.php; docker exec moodle39-app-aula rm -f /tmp/publicar-guias.php; rm -f /tmp/publicar-guias.php'
```
- Sale «CREADA cm …» por curso. Si hay `borrar_recursos`, la guía vieja se borra después (a la papelera del curso).
- `--revert` borra las páginas creadas.
- **El nombre de la página empieza por «Guía didáctica»**: así la encuentra el bloque «Seguimiento y control · FUNDAE» del inspector.

### 5. Cambiar una guía YA publicada (`--update`, misma página)
Este es el molde del 26/10, con el que se cambió la guía de DriiveMe sin tocar nada más:
1. **Línea base:** regenerar con las fichas publicadas **sin cambios** y comparar el texto con el PDF publicado (`pdftotext -layout`). Tienen que salir 0 diferencias.
2. Cambiar **solo** el dato en una copia nueva de las fichas (`…-vN/`) y regenerar. Comparar palabra a palabra, en el PDF y en el HTML (`html_pagina`). Solo puede cambiar ese dato.
3. **Q75:** el `MD5(mdl_page.content)` publicado tiene que ser el del HTML de las fichas sin cambios. Si no coincide, alguien editó la página a mano: no se sustituye.
4. **Ensayo:** `aula-php /tmp/publicar-guias.php --ensayo-update`. No escribe nada. En cada curso enseña el md5 del HTML y el sha1 del PDF, el de antes y el de después. Si hay más o menos de una página con ese nombre en el curso, no la toca.
5. **Publicar:** `aula-php /tmp/publicar-guias.php --update` → sustituye el HTML y el PDF (mismo nombre de fichero: el botón «Descargar» sigue valiendo) y sube la revisión. Después, `purge_caches` y borrar el script.
6. Q75 otra vez, con los md5 nuevos. Guardar el PDF publicado como evidencia en `cumplimiento-sepe/02-clientes/<cliente>/evidencias/<AF>/AAAA-MM-DD_<AF>_P4_guia-publicada-vN.pdf`.

### 6. Al terminar
- En el Project: el ticket con los cm, el md5 del script, lo borrado y la verificación; y el registro del día.
- En este repo del aula (lo commitea En, dev → CI → main): las fichas nuevas y una línea en el historial de abajo.
- En `cumplimiento-sepe`: el PDF publicado como evidencia en `02-clientes/<cliente>/evidencias/<AF>/`.

## Estilo (colores del portal, desde el 26/10/2026)
- Paleta de `docs/ESTILO-PAGINAS-AULA.md` del repo del aula, la misma del panel FUNDAE y de las páginas del inspector:
  - cabeceras de apartado #008ba3 con texto blanco;
  - etiquetas #e0f7fa;
  - texto de color (título y etiquetas) #006b7f (`AZUL_OSC`, contraste AA);
  - bordes #dee2e6;
  - pie #6c757d.
- Botón «Descargar la guía didáctica en PDF» con la clase `btn btn-primary` del tema.
- Fuente DejaVu Sans, cuerpo de 9,2 pt, A4 con márgenes de 2 cm.
- **11 apartados** (Orden TMS/369/2019, Anexo III.B): datos de la acción · destinatarios y acceso · objetivos · contenidos · metodología (con la dedicación y la cronología) · tutoría y seguimiento · evaluación · recursos · requisitos técnicos · cómo usar la plataforma · atención al usuario. Los dos últimos y la dedicación solo salen si están en la ficha o en la base (v4).

## Historial
| Fecha | Versión | Qué cambió | Dónde se usó |
|---|---|---|---|
| 6/10/2026 | v1 | Primera versión (T-112) | DriiveMe 112–114 (v1) |
| 6/10/2026 | `guias_fundae_v2.py` | Apartado 6 de cronología por curso, `--update`. v2.1 sin horas de tutoría (10 comunicadas frente a 8 impartidas) | DriiveMe (W33, W35) |
| 15/10/2026 | copia T-189 | Saltos de línea literales, subtítulos y listas, `nota_objetivos`, cronología en tabla, botón del tema, `seccion`, borrado de la guía vieja, doble lectura del PDF | CENIEH 035–039 (W24–W38) |
| 26/10/2026 | v2.2 | DriiveMe: «Horas de tutoría: 10 = 8 por videoconferencia + 2 de atención en plataforma y revisión de entregables» (decisión de En) | DriiveMe, 21:01 |
| 26/10/2026 | v2.3 | Colores del portal | DriiveMe, 21:06 (revisión 4) |
| 27/10/2026 | **v4 (base)** | Guía única: apartados 10 y 11, dedicación y requisitos concretos; `base-aula.json` + personalización; ficha en `mdl_fundae_guia_didactica.ficha_json` y registro en `mdl_fundae_documentos`. Con `--sin-base`, las guías anteriores salen idénticas (CENIEH W38 y Lin3s v3: mismo md5 de HTML y mismo texto de PDF) | Lin3s 2.ª (00068–00072) |
| 26/10/2026 | **v3 (base)** | Base = copia T-189 de CENIEH + colores del portal + `--update`. Comprobado: con las fichas W38 de CENIEH el texto de los 5 PDF es idéntico al publicado (solo cambian los colores), y DriiveMe con `guias_fundae_v2.py` sobre la base sale igual que lo publicado (md5 del HTML 2500f124…) | Base para Lin3s y el resto |
