<?php
use App\Support\Money;
use App\Support\Security;
$book = $book ?? [];
$items = $items ?? [];
$approvals = $approvals ?? [];
$title = 'كتاب مصروفات ' . ($book['book_number'] ?? '');
include __DIR__ . '/partials/head.php';
?>
<link rel="stylesheet" href="/assets/css/print.css">
<body class="print-body">
  <div class="print-toolbar no-print">
    <button class="btn primary" type="button" onclick="window.print()">طباعة الكتاب</button>
    <a class="btn" href="/app#/expenses">عودة</a>
  </div>
  <article class="a4-sheet" dir="rtl">
    <header class="doc-head">
      <div>
        <strong><?= Security::e($identity['header_text'] ?? '') ?></strong>
        <div><?= Security::e($identity['university_name'] ?? '') ?></div>
        <div><?= Security::e($identity['college_name'] ?? '') ?></div>
      </div>
      <div class="meta">
        <div class="receipt-no"><?= Security::e($book['book_number'] ?? '') ?></div>
        <div>تاريخ الإنشاء: <?= Security::e($book['created_at'] ?? '') ?></div>
        <div>الحالة: <?= Security::e($book['status'] ?? '') ?></div>
      </div>
    </header>
    <h1><?= Security::e($book['title'] ?? 'كتاب مصروفات') ?></h1>
    <p>الجهة: <strong><?= Security::e($book['beneficiary'] ?? '') ?></strong></p>
    <p><?= Security::e($book['justification'] ?? '') ?></p>
    <table class="doc-table">
      <thead><tr><th>ت</th><th>المادة / الخدمة</th><th>الوصف</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th></tr></thead>
      <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= (int) $item['sequence'] ?></td>
          <td><?= Security::e($item['name']) ?></td>
          <td><?= Security::e($item['description'] ?? '') ?></td>
          <td><?= Security::e($item['quantity']) ?></td>
          <td><?= Security::e(Money::format((string) $item['unit_price'])) ?></td>
          <td><?= Security::e(Money::format((string) $item['line_total'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="5">المجموع</td><td><?= Security::e(Money::format((string) $book['total_amount'])) ?></td></tr></tfoot>
    </table>
    <section class="signs">
      <?php foreach ($approvals as $a): ?>
        <div>
          <span><?= $a['step'] === 'dean' ? 'العميد' : 'المعاون / المراجعة' ?></span>
          <strong><?= Security::e($a['signer_name'] ?: 'بانتظار الاعتماد') ?></strong>
          <small><?= Security::e($a['signed_at'] ?? '') ?></small>
        </div>
      <?php endforeach; ?>
    </section>
  </article>
</body>
</html>
