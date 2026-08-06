#!/usr/bin/env python3
"""
gen_guia_didactica.py — Generador automático de Guías Didácticas FUNDAE
Micro Ventures SL · CIF B71259352 · Expediente B265364AC

Uso:
    python3 gen_guia_didactica.py --c_fundae 040-01
    python3 gen_guia_didactica.py --c_fundae 029-01 --output /tmp
    python3 gen_guia_didactica.py --list   # muestra todos los pendientes

Fuentes de datos:
    - fundae_oficial_stg  → denominación oficial, modalidad, horas, fechas
    - mdl_fundae          → courseid, empresa, CIF, nivel, idioma, horas desglosadas
    - mdl_fundae_guia_didactica → tutor, herramienta, objetivos (debe estar poblada)
    - mdl_course_sections → contenidos reales del curso
    - mdl_user            → nombre y email del tutor
    - mdl_user_enrolments → participantes matriculados

Requisitos:
    pip install reportlab --user
"""

import subprocess
import argparse
import os
import time
from datetime import datetime
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import cm
from reportlab.lib.colors import HexColor, white, black
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, HRFlowable
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_CENTER

# ── Colores corporativos ──────────────────────────────────────────────────────
AZUL    = HexColor('#1F4E78')
AZUL2   = HexColor('#2E75B6')
AZUL_CL = HexColor('#EBF3FB')
GRIS    = HexColor('#555555')
GRIS_CL = HexColor('#F5F5F5')

DB = "aulatuspeaking35"

# ── Estilos PDF ───────────────────────────────────────────────────────────────
title_s  = ParagraphStyle('t',  fontSize=16, textColor=AZUL,  fontName='Helvetica-Bold', alignment=TA_CENTER, spaceAfter=4)
sub_s    = ParagraphStyle('s',  fontSize=10, textColor=GRIS,  fontName='Helvetica-Oblique', alignment=TA_CENTER, spaceAfter=4)
h1_s     = ParagraphStyle('h1', fontSize=11, textColor=white, fontName='Helvetica-Bold')
body_s   = ParagraphStyle('b',  fontSize=9.5, textColor=black, fontName='Helvetica', spaceAfter=4, leading=13)
bullet_s = ParagraphStyle('bu', fontSize=9.5, textColor=black, fontName='Helvetica', spaceAfter=3, leading=13, leftIndent=15, firstLineIndent=-10)
footer_s = ParagraphStyle('f',  fontSize=7,  textColor=GRIS,  fontName='Helvetica-Oblique', alignment=TA_CENTER)
lkey_s   = ParagraphStyle('lk', fontSize=9,  textColor=AZUL,  fontName='Helvetica-Bold', leading=12)
lval_s   = ParagraphStyle('lv', fontSize=9,  textColor=black, fontName='Helvetica', leading=12)
mh_s     = ParagraphStyle('mh', fontSize=10, textColor=white, fontName='Helvetica-Bold')

# ── Helpers PDF ───────────────────────────────────────────────────────────────
def hdr(text):
    t = Table([[Paragraph(text, h1_s)]], colWidths=[16*cm])
    t.setStyle(TableStyle([
        ('BACKGROUND',(0,0),(-1,-1),AZUL),
        ('TOPPADDING',(0,0),(-1,-1),7),('BOTTOMPADDING',(0,0),(-1,-1),7),
        ('LEFTPADDING',(0,0),(-1,-1),10),
    ]))
    return t

def info_table(rows_data):
    rows = [[Paragraph(k, lkey_s), Paragraph(str(v), lval_s)] for k, v in rows_data]
    t = Table(rows, colWidths=[5*cm, 11*cm])
    t.setStyle(TableStyle([
        ('BACKGROUND',(0,0),(0,-1),AZUL_CL),
        ('GRID',(0,0),(-1,-1),0.5,HexColor('#CCCCCC')),
        ('TOPPADDING',(0,0),(-1,-1),5),('BOTTOMPADDING',(0,0),(-1,-1),5),
        ('LEFTPADDING',(0,0),(-1,-1),8),('RIGHTPADDING',(0,0),(-1,-1),8),
        ('VALIGN',(0,0),(-1,-1),'TOP'),
    ]))
    return t

def modulo_hdr(num, titulo, horas):
    t = Table([[Paragraph(f"Módulo {num} — {titulo}  ({horas})", mh_s)]], colWidths=[16*cm])
    t.setStyle(TableStyle([
        ('BACKGROUND',(0,0),(-1,-1),AZUL2),
        ('TOPPADDING',(0,0),(-1,-1),6),('BOTTOMPADDING',(0,0),(-1,-1),6),
        ('LEFTPADDING',(0,0),(-1,-1),10),
    ]))
    return t

# ── SQL Helper ────────────────────────────────────────────────────────────────
def run_sql(sql, db=DB):
    result = subprocess.run(["mysql", db, "-e", sql], capture_output=True, text=True)
    if result.returncode != 0:
        print(f"Error SQL: {result.stderr}")
        return []
    lines = result.stdout.strip().split("\n")
    if len(lines) < 2:
        return []
    headers = lines[0].split("\t")
    rows = []
    for line in lines[1:]:
        if line.strip():
            cols = line.split("\t")
            rows.append(dict(zip(headers, cols)))
    return rows

# ── Obtener datos del curso ───────────────────────────────────────────────────
def get_datos_curso(c_fundae):
    sql = f"""
SELECT
    o.id_grupo, o.c_fundae, o.denominacion_oficial, o.modalidad,
    o.duracion AS horas_totales, o.fecha_inicio, o.fecha_fin, o.estado,
    f.courseid, f.empresa, f.razon_social, f.cif, f.nivel, f.idioma,
    f.horas_plataforma, f.horas_conversacion, f.num_sesiones,
    CONCAT(COALESCE(um.firstname,''), ' ', COALESCE(um.lastname,'')) AS tutor_moodle,
    um.email AS tutor_moodle_email,
    CONCAT(COALESCE(ur.firstname,''), ' ', COALESCE(ur.lastname,'')) AS tutor_real,
    ur.email AS tutor_real_email,
    CONCAT(COALESCE(ut.firstname,''), ' ', COALESCE(ut.lastname,'')) AS tutor_teams,
    ut.email AS tutor_teams_email,
    g.herramienta_sincrona, g.objetivo_general, g.objetivos_especificos,
    g.criterio_evaluacion, g.estado AS estado_guia
FROM fundae_oficial_stg o
JOIN mdl_fundae f ON f.c_fundae = o.c_fundae
LEFT JOIN mdl_fundae_guia_didactica g ON g.c_fundae = o.c_fundae
LEFT JOIN mdl_user um ON um.id = g.tutor_moodle_userid
LEFT JOIN mdl_user ur ON ur.id = g.tutor_real_userid
LEFT JOIN mdl_user ut ON ut.id = g.tutor_teams_userid
WHERE o.c_fundae = '{c_fundae}'
LIMIT 1;
"""
    rows = run_sql(sql)
    return rows[0] if rows else None

def get_participantes(courseid):
    sql = f"""
SELECT u.firstname, u.lastname, u.email
FROM mdl_user_enrolments ue
JOIN mdl_enrol e ON e.id = ue.enrolid AND e.courseid = {courseid}
JOIN mdl_user u ON u.id = ue.userid
JOIN mdl_role_assignments ra ON ra.userid = u.id
JOIN mdl_role r ON r.id = ra.roleid AND r.shortname = 'student'
JOIN mdl_context ctx ON ctx.id = ra.contextid AND ctx.instanceid = {courseid} AND ctx.contextlevel = 50
WHERE u.deleted = 0
ORDER BY u.lastname;
"""
    return run_sql(sql)

def get_contenidos(courseid):
    sql = f"""
SELECT cs.name AS seccion, cs.section AS orden,
       m.name AS tipo,
       COALESCE(p.name, h.name, a.name, r.name, u2.name) AS nombre
FROM mdl_course_sections cs
JOIN mdl_course_modules cm ON cm.section = cs.id AND cm.visible = 1
JOIN mdl_modules m ON m.id = cm.module
LEFT JOIN mdl_page p ON p.id = cm.instance AND m.name = 'page'
LEFT JOIN mdl_hvp h ON h.id = cm.instance AND m.name = 'hvp'
LEFT JOIN mdl_assign a ON a.id = cm.instance AND m.name = 'assign'
LEFT JOIN mdl_resource r ON r.id = cm.instance AND m.name = 'resource'
LEFT JOIN mdl_url u2 ON u2.id = cm.instance AND m.name = 'url'
WHERE cs.course = {courseid}
AND cs.name IS NOT NULL AND cs.name != ''
AND COALESCE(p.name, h.name, a.name, r.name, u2.name) IS NOT NULL
ORDER BY cs.section, cm.id;
"""
    return run_sql(sql)

def get_pendientes():
    sql = """
SELECT o.c_fundae, o.id_grupo, o.denominacion_oficial, o.modalidad,
       o.duracion, o.estado AS estado_fundae,
       COALESCE(g.estado, 'SIN REGISTRO') AS estado_guia
FROM fundae_oficial_stg o
LEFT JOIN mdl_fundae_guia_didactica g
    ON g.c_fundae = o.c_fundae
WHERE o.estado IN ('Válido','Finalizado')
AND COALESCE(g.estado,'pendiente') != 'revisado'
ORDER BY o.c_fundae;
"""
    return run_sql(sql)

# ── Objetivos por nivel ───────────────────────────────────────────────────────
OBJETIVOS = {
    'A1': ('Adquirir las competencias lingüísticas básicas del nivel A1 del MCER para comunicarse en situaciones cotidianas simples.',
           ['Presentarse y hablar sobre información personal básica.',
            'Comprender y usar expresiones cotidianas muy frecuentes.',
            'Interactuar de forma sencilla cuando el interlocutor habla despacio.',
            'Escribir frases y expresiones simples sobre uno mismo.',
            'Familiarizarse con el vocabulario básico de uso cotidiano.']),
    'A2': ('Desarrollar las competencias lingüísticas del nivel A2 del MCER para comunicarse en situaciones cotidianas habituales.',
           ['Comprender frases y expresiones relacionadas con áreas de experiencia inmediata.',
            'Comunicarse en tareas sencillas y rutinarias sobre temas familiares.',
            'Describir aspectos del entorno inmediato: trabajo, familia, compras.',
            'Ampliar el vocabulario activo en ámbitos cotidianos y profesionales.',
            'Mejorar la fluidez oral en conversaciones básicas.']),
    'B1': ('Consolidar las competencias lingüísticas del nivel B1 del MCER, desarrollando la capacidad de comunicación oral y escrita en contextos cotidianos y profesionales.',
           ['Comprender los puntos principales de textos claros sobre temas cotidianos y de trabajo.',
            'Desenvolverse en la mayoría de situaciones que puedan surgir en el entorno laboral.',
            'Producir textos sencillos y coherentes sobre temas familiares.',
            'Mejorar la pronunciación y la fluidez oral mediante sesiones síncronas.',
            'Desarrollar la autonomía del aprendiente para continuar su progresión lingüística.']),
    'B1.2': ('Progresar en las competencias del nivel B1 hacia el B2 del MCER, consolidando estructuras intermedias y ampliando la expresión oral y escrita.',
             ['Comprender textos de cierta complejidad sobre temas cotidianos y profesionales.',
              'Expresarse con fluidez y espontaneidad en situaciones habituales de trabajo.',
              'Producir textos claros y detallados sobre temas de interés personal y profesional.',
              'Ampliar el dominio de estructuras gramaticales intermedias.',
              'Mejorar la precisión y naturalidad en la expresión oral.']),
    'B2': ('Alcanzar las competencias lingüísticas del nivel B2 del MCER para comunicarse con fluidez y eficacia en contextos profesionales complejos.',
           ['Comprender las ideas principales de textos complejos sobre temas concretos y abstractos.',
            'Relacionarse con hablantes nativos con un grado de fluidez natural.',
            'Producir textos claros y detallados sobre una amplia gama de temas.',
            'Dominar estructuras gramaticales avanzadas en contextos profesionales.',
            'Argumentar y debatir con precisión en el entorno laboral.']),
    'C1': ('Dominar las competencias lingüísticas del nivel C1 del MCER para comunicarse con eficacia, fluidez y precisión en contextos profesionales exigentes.',
           ['Comprender textos extensos y complejos con sentido implícito.',
            'Expresarse de forma fluida y espontánea sin esfuerzo aparente.',
            'Usar el idioma de manera flexible y eficaz para fines sociales y profesionales.',
            'Producir textos bien estructurados, detallados y complejos.',
            'Dominar matices de significado y registros formales e informales.']),
}

def get_objetivos(nivel):
    nivel_key = nivel.split(' ')[0].split('(')[0].strip() if nivel else 'B1'
    for k in OBJETIVOS:
        if nivel_key.upper().startswith(k):
            return OBJETIVOS[k]
    return OBJETIVOS['B1']

# ── Generador principal ───────────────────────────────────────────────────────
def generar_guia(c_fundae, output_dir):
    print(f"\n{'='*60}")
    print(f"  Generando guía didáctica: {c_fundae}")
    print(f"{'='*60}\n")

    # 1. Obtener datos
    d = get_datos_curso(c_fundae)
    if not d:
        print(f"ERROR: No se encontró el curso {c_fundae}")
        return None

    courseid = d['courseid']
    participantes = get_participantes(courseid)
    contenidos = get_contenidos(courseid)

    # 2. Determinar tutor según modalidad
    modalidad = d['modalidad']
    if 'Presencial' in modalidad or 'Teams' in str(d.get('herramienta_sincrona','')):
        tutor_nombre = d['tutor_teams'].strip() or d['tutor_moodle'].strip()
        tutor_email  = d['tutor_teams_email'] or d['tutor_moodle_email']
        herramienta  = 'Microsoft Teams'
    else:
        tutor_nombre = d['tutor_real'].strip() or d['tutor_moodle'].strip()
        tutor_email  = d['tutor_real_email'] or d['tutor_moodle_email']
        herramienta  = 'Zoom'

    # 3. Horas
    horas_total = int(d['horas_totales'] or 0)
    horas_plat  = int(d['horas_plataforma']) if d['horas_plataforma'] not in (None,'NULL','') else 0
    horas_conv  = int(d['horas_conversacion']) if d['horas_conversacion'] not in (None,'NULL','') else 0
    if horas_plat == 0 and horas_conv == 0 and horas_total > 0:
        horas_conv = round(horas_total * 0.15)
        horas_plat = horas_total - horas_conv

    pct_plat = round(horas_plat / horas_total * 100) if horas_total > 0 else 0
    pct_conv = round(horas_conv / horas_total * 100) if horas_total > 0 else 0

    # 4. Participantes
    partic_txt = '\n'.join([f"{p['firstname']} {p['lastname']} · {p['email']}" for p in participantes]) or '—'

    # 5. Objetivos
    nivel = d.get('nivel') or 'B1'
    obj_general, obj_especificos = get_objetivos(nivel)
    if d.get('objetivo_general') and d['objetivo_general'] != 'NULL':
        obj_general = d['objetivo_general']
    if d.get('objetivos_especificos') and d['objetivos_especificos'] != 'NULL':
        obj_especificos = [d['objetivos_especificos']]

    # 6. Contenidos agrupados por sección
    secciones = {}
    for c in contenidos:
        sec = c['seccion']
        if sec not in secciones:
            secciones[sec] = []
        secciones[sec].append(c)

    # 7. Fechas formateadas
    def fmt_fecha(f):
        if not f or f == 'NULL': return '—'
        try:
            return datetime.strptime(f, '%Y-%m-%d').strftime('%d/%m/%Y')
        except:
            return f

    fecha_inicio = fmt_fecha(d['fecha_inicio'])
    fecha_fin    = fmt_fecha(d['fecha_fin'])

    # 8. Nombre archivo
    slug = c_fundae.replace('-','_')
    id_grupo = d['id_grupo']
    fname = f"{output_dir}/Doc4_Guia_Didactica_{id_grupo}_{slug}.pdf"

    # 9. Construir PDF
    doc = SimpleDocTemplate(fname, pagesize=A4,
        leftMargin=2.5*cm, rightMargin=2.5*cm,
        topMargin=2.5*cm, bottomMargin=2.5*cm)

    af_num   = d['c_fundae'].split('-')[0].lstrip('0')
    gr_num   = d['c_fundae'].split('-')[1].lstrip('0')
    af_label = f"AF {af_num.zfill(3)} / Grupo {gr_num.zfill(2)}"

    story = [
        Paragraph("GUÍA DIDÁCTICA — PLAN FORMATIVO", title_s),
        Paragraph("Formación Programada para Empresas · FTFE 2026 · Micro Ventures S.L.", sub_s),
        Spacer(1, 0.3*cm),

        hdr("1. Datos identificativos de la acción formativa"),
        Spacer(1, 0.2*cm),
        info_table([
            ("Denominación oficial",  d['denominacion_oficial']),
            ("Entidad formadora",     "Micro Ventures S.L. — CIF B71259352"),
            ("Empresa beneficiaria",  f"{d['razon_social']} — CIF {d['cif']}"),
            ("ID Grupo FUNDAE",       str(id_grupo)),
            ("Nº AF / Grupo",         af_label),
            ("Modalidad",             modalidad),
            ("Nivel",                 nivel),
            ("Idioma",                d.get('idioma') or '—'),
            ("Duración total",        f"{horas_total} horas"),
            ("Periodo",               f"{fecha_inicio} – {fecha_fin}"),
            ("Plataforma",            "Moodle — aula.tuspeaking.com"),
            ("Tutor/a",               f"{tutor_nombre} — {tutor_email}"),
            ("Participantes",         partic_txt),
        ]),
        Spacer(1, 0.4*cm),

        hdr("2. Objetivos"),
        Spacer(1, 0.2*cm),
        Paragraph("<b>Objetivo general:</b>", body_s),
        Paragraph(obj_general, body_s),
        Paragraph("<b>Objetivos específicos:</b>", body_s),
    ]

    for obj in obj_especificos:
        story.append(Paragraph(f"• {obj}", bullet_s))

    story += [Spacer(1, 0.4*cm), hdr("3. Contenidos"), Spacer(1, 0.2*cm)]

    # Módulos
    total_hvp  = sum(1 for c in contenidos if c['tipo'] == 'hvp')
    total_assign = sum(1 for c in contenidos if c['tipo'] == 'assign')

    for i, (sec_name, items) in enumerate(secciones.items()):
        hvp_sec = sum(1 for c in items if c['tipo'] == 'hvp')
        assign_sec = sum(1 for c in items if c['tipo'] == 'assign')

        if i == 0:
            horas_sec = "0 h"
        else:
            horas_sec_n = round(horas_plat / max(len(secciones)-1, 1))
            horas_sec = f"{horas_sec_n} h"

        story.append(modulo_hdr(i, sec_name, horas_sec))

        # Recursos por tipo
        recursos = [c for c in items if c['tipo'] == 'resource']
        hvps     = [c for c in items if c['tipo'] == 'hvp']
        assigns  = [c for c in items if c['tipo'] == 'assign']
        pages    = [c for c in items if c['tipo'] == 'page']

        if recursos:
            for r in recursos:
                story.append(Paragraph(f"• {r['nombre']}", bullet_s))
        if hvps:
            story.append(Paragraph(f"• {len(hvps)} actividades interactivas (drag & drop, multiple choice, fill in the gaps, true/false, translations)", bullet_s))
        if assigns:
            for a in assigns:
                story.append(Paragraph(f"• Entrega escrita/oral: {a['nombre']}", bullet_s))
        if pages:
            for p in pages:
                story.append(Paragraph(f"• {p['nombre']}", bullet_s))

        story.append(Spacer(1, 0.2*cm))

    # Metodología
    if 'Presencial' in modalidad:
        metod_txt = f"La acción formativa se desarrolla en modalidad de <b>aula virtual</b> mediante {herramienta}. Las sesiones son síncronas, con el/la tutor/a y el participante conectados simultáneamente, siguiendo una estructura de clase en directo."
        comp1 = f"<b>Sesiones síncronas en directo ({herramienta}):</b> el/la tutor/a imparte la clase en tiempo real, trabajando pronunciación, fluidez y expresión oral con el participante."
        comp2 = "<b>Materiales de apoyo:</b> el participante accede a recursos y actividades complementarias en la plataforma Moodle entre sesiones."
    else:
        metod_txt = "La acción formativa se desarrolla en modalidad de <b>teleformación</b>, combinando dos componentes complementarios:"
        comp1 = "<b>Autoestudio en plataforma:</b> el participante trabaja de forma autónoma los contenidos, actividades y recursos disponibles en la plataforma Moodle, a su propio ritmo y en los horarios que mejor se adapten a su disponibilidad."
        comp2 = f"<b>Sesiones síncronas de conversación ({herramienta}):</b> el/la tutor/a realiza sesiones individuales de práctica oral con el participante mediante videoconferencia, trabajando la fluidez, pronunciación y expresión espontánea."

    story += [
        hdr("4. Metodología"),
        Spacer(1, 0.2*cm),
        Paragraph(metod_txt, body_s),
        Paragraph(f"• {comp1}", bullet_s),
        Paragraph(f"• {comp2}", bullet_s),
        Spacer(1, 0.4*cm),

        hdr("5. Temporalización"),
        Spacer(1, 0.2*cm),
    ]

    if 'Presencial' in modalidad:
        story.append(info_table([
            ("Sesiones síncronas en directo", f"{horas_total} horas (100%)"),
            ("TOTAL", f"{horas_total} horas"),
        ]))
    else:
        story.append(info_table([
            ("Autoestudio en plataforma (Moodle)", f"{horas_plat} horas ({pct_plat}%)"),
            (f"Sesiones síncronas con tutor/a ({herramienta})", f"{horas_conv} horas ({pct_conv}%)"),
            ("TOTAL", f"{horas_total} horas"),
        ]))

    story += [
        Spacer(1, 0.4*cm),
        hdr("6. Tutorización"),
        Spacer(1, 0.2*cm),
        info_table([
            ("Tutor/a",               tutor_nombre),
            ("Email",                 tutor_email or '—'),
            ("Canal de comunicación", f"Mensajería interna Moodle + {herramienta}"),
            ("Horario de tutorías",   "Lunes a viernes, según disponibilidad del participante"),
        ]),
        Spacer(1, 0.4*cm),

        hdr("7. Recursos didácticos y tecnológicos"),
        Spacer(1, 0.2*cm),
        info_table([
            ("Plataforma de autoestudio",   "Moodle — aula.tuspeaking.com"),
            ("Herramienta de tutorización", f"{herramienta} (videoconferencia síncrona)"),
            ("Materiales didácticos",       "Actividades interactivas H5P, presentaciones PDF, recursos de vocabulario y gramática integrados en la plataforma"),
            ("Recursos de apoyo",           "Materiales complementarios facilitados por el/la tutor/a durante las sesiones síncronas"),
            ("Requisito técnico",           f"Dispositivo con conexión a internet, navegador actualizado y micrófono/cámara para las sesiones {herramienta}"),
        ]),
        Spacer(1, 0.4*cm),

        hdr("8. Evaluación y criterios de superación"),
        Spacer(1, 0.2*cm),
        Paragraph("La evaluación es continua y se basa en el seguimiento del progreso del participante:", body_s),
        Paragraph("• Completar un mínimo del <b>75% de las actividades</b> propuestas en la plataforma de autoestudio.", bullet_s),
        Paragraph("• Participar en las <b>sesiones de tutorización síncrona</b> programadas.", bullet_s),
        Paragraph("• La calificación final se emite al término del curso, una vez verificado el cumplimiento de los criterios anteriores.", bullet_s),
        Spacer(1, 0.6*cm),

        HRFlowable(width="100%", thickness=0.5, color=GRIS),
        Spacer(1, 0.15*cm),
        Paragraph("Micro Ventures S.L. — CIF B71259352 · Calle Nacedero del Urederra 7, 1ºB · Sarriguren, Navarra", footer_s),
        Paragraph(f"Guía didáctica · SEPE Navarra · Expediente B265364AC · ID Grupo {id_grupo} · {af_label}", footer_s),
    ]

    doc.build(story)

    # 10. Registrar en mdl_fundae_documentos
    ts = int(time.time())
    reg_sql = f"""
INSERT INTO mdl_fundae_documentos
    (tipo_documento, c_fundae, cif_empresa, path_archivo, nombre_archivo,
     fecha_documento, reutilizable, vigente, timecreated)
VALUES
    ('guia_didactica', '{c_fundae}', '{d['cif']}',
     '{fname}', '{os.path.basename(fname)}',
     CURDATE(), 0, 1, {ts})
ON DUPLICATE KEY UPDATE
    path_archivo='{fname}', vigente=1, timecreated={ts};
"""
    run_sql(reg_sql)

    # 11. Actualizar estado en mdl_fundae_guia_didactica
    run_sql(f"""
UPDATE mdl_fundae_guia_didactica
SET pdf_path='{fname}', pdf_generado_at=NOW(), estado='generado', timemodified={ts}
WHERE c_fundae='{c_fundae}';
""")

    print(f"✓ PDF generado: {fname}")
    print(f"✓ Registrado en mdl_fundae_documentos")
    return fname


# ── Main ──────────────────────────────────────────────────────────────────────
def main():
    parser = argparse.ArgumentParser(description='Generador de Guías Didácticas FUNDAE')
    parser.add_argument('--c_fundae', help='Código AF/Grupo (ej: 040-01)')
    parser.add_argument('--output',   default='/tmp', help='Directorio de salida')
    parser.add_argument('--list',     action='store_true', help='Listar cursos pendientes')
    args = parser.parse_args()

    if args.list:
        pendientes = get_pendientes()
        print(f"\n{'='*80}")
        print(f"  Cursos con guía didáctica pendiente ({len(pendientes)} total)")
        print(f"{'='*80}")
        print(f"{'c_fundae':<12} {'id_grupo':<10} {'estado_fundae':<12} {'estado_guia':<15} {'denominacion'}")
        print("-"*80)
        for p in pendientes:
            print(f"{p['c_fundae']:<12} {p['id_grupo']:<10} {p['estado_fundae']:<12} {p['estado_guia']:<15} {p['denominacion_oficial'][:45]}")
        return

    if not args.c_fundae:
        parser.print_help()
        return

    generar_guia(args.c_fundae, args.output)

if __name__ == '__main__':
    main()
