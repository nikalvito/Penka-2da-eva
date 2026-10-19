<?php

include("setup.php");
iniciar_sesion();

// Tras el login (exitoso o no) se vuelve al restaurante que se estaba viendo.
$destino = '../index.php?id=' . (id_valido($_SESSION['id'] ?? null) ?? 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location:' . $destino);
    exit;
}
csrf_validar();

const MAX_INTENTOS = 5;      // intentos fallidos permitidos
const VENTANA_MIN  = 15;     // en esta ventana de minutos

$email    = isset($_POST['frmusuario'])  && is_string($_POST['frmusuario'])  ? trim($_POST['frmusuario'])  : '';
$password = isset($_POST['frmpassword']) && is_string($_POST['frmpassword']) ? $_POST['frmpassword']       : '';
$ip       = $_SERVER['REMOTE_ADDR'] ?? '';

// Anti fuerza bruta: se cuentan los fallos recientes por correo o por IP.
$fallos = (int)consulta(
    "SELECT COUNT(*) FROM login_intentos
      WHERE exitoso = 0 AND fecha > (NOW() - INTERVAL " . VENTANA_MIN . " MINUTE)
        AND (email = ? OR ip = ?)",
    [$email, $ip]
)->fetchColumn();

if ($fallos >= MAX_INTENTOS) {
    error_generico(429, 'Demasiados intentos fallidos. Intente nuevamente en unos minutos.');
}

// VUL-003: consulta preparada; la contraseña nunca se compara en SQL.
$usuario = consulta(
    "SELECT Id, nombre, password FROM usuarios WHERE email = ? AND estado = '1'",
    [$email]
)->fetch();

// Se verifica siempre contra un hash (aunque el usuario no exista) para no revelar su existencia por tiempo de respuesta.
$hash_senuelo = '$2y$10$IDzO65JifW4NBEaKREWRvegL4KS36PrUZLsRw2w50U2.eb9xp85Xa';
$hash = $usuario ? $usuario['password'] : $hash_senuelo;
$valido = password_verify($password, $hash) && $usuario;

consulta("INSERT INTO login_intentos (email, ip, exitoso, fecha) VALUES (?, ?, ?, NOW())",
    [substr($email, 0, 255), substr($ip, 0, 45), $valido ? 1 : 0]);

if ($valido) {
    // Previene fijación de sesión: nuevo ID al autenticar. Se conserva el id del restaurante.
    $rest = $_SESSION['id'] ?? null;
    session_regenerate_id(true);
    $_SESSION['id']         = $rest;
    $_SESSION['nombre']     = $usuario['nombre'];
    $_SESSION['usuario_id'] = (int)$usuario['Id'];
    unset($_SESSION['csrf']); // token nuevo para la sesión autenticada
    if (password_needs_rehash($usuario['password'], PASSWORD_DEFAULT)) {
        consulta("UPDATE usuarios SET password = ? WHERE Id = ?",
            [password_hash($password, PASSWORD_DEFAULT), $usuario['Id']]);
    }
}

header('Location:' . $destino);
exit;
