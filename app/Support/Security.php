<?php
declare(strict_types=1);

namespace App\Support;

final class Security
{
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function validEmail(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function validPhone(string $phone): bool
    {
        $phone = preg_replace('/\s+/', '', $phone) ?? '';
        return (bool) preg_match('/^(07[0-9]{9}|\+9647[0-9]{9})$/', $phone);
    }

    public static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone) ?? '';
        if (str_starts_with($phone, '+964')) {
            return '0' . substr($phone, 4);
        }
        return $phone;
    }
}
