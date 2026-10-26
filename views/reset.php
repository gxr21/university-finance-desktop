<?php
use App\Support\Security;
$title = 'استعادة كلمة المرور';
include __DIR__ . '/partials/head.php';
?>
<body class="auth-body">
  <main class="auth-shell">
    <section class="auth-card">
      <h1>استعادة كلمة المرور</h1>
      <p class="muted">لن نكشف إن كان الحساب موجوداً. إن وُجد طلب صالح فستُحفظ تعليمات الاستعادة في سجل الاستعادة المحلي للتطوير.</p>
      <div id="alert" class="alert" hidden></div>
      <?php if (empty($token)): ?>
      <form id="forgot-form" class="form-grid">
        <label>اسم المستخدم أو الهاتف أو البريد
          <input name="identifier" required>
        </label>
        <button class="btn primary" type="submit">طلب رابط الاستعادة</button>
      </form>
      <?php else: ?>
      <form id="reset-form" class="form-grid">
        <input type="hidden" name="token" value="<?= Security::e($token) ?>">
        <label>كلمة المرور الجديدة
          <input name="password" type="password" required minlength="8">
        </label>
        <label>التأكيد
          <input name="password_confirmation" type="password" required minlength="8">
        </label>
        <button class="btn primary" type="submit">تعيين كلمة المرور</button>
      </form>
      <?php endif; ?>
      <p class="help"><a href="/login">العودة لتسجيل الدخول</a></p>
    </section>
  </main>
  <script src="/assets/js/api.js"></script>
  <script src="/assets/js/auth.js"></script>
</body>
</html>
