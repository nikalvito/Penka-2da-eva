<?php

include("setup.php");
iniciar_sesion();

// El cierre de sesión cambia estado: solo por POST y con token CSRF.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $rest = id_valido($_SESSION['id'] ?? null) ?? 1;
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location:../index.php?id=' . $rest);
    exit;
}

header('Location:../index.php?id=' . (id_valido($_SESSION['id'] ?? null) ?? 1));
exit;
