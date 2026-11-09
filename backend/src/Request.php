<?php
declare(strict_types=1);

namespace Soportix;

final class Request
{
    public array $params = [];
    public ?array $user = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $headers,
        public readonly string $ip
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        $raw = (string) file_get_contents('php://input', false, null, 0, 2_000_001);
        if (strlen($raw) > 2_000_000) {
            throw new HttpError(413, 'Cuerpo demasiado grande');
        }
        $body = [];
        if ($raw !== '') {
            $body = json_decode($raw, true);
            if (!is_array($body)) {
                throw new HttpError(400, 'JSON no válido');
            }
        }
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH),
            $_GET,
            $body,
            $headers,
            self::clientIp($headers)
        );
    }

    private static function clientIp(array $headers): string
    {
        $forwarded = trim((string) strrchr(',' . ($headers['x-forwarded-for'] ?? ''), ','), ', ');
        if (getenv('TRUST_PROXY') === '1' && filter_var($forwarded, FILTER_VALIDATE_IP)) {
            return $forwarded;
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function bearer(): ?string
    {
        return preg_match('/^Bearer\s+(\S+)$/i', $this->header('authorization') ?? '', $m) ? $m[1] : null;
    }

    public function int(string $param): int
    {
        return (int) $this->params[$param];
    }
}
