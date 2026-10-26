(function () {
  document.querySelectorAll('[data-toggle="password"]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const input = document.getElementById('password') || btn.parentElement.querySelector('input');
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
    });
  });
  const alertEl = document.getElementById('alert');
  async function submit(form, path, then) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      const body = Object.fromEntries(fd.entries());
      if (form.querySelector('[name=agree]')) body.agree = form.querySelector('[name=agree]').checked;
      try {
        const data = await Ufam.api(path, { method: 'POST', body });
        if (data.token) {
          sessionStorage.setItem('ufam_token', data.token);
          Ufam.token = data.token;
          if (data.csrf) sessionStorage.setItem('ufam_csrf', data.csrf);
          location.href = data.user?.must_change_password ? '/app#/password' : '/app';
          return;
        }
        Ufam.alertBox(alertEl, data.message || 'تم.', 'success');
        if (then) then(data);
      } catch (err) {
        const extra = err.payload?.errors ? Object.values(err.payload.errors).join(' ') : '';
        Ufam.alertBox(alertEl, (err.message || '') + (extra ? ' ' + extra : ''), 'error');
      }
    });
  }
  const login = document.getElementById('login-form');
  if (login) submit(login, '/api/auth/login');
  const reg = document.getElementById('register-form');
  if (reg) submit(reg, '/api/auth/register');
  const qr = document.getElementById('qr-form');
  if (qr) submit(qr, '/api/auth/qr');
  const forgot = document.getElementById('forgot-form');
  if (forgot) submit(forgot, '/api/auth/forgot');
  const reset = document.getElementById('reset-form');
  if (reset) submit(reset, '/api/auth/reset', () => setTimeout(() => location.href = '/login', 800));
})();
