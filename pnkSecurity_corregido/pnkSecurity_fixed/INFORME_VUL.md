# Informe de Vulnerabilidades — pnkSecurity

**Aplicación:** pnkSecurity (carta digital de restaurantes) — PHP + MySQL
**Alcance:** código fuente completo del proyecto (`index.php`, `mostrar_carrito.php`, `carrito.php`, `grcomentarios.php`, `setup/*`, `js/controladorajax.js`, `Script_BD/pnk_security.sql`). Se excluyen las librerías de terceros de `vendors/`.
**Versión revisada (vulnerable):** commit `fe9f7a0`
**Versión corregida:** código en commit `059f530` (rama `main`; el commit siguiente solo agrega este informe)
**Referencia OWASP:** OWASP Top 10 2021
**Método:** revisión manual de código (SAST manual) + pruebas de comportamiento sobre la versión corregida (`tests/verificar_seguridad.sh`).

> Las pruebas se ejecutaron en un entorno local de laboratorio. Los hallazgos de la versión vulnerable se respaldan con el código fuente (archivo y línea del commit `fe9f7a0`); no se incluyen payloads de explotación. Para reproducir el estado vulnerable en el laboratorio basta `git checkout fe9f7a0`.

## Resumen

| ID | Hallazgo | Severidad | OWASP Top 10 2021 | Commit de corrección |
|---|---|---|---|---|
| VUL-001 | SQL Injection + IDOR en parámetro `id` (restaurante) | Crítica | A03 Injection · A01 Broken Access Control | `b989379` |
| VUL-002 | SQL Injection + IDOR + CSRF en el carrito (`iditems`, `pos`) | Alta | A03 · A01 | `c536006` |
| VUL-003 | SQL Injection en login (bypass de autenticación), contraseñas en texto plano, sin protección de fuerza bruta ni de fijación de sesión | Crítica | A03 · A07 Identification & Authentication Failures · A02 Cryptographic Failures | `464a42e` |
| VUL-004 | SQL Injection (INSERT), suplantación de autor y CSRF en comentarios | Alta | A03 · A01 · A04 Insecure Design | `eca0bd5` |
| VUL-005 | Cross-Site Scripting almacenado y reflejado | Alta | A03 Injection (XSS) | `4d44d5b` |
| VUL-006 | Credenciales de BD `root` sin clave en el código, errores internos expuestos, sin cabeceras de seguridad | Media | A05 Security Misconfiguration | `d5de0d3` |

Estado final: las 6 vulnerabilidades corregidas; 40/40 pruebas de verificación aprobadas (ver "Evidencia sin vulnerabilidad").

---

## VUL-001 → SQLi + IDOR en el parámetro `id`

**VUL:** SQL Injection (inyección en consultas con `id` concatenado) e IDOR (acceso a objetos sin verificar estado ni pertenencia).
**OWASP:** A03:2021 Injection · A01:2021 Broken Access Control.
**ASVS v5.0.0:** capítulos V1 (Encoding and Sanitization), V2 (Validation) y V8 (Authorization) — el identificador exacto del requisito debe validarse contra la fuente oficial antes de citarlo en un informe formal.

**Descripción:**
`index.php` y `mostrar_carrito.php` toman `$_GET['id']` y lo concatenan directamente en 7 consultas SQL (restaurante, destacados, cartas, categorías, ítems, comentarios), sin validar que sea numérico. Un atacante puede alterar la consulta y leer o modificar datos de la base. Además `mostrar_carrito.php` no filtra `eliminado IS NULL`, por lo que permite ver datos de restaurantes dados de baja cambiando el `id` (IDOR). El valor sin validar también se guarda en `$_SESSION['id']` y se usa después en otras consultas (inyección de segundo orden).

**URL:**
- `GET /index.php?id=<id>`
- `GET /mostrar_carrito.php?id=<id>` (antes `?keyid=`)
- En AWS: `https://<host-AWS>/index.php?id=1`

**Evidencia (versión vulnerable, commit `fe9f7a0`):**
- `index.php:7-8` → `$key=$_GET['id']; $_SESSION['id']=$_GET['id'];` (sin validación)
- `index.php:11, 148, 228, 248, 271, 407` → consultas con `".$key."` / `".$datos_categorias['id']."` concatenado.
- `mostrar_carrito.php:5-8` → consulta con `".$key` y sin filtro `eliminado IS NULL`.
- Comando para revisarlo: `git show fe9f7a0:pnkSecurity/index.php | sed -n '7,11p'`

**Corrección:**
- **Commit Git:** `b989379` (`git show b989379`)
- **Código original:**
```php
$key=$_GET['id'];
$_SESSION['id']=$_GET['id'];

$sql_restorant="SELECT ... FROM restautantes INNER JOIN direcciones ON restautantes.direcciones_id =
direcciones.id WHERE restautantes.id = ".$key." AND restautantes.eliminado IS NULL";
$result_restorant=mysqli_query(conectar(),$sql_restorant);
$datos_restorant=mysqli_fetch_array($result_restorant);
```
- **Código nuevo:**
```php
$key = id_valido($_GET['id'] ?? null);          // entero positivo o null
if ($key === null) {
    error_generico(400, 'Solicitud no válida.');
}
$sql_restorant = "SELECT ... FROM restautantes INNER JOIN direcciones ON restautantes.direcciones_id = direcciones.id
                  WHERE restautantes.id = ? AND restautantes.eliminado IS NULL";
$datos_restorant = consulta($sql_restorant, [$key])->fetch();   // sentencia preparada (PDO)
if (!$datos_restorant) {
    error_generico(404, 'Restaurante no encontrado.');
}
$_SESSION['id'] = $key;                          // solo un id ya validado
```
  Las 6 consultas restantes de `index.php` y la de `mostrar_carrito.php` usan el mismo patrón (`?` + arreglo de parámetros). `mostrar_carrito.php` ahora exige restaurante activo y muestra solo los ítems del carrito que pertenecen a ese restaurante.

**Evidencia sin vulnerabilidad:**
```
[OK]    id válido -> 200 (esperado 200, obtenido 200)
[OK]    id con sintaxis SQL en index.php -> 400 (esperado 400, obtenido 400)
[OK]    id con comilla en index.php -> 400 (esperado 400, obtenido 400)
[OK]    id con sintaxis SQL en mostrar_carrito -> 400 (esperado 400, obtenido 400)
[OK]    id inexistente -> 404 (esperado 404, obtenido 404)
[OK]    id como arreglo -> 400 (esperado 400, obtenido 400)
[OK]    sin mensajes de error SQL en respuesta
```

**Riesgo residual:** Bajo. Mantener consultas preparadas en todo código nuevo.

---

## VUL-002 → SQLi + IDOR + CSRF en el carrito

**VUL:** SQL Injection en `iditems`; IDOR (se puede agregar al carrito cualquier ítem, incluso ocultos, eliminados o de otro restaurante); CSRF; `posicion` que se sobrescribe; `session_destroy()` que cierra la sesión del usuario.
**OWASP:** A03:2021 Injection · A01:2021 Broken Access Control.
**ASVS v5.0.0:** V1, V4 (API and Web Service), V8, V3 (anti-CSRF) — validar identificadores exactos.

**Descripción:**
`carrito.php` concatena `$_POST['iditems']` en el `SELECT`, no valida que el ítem esté visible/activo ni que pertenezca al restaurante que se está viendo, y acepta cualquier petición sin token anti-CSRF (un sitio externo puede agregar o vaciar el carrito de un usuario). La operación "Eliminar todo" ejecuta `session_destroy()`, lo que además cierra la sesión del usuario. Además `$_POST['op']` se usa sin comprobar que exista.

**URL:** `POST /carrito.php` con `op=1&iditems=<id>` (agregar), `op=2&pos=<n>` (quitar), `op=3` (vaciar).

**Evidencia (versión vulnerable, commit `fe9f7a0`):**
- `carrito.php:19` → `"select id, nombre, precio from items where id=".$_POST['iditems']`
- `carrito.php:6` → `switch($_POST['op'])` sin validación; ningún token CSRF en `js/controladorajax.js`.
- `carrito.php:35` → `session_destroy()` en `eliminartodo()`.

**Corrección:**
- **Commit Git:** `c536006` (`git show c536006`)
- **Código original:**
```php
function insertar()
{
    $sql="select id, nombre, precio from items where id=".$_POST['iditems'];
    $result=mysqli_query(conectar(),$sql);
    $datos=mysqli_fetch_array($result);
    $pos=count($_SESSION["carrito"])+1;
    ...
}
function eliminartodo() { session_destroy(); }
```
- **Código nuevo:**
```php
csrf_validar();                                   // token en cada operación; solo POST
...
$iditem = id_valido($_POST['iditems'] ?? null);
$rest   = id_valido($_SESSION['id'] ?? null);
$sql = "SELECT items.id, items.nombre, items.precio
        FROM items
        INNER JOIN categorias ON items.categorias_id = categorias.id
        INNER JOIN cartas ON categorias.cartas_id = cartas.id
        WHERE items.id = ? AND items.visible = 1 AND items.eliminado IS NULL
          AND categorias.visible = 1 AND categorias.eliminado IS NULL
          AND cartas.visible = 1 AND cartas.eliminada IS NULL
          AND cartas.restautantes_id = ?";        // solo ítems del restaurante activo
$datos = consulta($sql, [$iditem, $rest])->fetch();
...
$_SESSION['carrito_seq'] = ($_SESSION['carrito_seq'] ?? 0) + 1;   // posición única
...
function eliminartodo() { $_SESSION['carrito'] = []; }            // ya no destruye la sesión
```
  `js/controladorajax.js` envía el token CSRF (leído de `<meta name="csrf-token">`) en las tres operaciones.

**Evidencia sin vulnerabilidad:**
```
[OK]    carrito sin token CSRF -> 403 (esperado 403, obtenido 403)
[OK]    carrito por GET -> 405 (esperado 405, obtenido 405)
[OK]    agregar ítem válido -> 200 (esperado 200, obtenido 200)
[OK]    iditems con sintaxis SQL -> 400 (esperado 400, obtenido 400)
[OK]    ítem eliminado (id 3) no agregable -> 404 (esperado 404, obtenido 404)
[OK]    ítem inexistente -> 404 (esperado 404, obtenido 404)
[OK]    IDOR: ítem del restaurante 1 no agregable desde restaurante 2 -> 404 (esperado 404, obtenido 404)
[OK]    operación desconocida -> 400 (esperado 400, obtenido 400)
[OK]    vaciar carrito no destruye la sesión (token sigue válido)
```

**Riesgo residual:** Bajo. El carrito vive en sesión del servidor; el precio siempre se lee de la BD (no del cliente).

---

## VUL-003 → SQLi en login, contraseñas en texto plano, sin anti fuerza bruta

**VUL:** SQL Injection con bypass de autenticación; almacenamiento de contraseñas en texto plano; sin límite de intentos; sin regeneración del ID de sesión (fijación de sesión); cierre de sesión por GET (CSRF); sin protección contra CSRF de login.
**OWASP:** A03:2021 Injection · A07:2021 Identification and Authentication Failures · A02:2021 Cryptographic Failures.
**ASVS v5.0.0:** V6 (Authentication), V7 (Session Management), V1 — validar identificadores exactos.

**Descripción:**
`setup/procesalogin.php` arma la consulta de login concatenando correo y contraseña. Con una entrada manipulada se puede alterar la condición del `WHERE` y entrar sin credenciales válidas (incluida la cuenta de administrador). Las contraseñas se comparan y guardan en texto plano (`admin01`, `alondra01` visibles en el script SQL), por lo que cualquier lectura de la BD las expone. No hay límite de intentos y la sesión conserva el mismo ID tras autenticar.

**URL:** `POST /setup/procesalogin.php` (campos `frmusuario`, `frmpassword`); `GET /setup/cerrar_sesion.php`.

**Evidencia (versión vulnerable, commit `fe9f7a0`):**
- `setup/procesalogin.php:5` → `"select * from usuarios where email='".$_POST['frmusuario']."' and password='".$_POST['frmpassword']."'"`
- `Script_BD/pnk_security.sql:170` → `INSERT INTO usuarios VALUES (1,'Administrador','admin@gmail.com','admin01','1'),...`
- `setup/procesalogin.php:12` → `session_start()` sin `session_regenerate_id()`.

**Corrección:**
- **Commit Git:** `464a42e` (`git show 464a42e`)
- **Código original:**
```php
$sql="select * from usuarios where email='".$_POST['frmusuario']."' and password='".$_POST['frmpassword']."'";
$result=mysqli_query(conectar(),$sql);
$cont_login=mysqli_num_rows($result);
...
if($cont_login!=0) { session_start(); $_SESSION['nombre']=$datos['nombre']; ... }
```
- **Código nuevo:**
```php
csrf_validar();
// Anti fuerza bruta: 5 fallos / 15 min por correo o por IP -> HTTP 429
$usuario = consulta("SELECT Id, nombre, password FROM usuarios WHERE email = ? AND estado = '1'", [$email])->fetch();
$hash   = $usuario ? $usuario['password'] : $hash_senuelo;      // mismo costo exista o no el usuario
$valido = password_verify($password, $hash) && $usuario;        // la clave nunca se compara en SQL
...
if ($valido) {
    session_regenerate_id(true);                                // anti fijación de sesión
    $_SESSION['nombre'] = $usuario['nombre'];
}
```
  - Contraseñas almacenadas con `password_hash()` (bcrypt) en `Script_BD/pnk_security.sql`; nueva tabla `login_intentos`.
  - `Script_BD/migrar_passwords.php` migra bases ya desplegadas con contraseñas en texto plano.
  - `cerrar_sesion.php` ahora exige POST + token CSRF y destruye la sesión y la cookie.

**Evidencia sin vulnerabilidad:**
```
[OK]    login sin token CSRF -> 403 (esperado 403, obtenido 403)
[OK]    bypass clásico de login no autentica
[OK]    contraseña errónea no autentica
[OK]    credenciales correctas autentican
[OK]    el ID de sesión cambia al autenticar (anti fijación)
[OK]    logout por GET no cierra sesión (302 sin efecto)
[OK]    logout por POST con CSRF cierra sesión
[OK]    tras 5 fallos el login responde 429 (esperado 429, obtenido 429)
```

**Riesgo residual:** Medio-bajo. (1) Las claves de laboratorio (`admin01`, `alondra01`) siguen siendo débiles aunque ahora estén hasheadas: **cámbielas**. (2) El límite por IP usa `REMOTE_ADDR`; detrás de un balanceador AWS (ALB) verá la IP del balanceador, por lo que conviene configurar la IP real del cliente desde un proxy de confianza. (3) No hay MFA.

---

## VUL-004 → SQLi, suplantación de autor y CSRF en comentarios

**VUL:** SQL Injection en `INSERT`; el nombre del autor lo envía el cliente (suplantación); cualquiera puede publicar sin sesión llamando directo al endpoint; sin CSRF; comentarios asociados a un restaurante arbitrario guardado en sesión sin validar.
**OWASP:** A03:2021 Injection · A01:2021 Broken Access Control · A04:2021 Insecure Design.
**ASVS v5.0.0:** V1, V8, V3 — validar identificadores exactos.

**Descripción:**
`grcomentarios.php` concatena `$_POST['usuario']`, `$_POST['comentario']` y `$_SESSION['id']` en un `INSERT`. El control "solo usuarios con sesión pueden comentar" existe únicamente en la interfaz (el formulario se oculta), no en el servidor: el endpoint acepta peticiones anónimas. El campo `usuario` es editable, por lo que se puede publicar con el nombre de otra persona.

**URL:** `POST /grcomentarios.php` (campos `usuario`, `comentario`).

**Evidencia (versión vulnerable, commit `fe9f7a0`):**
- `grcomentarios.php:6` → `"INSERT INTO comentarios SET usuario='".$_POST['usuario']."',comentario='".$_POST['comentario']."',id_restaurante=".$_SESSION['id']`
- `index.php:392` → campo `usuario` editable en el formulario; ninguna verificación de sesión en `grcomentarios.php`.

**Corrección:**
- **Commit Git:** `eca0bd5` (`git show eca0bd5`)
- **Código original:**
```php
$sql="INSERT INTO comentarios SET usuario='".$_POST['usuario']."',comentario='".$_POST['comentario']."',id_restaurante=".$_SESSION['id'];
mysqli_query(conectar(),$sql);
header('Location:index.php?id='.$_SESSION['id'])
```
- **Código nuevo:**
```php
csrf_validar();
if (empty($_SESSION['nombre'])) { error_generico(403, 'Debe iniciar sesión para comentar.'); }
// restaurante existente y activo; comentario obligatorio y <= 1000 caracteres
consulta(
    "INSERT INTO comentarios (usuario, comentario, id_restaurante) VALUES (?, ?, ?)",
    [$_SESSION['nombre'], $comentario, $rest]     // autor = usuario autenticado, no el enviado por el cliente
);
```

**Evidencia sin vulnerabilidad:**
```
[OK]    comentar sin sesión -> 403 (esperado 403, obtenido 403)
[OK]    comentar sin CSRF (con sesión) -> 403 (esperado 403, obtenido 403)
[OK]    comentario válido -> 302 (esperado 302, obtenido 302)
[OK]    el autor no se puede suplantar
[OK]    autor = usuario de la sesión
```

**Riesgo residual:** Bajo. No hay moderación ni límite de frecuencia de comentarios (posible spam).

---

## VUL-005 → Cross-Site Scripting (almacenado y reflejado)

**VUL:** XSS almacenado (comentarios y datos de la BD) y reflejado (`keyid`, `id`).
**OWASP:** A03:2021 Injection (XSS).
**ASVS v5.0.0:** V1 (Encoding and Sanitization), V3 (Web Frontend Security) — validar identificadores exactos.

**Descripción:**
La aplicación imprime sin escapar el nombre y el texto de los comentarios, valores de la BD (nombres, descripciones, fono, email, fotos), el nombre de sesión y los parámetros `id` y `keyid` de la URL dentro de atributos HTML. Un comentario con HTML/JavaScript se ejecuta en el navegador de cada visitante de la carta (XSS almacenado), y un enlace con `keyid` manipulado se ejecuta en el navegador de quien lo abre (XSS reflejado), con riesgo de robo de sesión o acciones en nombre de la víctima.

**URL:**
- `GET /index.php?id=<id>` (sección Comentarios)
- `GET /mostrar_carrito.php?id=<id>&keyid=<valor>` (antes reflejaba `keyid` en el enlace "Volver a la Carta")

**Evidencia (versión vulnerable, commit `fe9f7a0`):**
- `index.php:414,416` → `<?php echo $datoscomentarios['usuario'];?>` / `['comentario']` sin escape.
- `index.php:54` → `echo "Bienvenido :".$_SESSION['nombre']`; `index.php:392` → `value="<?php echo $_SESSION['nombre'];?>"`.
- `index.php:89` → `?keyid=<?php echo $_GET['id'];?>`; `mostrar_carrito.php:61` → `?id=<?php echo $_GET['keyid'];?>`.
- Más de 30 `echo` de datos de BD, sesión y URL sin `htmlspecialchars`.

**Corrección:**
- **Commit Git:** `4d44d5b` (`git show 4d44d5b`)
- **Código original:**
```php
<b><?php echo $datoscomentarios['usuario'];?></b>
<?php echo $datoscomentarios['comentario'];?>
<a class="volver" href="index.php?id=<?php echo $_GET['keyid'];?>">
```
- **Código nuevo:**
```php
function e($valor) { return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
...
<b><?php echo e($datoscomentarios['usuario']);?></b>
<?php echo e($datoscomentarios['comentario']);?>
<a class="volver" href="index.php?id=<?php echo $key;?>">     <!-- $key ya validado como entero -->
```
  Todas las salidas de `index.php` y `mostrar_carrito.php` pasan por `e()`. Además se añade `Content-Security-Policy` y `X-Content-Type-Options` (VUL-006) como defensa en profundidad. Se eliminó `utf8_encode()` (obsoleto en PHP 8.2+); la conexión ahora usa `charset=utf8`.

**Evidencia sin vulnerabilidad:**
```
[OK]    HTML del comentario se muestra escapado
[OK]    no hay <script> inyectado en la página
[OK]    parámetro keyid reflejado ya no existe
[OK]    cabecera Content-Security-Policy
```

**Riesgo residual:** Bajo. La CSP mantiene `'unsafe-inline'` en `script-src` y `style-src` porque el tema usa estilos incrustados; se recomienda endurecerla con nonces.

---

## VUL-006 → Configuración insegura (credenciales, errores, cabeceras, sesión)

**VUL:** credenciales de BD (`root`, clave vacía) escritas en el código; la aplicación abre una conexión nueva en cada consulta; errores/advertencias de PHP y MySQL visibles al usuario; cookie de sesión sin atributos de seguridad; sin cabeceras de seguridad; sin `.htaccess` (listado de directorios y archivos `.sql` descargables si se publican).
**OWASP:** A05:2021 Security Misconfiguration.
**ASVS v5.0.0:** V13 (Configuration), V12 (Secure Communication), V7 — validar identificadores exactos.

**Descripción:**
`setup/setup.php` conecta como `root` sin contraseña con datos fijos en el código: si el código se filtra o la BD queda expuesta, el acceso es total y de máximo privilegio. Sin manejo de errores, una consulta fallida muestra detalles internos (consulta, tablas, rutas). La sesión se crea con valores por defecto de PHP y no se envían cabeceras que mitiguen clickjacking, sniffing de contenido o XSS.

**URL:** toda la aplicación (`/setup/setup.php` es incluido por todas las páginas).

**Evidencia (versión vulnerable, commit `fe9f7a0`):**
- `setup/setup.php:5` → `mysqli_connect("localhost","root","","pnk_security")`
- Sin `ini_set('display_errors', '0')` ni manejo de excepciones; `session_start()` sin parámetros de cookie en todos los archivos.

**Corrección:**
- **Commit Git:** `d5de0d3` (`git show d5de0d3`)
- **Código original:**
```php
function conectar()
{
    $con=mysqli_connect("localhost","root","","pnk_security");
    return $con;
}
```
- **Código nuevo:**
```php
ini_set('display_errors', '0'); ini_set('log_errors', '1');
function conectar()                         // PDO único, credenciales por variables de entorno
{
    static $pdo = null;
    ...
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,     // parametrización real en el servidor
    ]);
}
// Sesión: HttpOnly, SameSite=Lax, Secure bajo HTTPS, modo estricto
// Cabeceras: X-Content-Type-Options, X-Frame-Options, Referrer-Policy, CSP, Cache-Control
```
  Variables de entorno: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`. El script SQL incluye (comentado) un usuario `pnk_app` con privilegios mínimos. Se agregó `.htaccess` (sin listado de directorios, bloqueo de `.sql/.log/.env`).

**Evidencia sin vulnerabilidad:**
```
[OK]    cookie de sesión HttpOnly
[OK]    cookie de sesión SameSite
[OK]    cabecera X-Content-Type-Options
[OK]    cabecera Content-Security-Policy
[OK]    sin mensajes de error SQL en respuesta
```

**Riesgo residual:** Medio hasta que se cumpla en el despliegue: usar HTTPS (para que la cookie lleve `Secure`), usuario de BD de mínimo privilegio, y no publicar `Script_BD/` en el servidor web.

---

## Versión sin vulnerabilidades en AWS

La carpeta `pnkSecurity/` es el *document root* de la versión corregida. **No se realizó despliegue en AWS** (no hay acceso desde este entorno); pasos para publicarla:

1. Desplegar `pnkSecurity/` (EC2, Elastic Beanstalk o contenedor con PHP 8.x + `pdo_mysql`).
2. Crear la BD (por ejemplo en RDS) con `Script_BD/pnk_security.sql`; si ya existe una BD con claves en texto plano, ejecutar `php Script_BD/migrar_passwords.php`.
3. Definir `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` como variables de entorno (o Secrets Manager) con un usuario de mínimo privilegio.
4. Publicar solo por HTTPS (certificado en ALB/CloudFront) y no exponer `Script_BD/` ni `tests/`.
5. Ejecutar `tests/verificar_seguridad.sh https://<host-AWS>` y adjuntar la salida como evidencia final del ambiente.

## Alcance y limitaciones

- Revisión manual del código propio; no se ejecutó escaneo automático (SAST/DAST) ni pentest completo, ni se revisaron las versiones de las librerías de `vendors/` (jQuery 3.2.1, Bootstrap, etc.: se recomienda un análisis de dependencias SCA; jQuery 3.2.1 es antigua).
- Las pruebas se corrieron con PHP 8.3 contra un servidor compatible con MySQL (Dolt), no contra MySQL/MariaDB oficial. Conviene repetir `tests/verificar_seguridad.sh` en el entorno final.
- Los números de requisito ASVS v5 se citan a nivel de capítulo; el identificador exacto debe validarse contra la fuente oficial antes de usarlo en un informe formal de auditoría.
- Esta aplicación procesa datos personales mínimos (nombre, correo de usuarios y comentarios): corresponde evaluar retención y base de licitud según la Ley 19.628 (y la Ley 21.719 desde el 1-12-2026).
