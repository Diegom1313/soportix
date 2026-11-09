<?php
declare(strict_types=1);

namespace Soportix;

use PDO;

final class Database
{
    public readonly PDO $pdo;
    public readonly string $driver;

    public function __construct(string $dsn, ?string $user = null, ?string $password = null)
    {
        $this->pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($this->driver === 'sqlite') {
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
        }
    }

    public static function fromEnv(): self
    {
        return new self(
            getenv('DB_DSN') ?: 'sqlite:' . dirname(__DIR__) . '/data/soportix.db',
            getenv('DB_USER') ?: null,
            getenv('DB_PASSWORD') ?: null
        );
    }

    public function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function one(string $sql, array $params = []): ?array
    {
        return $this->run($sql, $params)->fetch() ?: null;
    }

    public function value(string $sql, array $params = []): mixed
    {
        return $this->run($sql, $params)->fetchColumn();
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    public function migrate(string $dir): array
    {
        $pk = $this->driver === 'pgsql' ? 'BIGSERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(190) PRIMARY KEY, applied_at VARCHAR(25) NOT NULL)');
        $applied = [];
        foreach (glob($dir . '/*.sql') ?: [] as $file) {
            $name = basename($file);
            if ($this->value('SELECT COUNT(*) FROM migrations WHERE name = ?', [$name])) {
                continue;
            }
            $this->pdo->beginTransaction();
            try {
                foreach (array_filter(array_map('trim', explode(';', (string) file_get_contents($file)))) as $statement) {
                    $this->pdo->exec(str_replace('{{PK}}', $pk, $statement));
                }
                $this->run('INSERT INTO migrations (name, applied_at) VALUES (?, ?)', [$name, Clock::now()]);
                $this->pdo->commit();
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
            $applied[] = $name;
        }
        return $applied;
    }
}
