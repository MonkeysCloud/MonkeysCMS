{{-- ═══ Language Switcher ═══ --}}
{{-- Only renders when multilingual is enabled and >1 language --}}
@if(!empty($multilingualEnabled) && !empty($enabledLanguages) && count($enabledLanguages) > 1)
<div class="lang-switcher" role="navigation" aria-label="Language">
  <button class="lang-switcher__trigger" aria-expanded="false" aria-haspopup="listbox"
          onclick="this.parentElement.classList.toggle('open');this.setAttribute('aria-expanded',this.getAttribute('aria-expanded')==='false'?'true':'false')">
    @php
      $currentLangObj = null;
      foreach ($enabledLanguages as $l) {
        if ($l->code === ($locale ?? 'en')) { $currentLangObj = $l; break; }
      }
    @endphp
    <span class="lang-switcher__flag">{{ $currentLangObj?->flagEmoji ?? '🌐' }}</span>
    <span class="lang-switcher__code">{{ strtoupper($locale ?? 'en') }}</span>
    <svg class="lang-switcher__caret" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
      <polyline points="6 9 12 15 18 9"/>
    </svg>
  </button>

  <ul class="lang-switcher__menu" role="listbox" aria-label="Select language">
    @foreach($enabledLanguages as $lang)
    @php
      // Resolve URL for this language
      $langUrl = '/';
      if (!empty($translationSiblings[$lang->code])) {
        $langUrl = $translationSiblings[$lang->code];
      } elseif ($lang->code !== ($defaultLang ?? 'en')) {
        $langUrl = '/' . $lang->code;
      }
      $isCurrent = ($lang->code === ($locale ?? 'en'));
    @endphp
    <li role="option" {{ $isCurrent ? 'aria-selected="true"' : '' }}>
      <a href="{{ $langUrl }}" class="lang-switcher__item {{ $isCurrent ? 'lang-switcher__item--active' : '' }}"
         hreflang="{{ $lang->code }}" lang="{{ $lang->code }}">
        <span class="lang-switcher__item-flag">{{ $lang->flagEmoji }}</span>
        <span class="lang-switcher__item-name">{{ $lang->native }}</span>
        @if($isCurrent)
        <svg class="lang-switcher__check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
        @endif
      </a>
    </li>
    @endforeach
  </ul>
</div>

<style>
/* ── Language Switcher ────────────────────────────────────────── */
.lang-switcher { position: relative; margin-left: .5rem; }
.lang-switcher__trigger {
  display: inline-flex; align-items: center; gap: .3rem;
  padding: .35rem .6rem; border: 1px solid rgba(255,255,255,.1);
  border-radius: 8px; background: transparent; color: inherit;
  font-size: .82rem; cursor: pointer; transition: all .2s;
  font-family: inherit;
}
.lang-switcher__trigger:hover { border-color: rgba(255,255,255,.2); background: rgba(255,255,255,.04); }
.lang-switcher.open .lang-switcher__trigger { border-color: rgba(99,102,241,.4); background: rgba(99,102,241,.06); }
.lang-switcher__flag { font-size: 1rem; line-height: 1; }
.lang-switcher__code { font-weight: 600; font-size: .72rem; letter-spacing: .03em; }
.lang-switcher__caret { transition: transform .2s; opacity: .6; }
.lang-switcher.open .lang-switcher__caret { transform: rotate(180deg); }
.lang-switcher__menu {
  display: none; position: absolute; top: calc(100% + .4rem); right: 0;
  min-width: 180px; background: rgba(20,22,38,.98);
  border: 1px solid rgba(255,255,255,.08); border-radius: 10px;
  padding: .35rem; margin: 0; list-style: none; z-index: 100;
  box-shadow: 0 8px 32px rgba(0,0,0,.4);
  backdrop-filter: blur(12px);
}
.lang-switcher.open .lang-switcher__menu { display: block; }
.lang-switcher__item {
  display: flex; align-items: center; gap: .5rem;
  padding: .45rem .65rem; color: #cbd5e1; text-decoration: none;
  border-radius: 7px; font-size: .82rem; transition: all .15s;
}
.lang-switcher__item:hover { background: rgba(255,255,255,.06); color: #f1f5f9; }
.lang-switcher__item--active { color: #a5b4fc; background: rgba(99,102,241,.08); }
.lang-switcher__item-flag { font-size: 1.1rem; }
.lang-switcher__item-name { flex: 1; }
.lang-switcher__check { color: #818cf8; margin-left: auto; }

/* Close on outside click */
@media (min-width: 1px) {
  .lang-switcher__menu { animation: langMenuIn .15s ease-out; }
  @keyframes langMenuIn {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
  }
}
</style>

<script>
// Close language switcher when clicking outside
document.addEventListener('click', function(e) {
  document.querySelectorAll('.lang-switcher.open').forEach(function(el) {
    if (!el.contains(e.target)) {
      el.classList.remove('open');
      el.querySelector('[aria-expanded]')?.setAttribute('aria-expanded', 'false');
    }
  });
});
</script>
@endif
