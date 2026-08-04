#!/usr/bin/env python3
"""
hansel_status_digest.py — informe consolidado del estado de los crons.

Revisa cada proceso (por frescura de su log o por un check concreto), lo agrupa
y manda UN email por Gmail SMTP. Pensado para correr a las 8:00 y 20:00.

Cron:
    0 8,20 * * * /home/coreadmin/venv-autocorrect/bin/python /home/coreadmin/scripts/hansel_status_digest.py >> /home/coreadmin/cron_status_digest.log 2>&1

Uso manual:
    python3 hansel_status_digest.py            # calcula y envía
    python3 hansel_status_digest.py --print     # solo imprime, NO envía (para probar)

Sin dependencias externas (stdlib + send_alert.py en el mismo dir).
"""
import os, sys, time, glob
from datetime import datetime

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
try:
    from send_alert import send_alert
except Exception:
    send_alert = None

HL   = '/home/coreadmin'
ZLOG = '/tmp/i3code_download_zoomdata'   # patrón: ZLOG_%Y%m%d.log
BK   = '/home/coreadmin/backups/mysql'
NOW  = time.time()

# ── Definición de checks ─────────────────────────────────────────────
# tipo 'log'  : ok si el log se escribió hace menos de max_h horas
# tipo 'file' : ok si existe hoy un fichero que casa con el patrón (y pesa > min_kb)
# tipo 'note' : informativo/manual (estado fijo)
CHECKS = [
    ("🟢 DATOS Y ASISTENCIA", [
        dict(t='log',  name='Ingesta Zoom (4:05)',      why='actualiza asistencias',
             path=f'{HL}/cron_ingesta.log', max_h=26),
        dict(t='log',  name='Autocorrector (cada 2h)',   why='corrige writings/audios',
             path=f'{HL}/cron_autocorrect.log', max_h=3, tail_done=True),
        dict(t='log',  name='Quiz grader (cada 4h)',     why='corrige quiz/essays',
             path=f'{HL}/cron_quiz.log', max_h=5),
        dict(t='moodle_cron', name='Moodle cron (4:15)',  why='tareas programadas internas',
             max_h=26),
    ]),
    ("🟢 BACKUPS Y SEGURIDAD", [
        dict(t='file', name='Backup BD principal (2:00)', why='recuperación ante desastre',
             pat=f'{BK}/db_%Y%m%d.sql.gz', min_kb=1000),
        dict(t='file', name='Backup BD CESCE (2:05)',     why='recuperación CESCE',
             pat=f'{BK}/db_cesce_%Y%m%d.sql.gz', min_kb=100),
        dict(t='note', name='Backup offsite (3:00)', why='copia fuera del server',
             status='WARN', msg='pendiente configurar en Hetzner'),
    ]),
    ("🟢 REPORTES", [
        dict(t='note', name='Feedback (cada 30m)',        why='envío de feedback a alumnos',
             status='WARN', msg='pendiente configurar en Hetzner'),
    ]),
    ("🟢 MONITOR", [
        dict(t='note', name='Heartbeat (cada hora)',      why='vigila que los crons corran',
             status='WARN', msg='pendiente configurar en Hetzner'),
    ]),
    ("🔒 SEGURIDAD Y RECURSOS", [
        dict(t='disk', name='Disco',        why='si se llena, se cae todo en silencio', path='/'),
        dict(t='swap', name='Swap',         why='memoria virtual'),
        dict(t='ssl',  name='Certificado SSL', why='si caduca, la web deja de cargar',
             host='aula.tuspeaking.com'),
    ]),
]


def _fmt_age(secs):
    h = secs / 3600.0
    if h < 1:   return f'{int(secs/60)} min'
    if h < 48:  return f'{h:.1f} h'
    return f'{h/24:.1f} d'


def _last_line(path, needle=None):
    try:
        with open(path, errors='ignore') as f:
            lines = [l.strip() for l in f if l.strip()]
        if needle:
            for l in reversed(lines):
                if needle in l:
                    return l
        return lines[-1] if lines else ''
    except Exception:
        return ''


def check_log(c):
    path = datetime.now().strftime(c['path']) if '%' in c['path'] else c['path']
    if not os.path.exists(path):
        return 'FAIL', 'sin log (no existe)'
    age = NOW - os.path.getmtime(path)
    extra = ''
    if c.get('tail_done'):
        d = _last_line(path, 'DONE')
        if d:
            extra = ' · ' + d.split('DONE')[-1].strip(' —')[:48]
    if c.get('tail_last'):
        extra = ' · ' + _last_line(path)[-40:]
    if age <= c['max_h'] * 3600:
        return 'OK', f'última hace {_fmt_age(age)}{extra}'
    if age <= 2 * c['max_h'] * 3600:
        return 'WARN', f'última hace {_fmt_age(age)} (esperado <{c["max_h"]}h){extra}'
    return 'FAIL', f'sin correr hace {_fmt_age(age)} (esperado <{c["max_h"]}h)'


def check_file(c):
    pat = datetime.now().strftime(c['pat'])
    hits = glob.glob(pat)
    if not hits:
        return 'FAIL', 'no existe el backup de hoy'
    kb = os.path.getsize(hits[0]) / 1024.0
    if kb < c.get('min_kb', 0):
        return 'WARN', f'existe pero pequeño ({kb/1024:.1f} MB)'
    return 'OK', f'{os.path.basename(hits[0])} ({kb/1024:.1f} MB)'


def check_disk(c):
    import shutil
    t, u, f = shutil.disk_usage(c.get('path', '/'))
    pct = u / t * 100
    free_gb = f / 1e9
    detail = f'{pct:.0f}% usado · {free_gb:.1f} GB libres'
    if pct >= 95:
        return 'FAIL', detail
    if pct >= 85:
        return 'WARN', detail
    return 'OK', detail


def check_swap(c):
    try:
        mem = {}
        for line in open('/proc/meminfo'):
            k, _, v = line.partition(':')
            mem[k.strip()] = v.strip()
        total = int(mem.get('SwapTotal', '0 kB').split()[0])
        free = int(mem.get('SwapFree', '0 kB').split()[0])
        if total == 0:
            return 'OK', 'sin swap configurado'
        used = (total - free) / total * 100
        return ('WARN' if used >= 90 else 'OK'), f'{used:.0f}% usado'
    except Exception as e:
        return 'WARN', f'no verificable ({e})'


def check_ssl(c):
    import ssl as _ssl, socket
    from datetime import datetime as dt
    host = c.get('host', 'aula.tuspeaking.com')
    try:
        ctx = _ssl.create_default_context()
        with socket.create_connection((host, 443), timeout=8) as sock:
            with ctx.wrap_socket(sock, server_hostname=host) as ss:
                cert = ss.getpeercert()
        exp = dt.strptime(cert['notAfter'], '%b %d %H:%M:%S %Y %Z')
        days = (exp - dt.utcnow()).days
        detail = f'caduca en {days} d ({exp:%d/%m/%Y})'
        if days <= 7:
            return 'FAIL', detail
        if days <= 21:
            return 'WARN', detail
        return 'OK', f'válido {days} d (hasta {exp:%d/%m/%Y})'
    except Exception as e:
        return 'WARN', f'no verificable ({e})'


def check_moodle_cron(c):
    """El cron interno de Moodle no deja log fiable; se comprueba en la BD
    (mdl_task_scheduled.lastruntime), que es la fuente autoritativa."""
    try:
        import mysql.connector
        env = {}
        for p in ('/home/coreadmin/.env', '/home/aulatuspeaking/.env'):
            if os.path.exists(p):
                for line in open(p):
                    line = line.strip()
                    if line and not line.startswith('#') and '=' in line:
                        k, v = line.split('=', 1)
                        env[k.strip()] = v.strip().strip('"\'')
                break
        conn = mysql.connector.connect(
            host=env.get('MOODLE_DB_HOST', '127.0.0.1'),
            port=int(env.get('MOODLE_DB_PORT', 3307)),
            user=env.get('MOODLE_DB_USER', 'moodle35'),
            password=env.get('MOODLE_DB_PASSWORD', ''),
            database=env.get('MOODLE_DB_NAME', 'aulatuspeaking35'))
        cur = conn.cursor()
        cur.execute("SELECT MAX(lastruntime) FROM mdl_task_scheduled")
        row = cur.fetchone()
        cur.close(); conn.close()
        if not row or not row[0]:
            return 'FAIL', 'sin datos de lastruntime'
        age = NOW - int(row[0])
        if age <= c['max_h'] * 3600:
            return 'OK', f'última tarea hace {_fmt_age(age)}'
        if age <= 2 * c['max_h'] * 3600:
            return 'WARN', f'hace {_fmt_age(age)} (esperado <{c["max_h"]}h)'
        return 'FAIL', f'sin correr hace {_fmt_age(age)}'
    except Exception as e:
        return 'WARN', f'no verificable ({e})'


ICON = {'OK': '✅', 'WARN': '⚠️', 'FAIL': '❌'}


def build_report():
    n_ok = n_warn = n_fail = 0
    out = [f'ESTADO tuSpeaking — {datetime.now().strftime("%d/%m %H:%M")}',
           '=' * 44]
    for group, checks in CHECKS:
        out.append('')
        out.append(group)
        for c in checks:
            if c['t'] == 'log':
                st, detail = check_log(c)
            elif c['t'] == 'file':
                st, detail = check_file(c)
            elif c['t'] == 'moodle_cron':
                st, detail = check_moodle_cron(c)
            elif c['t'] == 'disk':
                st, detail = check_disk(c)
            elif c['t'] == 'swap':
                st, detail = check_swap(c)
            elif c['t'] == 'ssl':
                st, detail = check_ssl(c)
            else:
                st, detail = c.get('status', 'OK'), c.get('msg', '')
            n_ok   += st == 'OK'
            n_warn += st == 'WARN'
            n_fail += st == 'FAIL'
            out.append(f'  {ICON[st]} {c["name"]} — {c["why"]}. {detail}')
    out.append('')
    out.append('=' * 44)
    out.append(f'Resumen: {n_ok+n_warn+n_fail} procesos · {n_ok} OK · {n_warn} aviso · {n_fail} fallo')
    return '\n'.join(out), n_ok, n_warn, n_fail


def main():
    report, n_ok, n_warn, n_fail = build_report()
    tag = '🔴' if n_fail else ('🟡' if n_warn else '🟢')
    subject = f'{tag} Estado tuSpeaking {datetime.now().strftime("%d/%m %H:%M")} — {n_fail} fallo, {n_warn} aviso'

    if '--print' in sys.argv or send_alert is None:
        print(report)
        if send_alert is None:
            print('\n[send_alert no disponible — solo impresión]')
        return
    send_alert(subject, report)
    print(f'Digest enviado. {subject}')


if __name__ == '__main__':
    main()
