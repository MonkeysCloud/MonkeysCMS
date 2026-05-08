@extends('layouts.admin')
@section('title', 'Appearance')

@section('toolbar_actions')
<a href="/admin/appearance/site-identity" class="btn btn--sm btn--ghost">
  <i data-lucide="image" class="w-4 h-4"></i> Site Identity
</a>
<a href="/admin/appearance/editor" class="btn btn--sm btn--ghost">
  <i data-lucide="code-2" class="w-4 h-4"></i> Theme Editor
</a>
@endsection

@section('content')
<div class="admin-content appearance-page">

  @if(!empty($flashSuccess))
  <div class="alert alert--success mb-4">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    {{ $flashSuccess }}
  </div>
  @endif

  @if(!empty($flashError))
  <div class="alert alert--error mb-4">
    <i data-lucide="alert-circle" class="w-4 h-4"></i>
    {{ $flashError }}
  </div>
  @endif

  {{-- Frontend Themes --}}
  <div class="appearance-section">
    <div class="appearance-section__header">
      <h2 class="appearance-section__title">
        <i data-lucide="monitor" class="w-5 h-5"></i>
        Frontend Themes
      </h2>
      <span class="appearance-section__count">{{ count($frontendThemes ?? []) }} theme{{ count($frontendThemes ?? []) !== 1 ? 's' : '' }}</span>
    </div>

    <div class="theme-grid">
      @foreach($frontendThemes ?? [] as $theme)
      <div class="theme-card {{ $theme['is_active'] ? 'theme-card--active' : '' }}" data-theme="{{ $theme['name'] }}">
        <div class="theme-card__preview">
          @if($theme['screenshot'])
          <img src="{{ $theme['screenshot'] }}" alt="{{ $theme['label'] }}" loading="lazy">
          @else
          <div class="theme-card__placeholder" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i data-lucide="palette" class="w-10 h-10"></i>
            <span>{{ $theme['label'] }}</span>
          </div>
          @endif
          @if($theme['is_active'])
          <div class="theme-card__active-badge">
            <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Active
          </div>
          @endif
        </div>
        <div class="theme-card__body">
          <div class="theme-card__header">
            <h3 class="theme-card__name">{{ $theme['label'] }}</h3>
            <span class="theme-card__tier theme-card__tier--{{ $theme['tier'] }}">{{ ucfirst($theme['tier']) }}</span>
          </div>
          <p class="theme-card__desc">{{ $theme['description'] }}</p>
          <div class="theme-card__meta">
            <span class="theme-card__version">v{{ $theme['version'] }}</span>
            @if($theme['parent_label'])
            <span class="theme-card__parent">
              <i data-lucide="git-branch" class="w-3 h-3"></i> {{ $theme['parent_label'] }}
            </span>
            @endif
          </div>
          <div class="theme-card__actions">
            @if($theme['is_active'])
            <button class="btn btn--sm btn--primary" disabled>
              <i data-lucide="check" class="w-3.5 h-3.5"></i> Active
            </button>
            @else
            <button class="btn btn--sm btn--primary theme-activate-btn"
                    data-theme="{{ $theme['name'] }}" data-type="frontend">
              <i data-lucide="zap" class="w-3.5 h-3.5"></i> Activate
            </button>
            @endif
            <a href="/admin/appearance/editor?theme={{ $theme['name'] }}" class="btn btn--sm btn--ghost">
              <i data-lucide="code-2" class="w-3.5 h-3.5"></i> Editor
            </a>
            @if($theme['can_delete'] && !$theme['is_active'])
            <button class="btn btn--sm btn--ghost text-danger theme-delete-btn"
                    data-theme="{{ $theme['name'] }}" data-label="{{ $theme['label'] }}">
              <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            </button>
            @endif
          </div>
        </div>
      </div>
      @endforeach
    </div>
  </div>

  {{-- Admin Themes --}}
  <div class="appearance-section">
    <div class="appearance-section__header">
      <h2 class="appearance-section__title">
        <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
        Admin Themes
      </h2>
      <span class="appearance-section__count">{{ count($adminThemes ?? []) }} theme{{ count($adminThemes ?? []) !== 1 ? 's' : '' }}</span>
    </div>

    <div class="theme-grid">
      @foreach($adminThemes ?? [] as $theme)
      <div class="theme-card {{ $theme['is_active'] ? 'theme-card--active' : '' }}" data-theme="{{ $theme['name'] }}">
        <div class="theme-card__preview">
          @if($theme['screenshot'])
          <img src="{{ $theme['screenshot'] }}" alt="{{ $theme['label'] }}" loading="lazy">
          @else
          <div class="theme-card__placeholder" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);">
            <i data-lucide="layout-dashboard" class="w-10 h-10"></i>
            <span>{{ $theme['label'] }}</span>
          </div>
          @endif
          @if($theme['is_active'])
          <div class="theme-card__active-badge">
            <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Active
          </div>
          @endif
        </div>
        <div class="theme-card__body">
          <div class="theme-card__header">
            <h3 class="theme-card__name">{{ $theme['label'] }}</h3>
            <span class="theme-card__tier theme-card__tier--{{ $theme['tier'] }}">{{ ucfirst($theme['tier']) }}</span>
          </div>
          <p class="theme-card__desc">{{ $theme['description'] }}</p>
          <div class="theme-card__meta">
            <span class="theme-card__version">v{{ $theme['version'] }}</span>
            @if($theme['parent_label'])
            <span class="theme-card__parent">
              <i data-lucide="git-branch" class="w-3 h-3"></i> {{ $theme['parent_label'] }}
            </span>
            @endif
          </div>
          <div class="theme-card__actions">
            @if($theme['is_active'])
            <button class="btn btn--sm btn--primary" disabled>
              <i data-lucide="check" class="w-3.5 h-3.5"></i> Active
            </button>
            @else
            <button class="btn btn--sm btn--primary theme-activate-btn"
                    data-theme="{{ $theme['name'] }}" data-type="admin">
              <i data-lucide="zap" class="w-3.5 h-3.5"></i> Activate
            </button>
            @endif
            <a href="/admin/appearance/editor?theme={{ $theme['name'] }}" class="btn btn--sm btn--ghost">
              <i data-lucide="code-2" class="w-3.5 h-3.5"></i> Editor
            </a>
            @if($theme['can_delete'] && !$theme['is_active'])
            <button class="btn btn--sm btn--ghost text-danger theme-delete-btn"
                    data-theme="{{ $theme['name'] }}" data-label="{{ $theme['label'] }}">
              <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            </button>
            @endif
          </div>
        </div>
      </div>
      @endforeach
    </div>
  </div>

</div>

@push('head')
<style>
.appearance-page { padding: 1.5rem 2rem; max-width: 1400px; }

.appearance-section { margin-bottom: 2.5rem; }
.appearance-section__header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 1.25rem; padding-bottom: 0.75rem;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.appearance-section__title {
  display: flex; align-items: center; gap: 0.5rem;
  font-size: 1.1rem; font-weight: 600; color: #e2e8f0;
}
.appearance-section__count {
  font-size: 0.75rem; color: #64748b;
  background: rgba(100,116,139,0.1); padding: 0.2rem 0.6rem;
  border-radius: 10px;
}

.theme-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1.25rem;
}

.theme-card {
  background: rgba(15,17,28,0.6);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 14px; overflow: hidden;
  transition: all 0.25s ease;
}
.theme-card:hover {
  border-color: rgba(99,102,241,0.3);
  box-shadow: 0 8px 30px rgba(0,0,0,0.2);
  transform: translateY(-2px);
}
.theme-card--active {
  border-color: rgba(99,102,241,0.4);
  box-shadow: 0 0 0 1px rgba(99,102,241,0.2), 0 8px 30px rgba(99,102,241,0.1);
}

.theme-card__preview {
  position: relative; height: 200px; overflow: hidden;
  background: rgba(15,17,28,0.8);
}
.theme-card__preview img {
  width: 100%; height: 100%;
  object-fit: cover; object-position: top;
  transition: transform 0.3s ease;
}
.theme-card:hover .theme-card__preview img { transform: scale(1.03); }
.theme-card__placeholder {
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  height: 100%; gap: 0.75rem;
  color: rgba(255,255,255,0.5);
  font-size: 0.85rem; font-weight: 500;
}
.theme-card__active-badge {
  position: absolute; top: 0.75rem; right: 0.75rem;
  display: flex; align-items: center; gap: 0.3rem;
  background: rgba(99,102,241,0.9);
  color: #fff; font-size: 0.7rem; font-weight: 600;
  padding: 0.25rem 0.6rem; border-radius: 20px;
  backdrop-filter: blur(8px);
}

.theme-card__body { padding: 1rem 1.25rem 1.25rem; }
.theme-card__header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 0.5rem;
}
.theme-card__name { font-size: 0.95rem; font-weight: 600; color: #e2e8f0; }
.theme-card__tier {
  font-size: 0.6rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.05em;
  padding: 0.15rem 0.45rem; border-radius: 4px;
}
.theme-card__tier--core { background: rgba(59,130,246,0.15); color: #60a5fa; }
.theme-card__tier--contrib { background: rgba(168,85,247,0.15); color: #c084fc; }
.theme-card__tier--custom { background: rgba(34,197,94,0.15); color: #4ade80; }

.theme-card__desc {
  font-size: 0.78rem; color: #94a3b8; line-height: 1.5;
  margin-bottom: 0.75rem;
  display: -webkit-box; -webkit-line-clamp: 2;
  -webkit-box-orient: vertical; overflow: hidden;
}
.theme-card__meta {
  display: flex; align-items: center; gap: 0.75rem;
  margin-bottom: 0.85rem;
}
.theme-card__version {
  font-size: 0.7rem; color: #64748b;
  background: rgba(100,116,139,0.1);
  padding: 0.1rem 0.4rem; border-radius: 4px;
}
.theme-card__parent {
  display: flex; align-items: center; gap: 0.25rem;
  font-size: 0.7rem; color: #a78bfa;
}
.theme-card__actions {
  display: flex; align-items: center; gap: 0.5rem;
  padding-top: 0.75rem;
  border-top: 1px solid rgba(255,255,255,0.04);
}

.appearance-toast {
  position: fixed; bottom: 2rem; right: 2rem; z-index: 99999;
  padding: 0.75rem 1.25rem; border-radius: 10px;
  font-size: 0.82rem; font-weight: 500;
  background: rgba(99,102,241,0.95); color: #fff;
  box-shadow: 0 8px 30px rgba(0,0,0,0.3);
  animation: toastSlideIn 0.3s ease;
}
.appearance-toast--error { background: rgba(239,68,68,0.95); }
@keyframes toastSlideIn {
  from { transform: translateY(20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.confirm-overlay {
  position: fixed; inset: 0; z-index: 99998;
  background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
}
.confirm-modal {
  background: rgba(20,22,38,0.98); border: 1px solid rgba(255,255,255,0.08);
  border-radius: 16px; padding: 2rem; width: 100%; max-width: 420px;
  box-shadow: 0 24px 60px rgba(0,0,0,0.5);
}
.confirm-modal__title {
  font-size: 1rem; font-weight: 600; color: #e2e8f0; margin-bottom: 0.75rem;
}
.confirm-modal__message {
  font-size: 0.82rem; color: #94a3b8; line-height: 1.6; margin-bottom: 1.5rem;
}
.confirm-modal__footer {
  display: flex; justify-content: flex-end; gap: 0.75rem;
}

.spin { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.theme-activate-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const theme = btn.dataset.theme;
      const type = btn.dataset.type;
      btn.disabled = true;
      btn.innerHTML = '<i data-lucide="loader" class="w-3.5 h-3.5 spin"></i> Activating...';
      if (window.lucide) lucide.createIcons();

      try {
        const resp = await CMS.fetch('/admin/appearance/activate', {
          method: 'POST',
          body: JSON.stringify({ theme, type }),
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.error || 'Failed');
        showToast(data.message || 'Theme activated!');
        setTimeout(() => window.location.reload(), 800);
      } catch (err) {
        showToast(err.message, true);
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="zap" class="w-3.5 h-3.5"></i> Activate';
        if (window.lucide) lucide.createIcons();
      }
    });
  });

  document.querySelectorAll('.theme-delete-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const theme = btn.dataset.theme;
      const label = btn.dataset.label;

      const confirmed = await showConfirm(
        'Delete Theme',
        `Are you sure you want to permanently delete "${label}"? This will remove all theme files and cannot be undone.`
      );
      if (!confirmed) return;

      try {
        const resp = await CMS.fetch('/admin/appearance/delete', {
          method: 'POST',
          body: JSON.stringify({ theme }),
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.error || 'Failed');
        showToast(data.message || 'Theme deleted!');
        const card = btn.closest('.theme-card');
        if (card) {
          card.style.transition = 'opacity 0.3s, transform 0.3s';
          card.style.opacity = '0';
          card.style.transform = 'scale(0.95)';
          setTimeout(() => card.remove(), 300);
        }
      } catch (err) {
        showToast(err.message, true);
      }
    });
  });

  function showToast(message, isError = false) {
    const toast = document.createElement('div');
    toast.className = 'appearance-toast' + (isError ? ' appearance-toast--error' : '');
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(20px)';
      toast.style.transition = 'all 0.3s';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  function showConfirm(title, message) {
    return new Promise(resolve => {
      const overlay = document.createElement('div');
      overlay.className = 'confirm-overlay';
      overlay.innerHTML = `
        <div class="confirm-modal">
          <div class="confirm-modal__title">${title}</div>
          <p class="confirm-modal__message">${message}</p>
          <div class="confirm-modal__footer">
            <button class="btn btn--sm btn--ghost" data-cancel>Cancel</button>
            <button class="btn btn--sm btn--danger-solid" data-ok>Delete</button>
          </div>
        </div>
      `;
      const close = (result) => { overlay.remove(); resolve(result); };
      overlay.querySelector('[data-cancel]').addEventListener('click', () => close(false));
      overlay.querySelector('[data-ok]').addEventListener('click', () => close(true));
      overlay.addEventListener('click', e => { if (e.target === overlay) close(false); });
      document.body.appendChild(overlay);
    });
  }
});
</script>
@endpush

@endsection
