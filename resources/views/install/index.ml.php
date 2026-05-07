<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Install MonkeysCMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #0f172a; --bg-card: #1e293b; --bg-surface: #334155;
      --text: #e2e8f0; --text-muted: #94a3b8; --heading: #f8fafc;
      --primary: #6366f1; --primary-hover: #4f46e5; --primary-light: rgba(99,102,241,.12);
      --success: #22c55e; --danger: #ef4444; --warning: #f59e0b;
      --border: #475569; --radius: 12px; --radius-sm: 8px;
    }
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; }
    .installer { max-width:560px; width:100%; margin:2rem; }
    .installer__header { text-align:center; margin-bottom:2.5rem; }
    .installer__logo { font-size:3rem; margin-bottom:0.5rem; }
    .installer__title { font-size:1.75rem; font-weight:800; color:var(--heading); }
    .installer__subtitle { font-size:0.9rem; color:var(--text-muted); margin-top:0.25rem; }
    .card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; margin-bottom:1.5rem; }
    .card__header { padding:1rem 1.25rem; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
    .card__title { font-weight:600; font-size:0.95rem; color:var(--heading); }
    .card__body { padding:1.25rem; }
    .step-indicator { display:flex; gap:0.5rem; justify-content:center; margin-bottom:2rem; }
    .step-dot { width:10px; height:10px; border-radius:50%; background:var(--bg-surface); transition:all 300ms; }
    .step-dot.active { background:var(--primary); width:24px; border-radius:5px; }
    .step-dot.done { background:var(--success); }
    .step-panel { display:none; }
    .step-panel.visible { display:block; }
    .form-group { margin-bottom:1rem; }
    .form-label { display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.35rem; color:var(--text); }
    .form-input, .form-select { width:100%; padding:0.6rem 0.75rem; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius-sm); color:var(--text); font-size:0.9rem; font-family:inherit; outline:none; transition:border 200ms; }
    .form-input:focus { border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-light); }
    .btn { display:inline-flex; align-items:center; justify-content:center; gap:0.5rem; padding:0.65rem 1.5rem; border:none; border-radius:var(--radius-sm); font-size:0.9rem; font-weight:600; cursor:pointer; transition:all 200ms; font-family:inherit; text-decoration:none; }
    .btn-primary { background:var(--primary); color:#fff; }
    .btn-primary:hover { background:var(--primary-hover); }
    .btn-primary:disabled { opacity:0.5; cursor:not-allowed; }
    .btn-secondary { background:var(--bg-surface); color:var(--text); }
    .actions { display:flex; justify-content:space-between; margin-top:1.5rem; }
    .check-item { display:flex; align-items:center; gap:0.75rem; padding:0.5rem 0; font-size:0.9rem; }
    .check-item__icon { font-size:1.1rem; }
    .check-item__value { margin-left:auto; font-size:0.8rem; color:var(--text-muted); }
    .msg { padding:0.75rem 1rem; border-radius:var(--radius-sm); font-size:0.85rem; margin-top:0.75rem; display:none; }
    .msg.show { display:block; }
    .msg--success { background:rgba(34,197,94,.1); border:1px solid var(--success); color:var(--success); }
    .msg--error { background:rgba(239,68,68,.1); border:1px solid var(--danger); color:var(--danger); }
    .migration-row { display:flex; align-items:center; gap:0.5rem; padding:0.5rem 0; border-bottom:1px solid rgba(255,255,255,.05); font-size:0.85rem; }
    .migration-row:last-child { border:none; }
    .spinner { display:inline-block; width:16px; height:16px; border:2px solid var(--border); border-top-color:var(--primary); border-radius:50%; animation:spin 600ms linear infinite; }
    @keyframes spin { to { transform:rotate(360deg); } }
    .complete-check { font-size:4rem; text-align:center; margin:1.5rem 0; }
    .form-grid { display:grid; grid-template-columns:1fr 100px; gap:0.75rem; }
  </style>
</head>
<body>

<div class="installer" id="installer-app">
  <div class="installer__header">
    <div class="installer__logo">🐒</div>
    <h1 class="installer__title">MonkeysCMS</h1>
    <p class="installer__subtitle">Installation Wizard</p>
  </div>

  <div class="step-indicator" id="step-dots">
    <div class="step-dot active" data-step="1"></div>
    <div class="step-dot" data-step="2"></div>
    <div class="step-dot" data-step="3"></div>
    <div class="step-dot" data-step="4"></div>
    <div class="step-dot" data-step="5"></div>
    <div class="step-dot" data-step="6"></div>
  </div>

  {{-- Step 1: Requirements --}}
  <div class="step-panel visible" id="step-1">
    <div class="card">
      <div class="card__header"><span class="card__title">1. System Requirements</span></div>
      <div class="card__body">
        @foreach($requirements as $req)
        <div class="check-item">
          <span class="check-item__icon">{!! $req['passed'] ? '✅' : '❌' !!}</span>
          <span>{{ $req['name'] }}</span>
          <span class="check-item__value">{{ $req['value'] }}</span>
        </div>
        @endforeach
      </div>
    </div>
    <div class="actions">
      <span></span>
      <button class="btn btn-primary" onclick="goStep(2)" {!! in_array(false, array_column($requirements, 'passed')) ? 'disabled' : '' !!}>Continue →</button>
    </div>
  </div>

  {{-- Step 2: Database --}}
  <div class="step-panel" id="step-2">
    <div class="card">
      <div class="card__header"><span class="card__title">2. Database Configuration</span></div>
      <div class="card__body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Host</label>
            <input class="form-input" id="db-host" value="db" placeholder="127.0.0.1">
          </div>
          <div class="form-group">
            <label class="form-label">Port</label>
            <input class="form-input" id="db-port" value="3306" placeholder="3306">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Database Name</label>
          <input class="form-input" id="db-name" value="monkeyscms" placeholder="monkeyscms">
        </div>
        <div class="form-group">
          <label class="form-label">Username</label>
          <input class="form-input" id="db-user" value="" placeholder="root">
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input class="form-input" type="password" id="db-pass" value="">
        </div>
        <div class="msg" id="db-msg"></div>
      </div>
    </div>
    <div class="actions">
      <button class="btn btn-secondary" onclick="goStep(1)">← Back</button>
      <button class="btn btn-primary" id="btn-test-db" onclick="testDatabase()">Test & Save →</button>
    </div>
  </div>

  {{-- Step 3: Migrations --}}
  <div class="step-panel" id="step-3">
    <div class="card">
      <div class="card__header"><span class="card__title">3. Database Schema</span></div>
      <div class="card__body">
        <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom:1rem;">
          The installer will create all required tables from MLC schema definitions.
        </p>
        <div id="migration-list"></div>
        <div class="msg" id="migrate-msg"></div>
      </div>
    </div>
    <div class="actions">
      <button class="btn btn-secondary" onclick="goStep(2)">← Back</button>
      <button class="btn btn-primary" id="btn-migrate" onclick="runMigrations()">Run Migrations →</button>
    </div>
  </div>

  {{-- Step 4: Admin User --}}
  <div class="step-panel" id="step-4">
    <div class="card">
      <div class="card__header"><span class="card__title">4. Admin Account</span></div>
      <div class="card__body">
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input class="form-input" id="admin-name" placeholder="Admin">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input class="form-input" type="email" id="admin-email" placeholder="admin@example.com">
        </div>
        <div class="form-group">
          <label class="form-label">Password (min 8 characters)</label>
          <input class="form-input" type="password" id="admin-password">
        </div>
        <div class="msg" id="admin-msg"></div>
      </div>
    </div>
    <div class="actions">
      <button class="btn btn-secondary" onclick="goStep(3)">← Back</button>
      <button class="btn btn-primary" id="btn-admin" onclick="createAdmin()">Create Admin →</button>
    </div>
  </div>

  {{-- Step 5: Site Config --}}
  <div class="step-panel" id="step-5">
    <div class="card">
      <div class="card__header"><span class="card__title">5. Site Configuration</span></div>
      <div class="card__body">
        <div class="form-group">
          <label class="form-label">Site Name</label>
          <input class="form-input" id="site-name" value="MonkeysCMS" placeholder="My Website">
        </div>
        <div class="form-group">
          <label class="form-label">Tagline</label>
          <input class="form-input" id="site-tagline" placeholder="A modern website">
        </div>
        <div class="form-group">
          <label class="form-label">Site URL</label>
          <input class="form-input" id="site-url" placeholder="https://example.com">
        </div>
        <div class="form-group">
          <label class="form-label">Contact Email</label>
          <input class="form-input" type="email" id="site-email">
        </div>
        <div class="form-group">
          <label class="form-label">Timezone</label>
          <select class="form-select" id="site-timezone">
            <option value="UTC">UTC</option>
            <option value="America/New_York">Eastern (US)</option>
            <option value="America/Chicago">Central (US)</option>
            <option value="America/Denver">Mountain (US)</option>
            <option value="America/Los_Angeles">Pacific (US)</option>
            <option value="America/Mexico_City">Mexico City</option>
            <option value="Europe/London">London</option>
            <option value="Europe/Berlin">Berlin</option>
            <option value="Europe/Madrid">Madrid</option>
            <option value="Asia/Tokyo">Tokyo</option>
          </select>
        </div>
      </div>
    </div>
    <div class="actions">
      <button class="btn btn-secondary" onclick="goStep(4)">← Back</button>
      <button class="btn btn-primary" id="btn-site" onclick="saveSiteConfig()">Finish →</button>
    </div>
  </div>

  {{-- Step 6: Complete --}}
  <div class="step-panel" id="step-6">
    <div class="card">
      <div class="card__body" style="text-align:center; padding:2.5rem;">
        <div class="complete-check">🎉</div>
        <h2 style="color:var(--heading); font-size:1.5rem; margin-bottom:0.5rem;">Installation Complete!</h2>
        <p style="color:var(--text-muted); margin-bottom:1.5rem;">MonkeysCMS has been installed successfully.</p>
        <div style="display:flex; gap:0.75rem; justify-content:center;">
          <a href="/admin" class="btn btn-primary">Go to Admin →</a>
          <a href="/" class="btn btn-secondary">View Site</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // ── Installer State ─────────────────────────────────────────────────────
  let currentStep = 1;

  function goStep(n) {
    document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('visible'));
    document.getElementById('step-' + n).classList.add('visible');
    document.querySelectorAll('.step-dot').forEach(d => {
      const s = parseInt(d.dataset.step);
      d.classList.toggle('active', s === n);
      d.classList.toggle('done', s < n);
    });
    currentStep = n;
  }

  function showMsg(id, text, success) {
    const el = document.getElementById(id);
    el.textContent = text;
    el.className = 'msg show ' + (success ? 'msg--success' : 'msg--error');
  }

  async function post(path, data) {
    const res = await fetch('/install' + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(data),
    });
    return res.json();
  }

  // ── Step 2: Database ──────────────────────────────────────────────────
  async function testDatabase() {
    const btn = document.getElementById('btn-test-db');
    btn.disabled = true; btn.textContent = 'Testing...';
    try {
      const data = await post('/database', {
        db_host: document.getElementById('db-host').value,
        db_port: document.getElementById('db-port').value,
        db_name: document.getElementById('db-name').value,
        db_user: document.getElementById('db-user').value,
        db_pass: document.getElementById('db-pass').value,
      });
      if (data.success) {
        showMsg('db-msg', '✅ Connected!', true);
        setTimeout(() => goStep(3), 500);
      } else {
        showMsg('db-msg', data.error || 'Connection failed', false);
      }
    } catch (e) { showMsg('db-msg', 'Connection failed', false); }
    btn.disabled = false; btn.textContent = 'Test & Save →';
  }

  // ── Step 3: Migrations ────────────────────────────────────────────────
  async function runMigrations() {
    const btn = document.getElementById('btn-migrate');
    btn.disabled = true; btn.textContent = 'Running...';
    try {
      const data = await post('/migrate', {});
      const list = document.getElementById('migration-list');
      list.innerHTML = '';
      (data.executed || []).forEach(m => {
        list.innerHTML += '<div class="migration-row">✅ ' + m.id + ' <span style="margin-left:auto;color:var(--text-muted);font-size:0.8rem">' + (m.time_ms || '') + 'ms</span></div>';
      });
      if (data.success) {
        showMsg('migrate-msg', '✅ All migrations completed!', true);
        setTimeout(() => goStep(4), 800);
      } else {
        showMsg('migrate-msg', (data.errors && data.errors[0] && data.errors[0].error) || 'Migration failed', false);
      }
    } catch (e) { showMsg('migrate-msg', 'Migration failed', false); }
    btn.disabled = false; btn.textContent = 'Run Migrations →';
  }

  // ── Step 4: Admin User ────────────────────────────────────────────────
  async function createAdmin() {
    const btn = document.getElementById('btn-admin');
    btn.disabled = true; btn.textContent = 'Creating...';
    try {
      const data = await post('/admin-user', {
        name: document.getElementById('admin-name').value,
        email: document.getElementById('admin-email').value,
        password: document.getElementById('admin-password').value,
      });
      if (data.success) {
        showMsg('admin-msg', '✅ Admin created!', true);
        setTimeout(() => goStep(5), 500);
      } else { showMsg('admin-msg', data.error || 'Failed', false); }
    } catch (e) { showMsg('admin-msg', 'Failed', false); }
    btn.disabled = false; btn.textContent = 'Create Admin →';
  }

  // ── Step 5: Site Config ───────────────────────────────────────────────
  async function saveSiteConfig() {
    const btn = document.getElementById('btn-site');
    btn.disabled = true; btn.textContent = 'Saving...';
    try {
      const data = await post('/configure', {
        site_name: document.getElementById('site-name').value,
        site_tagline: document.getElementById('site-tagline').value,
        site_url: document.getElementById('site-url').value || window.location.origin,
        site_email: document.getElementById('site-email').value,
        timezone: document.getElementById('site-timezone').value,
      });
      if (data.success) goStep(6);
    } catch (e) { console.error(e); }
    btn.disabled = false; btn.textContent = 'Finish →';
  }

  // Set site URL default
  document.getElementById('site-url').value = window.location.origin;
</script>
</body>
</html>
