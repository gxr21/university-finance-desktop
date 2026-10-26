<?php
use App\Support\Security;
$title = 'التحقق من وصل رسمي';
include __DIR__ . '/partials/head.php';
?>
<body class="auth-body">
  <main class="auth-shell">
    <section class="auth-card">
      <h1>التحقق من صحة الوصل</h1>
      <?php if (empty($row)): ?>
        <div class="alert error">رمز التحقق غير صالح أو أن الوصل غير موجود.</div>
      <?php else: ?>
        <div class="alert success">هذا وصل رسمي صادر عن المؤسسة.</div>
        <ul class="plain">
          <li>رقم الوصل: <strong><?= Security::e($row['receipt_number']) ?></strong></li>
          <li>تاريخ الإصدار: <?= Security::e($row['issued_at']) ?></li>
          <li>الطالب: <?= Security::e($row['full_name']) ?></li>
          <li>الرقم الجامعي: <?= Security::e($row['university_number']) ?></li>
          <li>الحالة: <?= Security::e($row['status'] === 'issued' ? 'صادر' : 'ملغى') ?></li>
        </ul>
        <p class="muted">لا تعرض هذه الصفحة تفاصيل الذمة المتبقية أو بيانات مالية تفصيلية.</p>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
