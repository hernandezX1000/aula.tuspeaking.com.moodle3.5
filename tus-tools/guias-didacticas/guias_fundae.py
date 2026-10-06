#!/usr/bin/env python3
"""guias_fundae.py — Guías didácticas FUNDAE (teleformación) para cualquier idioma y nivel.

Una ficha JSON por acción formativa (AF) → PDF (Doc4, estilo tuSpeaking) + script PHP que publica en
el aula Moodle una Página con la guía en HTML y el PDF descargable dentro.

  python3 guias_fundae.py pdf    fichas/*.json -o salida/      # PDF + comprobación literal
  python3 guias_fundae.py moodle fichas/*.json -o salida/publicar-guias.php   # requiere los PDF en salida/

Reglas (no negociables):
  · objetivos y contenidos = texto LITERAL de la ficha de la AF comunicada a FUNDAE (se comprueba en el PDF);
  · solo se escribe lo que el curso cumple de verdad (controles, tutorías, materiales);
  · el PHP corre SIEMPRE como www-data (aula-php) y aborta si es root.
Requisitos: python3 + reportlab; fuentes DejaVu (Linux: /usr/share/fonts/truetype/dejavu).
"""
import argparse, base64, html, json, os, re, subprocess, sys

# ── Estilo tuSpeaking (mismo que el generador del aula gen_guia_didactica.py) ─────────────
AZUL, AZUL_CL, GRIS, BORDE = '#1F4E78', '#EBF3FB', '#555555', '#CCCCCC'
PAT_OBJ = r'((?:Comprensión|Expresión|Competencia|Resultado esperado|Interacción|Mediación)[^:.;]{0,40}:)'
PAT_CON = r'((?:\d+\. )?[A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ ]{3,}[A-ZÁÉÍÓÚÑ]:)'
FONT_DIRS = ['/usr/share/fonts/truetype/dejavu/', '/Library/Fonts/', os.path.expanduser('~/Library/Fonts/')]

def cargar(p):
    d = json.load(open(p, encoding='utf-8'))
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

def partir(texto, patron):
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
    T = ParagraphStyle('t', fontName='DVB', fontSize=17, textColor=A, alignment=TA_CENTER, spaceAfter=4, leading=21)
    S = ParagraphStyle('s', fontName='DV', fontSize=10.5, textColor=G, alignment=TA_CENTER, leading=14)
    H = ParagraphStyle('h', fontName='DVB', fontSize=10.5, textColor=white)
    B = ParagraphStyle('b', fontName='DV', fontSize=9.2, leading=13, spaceAfter=5, alignment=TA_JUSTIFY)
    BU = ParagraphStyle('bu', parent=B, leftIndent=14, firstLineIndent=-9, spaceAfter=3, alignment=0)
    K = ParagraphStyle('k', fontName='DVB', fontSize=8.8, textColor=A, leading=12)
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
    par = lambda partes: [Paragraph((f'<b>{e(k)}</b>' if k else '') + e(v), B) for k, v in partes]
    s = [Paragraph('GUÍA DIDÁCTICA', T), Paragraph(e(d['denominacion']), T),
         Paragraph(f"Acción formativa {d['af']} · Grupo {d['grupo']} · {e(d['modalidad'])}", S), Spacer(1, 10),
         hdr('1. Datos de la acción formativa'), Spacer(1, 4), kv(filas_datos(d)), Spacer(1, 10),
         hdr('2. Destinatarios y acceso'), Spacer(1, 4)] + [Paragraph(x, B) for x in d.get('destinatarios', [])] + [Spacer(1, 6),
         hdr('3. Objetivos'), Spacer(1, 4)] + par(partir(d['objetivos'], d.get('patron_objetivos', PAT_OBJ))) + [Spacer(1, 6),
         hdr('4. Contenidos'), Spacer(1, 4)] + par(partir(d['contenidos'], d.get('patron_contenidos', PAT_CON))) + [Spacer(1, 6),
         hdr('5. Metodología y funcionamiento del curso'), Spacer(1, 4)]
    s += [Paragraph(x, B) for x in d.get('metodologia_intro', [])] + [bl(x) for x in d.get('metodologia', [])] + [Spacer(1, 6)]
    s += [KeepTogether([hdr('6. Tutoría y seguimiento'), Spacer(1, 4), kv(filas_tutoria(d)), Spacer(1, 10)])]
    ev = d['evaluacion']
    s += [KeepTogether([hdr('7. Evaluación'), Spacer(1, 4)] + [Paragraph(f'<b>{k}:</b> {v}', B) for k, v in ev] + [Spacer(1, 6)])]
    s += [KeepTogether([hdr('8. Recursos y materiales'), Spacer(1, 4)] + [bl(x) for x in d.get('recursos', [])] + [Spacer(1, 6)])]
    s += [KeepTogether([hdr('9. Requisitos técnicos'), Spacer(1, 4)] + [bl(x) for x in d.get('requisitos', [])] + [Spacer(1, 14)])]
    s += [Paragraph(f"{e(d['entidad'])} · Guía didáctica de la acción formativa {d['af']}-{d['grupo']} «{e(d['denominacion'])}»", FT)]
    SimpleDocTemplate(salida, pagesize=A4, leftMargin=2 * cm, rightMargin=2 * cm, topMargin=1.6 * cm, bottomMargin=1.6 * cm,
                      title=d['titulo_pagina'], author=d['entidad']).build(s)

def comprobar_literal(d, ruta):
    norm = lambda t: re.sub(r'\s+', ' ', t).strip()
    txt = norm(subprocess.run(['pdftotext', ruta, '-'], capture_output=True, text=True).stdout)
    mal = [k for k in ('objetivos', 'contenidos') if norm(d[k]) not in txt]
    return mal

# ── HTML para la Página de Moodle ──────────────────────────────────────────────────────
def html_pagina(d):
    e = html.escape
    h = lambda t: f'<h3 style="background:{AZUL};color:#fff;padding:6px 10px;margin-top:18px">{t}</h3>'
    tabla = lambda rows: ('<table style="border-collapse:collapse;width:100%">' + ''.join(
        f'<tr><th style="text-align:left;background:{AZUL_CL};padding:4px 8px;border:1px solid {BORDE};width:30%">{e(k)}</th>'
        f'<td style="padding:4px 8px;border:1px solid {BORDE}">{e(v)}</td></tr>' for k, v in rows) + '</table>')
    ps = lambda partes: '\n'.join(f'<p>{"<strong>" + e(k) + "</strong>" if k else ""}{e(v)}</p>' for k, v in partes)
    ul = lambda xs: '<ul>' + ''.join(f'<li>{x}</li>' for x in xs) + '</ul>' if xs else ''
    return '\n'.join([
        f'<p style="font-size:1.1em"><strong><a href="@@PLUGINFILE@@/{d["pdf"]}">&#11015; Descargar la guía didáctica en PDF</a></strong></p>',
        f"<p><em>Acción formativa {d['af']} · Grupo {d['grupo']} · {e(d['modalidad'])}</em></p>",
        h('1. Datos de la acción formativa'), tabla(filas_datos(d)),
        h('2. Destinatarios y acceso'), ''.join(f'<p>{x}</p>' for x in d.get('destinatarios', [])),
        h('3. Objetivos'), ps(partir(d['objetivos'], d.get('patron_objetivos', PAT_OBJ))),
        h('4. Contenidos'), ps(partir(d['contenidos'], d.get('patron_contenidos', PAT_CON))),
        h('5. Metodología y funcionamiento del curso'), ''.join(f'<p>{x}</p>' for x in d.get('metodologia_intro', [])), ul(d.get('metodologia', [])),
        h('6. Tutoría y seguimiento'), tabla(filas_tutoria(d)),
        h('7. Evaluación'), ''.join(f'<p><strong>{k}:</strong> {v}</p>' for k, v in d['evaluacion']),
        h('8. Recursos y materiales'), ul(d.get('recursos', [])),
        h('9. Requisitos técnicos'), ul(d.get('requisitos', [])),
        f'<p style="font-size:0.85em;color:{GRIS}"><em>{e(d["entidad"])} · Guía didáctica de la acción formativa {d["af"]}-{d["grupo"]} «{e(d["denominacion"])}»</em></p>'])

PHP_HEAD = r"""<?php
// Publica guías didácticas FUNDAE en el aula: una Página por curso con la guía en HTML y el PDF descargable dentro.
// Generado por guias_fundae.py. Uso SIEMPRE como www-data:
//   aula-php /tmp/SCRIPT.php            → ensayo (no escribe)
//   aula-php /tmp/SCRIPT.php --apply    → crea las páginas (y después borra los recursos viejos indicados)
//   aula-php /tmp/SCRIPT.php --revert   → borra las páginas creadas (no restaura lo borrado)
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
$mode = in_array('--apply', $argv) ? 'apply' : (in_array('--revert', $argv) ? 'revert' : 'ensayo');
echo "Modo: $mode · usuario PHP: " . (function_exists('posix_geteuid') ? posix_getpwuid(posix_geteuid())['name'] : '?') . "\n";
$PDF = []; $HTML = []; $G = [];
"""

PHP_TAIL = r"""
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
    if ($existing) { echo "  YA EXISTE la página «{$g['name']}» (cm " . implode(',', array_keys($existing)) . ") → no se crea otra\n"; }
    $viejos = [];
    foreach ($g['borrar'] as $like) {
        $viejos += $DB->get_records_sql("SELECT cm.id, r.name FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module AND m.name = 'resource'
            JOIN {resource} r ON r.id = cm.instance WHERE cm.course = ? AND r.name LIKE ? AND cm.deletioninprogress = 0", [$courseid, $like]);
    }
    foreach ($viejos as $v) echo "  " . ($mode === 'apply' ? 'Se borrará (tras crear la guía)' : 'Borraría') . " recurso cm {$v->id} «{$v->name}»\n";
    $sec0 = $DB->get_record('course_sections', ['course' => $courseid, 'section' => 0], '*', MUST_EXIST);
    $seq = array_values(array_filter(explode(',', (string)$sec0->sequence)));
    $pdf = base64_decode($PDF[$g['pdf']]);
    echo "  Página «{$g['name']}» en el tema 0, después del primer elemento (cm " . ($seq[0] ?? '-') . "), con el PDF {$g['pdf']} (" . strlen($pdf) . " bytes)\n";
    if ($mode !== 'apply') continue;
    if (!$existing) {
        $mi = new stdClass();
        $mi->modulename = 'page'; $mi->course = $courseid; $mi->section = 0; $mi->visible = 1; $mi->visibleoncoursepage = 1;
        $mi->name = $g['name']; $mi->introeditor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()]; $mi->showdescription = 0;
        $mi->content = $HTML[$g['pdf']]; $mi->contentformat = FORMAT_HTML;
        $mi->display = RESOURCELIB_DISPLAY_AUTO; $mi->printheading = 1; $mi->printintro = 0; $mi->printlastmodified = 1;
        $mi->cmidnumber = ''; $mi->groupmode = 0; $mi->groupingid = 0; $mi->completion = 0;
        $mi = create_module($mi);
        $ctx = context_module::instance($mi->coursemodule);
        get_file_storage()->create_file_from_string(['contextid' => $ctx->id, 'component' => 'mod_page', 'filearea' => 'content', 'itemid' => 0,
            'filepath' => '/', 'filename' => $g['pdf']], $pdf);
        $sec0 = $DB->get_record('course_sections', ['course' => $courseid, 'section' => 0], '*', MUST_EXIST);
        $seq = array_values(array_filter(explode(',', (string)$sec0->sequence), function ($x) use ($mi) { return (int)$x !== (int)$mi->coursemodule; }));
        if (count($seq) >= 2) {
            moveto_module($DB->get_record('course_modules', ['id' => $mi->coursemodule], '*', MUST_EXIST), $sec0, (int)$seq[1]);
        }
        echo "  CREADA cm {$mi->coursemodule} → {$CFG->wwwroot}/mod/page/view.php?id={$mi->coursemodule}\n";
    }
    foreach ($viejos as $v) { course_delete_module($v->id); echo "  BORRADO recurso cm {$v->id}\n"; }
    rebuild_course_cache($courseid, true);
}
echo "\nFin ($mode).\n";
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
        for c in d['cursos']:
            borrar = ', '.join(q(x) for x in d.get('borrar_recursos', {}).get(str(c), []))
            out.append(f"$G[{int(c)}] = ['name' => {q(d['titulo_pagina'])}, 'pdf' => {q(d['pdf'])}, 'borrar' => [{borrar}]];\n")
    out.append(PHP_TAIL)
    open(salida, 'w', encoding='utf-8').write(''.join(out))

def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('accion', choices=['pdf', 'moodle'])
    ap.add_argument('fichas', nargs='+')
    ap.add_argument('-o', '--salida', required=True)
    a = ap.parse_args()
    fichas = [cargar(p) for p in a.fichas]
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
