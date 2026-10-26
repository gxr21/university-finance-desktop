<?php
declare(strict_types=1);

namespace App\Http;

final class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $query,
        public array $body,
        public array $headers,
        public array $cookies,
        public array $files,
        public string $ip,
        public string $userAgent,
        public mixed $user = null,
        public array $permissions = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        $body = is_array($json) ? $json : $_POST;
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        return new self(
            $method,
            $path === '' ? '/' : $path,
            $_GET,
            is_array($body) ? $body : [],
            $headers,
            $_COOKIE,
            $_FILES,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
        );
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function bearer(): ?string
    {
        $auth = $this->header('authorization');
        if ($auth && str_starts_with(strtolower($auth), 'bearer ')) {
            return trim(substr($auth, 7));
        }
        return null;
    }

    public function wantsJson(): bool
    {
        return str_starts_with($this->path, '/api')
            || str_contains((string) $this->header('accept', ''), 'application/json');
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
