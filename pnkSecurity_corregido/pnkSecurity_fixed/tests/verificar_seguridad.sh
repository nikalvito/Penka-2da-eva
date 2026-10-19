#!/usr/bin/env bash
# Verificación de controles de seguridad (pruebas de comportamiento) - pnkSecurity
# Uso: tests/verificar_seguridad.sh [URL_BASE]     p.ej. http://127.0.0.1:8080
# Úselo SOLO contra sistemas propios / autorizados.
BASE="${1:-http://127.0.0.1:8080}"
J1=$(mktemp); J2=$(mktemp); trap 'rm -f "$J1" "$J2"' EXIT
PASS=0; FAIL=0
check() { # descripcion esperado obtenido
  if [ "$2" = "$3" ]; then echo "[OK]    $1 (esperado $2, obtenido $3)"; PASS=$((PASS+1));
  else echo "[FALLA] $1 (esperado $2, obtenido $3)"; FAIL=$((FAIL+1)); fi; }
contains() { # descripcion texto contenido(0/1 esperado) cuerpo
  if { [ "$3" = 1 ] && grep -qF -- "$2" <<<"$4"; } || { [ "$3" = 0 ] && ! grep -qF -- "$2" <<<"$4"; }; then
    echo "[OK]    $1"; PASS=$((PASS+1)); else echo "[FALLA] $1"; FAIL=$((FAIL+1)); fi; }
code() { curl -s -o /dev/null -w "%{http_code}" "$@"; }
token() { curl -s -b "$1" -c "$1" "$BASE/index.php?id=1" | grep -o 'name="csrf-token" content="[^"]*"' | head -1 | sed 's/.*content="//;s/"//'; }

echo "== VUL-001 SQLi/IDOR: parámetro id =="
check "id válido -> 200"                         200 "$(code "$BASE/index.php?id=1")"
check "id con sintaxis SQL en index.php -> 400"  400 "$(code -G "$BASE/index.php" --data-urlencode "id=1 OR 1=1")"
check "id con comilla en index.php -> 400"       400 "$(code -G "$BASE/index.php" --data-urlencode "id=1'")"
check "id con sintaxis SQL en mostrar_carrito -> 400" 400 "$(code -G "$BASE/mostrar_carrito.php" --data-urlencode "id=1 OR 1=1")"
check "id inexistente -> 404"                    404 "$(code "$BASE/index.php?id=9999")"
check "id como arreglo -> 400"                   400 "$(code "$BASE/index.php?id[]=1")"
check "mostrar_carrito válido -> 200"            200 "$(code "$BASE/mostrar_carrito.php?id=1")"
body=$(curl -s -G "$BASE/index.php" --data-urlencode "id=1'")
contains "sin mensajes de error SQL en respuesta" "mysqli" 0 "$body"

echo "== VUL-002 Carrito: CSRF, validación e IDOR de ítems =="
T=$(token "$J1")
check "carrito sin token CSRF -> 403"            403 "$(code -b "$J1" -c "$J1" -d 'op=1&iditems=5' "$BASE/carrito.php")"
check "carrito por GET -> 405"                   405 "$(code -b "$J1" "$BASE/carrito.php?op=1&iditems=5")"
check "agregar ítem válido -> 200"               200 "$(code -b "$J1" -c "$J1" -d "op=1&iditems=5&csrf=$T" "$BASE/carrito.php")"
check "iditems con sintaxis SQL -> 400"          400 "$(code -b "$J1" -c "$J1" --data-urlencode "iditems=5 OR 1=1" -d "op=1&csrf=$T" "$BASE/carrito.php")"
check "ítem eliminado (id 3) no agregable -> 404" 404 "$(code -b "$J1" -c "$J1" -d "op=1&iditems=3&csrf=$T" "$BASE/carrito.php")"
check "ítem inexistente -> 404"                  404 "$(code -b "$J1" -c "$J1" -d "op=1&iditems=9999&csrf=$T" "$BASE/carrito.php")"
T_R2=$(token "$J1"); curl -s -o /dev/null -b "$J1" -c "$J1" "$BASE/index.php?id=2"
check "IDOR: ítem del restaurante 1 no agregable desde restaurante 2 -> 404" 404 "$(code -b "$J1" -c "$J1" -d "op=1&iditems=5&csrf=$T" "$BASE/carrito.php")"
curl -s -o /dev/null -b "$J1" -c "$J1" "$BASE/index.php?id=1"
check "operación desconocida -> 400"             400 "$(code -b "$J1" -c "$J1" -d "op=9&csrf=$T" "$BASE/carrito.php")"
contains "carrito muestra el ítem agregado"      "Lomo a lo Pobre" 1 "$(curl -s -b "$J1" "$BASE/mostrar_carrito.php?id=1")"
check "vaciar carrito -> 200"                    200 "$(code -b "$J1" -c "$J1" -d "op=3&csrf=$T" "$BASE/carrito.php")"
contains "vaciar carrito no destruye la sesión (token sigue válido)" "csrf-token" 1 "$(curl -s -b "$J1" "$BASE/index.php?id=1")"

echo "== VUL-003 Autenticación =="
T2=$(token "$J2")
check "login sin token CSRF -> 403"              403 "$(code -b "$J2" -c "$J2" --data-urlencode "frmusuario=admin@gmail.com" -d "frmpassword=admin01" "$BASE/setup/procesalogin.php")"
contains "bypass clásico de login no autentica"   "Bienvenido" 0 "$(curl -s -L -b "$J2" -c "$J2" --data-urlencode "frmusuario=' OR '1'='1' -- " -d "frmpassword=x&csrf=$T2" "$BASE/setup/procesalogin.php")"
contains "contraseña errónea no autentica"        "Bienvenido" 0 "$(curl -s -L -b "$J2" -c "$J2" -d "frmusuario=admin@gmail.com&frmpassword=incorrecta&csrf=$T2" "$BASE/setup/procesalogin.php")"
SID_ANTES=$(grep -o 'PHPSESSID.*' "$J2" | awk '{print $2}')
contains "credenciales correctas autentican"      "Bienvenido: Administrador" 1 "$(curl -s -L -b "$J2" -c "$J2" -d "frmusuario=admin@gmail.com&frmpassword=admin01&csrf=$T2" "$BASE/setup/procesalogin.php")"
SID_DESPUES=$(grep -o 'PHPSESSID.*' "$J2" | awk '{print $2}')
if [ -n "$SID_ANTES" ] && [ "$SID_ANTES" != "$SID_DESPUES" ]; then echo "[OK]    el ID de sesión cambia al autenticar (anti fijación)"; PASS=$((PASS+1)); else echo "[FALLA] el ID de sesión no cambió"; FAIL=$((FAIL+1)); fi
HDR=$(curl -s -D - -o /dev/null "$BASE/index.php?id=1" | tr -d '\r')
contains "cookie de sesión HttpOnly"              "HttpOnly" 1 "$HDR"
contains "cookie de sesión SameSite"              "SameSite=Lax" 1 "$HDR"
contains "cabecera X-Content-Type-Options"        "X-Content-Type-Options: nosniff" 1 "$HDR"
contains "cabecera Content-Security-Policy"       "Content-Security-Policy" 1 "$HDR"

echo "== VUL-004/005 Comentarios y XSS =="
T3=$(token "$J2")
check "comentar sin sesión -> 403"               403 "$(code -d "comentario=hola&csrf=x" "$BASE/grcomentarios.php")"
check "comentar sin CSRF (con sesión) -> 403"    403 "$(code -b "$J2" -c "$J2" -d "comentario=hola" "$BASE/grcomentarios.php")"
PAY='<script>alert(1)</script><img src=x onerror=alert(2)>'
check "comentario válido -> 302"                 302 "$(code -b "$J2" -c "$J2" --data-urlencode "comentario=$PAY" --data-urlencode "usuario=Suplantado" -d "csrf=$T3" "$BASE/grcomentarios.php")"
PAGE=$(curl -s -b "$J2" "$BASE/index.php?id=1")
contains "HTML del comentario se muestra escapado" "&lt;script&gt;alert(1)&lt;/script&gt;" 1 "$PAGE"
contains "no hay <script> inyectado en la página"  "<script>alert(1)" 0 "$PAGE"
contains "el autor no se puede suplantar"          "<b>Suplantado</b>" 0 "$PAGE"
contains "autor = usuario de la sesión"            "<b>Administrador</b>" 1 "$PAGE"
CART=$(curl -s -b "$J2" "$BASE/mostrar_carrito.php?id=1&keyid=%22%3E%3Cscript%3Ealert(3)%3C/script%3E")
contains "parámetro keyid reflejado ya no existe"  "alert(3)" 0 "$CART"

echo "== Cierre de sesión =="
check "logout por GET no cierra sesión (302 sin efecto)" 302 "$(code -b "$J2" "$BASE/setup/cerrar_sesion.php")"
contains "sesión sigue activa tras GET"           "Bienvenido" 1 "$(curl -s -b "$J2" "$BASE/index.php?id=1")"
T4=$(token "$J2")
curl -s -o /dev/null -b "$J2" -c "$J2" -d "csrf=$T4" "$BASE/setup/cerrar_sesion.php"
contains "logout por POST con CSRF cierra sesión" "Bienvenido" 0 "$(curl -s -b "$J2" "$BASE/index.php?id=1")"

echo "== Anti fuerza bruta =="
J3=$(mktemp); T5=$(token "$J3"); LAST=""
for i in 1 2 3 4 5 6 7; do LAST=$(code -b "$J3" -c "$J3" -d "frmusuario=victima@correo.cl&frmpassword=intento$i&csrf=$T5" "$BASE/setup/procesalogin.php"); done
rm -f "$J3"
check "tras 5 fallos el login responde 429"      429 "$LAST"

echo; echo "RESULTADO: $PASS OK, $FAIL fallas"; [ "$FAIL" -eq 0 ]
