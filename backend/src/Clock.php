<?php
declare(strict_types=1);

namespace Soportix;

final class Clock
{
    public static function now(int $offsetSeconds = 0): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', time() + $offsetSeconds);
    }
}
