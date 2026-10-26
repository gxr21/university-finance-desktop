<?php
use App\Support\Security;
$title = ($identity['college_name'] ?? 'النظام') . ' — لوحة التحكم';
include __DIR__ . '/partials/head.php';
?>
<body class="app-body" data-admin="<?= !empty($adminOnly) ? '1' : '0' ?>" data-csrf="<?= Security::e($csrf ?? '') ?>">
  <div id="boot-screen" class="boot-screen">
    <div class="spinner"></div>
    <p>جاري التحقق من الجلسة والصلاحيات…</p>
  </div>
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-mark sm"><span class="material-symbols-outlined">account_balance</span></div>
      <div>
        <strong id="college-label">الكلية</strong>
        <small id="university-label">الجامعة</small>
      </div>
    </div>
    <nav id="nav" class="side-nav"></nav>
    <div class="sidebar-foot">
      <span>وزارة التعليم العالي والبحث العلمي</span>
    </div>
  </aside>
  <div class="workspace">
    <header class="topbar">
      <button class="icon-btn menu-btn" id="menu-toggle" type="button" aria-label="القائمة"><span class="material-symbols-outlined">menu</span></button>
      <div class="top-meta">
        <span id="year-chip" class="chip"></span>
        <span id="role-chip" class="chip navy"></span>
      </div>
      <div class="top-actions">
        <button class="icon-btn" id="notif-btn" type="button" aria-label="الإشعارات">
          <span class="material-symbols-outlined">notifications</span>
          <span class="badge" id="notif-count" hidden>0</span>
        </button>
        <div class="who">
          <strong id="user-name"></strong>
          <small id="user-role"></small>
        </div>
        <button class="icon-btn" id="logout-btn" type="button" title="تسجيل الخروج"><span class="material-symbols-outlined">logout</span></button>
      </div>
    </header>
    <div id="notif-panel" class="notif-panel" hidden></div>
    <main id="main" class="main">
      <div class="skeleton-page"></div>
    </main>
  </div>
  <div id="modal" class="modal" hidden></div>
  <div id="toast" class="toast" hidden></div>
  <script src="/assets/js/api.js"></script>
  <script src="/assets/js/ui.js"></script>
  <script src="/assets/js/app.js"></script>
</body>
</html>
