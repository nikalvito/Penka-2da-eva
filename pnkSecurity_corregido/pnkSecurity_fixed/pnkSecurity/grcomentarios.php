<?php

include("setup/setup.php");
iniciar_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_generico(405, 'Método no permitido.');
}
csrf_validar();

// VUL-004: solo usuarios autenticados pueden comentar.
if (empty($_SESSION['nombre'])) {
    error_generico(403, 'Debe iniciar sesión para comentar.');
}

// El restaurante debe existir y estar activo (no se confía en un id arbitrario).
$rest = id_valido($_SESSION['id'] ?? null);
if ($rest === null || !consulta("SELECT 1 FROM restautantes WHERE id = ? AND eliminado IS NULL", [$rest])->fetchColumn()) {
    error_generico(404, 'Restaurante no encontrado.');
}

$comentario = isset($_POST['comentario']) && is_string($_POST['comentario']) ? trim($_POST['comentario']) : '';
if ($comentario === '' || mb_strlen($comentario, 'UTF-8') > 1000) {
    error_generico(400, 'El comentario es obligatorio y debe tener hasta 1000 caracteres.');
}

// El autor siempre es el usuario autenticado: el nombre enviado por el cliente se ignora (evita suplantación).
consulta(
    "INSERT INTO comentarios (usuario, comentario, id_restaurante) VALUES (?, ?, ?)",
    [$_SESSION['nombre'], $comentario, $rest]
);

header('Location:index.php?id=' . $rest);
exit;
