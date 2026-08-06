#!/usr/bin/env python3
"""
fundae_zoom_moodle.py — Extracción de sesiones Zoom para requerimientos FUNDAE
Ejecutar en: aula.tuspeaking.com (servidor Moodle)
BD: aulatuspeaking35 · Tabla: mdl_i3code_acuityZoom

Uso:
    python3 fundae_zoom_moodle.py --email EMAIL --desde YYYY-MM-DD --hasta YYYY-MM-DD
    python3 fundae_zoom_moodle.py --emails EMAIL1,EMAIL2,EMAIL3 --desde 2026-01-12 --hasta 2026-03-31
    python3 fundae_zoom_moodle.py --categoria 515 --desde 2026-01-12 --hasta 2026-03-31

Salida:
    - Resumen en consola con trazabilidad completa
    - zoom_ALUMNO_PERIODO.tsv por cada alumno
    - resumen_cobertura_PERIODO.txt con totales
"""

import argparse
import subprocess
import getpass
from datetime import datetime

G = "\033[92m"; R = "\033[91m"; Y = "\033[93m"; B = "\033[94m"; E = "\033[0m"

_password = None

def get_password():
    global _password
    if _password is None:
        _password = getpass.getpass("Contraseña MySQL (moodle35): ")
    return _password

def run_sql(sql):
    pwd = get_password()
    result = subprocess.run(
        ["mysql", "-u", "moodle35", f"-p{pwd}", "aulatuspeaking35", "-e", sql],
        capture_output=True, text=True
    )
    if result.returncode != 0:
        print(f"{R}Error SQL:{E} {result.stderr.strip()}")
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

def get_zoom_sessions(emails, desde, hasta):
    email_list = "','".join(emails)
    sql = f"""
SELECT
    u.firstname                         AS nombre,
    u.lastname                          AS apellidos,
    u.email                             AS email_alumno,
    z.zoom_meetingid                    AS id_reunion_zoom,
    z.zoom_starttime                    AS fecha_hora_inicio,
    z.zoom_endtime                      AS fecha_hora_fin,
    z.zoom_duration                     AS duracion_min,
    z.zoom_topic                        AS descripcion_sesion,
    z.zoom_username                     AS tutor,
    z.zoom_email                        AS email_tutor,
    z.zoom_participants                 AS participantes,
    z.zoom_clasecompletada              AS clase_completada
FROM mdl_i3code_acuityZoom z
JOIN mdl_user u ON u.id = z.studentid
WHERE u.email IN ('{email_list}')
  AND z.zoom_starttime IS NOT NULL
  AND DATE(z.zoom_starttime) BETWEEN '{desde}' AND '{hasta}'
ORDER BY u.lastname, z.zoom_starttime;
"""
    return run_sql(sql)

def get_zoom_by_categoria(cat_id, desde, hasta):
    sql = f"""
SELECT
    u.firstname                         AS nombre,
    u.lastname                          AS apellidos,
    u.email                             AS email_alumno,
    z.zoom_meetingid                    AS id_reunion_zoom,
    z.zoom_starttime                    AS fecha_hora_inicio,
    z.zoom_endtime                      AS fecha_hora_fin,
    z.zoom_duration                     AS duracion_min,
    z.zoom_topic                        AS descripcion_sesion,
    z.zoom_username                     AS tutor,
    z.zoom_email                        AS email_tutor,
    z.zoom_participants                 AS participantes
FROM mdl_i3code_acuityZoom z
JOIN mdl_user u ON u.id = z.studentid
JOIN mdl_user_enrolments ue ON ue.userid = u.id
JOIN mdl_enrol e ON e.id = ue.enrolid
JOIN mdl_course c ON c.id = e.courseid
WHERE c.category = {cat_id}
  AND z.zoom_starttime IS NOT NULL
  AND DATE(z.zoom_starttime) BETWEEN '{desde}' AND '{hasta}'
ORDER BY u.lastname, z.zoom_starttime;
"""
    return run_sql(sql)

def save_tsv(rows, filename):
    if not rows:
        return
    with open(filename, "w", encoding="utf-8") as f:
        f.write("\t".join(rows[0].keys()) + "\n")
        for row in rows:
            f.write("\t".join(str(v or "") for v in row.values()) + "\n")

def procesar(rows, desde, hasta, output):
    if not rows:
        print(f"  {R}Sin sesiones en el periodo{E}")
        return

    # Agrupar por alumno
    alumnos = {}
    for r in rows:
        email = r.get("email_alumno", "")
        if email not in alumnos:
            alumnos[email] = {"nombre": f"{r.get('nombre','')} {r.get('apellidos','')}".strip(), "sesiones": []}
        alumnos[email]["sesiones"].append(r)

    resumen_lines = [f"FUNDAE — Resumen sesiones Zoom\nPeriodo: {desde} → {hasta}\nGenerado: {datetime.now().strftime('%Y-%m-%d %H:%M')}\n"]

    for email, data in alumnos.items():
        sesiones = data["sesiones"]
        total_min = sum(int(r.get("duracion_min") or 0) for r in sesiones)
        tutores = list(dict.fromkeys(r.get("tutor","") for r in sesiones))
        cortas = [r for r in sesiones if int(r.get("duracion_min") or 0) < 5]

        slug = email.split("@")[0].replace(".", "_")
        period = f"{desde}_{hasta}"
        fname = f"{output}/zoom_{slug}_{period}.tsv"
        save_tsv(sesiones, fname)

        print(f"\n  {G}✓{E} {data['nombre']} ({email})")
        print(f"    Sesiones  : {len(sesiones)}")
        print(f"    Total     : {total_min} min ({total_min/60:.1f}h)")
        print(f"    Tutores   : {', '.join(tutores)}")
        if cortas:
            print(f"    {Y}⚠️  {len(cortas)} sesión(es) < 5 min{E}")
        print(f"    → {fname}")

        resumen_lines.append(
            f"{data['nombre']} ({email})\n"
            f"  Sesiones: {len(sesiones)} · {total_min} min ({total_min/60:.1f}h)\n"
            f"  Tutores: {', '.join(tutores)}\n"
            + (f"  ⚠️ {len(cortas)} sesión(es) < 5 min\n" if cortas else "")
        )

    resumen_file = f"{output}/resumen_zoom_{desde}_{hasta}.txt"
    with open(resumen_file, "w") as f:
        f.write("\n".join(resumen_lines))
    print(f"\n  → Resumen: {resumen_file}")

def main():
    parser = argparse.ArgumentParser(description="Extracción sesiones Zoom FUNDAE — Moodle")
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--email",     help="Email de un alumno")
    group.add_argument("--emails",    help="Emails separados por coma")
    group.add_argument("--categoria", help="ID de categoría Moodle (empresa)")
    parser.add_argument("--desde",    required=True, help="Fecha inicio YYYY-MM-DD")
    parser.add_argument("--hasta",    required=True, help="Fecha fin YYYY-MM-DD")
    parser.add_argument("--output",   default=".", help="Directorio de salida")
    args = parser.parse_args()

    print(f"\n{B}{'='*60}{E}")
    print(f"{B}  FUNDAE — Sesiones Zoom (Moodle){E}")
    print(f"  Periodo: {args.desde} → {args.hasta}")
    print(f"{B}{'='*60}{E}\n")

    print(f"{Y}▶ Consultando mdl_i3code_acuityZoom...{E}")

    if args.email:
        rows = get_zoom_sessions([args.email], args.desde, args.hasta)
    elif args.emails:
        emails = [e.strip() for e in args.emails.split(",")]
        rows = get_zoom_sessions(emails, args.desde, args.hasta)
    else:
        rows = get_zoom_by_categoria(args.categoria, args.desde, args.hasta)

    print(f"  {G}✓{E} {len(rows)} registros encontrados")
    procesar(rows, args.desde, args.hasta, args.output)
    print(f"\n{G}✓ Extracción completada{E}\n")

if __name__ == "__main__":
    main()
