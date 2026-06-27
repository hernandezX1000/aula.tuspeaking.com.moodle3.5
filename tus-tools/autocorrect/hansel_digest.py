#!/usr/bin/env python3
"""
hansel_digest.py — Digest diario de correcciones automáticas tuSpeaking
Envía email a hfernandez@tuspeaking.com con el audit de las últimas 24h.
Cron: 0 7 * * *
"""

import json
import os
import re
import smtplib
import subprocess
import sys
from datetime import datetime, timedelta
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText

import pymysql

# ──────────────────────────────────────────────────────────────
# CONFIGURACIÓN
# ──────────────────────────────────────────────────────────────

DB_HOST     = 'localhost'
DB_USER     = 'moodle35'
DB_PASS     = 'TuspeakingFix2025!'
DB_NAME     = 'aulatuspeaking35'

LOG_FILE    = '/home/aulatuspeaking/logs/hansel_autocorrect.log'
DIGEST_TO   = 'hfernandez@tuspeaking.com'
DIGEST_FROM = 'no-reply@tuspeaking.com'

GRADER_NAMES = {
    14:   'Hansel (auto)',
    4414: 'Tutors tuSpeaking',
}

COMPLETION_LABELS = {
    0: ('—', '#999'),
    1: ('✔ Completada', '#27ae60'),
    2: ('✔ Completada (nota)', '#27ae60'),
}

# ──────────────────────────────────────────────────────────────
# BASE DE DATOS
# ──────────────────────────────────────────────────────────────

def get_conn():
    return pymysql.connect(
        host=DB_HOST, user=DB_USER, password=DB_PASS,
        database=DB_NAME, charset='utf8mb4',
        cursorclass=pymysql.cursors.DictCursor,
    )


def fetch_grades_last_24h(conn):
    """Grades guardadas en las últimas 24h por el autocorrector (grader 14 o 4414)."""
    sql = """
        SELECT
            ag.assignment,
            ag.userid,
            ag.grade,
            ag.grader,
            ag.timemodified,
            FROM_UNIXTIME(ag.timemodified) AS graded_at,
            u.firstname,
            u.lastname,
            u.email                          AS student_email,
            c.fullname                       AS course_name,
            a.name                           AS assign_name,
            afc.commenttext                  AS feedback,
            cmc.completionstate,
            FROM_UNIXTIME(cmc.timemodified)  AS completed_at
        FROM mdl_assign_grades ag
        JOIN mdl_user    u  ON u.id  = ag.userid
        JOIN mdl_assign  a  ON a.id  = ag.assignment
        JOIN mdl_course  c  ON c.id  = a.course
        LEFT JOIN mdl_assignfeedback_comments afc
            ON  afc.assignment = ag.assignment
            AND afc.grade      = ag.id
        LEFT JOIN mdl_course_modules cm
            ON  cm.instance = a.id
            AND cm.module   = (SELECT id FROM mdl_modules WHERE name='assign')
        LEFT JOIN mdl_course_modules_completion cmc
            ON  cmc.coursemoduleid = cm.id
            AND cmc.userid         = ag.userid
        WHERE ag.grader IN (14, 4414)
          AND ag.timemodified >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 24 HOUR))
        ORDER BY ag.timemodified DESC
    """
    with conn.cursor() as cur:
        cur.execute(sql)
        return cur.fetchall()


def fetch_ips_last_24h(conn):
    """IPs registradas en mdl_logstore_standard_log para las correcciones del autocorrector."""
    sql = """
        SELECT
            objectid      AS assignment_id,
            relateduserid AS student_userid,
            ip,
            timecreated
        FROM mdl_logstore_standard_log
        WHERE userid    IN (14, 4414)
          AND component  = 'mod_assign'
          AND action     = 'graded'
          AND timecreated >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 24 HOUR))
        ORDER BY timecreated DESC
    """
    with conn.cursor() as cur:
        cur.execute(sql)
        rows = cur.fetchall()

    # Índice: (assignment_id, student_userid) → ip más reciente
    index = {}
    for r in rows:
        key = (r['assignment_id'], r['student_userid'])
        if key not in index:
            index[key] = r['ip']
    return index


# ──────────────────────────────────────────────────────────────
# PARSING DEL LOG
# ──────────────────────────────────────────────────────────────

def parse_log_last_24h():
    """Lee el log del autocorrector y extrae líneas de las últimas 24h."""
    cutoff = datetime.now() - timedelta(hours=24)
    lines_all  = []
    errors     = []
    runs_ok    = 0
    runs_error = 0

    if not os.path.exists(LOG_FILE):
        return lines_all, errors, runs_ok, runs_error

    ts_re  = re.compile(r'^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})')
    err_re = re.compile(r'\[(ERROR|WARNING)\]')
    done_re = re.compile(r'DONE — Writings: (\d+) \| Audio: (\d+)')
    exc_re  = re.compile(r'exception|traceback|error', re.IGNORECASE)

    with open(LOG_FILE, encoding='utf-8', errors='replace') as f:
        for line in f:
            m = ts_re.match(line)
            if not m:
                continue
            try:
                ts = datetime.strptime(m.group(1), '%Y-%m-%d %H:%M:%S')
            except ValueError:
                continue
            if ts < cutoff:
                continue
            lines_all.append(line.rstrip())
            if err_re.search(line):
                errors.append(line.rstrip())
            dm = done_re.search(line)
            if dm:
                w = int(dm.group(1))
                a = int(dm.group(2))
                if w + a > 0:
                    runs_ok += 1
                else:
                    runs_ok += 1  # corrió sin errores aunque sin items
            if exc_re.search(line) and '[INFO]' not in line:
                if 'Error processing' in line or 'exception' in line.lower():
                    runs_error += 1

    return lines_all, errors, runs_ok, runs_error


# ──────────────────────────────────────────────────────────────
# GENERACIÓN DEL EMAIL HTML
# ──────────────────────────────────────────────────────────────

def truncate(text, n=120):
    if not text:
        return '—'
    text = re.sub(r'<[^>]+>', '', str(text))  # strip HTML tags
    return text[:n] + '…' if len(text) > n else text


def build_html(grades, ip_index, errors, runs_ok, runs_error, log_lines):
    today = datetime.now().strftime('%d/%m/%Y')
    total = len(grades)

    completion_ok  = sum(1 for g in grades if (g['completionstate'] or 0) >= 1)
    completion_no  = total - completion_ok

    # ── Colores ────────────────────────────────────────────────
    COLOR_OK  = '#27ae60'
    COLOR_ERR = '#e74c3c'
    COLOR_HDR = '#2c3e50'
    COLOR_ROW = '#f9f9f9'

    # ── Filas de la tabla ──────────────────────────────────────
    rows_html = ''
    for i, g in enumerate(grades):
        bg = '#ffffff' if i % 2 == 0 else COLOR_ROW
        key        = (g['assignment'], g['userid'])
        ip         = ip_index.get(key, '—')
        comp_state = g['completionstate'] or 0
        comp_label, comp_color = COMPLETION_LABELS.get(comp_state, ('—', '#999'))
        grader_label = GRADER_NAMES.get(g['grader'], f"uid={g['grader']}")
        grade_val    = f"{g['grade']:.1f}" if g['grade'] is not None else '—'
        feedback_txt = truncate(g['feedback'], 110)
        graded_ts    = str(g['graded_at'])[:16] if g['graded_at'] else '—'

        rows_html += f"""
        <tr style="background:{bg}">
            <td style="padding:8px 10px">{graded_ts}</td>
            <td style="padding:8px 10px"><strong>{g['firstname']} {g['lastname']}</strong><br>
                <span style="color:#666;font-size:12px">{g['student_email']}</span></td>
            <td style="padding:8px 10px;font-size:12px">{g['course_name']}<br>
                <em>{g['assign_name']}</em></td>
            <td style="padding:8px 10px;text-align:center;font-weight:bold">{grade_val}</td>
            <td style="padding:8px 10px;font-size:12px">{grader_label}</td>
            <td style="padding:8px 10px;font-family:monospace;font-size:12px">{ip}</td>
            <td style="padding:8px 10px;color:{comp_color};font-size:12px">{comp_label}</td>
            <td style="padding:8px 10px;font-size:12px;color:#555">{feedback_txt}</td>
        </tr>"""

    # ── Errores del log ────────────────────────────────────────
    errors_html = ''
    if errors:
        errs_text = '\n'.join(errors[-30:])  # últimas 30 líneas de error
        errors_html = f"""
        <h3 style="color:{COLOR_ERR};margin-top:32px">⚠ Errores en log ({len(errors)})</h3>
        <pre style="background:#fff5f5;border:1px solid #fcc;padding:12px;font-size:12px;
                    overflow-x:auto;border-radius:4px">{errs_text}</pre>"""

    # ── Estado del cron ────────────────────────────────────────
    cron_color = COLOR_OK if runs_error == 0 else COLOR_ERR
    cron_label = 'OK' if runs_error == 0 else (str(runs_error) + ' ejecucion(es) con error')

    # ── KPIs (pre-build para evitar f-strings anidados en Python 3.9) ──
    comp_no_color = '#e67e22' if completion_no else '#aaa'
    err_color     = COLOR_ERR if errors else '#aaa'
    kpis_html = (
        '<div style="flex:1;padding:18px 24px;border-right:1px solid #eee;text-align:center">'
        '<div style="font-size:28px;font-weight:bold;color:' + COLOR_HDR + '">' + str(total) + '</div>'
        '<div style="font-size:12px;color:#666;margin-top:4px">Entregas procesadas</div></div>'

        '<div style="flex:1;padding:18px 24px;border-right:1px solid #eee;text-align:center">'
        '<div style="font-size:28px;font-weight:bold;color:' + COLOR_OK + '">' + str(completion_ok) + '</div>'
        '<div style="font-size:12px;color:#666;margin-top:4px">Actividades completadas</div></div>'

        '<div style="flex:1;padding:18px 24px;border-right:1px solid #eee;text-align:center">'
        '<div style="font-size:28px;font-weight:bold;color:' + comp_no_color + '">' + str(completion_no) + '</div>'
        '<div style="font-size:12px;color:#666;margin-top:4px">Sin completar</div></div>'

        '<div style="flex:1;padding:18px 24px;border-right:1px solid #eee;text-align:center">'
        '<div style="font-size:28px;font-weight:bold;color:' + err_color + '">' + str(len(errors)) + '</div>'
        '<div style="font-size:12px;color:#666;margin-top:4px">Errores en log</div></div>'

        '<div style="flex:1;padding:18px 24px;text-align:center">'
        '<div style="font-size:20px;font-weight:bold;color:' + cron_color + '">' + cron_label + '</div>'
        '<div style="font-size:12px;color:#666;margin-top:4px">Estado cron</div></div>'
    )

    # ── Tabla de entregas (pre-build) ──────────────────────────
    if not grades:
        table_html = '<p style="color:#aaa">Sin entregas en las ultimas 24 horas.</p>'
    else:
        table_html = (
            '<div style="overflow-x:auto">'
            '<table style="width:100%;border-collapse:collapse;font-size:13px">'
            '<thead><tr style="background:' + COLOR_HDR + ';color:#fff">'
            '<th style="padding:10px;text-align:left;white-space:nowrap">Timestamp</th>'
            '<th style="padding:10px;text-align:left">Alumno</th>'
            '<th style="padding:10px;text-align:left">Curso / Actividad</th>'
            '<th style="padding:10px;text-align:center">Nota</th>'
            '<th style="padding:10px;text-align:left">Tutor corrector</th>'
            '<th style="padding:10px;text-align:left">IP registrada</th>'
            '<th style="padding:10px;text-align:left">Completion</th>'
            '<th style="padding:10px;text-align:left">Feedback (resumen)</th>'
            '</tr></thead>'
            '<tbody>' + rows_html + '</tbody>'
            '</table></div>'
        )

    html = (
        '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
        '<title>Digest autocorreccion ' + today + '</title></head>'
        '<body style="font-family:Arial,sans-serif;color:#333;margin:0;padding:0;background:#f4f4f4">'
        '<div style="max-width:1100px;margin:24px auto;background:#fff;border-radius:6px;'
        'box-shadow:0 2px 8px rgba(0,0,0,.1);overflow:hidden">'

        '<!-- Cabecera -->'
        '<div style="background:' + COLOR_HDR + ';padding:20px 28px;color:#fff">'
        '<h1 style="margin:0;font-size:20px">Digest autocorreccion Hansel</h1>'
        '<p style="margin:4px 0 0;opacity:.8">' + today + ' - generado a las 07:00</p>'
        '</div>'

        '<!-- KPIs -->'
        '<div style="display:flex;gap:0;border-bottom:1px solid #eee">'
        + kpis_html +
        '</div>'

        '<!-- Tabla entregas -->'
        '<div style="padding:24px 28px">'
        '<h2 style="margin:0 0 16px;font-size:16px;color:' + COLOR_HDR + '">Entregas ultimas 24h</h2>'
        + table_html +
        '</div>'

        + errors_html +

        '<!-- Footer -->'
        '<div style="background:#f4f4f4;padding:14px 28px;font-size:11px;color:#999;border-top:1px solid #eee">'
        'Script: /home/aulatuspeaking/scripts/hansel_autocorrect.py &nbsp;·&nbsp;'
        'Log: ' + LOG_FILE + ' &nbsp;·&nbsp;'
        'BD: ' + DB_NAME +
        '</div>'

        '</div></body></html>'
    )

    return html


# ──────────────────────────────────────────────────────────────
# ENVÍO DE EMAIL
# ──────────────────────────────────────────────────────────────

def send_email_sendmail(subject, html_body, to=DIGEST_TO, from_addr=DIGEST_FROM):
    """Envía el email usando sendmail (disponible en el servidor Moodle)."""
    msg = MIMEMultipart('alternative')
    msg['Subject'] = subject
    msg['From']    = from_addr
    msg['To']      = to
    msg.attach(MIMEText(html_body, 'html', 'utf-8'))

    raw = msg.as_bytes()
    try:
        proc = subprocess.run(
            ['/usr/sbin/sendmail', '-t', '-oi'],
            input=raw, capture_output=True, timeout=30
        )
        if proc.returncode != 0:
            raise RuntimeError(proc.stderr.decode('utf-8', errors='replace'))
        print(f"Email enviado a {to} via sendmail")
    except FileNotFoundError:
        # Fallback: SMTP localhost
        send_email_smtp(subject, html_body, to, from_addr)


def send_email_smtp(subject, html_body, to=DIGEST_TO, from_addr=DIGEST_FROM):
    """Fallback: SMTP en localhost:25."""
    msg = MIMEMultipart('alternative')
    msg['Subject'] = subject
    msg['From']    = from_addr
    msg['To']      = to
    msg.attach(MIMEText(html_body, 'html', 'utf-8'))

    with smtplib.SMTP('localhost', 25, timeout=30) as smtp:
        smtp.sendmail(from_addr, [to], msg.as_string())
    print(f"Email enviado a {to} via SMTP localhost")


# ──────────────────────────────────────────────────────────────
# MAIN
# ──────────────────────────────────────────────────────────────

def main():
    today = datetime.now().strftime('%d/%m/%Y')
    print(f"[hansel_digest] Generando digest {today}…")

    # 1. Leer log
    log_lines, errors, runs_ok, runs_error = parse_log_last_24h()
    print(f"  Log: {len(log_lines)} líneas | {len(errors)} errores | {runs_ok} ejecuciones OK")

    # 2. Consultar BD
    try:
        conn = get_conn()
        grades   = fetch_grades_last_24h(conn)
        ip_index = fetch_ips_last_24h(conn)
        conn.close()
        print(f"  BD: {len(grades)} entregas procesadas en 24h")
    except Exception as e:
        print(f"  ERROR conectando BD: {e}")
        grades   = []
        ip_index = {}
        errors.append(f"[ERROR BD] {e}")

    # 3. Construir HTML
    html = build_html(grades, ip_index, errors, runs_ok, runs_error, log_lines)

    # 4. Guardar copia local para debug
    debug_path = '/home/aulatuspeaking/logs/hansel_digest_last.html'
    try:
        with open(debug_path, 'w', encoding='utf-8') as f:
            f.write(html)
    except Exception:
        pass

    # 5. Enviar email
    n_processed = len(grades)
    n_errors    = len(errors)
    subject = (
        f"[tuSpeaking] Autocorrección {today} — "
        f"{n_processed} entrega{'s' if n_processed != 1 else ''} procesada{'s' if n_processed != 1 else ''}"
        + (f" · {n_errors} error{'es' if n_errors != 1 else ''}" if n_errors else "")
    )

    try:
        send_email_sendmail(subject, html)
    except Exception as e:
        print(f"  ERROR enviando email: {e}")
        sys.exit(1)

    print("[hansel_digest] OK")


if __name__ == '__main__':
    main()
