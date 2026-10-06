# Guías didácticas FUNDAE (teleformación) — `tus-tools/guias-didacticas/`

Genera la **guía didáctica** de una acción formativa (AF) de teleformación para cualquier idioma y nivel, y la
**publica en el curso del aula** como Página de Moodle, con la guía en HTML y el PDF descargable dentro.
Primer uso: DriiveMe Español 112–114 (6/10/2026, T-112). El procedimiento completo está en la skill
`guias-didacticas-fundae`.

## Reglas
1. **Objetivos y contenidos = texto literal de la ficha de la AF comunicada a FUNDAE.** El script lo comprueba
   en el PDF; si no coincide, falla. No se resume, no se corrige y no se completa.
2. **Solo se escribe lo que el curso cumple de verdad.** Primero se audita el curso del aula (controles,
   tutorías, materiales, foros) y la guía describe eso. No se ponen autocorregidos, test final ni horas que no
   existan.
3. **Controles de aprendizaje:** en el aula, los que haya de verdad. Es la regla FUNDAE-21d: el autocorregido
   que cierra cada lección. Si el curso no tiene autocorregidos (por ejemplo, el español de Álvaro), son las
   actividades entregables calificadas por el tutor (decisión de En del 6/10/2026). Criterio: realizar al menos
   el 75 % de los controles. En Learn, la guía es «Mi plan de aprendizaje» (`NORMA-FUNDAE-GUIA-DIDACTICA.md`).
4. El PHP de publicación corre **siempre como www-data** (`aula-php`) y aborta si es root.

## Ficha (una por AF): `fichas-<cliente>-<año>/afNNN-GG.json`
Campos obligatorios: `af`, `grupo`, `denominacion`, `horas`, `fecha_inicio`, `fecha_fin`, `empresa`, `tutor`,
`objetivos`, `contenidos`, `evaluacion`, `cursos`.
Campos opcionales: `id_fundae`, `area`, `modalidad` (Teleformación), `entidad`, `plataforma`, `destinatarios[]`,
`metodologia_intro[]`, `metodologia[]`, `tutoria{horas, sesiones, consultas, avisos, horario, seguimiento}`,
`recursos[]`, `requisitos[]`, `borrar_recursos{"<courseid>": ["nombre LIKE%"]}`, `patron_objetivos`,
`patron_contenidos`. Ejemplo completo en `fichas-driiveme-2026/`.

| Dato | De dónde sale |
|---|---|
| Objetivos y contenidos | Aplicación de FUNDAE → Formación → Acciones formativas → la AF → pestaña **Descripción** (copiar el texto). Si la AF se dio de alta desde Learn, también están en `gestion_fundae_acciones.objetivos_formativos` y `contenidos_formativos` |
| Denominación, horas, área, URL e inspector | Listado de FUNDAE → «Exportar Excel» (`AccionesFormativas.xls`) |
| Fechas, tutor, horas de tutoría y horario | Ficha del grupo en FUNDAE (pestañas Descripción y Tutores/Centros) |
| Metodología, recursos, controles | Auditoría del curso del aula (consultas de la skill) |

## Uso
```bash
pip install reportlab                     # una vez; necesita las fuentes DejaVu y pdftotext (poppler)
python3 guias_fundae.py pdf    fichas-cliente-2026/*.json -o salida/
python3 guias_fundae.py moodle fichas-cliente-2026/*.json -o salida/publicar-guias.php
```
Publicación en el servidor del aula: ver la skill (scp → `docker cp` → `chmod 644` → `aula-php` ensayo →
`--apply` → `purge_caches` → borrar el script).

## Estilo
- Cabeceras de apartado en azul `#1F4E78` con texto blanco; columna de etiquetas en `#EBF3FB`; bordes `#CCCCCC`;
  pie en gris `#555555`. Es el mismo estilo de `blocks/fundae/gen_guia_didactica.py`.
- Fuente DejaVu Sans, cuerpo de 9,2 pt, A4 con márgenes de 2 cm.
- **9 apartados** (Orden TMS/369/2019, Anexo III, «Guía del alumno»): datos de la acción · destinatarios y
  acceso · objetivos · contenidos · metodología · tutoría y seguimiento · evaluación · recursos · requisitos
  técnicos.
