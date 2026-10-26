<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Support\Money;

final class ReportService
{
    public function __construct(private Database $db)
    {
    }

    public function dashboard(array $permissions, ?int $studentId): array
    {
        $canFinance = in_array('reports.financial', $permissions, true) || in_array('cashbox.view_balance', $permissions, true);
        $canStudents = in_array('students.view', $permissions, true) || in_array('students.manage', $permissions, true);
        $out = ['permissions' => $permissions];
        if ($studentId) {
            $fileBits = $this->db->queryOne(
                'SELECT i.net_due, i.paid_total, i.remaining, i.status FROM student_invoices i
                 JOIN student_enrollments e ON e.id=i.enrollment_id WHERE e.student_id=? ORDER BY i.id DESC LIMIT 1',
                [$studentId]
            );
            $out['me'] = $fileBits;
            $out['notifications'] = $this->db->query(
                'SELECT * FROM notifications n JOIN users u ON u.id=n.user_id WHERE u.student_id=? ORDER BY n.id DESC LIMIT 10',
                [$studentId]
            );
            return $out;
        }
        if ($canStudents) {
            $out['students_count'] = (int) ($this->db->queryOne('SELECT COUNT(*) c FROM students')['c'] ?? 0);
        }
        if ($canFinance) {
            $agg = $this->db->queryOne(
                'SELECT COALESCE(SUM(net_due),0) due_sum, COALESCE(SUM(paid_total),0) paid_sum, COALESCE(SUM(remaining),0) rem_sum FROM student_invoices'
            );
            $out['due_total'] = $agg['due_sum'];
            $out['paid_total'] = $agg['paid_sum'];
            $out['remaining_total'] = $agg['rem_sum'];
            $today = $this->db->queryOne(
                "SELECT COALESCE(SUM(amount),0) t FROM payments WHERE status='posted' AND DATE(created_at)=CURDATE() AND reversal_of IS NULL AND amount > 0"
            );
            $out['today_income'] = $today['t'];
            $out['by_study_type'] = $this->db->query(
                "SELECT st.name, COALESCE(SUM(p.amount),0) total
                 FROM payments p
                 JOIN student_enrollments e ON e.id=p.enrollment_id
                 JOIN study_types st ON st.id=e.study_type_id
                 WHERE p.status='posted' AND p.reversal_of IS NULL AND p.amount > 0
                 GROUP BY st.id, st.name"
            );
            $out['cashboxes'] = [];
            foreach ($this->db->query('SELECT * FROM cashboxes') as $box) {
                $credits = $this->db->queryOne("SELECT COALESCE(SUM(amount),0) t FROM cash_transactions WHERE cashbox_id=? AND direction='credit' AND status='posted'", [$box['id']]);
                $debits = $this->db->queryOne("SELECT COALESCE(SUM(amount),0) t FROM cash_transactions WHERE cashbox_id=? AND direction='debit' AND status='posted'", [$box['id']]);
                $out['cashboxes'][] = [
                    'id' => $box['id'],
                    'name' => $box['name'],
                    'code' => $box['code'],
                    'balance' => Money::sub($credits['t'], $debits['t']),
                ];
            }
        }
        if (in_array('expenses.view', $permissions, true) || in_array('expenses.create', $permissions, true)) {
            $exp = $this->db->queryOne("SELECT COUNT(*) c, COALESCE(SUM(total_amount),0) t FROM expense_books WHERE status IN ('approved','disbursed','submitted','pending_approval','draft')");
            $out['expenses_summary'] = ['count' => $exp['c'], 'amount' => in_array('cashbox.view_balance', $permissions, true) || in_array('reports.financial', $permissions, true) ? $exp['t'] : null];
        }
        $out['recent'] = $this->db->query(
            'SELECT action, entity_type, entity_id, result, created_at FROM audit_logs ORDER BY id DESC LIMIT 8'
        );
        return $out;
    }

    public function financial(array $filters): array
    {
        $year = $filters['academic_year_id'] ?? null;
        $params = [];
        $yearSql = '';
        if ($year) {
            $yearSql = ' AND e.academic_year_id = ?';
            $params[] = $year;
        }
        $invoices = $this->db->query(
            "SELECT st.name AS study_type, COUNT(i.id) invoices, COALESCE(SUM(i.net_due),0) due_sum, COALESCE(SUM(i.paid_total),0) paid_sum, COALESCE(SUM(i.remaining),0) rem_sum
             FROM student_invoices i JOIN student_enrollments e ON e.id=i.enrollment_id JOIN study_types st ON st.id=e.study_type_id
             WHERE 1=1 $yearSql GROUP BY st.id, st.name",
            $params
        );
        $discounts = $this->db->query(
            "SELECT dt.name, COUNT(sd.id) c, COALESCE(SUM(sd.amount),0) t
             FROM student_discounts sd JOIN discount_types dt ON dt.id=sd.discount_type_id
             WHERE sd.status='approved' GROUP BY dt.id, dt.name"
        );
        return compact('invoices', 'discounts');
    }
}
