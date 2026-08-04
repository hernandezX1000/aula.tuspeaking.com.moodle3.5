#!/usr/bin/env python3
"""
send_alert.py — envío de avisos por Gmail SMTP (el mismo que usa Moodle).

Usa las credenciales del .env (nunca hardcodeadas):
    SMTP_HOST=smtp.gmail.com
    SMTP_PORT=587
    SMTP_USER=hfernandez@tuspeaking.com
    SMTP_PASS=<app-password de Gmail>   (= mdl_config.smtppass)
    SMTP_FROM=soporte@tuspeaking.com    (opcional; por defecto = SMTP_USER)
    ALERT_EMAIL=hfernandez@tuspeaking.com

Uso:
    python3 send_alert.py "Asunto" "Cuerpo del mensaje"
    echo "cuerpo" | python3 send_alert.py "Asunto"      # cuerpo por stdin

Como módulo:
    from send_alert import send_alert
    send_alert("Asunto", "Cuerpo")
"""
import sys, os, ssl, smtplib
from email.message import EmailMessage


def _load_env(path='/home/coreadmin/.env'):
    if not os.path.exists(path):
        return
    with open(path) as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith('#') or '=' not in line:
                continue
            k, v = line.split('=', 1)
            os.environ.setdefault(k.strip(), v.strip().strip('"\''))


def send_alert(subject: str, body: str, to: str = None) -> None:
    _load_env()
    host = os.environ.get('SMTP_HOST', 'smtp.gmail.com')
    port = int(os.environ.get('SMTP_PORT', 587))
    user = os.environ['SMTP_USER']
    pwd  = os.environ['SMTP_PASS']
    frm  = os.environ.get('SMTP_FROM', user)
    to   = to or os.environ.get('ALERT_EMAIL', user)

    msg = EmailMessage()
    msg['Subject'] = subject
    msg['From'] = frm
    msg['To'] = to
    msg.set_content(body)

    ctx = ssl.create_default_context()
    with smtplib.SMTP(host, port) as s:
        s.ehlo()
        s.starttls(context=ctx)
        s.login(user, pwd)
        s.send_message(msg)


if __name__ == '__main__':
    subject = sys.argv[1] if len(sys.argv) > 1 else 'Aviso tuSpeaking'
    body = sys.argv[2] if len(sys.argv) > 2 else sys.stdin.read()
    send_alert(subject, body)
    print('OK enviado')
