#!/usr/bin/env python3
"""guias_fundae.py — HERRAMIENTA BASE de guías didácticas FUNDAE (teleformación del aula), cualquier idioma y nivel.

Versión 3 (26/10/2026, T-306). Base = la copia T-189 con la que se hicieron las guías de CENIEH (15/10/2026, W24–W38):
respeta los saltos de línea del texto literal de FUNDAE (listas y subtítulos), nota bajo los objetivos, tabla de
cronología opcional, botón «Descargar» con estilo del tema, sección del curso y borrado de la guía vieja.
Añadido en v3:
  · colores del portal del aula (docs/ESTILO-PAGINAS-AULA.md del repo del aula): cabeceras #008ba3, etiquetas #e0f7fa,
    texto de color #006b7f (AZUL_OSC), bordes #dee2e6, pie #6c757d — los mismos que la guía de DriiveMe v2.3;
  · modo --update del PHP: sustituye HTML y PDF de la página que ya existe (mismo cm), sin crear otra.
Añadido en v4 (27/10/2026, T-306 / FUN-8, guía ÚNICA: ya no hay guía de la plataforma aparte):
  · apartados nuevos: «10. Cómo usar la plataforma» (`uso_plataforma`) y «11. Atención al usuario» (`atencion_usuario`);
    «Dedicación estimada» (`dedicacion`) en el apartado 5; requisitos técnicos concretos (`requisitos`). Anexo III.B Orden TMS/369/2019;
  · BASE COMÚN + PERSONALIZACIÓN: base-aula.json (junto a este fichero) lleva los textos comunes; la ficha de cada AF los
    sobrescribe (un dict como `tutoria` se mezcla clave a clave; una lista se sustituye entera; `_quitar: [claves]` quita
    apartados de la base en ese curso). `--base otra.json` o `--sin-base` para no usarla;
  · LA FICHA SE GUARDA EN LA BASE DE DATOS: el PHP, al crear (--apply) o actualizar (--update) la página, y con
    --registrar, guarda la ficha completa en mdl_fundae_guia_didactica.ficha_json (fila por c_fundae = «AF-grupo»; rellena
    también objetivo_general, criterio_evaluacion, tutor_fundae_nombre, herramienta_sincrona, pdf_path, estado) y registra
    la guía en mdl_fundae_documentos (tipo guia_didactica; la anterior de esa AF pasa a vigente = 0). --exportar la lee.
Sustituye a las versiones anteriores (v1 del 6/10 y copia T-189). guias_fundae_v2.py (DriiveMe) sigue funcionando: importa de aquí.

Una ficha JSON por acción formativa (AF) → PDF (Doc4, estilo tuSpeaking) + script PHP que publica en
el aula Moodle una Página con la guía en HTML y el PDF descargable dentro.

  python3 guias_fundae.py pdf    fichas/*.json -o salida/      # PDF + comprobación literal
  python3 guias_fundae.py moodle fichas/*.json -o salida/publicar-guias.php   # requiere los PDF en salida/
  (las dos aplican base-aula.json si está junto a este fichero; --sin-base para no aplicarla)

Reglas (no negociables):
  · objetivos y contenidos = texto LITERAL de la ficha de la AF comunicada a FUNDAE (se comprueba en el PDF);
  · solo se escribe lo que el curso cumple de verdad (controles, tutorías, materiales);
  · el PHP corre SIEMPRE como www-data (aula-php) y aborta si es root.
Requisitos: python3 + reportlab; fuentes DejaVu (Linux: /usr/share/fonts/truetype/dejavu).
"""
import argparse, base64, html, json, os, re, subprocess, sys

# ── Estilo tuSpeaking (mismo que el generador del aula gen_guia_didactica.py) ─────────────
# Colores del portal del aula (docs/ESTILO-PAGINAS-AULA.md, 26/10/2026). Antes: #1F4E78, #EBF3FB, #555555, #CCCCCC.
AZUL, AZUL_CL, GRIS, BORDE = '#008ba3', '#e0f7fa', '#6c757d', '#dee2e6'
AZUL_OSC = '#006b7f'   # texto de color sobre blanco o fondo suave (contraste AA)
PAT_OBJ = r'((?:Comprensión|Expresión|Competencia|Resultado esperado|Interacción|Mediación)[^:.;]{0,40}:)'
PAT_CON = r'((?:\d+\. )?[A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ ]{3,}[A-ZÁÉÍÓÚÑ]:)'
FONT_DIRS = ['/usr/share/fonts/truetype/dejavu/', '/Library/Fonts/', os.path.expanduser('~/Library/Fonts/')]

BASE_DEF = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'base-aula.json')

def mezclar(base, ficha):
    """Ficha sobre base: dict → clave a clave; lista o texto → lo de la ficha; `_quitar` borra claves de la base."""
    d = json.loads(json.dumps(base))
    for k in ficha.get('_quitar', []): d.pop(k, None)
    for k, v in ficha.items():
        if k == '_quitar': continue
        d[k] = {**d[k], **v} if isinstance(v, dict) and isinstance(d.get(k), dict) else v
    return d

def cargar(p, base=None):
    d = json.load(open(p, encoding='utf-8'))
    if base: d = mezclar({k: v for k, v in base.items() if not k.startswith('_')}, d)
    falta = [k for k in ('af', 'grupo', 'denominacion', 'horas', 'fecha_inicio', 'fecha_fin', 'empresa', 'tutor',
                         'objetivos', 'contenidos', 'evaluacion', 'cursos') if not d.get(k)]
    if falta: sys.exit(f'{p}: faltan campos {falta}')
    d.setdefault('modalidad', 'Teleformación')
    d.setdefault('entidad', 'Micro Ventures S.L. (tuSpeaking) · CIF B71259352')
    d.setdefault('plataforma', 'aula.tuspeaking.com')
    d.setdefault('pdf', 'Guia_Didactica_' + re.sub(r'[^A-Za-z0-9]+', '_', d['denominacion'].replace('ñ', 'n').replace('Ñ', 'N')).strip('_')
                 + f"_AF{d['af']}-{d['grupo']}.pdf")
    d.setdefault('titulo_pagina', f"Guía didáctica – {d['denominacion']} (AF {d['af']}-{d['grupo']})")
    return d

def filas_datos(d):
    f = [('Denominación', d['denominacion']),
         ('Acción formativa / grupo', f"{d['af']} / {d['grupo']}" + (f" (ID FUNDAE {d['id_fundae']})" if d.get('id_fundae') else '')),
         ('Modalidad', d['modalidad']), ('Duración', f"{d['horas']} horas"),
         ('Fechas', f"Del {d['fecha_inicio']} al {d['fecha_fin']}")]
    if d.get('area'): f.append(('Área profesional', d['area']))
    f += [('Empresa', d['empresa']), ('Entidad formadora', d['entidad']), ('Plataforma', d['plataforma']), ('Tutor-formador', d['tutor'])]
    return f

def filas_tutoria(d):
    t = d.get('tutoria', {})
    f = [('Tutor-formador', d['tutor'])]
    for k, et in (('horas', 'Horas de tutoría'), ('sesiones', 'Tutorías'), ('consultas', 'Consultas'), ('avisos', 'Avisos'),
                  ('horario', 'Horario de atención'), ('seguimiento', 'Seguimiento')):
        if t.get(k): f.append((et, t[k]))
    return f

def es_subtitulo(l):
    # Línea literal que hace de subtítulo: corta, sin guion inicial y sin punto final (o muy corta).
    return len(l) < 60 and not l.startswith('-') and (not l.endswith('.') or len(l) < 30)

def partir(texto, patron):
    # Texto con saltos de línea (copiado tal cual de FUNDAE): una línea = un párrafo, sin tocar el texto.
    if '\n' in texto:
        return [('', l.strip()) for l in texto.split('\n') if l.strip()]
    p = re.split(patron, texto)
    out = [('', p[0].strip())] if p[0].strip() else []
    for i in range(1, len(p), 2):
        out.append((p[i], p[i + 1].rstrip() if i + 1 < len(p) else ''))
    return out

# ── PDF ───────────────────────────────────────────────────────────────────────────────
def pdf(d, salida):
    from reportlab.lib.pagesizes import A4
    from reportlab.lib.units import cm
    from reportlab.lib.colors import HexColor, white
    from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, KeepTogether
    from reportlab.lib.styles import ParagraphStyle
    from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY
    from reportlab.pdfbase import pdfmetrics
    from reportlab.pdfbase.ttfonts import TTFont
    from reportlab.lib.fonts import addMapping
    fd = next((x for x in FONT_DIRS if os.path.exists(x + 'DejaVuSans.ttf')), None)
    if not fd: sys.exit('No encuentro DejaVuSans.ttf')
    for n, f in (('DV', 'DejaVuSans.ttf'), ('DVB', 'DejaVuSans-Bold.ttf'), ('DVI', 'DejaVuSans-Oblique.ttf')):
        pdfmetrics.registerFont(TTFont(n, fd + f))
    addMapping('DV', 0, 0, 'DV'); addMapping('DV', 1, 0, 'DVB'); addMapping('DV', 0, 1, 'DVI'); addMapping('DV', 1, 1, 'DVB')
    A, AC, G = HexColor(AZUL), HexColor(AZUL_CL), HexColor(GRIS)
    AO = HexColor(AZUL_OSC)
    T = ParagraphStyle('t', fontName='DVB', fontSize=17, textColor=AO, alignment=TA_CENTER, spaceAfter=4, leading=21)
    S = ParagraphStyle('s', fontName='DV', fontSize=10.5, textColor=G, alignment=TA_CENTER, leading=14)
    H = ParagraphStyle('h', fontName='DVB', fontSize=10.5, textColor=white)
    B = ParagraphStyle('b', fontName='DV', fontSize=9.2, leading=13, spaceAfter=5, alignment=TA_JUSTIFY)
    BU = ParagraphStyle('bu', parent=B, leftIndent=14, firstLineIndent=-9, spaceAfter=3, alignment=0)
    K = ParagraphStyle('k', fontName='DVB', fontSize=8.8, textColor=AO, leading=12)
    V = ParagraphStyle('v', fontName='DV', fontSize=8.8, leading=12)
    FT = ParagraphStyle('f', fontName='DVI', fontSize=7, textColor=G, alignment=TA_CENTER)
    e = lambda t: html.escape(t, quote=False)
    def hdr(t):
        x = Table([[Paragraph(t, H)]], colWidths=[17 * cm])
        x.setStyle(TableStyle([('BACKGROUND', (0, 0), (-1, -1), A), ('TOPPADDING', (0, 0), (-1, -1), 6),
                               ('BOTTOMPADDING', (0, 0), (-1, -1), 6), ('LEFTPADDING', (0, 0), (-1, -1), 9)]))
        return x
    def kv(rows):
        x = Table([[Paragraph(e(k), K), Paragraph(e(v), V)] for k, v in rows], colWidths=[5.2 * cm, 11.8 * cm])
        x.setStyle(TableStyle([('BACKGROUND', (0, 0), (0, -1), AC), ('GRID', (0, 0), (-1, -1), 0.5, HexColor(BORDE)),
                               ('VALIGN', (0, 0), (-1, -1), 'TOP'), ('TOPPADDING', (0, 0), (-1, -1), 4),
                               ('BOTTOMPADDING', (0, 0), (-1, -1), 4), ('LEFTPADDING', (0, 0), (-1, -1), 7)]))
        return x
    bl = lambda t: Paragraph('•&nbsp;&nbsp;' + t, BU)
    BL = ParagraphStyle('bl', parent=B, leftIndent=12, spaceAfter=2, alignment=0)
    BS = ParagraphStyle('bs', parent=B, fontName='DVB', spaceBefore=4, spaceAfter=2, alignment=0)
    BN = ParagraphStyle('bn', parent=B, spaceAfter=2, alignment=0)
    def par(partes):
        out = []
        for k, v in partes:
            if k: out.append(Paragraph(f'<b>{e(k)}</b>' + e(v), B))
            elif v.startswith('-'): out.append(Paragraph(e(v), BL))
            elif es_subtitulo(v): out.append(Paragraph(e(v), BS))
            else: out.append(Paragraph(e(v), BN))
        return out
    s = [Paragraph('GUÍA DIDÁCTICA', T), Paragraph(e(d['denominacion']), T),
         Paragraph(f"Acción formativa {d['af']} · Grupo {d['grupo']} · {e(d['modalidad'])}", S), Spacer(1, 10),
         hdr('1. Datos de la acción formativa'), Spacer(1, 4), kv(filas_datos(d)), Spacer(1, 10),
         hdr('2. Destinatarios y acceso'), Spacer(1, 4)] + [Paragraph(x, B) for x in d.get('destinatarios', [])] + [Spacer(1, 6),
         hdr('3. Objetivos'), Spacer(1, 4)] + par(partir(d['objetivos'], d.get('patron_objetivos', PAT_OBJ))) + ([Spacer(1, 4), Paragraph(f"<i>{e(d['nota_objetivos'])}</i>", B)] if d.get('nota_objetivos') else []) + [Spacer(1, 6),
         hdr('4. Contenidos'), Spacer(1, 4)] + par(partir(d['contenidos'], d.get('patron_contenidos', PAT_CON))) + [Spacer(1, 6),
         hdr('5. Metodología y funcionamiento del curso'), Spacer(1, 4)]
    s += [Paragraph(x, B) for x in d.get('metodologia_intro', [])] + [bl(x) for x in d.get('metodologia', [])] + [Spacer(1, 6)]
    if d.get('dedicacion'): s += [Paragraph(f"<b>Dedicación estimada:</b> {d['dedicacion']}", B), Spacer(1, 6)]
    cr = d.get('cronologia')
    if cr:
        TH = ParagraphStyle('th', fontName='DVB', fontSize=8, textColor=white, leading=10)
        TD = ParagraphStyle('td', fontName='DV', fontSize=8, leading=10)
        rows = [[Paragraph(e(c), TH) for c in cr['cabecera']]] + [[Paragraph(e(str(c)), TD) for c in f] for f in cr['filas']]
        x = Table(rows, colWidths=[w * cm for w in cr['anchos']], repeatRows=1)
        x.setStyle(TableStyle([('BACKGROUND', (0, 0), (-1, 0), A), ('GRID', (0, 0), (-1, -1), 0.5, HexColor(BORDE)),
                               ('VALIGN', (0, 0), (-1, -1), 'TOP'), ('TOPPADDING', (0, 0), (-1, -1), 3), ('BOTTOMPADDING', (0, 0), (-1, -1), 3),
                               ('LEFTPADDING', (0, 0), (-1, -1), 4), ('RIGHTPADDING', (0, 0), (-1, -1), 4)]))
        s += [Paragraph(f"<b>{e(cr['titulo'])}</b>", B), x] + [Paragraph(n, B) for n in cr.get('notas', [])] + [Spacer(1, 6)]
    s += [KeepTogether([hdr('6. Tutoría y seguimiento'), Spacer(1, 4), kv(filas_tutoria(d)), Spacer(1, 10)])]
    ev = d['evaluacion']
    s += [KeepTogether([hdr('7. Evaluación'), Spacer(1, 4)] + [Paragraph(f'<b>{k}:</b> {v}', B) for k, v in ev] + [Spacer(1, 6)])]
    s += [KeepTogether([hdr('8. Recursos y materiales'), Spacer(1, 4)] + [bl(x) for x in d.get('recursos', [])] + [Spacer(1, 6)])]
    s += [KeepTogether([hdr('9. Requisitos técnicos'), Spacer(1, 4)] + [bl(x) for x in d.get('requisitos', [])] + [Spacer(1, 10)])]
    if d.get('uso_plataforma'):
        s += [hdr('10. Cómo usar la plataforma'), Spacer(1, 4)] + [Paragraph(f'<b>{k}:</b> {v}', B) for k, v in d['uso_plataforma']] + [Spacer(1, 10)]
    if d.get('atencion_usuario'):
        s += [KeepTogether([hdr('11. Atención al usuario (soporte técnico)'), Spacer(1, 4), kv(d['atencion_usuario']), Spacer(1, 14)])]
    s += [Paragraph(f"{e(d['entidad'])} · Guía didáctica de la acción formativa {d['af']}-{d['grupo']} «{e(d['denominacion'])}»", FT)]
    SimpleDocTemplate(salida, pagesize=A4, leftMargin=2 * cm, rightMargin=2 * cm, topMargin=1.6 * cm, bottomMargin=1.6 * cm,
                      title=d['titulo_pagina'], author=d['entidad']).build(s)

def comprobar_literal(d, ruta):
    norm = lambda t: re.sub(r'\s+', ' ', t).strip()
    # Dos lecturas: la normal une «-» a final de línea con la siguiente (p. ej. «- ably» → «ably»); -layout no.
    txts = [norm(subprocess.run(['pdftotext'] + m + [ruta, '-'], capture_output=True, text=True).stdout) for m in ([], ['-layout'])]
    mal = [k for k in ('objetivos', 'contenidos') if not any(norm(d[k]) in x for x in txts)]
    return mal

# ── HTML para la Página de Moodle ──────────────────────────────────────────────────────
def html_pagina(d):
    e = html.escape
    h = lambda t: f'<h3 style="background:{AZUL};color:#fff;padding:6px 10px;margin-top:18px">{t}</h3>'
    tabla = lambda rows: ('<table style="border-collapse:collapse;width:100%">' + ''.join(
        f'<tr><th style="text-align:left;background:{AZUL_CL};padding:4px 8px;border:1px solid {BORDE};width:30%">{e(k)}</th>'
        f'<td style="padding:4px 8px;border:1px solid {BORDE}">{e(v)}</td></tr>' for k, v in rows) + '</table>')
    def ps(partes):
        out = []
        for k, v in partes:
            if k: out.append(f'<p><strong>{e(k)}</strong>{e(v)}</p>')
            elif v.startswith('-'): out.append(f'<p style="margin:0 0 2px 14px">{e(v)}</p>')
            elif es_subtitulo(v): out.append(f'<p style="margin:8px 0 2px"><strong>{e(v)}</strong></p>')
            else: out.append(f'<p style="margin:0 0 2px">{e(v)}</p>')
        return '\n'.join(out)
    ul = lambda xs: '<ul>' + ''.join(f'<li>{x}</li>' for x in xs) + '</ul>' if xs else ''
    def tabla_cr(cr):
        if not cr: return ''
        td = f'padding:3px 6px;border:1px solid {BORDE};vertical-align:top'
        return (f'<p><strong>{e(cr["titulo"])}</strong></p><table style="border-collapse:collapse;width:100%;font-size:0.9em"><tr>'
                + ''.join(f'<th style="{td};background:{AZUL};color:#fff;text-align:left">{e(c)}</th>' for c in cr['cabecera']) + '</tr>'
                + ''.join('<tr>' + ''.join(f'<td style="{td}">{e(str(c))}</td>' for c in f) + '</tr>' for f in cr['filas']) + '</table>'
                + ''.join(f'<p>{n}</p>' for n in cr.get('notas', [])))
    # v4: los apartados nuevos que no estén en la ficha dan None y no se escriben (así las guías anteriores no cambian ni un byte)
    return '\n'.join(x for x in [
        f'<p><a class="btn btn-primary" href="@@PLUGINFILE@@/{d["pdf"]}">&#11015; Descargar la guía didáctica en PDF</a></p>',
        f"<p><em>Acción formativa {d['af']} · Grupo {d['grupo']} · {e(d['modalidad'])}</em></p>",
        h('1. Datos de la acción formativa'), tabla(filas_datos(d)),
        h('2. Destinatarios y acceso'), ''.join(f'<p>{x}</p>' for x in d.get('destinatarios', [])),
        h('3. Objetivos'), ps(partir(d['objetivos'], d.get('patron_objetivos', PAT_OBJ))),
        f'<p style="margin-top:8px"><em>{e(d["nota_objetivos"])}</em></p>' if d.get('nota_objetivos') else '',
        h('4. Contenidos'), ps(partir(d['contenidos'], d.get('patron_contenidos', PAT_CON))),
        h('5. Metodología y funcionamiento del curso'), ''.join(f'<p>{x}</p>' for x in d.get('metodologia_intro', [])), ul(d.get('metodologia', [])),
        f"<p><strong>Dedicación estimada:</strong> {d['dedicacion']}</p>" if d.get('dedicacion') else None,
        tabla_cr(d.get('cronologia')),
        h('6. Tutoría y seguimiento'), tabla(filas_tutoria(d)),
        h('7. Evaluación'), ''.join(f'<p><strong>{k}:</strong> {v}</p>' for k, v in d['evaluacion']),
        h('8. Recursos y materiales'), ul(d.get('recursos', [])),
        h('9. Requisitos técnicos'), ul(d.get('requisitos', [])),
        (h('10. Cómo usar la plataforma') + ''.join(f'<p><strong>{e(k)}:</strong> {v}</p>' for k, v in d['uso_plataforma'])) if d.get('uso_plataforma') else None,
        (h('11. Atención al usuario (soporte técnico)') + tabla(d['atencion_usuario'])) if d.get('atencion_usuario') else None,
        f'<p style="font-size:0.85em;color:{GRIS}"><em>{e(d["entidad"])} · Guía didáctica de la acción formativa {d["af"]}-{d["grupo"]} «{e(d["denominacion"])}»</em></p>'] if x is not None)

PHP_HEAD = r"""<?php
// Publica guías didácticas FUNDAE en el aula: una Página por curso con la guía en HTML y el PDF descargable dentro.
// Generado por guias_fundae.py. Uso SIEMPRE como www-data:
//   aula-php /tmp/SCRIPT.php            → ensayo (no escribe)
//   aula-php /tmp/SCRIPT.php --apply    → crea las páginas (y después borra los recursos viejos indicados)
//   aula-php /tmp/SCRIPT.php --revert   → borra las páginas creadas (no restaura lo borrado)
//   aula-php /tmp/SCRIPT.php --ensayo-update → ensayo del cambio: md5 del HTML y sha1 del PDF de antes y de después (no escribe)
//   aula-php /tmp/SCRIPT.php --update   → sustituye HTML y PDF de la página que YA existe (mismo cm, misma posición); no crea ni borra nada más
//   Antes de --update: comprobar con Q75 (MD5 de mdl_page.content) que la página publicada no se ha editado a mano.
//   aula-php /tmp/SCRIPT.php --ensayo-registrar → enseña qué guardaría en la base de datos (no escribe)
//   aula-php /tmp/SCRIPT.php --registrar → solo guarda la ficha en mdl_fundae_guia_didactica.ficha_json y registra la guía en
//                                          mdl_fundae_documentos, para páginas que YA existen (no toca la página)
//   aula-php /tmp/SCRIPT.php --exportar  → escribe la ficha guardada en la base de datos de cada AF (no escribe nada)
//   --apply (al crear) y --update guardan también la ficha en la base de datos (v4, FUN-8).
define('CLI_SCRIPT', true);
if (function_exists('posix_geteuid') && posix_geteuid() === 0) { fwrite(STDERR, "ABORTA: no se ejecuta como root. Usa aula-php.\n"); exit(1); }
foreach ([getcwd() . '/config.php', '/var/www/html/app/moodle/config.php', '/var/www/html/config.php'] as $cfg) {
    if (is_readable($cfg)) { require($cfg); break; }
}
if (empty($CFG)) { fwrite(STDERR, "No encuentro config.php de Moodle\n"); exit(1); }
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/resourcelib.php');
\core\session\manager::set_user(get_admin());
$mode = in_array('--apply', $argv) ? 'apply' : (in_array('--revert', $argv) ? 'revert' : (in_array('--update', $argv) ? 'update'
      : (in_array('--registrar', $argv) ? 'registrar' : (in_array('--exportar', $argv) ? 'exportar' : 'ensayo'))));
echo "Modo: $mode · usuario PHP: " . (function_exists('posix_geteuid') ? posix_getpwuid(posix_geteuid())['name'] : '?') . "\n";
$PDF = []; $HTML = []; $G = []; $F = [];
"""

PHP_TAIL = r"""
// ── v4 (FUN-8): la ficha completa de la AF en la base de datos (fila por c_fundae) + registro en mdl_fundae_documentos ──
// SQL directo con parámetros: son tablas propias (no de Moodle) con columnas ENUM y ficha_json nueva.
$T306_HECHO = [];
function t306_guardar_bd($cf, $ficha_json, $cmid, $pdfname, $escribir) {
    global $DB, $CFG, $T306_HECHO;
    if (!$cf || $ficha_json === null) { return; }   // guías generadas sin ficha (p. ej. guias_fundae_v2.py)
    if (!$DB->get_records_sql("SHOW COLUMNS FROM {fundae_guia_didactica} LIKE 'ficha_json'")) { echo "  BD: falta la columna ficha_json (t306-11): no se guarda\n"; return; }
    $f = json_decode($ficha_json, true); $now = time();
    $crit = null; foreach (($f['evaluacion'] ?? []) as $r) { if (stripos($r[0], 'criterio') !== false) { $crit = trim(strip_tags($r[1])); } }
    $herr = strpos(json_encode($f['tutoria'] ?? []), 'Teams') !== false ? 'Teams' : 'Zoom';
    $url = $CFG->wwwroot . '/mod/page/view.php?id=' . $cmid;
    $fila = $DB->get_record_sql("SELECT id, estado FROM {fundae_guia_didactica} WHERE c_fundae = ?", [$cf]);
    $cif = $DB->get_field_sql("SELECT MAX(cif) FROM {fundae} WHERE c_fundae = ?", [$cf]);
    echo "  BD: " . ($fila ? "actualiza la fila {$fila->id} (estado {$fila->estado})" : "crea la fila") . " de {$cf} en mdl_fundae_guia_didactica (ficha_json " . strlen($ficha_json) . " bytes)"
       . " · registra «{$pdfname}» en mdl_fundae_documentos (" . (isset($T306_HECHO[$cf]) ? 'otro curso de la misma AF' : 'la anterior de esa AF pasa a vigente = 0') . ", CIF " . ($cif ?: '-') . ")\n";
    if (!$escribir) return;
    $v = [$ficha_json, $f['objetivos'] ?? null, $crit, $f['tutor'] ?? null, $herr, $url, date('Y-m-d H:i:s'), $now];
    if ($fila) {
        $DB->execute("UPDATE {fundae_guia_didactica} SET ficha_json = ?, objetivo_general = ?, criterio_evaluacion = ?, tutor_fundae_nombre = ?,
            herramienta_sincrona = ?, pdf_path = ?, pdf_generado_at = ?, estado = 'generado', timemodified = ? WHERE id = ?", array_merge($v, [$fila->id]));
    } else {
        $DB->execute("INSERT INTO {fundae_guia_didactica} (ficha_json, objetivo_general, criterio_evaluacion, tutor_fundae_nombre, herramienta_sincrona,
            pdf_path, pdf_generado_at, estado, timemodified, c_fundae, timecreated) VALUES (?, ?, ?, ?, ?, ?, ?, 'generado', ?, ?, ?)", array_merge($v, [$cf, $now]));
    }
    if (!isset($T306_HECHO[$cf])) {
        $DB->execute("UPDATE {fundae_documentos} SET vigente = 0 WHERE tipo_documento = 'guia_didactica' AND c_fundae = ?", [$cf]);
        $T306_HECHO[$cf] = 1;
    }
    $DB->execute("INSERT INTO {fundae_documentos} (tipo_documento, c_fundae, cif_empresa, user_id, path_archivo, nombre_archivo, fecha_documento,
        reutilizable, vigente, timecreated) VALUES ('guia_didactica', ?, ?, NULL, ?, ?, ?, 0, 1, ?)", [$cf, $cif ?: null, $url, $pdfname, date('Y-m-d'), $now]);
    echo "  BD: GUARDADA la ficha de {$cf} y registrada la guía\n";
}
if ($mode === 'exportar') {
    foreach (array_unique(array_map(function ($g) { return $g['cf']; }, $G)) as $cf) {
        $j = $DB->get_field_sql("SELECT ficha_json FROM {fundae_guia_didactica} WHERE c_fundae = ?", [$cf]);
        echo "== {$cf}\n" . ($j ? json_encode(json_decode($j), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '(sin ficha guardada)') . "\n";
    }
    exit(0);
}
foreach ($G as $courseid => $g) {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    echo "\n== Curso $courseid · {$course->fullname}\n";
    $existing = $DB->get_records_sql("SELECT cm.id, p.name FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module AND m.name = 'page'
        JOIN {page} p ON p.id = cm.instance WHERE cm.course = ? AND p.name = ? AND cm.deletioninprogress = 0", [$courseid, $g['name']]);
    if ($mode === 'revert') {
        foreach ($existing as $x) { course_delete_module($x->id); echo "  BORRADA página cm {$x->id}\n"; }
        if (!$existing) echo "  (no hay página que borrar)\n";
        continue;
    }
    if ($existing) { echo "  YA EXISTE la página «{$g['name']}» (cm " . implode(',', array_keys($existing)) . ") → no se crea otra" . (count($existing) === 1 ? ' (--update la sustituye)' : '') . "\n"; }
    if ($mode === 'registrar' || in_array('--ensayo-registrar', $argv)) {
        if (count($existing) !== 1) { echo "  --registrar: hace falta exactamente UNA página «{$g['name']}» (hay " . count($existing) . ")\n"; continue; }
        t306_guardar_bd($g['cf'] ?? null, $F[$g['cf'] ?? ''] ?? null, (int)array_key_first_t306($existing), $g['pdf'], $mode === 'registrar');
        continue;
    }
    if ($mode === 'update' || in_array('--ensayo-update', $argv)) {
        if (count($existing) !== 1) { echo "  --update: hace falta exactamente UNA página «{$g['name']}» en el curso (hay " . count($existing) . "): no se toca\n"; continue; }
        $x = reset($existing); $ctx = context_module::instance($x->id); $fs = get_file_storage();
        $act = array_values(array_filter($fs->get_area_files($ctx->id, 'mod_page', 'content', 0, 'id', false), function ($f) { return $f->get_filename() !== '.'; }));
        $pdf = base64_decode($PDF[$g['pdf']]); $page = $DB->get_record('page', ['id' => $DB->get_field('course_modules', 'instance', ['id' => $x->id])], '*', MUST_EXIST);
        echo "  --update cm {$x->id}: HTML md5 " . md5($page->content) . " → " . md5($HTML[$g['pdf']]) . " · PDF " . ($act ? $act[0]->get_filename() . ' sha1 ' . $act[0]->get_contenthash() : '(ninguno)') . " → {$g['pdf']} sha1 " . sha1($pdf) . "\n";
        if ($mode !== 'update') { t306_guardar_bd($g['cf'] ?? null, $F[$g['cf'] ?? ''] ?? null, (int)$x->id, $g['pdf'], false); continue; }
        $page->content = $HTML[$g['pdf']]; $page->contentformat = FORMAT_HTML; $page->revision++; $page->timemodified = time();
        $DB->update_record('page', $page);
        $fs->delete_area_files($ctx->id, 'mod_page', 'content');
        $fs->create_file_from_string(['contextid' => $ctx->id, 'component' => 'mod_page', 'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => $g['pdf']], $pdf);
        rebuild_course_cache($courseid, true);
        echo "  ACTUALIZADA (revisión {$page->revision}) → {$CFG->wwwroot}/mod/page/view.php?id={$x->id}\n";
        t306_guardar_bd($g['cf'] ?? null, $F[$g['cf'] ?? ''] ?? null, (int)$x->id, $g['pdf'], true);
        continue;
    }
    $viejos = [];
    foreach ($g['borrar'] as $like) {
        $viejos += $DB->get_records_sql("SELECT cm.id, r.name FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module AND m.name = 'resource'
            JOIN {resource} r ON r.id = cm.instance WHERE cm.course = ? AND r.name LIKE ? AND cm.deletioninprogress = 0", [$courseid, $like]);
    }
    foreach ($viejos as $v) echo "  " . ($mode === 'apply' ? 'Se borrará (tras crear la guía)' : 'Borraría') . " recurso cm {$v->id} «{$v->name}»\n";
    $secnum = (int)($g['seccion'] ?? 0);
    $sec0 = $DB->get_record('course_sections', ['course' => $courseid, 'section' => $secnum], '*', MUST_EXIST);
    $seq = array_values(array_filter(explode(',', (string)$sec0->sequence)));
    $antes = null; // si una guía vieja a borrar está en esa sección, la nueva ocupa su sitio
    foreach ($viejos as $v) { if ((int)$DB->get_field('course_modules', 'section', ['id' => $v->id]) === (int)$sec0->id) { $antes = (int)$v->id; break; } }
    $pdf = base64_decode($PDF[$g['pdf']]);
    $donde = $secnum === 0 ? "en el tema 0, después del primer elemento (cm " . ($seq[0] ?? '-') . ")"
        : "en la sección $secnum «" . get_section_name($course, $sec0) . "», " . ($antes ? "en el sitio de la guía vieja (cm $antes)" : "al final");
    echo "  Página «{$g['name']}» $donde, con el PDF {$g['pdf']} (" . strlen($pdf) . " bytes)\n";
    if ($mode !== 'apply') continue;
    if (!$existing) {
        $mi = new stdClass();
        $mi->modulename = 'page'; $mi->course = $courseid; $mi->section = $secnum; $mi->visible = 1; $mi->visibleoncoursepage = 1;
        $mi->name = $g['name']; $mi->introeditor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()]; $mi->showdescription = 0;
        $mi->content = $HTML[$g['pdf']]; $mi->contentformat = FORMAT_HTML;
        $mi->display = RESOURCELIB_DISPLAY_AUTO; $mi->printheading = 1; $mi->printintro = 0; $mi->printlastmodified = 0;
        $mi->cmidnumber = ''; $mi->groupmode = 0; $mi->groupingid = 0; $mi->completion = 0;
        $mi = create_module($mi);
        $ctx = context_module::instance($mi->coursemodule);
        get_file_storage()->create_file_from_string(['contextid' => $ctx->id, 'component' => 'mod_page', 'filearea' => 'content', 'itemid' => 0,
            'filepath' => '/', 'filename' => $g['pdf']], $pdf);
        $sec0 = $DB->get_record('course_sections', ['course' => $courseid, 'section' => $secnum], '*', MUST_EXIST);
        $seq = array_values(array_filter(explode(',', (string)$sec0->sequence), function ($x) use ($mi) { return (int)$x !== (int)$mi->coursemodule; }));
        $cmnuevo = $DB->get_record('course_modules', ['id' => $mi->coursemodule], '*', MUST_EXIST);
        if ($antes) { moveto_module($cmnuevo, $sec0, $antes); }
        else if ($secnum === 0 && count($seq) >= 2) { moveto_module($cmnuevo, $sec0, (int)$seq[1]); }
        echo "  CREADA cm {$mi->coursemodule} → {$CFG->wwwroot}/mod/page/view.php?id={$mi->coursemodule}\n";
        t306_guardar_bd($g['cf'] ?? null, $F[$g['cf'] ?? ''] ?? null, (int)$mi->coursemodule, $g['pdf'], true);
    }
    foreach ($viejos as $v) { course_delete_module($v->id); echo "  BORRADO recurso cm {$v->id}\n"; }
    rebuild_course_cache($courseid, true);
}
echo "\nFin ($mode).\n";
function array_key_first_t306($a) { foreach ($a as $k => $x) { return $k; } return null; }
"""

def php(fichas, dir_pdf, salida):
    def nowdoc(s):
        assert '\nTXT;' not in s
        return f"<<<'TXT'\n{s}\nTXT"
    q = lambda s: "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"
    out = [PHP_HEAD]
    for d in fichas:
        ruta = os.path.join(dir_pdf, d['pdf'])
        if not os.path.exists(ruta): sys.exit(f'Falta el PDF {ruta}: ejecuta antes «pdf»')
        out.append(f"$PDF[{q(d['pdf'])}] = '{base64.b64encode(open(ruta, 'rb').read()).decode()}';\n")
        out.append(f"$HTML[{q(d['pdf'])}] = {nowdoc(html_pagina(d))};\n")
        cf = f"{d['af']}-{d['grupo']}"
        out.append(f"$F[{q(cf)}] = {nowdoc(json.dumps(d, ensure_ascii=False, sort_keys=True))};\n")
        for c in d['cursos']:
            borrar = ', '.join(q(x) for x in d.get('borrar_recursos', {}).get(str(c), []))
            out.append(f"$G[{int(c)}] = ['name' => {q(d['titulo_pagina'])}, 'pdf' => {q(d['pdf'])}, 'borrar' => [{borrar}], 'seccion' => {int(d.get('seccion', 0))}, 'cf' => {q(cf)}];\n")
    out.append(PHP_TAIL)
    open(salida, 'w', encoding='utf-8').write(''.join(out))

def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('accion', choices=['pdf', 'moodle'])
    ap.add_argument('fichas', nargs='+')
    ap.add_argument('-o', '--salida', required=True)
    ap.add_argument('--base', default=BASE_DEF, help='textos comunes (por defecto base-aula.json junto a este fichero)')
    ap.add_argument('--sin-base', action='store_true', help='no aplicar textos comunes (guías anteriores a v4)')
    a = ap.parse_args()
    base = None if a.sin_base or not os.path.exists(a.base) else json.load(open(a.base, encoding='utf-8'))
    if base: print(f"base común: {a.base}", file=sys.stderr)
    fichas = [cargar(p, base) for p in a.fichas]
    if a.accion == 'pdf':
        os.makedirs(a.salida, exist_ok=True)
        err = 0
        for d in fichas:
            ruta = os.path.join(a.salida, d['pdf']); pdf(d, ruta)
            mal = comprobar_literal(d, ruta)
            print(f"{ruta}  ·  literal: {'OK' if not mal else 'FALLA ' + ','.join(mal)}"); err += bool(mal)
        sys.exit(1 if err else 0)
    php(fichas, os.path.dirname(os.path.abspath(a.salida)), a.salida)
    print(a.salida)

if __name__ == '__main__':
    main()
