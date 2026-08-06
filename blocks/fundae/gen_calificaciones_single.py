#!/usr/bin/env python3
"""
gen_calificaciones_single.py — Exportar calificaciones de un alumno específico
Llamado desde fundae_calificaciones.php
"""
import subprocess
import argparse
from datetime import datetime
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import cm
from reportlab.lib.colors import HexColor, white, black
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_CENTER

AZUL=HexColor('#1F4E78'); AZUL2=HexColor('#2E75B6'); VERDE=HexColor('#28A745')
GRIS=HexColor('#6C757D'); AZUL_CL=HexColor('#EBF3FB'); VERDE_CL=HexColor('#D4EDDA')
ROJO_CL=HexColor('#F8D7DA'); GRIS_CL=HexColor('#F5F5F5'); AMARILLO=HexColor('#FFC107')

DB = "aulatuspeaking35"

def run_sql(sql):
    result = subprocess.run(["mysql", DB, "-e", sql], capture_output=True, text=True)
    if result.returncode != 0:
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

def get_actividades(course_id, user_id):
    sql = f"""
SELECT m.name AS tipo,
       COALESCE(gi.itemname, CONCAT('Módulo ', cm.id)) AS actividad,
       cmc.completionstate AS completado,
       ROUND(gg.finalgrade, 2) AS nota,
       ROUND(gi.grademax, 2) AS nota_max
FROM mdl_course_modules cm
JOIN mdl_modules m ON m.id = cm.module
LEFT JOIN mdl_grade_items gi ON gi.iteminstance = cm.instance
    AND gi.itemtype = 'mod' AND gi.courseid = {course_id}
LEFT JOIN mdl_course_modules_completion cmc
    ON cmc.coursemoduleid = cm.id AND cmc.userid = {user_id}
LEFT JOIN mdl_grade_grades gg ON gg.itemid = gi.id AND gg.userid = {user_id}
WHERE cm.course = {course_id} AND cm.completion > 0
ORDER BY cm.id;"""
    return run_sql(sql)

def get_nota_final(course_id, user_id):
    sql = f"""
SELECT ROUND(gg.finalgrade, 2) AS nota_final, ROUND(gi.grademax, 2) AS nota_max
FROM mdl_grade_items gi
JOIN mdl_grade_grades gg ON gg.itemid = gi.id AND gg.userid = {user_id}
WHERE gi.courseid = {course_id} AND gi.itemtype = 'course' LIMIT 1;"""
    rows = run_sql(sql)
    return rows[0] if rows else {"nota_final": "NULL", "nota_max": "10"}

def get_completion_pct(course_id, user_id):
    sql = f"""
SELECT COUNT(*) AS total,
       SUM(CASE WHEN cmc.completionstate >= 1 THEN 1 ELSE 0 END) AS completadas
FROM mdl_course_modules cm
LEFT JOIN mdl_course_modules_completion cmc
    ON cmc.coursemoduleid = cm.id AND cmc.userid = {user_id}
WHERE cm.course = {course_id} AND cm.completion > 0;"""
    rows = run_sql(sql)
    if rows and rows[0]['total'] != '0':
        total = int(rows[0]['total'])
        comp  = int(rows[0]['completadas'] or 0)
        pct   = round(comp / total * 100, 1) if total > 0 else 0
        return total, comp, pct
    return 0, 0, 0

def make_pdf(args):
    actividades = get_actividades(args.course_id, args.user_id)
    nota_final  = get_nota_final(args.course_id, args.user_id)
    total, comp, pct = get_completion_pct(args.course_id, args.user_id)

    fname = f"{args.output}/{args.filename}"
    doc = SimpleDocTemplate(fname, pagesize=A4,
        leftMargin=2*cm, rightMargin=2*cm, topMargin=2*cm, bottomMargin=2*cm)

    title_s = ParagraphStyle('t', fontSize=16, textColor=AZUL, fontName='Helvetica-Bold', alignment=TA_CENTER, spaceAfter=4)
    sub_s   = ParagraphStyle('s', fontSize=10, textColor=GRIS, fontName='Helvetica-Oblique', alignment=TA_CENTER, spaceAfter=4)
    footer_s= ParagraphStyle('f', fontSize=7,  textColor=GRIS, fontName='Helvetica-Oblique', alignment=TA_CENTER)

    def hdr(text):
        t = Table([[text]], colWidths=[17*cm])
        t.setStyle(TableStyle([
            ('BACKGROUND',(0,0),(-1,-1),AZUL),('TEXTCOLOR',(0,0),(-1,-1),white),
            ('FONTNAME',(0,0),(-1,-1),'Helvetica-Bold'),('FONTSIZE',(0,0),(-1,-1),10),
            ('LEFTPADDING',(0,0),(-1,-1),10),('TOPPADDING',(0,0),(-1,-1),6),('BOTTOMPADDING',(0,0),(-1,-1),6),
        ]))
        return t

    def info_table(data):
        t = Table(data, colWidths=[5*cm, 12*cm])
        t.setStyle(TableStyle([
            ('BACKGROUND',(0,0),(0,-1),AZUL_CL),('TEXTCOLOR',(0,0),(0,-1),AZUL),
            ('FONTNAME',(0,0),(0,-1),'Helvetica-Bold'),('FONTNAME',(1,0),(1,-1),'Helvetica'),
            ('FONTSIZE',(0,0),(-1,-1),9),('GRID',(0,0),(-1,-1),0.5,HexColor('#CCCCCC')),
            ('TOPPADDING',(0,0),(-1,-1),5),('BOTTOMPADDING',(0,0),(-1,-1),5),('LEFTPADDING',(0,0),(-1,-1),8),
        ]))
        return t

    act_data = [['Nº','Tipo','Actividad','Estado','Nota']]
    for i, a in enumerate(actividades, 1):
        estado = a.get('completado','NULL')
        nota   = a.get('nota','NULL')
        nombre = str(a.get('actividad','—'))
        if len(nombre) > 65: nombre = nombre[:62]+'...'
        estado_txt = '✓ Completada' if estado in ('1','2') else '— Pendiente'
        nota_txt   = str(nota) if nota and nota != 'NULL' else '—'
        act_data.append([str(i), str(a.get('tipo','—')), nombre, estado_txt, nota_txt])

    act_table = Table(act_data, colWidths=[1*cm,1.5*cm,10*cm,2.8*cm,1.7*cm], repeatRows=1)
    act_style = [
        ('BACKGROUND',(0,0),(-1,0),AZUL2),('TEXTCOLOR',(0,0),(-1,0),white),
        ('FONTNAME',(0,0),(-1,0),'Helvetica-Bold'),('FONTSIZE',(0,0),(-1,-1),8),
        ('GRID',(0,0),(-1,-1),0.3,HexColor('#CCCCCC')),
        ('TOPPADDING',(0,0),(-1,-1),3),('BOTTOMPADDING',(0,0),(-1,-1),3),
        ('LEFTPADDING',(0,0),(-1,-1),4),('VALIGN',(0,0),(-1,-1),'MIDDLE'),
        ('ROWBACKGROUNDS',(0,1),(-1,-1),[white,GRIS_CL]),
    ]
    for i, a in enumerate(actividades, 1):
        estado = a.get('completado','NULL')
        if estado in ('1','2'):
            act_style += [('BACKGROUND',(3,i),(3,i),VERDE_CL),('TEXTCOLOR',(3,i),(3,i),HexColor('#155724'))]
        else:
            act_style += [('BACKGROUND',(3,i),(3,i),ROJO_CL),('TEXTCOLOR',(3,i),(3,i),HexColor('#721c24'))]
    act_table.setStyle(TableStyle(act_style))

    nf = nota_final.get('nota_final','NULL')
    nm = nota_final.get('nota_max','10')
    nf_txt = f"{nf} / {nm}" if nf and nf != 'NULL' else 'Pendiente de cierre'

    story = [
        Paragraph("LIBRO DE CALIFICACIONES Y ESTADO DE FINALIZACIÓN", title_s),
        Paragraph("Formación Programada para Empresas · FTFE 2026 · Micro Ventures S.L.", sub_s),
        Spacer(1, 0.3*cm),
        hdr("1. Datos del participante"),
        Spacer(1, 0.2*cm),
        info_table([
            ["Participante",  args.nombre],
            ["Email",         args.email],
            ["Empresa",       "e2y Commerce, S.L. — CIF B87748331"],
            ["Grupo FUNDAE",  args.fundae or "—"],
            ["Nivel",         args.nivel or "—"],
            ["Course ID",     str(args.course_id)],
            ["URL del curso", f"https://aula.tuspeaking.com/course/view.php?id={args.course_id}"],
        ]),
        Spacer(1, 0.4*cm),
        hdr("2. Resumen de progreso"),
        Spacer(1, 0.2*cm),
        info_table([
            ["Total actividades",        str(total)],
            ["Actividades completadas",  f"{comp} ({pct}%)"],
            ["Actividades pendientes",   str(total - comp)],
            ["Nota final del curso",     nf_txt],
            ["Fecha de extracción",      datetime.now().strftime('%d/%m/%Y %H:%M')],
        ]),
        Spacer(1, 0.4*cm),
        hdr("3. Detalle de actividades y calificaciones"),
        Spacer(1, 0.2*cm),
        act_table,
        Spacer(1, 0.5*cm),
        Paragraph("Micro Ventures S.L. — CIF B71259352 · Datos extraídos de BD Moodle (aulatuspeaking35)", footer_s),
        Paragraph(f"Generado el {datetime.now().strftime('%d/%m/%Y a las %H:%M')} · SEPE Navarra · Expediente B265364AC", footer_s),
    ]
    doc.build(story)
    print(f"OK: {fname} ({comp}/{total} actividades, {pct}%)")

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--email',     required=True)
    parser.add_argument('--user_id',   required=True, type=int)
    parser.add_argument('--course_id', required=True, type=int)
    parser.add_argument('--nombre',    default='')
    parser.add_argument('--fundae',    default='')
    parser.add_argument('--nivel',     default='')
    parser.add_argument('--output',    default='/tmp')
    parser.add_argument('--filename',  default='calificaciones.pdf')
    args = parser.parse_args()
    make_pdf(args)

if __name__ == '__main__':
    main()
