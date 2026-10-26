<?php
use App\Support\Security;
$title = 'تسجيل الدخول — ' . ($identity['college_name'] ?? '');
include __DIR__ . '/partials/head.php';
?>
<body class="auth-body">
  <main class="auth-shell">
    <header class="auth-banner">
      <div class="brand-mark" aria-hidden="true">
        <span class="material-symbols-outlined">account_balance</span>
      </div>
      <div>
        <p class="eyebrow"><?= Security::e($identity['university_name'] ?? '') ?></p>
        <h1><?= Security::e($identity['college_name'] ?? 'نظام الإدارة المالية والإدارية') ?></h1>
        <p class="muted">دخول الموظفين والطلبة المخوّلين. استخدم اسم المستخدم أو الهاتف أو البريد.</p>
      </div>
    </header>
    <section class="auth-card">
      <nav class="auth-tabs" aria-label="التبديل بين الدخول والتسجيل">
        <a class="active" href="/login">دخول مستخدم سابق</a>
        <a href="/register">إنشاء حساب جديد</a>
      </nav>
      <div id="alert" class="alert" hidden></div>
      <form id="login-form" class="form-grid">
        <label>اسم المستخدم أو رقم الهاتف أو البريد الإلكتروني
          <input name="identifier" required autocomplete="username" dir="ltr">
        </label>
        <label>كلمة المرور
          <div class="password-wrap">
            <input id="password" name="password" type="password" required autocomplete="current-password">
            <button type="button" class="icon-btn" data-toggle="password" aria-label="إظهار كلمة المرور"><span class="material-symbols-outlined">visibility</span></button>
          </div>
        </label>
        <button class="btn primary" type="submit">تسجيل الدخول</button>
      </form>
      <p class="help"><a href="/reset">استعادة كلمة المرور</a></p>
      <details class="qr-box">
        <summary>دخول برمز QR للمخولين</summary>
        <form id="qr-form" class="form-grid">
          <label>رمز التحقق (لا تشارك هذا الرمز)
            <input name="token" dir="ltr" autocomplete="off">
          </label>
          <button class="btn secondary" type="submit">تحقق من الرمز</button>
        </form>
      </details>
    </section>
  </main>
  <script src="/assets/js/api.js?v=2"></script>
  <script src="/assets/js/auth.js?v=2"></script>
</body>
</html>
