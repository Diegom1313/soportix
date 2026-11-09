<?php
declare(strict_types=1);

namespace Soportix\Controllers;

use Soportix\Clock;
use Soportix\Database;
use Soportix\HttpError;
use Soportix\Request;
use Soportix\Validator;

final class DeviceController
{
    private const DISK_ALERT_PCT = 90;
    private const COLUMNS = 'id, hostname, os, cpu, ram_mb, disk_total_gb, disk_used_pct, ip, last_seen, created_at';

    public function __construct(private readonly Database $db)
    {
    }

    public function index(Request $r): array
    {
        return [200, $this->db->all('SELECT ' . self::COLUMNS . ' FROM devices ORDER BY hostname')];
    }

    public function show(Request $r): array
    {
        $device = $this->db->one('SELECT ' . self::COLUMNS . ', software FROM devices WHERE id = ?', [$r->int('id')]);
        if (!$device) {
            throw new HttpError(404, 'Equipo no encontrado');
        }
        $device['software'] = json_decode($device['software'] ?? '[]', true) ?: [];
        $device['history'] = array_reverse($this->db->all(
            'SELECT disk_used_pct, reported_at FROM device_reports WHERE device_id = ? ORDER BY id DESC LIMIT 30',
            [$device['id']]
        ));
        $device['tickets'] = $this->db->all('SELECT id, title, status, priority FROM tickets WHERE device_id = ? ORDER BY id DESC LIMIT 20', [$device['id']]);
        return [200, $device];
    }

    public function store(Request $r): array
    {
        $data = (new Validator($r->body))->string('hostname', 1, 190)->validated();
        if ($this->db->value('SELECT COUNT(*) FROM devices WHERE hostname = ?', [$data['hostname']])) {
            throw new HttpError(409, 'Ese equipo ya existe');
        }
        $key = bin2hex(random_bytes(32));
        $id = $this->db->insert(
            'INSERT INTO devices (hostname, agent_key_hash, created_at) VALUES (?, ?, ?)',
            [$data['hostname'], hash('sha256', $key), Clock::now()]
        );
        return [201, ['id' => $id, 'hostname' => $data['hostname'], 'agent_key' => $key]];
    }

    public function report(Request $r): array
    {
        $key = $r->header('x-agent-key');
        $device = $key ? $this->db->one('SELECT * FROM devices WHERE agent_key_hash = ?', [hash('sha256', $key)]) : null;
        if (!$device) {
            throw new HttpError(401, 'Clave de agente no válida');
        }
        $data = (new Validator($r->body))->string('os', 1, 190, false)->string('cpu', 1, 190, false)->string('ip', 2, 45, false)->validated();
        $ram = self::range($r->body['ram_mb'] ?? null, 0, 100_000_000, 'ram_mb');
        $disk = self::range($r->body['disk_total_gb'] ?? null, 0, 10_000_000, 'disk_total_gb');
        $used = self::range($r->body['disk_used_pct'] ?? null, 0, 100, 'disk_used_pct');
        $software = array_values(array_slice(array_filter(
            is_array($r->body['software'] ?? null) ? $r->body['software'] : [],
            fn ($item) => is_string($item) && strlen($item) <= 200
        ), 0, 2000));
        $now = Clock::now();
        $this->db->pdo->beginTransaction();
        try {
            $this->db->run(
                'UPDATE devices SET os = ?, cpu = ?, ip = ?, ram_mb = ?, disk_total_gb = ?, disk_used_pct = ?, software = ?, last_seen = ? WHERE id = ?',
                [$data['os'] ?? null, $data['cpu'] ?? null, $data['ip'] ?? null, $ram, $disk, $used, json_encode($software, JSON_UNESCAPED_UNICODE), $now, $device['id']]
            );
            $this->db->run('INSERT INTO device_reports (device_id, disk_used_pct, reported_at) VALUES (?, ?, ?)', [$device['id'], $used, $now]);
            $ticketId = $used !== null && $used >= self::DISK_ALERT_PCT ? $this->diskAlert($device, $used, $now) : null;
            $this->db->pdo->commit();
        } catch (\Throwable $e) {
            $this->db->pdo->rollBack();
            throw $e;
        }
        return [200, ['ok' => true, 'ticket_id' => $ticketId]];
    }

    private function diskAlert(array $device, int $used, string $now): ?int
    {
        $open = $this->db->value(
            "SELECT COUNT(*) FROM tickets WHERE device_id = ? AND alert_key = 'disk' AND status IN ('abierto','en_curso')",
            [$device['id']]
        );
        if ($open) {
            return null;
        }
        return $this->db->insert(
            "INSERT INTO tickets (title, description, priority, source, alert_key, device_id, due_at, created_at, updated_at) VALUES (?, ?, 'alta', 'agente', 'disk', ?, ?, ?, ?)",
            ["Disco casi lleno en {$device['hostname']}", "El agente informa de un uso de disco del $used %.", $device['id'],
                Clock::now(TicketController::SLA_HOURS['alta'] * 3600), $now, $now]
        );
    }

    private static function range(mixed $value, int $min, int $max, string $field): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new HttpError(422, 'Datos no válidos', [$field => "Debe ser un entero entre $min y $max"]);
        }
        return $value;
    }
}
