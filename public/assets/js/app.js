(function () {
  const U = window.Ufam;
  const main = document.getElementById('main');
  const nav = document.getElementById('nav');

  const modules = [
    { id: 'dashboard', href: '#/dashboard', icon: 'space_dashboard', label: 'لوحة التحكم', any: [] },
    { id: 'tuition', href: '#/tuition', icon: 'payments', label: 'الأجور الدراسية', any: ['tuition.manage', 'students.financial.view'] },
    { id: 'discounts', href: '#/discounts', icon: 'price_check', label: 'الخصومات والإعفاءات', any: ['discounts.manage'] },
    { id: 'cashier', href: '#/cashier', icon: 'point_of_sale', label: 'الصندوق والوصولات', any: ['payments.create'] },
    { id: 'receipts', href: '#/receipts', icon: 'receipt_long', label: 'الأرشيف والوصولات', any: ['receipts.view', 'students.view'] },
    { id: 'cashboxes', href: '#/cashboxes', icon: 'savings', label: 'الخزائن', any: ['cashbox.view_balance'] },
    { id: 'expenses', href: '#/expenses', icon: 'shopping_cart', label: 'المصروفات والمشتريات', any: ['expenses.create', 'expenses.view'] },
    { id: 'students', href: '#/students', icon: 'groups', label: 'شؤون الطلبة', any: ['students.view', 'students.manage'] },
    { id: 'teachers', href: '#/teachers', icon: 'school', label: 'أجور التدريسيين', any: ['teachers.view', 'teachers.manage'] },
    { id: 'timetable', href: '#/timetable', icon: 'calendar_month', label: 'جدول الدوام', any: ['timetable.view', 'timetable.manage'] },
    { id: 'reports', href: '#/reports', icon: 'monitoring', label: 'التقارير', any: ['reports.financial', 'reports.admin'] },
    { id: 'users', href: '#/users', icon: 'manage_accounts', label: 'المستخدمون', any: ['users.view', 'users.manage'] },
    { id: 'academic', href: '#/academic', icon: 'account_tree', label: 'الهيكل الأكاديمي', any: ['academic.manage'] },
    { id: 'identity', href: '#/identity', icon: 'badge', label: 'الهوية والإعدادات', any: ['identity.manage', 'settings.manage'] },
    { id: 'qr', href: '#/qr', icon: 'qr_code_2', label: 'رموز QR', any: ['qr.manage'] },
    { id: 'audit', href: '#/audit', icon: 'policy', label: 'سجل التدقيق', any: ['audit.view'] },
    { id: 'backup', href: '#/backup', icon: 'cloud_upload', label: 'النسخ الاحتياطي', any: ['backup.manage'] },
    { id: 'me', href: '#/me', icon: 'person', label: 'حسابي', any: [] },
  ];

  function allowed(mod) {
    if (!mod.any.length) return true;
    if (mod.id === 'me') return !!U.me.student_id;
    return mod.any.some(U.can);
  }

  function renderNav(active) {
    nav.innerHTML = modules.filter(allowed).map((m) =>
      '<a href="' + m.href + '" class="' + (m.id === active ? 'active' : '') + '"><span class="material-symbols-outlined">' + m.icon + '</span>' + m.label + '</a>'
    ).join('');
  }

  async function boot() {
    try {
      const data = await U.api('/api/auth/me');
      U.me = data.user;
      U.identity = data.identity;
      U.catalog = data.catalog;
      if (data.csrf) { U.csrf = data.csrf; sessionStorage.setItem('ufam_csrf', data.csrf); }
      document.getElementById('boot-screen').hidden = true;
      document.getElementById('college-label').textContent = U.identity.college_name || 'الكلية';
      document.getElementById('university-label').textContent = U.identity.university_name || '';
      document.getElementById('user-name').textContent = U.me.full_name;
      const roleNames = Object.values(U.me.roles || {});
      document.getElementById('user-role').textContent = roleNames.join(' · ');
      document.getElementById('role-chip').textContent = roleNames[0] || 'مستخدم';
      const year = (U.catalog.academic_years || []).find((y) => y.is_current);
      document.getElementById('year-chip').textContent = year ? 'العام الدراسي ' + year.name : 'سنة غير محددة';
      if (U.me.must_change_password) {
        location.hash = '#/password';
      }
      if (document.body.dataset.admin === '1' && !U.can('admin.panel')) {
        main.innerHTML = '<div class="card">ليست لديك صلاحية دخول لوحة الإدارة.</div>';
        return;
      }
      route();
    } catch (e) {
      location.href = '/login';
    }
  }

  function pageHead(title, sub, actions) {
    return '<div class="page-head"><div><h1>' + U.esc(title) + '</h1><p class="muted">' + U.esc(sub || '') + '</p></div><div class="toolbar">' + (actions || '') + '</div></div>';
  }

  const pages = {};

  pages.dashboard = async () => {
    const d = await U.api('/api/dashboard');
    let stats = '';
    if (d.students_count != null) stats += stat('عدد الطلبة', d.students_count, 'groups');
    if (d.due_total != null) stats += stat('الأقساط المستحقة', U.fmt(d.due_total), 'request_quote');
    if (d.paid_total != null) stats += stat('المسدد', U.fmt(d.paid_total), 'payments');
    if (d.remaining_total != null) stats += stat('المتبقي', U.fmt(d.remaining_total), 'pending_actions');
    if (d.today_income != null) stats += stat('إيراد اليوم', U.fmt(d.today_income), 'today');
    if (d.me) stats += stat('متبقي حسابك', U.fmt(d.me.remaining), 'account_balance_wallet');
    const boxes = (d.cashboxes || []).map((b) => '<div class="card"><h3>' + U.esc(b.name) + '</h3><div class="stat-value">' + U.fmt(b.balance) + '</div></div>').join('');
    const byType = (d.by_study_type || []).map((r) => '<li>' + U.esc(r.name) + ': <strong>' + U.fmt(r.total) + '</strong></li>').join('');
    const recent = (d.recent || []).map((r) => '<tr><td>' + U.esc(r.action) + '</td><td>' + U.esc(r.entity_type) + '</td><td>' + U.esc(r.created_at) + '</td></tr>').join('');
    main.innerHTML = pageHead('لوحة التحكم', 'مؤشرات بحسب صلاحياتك فقط. محاسب المصروفات لا يرى أرصدة.') +
      '<div class="stats">' + (stats || '<div class="card">لا توجد بطاقات مالية لهذه الجلسة.</div>') + '</div>' +
      (boxes ? '<div class="stats">' + boxes + '</div>' : '') +
      '<div class="card"><h3>توزيع الإيرادات حسب نوع الدراسة</h3><ul>' + (byType || '<li>لا بيانات أو لا صلاحية.</li>') + '</ul></div>' +
      '<div class="card"><h3>آخر العمليات</h3><div class="table-wrap"><table class="data"><thead><tr><th>العملية</th><th>السجل</th><th>الوقت</th></tr></thead><tbody>' + (recent || '') + '</tbody></table></div></div>';
  };

  function stat(label, value, icon) {
    return '<div class="card"><div class="muted">' + label + '</div><div class="stat-value">' + value + '</div></div>';
  }

  pages.students = async () => {
    const q = new URLSearchParams(location.hash.split('?')[1] || '');
    const res = await U.api('/api/students?' + new URLSearchParams({ q: q.get('q') || '', page: q.get('page') || 1, payment_status: q.get('status') || '' }));
    const rows = (res.data || []).map((s) => '<tr><td>' + U.esc(s.university_number) + '</td><td><a href="#/student/' + s.id + '">' + U.esc(s.full_name) + '</a></td><td>' + U.esc(s.department_name) + '</td><td>' + U.esc(s.study_stage_name) + '</td><td>' + U.esc(s.study_type_name) + '</td><td>' + U.fmt(s.base_amount) + '</td><td>' + U.fmt(s.discount_total) + '</td><td>' + U.fmt(s.net_due) + '</td><td>' + U.fmt(s.paid_total) + '</td><td>' + U.fmt(s.remaining) + '</td><td>' + U.statusBadge(s.payment_status) + '</td><td>' + U.esc(s.last_payment_at || '—') + '</td><td>' + (U.can('payments.create') && s.invoice_id ? '<a class="btn" href="#/cashier?invoice=' + s.invoice_id + '">دفعة</a>' : '') + '</td></tr>').join('');
    main.innerHTML = pageHead('الطلبة', 'البحث والتصفية حسب القسم ونوع الدراسة وحالة الدفع.', U.can('students.manage') ? '<button class="btn primary" id="new-st">طالب جديد</button>' : '') +
      '<form class="toolbar" id="sf"><input name="q" placeholder="اسم أو رقم جامعي" value="' + U.esc(q.get('q') || '') + '"><select name="status"><option value="">كل الحالات</option><option value="unpaid">غير مدفوع</option><option value="partial">جزئي</option><option value="paid">مكتمل</option><option value="overdue">متأخر</option></select><button class="btn">بحث</button></form>' +
      (rows ? '<div class="table-wrap"><table class="data"><thead><tr><th>الرقم</th><th>الاسم</th><th>القسم</th><th>المرحلة</th><th>النوع</th><th>الأساسي</th><th>الخصم</th><th>المستحق</th><th>المسدد</th><th>المتبقي</th><th>الحالة</th><th>آخر دفعة</th><th></th></tr></thead><tbody>' + rows + '</tbody></table></div>' : U.empty('لا توجد سجلات مطابقة.'));
    document.getElementById('sf').onsubmit = (e) => { e.preventDefault(); const f = new FormData(e.target); location.hash = '#/students?q=' + encodeURIComponent(f.get('q')) + '&status=' + f.get('status'); };
    document.getElementById('new-st')?.addEventListener('click', studentForm);
  };

  function opts(list, valueKey, labelKey) {
    return (list || []).map((x) => '<option value="' + x[valueKey] + '">' + U.esc(x[labelKey]) + '</option>').join('');
  }

  function studentForm() {
    const c = U.catalog;
    U.modal('<h3>ملف طالب جديد</h3><form id="stf" class="form-grid two">' +
      '<label>الاسم<input name="full_name" required></label><label>الرقم الجامعي<input name="university_number" required dir="ltr"></label>' +
      '<label>القسم<select name="department_id">' + opts(c.departments, 'id', 'name') + '</select></label>' +
      '<label>نوع الدراسة<select name="study_type_id">' + opts(c.study_types, 'id', 'name') + '</select></label>' +
      '<label>المرحلة<select name="study_stage_id">' + opts(c.study_stages, 'id', 'name') + '</select></label>' +
      '<label>السنة<select name="academic_year_id">' + opts(c.academic_years, 'id', 'name') + '</select></label>' +
      '<label>الهاتف<input name="phone" dir="ltr"></label><label>البريد<input name="email"></label>' +
      '<button class="btn primary span-2">حفظ وإصدار فاتورة</button></form>');
    document.getElementById('stf').onsubmit = async (e) => {
      e.preventDefault();
      try {
        await U.api('/api/students', { method: 'POST', body: Object.fromEntries(new FormData(e.target)) });
        U.closeModal(); U.toast('أُنشئ الملف والفواتير من السياسة المعتمدة.'); location.hash = '#/students'; route();
      } catch (err) { U.toast(err.message); }
    };
  }

  pages.student = async (id) => {
    const f = await U.api('/api/students/' + id);
    const s = f.student;
    const tabs = ['شخصي', 'أكاديمي', 'أقساط', 'خصومات', 'دفعات', 'وصولات', 'تعديلات', 'مستندات'];
    main.innerHTML = pageHead(s.full_name, s.university_number) + '<div class="tabs" id="stabs">' + tabs.map((t, i) => '<button data-i="' + i + '" class="' + (i === 0 ? 'active' : '') + '">' + t + '</button>').join('') + '</div><div id="tbox"></div>';
    const box = document.getElementById('tbox');
    const show = (i) => {
      document.querySelectorAll('#stabs button').forEach((b) => b.classList.toggle('active', b.dataset.i == i));
      if (i === 0) box.innerHTML = '<div class="card">' + ['full_name', 'phone', 'email', 'national_id', 'address'].map((k) => '<p>' + k + ': ' + U.esc(s[k] || '—') + '</p>').join('') + '</div>';
      if (i === 1) box.innerHTML = table(['السنة', 'القسم', 'النوع', 'المرحلة'], (f.enrollments || []).map((e) => [e.academic_year_name, e.department_name, e.study_type_name, e.study_stage_name]));
      if (i === 2) box.innerHTML = table(['أساسي', 'خصم', 'صافي', 'مسدد', 'متبقي', 'حالة'], (f.invoices || []).map((x) => [U.fmt(x.base_amount), U.fmt(x.discount_total), U.fmt(x.net_due), U.fmt(x.paid_total), U.fmt(x.remaining), x.status]));
      if (i === 3) {
        box.innerHTML = table(['الخصم', 'المبلغ', 'الحالة'], (f.discounts || []).map((x) => [x.title, U.fmt(x.amount), x.status]));
        if (U.can('discounts.manage') && (f.invoices || [])[0]) {
          box.insertAdjacentHTML('beforeend', '<form id="ad" class="toolbar"><input name="rule_id" placeholder="رقم القاعدة"><input name="reason" placeholder="السبب"><button class="btn">اعتماد خصم</button></form>');
          document.getElementById('ad').onsubmit = async (ev) => {
            ev.preventDefault();
            const b = Object.fromEntries(new FormData(ev.target));
            try { await U.api('/api/invoices/' + f.invoices[0].id + '/discounts', { method: 'POST', body: b }); route(); } catch (err) { U.toast(err.message); }
          };
        }
      }
      if (i === 4) box.innerHTML = table(['المبلغ', 'الحالة', 'التاريخ', 'وصل'], (f.payments || []).map((x) => [U.fmt(x.amount), x.status, x.created_at, x.receipt_number || '']));
      if (i === 5) box.innerHTML = (f.receipts || []).length ? '<ul>' + f.receipts.map((r) => '<li><a href="/receipts/' + r.id + '/print" target="_blank">' + U.esc(r.receipt_number) + '</a> — ' + U.fmt(r.paid_now) + '</li>').join('') + '</ul>' : U.empty('لا وصولات مؤرشفة.');
      if (i === 6) box.innerHTML = table(['عملية', 'وقت'], (f.audit || []).map((a) => [a.action, a.created_at]));
      if (i === 7) box.innerHTML = (f.docs || []).length ? f.docs.map((d) => '<div class="card">' + U.esc(d.title) + '</div>').join('') : U.empty('لا مستندات مرفوعة.');
    };
    show(0);
    document.getElementById('stabs').onclick = (e) => { if (e.target.dataset.i) show(e.target.dataset.i); };
  };

  function table(headers, rows) {
    if (!rows.length) return U.empty('لا بيانات في هذا التبويب.');
    return '<div class="table-wrap"><table class="data"><thead><tr>' + headers.map((h) => '<th>' + h + '</th>').join('') + '</tr></thead><tbody>' +
      rows.map((r) => '<tr>' + r.map((c) => '<td>' + c + '</td>').join('') + '</tr>').join('') + '</tbody></table></div>';
  }

  pages.tuition = async () => {
    const [policies, students] = await Promise.all([
      U.api('/api/tuition-policies'),
      U.api('/api/students?per_page=50'),
    ]);
    main.innerHTML = pageHead('الأجور والأقساط', 'السياسات ديناميكية حسب السنة والنوع والمرحلة. تعديل السياسة لا يغيّر الفواتير التي لها وصولات.',
      U.can('tuition.manage') ? '<button class="btn primary" id="np">سياسة قسط جديدة</button>' : '') +
      '<div class="card"><h3>السياسات المعتمدة</h3>' + table(['الاسم', 'النوع', 'المبلغ', 'عدد الأقساط', 'ينطبق على'], (policies || []).map((p) => [p.name, p.study_type_name, U.fmt(p.base_amount), p.installment_count, p.applies_to === 'new_only' ? 'الجدد فقط' : 'الحاليون بلا وصولات'])) + '</div>' +
      '<div class="card"><h3>الذمم</h3>' + table(['طالب', 'مستحق', 'متبقي', 'حالة'], (students.data || []).map((s) => [s.full_name, U.fmt(s.net_due), U.fmt(s.remaining), U.statusBadge(s.payment_status)])) + '</div>';
    document.getElementById('np')?.addEventListener('click', () => {
      const c = U.catalog;
      U.modal('<h3>سياسة قسط</h3><form id="pf" class="form-grid"><label>الاسم<input name="name" required></label><label>المبلغ<input name="base_amount" required></label>' +
        '<label>عدد الأقساط<select name="installment_count"><option>1</option><option>2</option><option>4</option></select></label>' +
        '<label>نوع الدراسة<select name="study_type_id">' + opts(c.study_types, 'id', 'name') + '</select></label>' +
        '<label>السنة<select name="academic_year_id">' + opts(c.academic_years, 'id', 'name') + '</select></label>' +
        '<label>التطبيق<select name="applies_to"><option value="new_only">طلبة جدد فقط</option><option value="current_without_receipts">الحاليون بلا وصولات</option></select></label>' +
        '<button class="btn primary">حفظ</button></form>');
      document.getElementById('pf').onsubmit = async (e) => {
        e.preventDefault();
        const b = Object.fromEntries(new FormData(e.target));
        try { await U.api('/api/tuition-policies', { method: 'POST', body: b }); U.closeModal(); route(); } catch (err) { U.toast(err.message); }
      };
    });
  };

  pages.discounts = async () => {
    const rules = await U.api('/api/discount-rules');
    main.innerHTML = pageHead('الخصومات', 'القيم تُضبط هنا. لا تُعاد كتابة الخصم التاريخي بعد اعتماده.',
      U.can('discounts.manage') ? '<button class="btn primary" id="nr">قاعدة جديدة</button>' : '') +
      table(['النوع', 'القاعدة', 'الاحتساب', 'القيمة', 'فئة', 'جمع؟', 'حالة'], (rules || []).map((r) => [r.type_name, r.name, r.calculation === 'percent' ? 'نسبة' : 'ثابت', r.value, r.category_code || '—', r.stackable ? 'نعم' : 'لا', r.is_active ? 'فعال' : 'موقوف']));
    document.getElementById('nr')?.addEventListener('click', () => {
      U.modal('<h3>قاعدة خصم</h3><form id="df" class="form-grid"><label>الاسم<input name="name" required></label>' +
        '<label>النوع<select name="discount_type_id">' + opts(U.catalog.discount_types, 'id', 'name') + '</select></label>' +
        '<label>احتساب<select name="calculation"><option value="percent">نسبة</option><option value="fixed">مبلغ ثابت</option></select></label>' +
        '<label>القيمة<input name="value" required></label><label>فئة الأوائل<input name="category_code" placeholder="top1 / top2 / top3 / first_degree / self"></label>' +
        '<label class="check"><input type="checkbox" name="stackable" value="1"> قابل للجمع</label><button class="btn primary">حفظ</button></form>');
      document.getElementById('df').onsubmit = async (e) => {
        e.preventDefault();
        const b = Object.fromEntries(new FormData(e.target));
        b.stackable = e.target.stackable.checked;
        try { await U.api('/api/discount-rules', { method: 'POST', body: b }); U.closeModal(); route(); } catch (err) { U.toast(err.message); }
      };
    });
  };

  pages.cashier = async () => {
    main.innerHTML = pageHead('أمين الصندوق', 'يُعاد احتساب المبالغ على الخادم. الدفعة الصفرية أو المكررة مرفوضة.') +
      '<div class="card"><label>بحث طالب<input id="sq" placeholder="اسم أو رقم جامعي"></label><div id="sres"></div></div><div id="paybox"></div>';
    const sq = document.getElementById('sq');
    sq.addEventListener('input', async () => {
      if (sq.value.length < 2) return;
      const rows = await U.api('/api/students/search?q=' + encodeURIComponent(sq.value));
      document.getElementById('sres').innerHTML = (rows || []).map((s) => '<button class="btn" data-id="' + s.invoice_id + '">' + U.esc(s.full_name) + ' — ' + s.university_number + ' — متبقي ' + U.fmt(s.remaining) + '</button>').join('');
    });
    document.getElementById('sres').onclick = (e) => { if (e.target.dataset.id) openPay(e.target.dataset.id); };
    const pre = new URLSearchParams(location.hash.split('?')[1] || '').get('invoice');
    if (pre) openPay(pre);
  };

  async function openPay(invoiceId) {
    const snap = await U.api('/api/invoices/' + invoiceId);
    const inv = snap.invoice; const en = snap.enroll;
    document.getElementById('paybox').innerHTML = '<div class="card"><h3>' + U.esc(en.full_name) + '</h3>' +
      '<p>' + U.esc(en.university_number) + ' · ' + U.esc(en.study_type_name) + ' · ' + U.esc(en.study_stage_name) + '</p>' +
      '<div class="stats"><div class="card">أساسي<br><strong>' + U.fmt(inv.base_amount) + '</strong></div><div class="card">خصم<br><strong>' + U.fmt(inv.discount_total) + '</strong></div><div class="card">مستحق<br><strong>' + U.fmt(inv.net_due) + '</strong></div><div class="card">متبقي<br><strong>' + U.fmt(inv.remaining) + '</strong></div></div>' +
      '<form id="payf" class="form-grid two"><label>المبلغ المستلم الآن<input name="amount" required value="' + inv.remaining + '"></label><label>ملاحظات<input name="notes"></label><button class="btn primary span-2">تأكيد الاستلام وإصدار وصل</button></form></div>';
    document.getElementById('payf').onsubmit = async (e) => {
      e.preventDefault();
      const amount = e.target.amount.value;
      const ok = await U.confirm('تأكيد قبض ' + amount + ' دينار؟ سيُنشأ وصل فريد ويُرحَّل للخزنة المطابقة.');
      if (!ok) return;
      try {
        const r = await U.api('/api/payments', { method: 'POST', body: { invoice_id: Number(invoiceId), amount, notes: e.target.notes.value, idempotency_key: crypto.randomUUID() } });
        U.toast(r.replayed ? 'أُعيد استخدام الطلب السابق دون تكرار الدفعة.' : 'تم القبض.');
        window.open('/receipts/' + r.receipt.id + '/print', '_blank');
        route();
      } catch (err) { U.toast(err.message); }
    };
  }

  pages.receipts = async () => {
    const rows = await U.api('/api/receipts');
    main.innerHTML = pageHead('الوصولات المؤرشفة', 'الأرشفة تتم مع إصدار الوصل حتى لو تعذرت الطباعة.') +
      '<form class="toolbar" id="rf"><input name="q" placeholder="رقم وصل أو اسم أو رقم جامعي"><button class="btn">بحث</button></form>' +
      table(['الوصل', 'الطالب', 'المدفوع', 'المتبقي وقت الإصدار', 'التاريخ', 'طباعة'], (rows || []).map((r) => [r.receipt_number, r.full_name, U.fmt(r.paid_now), U.fmt(r.remaining_after), r.issued_at, '<a href="/receipts/' + r.id + '/print" target="_blank">A5</a>']));
    document.getElementById('rf').onsubmit = async (e) => {
      e.preventDefault();
      const q = new FormData(e.target).get('q');
      const found = await U.api('/api/receipts?q=' + encodeURIComponent(q));
      main.querySelector('.table-wrap')?.remove();
      main.insertAdjacentHTML('beforeend', table(['الوصل', 'الطالب', 'المدفوع', 'المتبقي', 'التاريخ', 'طباعة'], (found || []).map((r) => [r.receipt_number, r.full_name, U.fmt(r.paid_now), U.fmt(r.remaining_after), r.issued_at, '<a href="/receipts/' + r.id + '/print" target="_blank">A5</a>'])));
    };
  };

  pages.cashboxes = async () => {
    const rows = await U.api('/api/cashboxes');
    main.innerHTML = pageHead('الخزائن', 'الرصيد يُحسب من دفتر الحركات المرحلة.') +
      (rows || []).map((s) => '<div class="card"><h3>' + U.esc(s.cashbox.name) + '</h3><div class="stats">' +
        stat('افتتاحي', U.fmt(s.opening)) + stat('إيراد', U.fmt(s.income)) + stat('مصروف', U.fmt(s.expense)) + stat('الرصيد', U.fmt(s.balance)) +
        '</div><button class="btn" data-id="' + s.cashbox.id + '">دفتر الحركات</button></div>').join('');
    main.onclick = async (e) => {
      if (!e.target.dataset.id) return;
      const led = await U.api('/api/cashboxes/' + e.target.dataset.id + '/ledger');
      U.modal('<h3>الحركات</h3>' + table(['اتجاه', 'مبلغ', 'نوع', 'مرجع', 'من', 'وقت'], (led.data || []).map((t) => [t.direction, U.fmt(t.amount), t.kind, t.reference || '', t.actor_name, t.created_at])));
    };
  };

  pages.expenses = async () => {
    const rows = await U.api('/api/expenses');
    main.innerHTML = pageHead('كتب المصروفات', 'محاسب المصروفات يختار الخزنة دون رؤية رصيدها.',
      U.can('expenses.create') ? '<button class="btn primary" id="ne">كتاب جديد</button>' : '') +
      table(['الرقم', 'العنوان', 'الجهة', 'المبلغ', 'الحالة', ''], (rows || []).map((b) => [b.book_number, b.title, b.beneficiary, U.fmt(b.total_amount), b.status, '<a href="#/expense/' + b.id + '">فتح</a>']));
    document.getElementById('ne')?.addEventListener('click', expenseForm);
  };

  function expenseForm() {
    U.modal('<h3>كتاب مصروفات</h3><form id="ef" class="form-grid"><label>العنوان<input name="title" required></label><label>الجهة<input name="beneficiary" required></label>' +
      '<label>مصدر الصرف<select name="cashbox_id">' + opts(U.catalog.cashboxes, 'id', 'name') + '</select></label><label>المبرر<textarea name="justification"></textarea></label>' +
      '<div id="lines"></div><button type="button" class="btn" id="addline">بند</button><button class="btn primary">حفظ مسودة</button></form>');
    const lines = document.getElementById('lines');
    const add = () => { lines.insertAdjacentHTML('beforeend', '<div class="form-grid two line"><input name="iname" placeholder="المادة"><input name="iqty" value="1"><input name="iprice" placeholder="سعر الوحدة"></div>'); };
    add();
    document.getElementById('addline').onclick = add;
    document.getElementById('ef').onsubmit = async (e) => {
      e.preventDefault();
      const items = [...e.target.querySelectorAll('.line')].map((n) => ({ name: n.querySelector('[name=iname]').value, quantity: n.querySelector('[name=iqty]').value, unit_price: n.querySelector('[name=iprice]').value }));
      const body = { title: e.target.title.value, beneficiary: e.target.beneficiary.value, cashbox_id: e.target.cashbox_id.value, justification: e.target.justification.value, items };
      try { const r = await U.api('/api/expenses', { method: 'POST', body }); U.closeModal(); location.hash = '#/expense/' + r.book.id; route(); } catch (err) { U.toast(err.message); }
    };
  }

  pages.expense = async (id) => {
    const d = await U.api('/api/expenses/' + id);
    const b = d.book;
    let actions = '<a class="btn" href="/expenses/' + id + '/print" target="_blank">طباعة</a>';
    if (b.status === 'draft' && U.can('expenses.create')) actions += '<button class="btn primary" id="sub">تقديم للمراجعة</button>';
    if ((b.status === 'submitted' || b.status === 'pending_approval') && U.can('expenses.approve')) actions += '<button class="btn primary" id="appr">اعتماد</button><button class="btn danger" id="rej">رفض</button>';
    if (b.status === 'approved' && U.can('expenses.disburse')) actions += '<button class="btn primary" id="dis">تنفيذ الصرف</button>';
    main.innerHTML = pageHead(b.title, b.book_number + ' — أنشئ في ' + b.created_at + ' (لا يُستبدل بتاريخ اليوم)') +
      '<p>الحالة: ' + b.status + ' — الجهة: ' + U.esc(b.beneficiary) + '</p><div class="toolbar">' + actions + '</div>' +
      table(['ت', 'بند', 'كمية', 'سعر', 'إجمالي'], (d.items || []).map((i) => [i.sequence, i.name, i.quantity, U.fmt(i.unit_price), U.fmt(i.line_total)])) +
      '<p>المجموع: <strong>' + U.fmt(b.total_amount) + '</strong></p>' +
      table(['المرحلة', 'القرار', 'الموقّع', 'التاريخ'], (d.approvals || []).map((a) => [a.step, a.decision, a.signer_name || '—', a.signed_at || '']));
    document.getElementById('sub')?.addEventListener('click', async () => { await U.api('/api/expenses/' + id + '/submit', { method: 'POST', body: {} }); route(); });
    document.getElementById('appr')?.addEventListener('click', async () => {
      const step = (d.approvals || []).find((a) => a.decision === 'pending')?.step || 'review';
      await U.api('/api/expenses/' + id + '/approve', { method: 'POST', body: { step } }); route();
    });
    document.getElementById('rej')?.addEventListener('click', async () => {
      const reason = prompt('سبب الرفض');
      if (reason) { await U.api('/api/expenses/' + id + '/reject', { method: 'POST', body: { reason } }); route(); }
    });
    document.getElementById('dis')?.addEventListener('click', async () => {
      try { await U.api('/api/expenses/' + id + '/disburse', { method: 'POST', body: {} }); route(); }
      catch (err) { U.toast(err.message); }
    });
  };

  pages.users = async () => {
    const rows = await U.api('/api/users');
    main.innerHTML = pageHead('المستخدمون والصلاحيات', 'الحساب الجديد بلا أدوار مالية حتى الإسناد.',
      U.can('users.manage') ? '<button class="btn primary" id="nu">إنشاء حساب</button>' : '') +
      table(['الاسم', 'المستخدم', 'الهاتف', 'البريد', 'الحالة', 'أدوار', 'QR', ''], (rows || []).map((u) => [
        u.full_name, u.username, u.phone || '', u.email || '', u.status, Object.values(u.roles || {}).join('، '),
        u.qr ? (u.qr.revoked_at ? 'ملغى' : 'نشط') : '—',
        '<button class="btn" data-u="' + u.id + '">إدارة</button>'
      ]));
    document.getElementById('nu')?.addEventListener('click', () => {
      U.modal('<form id="uf" class="form-grid"><input name="full_name" placeholder="الاسم" required><input name="username" placeholder="المستخدم" required><input name="password" type="password" placeholder="كلمة مرور مؤقتة" required><button class="btn primary">حفظ</button></form>');
      document.getElementById('uf').onsubmit = async (e) => { e.preventDefault(); await U.api('/api/users', { method: 'POST', body: Object.fromEntries(new FormData(e.target)) }); U.closeModal(); route(); };
    });
    main.querySelectorAll('[data-u]').forEach((btn) => btn.onclick = () => userManage(btn.dataset.u, rows.find((x) => x.id == btn.dataset.u)));
  };

  function userManage(id, user) {
    const roles = (U.catalog.roles || []).map((r) => '<label class="check"><input type="checkbox" value="' + r.code + '"' + (user.roles[r.code] ? ' checked' : '') + '> ' + r.name + '</label>').join('');
    U.modal('<h3>' + U.esc(user.full_name) + '</h3><div class="toolbar"><button class="btn" id="act">تفعيل</button><button class="btn danger" id="dis">تعطيل</button></div><div id="roles">' + roles + '</div><button class="btn primary" id="sr">حفظ الأدوار</button>');
    document.getElementById('act').onclick = async () => { await U.api('/api/users/' + id + '/status', { method: 'POST', body: { status: 'active' } }); route(); };
    document.getElementById('dis').onclick = async () => { await U.api('/api/users/' + id + '/status', { method: 'POST', body: { status: 'disabled' } }); route(); };
    document.getElementById('sr').onclick = async () => {
      const selected = [...document.querySelectorAll('#roles input:checked')].map((i) => i.value);
      await U.api('/api/users/' + id + '/roles', { method: 'POST', body: { roles: selected } }); U.toast('حُفظت الأدوار');
    };
  }

  pages.qr = async () => {
    const rows = await U.api('/api/users');
    main.innerHTML = pageHead('رموز QR', 'الرمز عشوائي ولا يحتوي كلمة المرور أو قائمة الصلاحيات.') +
      table(['المستخدم', 'إصدار'], (rows || []).map((u) => [u.full_name, '<button class="btn" data-q="' + u.id + '">إصدار رمز دخول</button>']));
    main.onclick = async (e) => {
      if (!e.target.dataset.q) return;
      const r = await U.api('/api/users/' + e.target.dataset.q + '/qr', { method: 'POST', body: { purpose: 'login', single_use: true, ttl: 300 } });
      U.modal('<h3>رمز لمرة واحدة</h3><img alt="QR" src="' + r.qr + '" style="width:220px"><p dir="ltr">' + U.esc(r.token) + '</p><p class="muted">انسخ الرمز في شاشة الدخول. ألغِه فوراً عند الشك.</p>');
    };
  };

  pages.identity = async () => {
    const id = U.identity;
    main.innerHTML = pageHead('الهوية الرسمية', 'الأسماء والشعارات تُسحب من قاعدة البيانات.') +
      '<form id="idf" class="form-grid two card"><label>الجامعة<input name="university_name" value="' + U.esc(id.university_name) + '"></label>' +
      '<label>الكلية<input name="college_name" value="' + U.esc(id.college_name) + '"></label>' +
      '<label>القسم الافتراضي<input name="department_name" value="' + U.esc(id.department_name || '') + '"></label>' +
      '<label>العميد<input name="dean_name" value="' + U.esc(id.dean_name || '') + '"></label>' +
      '<label>عنوان الوثيقة<input name="document_title" value="' + U.esc(id.document_title || '') + '"></label>' +
      '<label>العملة<input name="currency_label" value="' + U.esc(id.currency_label || '') + '"></label>' +
      '<label class="span-2">ترويسة<textarea name="header_text">' + U.esc(id.header_text || '') + '</textarea></label>' +
      '<label class="span-2">تذييل<textarea name="footer_text">' + U.esc(id.footer_text || '') + '</textarea></label>' +
      '<label class="span-2">سبب التغيير<input name="reason" required placeholder="يُحفظ في سجل الهوية"></label>' +
      '<button class="btn primary span-2">حفظ الهوية</button></form>';
    document.getElementById('idf').onsubmit = async (e) => {
      e.preventDefault();
      await U.api('/api/settings/identity', { method: 'POST', body: Object.fromEntries(new FormData(e.target)) });
      U.toast('حُفظت الهوية مع سجل التغيير.');
    };
  };

  pages.academic = async () => {
    const c = U.catalog;
    main.innerHTML = pageHead('الهيكل الأكاديمي', 'أقسام، سنوات، مراحل، أنواع دراسة.') +
      '<div class="card"><h3>الأقسام</h3>' + table(['الاسم', 'الرمز'], (c.departments || []).map((d) => [d.name, d.code])) +
      (U.can('academic.manage') ? '<form id="dep" class="toolbar"><input name="name" placeholder="قسم جديد"><input name="code" placeholder="رمز"><button class="btn">إضافة</button></form>' : '') + '</div>' +
      '<div class="card"><h3>السنوات</h3>' + table(['الاسم', 'حالية؟'], (c.academic_years || []).map((y) => [y.name, y.is_current ? 'نعم' : 'لا'])) + '</div>';
    document.getElementById('dep')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const b = Object.fromEntries(new FormData(e.target));
      b.college_id = c.colleges[0].id;
      await U.api('/api/departments', { method: 'POST', body: b });
      location.reload();
    });
  };

  pages.teachers = async () => {
    const [staff, fees] = await Promise.all([U.api('/api/staff'), U.api('/api/teacher-fees')]);
    main.innerHTML = pageHead('أجور التدريسيين', 'وحدة مستقلة عن خزنتي الطلبة. المعادلة تُدخل كقيمة مستحقة قابلة للتهيئة.') +
      table(['التدريسي', 'القسم'], (staff || []).map((s) => [s.full_name, s.department_name || ''])) +
      table(['التدريسي', 'النوع', 'مستحق', 'مصروف', 'متبقي', 'حالة'], (fees || []).map((f) => [f.full_name, f.type_name, U.fmt(f.due_amount), U.fmt(f.paid_amount), U.fmt(f.remaining), f.status]));
  };

  pages.timetable = async () => {
    const rows = await U.api('/api/timetable');
    const days = ['أحد', 'اثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'];
    const cols = days.map((d, i) => {
      const slots = (rows || []).filter((r) => Number(r.day_of_week) === i).map((r) => '<div class="slot"><strong>' + U.esc(r.subject_name) + '</strong><div>' + r.starts_at + ' — ' + r.ends_at + '</div><div>' + U.esc(r.staff_name || '') + ' · ' + U.esc(r.room_name || '') + '</div></div>').join('');
      return '<div class="day-col"><strong>' + d + '</strong>' + (slots || '<p class="muted">—</p>') + '</div>';
    }).join('');
    main.innerHTML = pageHead('جدول الدوام المقرر', 'يُرفض تعارض القاعة أو التدريسي أو المرحلة.') + '<div class="week">' + cols + '</div>';
  };

  pages.reports = async () => {
    const r = await U.api('/api/reports/financial');
    main.innerHTML = pageHead('التقارير المالية', 'حسب نوع الدراسة والخصومات المعتمدة.') +
      table(['النوع', 'فواتير', 'مستحق', 'مسدد', 'متبقي'], (r.invoices || []).map((x) => [x.study_type, x.invoices, U.fmt(x.due_sum), U.fmt(x.paid_sum), U.fmt(x.rem_sum)])) +
      table(['نوع الخصم', 'العدد', 'المجموع'], (r.discounts || []).map((x) => [x.name, x.c, U.fmt(x.t)]));
  };

  pages.audit = async () => {
    const rows = await U.api('/api/audit');
    main.innerHTML = pageHead('سجل التدقيق', 'للإلحاق فقط. لا حذف من الواجهة.') +
      '<form class="toolbar" id="af"><input name="q" placeholder="بحث عملية أو مستخدم"><button class="btn">تصفية</button></form>' +
      table(['المستخدم', 'عملية', 'كيان', 'نتيجة', 'وقت'], (rows || []).map((a) => [a.full_name || 'نظام', a.action, a.entity_type + ' #' + (a.entity_id || ''), a.result, a.created_at]));
    document.getElementById('af').onsubmit = async (e) => {
      e.preventDefault();
      const rows2 = await U.api('/api/audit?q=' + encodeURIComponent(new FormData(e.target).get('q')));
      main.querySelector('.table-wrap').outerHTML = table(['المستخدم', 'عملية', 'كيان', 'نتيجة', 'وقت'], (rows2 || []).map((a) => [a.full_name || 'نظام', a.action, a.entity_type, a.result, a.created_at]));
    };
  };

  pages.backup = async () => {
    const rows = await U.api('/api/backup');
    main.innerHTML = pageHead('النسخ الاحتياطي', 'ملف SQL في مجلد التخزين خارج المسار العام.') +
      '<button class="btn primary" id="bk">إنشاء نسخة الآن</button>' +
      table(['الملف', 'الوقت'], (rows || []).map((b) => [b.filename, b.created_at]));
    document.getElementById('bk').onclick = async () => { try { await U.api('/api/backup', { method: 'POST', body: {} }); route(); } catch (e) { U.toast(e.message); } };
  };

  pages.me = async () => {
    if (!U.me.student_id) { main.innerHTML = pageHead('حسابي', '') + '<p>هذه الصفحة للطلبة المرتبطين بملف دراسي.</p>'; return; }
    const f = await U.api('/api/students/' + U.me.student_id);
    main.innerHTML = pageHead(f.student.full_name, 'بياناتك ووصولاتك فقط.') +
      table(['مستحق', 'مسدد', 'متبقي'], (f.invoices || []).map((i) => [U.fmt(i.net_due), U.fmt(i.paid_total), U.fmt(i.remaining)])) +
      '<h3>وصولاتي</h3>' + (f.receipts || []).map((r) => '<p><a href="/receipts/' + r.id + '/print" target="_blank">' + r.receipt_number + '</a></p>').join('');
  };

  pages.password = async () => {
    main.innerHTML = pageHead('تغيير كلمة المرور', 'مطلوب بعد الحساب الابتدائي.') +
      '<form id="cp" class="form-grid card"><label>الحالية<input name="current_password" type="password" required></label><label>الجديدة<input name="password" type="password" required></label><label>تأكيد<input name="password_confirmation" type="password" required></label><button class="btn primary">حفظ</button></form>';
    document.getElementById('cp').onsubmit = async (e) => {
      e.preventDefault();
      try { await U.api('/api/auth/change-password', { method: 'POST', body: Object.fromEntries(new FormData(e.target)) }); U.toast('تم التغيير'); location.hash = '#/dashboard'; }
      catch (err) { U.toast(err.message); }
    };
  };

  async function route() {
    const hash = location.hash.replace(/^#\/?/, '') || 'dashboard';
    const [name, id] = hash.split('?')[0].split('/');
    renderNav(name === 'student' ? 'students' : name === 'expense' ? 'expenses' : name);
    main.innerHTML = '<div class="skeleton-page"></div>';
    try {
      if (name === 'student' && id) return pages.student(id);
      if (name === 'expense' && id) return pages.expense(id);
      const fn = pages[name] || pages.dashboard;
      await fn(id);
    } catch (e) {
      main.innerHTML = '<div class="card alert error">' + U.esc(e.message) + '</div>';
    }
  }

  window.addEventListener('hashchange', route);
  document.getElementById('logout-btn').onclick = async () => {
    try { await U.api('/api/auth/logout', { method: 'POST', body: {} }); } catch (e) {}
    sessionStorage.clear();
    location.href = '/login';
  };
  document.getElementById('menu-toggle').onclick = () => document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('notif-btn').onclick = async () => {
    const p = document.getElementById('notif-panel');
    p.hidden = !p.hidden;
    if (!p.hidden) {
      const rows = await U.api('/api/notifications');
      p.innerHTML = ((rows || []).map((n) => '<div class="card"><strong>' + U.esc(n.title) + '</strong><p>' + U.esc(n.body) + '</p></div>').join('')) || '<p class="empty">لا إشعارات</p>';
    }
  };
  boot();
})();
