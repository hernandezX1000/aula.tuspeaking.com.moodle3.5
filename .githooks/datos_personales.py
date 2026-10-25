#!/usr/bin/env python3
"""Puerta de datos personales (REPO-5, 25-oct-2026).

Mira SOLO las líneas AÑADIDAS (trinquete: lo viejo no bloquea, lo nuevo sí) y para si encuentra:
  · correos de dominios que no son nuestros (alumnos, RRHH de clientes, proveedores),
  · DNI o NIE con letra de control válida,
  · teléfonos con prefijo +34.
Por qué: en CESCE se filtraron correos y nombres que estaban en tickets, CHANGELOG y SQL del repo.
Los datos de personas van en la BD o en el Project, nunca en el código.

Uso:  python3 .githooks/datos_personales.py --staged        (pre-commit)
      python3 .githooks/datos_personales.py BASE..HEAD       (CI)
Si una línea de verdad debe llevarlo, se añade en ESA línea el texto:  datos-personales: permitido
La salida enmascara lo encontrado (no repite el dato en el registro del CI).
"""
import re
import subprocess
import sys

if len(sys.argv) != 2:
    sys.exit('uso: datos_personales.py --staged | BASE..HEAD')
cmd = ['git', 'diff', '-U0', '--no-color', '--diff-filter=ACMR']
cmd += ['--cached'] if sys.argv[1] == '--staged' else [sys.argv[1]]
diff = subprocess.run(cmd, capture_output=True, text=True, errors='replace').stdout

EXCLUIDOS = {'.githooks/datos_personales.py', '.gitleaks.toml'}
EMAIL = re.compile(r'[A-Za-z0-9._%+-]+@((?:[A-Za-z0-9-]+\.)+([A-Za-z]{2,}))\b')
PROPIOS = re.compile(r'(^|\.)(tuspeaking\.com|example\.(com|org|net)|moodle\.(org|com|net)|github\.com|'
                     r'anthropic\.com|localhost|[a-z0-9-]+\.(test|invalid|example|local))$', re.I)
NO_TLD = {'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'css', 'js', 'php', 'min', 'map', 'ico', 'json', 'html'}
DNI = re.compile(r'\b([0-9]{8})[-\s]?([A-HJ-NP-TV-Z])\b')
NIE = re.compile(r'\b([XYZ])[-\s]?([0-9]{7})[-\s]?([A-HJ-NP-TV-Z])\b')
TEL = re.compile(r'\+34[\s.-]?[6789][0-9]{2}[\s.-]?[0-9]{3}[\s.-]?[0-9]{3}\b')
LETRAS = 'TRWAGMYFPDXBNJZSQVHLCKE'


def tapar(s):
    return s[:2] + '***' if len(s) > 2 else '***'


hallazgos = []
fichero, linea = None, 0
for l in diff.splitlines():
    if l.startswith('+++ '):
        fichero = l[6:] if l.startswith('+++ b/') else None
        continue
    if l.startswith('@@'):
        m = re.search(r'\+(\d+)', l)
        linea = int(m.group(1)) if m else 0
        continue
    if not l.startswith('+') or fichero is None:
        continue
    texto = l[1:]
    n, linea = linea, linea + 1
    if fichero in EXCLUIDOS or 'datos-personales: permitido' in texto:
        continue
    for m in EMAIL.finditer(texto):
        dominio, tld = m.group(1), m.group(2).lower()
        if tld in NO_TLD or PROPIOS.search(dominio):
            continue
        hallazgos.append((fichero, n, 'correo', tapar(m.group(0).split('@')[0]) + '@' + dominio))
    for m in DNI.finditer(texto):
        if LETRAS[int(m.group(1)) % 23] == m.group(2):
            hallazgos.append((fichero, n, 'DNI', tapar(m.group(1))))
    for m in NIE.finditer(texto):
        if LETRAS[int('XYZ'.index(m.group(1)).__str__() + m.group(2)) % 23] == m.group(3):
            hallazgos.append((fichero, n, 'NIE', m.group(1) + '***'))
    for m in TEL.finditer(texto):
        hallazgos.append((fichero, n, 'teléfono', '+34 ***'))

if hallazgos:
    print('\n  ✗ Datos personales en líneas nuevas:')
    for f, n, tipo, v in hallazgos:
        print(f'      {f}:{n}  {tipo}  {v}')
    print('    Los datos de personas van en la BD o en el Project, no en el repo.')
    print('    Si esa línea de verdad debe llevarlo, añádele:  datos-personales: permitido\n')
    sys.exit(1)
