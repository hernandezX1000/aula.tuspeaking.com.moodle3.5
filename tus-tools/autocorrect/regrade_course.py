#!/usr/bin/env python3
"""
AC-5 — Re-corregir entregas YA calificadas por el autocorrector.

Sirve para arreglar las correcciones que salieron en inglés (y, en audio, con nota
injustamente baja) sin esperar a que el alumno entregue algo nuevo.

Por defecto NO escribe nada: enseña lo que pondría. Para guardarlo hay que pasar --apply.

Uso:
    source /home/coreadmin/venv-autocorrect/bin/activate
    set -a; source /home/aulatuspeaking/.env; set +a

    # ver qué haría con un alumno concreto
    python3 regrade_course.py --course 3242 --user 5822

    # ver qué haría con el curso entero
    python3 regrade_course.py --course 3242

    # aplicarlo de verdad
    python3 regrade_course.py --course 3242 --user 5822 --apply
"""

import argparse
import os
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import hansel_autocorrect as ac


SQL = """
SELECT
    ag.id            AS gradeid,
    ag.assignment    AS assignment,
    ag.userid        AS userid,
    ag.grade         AS nota_actual,
    a.name           AS assign_name,
    c.id             AS course_id,
    c.fullname       AS course_name,
    u.firstname      AS firstname,
    u.lastname       AS lastname,
    ao.onlinetext    AS onlinetext,
    f.commenttext    AS feedback_actual
FROM mdl_assign_grades ag
JOIN mdl_assign a ON a.id = ag.assignment
JOIN mdl_course c ON c.id = a.course
JOIN mdl_user   u ON u.id = ag.userid
LEFT JOIN mdl_assign_submission asub
       ON asub.assignment = ag.assignment
      AND asub.userid     = ag.userid
      AND asub.latest     = 1
LEFT JOIN mdl_assignsubmission_onlinetext ao ON ao.submission = asub.id
LEFT JOIN mdl_assignfeedback_comments f      ON f.grade      = ag.id
WHERE c.id = %s
  AND ag.grader IN (%s, %s)
  {filtro_user}
ORDER BY ag.userid, a.name
"""


def main():
    p = argparse.ArgumentParser()
    p.add_argument('--course', type=int, required=True, help='ID del curso')
    p.add_argument('--user',   type=int, help='ID del alumno (opcional)')
    p.add_argument('--apply',  action='store_true', help='guardar de verdad en Moodle')
    p.add_argument('--keep-grade', action='store_true',
                   help='conservar la nota actual y reescribir solo el comentario')
    args = p.parse_args()

    if not ac.CLAUDE_API_KEY or not ac.CLAUDE_API_KEY.startswith('sk-ant-'):
        print("ERROR: falta ANTHROPIC_API_KEY"); sys.exit(1)
    if args.apply and not ac.MOODLE_WS_TOKEN:
        print("ERROR: falta MOODLE_WS_TOKEN y has pedido --apply"); sys.exit(1)

    modo = "APLICANDO CAMBIOS" if args.apply else "SIMULACIÓN (no escribe nada)"
    print(f"\n{'='*72}\n{modo}\n{'='*72}\n")

    conn = ac.get_db()
    sql  = SQL.format(filtro_user='AND ag.userid = %s' if args.user else '')
    par  = [args.course, ac.GRADER_HANSEL, ac.GRADER_EXTERNAL]
    if args.user:
        par.append(args.user)

    cur = conn.cursor(dictionary=True)
    cur.execute(sql, par)
    filas = cur.fetchall()
    cur.close()

    print(f"Entregas corregidas por el autocorrector: {len(filas)}\n")

    hechas = fallidas = 0

    for r in filas:
        curso    = r['course_name']
        nombre   = r['firstname']
        tarea    = r['assign_name']
        nivel    = ac.detect_level(curso)
        nota_max = ac.get_nota_max(curso)
        lang     = ac.detect_course_language(curso)
        es_audio = 'audio' in tarea.lower()

        print("─" * 72)
        print(f"{nombre} {r['lastname']} · {tarea} · {'AUDIO' if es_audio else 'TEXTO'} · {lang}")
        print(f"  Nota actual: {r['nota_actual']}/{nota_max}")
        ant = (r['feedback_actual'] or '').strip()
        if ant:
            print(f"  Feedback actual: {ac.strip_html(ant)[:110]}...")

        try:
            if es_audio:
                info = ac.fetch_audio_file(conn, r['assignment'], r['userid'])
                if not info:
                    print("  SALTADA — no se encuentra el fichero de audio"); continue
                texto = ac.transcribe_audio(info['filepath'], lang)
                if not texto:
                    print("  SALTADA — la transcripción sale vacía"); continue
                res = ac.call_claude_audio(texto, nivel, tarea, lang, nombre)
            else:
                texto = ac.strip_html(r['onlinetext'] or '')
                if len(texto) < ac.MIN_WRITING_CHARS:
                    info = ac.fetch_audio_file(conn, r['assignment'], r['userid'])
                    if not info:
                        print("  SALTADA — sin texto ni fichero"); continue
                    texto = ac.extract_text_from_file(info['filepath'], info['filename'])
                if len(texto) < ac.MIN_WRITING_CHARS:
                    print("  SALTADA — texto demasiado corto"); continue
                res = ac.call_claude_writing(texto, nivel, tarea, lang, nombre)

            calculada = round(res['grade'] * 10, 5) if nota_max == 100.0 else res['grade']

            if args.keep_grade:
                nueva = float(r['nota_actual'] or 0)
                print(f"  NOTA:        {nueva}/{nota_max}  (se conserva; el modelo habría puesto {calculada})")
            else:
                nueva = calculada
                delta = nueva - float(r['nota_actual'] or 0)
                print(f"  NOTA NUEVA:  {nueva}/{nota_max}   ({delta:+.1f})")

            print(f"  FEEDBACK NUEVO:\n    {res['feedback']}")

            if args.apply:
                ac.moodle_save_grade(r['assignment'], r['userid'], nueva, res['feedback'])
                print("  ✓ GUARDADO en Moodle")

            hechas += 1
            time.sleep(ac.API_CALL_DELAY)

        except Exception as e:
            print(f"  ERROR: {e}")
            fallidas += 1

    conn.close()

    print("\n" + "=" * 72)
    print(f"Procesadas: {hechas} · con error: {fallidas}")
    if not args.apply:
        print("SIMULACIÓN — no se ha escrito nada. Repite con --apply para guardarlo.")
    print("=" * 72 + "\n")


if __name__ == '__main__':
    main()
