#!/usr/bin/env python3
"""
Reconcilia own_acuity con Acuity tras la caída del webhook (2-6 ago 2026).

Lista las citas de Acuity desde MIN_DATE que NO están en own_acuity y, con APPLY=1,
las mete disparando el receptor newAcuity.php (idempotente: solo dispara las que faltan).

Contexto: docs/2026-08-06-incidente-feeder-own-acuity.md

Uso (en el servidor Hetzner, como coreadmin):
    export ACUITY_USER=... ACUITY_KEY=...
    python3 reconciliar_own_acuity.py            # DRY-RUN: solo lista lo que falta
    APPLY=1 python3 reconciliar_own_acuity.py     # aplica
    # después: docker exec -u www-data moodle35-app php .../i3code_download_zoomdata.php

Requiere: correr en el host con acceso a `docker exec moodle35-db` (para consultar own_acuity).
"""
import os, sys, json, time, base64, subprocess, urllib.request

ACUITY_USER = os.environ.get("ACUITY_USER")
ACUITY_KEY  = os.environ.get("ACUITY_KEY")
MIN_DATE      = os.environ.get("MIN_DATE", "2026-08-01")
MAX_DATE      = os.environ.get("MAX_DATE", "2026-09-30")  # ventana de FECHA DE CLASE
CREATED_AFTER = os.environ.get("CREATED_AFTER", "2026-08-02")  # solo reservas HECHAS en la caída
APPLY         = os.environ.get("APPLY") == "1"
NEWACUITY   = "https://aula.tuspeaking.com/newAcuity.php"

if not ACUITY_USER or not ACUITY_KEY:
    sys.exit("Define ACUITY_USER y ACUITY_KEY en el entorno.")

def acuity_get(path):
    req = urllib.request.Request("https://acuityscheduling.com/api/v1/" + path)
    tok = base64.b64encode(f"{ACUITY_USER}:{ACUITY_KEY}".encode()).decode()
    req.add_header("Authorization", "Basic " + tok)
    with urllib.request.urlopen(req, timeout=30) as r:
        return json.load(r)

# 1) Citas de Acuity en la ventana [MIN_DATE, MAX_DATE] (paginado), descartando canceladas
appts, page = [], 1
print(f"Descargando citas Acuity {MIN_DATE} → {MAX_DATE} ...", flush=True)
while True:
    batch = acuity_get(f"appointments?minDate={MIN_DATE}&maxDate={MAX_DATE}&max=100&page={page}")
    if not batch:
        break
    appts.extend(batch)
    print(f"  página {page}: +{len(batch)} (total {len(appts)})", flush=True)
    if len(batch) < 100:
        break
    page += 1
total_ventana = len(appts)
# Solo las reservas HECHAS durante la caída (por datetimeCreated), no canceladas.
appts = [a for a in appts
         if not a.get("canceled")
         and a.get("datetimeCreated", "")[:10] >= CREATED_AFTER]
print(f"Acuity: {total_ventana} citas en la ventana; "
      f"{len(appts)} reservadas desde {CREATED_AFTER} (candidatas)", flush=True)

# 2) Cuáles ya están en own_acuity
existing = set()
if appts:
    inlist = ",".join(str(a["id"]) for a in appts)
    sql = f"SELECT acuityid FROM own_acuity WHERE acuityid IN ({inlist});"
    out = subprocess.run(
        ["docker", "exec", "moodle35-db", "sh", "-c",
         f'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -N aulatuspeaking35 -e "{sql}"'],
        capture_output=True, text=True)
    if out.returncode != 0:
        sys.exit("Error consultando own_acuity:\n" + out.stderr)
    existing = set(out.stdout.split())

missing = [a for a in appts if str(a["id"]) not in existing]
print(f"Faltan en own_acuity: {len(missing)}\n")
for a in missing:
    print(f"  {a['id']}  {a['datetime']}  {a.get('email','')}  {a.get('calendar','')}")

# 3) Aplicar: disparar newAcuity.php por cada cita que falta
if not missing:
    print("\nNada que reconciliar.")
elif APPLY:
    print("\nAplicando...")
    for a in missing:
        try:
            req = urllib.request.Request(
                f"{NEWACUITY}?id={a['id']}",
                headers={"User-Agent": "curl/8.4 reconciliador"})  # Apache del aula bloquea UA de urllib
            urllib.request.urlopen(req, timeout=30).read()
            print("  OK ", a["id"])
        except Exception as e:
            print("  ERR", a["id"], e)
        time.sleep(0.5)
    print("\nHecho. Relanza la ingesta: docker exec -u www-data moodle35-app php "
          "/var/www/html/app/moodle/admin/cli/i3code_download_zoomdata.php")
else:
    print("\nDRY-RUN. Revisa la lista y, si cuadra, relanza con  APPLY=1  delante.")
