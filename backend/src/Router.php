<?php
declare(strict_types=1);

namespace Soportix;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler, ?array $roles = null): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[1-9][0-9]{0,17})', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler, $roles];
    }

    public function dispatch(Request $request, Auth $auth): array
    {
        $methodMismatch = false;
        foreach ($this->routes as [$method, $regex, $handler, $roles]) {
            if (!preg_match($regex, $request->path, $matches)) {
                continue;
            }
            if ($method !== $request->method) {
                $methodMismatch = true;
                continue;
            }
            $request->params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            if ($roles !== null) {
                $request->user = $auth->userFromToken($request->bearer());
                if (!$request->user) {
                    throw new HttpError(401, 'No autenticado');
                }
                if ($roles !== [] && !in_array($request->user['role'], $roles, true)) {
                    throw new HttpError(403, 'Sin permiso');
                }
            }
            return $handler($request);
        }
        throw new HttpError($methodMismatch ? 405 : 404, $methodMismatch ? 'Método no permitido' : 'No encontrado');
    }
}
