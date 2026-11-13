#!/usr/bin/env bash
# Runs Social Connect on PHP's built-in server: http://localhost:3000 (or PORT=8080 ./start-server.sh).
# For development only. On a real server, point Apache or nginx at the public/ folder.
set -euo pipefail
cd "$(dirname "$0")"
PORT=${PORT:-3000}

if [ ! -f .env ]; then
    echo "No .env file. Run: cp .env.example .env, then ./setup-database.sh"
    exit 1
fi

echo "Social Connect on http://localhost:$PORT (Ctrl+C to stop)"
# Several workers, so the chat poll and uploads do not block other requests.
PHP_CLI_SERVER_WORKERS=${PHP_CLI_SERVER_WORKERS:-4} exec php \
    -d upload_max_filesize=6M -d post_max_size=8M \
    -S "localhost:$PORT" -t public public/index.php
