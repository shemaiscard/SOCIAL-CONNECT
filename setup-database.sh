#!/usr/bin/env bash
# Creates the database and its user from the values in .env, imports the schema
# and, if you agree, adds the demo data. Needs a MySQL 8 account that can create users.
# Usage: ./setup-database.sh [--yes]   (--yes answers every question with yes)
set -euo pipefail
cd "$(dirname "$0")"

YES=0
[ "${1:-}" = "--yes" ] && YES=1

if [ ! -f .env ]; then
    echo "No .env file. Run: cp .env.example .env, then set DB_PASS."
    exit 1
fi
set -a; . ./.env; set +a
DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=${DB_PORT:-3306}
DB_NAME=${DB_NAME:-social_connect}
DB_USER=${DB_USER:-social_app}
if [ -z "${DB_PASS:-}" ] || [ "$DB_PASS" = "change-me" ]; then
    echo "Set DB_PASS in .env to a password of your own."
    exit 1
fi
if ! [[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ && "$DB_USER" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "DB_NAME and DB_USER may only use letters, digits and underscores."
    exit 1
fi

ask() {
    [ "$YES" = 1 ] && return 0
    read -r -p "$1 [y/N] " answer
    [[ "$answer" =~ ^[Yy] ]]
}

ADMIN_USER=${MYSQL_ADMIN_USER:-root}
echo "Social Connect database setup"
echo "  database: $DB_NAME on $DB_HOST:$DB_PORT"
echo "  app user: $DB_USER"
echo "  admin:    $ADMIN_USER (set MYSQL_ADMIN_USER to use another account)"
ask "Create the database and user now? Importing the schema replaces existing tables." || { echo "Cancelled."; exit 1; }

SQL_PASS=${DB_PASS//\\/\\\\}
SQL_PASS=${SQL_PASS//\'/\'\'}
ADMIN=(mysql --protocol=TCP -h "$DB_HOST" -P "$DB_PORT" -u "$ADMIN_USER")
[ -z "${MYSQL_ADMIN_PASS+x}" ] && ADMIN+=(-p) || export MYSQL_PWD="$MYSQL_ADMIN_PASS"

"${ADMIN[@]}" <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$SQL_PASS';
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$SQL_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$SQL_PASS';
ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$SQL_PASS';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, REFERENCES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, REFERENCES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

echo "Importing database/schema.sql..."
MYSQL_PWD="$DB_PASS" mysql --protocol=TCP -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" < database/schema.sql

if ask "Add the demo data (12 fictional people, password demo1234)?"; then
    php bin/seed.php --yes
fi
echo "Done. Start the app with ./start-server.sh"
