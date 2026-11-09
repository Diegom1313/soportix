<?php
declare(strict_types=1);

namespace Soportix\Controllers;

use Soportix\Clock;
use Soportix\Database;
use Soportix\HttpError;
use Soportix\Request;
use Soportix\Validator;

final class TicketController
{
    public const SLA_HOURS = ['critica' => 4, 'alta' => 8, 'media' => 24, 'baja' => 72];
    private const STATUSES = ['abierto', 'en_curso', 'resuelto', 'cerrado'];
    private const SELECT = 'SELECT t.*, r.name AS requester_name, a.name AS assignee_name, d.hostname AS device_hostname
        FROM tickets t LEFT JOIN users r ON r.id = t.requester_id LEFT JOIN users a ON a.id = t.assignee_id LEFT JOIN devices d ON d.id = t.device_id';

    public function __construct(private readonly Database $db)
    {
    }

    private static function isStaff(array $user): bool
    {
        return in_array($user['role'], ['admin', 'tecnico'], true);
    }

    private function find(Request $r): array
    {
        $ticket = $this->db->one(self::SELECT . ' WHERE t.id = ?', [$r->int('id')]);
        if (!$ticket || (!self::isStaff($r->user) && (int) $ticket['requester_id'] !== (int) $r->user['id'])) {
            throw new HttpError(404, 'Ticket no encontrado');
        }
        return $ticket;
    }

    private static function present(array $t): array
    {
        foreach (['id', 'requester_id', 'assignee_id', 'device_id'] as $key) {
            $t[$key] = $t[$key] === null ? null : (int) $t[$key];
        }
        $t['overdue'] = in_array($t['status'], ['abierto', 'en_curso'], true) && $t['due_at'] < Clock::now();
        return $t;
    }

    public function index(Request $r): array
    {
        $where = [];
        $params = [];
        if (!self::isStaff($r->user)) {
            $where[] = 't.requester_id = ?';
            $params[] = $r->user['id'];
        }
        foreach (['status', 'priority'] as $filter) {
            if (is_string($r->query[$filter] ?? null) && $r->query[$filter] !== '') {
                $where[] = "t.$filter = ?";
                $params[] = $r->query[$filter];
            }
        }
        $page = min(100000, max(1, (int) ($r->query['page'] ?? 1)));
        $sql = self::SELECT . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY t.id DESC LIMIT 50 OFFSET ' . (($page - 1) * 50);
        return [200, array_map([self::class, 'present'], $this->db->all($sql, $params))];
    }

    public function show(Request $r): array
    {
        $ticket = self::present($this->find($r));
        $sql = 'SELECT c.id, c.body, c.internal, c.created_at, u.name AS author FROM ticket_comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.ticket_id = ?';
        if (!self::isStaff($r->user)) {
            $sql .= ' AND c.internal = 0';
        }
        $ticket['comments'] = $this->db->all($sql . ' ORDER BY c.id', [$ticket['id']]);
        return [200, $ticket];
    }

    public function store(Request $r): array
    {
        $validator = (new Validator($r->body))->string('title', 3, 160)->string('description', 3, 5000)->in('priority', array_keys(self::SLA_HOURS));
        if (self::isStaff($r->user)) {
            $validator->id('device_id');
        }
        $data = $validator->validated();
        if (isset($data['device_id']) && !$this->db->value('SELECT COUNT(*) FROM devices WHERE id = ?', [$data['device_id']])) {
            throw new HttpError(422, 'Datos no válidos', ['device_id' => 'No existe']);
        }
        $now = Clock::now();
        $id = $this->db->insert(
            'INSERT INTO tickets (title, description, priority, requester_id, device_id, due_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$data['title'], $data['description'], $data['priority'], $r->user['id'], $data['device_id'] ?? null,
                Clock::now(self::SLA_HOURS[$data['priority']] * 3600), $now, $now]
        );
        return [201, self::present($this->db->one(self::SELECT . ' WHERE t.id = ?', [$id]))];
    }

    public function update(Request $r): array
    {
        $ticket = $this->find($r);
        $data = (new Validator($r->body))
            ->in('status', self::STATUSES, false)->in('priority', array_keys(self::SLA_HOURS), false)->id('assignee_id')->validated();
        if (array_key_exists('assignee_id', $data) && $data['assignee_id'] !== null
            && !$this->db->value("SELECT COUNT(*) FROM users WHERE id = ? AND role IN ('admin','tecnico') AND active = 1", [$data['assignee_id']])) {
            throw new HttpError(422, 'Datos no válidos', ['assignee_id' => 'Debe ser un técnico activo']);
        }
        $status = $data['status'] ?? $ticket['status'];
        $priority = $data['priority'] ?? $ticket['priority'];
        $assignee = array_key_exists('assignee_id', $data) ? $data['assignee_id'] : $ticket['assignee_id'];
        $resolved = in_array($status, ['resuelto', 'cerrado'], true) ? ($ticket['resolved_at'] ?? Clock::now()) : null;
        $due = $priority !== $ticket['priority']
            ? gmdate('Y-m-d\TH:i:s\Z', strtotime($ticket['created_at']) + self::SLA_HOURS[$priority] * 3600)
            : $ticket['due_at'];
        $this->db->run(
            'UPDATE tickets SET status = ?, priority = ?, assignee_id = ?, resolved_at = ?, due_at = ?, updated_at = ? WHERE id = ?',
            [$status, $priority, $assignee, $resolved, $due, Clock::now(), $ticket['id']]
        );
        return [200, self::present($this->db->one(self::SELECT . ' WHERE t.id = ?', [$ticket['id']]))];
    }

    public function comment(Request $r): array
    {
        $ticket = $this->find($r);
        $data = (new Validator($r->body))->string('body', 1, 5000)->in('internal', [true, false], false)->validated();
        $internal = !empty($data['internal']);
        if ($internal && !self::isStaff($r->user)) {
            throw new HttpError(403, 'Sin permiso');
        }
        if (in_array($ticket['status'], ['cerrado'], true)) {
            throw new HttpError(409, 'El ticket está cerrado');
        }
        $id = $this->db->insert(
            'INSERT INTO ticket_comments (ticket_id, user_id, body, internal, created_at) VALUES (?, ?, ?, ?, ?)',
            [$ticket['id'], $r->user['id'], $data['body'], (int) $internal, Clock::now()]
        );
        return [201, ['id' => $id]];
    }
}
