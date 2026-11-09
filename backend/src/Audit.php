<?php
declare(strict_types=1);

namespace Soportix;

final class Audit
{
    public function __construct(private readonly Database $db)
    {
    }

    public function record(Request $r, string $action, string $detail = ''): void
    {
        $this->db->run(
            'INSERT INTO audit_log (user_id, user_name, action, detail, ip, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$r->user['id'] ?? null, $r->user['name'] ?? null, $action, mb_substr($detail, 0, 255), $r->ip, Clock::now()]
        );
    }
}
