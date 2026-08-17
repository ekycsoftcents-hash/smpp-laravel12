#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_DIR="${PROJECT_DIR:-/opt/smpp-laravel12}"
LOG_DIR="${LOG_DIR:-/var/log/smpp}"
WINDOW_MINUTES="${WINDOW_MINUTES:-15}"
LOCK_FILE="${LOCK_FILE:-/run/lock/smpp-recovery-jobs.lock}"

mkdir -p "$LOG_DIR" "$(dirname "$LOCK_FILE")"
exec 9>"$LOCK_FILE"
flock -n 9 || exit 0

cd "$PROJECT_DIR"
FROM="$(date -u -d "${WINDOW_MINUTES} minutes ago" '+%Y-%m-%d %H:%M:%S')"
TO="$(date -u '+%Y-%m-%d %H:%M:%S')"

run_app() {
  docker compose exec -T app php artisan "$@"
}

{
  echo "[$(date -u '+%Y-%m-%dT%H:%M:%SZ')] recovery window: $FROM -> $TO"
  run_app billing:reconcile --from="$FROM" --to="$TO" --limit=2000 --commit
  run_app jasmin:dlr-sync --from="$FROM" --to="$TO" --limit=2000 --commit
  echo "[$(date -u '+%Y-%m-%dT%H:%M:%SZ')] recovery completed"
} >> "$LOG_DIR/recovery-jobs.log" 2>&1
