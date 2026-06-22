#!/usr/bin/env bash
# Simple DB backup helper for shuttle-backend
# Usage: cd shuttle-backend && tools/backup_db.sh

set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
BACKUP_DIR="$ROOT_DIR/backups"
mkdir -p "$BACKUP_DIR"

get_env() {
  local key="$1"
  grep -E "^${key}=" "$ENV_FILE" | cut -d'=' -f2- | tr -d '"' | tr -d "'"
}

DB_CONN=$(get_env DB_CONNECTION)
TIMESTAMP=$(date +%Y%m%d%H%M%S)

if [ "$DB_CONN" = "sqlite" ] || [ -z "$DB_CONN" ]; then
  DB_FILE=$(get_env DB_DATABASE)
  if [ -z "$DB_FILE" ]; then
    echo "No DB_DATABASE found in .env (sqlite)" >&2
    exit 1
  fi
  cp "$DB_FILE" "$BACKUP_DIR/$(basename "$DB_FILE").$TIMESTAMP.bak"
  echo "SQLite backup written: $BACKUP_DIR/$(basename "$DB_FILE").$TIMESTAMP.bak"
  exit 0
fi

if [ "$DB_CONN" = "mysql" ]; then
  DB_HOST=$(get_env DB_HOST)
  DB_PORT=$(get_env DB_PORT)
  DB_NAME=$(get_env DB_DATABASE)
  DB_USER=$(get_env DB_USERNAME)
  DB_PASS=$(get_env DB_PASSWORD)

  if [ -z "$DB_NAME" ]; then
    echo "DB_DATABASE not set" >&2
    exit 1
  fi

  export MYSQL_PWD="$DB_PASS"
  DUMP_FILE="$BACKUP_DIR/${DB_NAME}.$TIMESTAMP.sql"
  mysqldump -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USER" "$DB_NAME" > "$DUMP_FILE"
  echo "MySQL dump written: $DUMP_FILE"
  unset MYSQL_PWD
  exit 0
fi

echo "Unsupported DB_CONNECTION: $DB_CONN" >&2
exit 2
