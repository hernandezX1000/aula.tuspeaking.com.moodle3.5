#!/usr/bin/env bash
#
# deploy.sh — despliega los scripts del autocorrector del repo al runtime.
#
# El repo se checkea (Hetzner: /home/coreadmin/aula-repo), pero los scripts CORREN
# desde /home/coreadmin/scripts/. Este script copia la versión versionada al
# runtime, con backup previo. NO toca ficheros con secretos (.env) ni el core.
#
# ⚠️ ACTUALIZADO 26-jul-2026: rutas del Hetzner (antes era Dina /home/aulatuspeaking/).
# Normalmente lo llama deploy-aula.sh (raíz del repo), pero se puede correr suelto.
#
# Uso (en el Hetzner):
#   cd /home/coreadmin/aula-repo/tus-tools/autocorrect
#   bash deploy.sh              # despliega hansel_autocorrect.py + hermanos
#   bash deploy.sh --deps       # además instala/actualiza dependencias pip
#
# Recomendado ANTES de desplegar en vivo:
#   /home/coreadmin/venv-autocorrect/bin/python /home/coreadmin/scripts/hansel_autocorrect.py --dry-run
#   (revisa el log: no escribe notas)
#
set -euo pipefail

SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DST_DIR="/home/coreadmin/scripts"
BACKUP_DIR="$DST_DIR/_backups/$(date +%Y%m%d_%H%M%S)"

SCRIPTS=(hansel_autocorrect.py hansel_digest.py hansel_quiz_grader.py transcribe_whisper.py \
         send_alert.py hansel_status_digest.py)

if [[ "${1:-}" == "--deps" ]]; then
    echo "== Instalando dependencias pip (--user) =="
    pip3 install --user --upgrade \
        mysql-connector-python anthropic faster-whisper \
        python-docx pypdf odfpy striprtf
fi

echo "== Backup en $BACKUP_DIR =="
mkdir -p "$BACKUP_DIR"

for s in "${SCRIPTS[@]}"; do
    if [[ -f "$SRC_DIR/$s" ]]; then
        [[ -f "$DST_DIR/$s" ]] && cp -p "$DST_DIR/$s" "$BACKUP_DIR/$s"
        cp -p "$SRC_DIR/$s" "$DST_DIR/$s"
        # Sanity: no debe compilar con errores
        python3 -m py_compile "$DST_DIR/$s"
        echo "  desplegado: $s"
    fi
done

echo "== OK. Prueba en seco antes de vivo: =="
echo "   python3 $DST_DIR/hansel_autocorrect.py --dry-run"
