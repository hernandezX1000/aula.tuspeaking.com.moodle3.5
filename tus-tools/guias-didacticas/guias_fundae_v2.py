#!/usr/bin/env python3
"""guias_fundae_v2.py — añade el apartado «6. Cronología, temas y plazos» (A1.P4.03/.05/.06/.07) a la guía de guias_fundae.py.
Una ficha por CURSO (cada alumno tiene sus apuntes y plazos). Mismo estilo y mismas comprobaciones literales.
  python3 guias_fundae_v2.py pdf    fichas/*.json -o salida/
  python3 guias_fundae_v2.py moodle fichas/*.json -o salida/guias-v2.php      (PHP: ensayo / --update, sustituye la misma página)
"""
import argparse, base64, html, json, os, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import guias_fundae as gf1
from guias_fundae import AZUL, AZUL_CL, AZUL_OSC, GRIS, BORDE, PAT_OBJ, PAT_CON, FONT_DIRS, filas_datos, filas_tutoria, partir, comprobar_literal

def cargar(p):
    d = gf1.cargar(p)
    for k in ('cronologia', 'curso'):
        if not d.get(k): sys.exit(f'{p}: falta {k}')
    return d

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
    CH = ParagraphStyle('ch', fontName='DVB', fontSize=8, textColor=AO, leading=10)
    CV = ParagraphStyle('cv', fontName='DV', fontSize=8, leading=10)
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
    def crono(c):
        rows = [[Paragraph(x, CH) for x in ('Tema', 'Semana', 'Contenidos (publicados / previstos)', 'Tareas (plazo de entrega)')]]
        rows += [[Paragraph(e(str(a)), CV), Paragraph(e(b), CV), Paragraph(e(cc), CV), Paragraph(e(dd), CV)] for a, b, cc, dd in c['filas']]
        x = Table(rows, colWidths=[1.3 * cm, 2.6 * cm, 7.3 * cm, 5.8 * cm], repeatRows=1)
        x.setStyle(TableStyle([('BACKGROUND', (0, 0), (-1, 0), AC), ('GRID', (0, 0), (-1, -1), 0.5, HexColor(BORDE)),
                               ('VALIGN', (0, 0), (-1, -1), 'TOP'), ('TOPPADDING', (0, 0), (-1, -1), 3),
                               ('BOTTOMPADDING', (0, 0), (-1, -1), 3), ('LEFTPADDING', (0, 0), (-1, -1), 4)]))
        return x
    bl = lambda t: Paragraph('•&nbsp;&nbsp;' + t, BU)
    par = lambda partes: [Paragraph((f'<b>{e(k)}</b>' if k else '') + e(v), B) for k, v in partes]
    c = d['cronologia']
    s = [Paragraph('GUÍA DIDÁCTICA', T), Paragraph(e(d['denominacion']), T),
         Paragraph(f"Acción formativa {d['af']} · Grupo {d['grupo']} · {e(d['modalidad'])}", S), Spacer(1, 10),
         hdr('1. Datos de la acción formativa'), Spacer(1, 4), kv(filas_datos(d)), Spacer(1, 10),
         hdr('2. Destinatarios y acceso'), Spacer(1, 4)] + [Paragraph(x, B) for x in d.get('destinatarios', [])] + [Spacer(1, 6),
         hdr('3. Objetivos'), Spacer(1, 4)] + par(partir(d['objetivos'], d.get('patron_objetivos', PAT_OBJ))) + [Spacer(1, 6),
         hdr('4. Contenidos'), Spacer(1, 4)] + par(partir(d['contenidos'], d.get('patron_contenidos', PAT_CON))) + [Spacer(1, 6),
         hdr('5. Metodología y funcionamiento del curso'), Spacer(1, 4)]
    s += [Paragraph(x, B) for x in d.get('metodologia_intro', [])] + [bl(x) for x in d.get('metodologia', [])] + [Spacer(1, 6)]
    s += [hdr('6. Cronología, temas y plazos'), Spacer(1, 4), Paragraph(e(c['intro']), B), crono(c), Spacer(1, 4),
          Paragraph('<b>Pruebas de evaluación:</b> ' + e(c['pruebas']), B), Spacer(1, 6)]
    s += [KeepTogether([hdr('7. Tutoría y seguimiento'), Spacer(1, 4), kv(filas_tutoria(d)), Spacer(1, 10)])]
    s += [KeepTogether([hdr('8. Evaluación'), Spacer(1, 4)] + [Paragraph(f'<b>{k}:</b> {v}', B) for k, v in d['evaluacion']] + [Spacer(1, 6)])]
    s += [KeepTogether([hdr('9. Recursos y materiales'), Spacer(1, 4)] + [bl(x) for x in d.get('recursos', [])] + [Spacer(1, 6)])]
    s += [KeepTogether([hdr('10. Requisitos técnicos'), Spacer(1, 4)] + [bl(x) for x in d.get('requisitos', [])] + [Spacer(1, 14)])]
    s += [Paragraph(f"{e(d['entidad'])} · Guía didáctica de la acción formativa {d['af']}-{d['grupo']} «{e(d['denominacion'])}»", FT)]
    SimpleDocTemplate(salida, pagesize=A4, leftMargin=2 * cm, rightMargin=2 * cm, topMargin=1.6 * cm, bottomMargin=1.6 * cm,
                      title=d['titulo_pagina'], author=d['entidad']).build(s)

def html_pagina(d):
    e = html.escape
    h = lambda t: f'<h3 style="background:{AZUL};color:#fff;padding:6px 10px;margin-top:18px">{t}</h3>'
    tabla = lambda rows: ('<table style="border-collapse:collapse;width:100%">' + ''.join(
        f'<tr><th style="text-align:left;background:{AZUL_CL};padding:4px 8px;border:1px solid {BORDE};width:30%">{e(k)}</th>'
        f'<td style="padding:4px 8px;border:1px solid {BORDE}">{e(v)}</td></tr>' for k, v in rows) + '</table>')
    td = f'padding:4px 6px;border:1px solid {BORDE};vertical-align:top'
    c = d['cronologia']
    crono = (f'<table style="border-collapse:collapse;width:100%;font-size:0.92em"><tr>' +
             ''.join(f'<th style="{td};background:{AZUL_CL};text-align:left">{x}</th>' for x in ('Tema', 'Semana', 'Contenidos (publicados / previstos)', 'Tareas (plazo de entrega)')) + '</tr>' +
             ''.join('<tr>' + ''.join(f'<td style="{td}">{e(str(x))}</td>' for x in f) + '</tr>' for f in c['filas']) + '</table>')
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
        h('6. Cronología, temas y plazos') + f'<p>{e(c["intro"])}</p>' + crono + f'<p><strong>Pruebas de evaluación:</strong> {e(c["pruebas"])}</p>',
        h('7. Tutoría y seguimiento'), tabla(filas_tutoria(d)),
        h('8. Evaluación'), ''.join(f'<p><strong>{k}:</strong> {v}</p>' for k, v in d['evaluacion']),
        h('9. Recursos y materiales'), ul(d.get('recursos', [])),
        h('10. Requisitos técnicos'), ul(d.get('requisitos', [])),
        f'<p style="font-size:0.85em;color:{GRIS}"><em>{e(d["entidad"])} · Guía didáctica de la acción formativa {d["af"]}-{d["grupo"]} «{e(d["denominacion"])}»</em></p>'])

PHP_HEAD = r"""<?php
// Guía didáctica v2 (con «6. Cronología, temas y plazos») para los cursos indicados: sustituye el HTML y el PDF de la página
// «Guía didáctica – …» que ya existe en cada curso (misma página, mismo cm). Generado por gf2.py (T-115/T-116, 6/10/2026).
//   aula-php /tmp/SCRIPT.php            → ensayo: dice qué página cambia y el sha1 del PDF publicado y del nuevo (no escribe)
//   aula-php /tmp/SCRIPT.php --update   → sustituye HTML y PDF, sube la revisión, reconstruye la caché del curso
define('CLI_SCRIPT', true);
if (function_exists('posix_geteuid') && posix_geteuid() === 0) { fwrite(STDERR, "ABORTA: no se ejecuta como root. Usa aula-php.\n"); exit(1); }
foreach ([getcwd() . '/config.php', '/var/www/html/app/moodle/config.php', '/var/www/html/config.php'] as $cfg) {
    if (is_readable($cfg)) { require($cfg); break; }
}
if (empty($CFG)) { fwrite(STDERR, "No encuentro config.php de Moodle\n"); exit(1); }
require_once($CFG->dirroot . '/course/lib.php');
\core\session\manager::set_user(get_admin());
set_exception_handler(function($e) { fwrite(STDOUT, "\nERROR: " . $e->getMessage() . "\n" . (isset($e->debuginfo) ? $e->debuginfo . "\n" : '')); exit(1); });
$mode = in_array('--update', $argv) ? 'update' : 'ensayo';
echo "Modo: $mode\n";
$G = [];
"""

PHP_TAIL = r"""
$fs = get_file_storage();
foreach ($G as $courseid => $g) {
    $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', MUST_EXIST);
    echo "\n== Curso $courseid · {$course->fullname}\n";
    $cm = $DB->get_record_sql("SELECT cm.id, cm.instance FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module AND m.name = 'page'
        JOIN {page} p ON p.id = cm.instance WHERE cm.id = ? AND cm.course = ? AND p.name = ? AND cm.deletioninprogress = 0", [$g['cmid'], $courseid, $g['name']]);
    if (!$cm) { echo "  NO ENCUENTRO la página cm {$g['cmid']} «{$g['name']}» en este curso: no se toca\n"; continue; }
    $ctx = context_module::instance($cm->id);
    $nuevo = base64_decode($g['pdf64']);
    $actual = array_values(array_filter($fs->get_area_files($ctx->id, 'mod_page', 'content', 0, 'id', false), function ($f) { return $f->get_filename() !== '.'; }));
    echo "  Página cm {$cm->id} «{$g['name']}»\n";
    echo "    PDF publicado: " . ($actual ? $actual[0]->get_filename() . ' sha1 ' . $actual[0]->get_contenthash() : '(ninguno)') . "\n";
    echo "    PDF nuevo:     {$g['pdf']} sha1 " . sha1($nuevo) . " (" . strlen($nuevo) . " bytes)" . ($actual && $actual[0]->get_contenthash() === sha1($nuevo) ? '  → YA PUBLICADO' : '') . "\n";
    if ($mode !== 'update') continue;
    $page = $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);
    $page->content = $g['html']; $page->contentformat = FORMAT_HTML; $page->revision++; $page->timemodified = time();
    $DB->update_record('page', $page);
    $fs->delete_area_files($ctx->id, 'mod_page', 'content');
    $fs->create_file_from_string(['contextid' => $ctx->id, 'component' => 'mod_page', 'filearea' => 'content', 'itemid' => 0,
        'filepath' => '/', 'filename' => $g['pdf']], $nuevo);
    rebuild_course_cache($courseid, true);
    echo "    ACTUALIZADA (revisión {$page->revision}) → {$CFG->wwwroot}/mod/page/view.php?id={$cm->id}\n";
}
if ($mode === 'update') { purge_all_caches(); echo "\nCachés purgadas.\n"; }
echo "\nFin ($mode).\n";
"""

def php(fichas, dir_pdf, salida):
    def nowdoc(s):
        assert '\nTXT;' not in s
        return f"<<<'TXT'\n{s}\nTXT"
    q = lambda s: "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"
    out = [PHP_HEAD]
    for d in fichas:
        ruta = os.path.join(dir_pdf, d['_pdf_local'])
        out.append(f"$G[{int(d['curso'])}] = ['cmid' => {int(d['cmid'])}, 'name' => {q(d['titulo_pagina'])}, 'pdf' => {q(d['pdf'])}, "
                   f"'pdf64' => '{base64.b64encode(open(ruta, 'rb').read()).decode()}', 'html' => {nowdoc(html_pagina(d))}];\n")
    out.append(PHP_TAIL)
    open(salida, 'w', encoding='utf-8').write(''.join(out))

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('accion', choices=['pdf', 'moodle'])
    ap.add_argument('fichas', nargs='+')
    ap.add_argument('-o', '--salida', required=True)
    a = ap.parse_args()
    fichas = [cargar(p) for p in a.fichas]
    for d in fichas: d['_pdf_local'] = f"{d['curso']}_{d['pdf']}"
    if a.accion == 'pdf':
        os.makedirs(a.salida, exist_ok=True); err = 0
        for d in fichas:
            ruta = os.path.join(a.salida, d['_pdf_local']); pdf(d, ruta)
            mal = comprobar_literal(d, ruta)
            print(f"{ruta}  ·  literal: {'OK' if not mal else 'FALLA ' + ','.join(mal)}"); err += bool(mal)
        sys.exit(1 if err else 0)
    php(fichas, os.path.dirname(os.path.abspath(a.salida)), a.salida)
    print(a.salida)

if __name__ == '__main__':
    main()
