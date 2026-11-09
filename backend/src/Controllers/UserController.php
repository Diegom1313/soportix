<?php
declare(strict_types=1);

namespace Soportix\Controllers;

use Soportix\Audit;
use Soportix\Auth;
use Soportix\Clock;
use Soportix\Database;
use Soportix\HttpError;
use Soportix\Request;
use Soportix\Validator;

final class UserController
{
    private const ROLES = ['admin', 'tecnico', 'usuario'];

    public function __construct(private readonly Database $db, private readonly Auth $auth, private readonly Audit $audit)
    {
    }

    public function index(Request $r): array
    {
        return [200, array_map([Auth::class, 'publicUser'], $this->db->all('SELECT * FROM users ORDER BY name'))];
    }

    public function technicians(Request $r): array
    {
        return [200, $this->db->all("SELECT id, name FROM users WHERE role IN ('admin','tecnico') AND active = 1 ORDER BY name")];
    }

    public function store(Request $r): array
    {
        $data = (new Validator($r->body))
            ->string('name', 2, 120)->email('email')->password('password')->in('role', self::ROLES)->validated();
        if ($this->db->value('SELECT COUNT(*) FROM users WHERE email = ?', [$data['email']])) {
            throw new HttpError(409, 'Ese correo ya existe');
        }
        $id = $this->db->insert(
            'INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)',
            [$data['name'], $data['email'], password_hash($data['password'], PASSWORD_DEFAULT), $data['role'], Clock::now()]
        );
        $this->audit->record($r, 'usuario.alta', "{$data['email']} ({$data['role']})");
        return [201, Auth::publicUser($this->db->one('SELECT * FROM users WHERE id = ?', [$id]))];
    }

    public function resetPassword(Request $r): array
    {
        $id = $r->int('id');
        $user = $this->db->one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) {
            throw new HttpError(404, 'Usuario no encontrado');
        }
        $data = (new Validator($r->body))->password('password')->validated();
        $this->auth->setPassword($id, $data['password']);
        $this->auth->revokeAll($id);
        $this->audit->record($r, 'usuario.reinicio_contrasena', $user['email']);
        return [204, null];
    }

    public function update(Request $r): array
    {
        $id = $r->int('id');
        $user = $this->db->one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) {
            throw new HttpError(404, 'Usuario no encontrado');
        }
        $data = (new Validator($r->body))->in('role', self::ROLES, false)->in('active', [true, false], false)->validated();
        $role = $data['role'] ?? $user['role'];
        $active = array_key_exists('active', $data) ? (int) $data['active'] : (int) $user['active'];
        if ($user['role'] === 'admin' && ($role !== 'admin' || $active === 0)
            && (int) $this->db->value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1") <= 1) {
            throw new HttpError(409, 'Debe quedar al menos un administrador activo');
        }
        if ($id === (int) $r->user['id'] && $active === 0) {
            throw new HttpError(409, 'No puedes desactivar tu propia cuenta');
        }
        $this->db->run('UPDATE users SET role = ?, active = ? WHERE id = ?', [$role, $active, $id]);
        if ($active === 0) {
            $this->auth->revokeAll($id);
        }
        $this->audit->record($r, 'usuario.cambio', "{$user['email']}: rol $role, " . ($active ? 'activo' : 'inactivo'));
        return [200, Auth::publicUser($this->db->one('SELECT * FROM users WHERE id = ?', [$id]))];
    }
}
