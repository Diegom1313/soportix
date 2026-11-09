<?php
declare(strict_types=1);

namespace Soportix\Controllers;

use Soportix\Auth;
use Soportix\HttpError;
use Soportix\Request;
use Soportix\Validator;

final class AuthController
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function login(Request $r): array
    {
        $data = (new Validator($r->body))->email('email')->string('password', 1, 200)->validated();
        return [200, $this->auth->login($data['email'], $data['password'], $r->ip)];
    }

    public function logout(Request $r): array
    {
        $this->auth->logout((string) $r->bearer());
        return [204, null];
    }

    public function me(Request $r): array
    {
        return [200, Auth::publicUser($r->user)];
    }
}
