#!/usr/bin/env python3
"""
Reverifica contra Zoom las clases atascadas en estado 3 ("Verificando asistencia")
y acredita (estado=1) las que tengan conexión real del alumno.

Motivo: el sync (i3code_download_zoomdata.php) solo re-baja participantes de un
puñado de reuniones por corrida, así que clases dadas hace semanas se quedan en
estado 3 aunque Zoom sí tenga los datos. Esto las recupera en lote.

Uso (en el SERVIDOR, donde está el env de Zoom):
    set -a; source /home/aulatuspeaking/secrets/zoom_s2s.env; set +a
    mysql -u moodle35 -p'***' aulatuspeaking35 -N -e "
      SELECT id, zoom_meetingid FROM mdl_i3code_acuityZoom
      WHERE zoom_clasecompletada=3 AND acuity_canceled=0 AND zoom_meetingid IS NOT NULL
        AND STR_TO_DATE(SUBSTRING(acuity_datetime,1,19),'%Y-%m-%dT%H:%i:%s')
            BETWEEN DATE_SUB(NOW(),INTERVAL 90 DAY) AND NOW();
    " | python3 reverify_stuck_classes.py           # DRY-RUN (solo cuenta)
    ... | APPLY=1 python3 reverify_stuck_classes.py  # guarda IDs en /tmp/credit_ids.txt

Luego, para acreditar + proteger de la reversión nocturna + recalcular panel,
ver docs/2026-07-09-reproceso-verificando.md (apply_credit.sql).

Requiere variables de entorno: ZOOM_ACCOUNT_ID, ZOOM_CLIENT_ID, ZOOM_CLIENT_SECRET.
Criterio "asistió": algún participante NO-@tuspeaking con duración total > 300 s.
"""
import json, os, sys, time, base64, urllib.request, urllib.error

APPLY = os.environ.get('APPLY') == '1'
acc = os.environ['ZOOM_ACCOUNT_ID']; cid = os.environ['ZOOM_CLIENT_ID']; csec = os.environ['ZOOM_CLIENT_SECRET']

req = urllib.request.Request(
    "https://zoom.us/oauth/token?grant_type=account_credentials&account_id=" + acc, method="POST")
req.add_header("Authorization", "Basic " + base64.b64encode(f"{cid}:{csec}".encode()).decode())
tok = json.load(urllib.request.urlopen(req))['access_token']

stats = {'404': 0, '429': 0, 'other': 0}

def participants(mid):
    url = f"https://api.zoom.us/v2/past_meetings/{mid}/participants?page_size=300"
    for attempt in range(4):
        r = urllib.request.Request(url); r.add_header("Authorization", "Bearer " + tok)
        try:
            return json.load(urllib.request.urlopen(r)).get('participants')
        except urllib.error.HTTPError as e:
            if e.code == 429:
                stats['429'] += 1; time.sleep(2 + attempt); continue
            if e.code == 404:  # reunión inexistente en Zoom (no se celebró / purgada)
                stats['404'] += 1; return None
            stats['other'] += 1; time.sleep(1)
        except Exception:
            stats['other'] += 1; time.sleep(1)
    return None

rows = [ln.strip().split('\t') for ln in sys.stdin if ln.strip()]
credit, noshow, nodata = [], [], []
for cid_, mid in rows:
    ps = participants(mid); time.sleep(0.5)
    if ps is None:
        nodata.append(cid_)
    else:
        stud = sum((p.get('duration') or 0) for p in ps
                   if 'tuspeaking' not in (p.get('user_email') or '').lower())
        (credit if stud > 300 else noshow).append(cid_)

print(f"ASISTIO={len(credit)} no-show={len(noshow)} sin_datos={len(nodata)} "
      f"| 404={stats['404']} 429={stats['429']} otros={stats['other']}")
if APPLY and credit:
    open('/tmp/credit_ids.txt', 'w').write(",".join(credit))
    print("IDs a acreditar en /tmp/credit_ids.txt")
