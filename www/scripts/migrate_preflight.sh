#!/usr/bin/env bash
# Cafe24 SSH — 백업 + migrate --check ONLY (실제 migrate 전 승인 대기)
# Usage: bash scripts/migrate_preflight.sh
set -euo pipefail
cd "$(dirname "$0")/.."

STAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="${BACKUP_DIR:-./backups}"
mkdir -p "$BACKUP_DIR"

# DB 이름은 data/dbconfig.php / config/database.php 에서 읽음
DB_NAME=$(php -r "
require 'includes/db.php';
\$c = load_db_config();
echo \$c['name'];
")
DB_USER=$(php -r "
require 'includes/db.php';
\$c = load_db_config();
echo \$c['user'];
")

BACKUP_FILE="${BACKUP_DIR}/acep_backup_${STAMP}.sql"

echo "=== [1/2] mysqldump backup ==="
echo "DB: ${DB_NAME}  User: ${DB_USER}"
echo "Output: ${BACKUP_FILE}"
mysqldump -u "${DB_USER}" -p "${DB_NAME}" > "${BACKUP_FILE}"
ls -lh "${BACKUP_FILE}"

echo ""
echo "=== [2/2] migrate.php --check ==="
php migrations/migrate.php --check

echo ""
echo "=== DONE (preflight only) ==="
echo "실제 마이그레이션: php migrations/migrate.php  ← 승인 후 실행"
