<?php
declare(strict_types=1);

namespace Soportix;

use Soportix\Controllers\AuthController;
use Soportix\Controllers\DeviceController;
use Soportix\Controllers\StatsController;
use Soportix\Controllers\TicketController;
use Soportix\Controllers\UserController;

final class App
{
    private Router $router;
    private Auth $auth;

    public function __construct(Database $db)
    {
        $this->auth = new Auth($db);
        $this->router = new Router();
        $r = $this->router;
        $auth = new AuthController($this->auth);
        $users = new UserController($db);
        $tickets = new TicketController($db);
        $devices = new DeviceController($db);
        $stats = new StatsController($db);
        $staff = ['admin', 'tecnico'];
        $admin = ['admin'];

        $r->add('GET', '/api/health', [$stats, 'health']);
        $r->add('GET', '/metrics', [$stats, 'metrics']);
        $r->add('POST', '/api/login', [$auth, 'login']);
        $r->add('POST', '/api/logout', [$auth, 'logout'], []);
        $r->add('GET', '/api/me', [$auth, 'me'], []);
        $r->add('GET', '/api/users', [$users, 'index'], $admin);
        $r->add('POST', '/api/users', [$users, 'store'], $admin);
        $r->add('PATCH', '/api/users/{id}', [$users, 'update'], $admin);
        $r->add('GET', '/api/technicians', [$users, 'technicians'], $staff);
        $r->add('GET', '/api/tickets', [$tickets, 'index'], []);
        $r->add('POST', '/api/tickets', [$tickets, 'store'], []);
        $r->add('GET', '/api/tickets/{id}', [$tickets, 'show'], []);
        $r->add('PATCH', '/api/tickets/{id}', [$tickets, 'update'], $staff);
        $r->add('POST', '/api/tickets/{id}/comments', [$tickets, 'comment'], []);
        $r->add('GET', '/api/devices', [$devices, 'index'], $staff);
        $r->add('POST', '/api/devices', [$devices, 'store'], $admin);
        $r->add('GET', '/api/devices/{id}', [$devices, 'show'], $staff);
        $r->add('POST', '/api/agent/report', [$devices, 'report']);
        $r->add('GET', '/api/stats', [$stats, 'summary'], $staff);
    }

    public function handle(Request $request): array
    {
        try {
            $result = $this->router->dispatch($request, $this->auth);
            return [$result[0], $result[1], $result[2] ?? 'application/json'];
        } catch (HttpError $e) {
            $body = ['error' => $e->getMessage()];
            if ($e->fields) {
                $body['fields'] = $e->fields;
            }
            return [$e->status, $body, 'application/json'];
        } catch (\Throwable $e) {
            error_log((string) $e);
            return [500, ['error' => 'Error interno'], 'application/json'];
        }
    }
}
