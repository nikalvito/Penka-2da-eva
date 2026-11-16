<?php
declare(strict_types=1);

function conectar(): mysqli
{
    static $con = null;
    if ($con instanceof mysqli) {
        return $con;
    }

    $host   = 'pnk-db-cluster.cetvgnbo5tb0.us-east-1.rds.amazonaws.com';
    $user   = 'pnk_admin';
    $pass   = 'penka123.';
    $db     = 'pnk_security';
    $port   = 3306;
    $caCert = __DIR__ . '/global-bundle.pem';

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $con = mysqli_init();
        mysqli_options($con, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        mysqli_ssl_set($con, null, null, $caCert, null, null);
        mysqli_real_connect($con, $host, $user, $pass, $db, $port, null, MYSQLI_CLIENT_SSL);
        mysqli_set_charset($con, 'utf8mb4');
    } catch (mysqli_sql_exception $e) {
        error_log('Error de conexión MariaDB RDS [pnkSecurity]: ' . $e->getMessage());
        http_response_code(500);
        exit('Error interno de conexión con la base de datos.');
    }

    return $con;
}

function quitarespacios(?string $titulo): string
{
    if ($titulo === null || trim($titulo) === '') {
        return 'tab';
    }
    $transliterado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $titulo);
    $base = $transliterado !== false ? $transliterado : $titulo;
    $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $base);
    return $slug !== '' ? $slug : 'tab';
}

function moneda_chilena(int|float|string|null $numero): string
{
    $valor = (int)round((float)($numero ?? 0));
    return '$ ' . number_format($valor, 0, ',', '.');
}
