<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Support\Audit;

final class SettingsService
{
    public function __construct(private Database $db, private Audit $audit)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->db->queryOne('SELECT setting_value FROM system_settings WHERE setting_key = ?', [$key]);
        if (!$row) {
            return $default;
        }
        return json_decode((string) $row['setting_value'], true) ?? $default;
    }

    public function put(string $key, mixed $value, ?int $userId, ?string $reason = null): void
    {
        $old = $this->get($key);
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        $this->db->execute(
            'INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
            [$key, $json, $userId]
        );
        $this->db->execute(
            'INSERT INTO setting_revisions (setting_key, old_value, new_value, changed_by, reason) VALUES (?, ?, ?, ?, ?)',
            [$key, $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE), $json, $userId, $reason]
        );
        $this->audit->log($userId, 'settings.update', 'system_setting', $key, 'success', $reason, $old, $value);
    }

    public function identity(): array
    {
        $id = $this->get('identity', []);
        $university = $this->db->queryOne('SELECT * FROM universities ORDER BY id ASC LIMIT 1') ?? [];
        $college = $this->db->queryOne('SELECT * FROM colleges ORDER BY id ASC LIMIT 1') ?? [];
        return array_merge([
            'university_name' => $university['name'] ?? 'الجامعة',
            'university_logo' => $university['logo_path'] ?? null,
            'college_name' => $college['name'] ?? 'الكلية',
            'college_logo' => $college['logo_path'] ?? null,
            'department_name' => '',
            'dean_name' => $college['dean_name'] ?? '',
            'deputy_name' => '',
            'document_title' => 'وصل استلام أجور دراسية',
            'contact_phone' => '',
            'contact_email' => '',
            'contact_address' => '',
            'currency_code' => 'IQD',
            'currency_label' => 'دينار عراقي',
            'header_text' => 'جمهورية العراق — وزارة التعليم العالي والبحث العلمي',
            'footer_text' => 'وثيقة مالية رسمية — لا يعتد بالتعديل اليدوي.',
            'seal_path' => null,
            'signature_path' => null,
            'print_orientation' => 'portrait',
        ], is_array($id) ? $id : []);
    }

    public function financial(): array
    {
        $fin = $this->get('financial', []);
        return array_merge([
            'allow_overpayment' => false,
            'max_discount_rate' => 100,
            'max_discount_amount' => null,
            'late_after_days' => 14,
        ], is_array($fin) ? $fin : []);
    }
}
