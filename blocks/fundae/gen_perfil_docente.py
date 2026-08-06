#!/usr/bin/env python3
"""
gen_perfil_docente.py — Generador de perfiles de docentes FUNDAE
Micro Ventures SL · CIF B71259352

Uso:
    python3 gen_perfil_docente.py --user_id 2395          # generar y sincronizar perfil de Amber
    python3 gen_perfil_docente.py --all                    # sincronizar todos los docentes
    python3 gen_perfil_docente.py --list                   # listar docentes registrados
    python3 gen_perfil_docente.py --user_id 2395 --preview # solo mostrar HTML sin guardar

Flujo:
    mdl_fundae_docentes (datos JSON estructurados)
        → genera HTML con template corporativo
        → actualiza mdl_user.description
        → marca perfil_sync = 1
"""

import subprocess
import argparse
import json
import time

DB = "aulatuspeaking35"

# ── Colores corporativos tuSpeaking ──────────────────────────────────────────
COLOR_PRIMARY   = "#008ba3"
COLOR_SECONDARY = "#00bcd4"
COLOR_ACCENT    = "#62eeff"
COLOR_DARK      = "#454545"
COLOR_BG        = "#f7feff"

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

def get_docente(user_id):
    sql = f"""
SELECT d.*, u.firstname, u.lastname, u.email
FROM mdl_fundae_docentes d
JOIN mdl_user u ON u.id = d.user_id
WHERE d.user_id = {user_id}
LIMIT 1;"""
    rows = run_sql(sql)
    return rows[0] if rows else None

def get_all_docentes():
    sql = """
SELECT d.user_id, d.nombre_completo, d.titulo_rol, d.perfil_sync,
       u.email
FROM mdl_fundae_docentes d
JOIN mdl_user u ON u.id = d.user_id
ORDER BY d.nombre_completo;"""
    return run_sql(sql)

def parse_json_field(val):
    if not val or val == 'NULL':
        return []
    try:
        return json.loads(val)
    except:
        return []

def generar_html(d):
    nombre    = d.get('nombre_completo') or f"{d.get('firstname','')} {d.get('lastname','')}".strip()
    titulo    = d.get('titulo_rol') or 'Tutor/a de idiomas online – tuSpeaking'
    perfil    = d.get('perfil_profesional') or ''
    exp       = parse_json_field(d.get('experiencia_json'))
    form      = parse_json_field(d.get('formacion_json'))
    comp      = parse_json_field(d.get('competencias_json'))
    tefl      = d.get('certificado_tefl') == '1'
    horas_tefl= d.get('horas_tefl') or '0'
    anos_tel  = d.get('anos_teleformacion') or '0'
    idiomas   = d.get('idiomas') or ''

    def section(title):
        return f"""
    <div style="margin:14px 0 10px;">
      <div style="font-size:16px;font-weight:800;color:{COLOR_PRIMARY};margin:0 0 6px;">{title}</div>
      <div style="height:2px;background:{COLOR_ACCENT};width:100%;margin:0 0 10px;"></div>"""

    # Header
    html = f"""<div style="font-family:Arial, Helvetica, sans-serif;color:{COLOR_DARK};line-height:1.45;font-size:14px;max-width:900px;">

  <div style="border-left:6px solid {COLOR_SECONDARY};padding:10px 14px;background:{COLOR_BG};margin-bottom:14px;">
    <div style="font-size:24px;font-weight:800;letter-spacing:.3px;color:{COLOR_PRIMARY};text-transform:uppercase;">{nombre}</div>
    <div style="margin-top:4px;font-size:14px;color:{COLOR_DARK};">{titulo}</div>
  </div>"""

    # Perfil profesional
    if perfil:
        html += section("PERFIL PROFESIONAL")
        html += f"\n      <p style='margin:0;'>{perfil}</p>\n    </div>"

    # Experiencia
    if exp:
        html += section("EXPERIENCIA DOCENTE EN TELEFORMACIÓN")
        for e in exp:
            empresa = e.get('empresa','')
            rol     = e.get('rol','')
            desde   = e.get('desde','')
            hasta   = e.get('hasta','actualidad')
            desc    = e.get('descripcion','')
            html += f"\n      <p><strong>{empresa} – {rol}</strong><br />({desde} – {hasta})<br />{desc}</p>"
        html += "\n    </div>"

    # Formación
    if form:
        html += section("FORMACIÓN ACADÉMICA Y CERTIFICACIONES")
        html += "\n      <ul style='margin:0;padding-left:18px;'>"
        for f in form:
            titulo_f = f.get('titulo','')
            inst     = f.get('institucion','')
            ano      = f.get('año','')
            linea = titulo_f
            if inst: linea += f" – {inst}"
            if ano:  linea += f" ({ano})"
            html += f"\n        <li>{linea}</li>"
        if tefl and horas_tefl:
            html += f"\n        <li>Acreditación en teleformación: Certificado TEFL {horas_tefl} horas</li>"
        if anos_tel and int(str(anos_tel)) > 0:
            html += f"\n        <li>Experiencia acreditada en teleformación: más de {anos_tel} años como tutor/a online</li>"
        html += "\n      </ul>\n    </div>"

    # Idiomas
    if idiomas and idiomas != 'NULL':
        html += section("IDIOMAS")
        html += f"\n      <p style='margin:0;'>{idiomas}</p>\n    </div>"

    # Competencias tecnológicas
    if comp:
        html += section("COMPETENCIAS TECNOLÓGICAS PARA TELEFORMACIÓN")
        html += "\n      <ul style='margin:0;padding-left:18px;'>"
        for c in comp:
            competencia = c.get('competencia','')
            desc_c      = c.get('descripcion','')
            html += f"\n        <li><strong>{competencia}</strong> – {desc_c}</li>"
        html += "\n      </ul>\n    </div>"

    html += "\n</div>"
    return html

def sincronizar(user_id, preview=False):
    d = get_docente(user_id)
    if not d:
        print(f"ERROR: No se encontró docente con user_id={user_id}")
        return

    html = generar_html(d)
    nombre = d.get('nombre_completo') or f"{d.get('firstname','')} {d.get('lastname','')}".strip()

    if preview:
        print(f"\n{'='*60}")
        print(f"  Preview perfil: {nombre}")
        print(f"{'='*60}")
        print(html[:500] + "...")
        return

    # Escapar para SQL
    html_escaped = html.replace("'", "\\'").replace("\\", "\\\\")
    ts = int(time.time())

    # Actualizar mdl_user.description
    sql_user = f"""
UPDATE mdl_user
SET description = '{html_escaped}',
    descriptionformat = 1,
    timemodified = {ts}
WHERE id = {user_id};"""
    run_sql(sql_user)

    # Actualizar perfil_html y perfil_sync en mdl_fundae_docentes
    sql_doc = f"""
UPDATE mdl_fundae_docentes
SET perfil_html = '{html_escaped}',
    perfil_sync = 1,
    timemodified = {ts}
WHERE user_id = {user_id};"""
    run_sql(sql_doc)

    print(f"✓ Perfil sincronizado: {nombre} (user_id={user_id})")

def main():
    parser = argparse.ArgumentParser(description='Generador de perfiles docentes FUNDAE')
    parser.add_argument('--user_id', type=int, help='User ID de Moodle')
    parser.add_argument('--all',     action='store_true', help='Sincronizar todos los docentes')
    parser.add_argument('--list',    action='store_true', help='Listar docentes registrados')
    parser.add_argument('--preview', action='store_true', help='Solo mostrar HTML sin guardar')
    args = parser.parse_args()

    if args.list:
        docentes = get_all_docentes()
        print(f"\n{'='*70}")
        print(f"  Docentes registrados en mdl_fundae_docentes ({len(docentes)} total)")
        print(f"{'='*70}")
        print(f"{'user_id':<10} {'sync':<6} {'nombre':<30} {'email'}")
        print("-"*70)
        for d in docentes:
            sync = "✓" if d['perfil_sync'] == '1' else "⏳"
            print(f"{d['user_id']:<10} {sync:<6} {d['nombre_completo']:<30} {d['email']}")
        return

    if args.all:
        docentes = get_all_docentes()
        print(f"\nSincronizando {len(docentes)} docentes...")
        for d in docentes:
            sincronizar(int(d['user_id']), preview=False)
        print(f"\n✓ Completado")
        return

    if args.user_id:
        sincronizar(args.user_id, preview=args.preview)
        return

    parser.print_help()

if __name__ == '__main__':
    main()
