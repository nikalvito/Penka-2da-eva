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

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || $password === '') {
    header('Location: ../index.php?login=error_datos');
    exit;
}

try {
    $conn = conectar();
    
    $stmt = mysqli_prepare($conn, "SELECT id, nombre, email, password FROM usuarios WHERE email = ? LIMIT 1");
    if (!$stmt) {
        $stmt = mysqli_prepare($conn, "SELECT Id, nombre, email, password FROM usuarios WHERE email = ? LIMIT 1");
    }
    
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta de login.");
    }
    
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($user) {
        $storedHash = $user['password'] ?? $user['Password'] ?? '';
        $userId = (int)($user['id'] ?? $user['Id'] ?? 0);
        $userName = $user['nombre'] ?? $user['Nombre'] ?? 'Usuario';
        
        if ($storedHash !== '' && password_verify($password, $storedHash)) {
            $_SESSION['usuario'] = $userName;
            $_SESSION['email'] = $email;
            $_SESSION['user_id'] = $userId;

            // Obtener carrito temporal de invitado si existía
            $guestCarrito = isset($_SESSION['carrito']) && is_array($_SESSION['carrito']) ? $_SESSION['carrito'] : [];

            // Consultar carrito persistido en la BD para este usuario
            $stmt_c = mysqli_prepare($conn, "SELECT item_id, nombre, precio, posicion FROM carrito_persisted WHERE usuario_id = ? ORDER BY posicion ASC");
            $dbCarrito = [];
            if ($stmt_c) {
                mysqli_stmt_bind_param($stmt_c, "i", $userId);
                mysqli_stmt_execute($stmt_c);
                $res_c = mysqli_stmt_get_result($stmt_c);
                while ($item = mysqli_fetch_assoc($res_c)) {
                    $dbCarrito[] = [
                        'id' => (int)$item['item_id'],
                        'nombre' => $item['nombre'],
                        'precio' => (float)$item['precio'],
                        'posicion' => (int)$item['posicion']
                    ];
                }
                mysqli_stmt_close($stmt_c);
            }

            // Si el invitado tenía productos seleccionados, guardarlos en la BD y fusionarlos
            if (!empty($guestCarrito)) {
                foreach ($guestCarrito as $item) {
                    $pos = count($dbCarrito);
                    $stmt_ins = mysqli_prepare($conn, "INSERT INTO carrito_persisted (usuario_id, usuario_email, item_id, nombre, precio, posicion) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmt_ins) {
                        mysqli_stmt_bind_param($stmt_ins, "isisdi", $userId, $email, $item['id'], $item['nombre'], $item['precio'], $pos);
                        mysqli_stmt_execute($stmt_ins);
                        mysqli_stmt_close($stmt_ins);
                        $dbCarrito[] = $item;
                    }
                }
            }

            $_SESSION['carrito'] = $dbCarrito;

            header('Location: ../index.php?login=exito');
            exit;
        }
    }
    
    header('Location: ../index.php?login=error');
    exit;
} catch (Exception $e) {
    error_log("Error en procesalogin: " . $e->getMessage());
    header('Location: ../index.php?login=error');
    exit;
}
