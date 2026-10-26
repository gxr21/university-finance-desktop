<?php
use App\Support\Money;
use App\Support\Security;
$identity = $identity ?? [];
$receipt = $receipt ?? [];
$enroll = $snap['enroll'] ?? [];
$discounts = $snap['discounts'] ?? [];
$title = 'وصل ' . ($receipt['receipt_number'] ?? '');
include __DIR__ . '/partials/head.php';
?>
<link rel="stylesheet" href="/assets/css/print.css">
<body class="print-body">
  <div class="print-toolbar no-print">
    <button class="btn primary" type="button" onclick="window.print()">طباعة A5</button>
    <button class="btn" type="button" id="confirm-print">تأكيد خروج الورقة</button>
    <a class="btn" href="/app#/receipts">عودة</a>
  </div>
  <article class="a5-sheet" dir="rtl">
    <header class="doc-head">
      <div>
        <strong><?= Security::e($identity['header_text'] ?? '') ?></strong>
        <div><?= Security::e($identity['university_name'] ?? '') ?></div>
        <div><?= Security::e($identity['college_name'] ?? '') ?></div>
        <div><?= Security::e($enroll['department_name'] ?? $identity['department_name'] ?? '') ?></div>
      </div>
      <div class="seals">
        <?php if (!empty($identity['university_logo'])): ?><img src="/files/<?= (int) $identity['university_logo'] ?>" alt="شعار الجامعة" class="logo"><?php endif; ?>
        <?php if (!empty($identity['college_logo'])): ?><img src="/files/<?= (int) $identity['college_logo'] ?>" alt="شعار الكلية" class="logo"><?php endif; ?>
      </div>
      <div class="meta">
        <div class="receipt-no"><?= Security::e($receipt['receipt_number'] ?? '') ?></div>
        <div><?= Security::e($receipt['issued_at'] ?? '') ?></div>
      </div>
    </header>
    <h1><?= Security::e($identity['document_title'] ?? 'وصل استلام أجور دراسية') ?></h1>
    <section class="grid4">
      <div><span>اسم الطالب</span><strong><?= Security::e($enroll['full_name'] ?? '') ?></strong></div>
      <div><span>الرقم الجامعي</span><strong><?= Security::e($enroll['university_number'] ?? '') ?></strong></div>
      <div><span>نوع الدراسة</span><strong><?= Security::e($enroll['study_type_name'] ?? '') ?></strong></div>
      <div><span>المرحلة</span><strong><?= Security::e($enroll['study_stage_name'] ?? '') ?></strong></div>
    </section>
    <table class="doc-table">
      <thead><tr><th>البيان</th><th>المبلغ (<?= Security::e($identity['currency_label'] ?? 'د.ع') ?>)</th></tr></thead>
      <tbody>
        <tr><td>القسط الأساسي</td><td><?= Security::e(Money::format((string) $receipt['base_amount'])) ?></td></tr>
        <tr><td>الخصومات المعتمدة<?= $discounts ? ' — ' . Security::e(implode('، ', array_column($discounts, 'title'))) : '' ?></td><td><?= Security::e(Money::format((string) $receipt['discount_total'])) ?></td></tr>
        <tr><td>إجمالي المستحق</td><td><?= Security::e(Money::format((string) $receipt['net_due'])) ?></td></tr>
        <tr><td>المسدد سابقاً قبل هذا الوصل</td><td><?= Security::e(Money::format((string) $receipt['paid_before'])) ?></td></tr>
        <tr class="hl"><td>المبلغ المستلم في هذه العملية</td><td><?= Security::e(Money::format((string) $receipt['paid_now'])) ?></td></tr>
        <tr><td>المتبقي بعد هذا الوصل</td><td><?= Security::e(Money::format((string) $receipt['remaining_after'])) ?></td></tr>
      </tbody>
    </table>
    <p class="words">المبلغ كتابةً: <?= Security::e(Money::toArabicWords((string) $receipt['paid_now'])) ?></p>
    <footer class="doc-foot">
      <div>
        <span>أمين الصندوق</span>
        <strong><?= Security::e($receipt['cashier_name'] ?? '') ?></strong>
        <?php if (!empty($receipt['signature_path'])): ?><img class="sign" src="/files/<?= (int) $receipt['signature_path'] ?>" alt="توقيع"><?php endif; ?>
      </div>
      <div class="seal">
        <?php if (!empty($receipt['seal_path'])): ?><img src="/files/<?= (int) $receipt['seal_path'] ?>" alt="ختم"><?php else: ?><div class="seal-placeholder">الختم الإلكتروني</div><?php endif; ?>
      </div>
      <div class="qr"><?php if (!empty($qr)): ?><img src="<?= $qr ?>" alt="رمز التحقق"><?php endif; ?></div>
    </footer>
    <p class="fine"><?= Security::e($identity['footer_text'] ?? '') ?></p>
  </article>
  <script>
    document.getElementById('confirm-print')?.addEventListener('click', async () => {
      const id = <?= (int) ($receipt['id'] ?? 0) ?>;
      await Ufam.api('/api/receipts/' + id + '/print', { method: 'POST', body: { confirmed: true } });
      alert('سُجّل تأكيد الطباعة. نجاح نافذة الطباعة لا يعني بالضرورة خروج الورقة.');
    });
  </script>
  <script src="/assets/js/api.js"></script>
</body>
</html>
