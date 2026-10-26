<?php
declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(
        public int $status,
        string $message,
        public array $errors = [],
    ) {
        parent::__construct($message, $status);
    }

    public static function unauthorized(string $message = 'يلزم تسجيل الدخول.'): self
    {
        return new self(401, $message);
    }

    public static function forbidden(string $message = 'ليست لديك صلاحية تنفيذ هذه العملية.'): self
    {
        return new self(403, $message);
    }

    public static function notFound(string $message = 'العنصر غير موجود.'): self
    {
        return new self(404, $message);
    }

    public static function conflict(string $message): self
    {
        return new self(409, $message);
    }

    public static function unprocessable(string $message, array $errors = []): self
    {
        return new self(422, $message, $errors);
    }

    public static function tooMany(string $message = 'تجاوزت عدد المحاولات المسموح. حاول لاحقاً.'): self
    {
        return new self(429, $message);
    }
}
