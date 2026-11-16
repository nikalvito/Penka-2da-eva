#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="/root/Penka-2da-eva"
COMPOSE_FILE="$PROJECT_DIR/docker/docker-compose.prod.yml"
ENV_FILE="$PROJECT_DIR/docker/env/prod.env"

set -a
source "$ENV_FILE"
set +a

echo "=== [1/4] Reconstruyendo y levantando contenedor pnkSecurity ==="
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" down --remove-orphans
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --build

echo "=== [2/4] Esperando conexión con RDS MariaDB ($DB_HOST) ==="
until mariadb-admin ping -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_ROOT_USER" -p"$DB_ROOT_PASS" --silent; do
  sleep 4
done

echo "=== [3/4] Configurando base de datos, usuario pnk_app e importando archivos SQL ==="
mariadb -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_ROOT_USER" -p"$DB_ROOT_PASS" << SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL

while IFS= read -r -d '' sql_file; do
  echo "Importando: $sql_file"
  mariadb --force -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_ROOT_USER" -p"$DB_ROOT_PASS" "$DB_NAME" < "$sql_file"
done < <(find "$PROJECT_DIR" -type f -name "*.sql" -print0 | sort -z)

echo "=== Tablas creadas en $DB_NAME ==="
mariadb -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW TABLES;"

echo "=== [4/4] Verificando respuesta HTTP ==="
sleep 2
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" ps
curl -I -s http://localhost:80 | head -n 5
