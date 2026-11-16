<?php
declare(strict_types=1);

require_once __DIR__ . '/setup.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');
$email  = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if ($nombre === '' || !$email || strlen($password) < 6) {
    header('Location: ../index.php?registro=error_datos');
    exit;
}

try {
    $conn = conectar();

    $stmt_chk = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    if (!$stmt_chk) {
        $stmt_chk = mysqli_prepare($conn, "SELECT Id FROM usuarios WHERE email = ? LIMIT 1");
    }
    mysqli_stmt_bind_param($stmt_chk, "s", $email);
    mysqli_stmt_execute($stmt_chk);
    mysqli_stmt_store_result($stmt_chk);

    if (mysqli_stmt_num_rows($stmt_chk) > 0) {
        mysqli_stmt_close($stmt_chk);
        header('Location: ../index.php?registro=duplicado');
        exit;
    }
    mysqli_stmt_close($stmt_chk);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt_ins = mysqli_prepare($conn, "INSERT INTO usuarios (nombre, email, password, estado) VALUES (?, ?, ?, 1)");
    mysqli_stmt_bind_param($stmt_ins, "sss", $nombre, $email, $hash);

    if (mysqli_stmt_execute($stmt_ins)) {
        $newId = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_ins);

        $_SESSION['usuario'] = $nombre;
        $_SESSION['email'] = $email;
        $_SESSION['user_id'] = $newId;

        $guestCarrito = isset($_SESSION['carrito']) && is_array($_SESSION['carrito']) ? $_SESSION['carrito'] : [];
        if (!empty($guestCarrito)) {
            foreach ($guestCarrito as $pos => $item) {
                $stmt_p = mysqli_prepare($conn, "INSERT INTO carrito_persisted (usuario_id, usuario_email, item_id, nombre, precio, posicion) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt_p) {
                    mysqli_stmt_bind_param($stmt_p, "isisdi", $newId, $email, $item['id'], $item['nombre'], $item['precio'], $pos);
                    mysqli_stmt_execute($stmt_p);
                    mysqli_stmt_close($stmt_p);
                }
            }
        } else {
            $_SESSION['carrito'] = [];
        }

        header('Location: ../index.php?registro=exito');
        exit;
    } else {
        mysqli_stmt_close($stmt_ins);
        header('Location: ../index.php?registro=error');
        exit;
    }
} catch (Exception $e) {
    error_log("Error en procesaregistro: " . $e->getMessage());
    header('Location: ../index.php?registro=error');
    exit;
}
