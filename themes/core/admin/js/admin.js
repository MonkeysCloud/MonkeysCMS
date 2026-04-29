/**
 * MonkeysCMS Admin Theme — JS
 *
 * Theme-specific enhancements (keyboard shortcuts, accessibility, etc.)
 */

document.addEventListener('DOMContentLoaded', () => {
  // ── Keyboard Shortcuts ──────────────────────────────────────────────
  document.addEventListener('keydown', (e) => {
    // Ctrl/Cmd + S → Save
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
      e.preventDefault();
      const saveBtn = document.querySelector('[data-action="save"]') || document.querySelector('.btn-primary');
      if (saveBtn) saveBtn.click();
    }

    // Escape → Close modals/panels
    if (e.key === 'Escape') {
      document.querySelectorAll('[data-dismiss]').forEach(el => el.click());
    }
  });

  // ── Sidebar Active State ────────────────────────────────────────────
  const currentPath = window.location.pathname;
  document.querySelectorAll('.admin-sidebar__link').forEach(link => {
    const href = link.getAttribute('href');
    if (href && currentPath.startsWith(href) && href !== '/admin') {
      link.classList.add('active');
    } else if (href === '/admin' && currentPath === '/admin') {
      link.classList.add('active');
    }
  });

  // ── Auto-dismiss notifications ──────────────────────────────────────
  document.querySelectorAll('.notification[data-auto-dismiss]').forEach(n => {
    const delay = parseInt(n.dataset.autoDismiss) || 5000;
    setTimeout(() => { n.style.opacity = '0'; setTimeout(() => n.remove(), 200); }, delay);
  });
});
