<?php
declare(strict_types=1);

namespace Soportix\Controllers;

use Soportix\Clock;
use Soportix\Database;
use Soportix\HttpError;
use Soportix\Request;

final class StatsController
{
    public function __construct(private readonly Database $db)
    {
    }

    private function counts(): array
    {
        $now = Clock::now();
        $byStatus = array_fill_keys(['abierto', 'en_curso', 'resuelto', 'cerrado'], 0);
        foreach ($this->db->all('SELECT status, COUNT(*) AS n FROM tickets GROUP BY status') as $row) {
            $byStatus[$row['status']] = (int) $row['n'];
        }
        $byPriority = array_fill_keys(['baja', 'media', 'alta', 'critica'], 0);
        foreach ($this->db->all("SELECT priority, COUNT(*) AS n FROM tickets WHERE status IN ('abierto','en_curso') GROUP BY priority") as $row) {
            $byPriority[$row['priority']] = (int) $row['n'];
        }
        return [
            'by_status' => $byStatus,
            'open_by_priority' => $byPriority,
            'overdue' => (int) $this->db->value("SELECT COUNT(*) FROM tickets WHERE status IN ('abierto','en_curso') AND due_at < ?", [$now]),
            'devices' => (int) $this->db->value('SELECT COUNT(*) FROM devices'),
            'devices_stale' => (int) $this->db->value('SELECT COUNT(*) FROM devices WHERE last_seen IS NULL OR last_seen < ?', [Clock::now(-86400)]),
            'devices_disk_alert' => (int) $this->db->value('SELECT COUNT(*) FROM devices WHERE disk_used_pct >= 90'),
        ];
    }

    public function summary(Request $r): array
    {
        return [200, $this->counts()];
    }

    public function audit(Request $r): array
    {
        return [200, $this->db->all('SELECT id, user_name, action, detail, ip, created_at FROM audit_log ORDER BY id DESC LIMIT 200')];
    }

    public function health(Request $r): array
    {
        $this->db->value('SELECT 1');
        return [200, ['status' => 'ok']];
    }

    public function metrics(Request $r): array
    {
        $expected = getenv('METRICS_TOKEN') ?: '';
        if ($expected === '' || !hash_equals($expected, (string) $r->bearer())) {
            throw new HttpError(401, 'No autenticado');
        }
        $c = $this->counts();
        $lines = ['# TYPE soportix_tickets gauge'];
        foreach ($c['by_status'] as $status => $n) {
            $lines[] = "soportix_tickets{status=\"$status\"} $n";
        }
        $lines[] = '# TYPE soportix_tickets_open_priority gauge';
        foreach ($c['open_by_priority'] as $priority => $n) {
            $lines[] = "soportix_tickets_open_priority{priority=\"$priority\"} $n";
        }
        foreach (['overdue' => 'tickets_overdue', 'devices' => 'devices', 'devices_stale' => 'devices_stale', 'devices_disk_alert' => 'devices_disk_alert'] as $key => $name) {
            $lines[] = "# TYPE soportix_$name gauge";
            $lines[] = "soportix_$name {$c[$key]}";
        }
        return [200, implode("\n", $lines) . "\n", 'text/plain; version=0.0.4'];
    }
}
