<?php
declare(strict_types=1);

namespace Soportix\Controllers;

use Soportix\Audit;
use Soportix\Auth;
use Soportix\HttpError;
use Soportix\Request;
use Soportix\Validator;

final class AuthController
{
    public function __construct(private readonly Auth $auth, private readonly Audit $audit)
    {
    }

    public function login(Request $r): array
    {
        $data = (new Validator($r->body))->email('email')->string('password', 1, 200)->validated();
        $session = $this->auth->login($data['email'], $data['password'], $r->ip);
        $r->user = $session['user'];
        $this->audit->record($r, 'sesion.inicio');
        return [200, $session];
    }

    public function logout(Request $r): array
    {
        $this->auth->logout((string) $r->bearer());
        return [204, null];
    }

    public function changePassword(Request $r): array
    {
        $data = (new Validator($r->body))->string('current', 1, 200)->password('new')->validated();
        $this->auth->changePassword($r->user, $data['current'], $data['new'], (string) $r->bearer());
        $this->audit->record($r, 'usuario.cambio_contrasena');
        return [204, null];
    }

    public function me(Request $r): array
    {
        return [200, Auth::publicUser($r->user)];
    }
}
