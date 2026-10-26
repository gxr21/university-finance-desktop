<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Http\HttpException;
use App\Support\Audit;
use App\Support\Money;

final class TeacherService
{
    public function __construct(private Database $db, private Audit $audit)
    {
    }

    public function listStaff(): array
    {
        return $this->db->query(
            'SELECT t.*, d.name AS department_name FROM teaching_staff t LEFT JOIN departments d ON d.id=t.department_id ORDER BY t.full_name'
        );
    }

    public function saveStaff(array $data, int $userId): int
    {
        if (!empty($data['id'])) {
            $this->db->execute(
                'UPDATE teaching_staff SET full_name=?, department_id=?, title=?, staff_number=?, is_active=? WHERE id=?',
                [$data['full_name'], $data['department_id'] ?: null, $data['title'] ?? null, $data['staff_number'] ?? null, !empty($data['is_active']) ? 1 : 0, $data['id']]
            );
            return (int) $data['id'];
        }
        $this->db->execute(
            'INSERT INTO teaching_staff (full_name, department_id, title, staff_number) VALUES (?, ?, ?, ?)',
            [$data['full_name'], $data['department_id'] ?: null, $data['title'] ?? null, $data['staff_number'] ?? null]
        );
        $id = (int) $this->db->lastId();
        $this->audit->log($userId, 'staff.create', 'teaching_staff', $id, 'success');
        return $id;
    }

    public function listFees(): array
    {
        return $this->db->query(
            'SELECT f.*, s.full_name, t.name AS type_name, y.name AS year_name
             FROM teacher_fees f
             JOIN teaching_staff s ON s.id=f.staff_id
             JOIN teacher_fee_types t ON t.id=f.fee_type_id
             JOIN academic_years y ON y.id=f.academic_year_id
             ORDER BY f.id DESC'
        );
    }

    public function createFee(array $data, int $userId): int
    {
        $due = Money::norm((string) ($data['due_amount'] ?? '0'));
        if (Money::cmp($due, '0') <= 0) {
            throw HttpException::unprocessable('المبلغ المستحق يجب أن يكون موجباً. المعادلة تُحدَّد من نوع الاستحقاق لا من الشيفرة.');
        }
        $this->db->execute(
            'INSERT INTO teacher_fees (staff_id, academic_year_id, fee_type_id, due_amount, paid_amount, remaining, due_date, status, notes, created_by)
             VALUES (?, ?, ?, ?, 0, ?, ?, "pending", ?, ?)',
            [
                $data['staff_id'], $data['academic_year_id'], $data['fee_type_id'], $due, $due,
                $data['due_date'] ?: null, $data['notes'] ?? null, $userId,
            ]
        );
        $id = (int) $this->db->lastId();
        $this->audit->log($userId, 'teacher_fee.create', 'teacher_fee', $id, 'success');
        return $id;
    }

    public function payFee(int $id, string $amount, int $userId, ?string $notes = null): void
    {
        $this->db->transaction(function () use ($id, $amount, $userId, $notes) {
            $fee = $this->db->queryOne('SELECT * FROM teacher_fees WHERE id=? FOR UPDATE', [$id]);
            if (!$fee) {
                throw HttpException::notFound();
            }
            $amt = Money::norm($amount);
            if (Money::cmp($amt, '0') <= 0 || Money::cmp($amt, $fee['remaining']) > 0) {
                throw HttpException::unprocessable('مبلغ الصرف غير صالح أو يتجاوز المتبقي.');
            }
            $paid = Money::add($fee['paid_amount'], $amt);
            $rem = Money::sub($fee['due_amount'], $paid);
            $status = Money::cmp($rem, '0') <= 0 ? 'paid' : 'partial';
            $this->db->execute(
                'UPDATE teacher_fees SET paid_amount=?, remaining=?, status=? WHERE id=?',
                [$paid, $rem, $status, $id]
            );
            $this->db->execute(
                'INSERT INTO teacher_fee_payments (teacher_fee_id, amount, notes, created_by) VALUES (?, ?, ?, ?)',
                [$id, $amt, $notes, $userId]
            );
            $this->audit->log($userId, 'teacher_fee.pay', 'teacher_fee', $id, 'success', $notes, null, ['amount' => $amt]);
        });
    }
}
