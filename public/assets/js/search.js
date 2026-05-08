/**
 * MonkeysCMS — Global Search Widget
 *
 * A premium, full-screen search overlay with:
 *  - Debounced live search (300ms)
 *  - Autocomplete suggestions
 *  - Content type filtering
 *  - Keyboard navigation (↑/↓/Enter/Esc)
 *  - Cmd+K / Ctrl+K shortcut
 *  - Smooth animations
 *
 * API Endpoints used:
 *  GET /api/search?q=...&type=...&page=...
 *  GET /api/search/suggest?q=...
 *  GET /api/search/facets
 *
 * Usage: Include this script and call `MonkeysSearch.init()`,
 * or it auto-initializes when DOM is ready.
 */
;(function () {
  'use strict';

  const DEBOUNCE_MS = 300;
  const MIN_QUERY_LEN = 2;
  const RESULTS_PER_PAGE = 8;

  // ── State ──────────────────────────────────────────────────────────

  let overlay = null;
  let inputEl = null;
  let resultsEl = null;
  let filtersEl = null;
  let statusEl = null;
  let debounceTimer = null;
  let currentQuery = '';
  let currentType = '';
  let selectedIndex = -1;
  let facets = [];
  let isOpen = false;

  // ── Public API ────────────────────────────────────────────────────

  const MonkeysSearch = {
    init() {
      createOverlay();
      bindTriggers();
      bindKeyboardShortcut();
      loadFacets();
    },

    open() {
      if (isOpen) return;
      isOpen = true;
      overlay.classList.add('mks-search--open');
      document.body.style.overflow = 'hidden';
      requestAnimationFrame(() => inputEl.focus());
    },

    close() {
      if (!isOpen) return;
      isOpen = false;
      overlay.classList.remove('mks-search--open');
      document.body.style.overflow = '';
      inputEl.value = '';
      resultsEl.innerHTML = '';
      statusEl.textContent = '';
      currentQuery = '';
      selectedIndex = -1;
    },

    toggle() {
      isOpen ? this.close() : this.open();
    },
  };

  // ── DOM Creation ──────────────────────────────────────────────────

  function createOverlay() {
    overlay = document.createElement('div');
    overlay.className = 'mks-search';
    overlay.id = 'mks-search-overlay';
    overlay.innerHTML = `
      <div class="mks-search__backdrop" data-mks-close></div>
      <div class="mks-search__dialog" role="dialog" aria-label="Search">
        <div class="mks-search__header">
          <div class="mks-search__input-wrap">
            <svg class="mks-search__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
            </svg>
            <input type="text"
              class="mks-search__input"
              id="mks-search-input"
              placeholder="Search content…"
              autocomplete="off"
              spellcheck="false"
              aria-label="Search">
            <div class="mks-search__kbd">
              <kbd>Esc</kbd>
            </div>
          </div>
          <div class="mks-search__filters" id="mks-search-filters"></div>
        </div>
        <div class="mks-search__body">
          <div class="mks-search__status" id="mks-search-status"></div>
          <div class="mks-search__results" id="mks-search-results" role="listbox"></div>
        </div>
        <div class="mks-search__footer">
          <div class="mks-search__footer-hints">
            <span><kbd>↑</kbd><kbd>↓</kbd> Navigate</span>
            <span><kbd>↵</kbd> Open</span>
            <span><kbd>Esc</kbd> Close</span>
          </div>
          <span class="mks-search__footer-brand">Powered by MonkeysCMS</span>
        </div>
      </div>
    `;

    document.body.appendChild(overlay);

    inputEl = overlay.querySelector('#mks-search-input');
    resultsEl = overlay.querySelector('#mks-search-results');
    filtersEl = overlay.querySelector('#mks-search-filters');
    statusEl = overlay.querySelector('#mks-search-status');

    // Events
    inputEl.addEventListener('input', onInput);
    inputEl.addEventListener('keydown', onKeyDown);
    overlay.querySelector('[data-mks-close]').addEventListener('click', () => MonkeysSearch.close());
  }

  // ── Triggers ──────────────────────────────────────────────────────

  function bindTriggers() {
    // Bind any element with [data-mks-search] attribute
    document.querySelectorAll('[data-mks-search]').forEach(el => {
      el.addEventListener('click', (e) => {
        e.preventDefault();
        MonkeysSearch.open();
      });
    });
  }

  function bindKeyboardShortcut() {
    document.addEventListener('keydown', (e) => {
      // Cmd+K / Ctrl+K
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        MonkeysSearch.toggle();
      }
      // "/" to open search (when not in an input)
      if (e.key === '/' && !isInInput(e.target)) {
        e.preventDefault();
        MonkeysSearch.open();
      }
    });
  }

  function isInInput(el) {
    const tag = el.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
  }

  // ── Input Handling ────────────────────────────────────────────────

  function onInput(e) {
    const q = e.target.value.trim();
    currentQuery = q;

    clearTimeout(debounceTimer);

    if (q.length < MIN_QUERY_LEN) {
      resultsEl.innerHTML = q.length > 0
        ? '<div class="mks-search__hint">Type at least 2 characters…</div>'
        : '';
      statusEl.textContent = '';
      return;
    }

    statusEl.innerHTML = '<div class="mks-search__loading"><div class="mks-search__spinner"></div>Searching…</div>';
    debounceTimer = setTimeout(() => doSearch(q), DEBOUNCE_MS);
  }

  function onKeyDown(e) {
    const items = resultsEl.querySelectorAll('.mks-search__result');

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
        updateSelection(items);
        break;
      case 'ArrowUp':
        e.preventDefault();
        selectedIndex = Math.max(selectedIndex - 1, -1);
        updateSelection(items);
        break;
      case 'Enter':
        e.preventDefault();
        if (selectedIndex >= 0 && items[selectedIndex]) {
          const url = items[selectedIndex].dataset.url;
          if (url) window.location.href = url;
        } else if (currentQuery.length >= MIN_QUERY_LEN) {
          window.location.href = '/search?q=' + encodeURIComponent(currentQuery);
        }
        break;
      case 'Escape':
        MonkeysSearch.close();
        break;
    }
  }

  function updateSelection(items) {
    items.forEach((item, i) => {
      item.classList.toggle('mks-search__result--selected', i === selectedIndex);
      if (i === selectedIndex) {
        item.scrollIntoView({ block: 'nearest' });
      }
    });
  }

  // ── Search API ────────────────────────────────────────────────────

  async function doSearch(q) {
    try {
      const params = new URLSearchParams({
        q,
        per_page: String(RESULTS_PER_PAGE),
        highlight: '1',
        facets: '1',
      });
      if (currentType) params.set('type', currentType);

      const resp = await fetch('/api/search?' + params.toString());
      if (!resp.ok) throw new Error('Search failed');

      const json = await resp.json();

      // Guard: query changed while fetching
      if (q !== currentQuery) return;

      renderResults(json);
    } catch (err) {
      statusEl.innerHTML = '<div class="mks-search__error">Search unavailable. Try again later.</div>';
      resultsEl.innerHTML = '';
    }
  }

  // ── Rendering ─────────────────────────────────────────────────────

  function renderResults(json) {
    const { data, meta, facets: facetData } = json;
    selectedIndex = -1;

    // Status bar
    if (meta.total > 0) {
      statusEl.innerHTML = `
        <span>${meta.total} result${meta.total !== 1 ? 's' : ''}</span>
        <span class="mks-search__took">${meta.took_ms}ms</span>
      `;
    } else {
      statusEl.innerHTML = '';
    }

    // Results
    if (data.length === 0) {
      resultsEl.innerHTML = `
        <div class="mks-search__empty">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.3;margin-bottom:.5rem">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
            <path d="M8 11h6" stroke-linecap="round"/>
          </svg>
          <p>No results for "<strong>${escapeHtml(currentQuery)}</strong>"</p>
          <p class="mks-search__empty-hint">Try different keywords or check your spelling</p>
        </div>
      `;
      return;
    }

    resultsEl.innerHTML = data.map((hit, i) => `
      <a href="${escapeHtml(hit.url)}"
         class="mks-search__result"
         role="option"
         data-url="${escapeHtml(hit.url)}"
         data-index="${i}">
        <div class="mks-search__result-type">${escapeHtml(hit.type || 'page')}</div>
        <div class="mks-search__result-body">
          <div class="mks-search__result-title">${hit.highlights?.title || escapeHtml(hit.title)}</div>
          <div class="mks-search__result-excerpt">${hit.excerpt || ''}</div>
        </div>
        <div class="mks-search__result-meta">
          ${hit.published_at ? formatDate(hit.published_at) : ''}
          ${hit.author ? `<span>· ${escapeHtml(hit.author)}</span>` : ''}
        </div>
        <svg class="mks-search__result-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="m9 18 6-6-6-6"/>
        </svg>
      </a>
    `).join('');

    // "View all" link
    if (meta.total > RESULTS_PER_PAGE) {
      resultsEl.innerHTML += `
        <a href="/search?q=${encodeURIComponent(currentQuery)}${currentType ? '&type=' + currentType : ''}"
           class="mks-search__view-all">
          View all ${meta.total} results →
        </a>
      `;
    }

    // Update facet counts
    if (facetData?.content_type) {
      updateFacetCounts(facetData.content_type);
    }
  }

  // ── Facets / Filters ──────────────────────────────────────────────

  async function loadFacets() {
    try {
      const resp = await fetch('/api/search/facets');
      if (!resp.ok) return;
      const json = await resp.json();
      facets = json.content_types || [];
      renderFilters();
    } catch {
      // Silent fail — filters are optional
    }
  }

  function renderFilters() {
    if (facets.length < 2) {
      filtersEl.innerHTML = '';
      return;
    }

    let html = `<button class="mks-search__filter ${!currentType ? 'mks-search__filter--active' : ''}"
      data-type="">All</button>`;

    facets.forEach(f => {
      html += `<button class="mks-search__filter ${currentType === f.value ? 'mks-search__filter--active' : ''}"
        data-type="${escapeHtml(f.value)}">${escapeHtml(f.label)}
        <span class="mks-search__filter-count">${f.count}</span>
      </button>`;
    });

    filtersEl.innerHTML = html;

    filtersEl.querySelectorAll('.mks-search__filter').forEach(btn => {
      btn.addEventListener('click', () => {
        currentType = btn.dataset.type || '';
        filtersEl.querySelectorAll('.mks-search__filter').forEach(b =>
          b.classList.toggle('mks-search__filter--active', b === btn));
        if (currentQuery.length >= MIN_QUERY_LEN) {
          doSearch(currentQuery);
        }
      });
    });
  }

  function updateFacetCounts(counts) {
    filtersEl.querySelectorAll('.mks-search__filter').forEach(btn => {
      const type = btn.dataset.type;
      const countEl = btn.querySelector('.mks-search__filter-count');
      if (countEl && type && counts[type] !== undefined) {
        countEl.textContent = counts[type];
      }
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────

  function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function formatDate(isoStr) {
    try {
      const d = new Date(isoStr);
      return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    } catch {
      return '';
    }
  }

  // ── Auto-init ─────────────────────────────────────────────────────

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => MonkeysSearch.init());
  } else {
    MonkeysSearch.init();
  }

  // Expose globally
  window.MonkeysSearch = MonkeysSearch;

})();
