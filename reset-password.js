(() => {
  if (window.lucide) window.lucide.createIcons();

  async function csrfToken() {
    const response = await fetch('api/auth/csrf.php', { credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || !result.csrf_token) throw new Error('Could not start a secure session. Check that MAMP is running.');
    return result.csrf_token;
  }

  async function send(path, payload) {
    const csrf = await csrfToken();
    const response = await fetch(path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ ...payload, csrf_token: csrf }),
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || 'The request could not be completed.');
    return result;
  }

  const showMessage = (box, text) => {
    box.querySelector('span').textContent = text;
    box.hidden = false;
  };

  const forgotForm = document.querySelector('[data-forgot-form]');
  if (forgotForm) {
    const message = document.querySelector('[data-reset-message]');
    forgotForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = forgotForm.querySelector('button');
      button.disabled = true;
      try {
        const result = await send('api/auth/forgot-password.php', { email: forgotForm.elements.email.value.trim() });
        showMessage(message, result.message);
        forgotForm.reset();
      } catch (error) {
        showMessage(message, error.message);
      } finally {
        button.disabled = false;
      }
    });
  }

  const resetForm = document.querySelector('[data-reset-form]');
  if (resetForm) {
    const message = document.querySelector('[data-reset-message]');
    const token = new URLSearchParams(window.location.search).get('token') || '';
    resetForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (resetForm.elements.password.value !== resetForm.elements.confirm_password.value) {
        showMessage(message, 'Passwords do not match.');
        return;
      }
      const button = resetForm.querySelector('button');
      button.disabled = true;
      try {
        const result = await send('api/auth/reset-password.php', { token, password: resetForm.elements.password.value });
        showMessage(message, result.message);
        resetForm.reset();
        window.setTimeout(() => window.location.assign('login.html'), 1200);
      } catch (error) {
        showMessage(message, error.message);
      } finally {
        button.disabled = false;
      }
    });
  }
})();
