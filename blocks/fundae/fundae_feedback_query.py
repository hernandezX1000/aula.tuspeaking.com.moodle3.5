#!/usr/bin/env python3
"""
fundae_feedback_query.py — Extracción de datos FUNDAE para requerimientos SEPE/Navarra
TEMPORAL — no commitear. Para uso en producción mientras se desarrolla la versión oficial.

Uso:
    python3 fundae_feedback_query.py --email EMAIL_ALUMNO --desde YYYY-MM-DD --hasta YYYY-MM-DD
    python3 fundae_feedback_query.py --email adrian.barrena@e2ycommerce.com --desde 2026-01-12 --hasta 2026-03-31

Salida:
    - Resumen en consola
    - feedback_ALUMNO_PERIODO.tsv  (reportes Jotform)
    - zoom_ALUMNO_PERIODO.tsv      (sesiones Zoom)
    - cobertura_ALUMNO_PERIODO.txt (resumen de cobertura)
"""

import sys
import argparse
import subprocess
from datetime import datetime

# ── Colores para consola ──────────────────────────────────────────────────────
G = "\033[92m"; R = "\033[91m"; Y = "\033[93m"; B = "\033[94m"; E = "\033[0m"

def run_sql(db, sql):
    result = subprocess.run(
        ["sudo", "mysql", db, "-e", sql],
        capture_output=True, text=True
    )
    if result.returncode != 0:
        print(f"{R}Error SQL:{E} {result.stderr}")
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

def get_zoom_sessions(email, desde, hasta):
    sql = f"""
SELECT
    zoom_starttime AS fecha_inicio,
    zoom_endtime   AS fecha_fin,
    zoom_duration  AS duracion_min,
    zoom_topic     AS tema,
    zoom_username  AS tutor,
    zoom_email     AS email_tutor
FROM mdl_i3code_acuityZoom
WHERE studentid = (
    SELECT id FROM mdl_user WHERE email = '{email}' LIMIT 1
)
AND zoom_starttime IS NOT NULL
AND DATE(zoom_starttime) BETWEEN '{desde}' AND '{hasta}'
ORDER BY zoom_starttime;
"""
    return run_sql("aulatuspeaking35", sql)

def get_jotform_feedback(email, desde, hasta):
    sql = f"""
SELECT
    s.id,
    s.submitted_at,
    JSON_UNQUOTE(JSON_EXTRACT(s.raw_data, '$.answers.5.answer.datetime')) AS fecha_sesion,
    JSON_UNQUOTE(JSON_EXTRACT(s.raw_data, '$.answers.12.prettyFormat'))   AS profesor,
    JSON_UNQUOTE(JSON_EXTRACT(s.raw_data, '$.answers.8.answer'))          AS tema,
    JSON_UNQUOTE(JSON_EXTRACT(s.raw_data, '$.answers.7.answer'))          AS vocabulario,
    JSON_UNQUOTE(JSON_EXTRACT(s.raw_data, '$.answers.15.answer'))         AS que_hizo_bien,
    JSON_UNQUOTE(JSON_EXTRACT(s.raw_data, '$.answers.16.answer'))         AS que_mejorar,
    JSON_UNQUOTE(JSON_EXTRACT(s.raw_data, '$.answers.17.answer'))         AS para_proxima,
    JSON_UNQUOTE(JSON_EXTRACT(s.raw_data, '$.answers.36.answer'))         AS progreso,
    JSON_UNQUOTE(JSON_EXTRACT(s.raw_data, '$.answers.37.answer'))         AS pct_completado,
    JSON_UNQUOTE(JSON_EXTRACT(s.raw_data, '$.answers.14.answer'))         AS comentarios
FROM jf_submissions s
WHERE s.form_id = 9
  AND s.raw_data LIKE '%{email}%'
  AND s.submitted_at BETWEEN '{desde}' AND '{hasta} 23:59:59'
  AND JSON_EXTRACT(s.raw_data, '$.answers.5.answer.datetime') IS NOT NULL
ORDER BY fecha_sesion;
"""
    return run_sql("tuspeaking_db", sql)

def save_tsv(rows, filename):
    if not rows:
        return
    with open(filename, "w", encoding="utf-8") as f:
        f.write("\t".join(rows[0].keys()) + "\n")
        for row in rows:
            f.write("\t".join(str(v or "") for v in row.values()) + "\n")

def main():
    parser = argparse.ArgumentParser(description="Extracción datos FUNDAE")
    parser.add_argument("--email",  required=True, help="Email del alumno")
    parser.add_argument("--desde",  required=True, help="Fecha inicio YYYY-MM-DD")
    parser.add_argument("--hasta",  required=True, help="Fecha fin YYYY-MM-DD")
    parser.add_argument("--output", default=".", help="Directorio de salida")
    args = parser.parse_args()

    email  = args.email
    desde  = args.desde
    hasta  = args.hasta
    slug   = email.split("@")[0].replace(".", "_")
    period = f"{desde}_{hasta}"

    print(f"\n{B}{'='*60}{E}")
    print(f"{B}  FUNDAE — Extracción de datos{E}")
    print(f"  Alumno : {email}")
    print(f"  Periodo: {desde} → {hasta}")
    print(f"{B}{'='*60}{E}\n")

    # ── Sesiones Zoom ─────────────────────────────────────────
    print(f"{Y}▶ Consultando sesiones Zoom...{E}")
    zoom = get_zoom_sessions(email, desde, hasta)
    total_min = sum(int(r.get("duracion_min") or 0) for r in zoom)
    print(f"  {G}✓{E} {len(zoom)} sesiones · {total_min} min ({total_min/60:.1f}h)")

    if zoom:
        f_zoom = f"{args.output}/zoom_{slug}_{period}.tsv"
        save_tsv(zoom, f_zoom)
        print(f"  → {f_zoom}")

    # ── Reportes Jotform ──────────────────────────────────────
    print(f"\n{Y}▶ Consultando reportes Jotform...{E}")
    feedback = get_jotform_feedback(email, desde, hasta)
    print(f"  {G}✓{E} {len(feedback)} reportes con feedback")

    if feedback:
        f_feedback = f"{args.output}/feedback_{slug}_{period}.tsv"
        save_tsv(feedback, f_feedback)
        print(f"  → {f_feedback}")

    # ── Cobertura ─────────────────────────────────────────────
    print(f"\n{Y}▶ Resumen de cobertura:{E}")
    fechas_zoom     = {r["fecha_inicio"][:10] for r in zoom}
    fechas_feedback = set()
    for r in feedback:
        fs = r.get("fecha_sesion", "")
        if fs and fs != "NULL":
            fechas_feedback.add(fs[:10])

    sin_reporte = sorted(fechas_zoom - fechas_feedback)
    cobertura   = len(fechas_feedback) / len(fechas_zoom) * 100 if fechas_zoom else 0

    print(f"  Sesiones Zoom      : {len(zoom)}")
    print(f"  Reportes Jotform   : {len(feedback)}")
    print(f"  Cobertura          : {G}{cobertura:.0f}%{E}")

    if sin_reporte:
        print(f"\n  {R}Sesiones sin reporte ({len(sin_reporte)}):{E}")
        for d in sin_reporte:
            print(f"    - {d}")
    else:
        print(f"  {G}✓ Todas las sesiones tienen reporte{E}")

    # ── Guardar resumen ───────────────────────────────────────
    f_cob = f"{args.output}/cobertura_{slug}_{period}.txt"
    with open(f_cob, "w") as f:
        f.write(f"FUNDAE — Resumen cobertura\n")
        f.write(f"Alumno : {email}\n")
        f.write(f"Periodo: {desde} → {hasta}\n")
        f.write(f"Generado: {datetime.now().strftime('%Y-%m-%d %H:%M')}\n\n")
        f.write(f"Sesiones Zoom    : {len(zoom)}\n")
        f.write(f"Reportes Jotform : {len(feedback)}\n")
        f.write(f"Cobertura        : {cobertura:.0f}%\n")
        if sin_reporte:
            f.write(f"\nSesiones sin reporte:\n")
            for d in sin_reporte:
                f.write(f"  - {d}\n")
    print(f"\n  → {f_cob}")
    print(f"\n{G}✓ Extracción completada{E}\n")

if __name__ == "__main__":
    main()
