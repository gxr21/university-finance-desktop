<?php
declare(strict_types=1);

namespace App\Http;

final class Response
{
    public function __construct(
        public int $status = 200,
        public array $headers = [],
        public string $body = '',
    ) {
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'application/json; charset=utf-8'], json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '{}');
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self($status, ['Location' => $to], '');
    }

    public static function file(string $path, string $downloadName, string $mime): self
    {
        $body = is_file($path) ? (string) file_get_contents($path) : '';
        return new self(200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $downloadName . '"',
        ], $body);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
