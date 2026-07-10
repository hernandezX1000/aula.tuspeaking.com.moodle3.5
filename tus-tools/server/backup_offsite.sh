#!/usr/bin/env bash
#
# backup_offsite.sh — copia offsite de los dumps de BD al Hetzner (tuspeaking-lms).
#
# Saca los backups del server viejo (Dinaserver): si éste muere, la copia sigue
# en el Hetzner. Corre después de los dumps nocturnos (2:00/2:05) → cron a las 3:00.
# Avisa por email (Gmail SMTP) si el rsync falla.
#
# Destino: coreadmin@46.225.232.27:/mnt/HC_Volume_104828940/backups_dinaserver/
# Autenticación: clave dedicada ~/.ssh/id_ed25519_hetzner (sin contraseña).
#
# Cron (Dinaserver):
#   0 3 * * * /bin/bash /home/aulatuspeaking/scripts/backup_offsite.sh >> /home/aulatuspeaking/hansel_logs/backup_offsite.log 2>&1
#
set -uo pipefail

SRC="/home/aulatuspeaking/backups/db_daily/"
KEY="/home/aulatuspeaking/.ssh/id_ed25519_hetzner"
DEST_USER="coreadmin"
DEST_HOST="46.225.232.27"
DEST_DIR="/mnt/HC_Volume_104828940/backups_dinaserver/"
LOG="/home/aulatuspeaking/hansel_logs/backup_offsite.log"
ALERT="/home/aulatuspeaking/scripts/send_alert.py"

ts() { date '+%F %T'; }

rsync -az --delete --timeout=1800 \
    -e "ssh -i $KEY -o StrictHostKeyChecking=accept-new -o BatchMode=yes" \
    "$SRC" "${DEST_USER}@${DEST_HOST}:${DEST_DIR}"
rc=$?

if [ $rc -eq 0 ]; then
    n=$(ls -1 "$SRC" 2>/dev/null | wc -l)
    echo "$(ts) OFFSITE OK — $n ficheros sincronizados a ${DEST_HOST}"
else
    echo "$(ts) OFFSITE FALLO (rsync rc=$rc)"
    [ -f "$ALERT" ] && python3 "$ALERT" "🔴 Backup offsite FALLÓ" \
        "El rsync de backups de BD al Hetzner falló (rc=$rc) el $(ts). Revisar ${LOG}." || true
    exit 1
fi
