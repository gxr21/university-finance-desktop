window.Ufam = window.Ufam || {};
Ufam.token = sessionStorage.getItem('ufam_token') || '';
Ufam.csrf = document.body?.dataset?.csrf || sessionStorage.getItem('ufam_csrf') || '';

Ufam.needsPasswordChange = function (user) {
  const v = user && user.must_change_password;
  return v === true || v === 1 || v === '1';
};

Ufam.api = async function (path, options = {}) {
  const headers = Object.assign({
    'Accept': 'application/json',
    'X-CSRF-Token': Ufam.csrf || '',
  }, options.headers || {});
  if (Ufam.token) headers.Authorization = 'Bearer ' + Ufam.token;
  let body = options.body;
  if (body && !(body instanceof FormData) && typeof body !== 'string') {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(body);
  }
  const ctrl = new AbortController();
  const ms = options.timeoutMs ?? 15000;
  const timer = setTimeout(() => ctrl.abort(), ms);
  try {
    const res = await fetch(path, {
      method: options.method || 'GET',
      headers,
      body,
      credentials: 'same-origin',
      cache: 'no-store',
      signal: ctrl.signal,
    });
    const data = await res.json().catch(() => ({ ok: false, message: 'تعذر قراءة الاستجابة.' }));
    const csrf = data?.csrf || data?.data?.csrf;
    if (csrf) {
      Ufam.csrf = csrf;
      sessionStorage.setItem('ufam_csrf', csrf);
    }
    if (!res.ok || data.ok === false) {
      const err = new Error(data.message || 'تعذر تنفيذ العملية.');
      err.status = res.status;
      err.payload = data;
      throw err;
    }
    return data.data !== undefined ? data.data : data;
  } catch (e) {
    if (e && e.name === 'AbortError') {
      const err = new Error('انتهت مهلة الاتصال بالخادم. أعد المحاولة.');
      err.status = 0;
      throw err;
    }
    throw e;
  } finally {
    clearTimeout(timer);
  }
};

Ufam.alertBox = function (el, message, type) {
  if (!el) return;
  el.hidden = !message;
  el.className = 'alert ' + (type || 'error');
  el.textContent = message || '';
};
