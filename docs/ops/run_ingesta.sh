#!/bin/bash
# Wrapper del cron de la ingesta Zoom/Acuity.
# Motivo: la línea del crontab tenía `$(date +%F %T)` con `%` SIN escapar; en cron el
# `%` = salto de línea, rompía el comando (sintaxis) y la ingesta NUNCA corría por cron
# (solo a mano). Metiendo el comando en un script, el crontab queda sin `%` ni comillas.
#
# Instalar en el servidor: /home/coreadmin/scripts/run_ingesta.sh  (chmod +x)
# Crontab (coreadmin):
#   5 4 * * * /home/coreadmin/scripts/run_ingesta.sh
set -u
LOG=/home/coreadmin/cron_ingesta.log
FULL=/home/coreadmin/cron_ingesta_full.log

/usr/bin/docker exec moodle35-app php \
    /var/www/html/app/moodle/admin/cli/i3code_download_zoomdata.php >> "$FULL" 2>&1
RC=$?
echo "$(date +'%F %T') exit=$RC" >> "$LOG"
exit $RC
