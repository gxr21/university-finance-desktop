<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Http\HttpException;
use App\Support\Audit;
use App\Support\Money;

final class DiscountService
{
    public function __construct(
        private Database $db,
        private Audit $audit,
        private InvoiceService $invoices,
        private SettingsService $settings,
    ) {
    }

    public function apply(int $invoiceId, int $ruleId, int $userId, ?string $reason = null, bool $autoApprove = true): array
    {
        $invoice = $this->db->queryOne('SELECT * FROM student_invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice) {
            throw HttpException::notFound('الفاتورة غير موجودة.');
        }
        if (Money::cmp($invoice['paid_total'], '0') > 0) {
            // Historical amounts already collected stay; new discount only affects remaining.
        }
        $rule = $this->db->queryOne(
            'SELECT r.*, t.code AS type_code, t.name AS type_name FROM discount_rules r JOIN discount_types t ON t.id = r.discount_type_id WHERE r.id = ?',
            [$ruleId]
        );
        if (!$rule || !$rule['is_active']) {
            throw HttpException::unprocessable('قاعدة الخصم غير فعالة.');
        }
        if ($rule['valid_from'] && strtotime((string) $rule['valid_from']) > time()) {
            throw HttpException::unprocessable('قاعدة الخصم لم تبدأ صلاحيتها بعد.');
        }
        if ($rule['valid_to'] && strtotime((string) $rule['valid_to']) < strtotime('today')) {
            throw HttpException::unprocessable('انتهت صلاحية قاعدة الخصم.');
        }
        $existing = $this->db->query(
            "SELECT * FROM student_discounts WHERE invoice_id = ? AND status = 'approved'",
            [$invoiceId]
        );
        if ($existing && !(int) $rule['stackable']) {
            throw HttpException::unprocessable('هذه القاعدة لا تُجمع مع خصومات أخرى.');
        }
        foreach ($existing as $ex) {
            $exRule = $ex['discount_rule_id']
                ? $this->db->queryOne('SELECT stackable FROM discount_rules WHERE id = ?', [$ex['discount_rule_id']])
                : null;
            if ($exRule && !(int) $exRule['stackable']) {
                throw HttpException::unprocessable('يوجد خصم معتمد لا يسمح بالجمع.');
            }
        }
        $amount = $rule['calculation'] === 'percent'
            ? Money::percent($invoice['base_amount'], $rule['value'])
            : Money::norm($rule['value']);
        if ($rule['max_amount'] !== null && Money::cmp($amount, (string) $rule['max_amount']) > 0) {
            $amount = Money::norm($rule['max_amount']);
        }
        $fin = $this->settings->financial();
        $already = '0.00';
        foreach ($existing as $ex) {
            $already = Money::add($already, $ex['amount']);
        }
        $projected = Money::add($already, $amount);
        if (isset($fin['max_discount_amount']) && $fin['max_discount_amount'] !== null && Money::cmp($projected, (string) $fin['max_discount_amount']) > 0) {
            throw HttpException::unprocessable('تجاوز الخصم الحد الأعلى المعتمد في الإعدادات.');
        }
        $maxRate = (string) ($fin['max_discount_rate'] ?? 100);
        $maxByRate = Money::percent($invoice['base_amount'], $maxRate);
        if (Money::cmp($projected, $maxByRate) > 0) {
            $amount = Money::max(Money::sub($maxByRate, $already), '0.00');
        }
        $room = Money::sub($invoice['base_amount'], $already);
        if (Money::cmp($amount, $room) > 0) {
            $amount = Money::max($room, '0.00');
        }
        if (Money::cmp($amount, '0') <= 0) {
            throw HttpException::unprocessable('قيمة الخصم الناتجة غير موجبة.');
        }
        $status = $autoApprove ? 'approved' : 'pending';
        $this->db->execute(
            'INSERT INTO student_discounts (invoice_id, discount_rule_id, discount_type_id, title, calculation, rule_value, amount, category_code, reason, status, approved_by, approved_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $invoiceId, $ruleId, $rule['discount_type_id'], $rule['name'], $rule['calculation'], $rule['value'],
                $amount, $rule['category_code'], $reason, $status,
                $autoApprove ? $userId : null,
                $autoApprove ? date('Y-m-d H:i:s') : null,
                $userId,
            ]
        );
        $id = (int) $this->db->lastId();
        if ($autoApprove) {
            $this->invoices->recalculate($invoiceId);
        }
        $this->audit->log($userId, 'discount.apply', 'student_discount', $id, 'success', $reason, null, ['amount' => $amount, 'invoice_id' => $invoiceId]);
        return $this->db->queryOne('SELECT * FROM student_discounts WHERE id = ?', [$id]);
    }

    public function decide(int $id, string $decision, int $userId, ?string $reason = null): void
    {
        $row = $this->db->queryOne('SELECT * FROM student_discounts WHERE id = ?', [$id]);
        if (!$row) {
            throw HttpException::notFound('الخصم غير موجود.');
        }
        if ($row['status'] !== 'pending') {
            throw HttpException::conflict('تم البت في هذا الخصم مسبقاً.');
        }
        $status = $decision === 'approved' ? 'approved' : 'rejected';
        $this->db->execute(
            'UPDATE student_discounts SET status=?, approved_by=?, approved_at=NOW(), reason=COALESCE(?, reason) WHERE id=?',
            [$status, $userId, $reason, $id]
        );
        $this->invoices->recalculate((int) $row['invoice_id']);
        $this->audit->log($userId, 'discount.' . $status, 'student_discount', $id, 'success', $reason);
    }

    public function computePreview(string $base, array $rules): string
    {
        $total = '0.00';
        foreach ($rules as $rule) {
            $part = ($rule['calculation'] ?? '') === 'percent'
                ? Money::percent($base, (string) $rule['value'])
                : Money::norm((string) $rule['value']);
            $total = Money::add($total, $part);
        }
        if (Money::cmp($total, $base) > 0) {
            $total = Money::norm($base);
        }
        return $total;
    }
}
