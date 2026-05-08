/**
 * Slug Manager — Admin page JS for URL alias management.
 *
 * Handles: token chip insertion, pattern preview, alias search, slug edit modal, copy-to-clipboard.
 */
document.addEventListener('DOMContentLoaded', () => {

  // ── Token Chip Click-to-Insert ───────────────────────────────────
  let activePatternInput = null;

  // Track which pattern input was last focused
  document.querySelectorAll('[data-pattern-preview]').forEach(input => {
    input.addEventListener('focus', () => { activePatternInput = input; });
  });

  document.querySelectorAll('.slug-token-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      const token = chip.dataset.token;
      if (!token) return; // static display-only chips

      const input = activePatternInput || document.querySelector('[data-pattern-preview]');
      if (!input) return;

      // Insert token at cursor position
      const start = input.selectionStart;
      const end = input.selectionEnd;
      const val = input.value;
      input.value = val.substring(0, start) + token + val.substring(end);
      input.focus();
      input.selectionStart = input.selectionEnd = start + token.length;

      // Flash the chip
      chip.classList.add('is-inserted');
      setTimeout(() => chip.classList.remove('is-inserted'), 600);

      // Trigger preview update
      input.dispatchEvent(new Event('input'));
    });
  });

  // ── Pattern Preview ───────────────────────────────────────────────
  const now = new Date();
  const months = ['january','february','march','april','may','june','july','august','september','october','november','december'];
  const monthsShort = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
  const days = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
  const daysShort = ['sun','mon','tue','wed','thu','fri','sat'];

  const pad2 = n => String(n).padStart(2, '0');
  const yyyy = now.getFullYear().toString();
  const mm   = pad2(now.getMonth() + 1);
  const dd   = pad2(now.getDate());
  const iso  = `${yyyy}-${mm}-${dd}`;
  const ts   = Math.floor(now.getTime() / 1000).toString();
  const wk   = pad2(getISOWeek(now));

  // All available node tokens
  const nodeTokens = {
    '[title]':              'my-example-title',
    '[type]':               '{bundle}', // replaced per-row
    '[id]':                 '42',
    '[summary]':            'brief-summary',
    '[language]':           'en',
    // Author
    '[author]':             'admin',
    '[author:id]':          '1',
    '[author:name]':        'admin',
    // Published date
    '[year]':               yyyy,
    '[month]':              mm,
    '[day]':                dd,
    '[week]':               wk,
    '[month:name]':         months[now.getMonth()],
    '[month:short]':        monthsShort[now.getMonth()],
    '[day:name]':           days[now.getDay()],
    '[day:short]':          daysShort[now.getDay()],
    '[date:iso]':           iso,
    '[date:timestamp]':     ts,
    // Created date
    '[created:year]':       yyyy,
    '[created:month]':      mm,
    '[created:day]':        dd,
    '[created:week]':       wk,
    '[created:month:name]': months[now.getMonth()],
    '[created:month:short]':monthsShort[now.getMonth()],
    '[created:day:name]':   days[now.getDay()],
    '[created:day:short]':  daysShort[now.getDay()],
    '[created:iso]':        iso,
    '[created:timestamp]':  ts,
    // Updated / modified date
    '[updated:year]':       yyyy,
    '[updated:month]':      mm,
    '[updated:day]':        dd,
    '[updated:week]':       wk,
    '[updated:month:name]': months[now.getMonth()],
    '[updated:month:short]':monthsShort[now.getMonth()],
    '[updated:day:name]':   days[now.getDay()],
    '[updated:day:short]':  daysShort[now.getDay()],
    '[updated:iso]':        iso,
    '[updated:timestamp]':  ts,
  };

  // Taxonomy term tokens
  const termTokens = {
    '[name]':          'example-term',
    '[vocabulary]':    '{bundle}',
    '[id]':            '7',
    '[description]':   'term-description',
    '[weight]':        '0',
    '[parent]':        'parent-term',
    '[parent:id]':     '3',
    '[created:year]':  yyyy,
    '[created:month]': mm,
    '[created:day]':   dd,
    '[created:iso]':   iso,
    '[updated:year]':  yyyy,
    '[updated:month]': mm,
    '[updated:day]':   dd,
    '[updated:iso]':   iso,
  };

  document.querySelectorAll('[data-pattern-preview]').forEach(input => {
    const row = input.closest('.slug-pattern-row');
    const output = row?.querySelector('[data-preview-output] .slug-preview-text');
    if (!output) return;

    const bundle = input.dataset.bundle || 'article';
    const isTerm = row.classList.contains('slug-pattern-row--term');
    const tokenMap = isTerm ? termTokens : nodeTokens;

    const update = () => {
      const pattern = input.value || (isTerm ? '[name]' : '[title]');
      let preview = pattern;

      for (const [token, val] of Object.entries(tokenMap)) {
        const replacement = val === '{bundle}' ? bundle : val;
        preview = preview.replaceAll(token, replacement);
      }
      output.textContent = '/' + preview.replace(/^\/+/, '');
    };

    input.addEventListener('input', update);
  });

  // ── Alias Search/Filter ──────────────────────────────────────────
  const searchInput = document.getElementById('alias-search');
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      const q = searchInput.value.toLowerCase();
      document.querySelectorAll('[data-alias-row]').forEach(row => {
        const title = row.dataset.title || '';
        const slug = row.dataset.slug || '';
        row.hidden = q.length > 0 && !(title.includes(q) || slug.includes(q));
      });
    });
  }

  // ── Copy to Clipboard ────────────────────────────────────────────
  document.querySelectorAll('.slug-copy-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const text = btn.dataset.copy;
      navigator.clipboard.writeText(text).then(() => {
        btn.classList.add('is-copied');
        setTimeout(() => btn.classList.remove('is-copied'), 1200);
      });
    });
  });
});

// ── Edit Slug Modal ────────────────────────────────────────────────
function editSlug(id, currentSlug, contentTitle) {
  const modal = document.getElementById('edit-slug-modal');
  const form = document.getElementById('edit-slug-form');
  const input = document.getElementById('edit-slug-input');
  const subtitle = document.getElementById('edit-slug-content-title');

  form.action = '/admin/url-aliases/' + id + '/update';
  input.value = currentSlug;
  if (subtitle) subtitle.textContent = contentTitle || '';
  modal.hidden = false;
  input.focus();
  input.select();
}

function closeEditSlug() {
  document.getElementById('edit-slug-modal').hidden = true;
}

// Close modal on overlay click
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('slug-modal-overlay')) {
    closeEditSlug();
  }
});

// Close modal on Escape
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeEditSlug();
});

// ── Helpers ────────────────────────────────────────────────────────
function getISOWeek(date) {
  const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
  d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7));
  const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
  return Math.ceil(((d - yearStart) / 86400000 + 1) / 7);
}
