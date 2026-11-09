<?php
declare(strict_types=1);

use Soportix\Clock;
use Soportix\Database;

require dirname(__DIR__) . '/src/bootstrap.php';

[$email, $password] = [getenv('ADMIN_EMAIL') ?: '', getenv('ADMIN_PASSWORD') ?: ''];
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 12) {
    fwrite(STDERR, "Define ADMIN_EMAIL y ADMIN_PASSWORD (mínimo 12 caracteres)\n");
    exit(1);
}
$db = Database::fromEnv();
if ($db->value('SELECT COUNT(*) FROM users WHERE email = ?', [strtolower($email)])) {
    echo "El administrador ya existe\n";
    exit(0);
}
$db->run(
    "INSERT INTO users (name, email, password_hash, role, created_at) VALUES ('Administración', ?, ?, 'admin', ?)",
    [strtolower($email), password_hash($password, PASSWORD_DEFAULT), Clock::now()]
);
echo "Administrador creado\n";
