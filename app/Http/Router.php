<?php
declare(strict_types=1);

namespace App\Http;

final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:callable, auth:bool, permission:?string}> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler, bool $auth = true, ?string $permission = null): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $path,
            'handler' => $handler,
            'auth' => $auth,
            'permission' => $permission,
        ];
    }

    public function match(Request $request): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method && $route['method'] !== 'ANY') {
                continue;
            }
            $params = $this->matchPath($route['pattern'], $request->path);
            if ($params === null) {
                continue;
            }
            return ['route' => $route, 'params' => $params];
        }
        return null;
    }

    private function matchPath(string $pattern, string $path): ?array
    {
        $pattern = rtrim($pattern, '/') ?: '/';
        $names = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) use (&$names) {
            $names[] = $m[1];
            return '([^/]+)';
        }, $pattern);
        if (!preg_match('#^' . $regex . '$#', $path, $matches)) {
            return null;
        }
        array_shift($matches);
        $params = [];
        foreach ($names as $i => $name) {
            $params[$name] = $matches[$i] ?? null;
        }
        return $params;
    }
}
