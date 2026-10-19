<?php
/**
 * VUL-003 - Migración de contraseñas en texto plano a hash bcrypt.
 * Uso (CLI, una sola vez, con respaldo previo de la BD):
 *   DB_USER=... DB_PASS=... php Script_BD/migrar_passwords.php
 * Requiere un usuario de BD con UPDATE sobre usuarios.password.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../pnkSecurity/setup/setup.php';

$pdo = conectar();
$n = 0;
foreach ($pdo->query("SELECT Id, password FROM usuarios")->fetchAll() as $u) {
    // Ya es hash (bcrypt/argon): se omite.
    if (password_get_info($u['password'])['algo'] !== null && password_get_info($u['password'])['algo'] !== 0) {
        continue;
    }
    $st = $pdo->prepare("UPDATE usuarios SET password = ? WHERE Id = ?");
    $st->execute([password_hash($u['password'], PASSWORD_DEFAULT), $u['Id']]);
    $n++;
}
echo "Contraseñas migradas: $n\n";
