<?php
declare(strict_types=1);

use Soportix\App;
use Soportix\Clock;
use Soportix\Database;
use Soportix\Request;

require dirname(__DIR__) . '/src/bootstrap.php';

$file = null;
$dsn = getenv('TEST_DB_DSN');
if (!$dsn) {
    $file = tempnam(sys_get_temp_dir(), 'soportix');
    $dsn = "sqlite:$file";
}
putenv('AGENT_MIN_INTERVAL=0');
$db = new Database($dsn, getenv('TEST_DB_USER') ?: null, getenv('TEST_DB_PASSWORD') ?: null);
$db->migrate(dirname(__DIR__) . '/migrations');
$app = new App($db);
$passed = 0;
$failed = 0;

function check(string $name, bool $ok): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo($ok ? '  ok   ' : '  FAIL ') . $name . PHP_EOL;
}

function call(string $method, string $path, array $body = [], ?string $token = null, array $headers = [], string $ip = '10.0.0.1'): array
{
    global $app;
    parse_str((string) parse_url($path, PHP_URL_QUERY), $query);
    $h = array_change_key_case($headers);
    if ($token) {
        $h['authorization'] = "Bearer $token";
    }
    return $app->handle(new Request($method, (string) parse_url($path, PHP_URL_PATH), $query, $body, $h, $ip));
}

function user(string $role, string $email): string
{
    global $db;
    $db->run('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)', [ucfirst($role), $email, password_hash('clave-de-pruebas-1', PASSWORD_BCRYPT, ['cost' => 4]), $role, Clock::now()]);
    return call('POST', '/api/login', ['email' => $email, 'password' => 'clave-de-pruebas-1'], null, [], "10.1.1.$role")[1]['token'];
}

$admin = user('admin', 'admin@t.test');
$tech = user('tecnico', 'tec@t.test');
$ana = user('usuario', 'ana@t.test');
$luis = user('usuario', 'luis@t.test');

echo "Autenticación\n";
check('login devuelve token', strlen($admin) === 64);
check('ruta protegida sin token', call('GET', '/api/me')[0] === 401);
check('token inventado', call('GET', '/api/me', [], str_repeat('a', 64))[0] === 401);
check('me devuelve rol', call('GET', '/api/me', [], $tech)[1]['role'] === 'tecnico');
check('me no expone el hash', !isset(call('GET', '/api/me', [], $tech)[1]['password_hash']));
check('contraseña incorrecta', call('POST', '/api/login', ['email' => 'ana@t.test', 'password' => 'mala'], null, [], '10.2.0.1')[0] === 401);
check('usuario inexistente, mismo error', call('POST', '/api/login', ['email' => 'no@t.test', 'password' => 'mala'], null, [], '10.2.0.1')[1]['error'] === 'Credenciales incorrectas');
for ($i = 0; $i < 5; $i++) {
    $last = call('POST', '/api/login', ['email' => 'luis@t.test', 'password' => 'mala'], null, [], '10.3.0.1');
}
check('bloquea tras 5 fallos', call('POST', '/api/login', ['email' => 'luis@t.test', 'password' => 'clave-de-pruebas-1'], null, [], '10.3.0.1')[0] === 429);
check('el bloqueo no afecta a otra IP', call('POST', '/api/login', ['email' => 'luis@t.test', 'password' => 'clave-de-pruebas-1'], null, [], '10.3.0.2')[0] === 200);
$temp = call('POST', '/api/login', ['email' => 'ana@t.test', 'password' => 'clave-de-pruebas-1'], null, [], '10.4.0.1')[1]['token'];
check('logout', call('POST', '/api/logout', [], $temp)[0] === 204);
check('token anulado tras logout', call('GET', '/api/me', [], $temp)[0] === 401);
$db->run("UPDATE api_tokens SET expires_at = '2000-01-01T00:00:00Z' WHERE token_hash = ?", [hash('sha256', $luis)]);
check('token caducado', call('GET', '/api/me', [], $luis)[0] === 401);
$luis = call('POST', '/api/login', ['email' => 'luis@t.test', 'password' => 'clave-de-pruebas-1'], null, [], '10.4.0.2')[1]['token'];

echo "Usuarios\n";
check('usuario no lista usuarios', call('GET', '/api/users', [], $ana)[0] === 403);
check('técnico no crea usuarios', call('POST', '/api/users', [], $tech)[0] === 403);
$new = call('POST', '/api/users', ['name' => 'Nueva', 'email' => 'NUEVA@t.test', 'password' => 'clave-suficientemente-larga', 'role' => 'usuario'], $admin);
check('admin crea usuario', $new[0] === 201 && $new[1]['email'] === 'nueva@t.test');
check('correo duplicado', call('POST', '/api/users', ['name' => 'Otra', 'email' => 'nueva@t.test', 'password' => 'clave-suficientemente-larga', 'role' => 'usuario'], $admin)[0] === 409);
check('contraseña corta', call('POST', '/api/users', ['name' => 'Otra', 'email' => 'o@t.test', 'password' => 'corta', 'role' => 'usuario'], $admin)[0] === 422);
$adminId = call('GET', '/api/me', [], $admin)[1]['id'];
check('no se puede quitar el último admin', call('PATCH', "/api/users/$adminId", ['active' => false], $admin)[0] === 409);
$nt = call('POST', '/api/login', ['email' => 'nueva@t.test', 'password' => 'clave-suficientemente-larga'], null, [], '10.4.0.3')[1]['token'];
call('PATCH', '/api/users/' . $new[1]['id'], ['active' => false], $admin);
check('desactivar revoca sesiones', call('GET', '/api/me', [], $nt)[0] === 401);

echo "Tickets\n";
$t = call('POST', '/api/tickets', ['title' => 'No enciende', 'description' => 'El equipo no arranca', 'priority' => 'critica'], $ana);
$id = $t[1]['id'];
check('crea ticket', $t[0] === 201 && $t[1]['status'] === 'abierto');
$hours = (strtotime($t[1]['due_at']) - strtotime($t[1]['created_at'])) / 3600;
check('plazo crítico de 4 h', $hours === 4 || $hours === 4.0);
check('título vacío', call('POST', '/api/tickets', ['title' => '', 'description' => 'x', 'priority' => 'baja'], $ana)[0] === 422);
check('prioridad inválida', call('POST', '/api/tickets', ['title' => 'Prueba', 'description' => 'xxx', 'priority' => 'urgente'], $ana)[0] === 422);
check('tipo no texto', call('POST', '/api/tickets', ['title' => ['a'], 'description' => 'xxx', 'priority' => 'baja'], $ana)[0] === 422);
check('el dueño lo ve', call('GET', "/api/tickets/$id", [], $ana)[0] === 200);
check('otro usuario no lo ve', call('GET', "/api/tickets/$id", [], $luis)[0] === 404);
check('el listado solo muestra los propios', call('GET', '/api/tickets', [], $luis)[1]['items'] === []);
check('el técnico lo ve', count(call('GET', '/api/tickets', [], $tech)[1]['items']) === 1);
check('el usuario no cambia estado', call('PATCH', "/api/tickets/$id", ['status' => 'cerrado'], $ana)[0] === 403);
$techId = call('GET', '/api/me', [], $tech)[1]['id'];
$anaId = call('GET', '/api/me', [], $ana)[1]['id'];
$u = call('PATCH', "/api/tickets/$id", ['status' => 'en_curso', 'assignee_id' => $techId], $tech);
check('asigna técnico', $u[1]['assignee_id'] === $techId && $u[1]['status'] === 'en_curso');
check('no asigna a un usuario normal', call('PATCH', "/api/tickets/$id", ['assignee_id' => $anaId], $tech)[0] === 422);
$u = call('PATCH', "/api/tickets/$id", ['priority' => 'baja'], $tech);
check('cambiar prioridad recalcula plazo', (strtotime($u[1]['due_at']) - strtotime($u[1]['created_at'])) === 72 * 3600);
check('filtro por estado', count(call('GET', '/api/tickets?status=en_curso', [], $tech)[1]['items']) === 1 && call('GET', '/api/tickets?status=cerrado', [], $tech)[1]['items'] === []);
check('filtro no permite inyección', call('GET', "/api/tickets?status=' OR 1=1 --", [], $tech)[0] === 422);
check('nota pública', call('POST', "/api/tickets/$id/comments", ['body' => 'Lo miro hoy'], $tech)[0] === 201);
check('nota interna', call('POST', "/api/tickets/$id/comments", ['body' => 'Secreto', 'internal' => true], $tech)[0] === 201);
check('usuario no crea notas internas', call('POST', "/api/tickets/$id/comments", ['body' => 'x', 'internal' => true], $ana)[0] === 403);
check('otro usuario no comenta', call('POST', "/api/tickets/$id/comments", ['body' => 'x'], $luis)[0] === 404);
check('el usuario no ve notas internas', count(call('GET', "/api/tickets/$id", [], $ana)[1]['comments']) === 1);
check('el técnico ve notas internas', count(call('GET', "/api/tickets/$id", [], $tech)[1]['comments']) === 2);
$u = call('PATCH', "/api/tickets/$id", ['status' => 'resuelto'], $tech);
check('resolver guarda fecha', $u[1]['resolved_at'] !== null);
$db->run("UPDATE tickets SET status = 'abierto', resolved_at = NULL, due_at = '2000-01-01T00:00:00Z' WHERE id = ?", [$id]);
check('marca retraso', call('GET', "/api/tickets/$id", [], $tech)[1]['overdue'] === true);
call('PATCH', "/api/tickets/$id", ['status' => 'cerrado'], $tech);
check('cerrado no admite comentarios', call('POST', "/api/tickets/$id/comments", ['body' => 'x'], $ana)[0] === 409);

echo "Inventario y agente\n";
check('técnico no da de alta equipos', call('POST', '/api/devices', ['hostname' => 'X'], $tech)[0] === 403);
$d = call('POST', '/api/devices', ['hostname' => 'PC-01'], $admin);
$key = $d[1]['agent_key'];
check('alta de equipo devuelve clave', $d[0] === 201 && strlen($key) === 64);
check('la clave no se guarda en claro', !$db->value('SELECT COUNT(*) FROM devices WHERE agent_key_hash = ?', [$key]));
check('agente sin clave', call('POST', '/api/agent/report', ['disk_used_pct' => 10])[0] === 401);
check('agente con clave falsa', call('POST', '/api/agent/report', ['disk_used_pct' => 10], null, ['X-Agent-Key' => 'falsa'])[0] === 401);
check('porcentaje fuera de rango', call('POST', '/api/agent/report', ['disk_used_pct' => 150], null, ['X-Agent-Key' => $key])[0] === 422);
$report = ['os' => 'Ubuntu', 'cpu' => 'i5', 'ip' => '192.0.2.4', 'ram_mb' => 8192, 'disk_total_gb' => 256, 'software' => ['vim', 'git', 5]];
$r = call('POST', '/api/agent/report', $report + ['disk_used_pct' => 50], null, ['X-Agent-Key' => $key]);
check('informe normal sin ticket', $r[0] === 200 && $r[1]['ticket_id'] === null);
$r = call('POST', '/api/agent/report', $report + ['disk_used_pct' => 95], null, ['X-Agent-Key' => $key]);
$alert = $r[1]['ticket_id'];
check('disco lleno abre ticket', is_int($alert));
check('no duplica la alerta', call('POST', '/api/agent/report', $report + ['disk_used_pct' => 96], null, ['X-Agent-Key' => $key])[1]['ticket_id'] === null);
$show = call('GET', '/api/devices/' . $d[1]['id'], [], $tech)[1];
check('guarda historial', count($show['history']) === 3);
check('filtra software no válido', $show['software'] === ['vim', 'git']);
check('no expone la clave', !isset($show['agent_key_hash']) && !isset(call('GET', '/api/devices', [], $tech)[1][0]['agent_key_hash']));
check('usuario no ve inventario', call('GET', '/api/devices', [], $ana)[0] === 403);
call('PATCH', "/api/tickets/$alert", ['status' => 'resuelto'], $tech);
check('reabre alerta tras resolver', is_int(call('POST', '/api/agent/report', $report + ['disk_used_pct' => 97], null, ['X-Agent-Key' => $key])[1]['ticket_id']));

echo "Métricas y errores\n";
$s = call('GET', '/api/stats', [], $tech)[1];
check('resumen', $s['devices'] === 1 && $s['devices_disk_alert'] === 1 && $s['by_status']['abierto'] === 1);
check('usuario no ve resumen', call('GET', '/api/stats', [], $ana)[0] === 403);
putenv('METRICS_TOKEN=token-metricas');
check('métricas sin token', call('GET', '/metrics')[0] === 401);
$m = call('GET', '/metrics', [], 'token-metricas');
check('métricas Prometheus', $m[0] === 200 && str_contains($m[1], 'soportix_devices 1'));
putenv('METRICS_TOKEN');
check('métricas desactivadas sin configurar', call('GET', '/metrics', [], '')[0] === 401);
check('salud', call('GET', '/api/health')[1]['status'] === 'ok');
check('ruta inexistente', call('GET', '/api/nada')[0] === 404);
check('método incorrecto', call('DELETE', '/api/tickets', [], $admin)[0] === 405);
check('id no numérico', call('GET', '/api/tickets/abc', [], $admin)[0] === 404);

echo "Endurecimiento\n";
check('filtro no UTF-8', call('GET', "/api/tickets?status=\xff", [], $tech)[0] === 422);
check('búsqueda no UTF-8', call('GET', "/api/tickets?q=%FF", [], $tech)[0] === 422);
check('rechaza caracteres de control', call('POST', '/api/tickets', ['title' => "ab\0c", 'description' => 'xxxx', 'priority' => 'baja'], $ana)[0] === 422);
check('identificador fuera de rango', call('PATCH', "/api/tickets/$id", ['assignee_id' => 99999999999], $tech)[0] === 422);
$bloqueos = 0;
for ($i = 0; $i < 35; $i++) {
    $bloqueos += call('POST', '/api/login', ['email' => "x$i@t.test", 'password' => 'mala'], null, [], '10.9.9.9')[0] === 429 ? 1 : 0;
}
check('limita una IP aunque rote correos', $bloqueos === 5);
for ($i = 0; $i < 25; $i++) {
    $estado = call('POST', '/api/login', ['email' => 'objetivo@t.test', 'password' => 'mala'], null, [], "10.8.0.$i")[0];
}
check('limita un correo aunque rote IP', $estado === 429);
for ($i = 1; $i <= 45; $i++) {
    call('POST', '/api/tickets', ['title' => "Carga 100% $i", 'description' => 'xxxx', 'priority' => 'baja'], $luis);
}
$p = call('GET', '/api/tickets?page=3', [], $luis)[1];
check('paginación', $p['total'] === 45 && $p['pages'] === 3 && count($p['items']) === 5 && $p['page'] === 3);
check('página fuera de rango', call('GET', '/api/tickets?page=999', [], $luis)[1]['page'] === 3);
check('búsqueda', call('GET', '/api/tickets?q=' . urlencode('100% 45'), [], $luis)[1]['total'] === 1);
check('búsqueda literal de comodines', call('GET', '/api/tickets?q=' . urlencode('100%'), [], $luis)[1]['total'] === 45 && call('GET', '/api/tickets?q=' . urlencode('%'), [], $luis)[1]['total'] === 45 && call('GET', '/api/tickets?q=' . urlencode('_'), [], $luis)[1]['total'] === 0);
check('la búsqueda respeta permisos', call('GET', '/api/tickets?q=carga', [], $ana)[1]['total'] === 0);
$s1 = call('POST', '/api/login', ['email' => 'tec@t.test', 'password' => 'clave-de-pruebas-1'], null, [], '10.7.0.1')[1]['token'];
$s2 = call('POST', '/api/login', ['email' => 'tec@t.test', 'password' => 'clave-de-pruebas-1'], null, [], '10.7.0.2')[1]['token'];
check('contraseña actual errónea', call('POST', '/api/me/password', ['current' => 'mal', 'new' => 'nueva-clave-segura-1'], $s1)[0] === 422);
check('contraseña demasiado larga', call('POST', '/api/me/password', ['current' => 'clave-de-pruebas-1', 'new' => str_repeat('a', 73)], $s1)[0] === 422);
check('cambia contraseña', call('POST', '/api/me/password', ['current' => 'clave-de-pruebas-1', 'new' => 'nueva-clave-segura-1'], $s1)[0] === 204);
check('mantiene la sesión actual', call('GET', '/api/me', [], $s1)[0] === 200);
check('cierra las demás sesiones', call('GET', '/api/me', [], $s2)[0] === 401);
$anaT = call('POST', '/api/login', ['email' => 'ana@t.test', 'password' => 'clave-de-pruebas-1'], null, [], '10.7.0.3')[1]['token'];
check('solo admin reinicia', call('POST', "/api/users/$anaId/password", ['password' => 'otra-clave-segura-22'], $anaT)[0] === 403);
check('admin reinicia y revoca', call('POST', "/api/users/$anaId/password", ['password' => 'otra-clave-segura-22'], $admin)[0] === 204 && call('GET', '/api/me', [], $anaT)[0] === 401);
$acciones = array_column(call('GET', '/api/audit', [], $admin)[1], 'action');
check('registro de actividad', in_array('usuario.reinicio_contrasena', $acciones, true) && in_array('ticket.cambio', $acciones, true) && in_array('equipo.alta', $acciones, true));
check('registro solo para admin', call('GET', '/api/audit', [], $s1)[0] === 403);
check('el registro no guarda contraseñas', !str_contains(json_encode(call('GET', '/api/audit', [], $admin)[1]), 'otra-clave'));
putenv('AGENT_MIN_INTERVAL=60');
check('limita informes frecuentes', call('POST', '/api/agent/report', $report + ['disk_used_pct' => 40], null, ['X-Agent-Key' => $key])[0] === 429);
putenv('AGENT_MIN_INTERVAL=0');
$db->run("UPDATE device_reports SET reported_at = '2000-01-01T00:00:00Z'");
call('POST', '/api/agent/report', $report + ['disk_used_pct' => 40], null, ['X-Agent-Key' => $key]);
check('purga informes antiguos', (int) $db->value('SELECT COUNT(*) FROM device_reports') === 1);
$activa = $alert;
$dupe = call('PATCH', "/api/tickets/$activa", ['status' => 'abierto'], $s1);
check('no reabre una alerta duplicada', $dupe[0] === 409);

if ($file) {
    unlink($file);
}
echo PHP_EOL . "$passed correctas, $failed fallidas" . PHP_EOL;
exit($failed ? 1 : 0);
