#!/usr/bin/env python3
"""
AC-5 — Prueba del ESTILO del feedback, sin tocar Moodle.

Genera comentarios de ejemplo con los prompts reales para comprobar tres cosas antes de
desplegar:

  1. que el feedback sale en el idioma del curso,
  2. que tutea y suena a persona, no a plantilla,
  3. que NO se repite la misma estructura en todos los comentarios.

No lee ni escribe en la base de datos. Solo llama a la API de Claude.

Uso (en el servidor, con el venv del autocorrector):

    source /home/coreadmin/venv-autocorrect/bin/activate
    export ANTHROPIC_API_KEY='sk-ant-...'
    python3 test_feedback_style.py
"""

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import hansel_autocorrect as ac


# Transcripciones/textos de ejemplo con errores típicos de aprendiz.
MUESTRAS = [
    {
        'tipo': 'audio',
        'curso': '2026.2 - GDES - Frances B2',
        'nivel': 'B2',
        'nombre': 'Nicolás',
        'tarea': 'Entrega: Audio',
        'texto': (
            "Bonjour, aujourd'hui je vais parler de la maison et la technologie. "
            "Ma maison est equipé avec beaucoup de appareils intelligents. "
            "J'ai un thermostat qui je peux controler avec mon telephone. "
            "Je pense que la technologie est très utile mais aussi il y a des risques "
            "pour la vie privée. Voilà, c'est tout, merci."
        ),
    },
    {
        'tipo': 'audio',
        'curso': '2026.2 - GDES - Frances A2',
        'nivel': 'A2',
        'nombre': 'Eduardo',
        'tarea': 'Entrega: Audio',
        'texto': (
            "Salut, je m'appelle Eduardo. Le matin je me leve à sept heures. "
            "Après je prends le petit dejeuner avec ma famille et je vais au travail "
            "en voiture. Le soir je regarde la television."
        ),
    },
    {
        'tipo': 'texto',
        'curso': '2026.2 - GDES - Frances B2',
        'nivel': 'B2',
        'nombre': 'Noelia',
        'tarea': 'Entrega: Rédaction',
        'texto': (
            "Dans mon travail, la communication est très important. Chaque semaine nous "
            "avons des reunions avec l'equipe pour parler des projets en cours. "
            "Je pense que le plus difficile c'est de expliquer les problèmes techniques "
            "aux personnes qui ne sont pas techniciens. Pour ça, j'essaie de utiliser "
            "des exemples simples et concrets."
        ),
    },
]


def main():
    if not ac.CLAUDE_API_KEY or not ac.CLAUDE_API_KEY.startswith('sk-ant-'):
        print("ERROR: falta ANTHROPIC_API_KEY")
        sys.exit(1)

    for i, m in enumerate(MUESTRAS, 1):
        lang = ac.detect_course_language(m['curso'])

        print("=" * 72)
        print(f"[{i}] {m['tipo'].upper()} · {m['curso']} · nivel {m['nivel']}")
        print(f"    idioma detectado: {lang}  (debe ser 'fr')")
        print("=" * 72)

        if m['tipo'] == 'audio':
            r = ac.call_claude_audio(m['texto'], m['nivel'], m['tarea'], lang, m['nombre'])
        else:
            r = ac.call_claude_writing(m['texto'], m['nivel'], m['tarea'], lang, m['nombre'])

        print(f"Nota: {r['grade']}/10")
        print(f"Feedback:\n{r['feedback']}\n")

    print("=" * 72)
    print("REVISA A MANO:")
    print("  · ¿Están los tres en francés, sin una sola palabra en inglés?")
    print("  · ¿Tutean?")
    print("  · ¿Empiezan los tres igual? Si sí, el prompt sigue siendo demasiado rígido.")
    print("  · ¿Aparece alguna flecha → o algún 'Correction:'? No debería.")
    print("  · ¿Citan algo concreto que dijo el alumno?")
    print("  · ¿Te los firmarías?")
    print("=" * 72)


if __name__ == '__main__':
    main()
