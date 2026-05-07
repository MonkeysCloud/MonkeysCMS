(function() {
  const form   = document.getElementById('login-form');
  const btn    = document.getElementById('login-btn');
  const errBox = document.getElementById('login-error');
  const errMsg = document.getElementById('login-error-msg');
  const okBox  = document.getElementById('login-success');
  const okMsg  = document.getElementById('login-success-msg');

  if (!form) return;

  let originalText = '';

  function showError(msg) {
    errMsg.textContent = msg;
    errBox.style.display = 'flex';
    okBox.style.display = 'none';
  }

  function showSuccess(msg) {
    okMsg.textContent = msg;
    okBox.style.display = 'flex';
    errBox.style.display = 'none';
  }

  function setLoading(on) {
    if (!btn) return;
    btn.disabled = on;
    if (on) {
      originalText = btn.textContent;
      btn.textContent = 'Signing in…';
      btn.style.opacity = '0.7';
    } else {
      btn.textContent = originalText;
      btn.style.opacity = '1';
    }
  }

  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    errBox.style.display = 'none';
    okBox.style.display = 'none';
    setLoading(true);

    const email    = document.getElementById('edit-email').value.trim();
    const password = document.getElementById('edit-password').value;

    if (!email || !password) {
      showError('Email and password are required.');
      setLoading(false);
      return;
    }

    try {
      const res = await fetch('/admin/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ email, password }),
      });

      const data = await res.json();

      if (data.success) {
        showSuccess('Login successful! Redirecting…');
        setTimeout(() => {
          window.location.href = data.redirect || '/admin';
        }, 600);
      } else {
        showError(data.message || 'Invalid email or password.');
        setLoading(false);
      }
    } catch (err) {
      showError('An error occurred. Please try again.');
      setLoading(false);
    }
  });
})();
