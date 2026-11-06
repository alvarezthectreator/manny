(() => {
  async function request(path, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    const headers = { Accept: 'application/json', ...options.headers };
    let body = options.body;

    if (method !== 'GET' && method !== 'HEAD') {
      const adminContext = document.body.dataset.authContext === 'admin';
      const csrfPath = adminContext ? 'api/auth/csrf.php?context=admin' : 'api/auth/csrf.php';
      const csrfResponse = await fetch(csrfPath, { credentials: 'same-origin' });
      const csrf = await csrfResponse.json();
      if (!csrfResponse.ok || !csrf.csrf_token) throw new Error('Your session expired. Sign in again.');
      headers['X-CSRF-Token'] = csrf.csrf_token;
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify({ ...(typeof body === 'object' && body !== null ? body : {}), csrf_token: csrf.csrf_token });
    }

    const response = await fetch(path, { ...options, method, headers, body, credentials: 'same-origin' });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.message || 'The request could not be completed.');
    return result;
  }

  window.cladvestApi = {
    get: (path) => request(path),
    send: (path, method, body, headers = {}) => request(path, { method, body, headers }),
    idempotencyKey: () => globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`
  };
})();