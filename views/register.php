<?php
use App\Support\Security;
$title = 'إنشاء حساب — ' . ($identity['college_name'] ?? '');
include __DIR__ . '/partials/head.php';
?>
<body class="auth-body">
  <main class="auth-shell">
    <header class="auth-banner">
      <div class="brand-mark"><span class="material-symbols-outlined">person_add</span></div>
      <div>
        <p class="eyebrow"><?= Security::e($identity['university_name'] ?? '') ?></p>
        <h1>طلب عضوية على النظام</h1>
        <p class="muted">الحساب الجديد لا يمنح صلاحيات مالية أو إدارية. التفعيل يتم من الإدارة بعد المراجعة.</p>
      </div>
    </header>
    <section class="auth-card">
      <nav class="auth-tabs">
        <a href="/login">دخول مستخدم سابق</a>
        <a class="active" href="/register">إنشاء حساب جديد</a>
      </nav>
      <div id="alert" class="alert" hidden></div>
      <form id="register-form" class="form-grid two">
        <label>الاسم الكامل *
          <input name="full_name" required minlength="5">
        </label>
        <label>اسم المستخدم *
          <input name="username" required dir="ltr" autocomplete="username">
        </label>
        <label>رقم الهاتف *
          <input name="phone" required dir="ltr" placeholder="07XXXXXXXXX">
        </label>
        <label>البريد الإلكتروني *
          <input name="email" type="email" required dir="ltr">
        </label>
        <label>كلمة المرور *
          <div class="password-wrap">
            <input id="password" name="password" type="password" required minlength="8">
            <button type="button" class="icon-btn" data-toggle="password"><span class="material-symbols-outlined">visibility</span></button>
          </div>
        </label>
        <label>تأكيد كلمة المرور *
          <input name="password_confirmation" type="password" required minlength="8">
        </label>
        <label class="check span-2">
          <input type="checkbox" name="agree" value="1" required>
          أوافق على سياسة استخدام النظام وأتحمل مسؤولية سرية بيانات الدخول.
        </label>
        <button class="btn primary span-2" type="submit">إرسال طلب التسجيل</button>
      </form>
    </section>
  </main>
  <script src="/assets/js/api.js"></script>
  <script src="/assets/js/auth.js"></script>
</body>
</html>
