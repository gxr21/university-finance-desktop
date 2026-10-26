<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Http\HttpException;
use App\Support\Audit;
use App\Support\Money;

final class ExpenseService
{
    public function __construct(
        private Database $db,
        private Audit $audit,
        private CashboxService $cashboxes,
        private SettingsService $settings,
    ) {
    }

    public function create(array $data, int $userId): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $beneficiary = trim((string) ($data['beneficiary'] ?? ''));
        $cashboxId = (int) ($data['cashbox_id'] ?? 0);
        $items = $data['items'] ?? [];
        if ($title === '' || $beneficiary === '' || !$cashboxId || !is_array($items) || count($items) === 0) {
            throw HttpException::unprocessable('أكمل عنوان الكتاب والجهة والبنود ومصدر الصرف.');
        }
        $box = $this->db->queryOne('SELECT id FROM cashboxes WHERE id = ?', [$cashboxId]);
        if (!$box) {
            throw HttpException::unprocessable('مصدر الصرف غير صالح.');
        }
        return $this->db->transaction(function () use ($title, $beneficiary, $cashboxId, $items, $data, $userId) {
            $number = $this->nextNumber();
            $identity = $this->settings->identity();
            $this->db->execute(
                'INSERT INTO expense_books (book_number, title, beneficiary, cashbox_id, total_amount, justification, status, identity_snapshot, created_by)
                 VALUES (?, ?, ?, ?, 0, ?, "draft", ?, ?)',
                [$number, $title, $beneficiary, $cashboxId, $data['justification'] ?? null, json_encode($identity, JSON_UNESCAPED_UNICODE), $userId]
            );
            $id = (int) $this->db->lastId();
            $total = $this->replaceItems($id, $items);
            $this->db->execute('UPDATE expense_books SET total_amount = ? WHERE id = ?', [$total, $id]);
            foreach (['review', 'dean'] as $step) {
                $this->db->execute(
                    'INSERT INTO expense_approvals (expense_book_id, step, decision) VALUES (?, ?, "pending")',
                    [$id, $step]
                );
            }
            $this->audit->log($userId, 'expense.create', 'expense_book', $id, 'success', null, null, ['number' => $number, 'total' => $total]);
            return $this->get($id);
        });
    }

    public function updateDraft(int $id, array $data, int $userId): array
    {
        $book = $this->requireBook($id);
        if ($book['status'] !== 'draft') {
            throw HttpException::conflict('لا يمكن تعديل كتاب معتمد أو مقدّم.');
        }
        if ((int) $book['created_by'] !== $userId) {
            throw HttpException::forbidden();
        }
        $items = $data['items'] ?? [];
        $total = $this->replaceItems($id, $items);
        $this->db->execute(
            'UPDATE expense_books SET title=?, beneficiary=?, cashbox_id=?, justification=?, total_amount=? WHERE id=?',
            [
                $data['title'] ?? $book['title'],
                $data['beneficiary'] ?? $book['beneficiary'],
                $data['cashbox_id'] ?? $book['cashbox_id'],
                $data['justification'] ?? $book['justification'],
                $total,
                $id,
            ]
        );
        return $this->get($id);
    }

    public function submit(int $id, int $userId): array
    {
        $book = $this->requireBook($id);
        if ($book['status'] !== 'draft') {
            throw HttpException::conflict('تعذر تقديم الكتاب في حالته الحالية.');
        }
        $this->db->execute('UPDATE expense_books SET status="submitted", submitted_at=NOW() WHERE id=?', [$id]);
        $this->audit->log($userId, 'expense.submit', 'expense_book', $id, 'success');
        return $this->get($id);
    }

    public function approve(int $id, string $step, int $userId, string $signerName, ?string $comment = null): array
    {
        $book = $this->requireBook($id);
        if (!in_array($book['status'], ['submitted', 'pending_approval'], true)) {
            throw HttpException::conflict('الكتاب ليس في مرحلة الموافقة.');
        }
        $approval = $this->db->queryOne(
            'SELECT * FROM expense_approvals WHERE expense_book_id = ? AND step = ?',
            [$id, $step]
        );
        if (!$approval || $approval['decision'] !== 'pending') {
            throw HttpException::conflict('هذه المرحلة معتمدة مسبقاً.');
        }
        $this->db->execute(
            'UPDATE expense_approvals SET decision="approved", signer_user_id=?, signer_name=?, signed_at=NOW(), comment=? WHERE id=?',
            [$userId, $signerName, $comment, $approval['id']]
        );
        $pending = $this->db->queryOne(
            'SELECT COUNT(*) c FROM expense_approvals WHERE expense_book_id=? AND decision="pending"',
            [$id]
        );
        $status = ((int) $pending['c'] === 0) ? 'approved' : 'pending_approval';
        $this->db->execute('UPDATE expense_books SET status=? WHERE id=?', [$status, $id]);
        $this->audit->log($userId, 'expense.approve', 'expense_book', $id, 'success', $step);
        return $this->get($id);
    }

    public function reject(int $id, int $userId, string $reason): array
    {
        $book = $this->requireBook($id);
        if (in_array($book['status'], ['disbursed', 'cancelled'], true)) {
            throw HttpException::conflict('لا يمكن رفض كتاب تم صرفه أو إلغاؤه.');
        }
        $this->db->execute(
            'UPDATE expense_books SET status="rejected", rejection_reason=? WHERE id=?',
            [$reason, $id]
        );
        $this->audit->log($userId, 'expense.reject', 'expense_book', $id, 'success', $reason);
        return $this->get($id);
    }

    public function disburse(int $id, int $userId): array
    {
        return $this->db->transaction(function () use ($id, $userId) {
            $book = $this->db->queryOne('SELECT * FROM expense_books WHERE id = ? FOR UPDATE', [$id]);
            if (!$book) {
                throw HttpException::notFound('كتاب المصروفات غير موجود.');
            }
            if ($book['status'] === 'disbursed') {
                throw HttpException::conflict('تم صرف هذا الكتاب مسبقاً.');
            }
            if ($book['status'] !== 'approved') {
                throw HttpException::conflict('لا يُصرف الكتاب قبل اكتمال الموافقات.');
            }
            $this->cashboxes->disburse(
                (int) $book['cashbox_id'],
                Money::norm($book['total_amount']),
                $id,
                $book['book_number'],
                $userId
            );
            $this->db->execute('UPDATE expense_books SET status="disbursed", disbursed_at=NOW() WHERE id=?', [$id]);
            $this->audit->log($userId, 'expense.disburse', 'expense_book', $id, 'success', null, null, [
                'amount' => $book['total_amount'], 'cashbox_id' => $book['cashbox_id'],
            ]);
            return $this->get($id);
        });
    }

    public function get(int $id): array
    {
        $book = $this->requireBook($id);
        $items = $this->db->query('SELECT * FROM expense_items WHERE expense_book_id=? ORDER BY sequence', [$id]);
        $approvals = $this->db->query('SELECT * FROM expense_approvals WHERE expense_book_id=?', [$id]);
        return compact('book', 'items', 'approvals');
    }

    public function list(?string $status, ?int $createdBy, bool $canSeeAll): array
    {
        $where = ['1=1'];
        $params = [];
        if ($status) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if (!$canSeeAll) {
            $where[] = 'created_by = ?';
            $params[] = $createdBy;
        }
        return $this->db->query(
            'SELECT b.*, c.name AS cashbox_name, u.full_name AS creator_name
             FROM expense_books b
             JOIN cashboxes c ON c.id = b.cashbox_id
             JOIN users u ON u.id = b.created_by
             WHERE ' . implode(' AND ', $where) . ' ORDER BY b.id DESC',
            $params
        );
    }

    private function requireBook(int $id): array
    {
        $book = $this->db->queryOne('SELECT * FROM expense_books WHERE id=?', [$id]);
        if (!$book) {
            throw HttpException::notFound('كتاب المصروفات غير موجود.');
        }
        return $book;
    }

    private function replaceItems(int $bookId, array $items): string
    {
        $this->db->execute('DELETE FROM expense_items WHERE expense_book_id=?', [$bookId]);
        $total = '0.00';
        $seq = 1;
        foreach ($items as $item) {
            $qty = Money::norm((string) ($item['quantity'] ?? '1'));
            $price = Money::norm((string) ($item['unit_price'] ?? '0'));
            if (Money::cmp($qty, '0') <= 0 || Money::cmp($price, '0') < 0) {
                throw HttpException::unprocessable('كمية وسعر البند غير صالحين.');
            }
            $line = Money::mul($qty, $price);
            $this->db->execute(
                'INSERT INTO expense_items (expense_book_id, sequence, name, description, quantity, unit_price, line_total, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $bookId, $seq++,
                    trim((string) ($item['name'] ?? '')),
                    $item['description'] ?? null,
                    $qty, $price, $line,
                    $item['notes'] ?? null,
                ]
            );
            $total = Money::add($total, $line);
        }
        if (Money::cmp($total, '0') <= 0) {
            throw HttpException::unprocessable('إجمالي الكتاب يجب أن يكون أكبر من صفر.');
        }
        return $total;
    }

    private function nextNumber(): string
    {
        $year = date('Y');
        $row = $this->db->queryOne(
            'SELECT book_number FROM expense_books WHERE book_number LIKE ? ORDER BY id DESC LIMIT 1 FOR UPDATE',
            ["EXP-$year-%"]
        );
        $seq = 1;
        if ($row && preg_match('/EXP-\d+-(\d+)/', $row['book_number'], $m)) {
            $seq = ((int) $m[1]) + 1;
        }
        return sprintf('EXP-%s-%06d', $year, $seq);
    }
}
