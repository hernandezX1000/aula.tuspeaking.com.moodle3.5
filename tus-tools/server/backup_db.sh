#!/bin/bash
# backup_db.sh — Daily MySQL backup wrapper
# Reads credentials from /home/aulatuspeaking/.env (never hardcoded here).
#
# Usage:
#   backup_db.sh main    — dump aulatuspeaking35 (main Moodle DB)
#   backup_db.sh cesce   — dump aulatuspeaking35cesce (CESCE DB, external host)
#
# Cron (same timing as old inline mysqldump lines):
#   0 2 * * * /home/aulatuspeaking/scripts/backup_db.sh main  >> /home/aulatuspeaking/logs/backup_db.log 2>&1
#   5 2 * * * /home/aulatuspeaking/scripts/backup_db.sh cesce >> /home/aulatuspeaking/logs/backup_db.log 2>&1
#
# Output files (unchanged — verify_db_backup.sh and find cleanup depend on these names):
#   /home/aulatuspeaking/backups/db_daily/db_YYYYMMDD.sql.gz
#   /home/aulatuspeaking/backups/db_daily/db_cesce_YYYYMMDD.sql.gz

set -euo pipefail

ENV_FILE="/home/aulatuspeaking/.env"
BACKUP_DIR="/home/aulatuspeaking/backups/db_daily"
DATE=$(date +%Y%m%d)
TS=$(date '+%Y-%m-%d %H:%M:%S')

# ── Load .env ──────────────────────────────────────────────────
if [ -f "$ENV_FILE" ]; then
    set -a
    # shellcheck disable=SC1090
    source "$ENV_FILE"
    set +a
else
    echo "[$TS] ERROR: $ENV_FILE not found" >&2
    exit 1
fi

# ── Main ───────────────────────────────────────────────────────
MODE="${1:-}"
if [ -z "$MODE" ]; then
    echo "[$TS] ERROR: Usage: $0 [main|cesce]" >&2
    exit 1
fi

case "$MODE" in

    main)
        DB_USER="${MOODLE_DB_USER:-moodle35}"
        DB_PASS="${MOODLE_DB_PASSWORD:?ERROR: MOODLE_DB_PASSWORD not set in .env}"
        DB_NAME="${MOODLE_DB_NAME:-aulatuspeaking35}"
        OUT="$BACKUP_DIR/db_${DATE}.sql.gz"

        echo "[$TS] START backup: $DB_NAME → $OUT"
        mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$OUT"
        SIZE=$(du -sh "$OUT" 2>/dev/null | cut -f1)
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] OK — $OUT ($SIZE)"
        ;;

    cesce)
        DB_HOST="${CESCE_DB_HOST:-mysql-5603.dinaserver.com}"
        DB_USER="${CESCE_DB_USER:-moodle35cesce}"
        DB_PASS="${CESCE_DB_PASSWORD:?ERROR: CESCE_DB_PASSWORD not set in .env}"
        DB_NAME="${CESCE_DB_NAME:-aulatuspeaking35cesce}"
        OUT="$BACKUP_DIR/db_cesce_${DATE}.sql.gz"

        echo "[$TS] START backup: $DB_NAME @ $DB_HOST → $OUT"
        mysqldump --no-tablespaces -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$OUT"
        SIZE=$(du -sh "$OUT" 2>/dev/null | cut -f1)
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] OK — $OUT ($SIZE)"
        ;;

    *)
        echo "[$TS] ERROR: Unknown mode '$MODE'. Use 'main' or 'cesce'." >&2
        exit 1
        ;;
esac
