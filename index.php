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
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <meta name="csrf-token" content="<?php echo e($_SESSION['csrf_token']); ?>">
  <title><?php echo e($datos_restorant['nombre']); ?></title>

  <link rel="stylesheet" href="vendors/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="vendors/themify-icons/themify-icons.css">
  <link rel="stylesheet" href="vendors/owl-carousel/owl.theme.default.min.css">
  <link rel="stylesheet" href="vendors/owl-carousel/owl.carousel.min.css">
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { background-color: #f8f9fa; }
    .hero-section { background: #ffffff; padding: 30px 0; border-bottom: 1px solid #dee2e6; margin-bottom: 40px; }
    .card-restaurant { background: #ffffff; border-radius: 8px; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075); padding: 20px; }
  </style>
</head>
<body>

  <!-- Barra de Navegación Superior -->
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
              <a class="nav-link btn btn-outline-light btn-sm px-3 text-white" href="#" data-toggle="modal" data-target="#authModal">
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
          <img class="img-fluid rounded shadow-sm" src="img/logo.png" alt="Logo PNK" style="max-height: 130px;">
        </div>
        <div class="col-lg-4 mb-4 mb-lg-0 text-center">
          <?php if (!empty($datos_restorant['foto'])): ?>
              <img class="img-fluid rounded shadow-sm mb-3" src="imagenes/cod<?php echo $key; ?>/<?php echo e(basename($datos_restorant['foto'])); ?>" alt="Restaurante" style="max-height: 120px;">
          <?php else: ?>
              <img class="img-fluid rounded shadow-sm mb-3" src="img/logo_empresa_comodin.png" alt="Restaurante" style="max-height: 120px;">
          <?php endif; ?>
          <h3 class="font-weight-bold text-dark mb-1"><?php echo e($datos_restorant['nombre']); ?></h3>
          <p class="text-muted small mb-1"><i class="ti-location-pin"></i> <?php echo e($datos_restorant['calle']); ?> #<?php echo e($datos_restorant['numero']); ?>, <?php echo e($datos_restorant['comuna']); ?></p>
          <p class="text-muted small mb-0"><i class="ti-headphone-alt"></i> <?php echo e($datos_restorant['fono']); ?> &nbsp;|&nbsp; <i class="ti-email"></i> <?php echo e($datos_restorant['email']); ?></p>
        </div>
        <div class="col-lg-4 text-center">
          <div class="card-restaurant border">
            <h5 class="font-weight-bold text-dark mb-2">¿Listo para ordenar?</h5>
            <p class="text-muted small mb-0">Selecciona tus platos favoritos de la carta y revisa tu pedido en el botón superior derecho <b>"Productos Seleccionados"</b>.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Sección de Menús y Pestañas -->
  <section class="container mb-5">
    <div class="text-center mb-4">
      <h2 class="font-weight-bold text-dark">Menús Destacados</h2>
      <hr class="w-25 border-primary">
    </div>

    <?php
    $sql_cartas = 'SELECT id, nombre FROM cartas WHERE restautantes_id = ? AND visible = b\'1\' ORDER BY orden ASC';
    $stmt_c = mysqli_prepare($conn, $sql_cartas);
    mysqli_stmt_bind_param($stmt_c, 'i', $key);
    mysqli_stmt_execute($stmt_c);
    $res_cartas = mysqli_stmt_get_result($stmt_c);
    
    $cartas_array = [];
    while ($c = mysqli_fetch_assoc($res_cartas)) {
        $cartas_array[] = $c;
    }
    mysqli_stmt_close($stmt_c);
    ?>

    <ul class="nav nav-pills justify-content-center mb-4" id="cartasTab" role="tablist">
      <?php foreach ($cartas_array as $index => $carta): 
          $active = $index === 0 ? 'active' : '';
          $slug = quitarespacios($carta['nombre']);
      ?>
        <li class="nav-item m-1">
          <a class="nav-link font-weight-bold px-4 py-2 shadow-sm rounded-pill <?php echo $active; ?>" id="<?php echo $slug; ?>-tab" data-toggle="tab" href="#<?php echo $slug; ?>" role="tab"><?php echo e($carta['nombre']); ?></a>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="tab-content" id="cartasTabContent">
      <?php foreach ($cartas_array as $index => $carta): 
          $active = $index === 0 ? 'show active' : '';
          $slug = quitarespacios($carta['nombre']);
          $carta_id = (int)$carta['id'];
      ?>
        <div class="tab-pane fade <?php echo $active; ?>" id="<?php echo $slug; ?>" role="tabpanel">
          <div class="row">
            <?php
            $sql_cat = 'SELECT id, nombre FROM categorias WHERE cartas_id = ? AND visible = b\'1\' ORDER BY orden ASC';
            $stmt_cat = mysqli_prepare($conn, $sql_cat);
            mysqli_stmt_bind_param($stmt_cat, 'i', $carta_id);
            mysqli_stmt_execute($stmt_cat);
            $res_cat = mysqli_stmt_get_result($stmt_cat);
            
            $has_items = false;
            while ($cat = mysqli_fetch_assoc($res_cat)):
                $cat_id = (int)$cat['id'];
            ?>
              <div class="col-12 mt-3 mb-2">
                <h4 class="text-dark border-bottom pb-2 font-weight-bold"><i class="ti-angle-right text-primary"></i> <?php echo e($cat['nombre']); ?></h4>
              </div>
              <?php
              $sql_items = 'SELECT id, nombre, descripcion, precio, foto, tiempo FROM items WHERE categorias_id = ? AND visible = b\'1\' ORDER BY orden ASC';
              $stmt_item = mysqli_prepare($conn, $sql_items);
              mysqli_stmt_bind_param($stmt_item, 'i', $cat_id);
              mysqli_stmt_execute($stmt_item);
              $res_item = mysqli_stmt_get_result($stmt_item);

              while ($item = mysqli_fetch_assoc($res_item)):
                  $has_items = true;
                  $item_id = (int)$item['id'];
              ?>
                <div class="col-md-6 mb-3">
                  <div class="media align-items-center p-3 bg-white rounded shadow-sm border h-100">
                    <?php if (!empty($item['foto'])): ?>
                        <img class="mr-3 rounded shadow-sm" src="imagenes/cod<?php echo $key; ?>/<?php echo e(basename($item['foto'])); ?>" width="85" height="85" style="object-fit: cover;" alt="Plato">
                    <?php else: ?>
                        <img class="mr-3 rounded shadow-sm" src="img/logo_empresa_comodin.png" width="85" height="85" style="object-fit: cover;" alt="Plato">
                    <?php endif; ?>
                    <div class="media-body">
                      <h5 class="mt-0 font-weight-bold text-dark mb-1"><?php echo e($item['nombre']); ?></h5>
                      <p class="text-muted small mb-1"><?php echo e($item['descripcion']); ?></p>
                      <p class="text-secondary small mb-2"><i class="ti-time"></i> <?php echo e($item['tiempo']); ?></p>
                      <div class="d-flex justify-content-between align-items-center">
                        <span class="font-weight-bold text-success h6 mb-0"><?php echo e(moneda_chilena($item['precio'])); ?></span>
                        <button class="btn btn-sm btn-outline-primary seleccionar-btn px-3 font-weight-bold" data-id="<?php echo $item_id; ?>">Seleccionar</button>
                      </div>
                    </div>
                  </div>
                </div>
              <?php 
              endwhile;
              mysqli_stmt_close($stmt_item);
            endwhile;
            mysqli_stmt_close($stmt_cat);

            if (!$has_items):
            ?>
              <div class="col-12 text-center py-5">
                <p class="text-muted font-italic">No hay información para mostrar en esta categoría actualmente.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- MODAL DE INICIO DE SESIÓN Y REGISTRO -->
  <div class="modal fade" id="authModal" tabindex="-1" role="dialog" aria-labelledby="authModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content shadow-lg border-0">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title font-weight-bold" id="authModalLabel">Acceso de Usuarios</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body p-4">
          <ul class="nav nav-pills nav-fill mb-4 shadow-sm rounded bg-light p-1" id="pills-tab" role="tablist">
            <li class="nav-item">
              <a class="nav-link active font-weight-bold rounded" id="pills-login-tab" data-toggle="pill" href="#pills-login" role="tab">Iniciar Sesión</a>
            </li>
            <li class="nav-item">
              <a class="nav-link font-weight-bold rounded" id="pills-register-tab" data-toggle="pill" href="#pills-register" role="tab">Registrarse</a>
            </li>
          </ul>
          <div class="tab-content" id="pills-tabContent">
            <!-- Formulario Login -->
            <div class="tab-pane fade show active" id="pills-login" role="tabpanel">
              <form action="setup/procesalogin.php" method="POST">
                <div class="form-group">
                  <label class="font-weight-bold small text-secondary">Correo Electrónico</label>
                  <input type="email" class="form-control" name="email" required placeholder="correo@ejemplo.cl">
                </div>
                <div class="form-group">
                  <label class="font-weight-bold small text-secondary">Contraseña</label>
                  <input type="password" class="form-control" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-primary btn-block font-weight-bold py-2">Ingresar</button>
              </form>
            </div>
            <!-- Formulario Registro -->
            <div class="tab-pane fade" id="pills-register" role="tabpanel">
              <form action="setup/procesaregistro.php" method="POST">
                <div class="form-group">
                  <label class="font-weight-bold small text-secondary">Nombre Completo</label>
                  <input type="text" class="form-control" name="nombre" required placeholder="Tu Nombre">
                </div>
                <div class="form-group">
                  <label class="font-weight-bold small text-secondary">Correo Electrónico</label>
                  <input type="email" class="form-control" name="email" required placeholder="correo@ejemplo.cl">
                </div>
                <div class="form-group">
                  <label class="font-weight-bold small text-secondary">Contraseña (Mín. 6 caracteres)</label>
                  <input type="password" class="form-control" name="password" required minlength="6" placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-success btn-block font-weight-bold py-2">Registrarse</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

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
  <script src="vendors/owl-carousel/owl.carousel.min.js"></script>
  <script src="js/main.js"></script>
  <script src="js/controladorajax.js"></script>
</body>
</html>
