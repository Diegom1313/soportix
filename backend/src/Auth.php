<?php
declare(strict_types=1);

namespace Soportix;

final class Auth
{
    private const TOKEN_TTL = 28800;
    private const MAX_ATTEMPTS = 5;
    private const WINDOW = 900;
    private const DUMMY_HASH = '$2y$10$F99QDoIKedLyHAkOLHNoNu8WCgNstaaywLAeeDTj8uXobCcWTrefO';

    public function __construct(private readonly Database $db)
    {
    }

    public function login(string $email, string $password, string $ip): array
    {
        $identifier = hash('sha256', strtolower($email) . '|' . $ip);
        $since = Clock::now(-self::WINDOW);
        $this->db->run('DELETE FROM login_attempts WHERE attempted_at < ?', [$since]);
        $count = (int) $this->db->value('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at >= ?', [$identifier, $since]);
        if ($count >= self::MAX_ATTEMPTS) {
            throw new HttpError(429, 'Demasiados intentos. Prueba más tarde');
        }
        $user = $this->db->one('SELECT * FROM users WHERE email = ? AND active = 1', [strtolower($email)]);
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;
        if (!password_verify($password, $hash) || !$user) {
            $this->db->run('INSERT INTO login_attempts (identifier, attempted_at) VALUES (?, ?)', [$identifier, Clock::now()]);
            throw new HttpError(401, 'Credenciales incorrectas');
        }
        $this->db->run('DELETE FROM login_attempts WHERE identifier = ?', [$identifier]);
        $this->db->run('DELETE FROM api_tokens WHERE expires_at < ?', [Clock::now()]);
        $token = bin2hex(random_bytes(32));
        $this->db->run('INSERT INTO api_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)', [
            $user['id'], hash('sha256', $token), Clock::now(self::TOKEN_TTL),
        ]);
        return ['token' => $token, 'user' => self::publicUser($user)];
    }

    public function userFromToken(?string $token): ?array
    {
        if ($token === null) {
            return null;
        }
        return $this->db->one(
            'SELECT u.* FROM api_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ? AND t.expires_at > ? AND u.active = 1',
            [hash('sha256', $token), Clock::now()]
        );
    }

    public function logout(string $token): void
    {
        $this->db->run('DELETE FROM api_tokens WHERE token_hash = ?', [hash('sha256', $token)]);
    }

    public static function publicUser(array $user): array
    {
        return [
            'id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email'],
            'role' => $user['role'], 'active' => (int) $user['active'] === 1,
        ];
    }
}
