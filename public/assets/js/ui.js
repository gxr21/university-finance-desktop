(function () {
  const U = window.Ufam;
  U.fmt = (n) => Number(n || 0).toLocaleString('en-US');
  U.el = (html) => {
    const t = document.createElement('template');
    t.innerHTML = html.trim();
    return t.content.firstElementChild;
  };
  U.toast = (msg) => {
    const n = document.getElementById('toast');
    if (!n) return;
    n.hidden = false;
    n.textContent = msg;
    setTimeout(() => { n.hidden = true; }, 2800);
  };
  U.modal = (html) => {
    const box = document.getElementById('modal');
    box.hidden = false;
    box.innerHTML = '<div class="dialog">' + html + '</div>';
    box.onclick = (e) => { if (e.target === box) box.hidden = true; };
    return box;
  };
  U.closeModal = () => { const b = document.getElementById('modal'); if (b) b.hidden = true; };
  U.confirm = (msg) => new Promise((resolve) => {
    U.modal('<p>' + msg + '</p><div class="toolbar"><button class="btn danger" id="c-yes">تأكيد</button><button class="btn" id="c-no">إلغاء</button></div>');
    document.getElementById('c-yes').onclick = () => { U.closeModal(); resolve(true); };
    document.getElementById('c-no').onclick = () => { U.closeModal(); resolve(false); };
  });
  U.statusBadge = (st) => {
    const map = { unpaid: ['غير مدفوع', 'badge-err'], partial: ['مدفوع جزئياً', 'badge-warn'], paid: ['مكتمل', 'badge-ok'], overdue: ['متأخر', 'badge-err'] };
    const x = map[st] || [st || '—', ''];
    return '<span class="' + x[1] + '">' + x[0] + '</span>';
  };
  U.esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  U.empty = (text) => '<div class="empty"><span class="material-symbols-outlined">inbox</span><p>' + U.esc(text) + '</p></div>';
  U.can = (perm) => (U.me?.permissions || []).includes(perm);
})();
