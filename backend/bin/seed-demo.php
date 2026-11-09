<?php
declare(strict_types=1);

use Soportix\Clock;
use Soportix\Database;

require dirname(__DIR__) . '/src/bootstrap.php';

$db = Database::fromEnv();
$password = password_hash('demo-soportix-2026', PASSWORD_DEFAULT);
$now = Clock::now();
$ids = [];
foreach ([['Ana Admin', 'admin@soportix.test', 'admin'], ['Teo Técnico', 'tecnico@soportix.test', 'tecnico'], ['Uma Usuaria', 'usuaria@soportix.test', 'usuario']] as [$name, $email, $role]) {
    $ids[$role] = $db->insert('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)', [$name, $email, $password, $role, $now]);
}
$devices = [['AULA-PC01', 'Windows 11 Pro', 62], ['SRV-WEB01', 'Ubuntu 24.04 LTS', 93], ['ADMIN-LT03', 'Windows 10 Pro', 41]];
foreach ($devices as [$host, $os, $disk]) {
    $id = $db->insert(
        'INSERT INTO devices (hostname, agent_key_hash, os, cpu, ram_mb, disk_total_gb, disk_used_pct, ip, software, last_seen, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$host, hash('sha256', random_bytes(16)), $os, 'Intel Core i5', 16384, 512, $disk, '192.0.2.' . random_int(10, 200), '["Firefox","7-Zip"]', $now, $now]
    );
    $deviceIds[$host] = $id;
}
$tickets = [['La impresora de aula no responde', 'No imprime desde esta mañana.', 'media', 'abierto', null], ['Sin acceso a la VPN', 'Error de autenticación al conectar.', 'alta', 'en_curso', $ids['tecnico']], ['Instalar LibreOffice', 'Necesario para el curso.', 'baja', 'resuelto', $ids['tecnico']]];
foreach ($tickets as [$title, $description, $priority, $status, $assignee]) {
    $db->run(
        'INSERT INTO tickets (title, description, priority, status, requester_id, assignee_id, device_id, due_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$title, $description, $priority, $status, $ids['usuario'], $assignee, $deviceIds['AULA-PC01'], Clock::now(86400), $now, $now]
    );
}
echo "Datos de demostración creados\n";
