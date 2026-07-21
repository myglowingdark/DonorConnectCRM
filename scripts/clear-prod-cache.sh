#!/bin/bash
# Usage (from project root):
#   ./scripts/clear-prod-cache.sh
#
# Or from anywhere:
#   /Applications/MAMP/htdocs/DRM/scripts/clear-prod-cache.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="$SCRIPT_DIR/prod-ssh.env"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE"
  echo "Copy prod-ssh.env.example to prod-ssh.env and fill in your details."
  exit 1
fi

# shellcheck disable=SC1090
source "$ENV_FILE"

: "${SSH_HOST:?Set SSH_HOST in prod-ssh.env}"
: "${SSH_USER:?Set SSH_USER in prod-ssh.env}"
: "${REMOTE_APP_PATH:?Set REMOTE_APP_PATH in prod-ssh.env}"
SSH_PORT="${SSH_PORT:-22}"

REMOTE_CMD="cd '$REMOTE_APP_PATH' && php artisan optimize:clear && php artisan route:clear && php artisan config:clear && php artisan view:clear && php artisan cache:clear && echo 'Cache cleared successfully.'"

echo "Connecting to $SSH_USER@$SSH_HOST …"
echo "Clearing cache in $REMOTE_APP_PATH"
echo

SSH_OPTS=(-p "$SSH_PORT" -o StrictHostKeyChecking=accept-new)

if [[ -n "${SSH_KEY:-}" ]]; then
  if [[ ! -f "$SSH_KEY" ]]; then
    echo "SSH_KEY file not found: $SSH_KEY"
    exit 1
  fi
  ssh "${SSH_OPTS[@]}" -i "$SSH_KEY" "$SSH_USER@$SSH_HOST" "$REMOTE_CMD"
elif [[ -n "${SSH_PASSWORD:-}" ]]; then
  if ! command -v sshpass >/dev/null 2>&1; then
    echo "Password login needs sshpass."
    echo "Install with: brew install hudochenkov/sshpass/sshpass"
    echo "Or set SSH_KEY in prod-ssh.env instead (recommended)."
    exit 1
  fi
  sshpass -p "$SSH_PASSWORD" ssh "${SSH_OPTS[@]}" "$SSH_USER@$SSH_HOST" "$REMOTE_CMD"
else
  echo "No SSH_KEY or SSH_PASSWORD set — enter password when prompted."
  ssh "${SSH_OPTS[@]}" "$SSH_USER@$SSH_HOST" "$REMOTE_CMD"
fi
