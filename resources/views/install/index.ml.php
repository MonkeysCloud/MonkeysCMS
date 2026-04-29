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
    .form-group { margin-bottom:1rem; }
    .form-label { display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.35rem; color:var(--text); }
    .form-input, .form-select { width:100%; padding:0.6rem 0.75rem; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius-sm); color:var(--text); font-size:0.9rem; font-family:inherit; outline:none; transition:border 200ms; }
    .form-input:focus { border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-light); }
    .btn { display:inline-flex; align-items:center; justify-content:center; gap:0.5rem; padding:0.65rem 1.5rem; border:none; border-radius:var(--radius-sm); font-size:0.9rem; font-weight:600; cursor:pointer; transition:all 200ms; font-family:inherit; }
    .btn-primary { background:var(--primary); color:#fff; }
    .btn-primary:hover { background:var(--primary-hover); }
    .btn-primary:disabled { opacity:0.5; cursor:not-allowed; }
    .btn-secondary { background:var(--bg-surface); color:var(--text); }
    .actions { display:flex; justify-content:space-between; margin-top:1.5rem; }
    .check-item { display:flex; align-items:center; gap:0.75rem; padding:0.5rem 0; font-size:0.9rem; }
    .check-item__icon { font-size:1.1rem; }
    .check-item__value { margin-left:auto; font-size:0.8rem; color:var(--text-muted); }
    .msg { padding:0.75rem 1rem; border-radius:var(--radius-sm); font-size:0.85rem; margin-top:0.75rem; }
    .msg--success { background:rgba(34,197,94,.1); border:1px solid var(--success); color:var(--success); }
    .msg--error { background:rgba(239,68,68,.1); border:1px solid var(--danger); color:var(--danger); }
    .migration-row { display:flex; align-items:center; gap:0.5rem; padding:0.5rem 0; border-bottom:1px solid rgba(255,255,255,.05); font-size:0.85rem; }
    .migration-row:last-child { border:none; }
    .spinner { display:inline-block; width:16px; height:16px; border:2px solid var(--border); border-top-color:var(--primary); border-radius:50%; animation:spin 600ms linear infinite; }
    @keyframes spin { to { transform:rotate(360deg); } }
    .complete-check { font-size:4rem; text-align:center; margin:1.5rem 0; }
  </style>
</head>
<body>

<div class="installer" id="installer-app">
  <div class="installer__header">
    <div class="installer__logo">🐒</div>
    <h1 class="installer__title">MonkeysCMS</h1>
    <p class="installer__subtitle">Installation Wizard</p>
  </div>

  {{-- Step Indicator --}}
  <div class="step-indicator">
    <div class="step-dot" :class="{ active: step === 1, done: step > 1 }"></div>
    <div class="step-dot" :class="{ active: step === 2, done: step > 2 }"></div>
    <div class="step-dot" :class="{ active: step === 3, done: step > 3 }"></div>
    <div class="step-dot" :class="{ active: step === 4, done: step > 4 }"></div>
    <div class="step-dot" :class="{ active: step === 5, done: step > 5 }"></div>
    <div class="step-dot" :class="{ active: step === 6, done: step > 6 }"></div>
  </div>

  {{-- Step 1: Requirements --}}
  <div $m-show="step === 1">
    <div class="card">
      <div class="card__header"><span class="card__title">1. System Requirements</span></div>
      <div class="card__body">
        @foreach($requirements as $req)
        <div class="check-item">
          <span class="check-item__icon">{{ $req['passed'] ? '✅' : '❌' }}</span>
          <span>{{ $req['name'] }}</span>
          <span class="check-item__value">{{ $req['value'] }}</span>
        </div>
        @endforeach
      </div>
    </div>
    <div class="actions">
      <span></span>
      <button class="btn btn-primary" $m-on:click="step = 2" @if(in_array(false, array_column($requirements, 'passed'))) disabled @endif>Continue →</button>
    </div>
  </div>

  {{-- Step 2: Database --}}
  <div $m-show="step === 2">
    <div class="card">
      <div class="card__header"><span class="card__title">2. Database Configuration</span></div>
      <div class="card__body">
        <div style="display:grid; grid-template-columns:1fr 100px; gap:0.75rem;">
          <div class="form-group">
            <label class="form-label">Host</label>
            <input class="form-input" $m-model="db.host" placeholder="127.0.0.1">
          </div>
          <div class="form-group">
            <label class="form-label">Port</label>
            <input class="form-input" $m-model="db.port" placeholder="3306">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Database Name</label>
          <input class="form-input" $m-model="db.name" placeholder="monkeyscms">
        </div>
        <div class="form-group">
          <label class="form-label">Username</label>
          <input class="form-input" $m-model="db.user" placeholder="root">
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input class="form-input" type="password" $m-model="db.pass">
        </div>
        <div $m-show="dbMessage" class="msg" :class="dbSuccess ? 'msg--success' : 'msg--error'" $m-text="dbMessage"></div>
      </div>
    </div>
    <div class="actions">
      <button class="btn btn-secondary" $m-on:click="step = 1">← Back</button>
      <button class="btn btn-primary" $m-on:click="testDatabase()" :disabled="loading">
        <span $m-show="loading" class="spinner"></span>
        <span $m-text="loading ? 'Testing...' : 'Test & Save →'"></span>
      </button>
    </div>
  </div>

  {{-- Step 3: Migrations --}}
  <div $m-show="step === 3">
    <div class="card">
      <div class="card__header"><span class="card__title">3. Database Schema</span></div>
      <div class="card__body">
        <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom:1rem;">
          The installer will create all required tables from MLC schema definitions.
        </p>
        <div $m-show="migrations.length > 0">
          <template $m-for="m in migrations">
            <div class="migration-row">
              <span $m-text="m.status === 'done' ? '✅' : m.status === 'error' ? '❌' : '⏳'"></span>
              <span $m-text="m.id"></span>
              <span style="margin-left:auto; color:var(--text-muted); font-size:0.8rem;" $m-text="m.time_ms ? m.time_ms + 'ms' : ''"></span>
            </div>
          </template>
        </div>
        <div $m-show="migrateMessage" class="msg" :class="migrateSuccess ? 'msg--success' : 'msg--error'" $m-text="migrateMessage"></div>
      </div>
    </div>
    <div class="actions">
      <button class="btn btn-secondary" $m-on:click="step = 2">← Back</button>
      <button class="btn btn-primary" $m-on:click="runMigrations()" :disabled="loading || migrateSuccess">
        <span $m-show="loading" class="spinner"></span>
        <span $m-text="migrateSuccess ? 'Done ✅' : loading ? 'Running...' : 'Run Migrations →'"></span>
      </button>
    </div>
  </div>

  {{-- Step 4: Admin User --}}
  <div $m-show="step === 4">
    <div class="card">
      <div class="card__header"><span class="card__title">4. Admin Account</span></div>
      <div class="card__body">
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input class="form-input" $m-model="admin.name" placeholder="Admin">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input class="form-input" type="email" $m-model="admin.email" placeholder="admin@example.com">
        </div>
        <div class="form-group">
          <label class="form-label">Password (min 8 characters)</label>
          <input class="form-input" type="password" $m-model="admin.password">
        </div>
        <div $m-show="adminMessage" class="msg" :class="adminSuccess ? 'msg--success' : 'msg--error'" $m-text="adminMessage"></div>
      </div>
    </div>
    <div class="actions">
      <button class="btn btn-secondary" $m-on:click="step = 3">← Back</button>
      <button class="btn btn-primary" $m-on:click="createAdmin()" :disabled="loading">
        <span $m-text="loading ? 'Creating...' : 'Create Admin →'"></span>
      </button>
    </div>
  </div>

  {{-- Step 5: Site Config --}}
  <div $m-show="step === 5">
    <div class="card">
      <div class="card__header"><span class="card__title">5. Site Configuration</span></div>
      <div class="card__body">
        <div class="form-group">
          <label class="form-label">Site Name</label>
          <input class="form-input" $m-model="site.name" placeholder="My Website">
        </div>
        <div class="form-group">
          <label class="form-label">Tagline</label>
          <input class="form-input" $m-model="site.tagline" placeholder="A modern website">
        </div>
        <div class="form-group">
          <label class="form-label">Site URL</label>
          <input class="form-input" $m-model="site.url" placeholder="https://example.com">
        </div>
        <div class="form-group">
          <label class="form-label">Contact Email</label>
          <input class="form-input" type="email" $m-model="site.email">
        </div>
        <div class="form-group">
          <label class="form-label">Timezone</label>
          <select class="form-select" $m-model="site.timezone">
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
      <button class="btn btn-secondary" $m-on:click="step = 4">← Back</button>
      <button class="btn btn-primary" $m-on:click="saveSiteConfig()" :disabled="loading">
        <span $m-text="loading ? 'Saving...' : 'Finish →'"></span>
      </button>
    </div>
  </div>

  {{-- Step 6: Complete --}}
  <div $m-show="step === 6">
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

<script type="module">
import { createApp, createClient } from 'monkeysjs';

const api = createClient({ baseURL: '/install', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } });

const app = createApp({
  step: {{ $step ?? 1 }},
  loading: false,

  db: { host: '127.0.0.1', port: '3306', name: '', user: '', pass: '' },
  dbMessage: '', dbSuccess: false,

  migrations: [], migrateMessage: '', migrateSuccess: false,

  admin: { name: '', email: '', password: '' },
  adminMessage: '', adminSuccess: false,

  site: { name: 'MonkeysCMS', tagline: '', url: window.location.origin, email: '', timezone: 'UTC' },

  async testDatabase() {
    this.loading = true; this.dbMessage = '';
    try {
      const res = await api.post('/database', JSON.stringify({ db_host: this.db.host, db_port: this.db.port, db_name: this.db.name, db_user: this.db.user, db_pass: this.db.pass }));
      if (res.data.success) { this.dbSuccess = true; this.dbMessage = '✅ Connected!'; setTimeout(() => { this.step = 3; }, 500); }
      else { this.dbMessage = res.data.error || 'Connection failed'; }
    } catch (e) { this.dbMessage = e.response?.data?.error || 'Connection failed'; }
    this.loading = false;
  },

  async runMigrations() {
    this.loading = true; this.migrateMessage = '';
    try {
      const res = await api.post('/migrate');
      if (res.data.success) {
        this.migrateSuccess = true;
        this.migrations = (res.data.executed || []).map(m => ({ ...m, status: 'done' }));
        this.migrateMessage = '✅ All migrations completed!';
        setTimeout(() => { this.step = 4; }, 800);
      } else {
        this.migrations = (res.data.executed || []).map(m => ({ ...m, status: 'done' }));
        if (res.data.errors?.length) this.migrations.push(...res.data.errors.map(e => ({ ...e, status: 'error' })));
        this.migrateMessage = res.data.errors?.[0]?.error || 'Migration failed';
      }
    } catch (e) { this.migrateMessage = e.response?.data?.error || 'Migration failed'; }
    this.loading = false;
  },

  async createAdmin() {
    this.loading = true; this.adminMessage = '';
    try {
      const res = await api.post('/admin-user', JSON.stringify(this.admin));
      if (res.data.success) { this.adminSuccess = true; this.adminMessage = '✅ Admin created!'; setTimeout(() => { this.step = 5; }, 500); }
      else { this.adminMessage = res.data.error; }
    } catch (e) { this.adminMessage = e.response?.data?.error || 'Failed'; }
    this.loading = false;
  },

  async saveSiteConfig() {
    this.loading = true;
    try {
      const res = await api.post('/configure', JSON.stringify({ site_name: this.site.name, site_tagline: this.site.tagline, site_url: this.site.url, site_email: this.site.email, timezone: this.site.timezone }));
      if (res.data.success) this.step = 6;
    } catch (e) { console.error(e); }
    this.loading = false;
  },
});

app.mount('#installer-app');
</script>
</body>
</html>
