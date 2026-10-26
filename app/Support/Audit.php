<?php
declare(strict_types=1);

namespace App\Support;

use App\Database;

final class Audit
{
    public function __construct(private Database $db)
    {
    }

    public function log(
        ?int $userId,
        string $action,
        string $entityType,
        string|int|null $entityId,
        string $result = 'success',
        ?string $reason = null,
        mixed $before = null,
        mixed $after = null,
        ?string $ip = null,
    ): void {
        $this->db->execute(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, result, reason, before_json, after_json, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $action,
                $entityType,
                $entityId === null ? null : (string) $entityId,
                $result,
                $reason,
                $before === null ? null : json_encode($this->scrub($before), JSON_UNESCAPED_UNICODE),
                $after === null ? null : json_encode($this->scrub($after), JSON_UNESCAPED_UNICODE),
                $ip,
            ]
        );
    }

    private function scrub(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }
        $blocked = ['password', 'password_hash', 'token', 'token_hash', 'secret', 'qr', 'csrf'];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower((string) $key), $blocked, true)) {
                $data[$key] = '[محجوب]';
            } elseif (is_array($value)) {
                $data[$key] = $this->scrub($value);
            }
        }
        return $data;
    }
}
