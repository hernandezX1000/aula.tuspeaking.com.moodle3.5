#!/usr/bin/env python3
"""
AC-6 — Comparar tamaños de modelo Whisper sobre un audio REAL de un alumno.

Motivo: el autocorrector usa `faster-whisper-base`. En inglés basta; en francés produce
transcripciones inventadas ("les tégems managers", "l'économie de spétite voulue") y el
alumno acaba puntuado por errores que no cometió. El audio en francés está en 4,19/10
frente al 6,98/10 del inglés.

Este script transcribe el MISMO audio con varios modelos y enseña los resultados juntos,
con el tiempo que tarda cada uno. No toca la base de datos ni Moodle.

Uso:
    source /home/coreadmin/venv-autocorrect/bin/activate
    set -a; source /home/aulatuspeaking/.env; set +a
    python3 compare_whisper.py --course 3242 --user 5822
    python3 compare_whisper.py --course 3242 --user 5822 --models base,small,medium
"""

import argparse
import os
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import hansel_autocorrect as ac


SQL_AUDIOS = """
SELECT ag.assignment, ag.userid, a.name AS assign_name, ag.grade AS nota_actual,
       c.fullname AS course_name, u.firstname, u.lastname
FROM mdl_assign_grades ag
JOIN mdl_assign a ON a.id = ag.assignment
JOIN mdl_course c ON c.id = a.course
JOIN mdl_user   u ON u.id = ag.userid
WHERE c.id = %s AND LOWER(a.name) LIKE '%%audio%%'
  {filtro_user}
ORDER BY ag.userid, a.name
LIMIT %s
"""


def transcribe_with(model_size: str, filepath: str, lang: str):
    """Transcribe con un tamaño concreto, sin tocar el modelo global del módulo."""
    from faster_whisper import WhisperModel
    t0 = time.time()
    model = WhisperModel(model_size, device='cpu', compute_type='int8')
    segments, info = model.transcribe(filepath, language=lang, beam_size=3)
    texto = ' '.join(s.text for s in segments).strip()
    return texto, time.time() - t0


def main():
    p = argparse.ArgumentParser()
    p.add_argument('--course', type=int, required=True)
    p.add_argument('--user',   type=int)
    p.add_argument('--limit',  type=int, default=2, help='cuántos audios probar')
    p.add_argument('--models', default='base,small,medium')
    args = p.parse_args()

    modelos = [m.strip() for m in args.models.split(',') if m.strip()]

    conn = ac.get_db()
    sql  = SQL_AUDIOS.format(filtro_user='AND ag.userid = %s' if args.user else '')
    par  = [args.course] + ([args.user] if args.user else []) + [args.limit]

    cur = conn.cursor(dictionary=True)
    cur.execute(sql, par)
    filas = cur.fetchall()
    cur.close()

    print(f"\nAudios a comparar: {len(filas)}   ·   modelos: {', '.join(modelos)}\n")

    for r in filas:
        lang = ac.detect_course_language(r['course_name'])
        info = ac.fetch_audio_file(conn, r['assignment'], r['userid'])
        if not info:
            print(f"SALTADO — sin fichero: {r['firstname']} · {r['assign_name']}")
            continue

        print("=" * 72)
        print(f"{r['firstname']} {r['lastname']} · {r['assign_name']} · idioma {lang}")
        print(f"Nota actual: {r['nota_actual']}   ·   fichero: {info['filename']}")
        print("=" * 72)

        for m in modelos:
            try:
                texto, secs = transcribe_with(m, info['filepath'], lang)
                print(f"\n── {m.upper()}  ({secs:.0f}s, {len(texto.split())} palabras)")
                print(f"   {texto}")
            except Exception as e:
                print(f"\n── {m.upper()}  ERROR: {e}")

        print()

    conn.close()

    print("=" * 72)
    print("QUÉ MIRAR:")
    print("  · ¿Desaparecen las palabras inventadas al subir de modelo?")
    print("  · ¿Se entiende de verdad lo que dijo el alumno?")
    print("  · ¿Cuánto tarda? Con pocos audios al día, 1-2 min por audio es asumible.")
    print("  · Elige el modelo más pequeño cuya transcripción sea FIABLE, no el más rápido.")
    print("=" * 72 + "\n")


if __name__ == '__main__':
    main()
