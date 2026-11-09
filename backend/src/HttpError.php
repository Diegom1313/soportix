<?php
declare(strict_types=1);

namespace Soportix;

final class HttpError extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message, public readonly array $fields = [])
    {
        parent::__construct($message);
    }
}
