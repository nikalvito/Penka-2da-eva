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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$key = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 1;
$conn = conectar();
$userId = $_SESSION['user_id'] ?? 0;
$userEmail = $_SESSION['email'] ?? '';

// Si el carrito de sesión está vacío, recuperarlo de la base de datos
if (empty($_SESSION['carrito']) && ($userId > 0 || $userEmail !== '')) {
    $stmt_pers = null;
    if ($userId > 0) {
        $stmt_pers = mysqli_prepare($conn, "SELECT item_id, nombre, precio, posicion FROM carrito_persisted WHERE usuario_id = ? ORDER BY posicion ASC");
        mysqli_stmt_bind_param($stmt_pers, "i", $userId);
    } else {
        $stmt_pers = mysqli_prepare($conn, "SELECT item_id, nombre, precio, posicion FROM carrito_persisted WHERE usuario_email = ? ORDER BY posicion ASC");
        mysqli_stmt_bind_param($stmt_pers, "s", $userEmail);
    }
    
    if ($stmt_pers) {
        mysqli_stmt_execute($stmt_pers);
        $res_pers = mysqli_stmt_get_result($stmt_pers);
        
        $_SESSION['carrito'] = [];
        while ($row = mysqli_fetch_assoc($res_pers)) {
            $_SESSION['carrito'][] = [
                'id' => (int)$row['item_id'],
                'nombre' => $row['nombre'],
                'precio' => (float)$row['precio'],
                'posicion' => (int)$row['posicion']
            ];
        }
        mysqli_stmt_close($stmt_pers);
    }
}

// Manejo de acciones (vaciar o eliminar ítem)
$accion = $_GET['accion'] ?? '';
if ($accion === 'vaciar') {
    $_SESSION['carrito'] = [];
    if ($userId > 0) {
        $stmt_del = mysqli_prepare($conn, "DELETE FROM carrito_persisted WHERE usuario_id = ?");
        mysqli_stmt_bind_param($stmt_del, "i", $userId);
        mysqli_stmt_execute($stmt_del);
        mysqli_stmt_close($stmt_del);
    } elseif ($userEmail !== '') {
        $stmt_del = mysqli_prepare($conn, "DELETE FROM carrito_persisted WHERE usuario_email = ?");
        mysqli_stmt_bind_param($stmt_del, "s", $userEmail);
        mysqli_stmt_execute($stmt_del);
        mysqli_stmt_close($stmt_del);
    }
    header('Location: mostrar_carrito.php?id=' . $key);
    exit;
} elseif ($accion === 'eliminar' && isset($_GET['pos'])) {
    $pos = (int)$_GET['pos'];
    if (isset($_SESSION['carrito'][$pos])) {
        unset($_SESSION['carrito'][$pos]);
        $_SESSION['carrito'] = array_values($_SESSION['carrito']);
        
        if ($userId > 0 || $userEmail !== '') {
            if ($userId > 0) {
                $stmt_del = mysqli_prepare($conn, "DELETE FROM carrito_persisted WHERE usuario_id = ?");
                mysqli_stmt_bind_param($stmt_del, "i", $userId);
            } else {
                $stmt_del = mysqli_prepare($conn, "DELETE FROM carrito_persisted WHERE usuario_email = ?");
                mysqli_stmt_bind_param($stmt_del, "s", $userEmail);
            }
            mysqli_stmt_execute($stmt_del);
            mysqli_stmt_close($stmt_del);

            foreach ($_SESSION['carrito'] as $newPos => $item) {
                $stmt_ins = mysqli_prepare($conn, "INSERT INTO carrito_persisted (usuario_id, usuario_email, item_id, nombre, precio, posicion) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt_ins) {
                    mysqli_stmt_bind_param($stmt_ins, "isisdi", $userId, $userEmail, $item['id'], $item['nombre'], $item['precio'], $newPos);
                    mysqli_stmt_execute($stmt_ins);
                    mysqli_stmt_close($stmt_ins);
                }
            }
        }
    }
    header('Location: mostrar_carrito.php?id=' . $key);
    exit;
}

$sql_restorant = 'SELECT direcciones.calle, direcciones.numero, direcciones.comuna, direcciones.region,
                         restautantes.nombre, restautantes.id, restautantes.fono, restautantes.email, restautantes.foto
                  FROM restautantes
                  INNER JOIN direcciones ON restautantes.direcciones_id = direcciones.id
                  WHERE restautantes.id = ? AND restautantes.eliminado IS NULL
                  LIMIT 1';
$stmt = mysqli_prepare($conn, $sql_restorant);
mysqli_stmt_bind_param($stmt, 'i', $key);
mysqli_stmt_execute($stmt);
$result_restorant = mysqli_stmt_get_result($stmt);
$datos_restorant = mysqli_fetch_assoc($result_restorant);
mysqli_stmt_close($stmt);

if (!$datos_restorant) {
    $datos_restorant = [
        'nombre' => 'Restaurante PNK',
        'calle' => 'Avenida Principal',
        'numero' => '123',
        'comuna' => 'La Serena',
        'fono' => '22334455',
        'email' => 'contacto@pnksecurity.cl',
        'foto' => 'logo_empresa_comodin.png'
    ];
}

$carrito = (isset($_SESSION['carrito']) && is_array($_SESSION['carrito'])) ? $_SESSION['carrito'] : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>Productos Seleccionados - <?php echo e($datos_restorant['nombre']); ?></title>

  <link rel="stylesheet" href="vendors/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="vendors/themify-icons/themify-icons.css">
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { background-color: #f8f9fa; }
    .hero-section { background: #ffffff; padding: 30px 0; border-bottom: 1px solid #dee2e6; margin-bottom: 40px; }
  </style>
</head>
<body>

  <!-- Barra de Navegación Superior Unificada -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
      <a class="navbar-brand font-weight-bold" href="index.php?id=<?php echo $key; ?>">
        <img src="img/logo.png" alt="Logo PNK" width="40" class="mr-2 rounded"> PNKSIPS04
      </a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ml-auto align-items-center">
          <li class="nav-item active mr-3">
            <a class="nav-link" href="index.php?id=<?php echo $key; ?>">Carta</a>
          </li>
          <li class="nav-item mr-3">
            <?php if (!empty($_SESSION['usuario'])): ?>
              <span class="navbar-text text-light mr-3 font-weight-bold">
                <i class="ti-user text-warning"></i> Hola, <?php echo e($_SESSION['usuario']); ?>
              </span>
              <a class="nav-link btn btn-outline-danger btn-sm px-3 text-white d-inline-block" href="setup/cerrar_sesion.php">
                <i class="ti-power-off"></i> Cerrar Sesión
              </a>
            <?php else: ?>
              <a class="nav-link btn btn-outline-light btn-sm px-3 text-white" href="index.php?id=<?php echo $key; ?>">
                <i class="ti-user"></i> Iniciar Sesión / Registrarse
              </a>
            <?php endif; ?>
          </li>
          <li class="nav-item">
            <a class="nav-link btn btn-warning btn-sm px-3 text-dark font-weight-bold" href="mostrar_carrito.php?id=<?php echo $key; ?>">
              <i class="ti-shopping-cart"></i> Productos Seleccionados
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Cabecera del Restaurante -->
  <section class="hero-section">
    <div class="container">
      <div class="row align-items-center text-center text-lg-left">
        <div class="col-lg-4 mb-4 mb-lg-0 text-center">
          <img class="img-fluid rounded shadow-sm" src="img/logo.png" alt="Logo PNK" style="max-height: 120px;">
        </div>
        <div class="col-lg-4 mb-4 mb-lg-0 text-center">
          <?php if (!empty($datos_restorant['foto'])): ?>
              <img class="img-fluid rounded shadow-sm mb-3" src="imagenes/cod<?php echo $key; ?>/<?php echo e(basename($datos_restorant['foto'])); ?>" alt="Restaurante" style="max-height: 110px;">
          <?php else: ?>
              <img class="img-fluid rounded shadow-sm mb-3" src="img/logo_empresa_comodin.png" alt="Restaurante" style="max-height: 110px;">
          <?php endif; ?>
          <h4 class="font-weight-bold text-dark mb-1"><?php echo e($datos_restorant['nombre']); ?></h4>
          <p class="text-muted small mb-0"><i class="ti-location-pin"></i> <?php echo e($datos_restorant['calle']); ?> #<?php echo e($datos_restorant['numero']); ?>, <?php echo e($datos_restorant['comuna']); ?></p>
        </div>
        <div class="col-lg-4 text-center">
          <div class="d-flex justify-content-center justify-content-lg-end">
            <a href="index.php?id=<?php echo $key; ?>" class="btn btn-outline-primary btn-sm font-weight-bold mr-2 px-3 py-2">
              <i class="ti-angle-left"></i> Volver a la Carta
            </a>
            <a href="mostrar_carrito.php?id=<?php echo $key; ?>&accion=vaciar" class="btn btn-outline-danger btn-sm font-weight-bold px-3 py-2">
              <i class="ti-trash"></i> Eliminar Todo
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Contenido del Carrito -->
  <section class="container mb-5">
    <div class="row">
      <div class="col-lg-12">
        <div class="card shadow-sm border-0 p-4 bg-white">
          <h3 class="font-weight-bold text-dark mb-4 text-center">Productos Agregados para Solicitar</h3>

          <?php if (empty($carrito)): ?>
            <div class="alert alert-info text-center py-4 mb-0" role="alert">
              <h5 class="mb-1">Tu carrito está vacío</h5>
              <p class="text-muted small mb-3">Aún no has seleccionado ningún producto de la carta.</p>
              <a href="index.php?id=<?php echo $key; ?>" class="btn btn-primary btn-sm font-weight-bold px-4">Explorar la Carta</a>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead class="thead-light">
                   <tr>
                      <th scope="col">Código</th>
                      <th scope="col">Producto</th>
                      <th scope="col">Precio</th>
                      <th scope="col" class="text-center">Acción</th>
                   </tr>
                </thead>
                <tbody>
                   <?php
                   $total = 0;
                   foreach ($carrito as $pos => $value):
                      $total += (float)$value['precio'];
                   ?>
                   <tr>
                      <th scope="row">#<?php echo (int)$value['id']; ?></th>
                      <td class="font-weight-bold text-dark"><?php echo e($value['nombre']); ?></td>
                      <td class="text-success font-weight-bold"><?php echo e(moneda_chilena($value['precio'])); ?></td>
                      <td class="text-center">
                        <a href="mostrar_carrito.php?id=<?php echo $key; ?>&accion=eliminar&pos=<?php echo $pos; ?>" class="btn btn-outline-danger btn-sm font-weight-bold px-3">
                          <i class="ti-trash"></i> Eliminar
                        </a>
                      </td>
                   </tr>
                   <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="mt-4 pt-3 border-top text-center">
              <h4 class="font-weight-bold text-dark mb-3">Total estimado: <span class="text-success"><?php echo e(moneda_chilena($total)); ?></span></h4>
              <div class="alert alert-warning py-3 font-weight-bold" role="alert">
                ¡¡¡Si estás listo con tu pedido, llama al garzón para solicitarlo!!!
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- Pie de Página -->
  <footer class="bg-dark text-white py-4 mt-5">
    <div class="container text-center">
      <p class="small m-0 text-white-50">
        Copyright 2020-2026, Todos los Derechos reservados PNK<br>
        Sitio desarrollado para la Explotación de Vulnerabilidades Web, Laboratorio de Programación Segura I<br>
        <b>Área Tecnologías de Información y Ciberseguridad - Inacap Sede La Serena</b>
      </p>
    </div>
  </footer>

  <script src="vendors/jquery/jquery-3.7.1.min.js"></script>
  <script src="vendors/bootstrap/bootstrap.bundle.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>
