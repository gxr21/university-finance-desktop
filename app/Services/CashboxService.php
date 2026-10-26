<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Http\HttpException;
use App\Support\Audit;
use App\Support\Money;

final class CashboxService
{
    public function __construct(private Database $db, private Audit $audit)
    {
    }

    public function forStudyType(int $studyTypeId): array
    {
        $box = $this->db->queryOne('SELECT * FROM cashboxes WHERE study_type_id = ?', [$studyTypeId]);
        if (!$box) {
            throw HttpException::unprocessable('لا توجد خزنة مرتبطة بنوع الدراسة.');
        }
        return $box;
    }

    public function balance(int $cashboxId): string
    {
        $row = $this->db->queryOne(
            "SELECT
                COALESCE(SUM(CASE WHEN direction='credit' THEN amount ELSE 0 END),0) AS credits,
                COALESCE(SUM(CASE WHEN direction='debit' THEN amount ELSE 0 END),0) AS debits
             FROM cash_transactions WHERE cashbox_id = ? AND status = 'posted'",
            [$cashboxId]
        );
        return Money::sub($row['credits'] ?? '0', $row['debits'] ?? '0');
    }

    public function summary(int $cashboxId): array
    {
        $box = $this->db->queryOne('SELECT * FROM cashboxes WHERE id = ?', [$cashboxId]);
        $opening = $this->db->queryOne(
            "SELECT COALESCE(SUM(amount),0) AS t FROM cash_transactions WHERE cashbox_id=? AND kind='opening' AND status='posted'",
            [$cashboxId]
        );
        $income = $this->db->queryOne(
            "SELECT COALESCE(SUM(amount),0) AS t FROM cash_transactions WHERE cashbox_id=? AND direction='credit' AND kind IN ('tuition_payment') AND status='posted'",
            [$cashboxId]
        );
        $expense = $this->db->queryOne(
            "SELECT COALESCE(SUM(amount),0) AS t FROM cash_transactions WHERE cashbox_id=? AND direction='debit' AND kind IN ('expense') AND status='posted'",
            [$cashboxId]
        );
        $adjust = $this->db->queryOne(
            "SELECT COALESCE(SUM(CASE WHEN direction='credit' THEN amount ELSE -amount END),0) AS t
             FROM cash_transactions WHERE cashbox_id=? AND kind IN ('adjustment','reversal') AND status='posted'",
            [$cashboxId]
        );
        return [
            'cashbox' => $box,
            'opening' => Money::norm($opening['t'] ?? '0'),
            'income' => Money::norm($income['t'] ?? '0'),
            'expense' => Money::norm($expense['t'] ?? '0'),
            'adjustments' => Money::norm($adjust['t'] ?? '0'),
            'balance' => $this->balance($cashboxId),
        ];
    }

    public function postTuition(array $enroll, string $amount, int $paymentId, string $reference, int $userId): void
    {
        $box = $this->forStudyType((int) $enroll['study_type_id']);
        $this->db->queryOne('SELECT id FROM cashboxes WHERE id = ? FOR UPDATE', [$box['id']]);
        $this->db->execute(
            'INSERT INTO cash_transactions (cashbox_id, direction, amount, kind, status, payment_id, reference, created_by)
             VALUES (?, "credit", ?, "tuition_payment", "posted", ?, ?, ?)',
            [$box['id'], $amount, $paymentId, $reference, $userId]
        );
    }

    public function reverseTuition(int $paymentId, int $enrollmentId, string $amount, int $reversalPaymentId, int $userId, string $reason): void
    {
        $enroll = $this->db->queryOne('SELECT * FROM student_enrollments WHERE id = ?', [$enrollmentId]);
        $box = $this->forStudyType((int) $enroll['study_type_id']);
        $this->db->queryOne('SELECT id FROM cashboxes WHERE id = ? FOR UPDATE', [$box['id']]);
        $this->db->execute(
            'INSERT INTO cash_transactions (cashbox_id, direction, amount, kind, status, payment_id, reference, notes, created_by)
             VALUES (?, "debit", ?, "reversal", "posted", ?, ?, ?, ?)',
            [$box['id'], $amount, $reversalPaymentId, 'REV-' . $paymentId, $reason, $userId]
        );
    }

    public function disburse(int $cashboxId, string $amount, int $expenseBookId, string $reference, int $userId): void
    {
        $this->db->queryOne('SELECT id FROM cashboxes WHERE id = ? FOR UPDATE', [$cashboxId]);
        $balance = $this->balance($cashboxId);
        if (Money::cmp($amount, $balance) > 0) {
            throw HttpException::unprocessable('تعذر إتمام العملية.');
        }
        $this->db->execute(
            'INSERT INTO cash_transactions (cashbox_id, direction, amount, kind, status, expense_book_id, reference, created_by)
             VALUES (?, "debit", ?, "expense", "posted", ?, ?, ?)',
            [$cashboxId, $amount, $expenseBookId, $reference, $userId]
        );
    }

    public function openingOrAdjust(int $cashboxId, string $direction, string $amount, string $kind, string $notes, int $userId): void
    {
        if (!in_array($kind, ['opening', 'adjustment'], true)) {
            throw HttpException::unprocessable('نوع الحركة غير مسموح.');
        }
        if (Money::cmp($amount, '0') <= 0) {
            throw HttpException::unprocessable('المبلغ يجب أن يكون موجباً.');
        }
        $this->db->transaction(function () use ($cashboxId, $direction, $amount, $kind, $notes, $userId) {
            $this->db->queryOne('SELECT id FROM cashboxes WHERE id = ? FOR UPDATE', [$cashboxId]);
            if ($direction === 'debit') {
                $balance = $this->balance($cashboxId);
                if (Money::cmp($amount, $balance) > 0) {
                    throw HttpException::unprocessable('تعذر إتمام العملية.');
                }
            }
            $this->db->execute(
                'INSERT INTO cash_transactions (cashbox_id, direction, amount, kind, status, notes, created_by)
                 VALUES (?, ?, ?, ?, "posted", ?, ?)',
                [$cashboxId, $direction, $amount, $kind, $notes, $userId]
            );
            $this->audit->log($userId, 'cashbox.' . $kind, 'cash_transaction', $this->db->lastId(), 'success', $notes, null, [
                'cashbox_id' => $cashboxId, 'amount' => $amount, 'direction' => $direction,
            ]);
        });
    }

    public function ledger(int $cashboxId, int $page = 1, int $perPage = 30): array
    {
        $offset = ($page - 1) * $perPage;
        $total = (int) ($this->db->queryOne('SELECT COUNT(*) c FROM cash_transactions WHERE cashbox_id=?', [$cashboxId])['c'] ?? 0);
        $rows = $this->db->query(
            'SELECT t.*, u.full_name AS actor_name
             FROM cash_transactions t JOIN users u ON u.id = t.created_by
             WHERE t.cashbox_id = ? ORDER BY t.id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            [$cashboxId]
        );
        return ['data' => $rows, 'total' => $total, 'page' => $page];
    }
}
