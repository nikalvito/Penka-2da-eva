<?php
declare(strict_types=1);

require_once __DIR__ . '/setup/setup.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID de producto inválido']);
    exit;
}

try {
    $conn = conectar();
    $stmt = mysqli_prepare($conn, "SELECT id, nombre, precio FROM items WHERE id = ? LIMIT 1");
    if (!$stmt) {
        $stmt = mysqli_prepare($conn, "SELECT Id, nombre, precio FROM items WHERE Id = ? LIMIT 1");
    }
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $item = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$item) {
        echo json_encode(['ok' => false, 'error' => 'Producto no encontrado en la base de datos']);
        exit;
    }

    if (!isset($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
        $_SESSION['carrito'] = [];
    }

    $itemId = (int)($item['id'] ?? $item['Id'] ?? $id);
    $itemName = $item['nombre'] ?? $item['Nombre'] ?? 'Producto';
    $itemPrecio = (float)($item['precio'] ?? $item['Precio'] ?? 0);

    $posicion = count($_SESSION['carrito']);
    $nuevo_producto = [
        'id' => $itemId,
        'nombre' => $itemName,
        'precio' => $itemPrecio,
        'posicion' => $posicion
    ];

    $_SESSION['carrito'][] = $nuevo_producto;

    // Persistir automáticamente en base de datos si hay sesión activa
    $userId = $_SESSION['user_id'] ?? 0;
    $userEmail = $_SESSION['email'] ?? '';

    if ($userId > 0 || $userEmail !== '') {
        $stmt_ins = mysqli_prepare($conn, "INSERT INTO carrito_persisted (usuario_id, usuario_email, item_id, nombre, precio, posicion) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt_ins) {
            mysqli_stmt_bind_param($stmt_ins, "isisdi", $userId, $userEmail, $itemId, $itemName, $itemPrecio, $posicion);
            mysqli_stmt_execute($stmt_ins);
            mysqli_stmt_close($stmt_ins);
        }
    }

    echo json_encode([
        'ok' => true,
        'total_items' => count($_SESSION['carrito']),
        'mensaje' => '¡Producto agregado al carrito con éxito!'
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
