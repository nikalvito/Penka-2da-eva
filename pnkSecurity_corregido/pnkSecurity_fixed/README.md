# pnkSecurity (versión corregida)

Carta digital de restaurantes (PHP + MySQL). Esta versión corrige las vulnerabilidades VUL-001 a VUL-006
documentadas en `INFORME_VUL.md` (un commit Git por hallazgo, ver `git log`).

## Requisitos
PHP 8.x con `pdo_mysql`, MySQL/MariaDB.

## Instalación
1. Crear la base: `mysql < Script_BD/pnk_security.sql`
2. Crear un usuario de BD de mínimo privilegio (ver comentarios al final del script SQL). **No usar root.**
3. Si ya tenía una BD con contraseñas en texto plano: `php Script_BD/migrar_passwords.php`
4. Configurar variables de entorno del servidor web / contenedor / AWS (Elastic Beanstalk, ECS, EC2):
   `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
5. Servir la carpeta `pnkSecurity/` (document root). Publicar solo por HTTPS.

## Verificación de seguridad
`tests/verificar_seguridad.sh https://su-host` ejecuta pruebas de comportamiento (validación, CSRF, login, XSS,
anti fuerza bruta, cabeceras). Úselo solo en sistemas propios o autorizados.

## Credenciales de laboratorio
Los usuarios del script SQL (`admin@gmail.com`, `alondra@gmail.com`) mantienen sus claves originales de laboratorio,
ahora almacenadas como hash. **Cámbielas antes de cualquier uso fuera de un entorno de pruebas.**
