<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Http\HttpException;
use App\Support\Audit;
use App\Support\Money;

final class InvoiceService
{
    public function __construct(private Database $db, private Audit $audit, private SettingsService $settings)
    {
    }

    public function issueForEnrollment(int $enrollmentId, int $userId): array
    {
        $existing = $this->db->queryOne('SELECT * FROM student_invoices WHERE enrollment_id = ?', [$enrollmentId]);
        if ($existing) {
            return $existing;
        }
        $enroll = $this->db->queryOne('SELECT * FROM student_enrollments WHERE id = ?', [$enrollmentId]);
        if (!$enroll) {
            throw HttpException::notFound('القيد الدراسي غير موجود.');
        }
        $policy = $this->matchPolicy($enroll);
        if (!$policy) {
            throw HttpException::unprocessable('لا توجد سياسة قسط مطابقة لهذا الطالب. أضف سياسة من إدارة الأجور أولاً.');
        }
        $base = Money::norm($policy['base_amount']);
        $this->db->execute(
            'INSERT INTO student_invoices (enrollment_id, tuition_policy_id, academic_year_id, base_amount, discount_total, net_due, paid_total, remaining, status, created_by)
             VALUES (?, ?, ?, ?, 0, ?, 0, ?, "unpaid", ?)',
            [$enrollmentId, $policy['id'], $enroll['academic_year_id'], $base, $base, $base, $userId]
        );
        $invoiceId = (int) $this->db->lastId();
        $schedules = $this->db->query(
            'SELECT * FROM installment_schedules WHERE tuition_policy_id = ? ORDER BY sequence',
            [$policy['id']]
        );
        if (!$schedules) {
            $this->db->execute(
                'INSERT INTO invoice_installments (invoice_id, sequence, amount, due_date) VALUES (?, 1, ?, NULL)',
                [$invoiceId, $base]
            );
        } else {
            foreach ($schedules as $row) {
                $this->db->execute(
                    'INSERT INTO invoice_installments (invoice_id, sequence, amount, due_date) VALUES (?, ?, ?, ?)',
                    [$invoiceId, $row['sequence'], Money::norm($row['amount']), $row['due_date']]
                );
            }
        }
        $this->recalculate($invoiceId);
        $this->audit->log($userId, 'invoice.create', 'invoice', $invoiceId, 'success', null, null, ['policy_id' => $policy['id'], 'base' => $base]);
        return $this->db->queryOne('SELECT * FROM student_invoices WHERE id = ?', [$invoiceId]);
    }

    public function recalculate(int $invoiceId): array
    {
        $invoice = $this->db->queryOne('SELECT * FROM student_invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice) {
            throw HttpException::notFound('الفاتورة غير موجودة.');
        }
        $disc = $this->db->queryOne(
            "SELECT COALESCE(SUM(amount),0) AS t FROM student_discounts WHERE invoice_id = ? AND status = 'approved'",
            [$invoiceId]
        );
        $discountTotal = Money::norm($disc['t'] ?? '0');
        $base = Money::norm($invoice['base_amount']);
        $net = Money::max(Money::sub($base, $discountTotal), '0.00');
        $paid = $this->db->queryOne(
            "SELECT COALESCE(SUM(amount),0) AS t FROM payments
             WHERE invoice_id = ? AND status = 'posted' AND reversal_of IS NULL AND amount > 0",
            [$invoiceId]
        );
        $paidTotal = Money::norm($paid['t'] ?? '0');
        $remaining = Money::sub($net, $paidTotal);
        if (Money::cmp($remaining, '0') < 0) {
            $remaining = '0.00';
        }
        $status = 'unpaid';
        if (Money::cmp($paidTotal, '0') > 0 && Money::cmp($remaining, '0') > 0) {
            $status = 'partial';
        }
        if (Money::cmp($remaining, '0') <= 0 && Money::cmp($net, '0') >= 0 && Money::cmp($paidTotal, $net) >= 0) {
            $status = 'paid';
        }
        $lateDays = (int) ($this->settings->financial()['late_after_days'] ?? 14);
        if ($status !== 'paid') {
            $overdue = $this->db->queryOne(
                'SELECT id FROM invoice_installments WHERE invoice_id = ? AND due_date IS NOT NULL AND due_date < DATE_SUB(CURDATE(), INTERVAL ? DAY) AND status <> "paid" LIMIT 1',
                [$invoiceId, $lateDays]
            );
            if ($overdue) {
                $status = 'overdue';
            }
        }
        $last = $this->db->queryOne(
            "SELECT MAX(created_at) AS t FROM payments WHERE invoice_id = ? AND status = 'posted'",
            [$invoiceId]
        );
        $this->db->execute(
            'UPDATE student_invoices SET discount_total=?, net_due=?, paid_total=?, remaining=?, status=?, last_payment_at=? WHERE id=?',
            [$discountTotal, $net, $paidTotal, $remaining, $status, $last['t'] ?? null, $invoiceId]
        );
        $this->allocateInstallments($invoiceId, $paidTotal);
        return $this->db->queryOne('SELECT * FROM student_invoices WHERE id = ?', [$invoiceId]);
    }

    public function snapshot(int $invoiceId): array
    {
        $invoice = $this->db->queryOne('SELECT * FROM student_invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice) {
            throw HttpException::notFound('الفاتورة غير موجودة.');
        }
        $enroll = $this->db->queryOne(
            'SELECT e.*, s.full_name, s.university_number, d.name AS department_name, st.name AS study_type_name, st.code AS study_type_code,
                    ss.name AS study_stage_name, y.name AS academic_year_name, c.name AS college_name, u.name AS university_name
             FROM student_enrollments e
             JOIN students s ON s.id = e.student_id
             JOIN departments d ON d.id = e.department_id
             JOIN study_types st ON st.id = e.study_type_id
             JOIN study_stages ss ON ss.id = e.study_stage_id
             JOIN academic_years y ON y.id = e.academic_year_id
             JOIN colleges c ON c.id = e.college_id
             JOIN universities u ON u.id = c.university_id
             WHERE e.id = ?',
            [$invoice['enrollment_id']]
        );
        $discounts = $this->db->query(
            "SELECT * FROM student_discounts WHERE invoice_id = ? AND status = 'approved'",
            [$invoiceId]
        );
        $installments = $this->db->query(
            'SELECT * FROM invoice_installments WHERE invoice_id = ? ORDER BY sequence',
            [$invoiceId]
        );
        $payments = $this->db->query(
            "SELECT p.*, r.receipt_number FROM payments p LEFT JOIN payment_receipts r ON r.payment_id = p.id WHERE p.invoice_id = ? ORDER BY p.id",
            [$invoiceId]
        );
        return compact('invoice', 'enroll', 'discounts', 'installments', 'payments');
    }

    private function matchPolicy(array $enroll): ?array
    {
        return $this->db->queryOne(
            'SELECT * FROM tuition_policies
             WHERE is_active = 1 AND academic_year_id = ? AND study_type_id = ?
               AND (department_id IS NULL OR department_id = ?)
               AND (study_stage_id IS NULL OR study_stage_id = ?)
             ORDER BY (department_id IS NULL), (study_stage_id IS NULL)
             LIMIT 1',
            [$enroll['academic_year_id'], $enroll['study_type_id'], $enroll['department_id'], $enroll['study_stage_id']]
        );
    }

    private function allocateInstallments(int $invoiceId, string $paidTotal): void
    {
        $rows = $this->db->query('SELECT * FROM invoice_installments WHERE invoice_id = ? ORDER BY sequence', [$invoiceId]);
        $left = $paidTotal;
        foreach ($rows as $row) {
            $cap = Money::norm($row['amount']);
            $paid = Money::cmp($left, $cap) >= 0 ? $cap : $left;
            $left = Money::sub($left, $paid);
            $st = 'pending';
            if (Money::cmp($paid, '0') > 0 && Money::cmp($paid, $cap) < 0) {
                $st = 'partial';
            }
            if (Money::cmp($paid, $cap) >= 0) {
                $st = 'paid';
            }
            $this->db->execute(
                'UPDATE invoice_installments SET paid_amount=?, status=? WHERE id=?',
                [$paid, $st, $row['id']]
            );
        }
    }
}
