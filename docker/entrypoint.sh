#!/usr/bin/env bash
set -e

cat << 'PHP' > /tmp/config.local.php
<?php
if (!defined('DB_HOST')) define('DB_HOST', '__DB_HOST__');
if (!defined('DB_PORT')) define('DB_PORT', '__DB_PORT__');
if (!defined('DB_NAME')) define('DB_NAME', '__DB_NAME__');
if (!defined('DB_USER')) define('DB_USER', '__DB_USER__');
if (!defined('DB_PASS')) define('DB_PASS', '__DB_PASS__');
if (!defined('APP_URL')) define('APP_URL', '__APP_URL__');

$host = DB_HOST;
$port = DB_PORT;
$db   = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;
$charset = 'utf8mb4';

$db_host = $host;
$db_port = $port;
$db_name = $db;
$db_user = $user;
$db_pass = $pass;

$servername = $host;
$username   = $user;
$password   = $pass;
$dbname     = $db;

return [
    'host'     => $host,
    'port'     => $port,
    'dbname'   => $db,
    'db'       => $db,
    'database' => $db,
    'user'     => $user,
    'username' => $user,
    'pass'     => $pass,
    'password' => $pass,
    'charset'  => $charset,
];
PHP

sed -i "s|__DB_HOST__|${DB_HOST}|g" /tmp/config.local.php
sed -i "s|__DB_PORT__|${DB_PORT:-3306}|g" /tmp/config.local.php
sed -i "s|__DB_NAME__|${DB_NAME}|g" /tmp/config.local.php
sed -i "s|__DB_USER__|${DB_USER}|g" /tmp/config.local.php
sed -i "s|__DB_PASS__|${DB_PASS}|g" /tmp/config.local.php
sed -i "s|__APP_URL__|${APP_URL}|g" /tmp/config.local.php

cp /tmp/config.local.php /var/www/html/config.local.php
cp /tmp/config.local.php "/var/www/html/pnkSecurity - copia/pnkSecurity/pnkSecurity/config.local.php"
[ -d "/var/www/html/pnkSecurity - copia/pnkSecurity/pnkSecurity/setup" ] && cp /tmp/config.local.php "/var/www/html/pnkSecurity - copia/pnkSecurity/pnkSecurity/setup/config.local.php"
rm -f /tmp/config.local.php

chown -R www-data:www-data /var/www/html
find /var/www/html -type d -exec chmod 755 {} \;
find /var/www/html -type f -exec chmod 644 {} \;
chmod 755 /usr/local/bin/entrypoint.sh

php-fpm -D
exec nginx -g "daemon off;"
