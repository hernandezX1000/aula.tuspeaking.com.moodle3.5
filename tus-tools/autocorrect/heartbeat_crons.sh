#!/bin/bash
# heartbeat_crons.sh — dead-man's-switch de los crons del aula.
#
# Corre cada hora. Si un proceso lleva más tiempo del esperado sin escribir su log
# (o si falta el backup del día), manda UN aviso por Gmail SMTP.
# Es la red de seguridad DEBAJO del digest de 8:00/20:00: si el digest se cae,
# esto avisa. Por eso no depende de él ni comparte código con él.
#
# Cron:
#   0 * * * * /home/coreadmin/scripts/heartbeat_crons.sh >> /home/coreadmin/cron_heartbeat.log 2>&1
#
# v2 — 2026-08-07 (MON-2). Reescrito para el Hetzner. La v1 (10-jul, Dinahosting)
# apuntaba a /home/aulatuspeaking/hansel_logs y a un cron de sync de asistencia que
# ya no existe: activarla tal cual habría disparado 4 falsas alertas CADA HORA.

set -uo pipefail

EMAIL="hfernandez@tuspeaking.com"
SCRIPTS="/home/coreadmin/scripts"
LOGDIR="/home/coreadmin"
BACKUPS="/mnt/moodle-data/backups"
STATE="/home/coreadmin/.heartbeat_state"
RENOTIFY_H=6          # si sigue mal, no repetir el aviso antes de N horas

now=$(date +%s)
prob=""

# chk <fichero> <horas_limite> <nombre legible>
chk() {
  local f="$1" max=$(( $2 * 3600 )) n="$3"
  if [ ! -f "$f" ]; then
    prob+="- ${n}: SIN LOG (${f} no existe)\n"
    return
  fi
  local age=$(( now - $(stat -c %Y "$f") ))
  if [ "$age" -gt "$max" ]; then
    prob+="- ${n}: sin escribir hace $((age/3600))h (límite $2h)\n"
  fi
}

# ── Procesos (rutas reales del Hetzner: los logs van a ~/cron_*.log) ──
chk "${LOGDIR}/cron_autocorrect.log"   4  "Autocorrector (cada 2h)"
chk "${LOGDIR}/cron_quiz.log"          6  "Quiz grader (cada 4h)"
chk "${LOGDIR}/cron_ingesta.log"      30  "Ingesta Zoom (4:05 diaria)"
chk "${LOGDIR}/cron_moodle.log"       30  "Moodle cron (4:15 diaria)"
chk "${LOGDIR}/cron_status_digest.log" 14 "Digest de estado (8:00 y 20:00)"

# ── Backup del día (MON-3: solo cuenta el AUTOMÁTICO de las 3:00) ──
# Los volcados manuales llevan sufijo (_fundae_manual, etc.) y NO deben puntuar:
# uno manual enmascarando la ausencia del automático es justo el fallo a evitar.
today=$(date +%Y%m%d)
for db in aula cesce; do
  f="${BACKUPS}/db_${db}_${today}_0300.sql.gz"
  if [ ! -f "$f" ]; then
    prob+="- Backup ${db} (3:00): NO existe el automático de hoy\n"
  else
    mb=$(( $(stat -c %s "$f") / 1048576 ))
    min=100; [ "$db" = "aula" ] && min=800
    [ "$mb" -lt "$min" ] && prob+="- Backup ${db} (3:00): sospechosamente pequeño (${mb} MB, esperado >${min} MB)\n"
  fi
done

# ── Sin problemas: registrar y limpiar el estado ──
if [ -z "$prob" ]; then
  rm -f "$STATE"
  echo "$(date '+%F %H:%M') OK - todo al día"
  exit 0
fi

# ── Con problemas: anti-ruido. Avisar en la transición y luego cada RENOTIFY_H ──
last=0
[ -f "$STATE" ] && last=$(cat "$STATE" 2>/dev/null || echo 0)
if [ $(( now - last )) -lt $(( RENOTIFY_H * 3600 )) ]; then
  echo "$(date '+%F %H:%M') PROBLEMA (aviso ya enviado hace <${RENOTIFY_H}h, no repito):"
  printf "%b" "$prob"
  exit 0
fi

body="Tareas caídas ($(date '+%F %H:%M')):\n\n${prob}\nServidor: $(hostname) · ${BACKUPS}\nRevisar: docs/tickets/2026-08-07-monitorizacion-crons-post-hetzner.md"

# Gmail SMTP (send_alert.py) — el mismo canal que el digest. sendmail solo de reserva:
# entrega con retraso y desde un remitente que acaba en spam.
if [ -f "${SCRIPTS}/send_alert.py" ]; then
  printf "%b" "$body" | /home/coreadmin/venv-autocorrect/bin/python -c \
    "import sys, os; sys.path.insert(0,'${SCRIPTS}'); from send_alert import send_alert; send_alert('🔴 ALERTA cron aula.tuspeaking — tarea caída', sys.stdin.read())" \
    && sent=1 || sent=0
else
  sent=0
fi

if [ "$sent" != "1" ]; then
  { echo "To: ${EMAIL}"; echo "Subject: ALERTA cron aula.tuspeaking - tarea caida"; echo; printf "%b" "$body"; } \
    | /usr/sbin/sendmail -t 2>/dev/null || echo "$(date '+%F %H:%M') ERROR: no se pudo enviar el aviso"
fi

echo "$now" > "$STATE"
echo "$(date '+%F %H:%M') ALERTA enviada:"
printf "%b" "$prob"
