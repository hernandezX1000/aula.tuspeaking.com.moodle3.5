#!/bin/bash
# Health-check del sync diario Zoom/Moodle (i3code_download_zoomdata.php).
# Ubicación en servidor: /home/aulatuspeaking/zoom_logs/check_zoom_sync.sh
# Cron: 0 5 * * * (tras el sync de las 04:05)
# Versión 2026-07-09: avisa a hfernandez@ además de soporte@, y distingue
#   errores del informe (críticos, rompen paneles) de errores de procesado
#   (residual conocido ~14/día; solo avisa por encima de UMBRAL_PROC).
LOG="/home/aulatuspeaking/www/app/moodle/admin/cli/logs/i3code_download_zoomdata/log_$(date +%Y%m%d).log"
ALERT="soporte@tuspeaking.com,hfernandez@tuspeaking.com"
UMBRAL_PROC=30
PROBLEMA=""
if [ ! -f "$LOG" ]; then
  PROBLEMA="No existe el log de hoy: el cron de sync no corrio."
elif ! grep -q "Generando informe" "$LOG"; then
  PROBLEMA="El sync murio a medias: no llego a 'Generando informe'."
else
  INFORME_ERR=$(grep -c "del informe: Error escribiendo" "$LOG")
  PROC_ERR=$(grep -c "Error guardando i3code_acuityZoom" "$LOG")
  if [ "$INFORME_ERR" -gt 0 ]; then
    PROBLEMA="CRITICO: $INFORME_ERR filas del informe no se guardaron (paneles afectados)."
  elif [ "$PROC_ERR" -gt "$UMBRAL_PROC" ]; then
    PROBLEMA="Aviso: $PROC_ERR errores al guardar clases (umbral $UMBRAL_PROC). Revisar posible regresion."
  fi
fi
if [ -n "$PROBLEMA" ]; then
  printf 'Servidor aula.tuspeaking.com - Sync seguimiento Moodle/Zoom.\n\n%s\n\nRevisar: /home/aulatuspeaking/zoom_logs/cron.log y %s\n' "$PROBLEMA" "$LOG" \
    | mail -s "[ALERTA] Sync seguimiento Moodle ($(date +%F))" "$ALERT"
  echo "$(date '+%F %T') ALERTA: $PROBLEMA" >> /home/aulatuspeaking/zoom_logs/check.log
else
  echo "$(date '+%F %T') OK" >> /home/aulatuspeaking/zoom_logs/check.log
fi
