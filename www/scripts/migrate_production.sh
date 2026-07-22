#!/usr/bin/env bash
# ACEP Production Migration (MariaDB)
set -euo pipefail
cd "$(dirname "$0")/.."

echo "[1/4] Pre-flight check..."
php migrations/migrate.php --check

echo "[2/4] BACKUP REQUIRED — mysqldump before continue"
read -r -p "Backup completed? (yes): " ok
[[ "$ok" == "yes" ]] || exit 1

echo "[3/4] Running migrations..."
php migrations/migrate.php

read -r -p "Run seed? (y/N): " seed
if [[ "${seed,,}" == "y" ]]; then
  php migrations/migrate.php --seed
fi

echo "[4/4] Validation..."
php scripts/validate_production.php
