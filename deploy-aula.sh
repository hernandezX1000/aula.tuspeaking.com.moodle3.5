#!/usr/bin/env bash
#
# deploy-aula.sh — Despliegue de aula (Moodle 3.5) al contenedor del Hetzner.
#
# ⚠️ CREADO 26-jul-2026 tras la migración Dina→Hetzner. **NO PROBADO AÚN EN
#    PRODUCCIÓN** — revísalo/pruébalo antes de confiar. Sustituye al viejo
#    "git pull en /home/aulatuspeaking/www/app/moodle" de Dinahosting.
#
# MODELO:
#   - Este repo se clona en /home/coreadmin/aula-repo (dueño coreadmin) → aquí git pull.
#   - El código servido vive en /mnt/moodle-data/moodle-code (dueño www-data/33),
#     montado en el contenedor moodle35-app.
#   - Este script sincroniza el código custom del checkout al volumen, despliega
#     los scripts Python al runtime, y purga cachés.
#   - El CORE de Moodle (gitignorado, NO está en el repo) no se toca (rsync sin --delete).
#
# USO (en el Hetzner, como coreadmin):
#   bash /home/coreadmin/aula-repo/deploy-aula.sh
#
# ALTA INICIAL (una vez, antes del primer uso):
#   git clone https://github.com/hernandezX1000/aula.tuspeaking.com.moodle3.5.git /home/coreadmin/aula-repo
#
set -euo pipefail

REPO=/home/coreadmin/aula-repo
CODE=/mnt/moodle-data/moodle-code
CONTAINER=moodle35-app
PURGE=/var/www/html/app/moodle/admin/cli/purge_caches.php

echo "== 1) git pull (main) =="
git -C "$REPO" checkout main
git -C "$REPO" pull --ff-only

echo "== 2) sincronizar código custom -> volumen del contenedor =="
# Sin --delete (NO borra el core de Moodle). Excluye meta del repo, config (infra) y tooling.
sudo rsync -a \
  --exclude='.git/' \
  --exclude='.gitignore' \
  --exclude='CLAUDE.md' \
  --exclude='docs/' \
  --exclude='tus-tools/' \
  --exclude='config.php' \
  --exclude='deploy-aula.sh' \
  "$REPO"/ "$CODE"/
sudo chown -R 33:33 "$CODE"

echo "== 3) desplegar scripts Python al runtime (/home/coreadmin/scripts) =="
bash "$REPO/tus-tools/autocorrect/deploy.sh"

echo "== 4) purgar caches de Moodle =="
docker exec -u www-data "$CONTAINER" php "$PURGE" || echo "(aviso OPcache inofensivo)"

echo "== DEPLOY AULA OK =="
echo "Recuerda: prueba en seco el autocorrector antes de fiarte en vivo:"
echo "  /home/coreadmin/venv-autocorrect/bin/python /home/coreadmin/scripts/hansel_autocorrect.py --dry-run"
