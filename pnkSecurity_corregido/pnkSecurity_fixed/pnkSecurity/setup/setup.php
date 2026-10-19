<?php
/**
 * setup.php - Configuración central y funciones de seguridad compartidas.
 *
 * - Credenciales de BD por variables de entorno (nunca en el código).
 * - Conexión PDO única con sentencias preparadas reales.
 * - Sesión endurecida, cabeceras de seguridad, escape de salida y CSRF.
 */

// No exponer errores internos al usuario; se registran en el log del servidor.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

function enviar_cabeceras_seguridad()
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-ancestors 'none'");
}

function iniciar_sesion()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'); // ALB/AWS
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Lee un valor de configuración. Orden de prioridad:
 *  1) setup/config.local.php (archivo local, fuera de git; ver config.local.php.example)
 *  2) variable de entorno (getenv)
 *  3) variable del servidor web (SetEnv de Apache llega a $_SERVER)
 *  4) valor por defecto
 */
function cfg($clave, $defecto = '')
{
    static $local = null;
    if ($local === null) {
        $local = [];
        $archivo = __DIR__ . '/config.local.php';
        if (is_file($archivo)) {
            $dato = include $archivo;
            if (is_array($dato)) {
                $local = $dato;
            }
        }
    }
    if (array_key_exists($clave, $local)) {
        return (string)$local[$clave];
    }
    $v = getenv($clave);
    if ($v !== false) {
        return $v;
    }
    if (isset($_SERVER[$clave]) && is_string($_SERVER[$clave])) {
        return $_SERVER[$clave];
    }
    return $defecto;
}

/**
 * Conexión PDO única. Credenciales: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS (ver cfg()).
 */
function conectar()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $host = cfg('DB_HOST', 'localhost');
    $port = cfg('DB_PORT', '3306');
    $name = cfg('DB_NAME', 'pnk_security');
    $user = cfg('DB_USER', 'pnk_app');
    $pass = cfg('DB_PASS', '');
    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$name;charset=utf8",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // parametrización real en el servidor
            ]
        );
    } catch (PDOException $ex) {
        error_log('pnkSecurity: error de conexión a BD: ' . $ex->getMessage());
        error_generico();
    }
    return $pdo;
}

/** Respuesta genérica: no filtra detalles internos (SQL, rutas, credenciales). */
function error_generico($codigo = 500, $mensaje = 'Ha ocurrido un error. Intente nuevamente más tarde.')
{
    if (!headers_sent()) {
        http_response_code($codigo);
        header('Content-Type: text/plain; charset=UTF-8');
    }
    exit($mensaje);
}

/** Ejecuta una consulta preparada y devuelve el statement. */
function consulta($sql, array $params = [])
{
    try {
        $st = conectar()->prepare($sql);
        $st->execute($params);
        return $st;
    } catch (PDOException $ex) {
        error_log('pnkSecurity: error SQL: ' . $ex->getMessage());
        error_generico();
    }
}

/** Valida un identificador numérico positivo; devuelve int o null. */
function id_valido($valor)
{
    if (is_array($valor) || $valor === null) {
        return null;
    }
    $v = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $v === false ? null : $v;
}

/** Escape de salida HTML (previene XSS). */
function e($valor)
{
    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------- CSRF ---------- */
function csrf_token()
{
    iniciar_sesion();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_validar()
{
    iniciar_sesion();
    $enviado = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['csrf']) || !is_string($enviado) || !hash_equals($_SESSION['csrf'], $enviado)) {
        error_generico(403, 'Solicitud no válida.');
    }
}

function quitarespacios($titulo)
{
    $titulo = str_replace(" ", "", $titulo);
    $cadena = str_replace("ñ", "", $titulo);
    $cadena = str_replace("Ñ", "", $cadena);
    return $cadena;
}

function moneda_chilena($numero)
{
    return '$ ' . number_format((int)$numero, 0, ',', '.');
}

enviar_cabeceras_seguridad();
