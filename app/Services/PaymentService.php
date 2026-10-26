<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Http\HttpException;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Security;

final class PaymentService
{
    public function __construct(
        private Database $db,
        private Audit $audit,
        private InvoiceService $invoices,
        private SettingsService $settings,
        private CashboxService $cashboxes,
    ) {
    }

    public function collect(array $payload, int $userId, string $cashierName, string $ip): array
    {
        $invoiceId = (int) ($payload['invoice_id'] ?? 0);
        $amount = Money::norm((string) ($payload['amount'] ?? '0'));
        $key = trim((string) ($payload['idempotency_key'] ?? ''));
        if ($key === '') {
            throw HttpException::unprocessable('مفتاح منع التكرار مطلوب.');
        }
        $existing = $this->db->queryOne('SELECT * FROM payments WHERE idempotency_key = ?', [$key]);
        if ($existing) {
            $receipt = $this->db->queryOne('SELECT * FROM payment_receipts WHERE payment_id = ?', [$existing['id']]);
            return ['payment' => $existing, 'receipt' => $receipt, 'replayed' => true];
        }
        if (Money::cmp($amount, '0') <= 0) {
            throw HttpException::unprocessable('لا يمكن تسجيل دفعة صفرية أو سالبة.');
        }
        return $this->db->transaction(function () use ($invoiceId, $amount, $key, $payload, $userId, $cashierName, $ip) {
            $invoice = $this->db->queryOne('SELECT * FROM student_invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if (!$invoice) {
                throw HttpException::notFound('الفاتورة غير موجودة.');
            }
            $fresh = $this->invoices->recalculate($invoiceId);
            $allowOver = (bool) ($this->settings->financial()['allow_overpayment'] ?? false);
            if (!$allowOver && Money::cmp($amount, $fresh['remaining']) > 0) {
                throw HttpException::unprocessable('المبلغ يتجاوز الاستحقاق المتبقي.');
            }
            $enroll = $this->db->queryOne('SELECT * FROM student_enrollments WHERE id = ?', [$fresh['enrollment_id']]);
            $this->db->execute(
                'INSERT INTO payments (invoice_id, enrollment_id, amount, method, status, idempotency_key, notes, created_by)
                 VALUES (?, ?, ?, ?, "posted", ?, ?, ?)',
                [
                    $invoiceId,
                    $fresh['enrollment_id'],
                    $amount,
                    $payload['method'] ?? 'cash',
                    $key,
                    $payload['notes'] ?? null,
                    $userId,
                ]
            );
            $paymentId = (int) $this->db->lastId();
            $paidBefore = Money::norm($fresh['paid_total']);
            $updated = $this->invoices->recalculate($invoiceId);
            $identity = $this->settings->identity();
            $verify = Security::randomToken(16);
            $receiptNumber = $this->nextReceiptNumber();
            $this->db->execute(
                'INSERT INTO payment_receipts
                    (payment_id, invoice_id, receipt_number, verify_token_hash, issued_at, base_amount, discount_total, net_due, paid_now, paid_before, remaining_after, cashier_name, identity_snapshot, signature_path, seal_path, status)
                 VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "issued")',
                [
                    $paymentId, $invoiceId, $receiptNumber, Security::hashToken($verify),
                    $updated['base_amount'], $updated['discount_total'], $updated['net_due'],
                    $amount, $paidBefore, $updated['remaining'], $cashierName,
                    json_encode($identity, JSON_UNESCAPED_UNICODE),
                    $identity['signature_path'] ?? null,
                    $identity['seal_path'] ?? null,
                ]
            );
            $receiptId = (int) $this->db->lastId();
            $this->db->execute(
                'INSERT INTO receipt_signatures (receipt_id, signer_user_id, kind, image_path, signed_at) VALUES (?, ?, "cashier", ?, NOW())',
                [$receiptId, $userId, $identity['signature_path'] ?? null]
            );
            if (!empty($identity['seal_path'])) {
                $this->db->execute(
                    'INSERT INTO receipt_signatures (receipt_id, signer_user_id, kind, image_path, signed_at) VALUES (?, ?, "seal", ?, NOW())',
                    [$receiptId, $userId, $identity['seal_path']]
                );
            }
            $this->cashboxes->postTuition($enroll, $amount, $paymentId, $receiptNumber, $userId);
            $this->notifyStudent((int) $enroll['student_id'], $updated, $amount, $receiptNumber);
            $this->audit->log($userId, 'payment.create', 'payment', $paymentId, 'success', null, null, [
                'amount' => $amount, 'receipt' => $receiptNumber, 'invoice_id' => $invoiceId,
            ], $ip);
            $this->audit->log($userId, 'receipt.issue', 'receipt', $receiptId, 'success', null, null, ['receipt_number' => $receiptNumber], $ip);
            $payment = $this->db->queryOne('SELECT * FROM payments WHERE id = ?', [$paymentId]);
            $receipt = $this->db->queryOne('SELECT * FROM payment_receipts WHERE id = ?', [$receiptId]);
            $receipt['verify_token'] = $verify;
            return ['payment' => $payment, 'receipt' => $receipt, 'invoice' => $updated, 'replayed' => false];
        });
    }

    public function reverse(int $paymentId, int $userId, string $reason, string $ip): array
    {
        if (trim($reason) === '') {
            throw HttpException::unprocessable('سبب التصحيح مطلوب.');
        }
        return $this->db->transaction(function () use ($paymentId, $userId, $reason, $ip) {
            $payment = $this->db->queryOne('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
            if (!$payment) {
                throw HttpException::notFound('الدفعة غير موجودة.');
            }
            if ($payment['status'] !== 'posted') {
                throw HttpException::conflict('هذه الدفعة ملغاة مسبقاً.');
            }
            $this->db->execute('UPDATE payments SET status = "reversed", reversed_reason = ? WHERE id = ?', [$reason, $paymentId]);
            $this->db->execute(
                'INSERT INTO payments (invoice_id, enrollment_id, amount, method, status, idempotency_key, notes, reversal_of, reversed_reason, created_by)
                 VALUES (?, ?, ?, ?, "posted", ?, ?, ?, ?, ?)',
                [
                    $payment['invoice_id'], $payment['enrollment_id'], Money::norm($payment['amount']),
                    $payment['method'], 'rev-' . $payment['idempotency_key'] . '-' . time(), 'قيد عكسي موثّق', $paymentId, $reason, $userId,
                ]
            );
            $revId = (int) $this->db->lastId();
            $this->db->execute('UPDATE payment_receipts SET status = "void" WHERE payment_id = ?', [$paymentId]);
            $this->cashboxes->reverseTuition((int) $paymentId, (int) $payment['enrollment_id'], Money::norm($payment['amount']), $revId, $userId, $reason);
            $updated = $this->invoices->recalculate((int) $payment['invoice_id']);
            $this->audit->log($userId, 'payment.reverse', 'payment', $paymentId, 'success', $reason, $payment, ['reversal_id' => $revId], $ip);
            return ['reversal_id' => $revId, 'invoice' => $updated];
        });
    }

    public function recordPrint(int $receiptId, int $userId, bool $confirmed = false): void
    {
        $this->db->execute(
            'INSERT INTO receipt_prints (receipt_id, requested_by, confirmed) VALUES (?, ?, ?)',
            [$receiptId, $userId, $confirmed ? 1 : 0]
        );
        $this->audit->log($userId, 'receipt.reprint', 'receipt', $receiptId, 'success', $confirmed ? 'confirmed' : 'requested');
    }

    public function publicVerify(string $token): ?array
    {
        $row = $this->db->queryOne(
            'SELECT r.receipt_number, r.issued_at, r.status, s.full_name, s.university_number
             FROM payment_receipts r
             JOIN student_invoices i ON i.id = r.invoice_id
             JOIN student_enrollments e ON e.id = i.enrollment_id
             JOIN students s ON s.id = e.student_id
             WHERE r.verify_token_hash = ?',
            [Security::hashToken($token)]
        );
        return $row;
    }

    private function nextReceiptNumber(): string
    {
        $year = date('Y');
        $row = $this->db->queryOne(
            'SELECT receipt_number FROM payment_receipts WHERE receipt_number LIKE ? ORDER BY id DESC LIMIT 1 FOR UPDATE',
            ["REC-$year-%"]
        );
        $seq = 1;
        if ($row && preg_match('/REC-\d+-(\d+)/', $row['receipt_number'], $m)) {
            $seq = ((int) $m[1]) + 1;
        }
        return sprintf('REC-%s-%06d', $year, $seq);
    }

    private function notifyStudent(int $studentId, array $invoice, string $amount, string $receipt): void
    {
        $user = $this->db->queryOne('SELECT id FROM users WHERE student_id = ?', [$studentId]);
        if (!$user) {
            return;
        }
        $this->db->execute(
            'INSERT INTO notifications (user_id, title, body, ref_type, ref_id) VALUES (?, ?, ?, "receipt", ?)',
            [
                $user['id'],
                'تم تسجيل دفعة دراسية',
                "تم استلام {$amount} وإصدار الوصل {$receipt}. المتبقي: {$invoice['remaining']}.",
                $invoice['id'],
            ]
        );
    }
}
