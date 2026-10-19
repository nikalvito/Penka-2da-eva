<?php

include("setup/setup.php");
iniciar_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_generico(405, 'Método no permitido.');
}
csrf_validar();

$op = $_POST['op'] ?? '';
switch ($op) {
    case "1": insertar();
        break;
    case "2": eliminaritems();
        break;
    case "3": eliminartodo();
        break;
    default:
        error_generico(400, 'Operación no válida.');
}

function insertar()
{
    // VUL-002: id validado + consulta preparada.
    $iditem = id_valido($_POST['iditems'] ?? null);
    // IDOR: solo se puede agregar un ítem visible, no eliminado y del restaurante que se está viendo.
    $rest = id_valido($_SESSION['id'] ?? null);
    if ($iditem === null || $rest === null) {
        error_generico(400, 'Solicitud no válida.');
    }
    $sql = "SELECT items.id, items.nombre, items.precio
            FROM items
            INNER JOIN categorias ON items.categorias_id = categorias.id
            INNER JOIN cartas ON categorias.cartas_id = cartas.id
            WHERE items.id = ? AND items.visible = 1 AND items.eliminado IS NULL
              AND categorias.visible = 1 AND categorias.eliminado IS NULL
              AND cartas.visible = 1 AND cartas.eliminada IS NULL
              AND cartas.restautantes_id = ?";
    $datos = consulta($sql, [$iditem, $rest])->fetch();
    if (!$datos) {
        error_generico(404, 'Producto no disponible.');
    }

    if (!isset($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
        $_SESSION['carrito'] = [];
    }
    // Contador incremental: evita que una posición se sobrescriba tras eliminar ítems.
    $_SESSION['carrito_seq'] = ($_SESSION['carrito_seq'] ?? 0) + 1;
    $pos = $_SESSION['carrito_seq'];
    $_SESSION['carrito'][$pos] = [
        "posicion" => $pos,
        "id"       => $datos['id'],
        "nombre"   => $datos['nombre'],
        "precio"   => $datos['precio'],
        "rest"     => $rest,
    ];
}

function eliminaritems()
{
    $pos = id_valido($_POST['pos'] ?? null);
    if ($pos !== null) {
        unset($_SESSION['carrito'][$pos]);
    }
}

function eliminartodo()
{
    // Solo vacía el carrito; no destruye la sesión (no cierra la sesión del usuario).
    $_SESSION['carrito'] = [];
}
