<?php
declare(strict_types=1);

use Soportix\Database;

require dirname(__DIR__) . '/src/bootstrap.php';

$applied = Database::fromEnv()->migrate(dirname(__DIR__) . '/migrations');
echo $applied ? 'Aplicadas: ' . implode(', ', $applied) . PHP_EOL : 'Sin migraciones pendientes' . PHP_EOL;
