<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Http\HttpException;
use App\Support\Audit;
use App\Support\Money;

final class StudentService
{
    public function __construct(
        private Database $db,
        private Audit $audit,
        private InvoiceService $invoices,
    ) {
    }

    public function list(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(s.full_name LIKE ? OR s.university_number LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
        }
        foreach (['department_id' => 'e.department_id', 'study_type_id' => 'e.study_type_id', 'study_stage_id' => 'e.study_stage_id', 'academic_year_id' => 'e.academic_year_id'] as $key => $col) {
            if (!empty($filters[$key])) {
                $where[] = "$col = ?";
                $params[] = $filters[$key];
            }
        }
        if (!empty($filters['payment_status'])) {
            $where[] = 'i.status = ?';
            $params[] = $filters['payment_status'];
        }
        $sqlWhere = implode(' AND ', $where);
        $total = (int) ($this->db->queryOne(
            "SELECT COUNT(DISTINCT s.id) AS c
             FROM students s
             LEFT JOIN student_enrollments e ON e.student_id = s.id AND e.status = 'active'
             LEFT JOIN student_invoices i ON i.enrollment_id = e.id
             WHERE $sqlWhere",
            $params
        )['c'] ?? 0);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $sort = in_array($filters['sort'] ?? '', ['name', 'remaining', 'paid', 'number'], true) ? $filters['sort'] : 'name';
        $dir = strtolower($filters['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        $order = match ($sort) {
            'remaining' => "i.remaining $dir",
            'paid' => "i.paid_total $dir",
            'number' => "s.university_number $dir",
            default => "s.full_name $dir",
        };
        $rows = $this->db->query(
            "SELECT s.id, s.university_number, s.full_name, s.status AS student_status,
                    e.id AS enrollment_id, d.name AS department_name, st.name AS study_type_name, st.code AS study_type_code,
                    ss.name AS study_stage_name, y.name AS academic_year_name,
                    i.id AS invoice_id, i.base_amount, i.discount_total, i.net_due, i.paid_total, i.remaining, i.status AS payment_status, i.last_payment_at
             FROM students s
             LEFT JOIN student_enrollments e ON e.student_id = s.id AND e.status = 'active'
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN study_types st ON st.id = e.study_type_id
             LEFT JOIN study_stages ss ON ss.id = e.study_stage_id
             LEFT JOIN academic_years y ON y.id = e.academic_year_id
             LEFT JOIN student_invoices i ON i.enrollment_id = e.id
             WHERE $sqlWhere
             ORDER BY $order
             LIMIT $perPage OFFSET $offset",
            $params
        );
        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function create(array $data, int $userId): array
    {
        $number = trim((string) ($data['university_number'] ?? ''));
        $name = trim((string) ($data['full_name'] ?? ''));
        if ($number === '' || mb_strlen($name) < 5) {
            throw HttpException::unprocessable('الاسم والرقم الجامعي مطلوبان.');
        }
        if ($this->db->queryOne('SELECT id FROM students WHERE university_number = ?', [$number])) {
            throw HttpException::unprocessable('الرقم الجامعي مستخدم مسبقاً.', ['university_number' => 'الرقم الجامعي مستخدم مسبقاً.']);
        }
        return $this->db->transaction(function () use ($data, $number, $name, $userId) {
            $this->db->execute(
                'INSERT INTO students (university_number, full_name, national_id, phone, email, gender, birth_date, address, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $number, $name,
                    $data['national_id'] ?? null,
                    $data['phone'] ?? null,
                    $data['email'] ?? null,
                    $data['gender'] ?? 'unspecified',
                    $data['birth_date'] ?? null,
                    $data['address'] ?? null,
                    $data['notes'] ?? null,
                    $userId,
                ]
            );
            $studentId = (int) $this->db->lastId();
            $enrollmentId = $this->enroll($studentId, $data, $userId);
            $invoice = $this->invoices->issueForEnrollment($enrollmentId, $userId);
            $this->audit->log($userId, 'student.create', 'student', $studentId, 'success', null, null, ['university_number' => $number]);
            return ['id' => $studentId, 'enrollment_id' => $enrollmentId, 'invoice' => $invoice];
        });
    }

    public function update(int $id, array $data, int $userId): void
    {
        $student = $this->db->queryOne('SELECT * FROM students WHERE id = ?', [$id]);
        if (!$student) {
            throw HttpException::notFound('الطالب غير موجود.');
        }
        $this->db->execute(
            'UPDATE students SET full_name=?, national_id=?, phone=?, email=?, gender=?, birth_date=?, address=?, notes=?, status=?, updated_by=? WHERE id=?',
            [
                trim((string) ($data['full_name'] ?? $student['full_name'])),
                $data['national_id'] ?? $student['national_id'],
                $data['phone'] ?? $student['phone'],
                $data['email'] ?? $student['email'],
                $data['gender'] ?? $student['gender'],
                $data['birth_date'] ?: $student['birth_date'],
                $data['address'] ?? $student['address'],
                $data['notes'] ?? $student['notes'],
                $data['status'] ?? $student['status'],
                $userId,
                $id,
            ]
        );
        $this->audit->log($userId, 'student.update', 'student', $id, 'success', null, $student, $data);
    }

    public function file(int $id): array
    {
        $student = $this->db->queryOne('SELECT * FROM students WHERE id = ?', [$id]);
        if (!$student) {
            throw HttpException::notFound('الطالب غير موجود.');
        }
        $enrollments = $this->db->query(
            'SELECT e.*, d.name AS department_name, st.name AS study_type_name, ss.name AS study_stage_name, y.name AS academic_year_name, c.name AS college_name, u.name AS university_name
             FROM student_enrollments e
             JOIN departments d ON d.id = e.department_id
             JOIN study_types st ON st.id = e.study_type_id
             JOIN study_stages ss ON ss.id = e.study_stage_id
             JOIN academic_years y ON y.id = e.academic_year_id
             JOIN colleges c ON c.id = e.college_id
             JOIN universities u ON u.id = c.university_id
             WHERE e.student_id = ? ORDER BY e.id DESC',
            [$id]
        );
        $invoices = $this->db->query(
            'SELECT i.* FROM student_invoices i JOIN student_enrollments e ON e.id = i.enrollment_id WHERE e.student_id = ? ORDER BY i.id DESC',
            [$id]
        );
        $invoiceIds = array_column($invoices, 'id') ?: [0];
        $ph = implode(',', array_fill(0, count($invoiceIds), '?'));
        $discounts = $this->db->query("SELECT * FROM student_discounts WHERE invoice_id IN ($ph)", $invoiceIds);
        $payments = $this->db->query(
            'SELECT p.*, r.receipt_number, r.id AS receipt_id FROM payments p
             LEFT JOIN payment_receipts r ON r.payment_id = p.id
             JOIN student_invoices i ON i.id = p.invoice_id
             JOIN student_enrollments e ON e.id = i.enrollment_id
             WHERE e.student_id = ? ORDER BY p.id DESC',
            [$id]
        );
        $receipts = $this->db->query(
            'SELECT r.* FROM payment_receipts r
             JOIN student_invoices i ON i.id = r.invoice_id
             JOIN student_enrollments e ON e.id = i.enrollment_id
             WHERE e.student_id = ? ORDER BY r.id DESC',
            [$id]
        );
        $audit = $this->db->query(
            "SELECT * FROM audit_logs WHERE entity_type IN ('student','invoice','payment','receipt','student_discount') AND entity_id = ?
             ORDER BY id DESC LIMIT 50",
            [(string) $id]
        );
        $docs = $this->db->query('SELECT * FROM student_documents WHERE student_id = ? ORDER BY id DESC', [$id]);
        return compact('student', 'enrollments', 'invoices', 'discounts', 'payments', 'receipts', 'audit', 'docs');
    }

    public function enroll(int $studentId, array $data, int $userId): int
    {
        $year = (int) ($data['academic_year_id'] ?? 0);
        $dept = (int) ($data['department_id'] ?? 0);
        $type = (int) ($data['study_type_id'] ?? 0);
        $stage = (int) ($data['study_stage_id'] ?? 0);
        $college = (int) ($data['college_id'] ?? 0);
        if (!$year || !$dept || !$type || !$stage) {
            throw HttpException::unprocessable('بيانات القيد الدراسي غير مكتملة.');
        }
        if (!$college) {
            $college = (int) ($this->db->queryOne('SELECT college_id FROM departments WHERE id = ?', [$dept])['college_id'] ?? 0);
        }
        $exists = $this->db->queryOne(
            'SELECT id FROM student_enrollments WHERE student_id = ? AND academic_year_id = ?',
            [$studentId, $year]
        );
        if ($exists) {
            return (int) $exists['id'];
        }
        $this->db->execute(
            'INSERT INTO student_enrollments (student_id, academic_year_id, college_id, department_id, study_type_id, study_stage_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$studentId, $year, $college, $dept, $type, $stage, $userId]
        );
        return (int) $this->db->lastId();
    }

    public function search(string $q): array
    {
        $like = '%' . $q . '%';
        return $this->db->query(
            "SELECT s.id, s.university_number, s.full_name, d.name AS department_name, st.name AS study_type_name, ss.name AS study_stage_name, i.id AS invoice_id, i.remaining, i.net_due, i.paid_total, i.status
             FROM students s
             LEFT JOIN student_enrollments e ON e.student_id = s.id AND e.status = 'active'
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN study_types st ON st.id = e.study_type_id
             LEFT JOIN study_stages ss ON ss.id = e.study_stage_id
             LEFT JOIN student_invoices i ON i.enrollment_id = e.id
             WHERE s.full_name LIKE ? OR s.university_number LIKE ?
             ORDER BY s.full_name ASC LIMIT 20",
            [$like, $like]
        );
    }
}
