#!/bin/bash
# =============================================================================
# Gate de despliegue del refactor secrets.php (aula Moodle 3.5)
# Ejecutar en el SERVIDOR (coreadmin). Reduce la incertidumbre de romper prod:
#   1) PRECHECK no destructivo: lint de los ficheros nuevos + secrets.php OK.
#   2) BACKUP de los ficheros actuales.
#   3) DEPLOY de los nuevos.
#   4) SMOKE test (sitio carga, endpoints sin fatal).
#   5) Si algo falla en el smoke -> ROLLBACK automático al backup.
#
# Uso:
#   - Deja los ficheros refactorizados (desde la rama agent/secrets-php) en:
#       /tmp/secrets_deploy/   (mismos rutas relativas: misclases.php, newAcuity.php,
#       admin-panel/demo_manager.php, empresas/acuity_zoom.php,
#       admin/cli/i3code_download_zoomdata.php)
#   - CREA PRIMERO secrets.php real en el server (copia de secrets.php.example + valores).
#   - Luego:   bash verify-secrets-deploy.sh
# =============================================================================
set -uo pipefail
C=moodle35-app
CODE=/var/www/html/app/moodle
STAGE=/tmp/secrets_deploy
BK=/tmp/secrets_deploy_backup_$(date +%Y%m%d_%H%M%S)
HOST=https://aula.tuspeaking.com
FILES=(misclases.php newAcuity.php admin-panel/demo_manager.php empresas/acuity_zoom.php admin/cli/i3code_download_zoomdata.php)
# Constantes que secrets.php DEBE definir (ajustar si el .example cambia):
CONSTS=(ACUITY_USER_ID ACUITY_API_KEY DEMO_DEFAULT_PASSWORD)

red(){ echo -e "\033[31m$*\033[0m"; }
grn(){ echo -e "\033[32m$*\033[0m"; }

echo "== 1) PRECHECK (no destructivo) =="
# 1a) secrets.php existe y define las constantes
docker exec "$C" test -f "$CODE/secrets.php" || { red "FALLO: no existe $CODE/secrets.php en el server. Créalo desde secrets.php.example ANTES de desplegar."; exit 1; }
MISS=$(docker exec "$C" php -r 'require "'"$CODE"'/secrets.php"; foreach(["'"${CONSTS[0]}"'","'"${CONSTS[1]}"'","'"${CONSTS[2]}"'"] as $c){ if(!defined($c)) echo $c." "; }')
[ -n "$MISS" ] && { red "FALLO: secrets.php no define: $MISS"; exit 1; }
grn "  secrets.php OK (constantes definidas)"
# 1b) lint de los ficheros NUEVOS (aún no desplegados) copiándolos a /tmp del contenedor
for f in "${FILES[@]}"; do
  [ -f "$STAGE/$f" ] || { red "FALLO: falta el fichero staged $STAGE/$f"; exit 1; }
  docker cp "$STAGE/$f" "$C":/tmp/_lint.php
  if ! docker exec "$C" php -l /tmp/_lint.php >/dev/null 2>&1; then
    red "FALLO de sintaxis (php -l) en: $f"; docker exec "$C" php -l /tmp/_lint.php; exit 1
  fi
done
grn "  php -l OK en los ${#FILES[@]} ficheros"

echo "== 2) BACKUP de los ficheros actuales =="
mkdir -p "$BK"
for f in "${FILES[@]}"; do
  mkdir -p "$BK/$(dirname "$f")"
  docker cp "$C":"$CODE/$f" "$BK/$f" 2>/dev/null && echo "  backup $f"
done

echo "== 3) DEPLOY de los nuevos (código montado read-only -> escribir en el host) =="
HOSTCODE=/mnt/moodle-data/moodle-code
for f in "${FILES[@]}"; do
  sudo cp "$STAGE/$f" "$HOSTCODE/$f" && sudo chown 33:33 "$HOSTCODE/$f"
done
docker exec -u www-data "$C" php "$CODE/admin/cli/purge_caches.php" >/dev/null 2>&1

echo "== 4) AUTOAUDITORÍA post-aplicación =="
FAIL=0
# marca del log ANTES del smoke, para ver solo errores NUEVOS
docker exec "$C" sh -c 'wc -l /var/log/apache2/error.log 2>/dev/null || echo 0' | awk '{print $1}' > /tmp/_logmark 2>/dev/null || echo 0 > /tmp/_logmark
LOGMARK=$(cat /tmp/_logmark 2>/dev/null || echo 0)
# 4a) home 200
code=$(curl -s -o /dev/null -w '%{http_code}' "$HOST/")
[ "$code" = "200" ] && grn "  [OK] home 200" || { red "  [FAIL] home devolvió $code"; FAIL=1; }
# 4b) newAcuity.php sin fatal (constante indefinida / Fatal error) — el fallo típico del refactor
body=$(curl -s "$HOST/newAcuity.php?id=0")
echo "$body" | grep -qiE "Fatal error|undefined (constant|function)|Call to undefined" && { red "  [FAIL] newAcuity.php: FATAL en la respuesta"; FAIL=1; } || grn "  [OK] newAcuity.php sin fatal"
# 4c) misclases (usa \$CFG->dbpass) — no debe dar 500
code=$(curl -s -o /dev/null -w '%{http_code}' "$HOST/misclases.php")
[ "$code" = "500" ] && { red "  [FAIL] misclases.php 500"; FAIL=1; } || grn "  [OK] misclases.php sin 500 (código $code)"
# 4d) demo panel (usa DEMO_DEFAULT_PASSWORD) — no debe dar fatal
body=$(curl -s "$HOST/admin-panel/demo_manager.php")
echo "$body" | grep -qiE "Fatal error|undefined (constant|function)" && { red "  [FAIL] demo_manager.php: FATAL"; FAIL=1; } || grn "  [OK] demo_manager.php sin fatal"
# 4e) ESCANEO DEL LOG: fatales NUEVOS escritos durante el smoke (la señal más fiable)
NEWLOG=$(docker exec "$C" sh -c "tail -n +$((LOGMARK+1)) /var/log/apache2/error.log 2>/dev/null" | grep -iE "PHP Fatal|undefined constant|undefined function|require_once.*secrets|Uncaught" | tail -8)
[ -n "$NEWLOG" ] && { red "  [FAIL] fatales NUEVOS en el log tras el despliegue:"; echo "$NEWLOG"; FAIL=1; } || grn "  [OK] log de errores sin fatales nuevos"

echo "== 5) Resultado =="
if [ "$FAIL" -eq 0 ]; then
  grn "TODO OK. Despliegue verificado. Backup en: $BK"
  grn "Si además quieres, prueba a mano: reservar/cancelar una clase y una corrida de la ingesta."
  exit 0
else
  red "SMOKE FALLÓ -> ROLLBACK automático a $BK"
  for f in "${FILES[@]}"; do
    [ -f "$BK/$f" ] && sudo cp "$BK/$f" "$HOSTCODE/$f" && sudo chown 33:33 "$HOSTCODE/$f"
  done
  docker exec -u www-data "$C" php "$CODE/admin/cli/purge_caches.php" >/dev/null 2>&1
  red "Restaurado el código anterior. Revisa el error antes de reintentar."
  exit 1
fi
