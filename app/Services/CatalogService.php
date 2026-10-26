<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Http\HttpException;
use App\Support\Audit;
use App\Support\Money;

final class CatalogService
{
    public function __construct(private Database $db, private Audit $audit)
    {
    }

    public function bootstrap(): array
    {
        return [
            'universities' => $this->db->query('SELECT * FROM universities'),
            'colleges' => $this->db->query('SELECT * FROM colleges'),
            'departments' => $this->db->query('SELECT * FROM departments WHERE is_active=1'),
            'academic_years' => $this->db->query('SELECT * FROM academic_years ORDER BY starts_on DESC'),
            'study_types' => $this->db->query('SELECT * FROM study_types'),
            'study_stages' => $this->db->query('SELECT * FROM study_stages ORDER BY sort_order'),
            'roles' => $this->db->query('SELECT * FROM roles'),
            'permissions' => $this->db->query('SELECT * FROM permissions ORDER BY module, code'),
            'cashboxes' => $this->db->query('SELECT c.*, st.name AS study_type_name FROM cashboxes c JOIN study_types st ON st.id=c.study_type_id'),
            'discount_types' => $this->db->query('SELECT * FROM discount_types'),
            'rooms' => $this->db->query('SELECT * FROM rooms'),
        ];
    }

    public function saveDepartment(array $data, int $userId): int
    {
        if (!empty($data['id'])) {
            $this->db->execute(
                'UPDATE departments SET name=?, code=?, is_active=? WHERE id=?',
                [$data['name'], $data['code'], !empty($data['is_active']) ? 1 : 0, $data['id']]
            );
            $this->audit->log($userId, 'department.update', 'department', $data['id'], 'success');
            return (int) $data['id'];
        }
        $this->db->execute(
            'INSERT INTO departments (college_id, name, code) VALUES (?, ?, ?)',
            [$data['college_id'], $data['name'], $data['code']]
        );
        $id = (int) $this->db->lastId();
        $this->audit->log($userId, 'department.create', 'department', $id, 'success');
        return $id;
    }

    public function saveYear(array $data, int $userId): int
    {
        if (!empty($data['is_current'])) {
            $this->db->execute('UPDATE academic_years SET is_current=0');
        }
        if (!empty($data['id'])) {
            $this->db->execute(
                'UPDATE academic_years SET name=?, starts_on=?, ends_on=?, is_current=?, is_closed=? WHERE id=?',
                [$data['name'], $data['starts_on'], $data['ends_on'], !empty($data['is_current']) ? 1 : 0, !empty($data['is_closed']) ? 1 : 0, $data['id']]
            );
            return (int) $data['id'];
        }
        $this->db->execute(
            'INSERT INTO academic_years (name, starts_on, ends_on, is_current) VALUES (?, ?, ?, ?)',
            [$data['name'], $data['starts_on'], $data['ends_on'], !empty($data['is_current']) ? 1 : 0]
        );
        $id = (int) $this->db->lastId();
        $this->audit->log($userId, 'year.create', 'academic_year', $id, 'success');
        return $id;
    }

    public function saveTuitionPolicy(array $data, int $userId): int
    {
        $count = (int) ($data['installment_count'] ?? 1);
        if (!in_array($count, [1, 2, 4], true) && empty($data['allow_custom'])) {
            // Custom counts are allowed if schedules provided.
        }
        $base = Money::norm((string) ($data['base_amount'] ?? '0'));
        if (Money::cmp($base, '0') <= 0) {
            throw HttpException::unprocessable('قيمة القسط يجب أن تكون أكبر من صفر.');
        }
        $this->db->execute(
            'INSERT INTO tuition_policies (academic_year_id, study_type_id, department_id, study_stage_id, name, base_amount, installment_count, applies_to, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
            [
                $data['academic_year_id'], $data['study_type_id'],
                ($data['department_id'] ?? null) ?: null, ($data['study_stage_id'] ?? null) ?: null,
                $data['name'], $base, $count,
                $data['applies_to'] ?? 'new_only', $userId,
            ]
        );
        $id = (int) $this->db->lastId();
        $schedules = $data['schedules'] ?? [];
        if (!$schedules) {
            $part = Money::norm(bcdiv($base, (string) max(1, $count), 2));
            $allocated = '0.00';
            for ($i = 1; $i <= $count; $i++) {
                $amt = $i === $count ? Money::sub($base, $allocated) : $part;
                $allocated = Money::add($allocated, $amt);
                $this->db->execute(
                    'INSERT INTO installment_schedules (tuition_policy_id, sequence, amount, due_date) VALUES (?, ?, ?, ?)',
                    [$id, $i, $amt, $data['due_dates'][$i - 1] ?? null]
                );
            }
        } else {
            $sum = '0.00';
            foreach ($schedules as $i => $row) {
                $amt = Money::norm((string) $row['amount']);
                $sum = Money::add($sum, $amt);
                $this->db->execute(
                    'INSERT INTO installment_schedules (tuition_policy_id, sequence, amount, due_date) VALUES (?, ?, ?, ?)',
                    [$id, $i + 1, $amt, $row['due_date'] ?? null]
                );
            }
            if (Money::cmp($sum, $base) !== 0) {
                throw HttpException::unprocessable('مجموع جدول الأقساط يجب أن يساوي القسط الأساسي.');
            }
        }
        $this->audit->log($userId, 'tuition.policy', 'tuition_policy', $id, 'success', $data['applies_to'] ?? 'new_only');
        return $id;
    }

    public function saveDiscountRule(array $data, int $userId): int
    {
        $this->db->execute(
            'INSERT INTO discount_rules (discount_type_id, name, calculation, value, category_code, study_type_id, department_id, study_stage_id, stackable, max_amount, required_documents, valid_from, valid_to, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['discount_type_id'], $data['name'], $data['calculation'], Money::norm((string) $data['value']),
                $data['category_code'] ?? null,
                $data['study_type_id'] ?: null,
                $data['department_id'] ?: null,
                $data['study_stage_id'] ?: null,
                !empty($data['stackable']) ? 1 : 0,
                isset($data['max_amount']) && $data['max_amount'] !== '' ? Money::norm((string) $data['max_amount']) : null,
                $data['required_documents'] ?? null,
                $data['valid_from'] ?: null,
                $data['valid_to'] ?: null,
                !isset($data['is_active']) || $data['is_active'] ? 1 : 0,
                $userId,
            ]
        );
        $id = (int) $this->db->lastId();
        $this->audit->log($userId, 'discount.rule', 'discount_rule', $id, 'success');
        return $id;
    }

    public function updateDiscountRule(int $id, array $data, int $userId): void
    {
        $old = $this->db->queryOne('SELECT * FROM discount_rules WHERE id=?', [$id]);
        if (!$old) {
            throw HttpException::notFound();
        }
        $this->db->execute(
            'UPDATE discount_rules SET name=?, calculation=?, value=?, category_code=?, stackable=?, max_amount=?, is_active=?, updated_by=? WHERE id=?',
            [
                $data['name'] ?? $old['name'],
                $data['calculation'] ?? $old['calculation'],
                isset($data['value']) ? Money::norm((string) $data['value']) : $old['value'],
                $data['category_code'] ?? $old['category_code'],
                array_key_exists('stackable', $data) ? (!empty($data['stackable']) ? 1 : 0) : $old['stackable'],
                array_key_exists('max_amount', $data) ? ($data['max_amount'] === '' || $data['max_amount'] === null ? null : Money::norm((string) $data['max_amount'])) : $old['max_amount'],
                array_key_exists('is_active', $data) ? (!empty($data['is_active']) ? 1 : 0) : $old['is_active'],
                $userId,
                $id,
            ]
        );
        $this->audit->log($userId, 'discount.rule_update', 'discount_rule', $id, 'success', null, $old, $data);
    }
}
