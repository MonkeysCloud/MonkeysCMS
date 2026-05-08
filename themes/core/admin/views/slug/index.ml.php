@extends('layouts.admin')

@section('title', 'URL Aliases')
@section('page_title', 'URL Aliases')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">URL Aliases</span>
@endsection

@section('content')
<div class="slug-page">

  {{-- Flash Messages --}}
  @if(!empty($flashSuccess))
  <div class="alert alert--success mb-4"><i data-lucide="check-circle" class="w-4 h-4"></i> {{ $flashSuccess }}</div>
  @endif

  @if(!empty($flashError))
  <div class="alert alert--danger mb-4"><i data-lucide="alert-circle" class="w-4 h-4"></i> {{ $flashError }}</div>
  @endif

  {{-- ═══ Stats Bar ═══ --}}
  <div class="slug-stats">
    <div class="slug-stats__item">
      <span class="slug-stats__value">{{ $nodePagination['total'] ?? 0 }}</span>
      <span class="slug-stats__label">Content Aliases</span>
    </div>
    <div class="slug-stats__item">
      <span class="slug-stats__value">{{ count($contentTypes) }}</span>
      <span class="slug-stats__label">Content Types</span>
    </div>
    <div class="slug-stats__item slug-stats__item--term">
      <span class="slug-stats__value">{{ $termPagination['total'] ?? 0 }}</span>
      <span class="slug-stats__label">Term Aliases</span>
    </div>
    <div class="slug-stats__item slug-stats__item--term">
      <span class="slug-stats__value">{{ count($vocabularies ?? []) }}</span>
      <span class="slug-stats__label">Vocabularies</span>
    </div>
  </div>

  {{-- ═══ Section 1: URL Patterns ═══ --}}
  <div class="card slug-patterns-card mb-6">
    <div class="card__header card__header--between">
      <div class="slug-patterns-header">
        <h3 class="card__title">
          <i data-lucide="route" class="w-5 h-5 card__title-icon"></i> URL Patterns
        </h3>
        <p class="slug-patterns-subtitle">Define how URLs are generated for each content type using tokens</p>
      </div>
      <form action="/admin/url-aliases/regenerate" method="POST" class="inline-form"
            data-confirm="Regenerate ALL slugs? Existing URLs may change." data-confirm-title="Regenerate Slugs" data-confirm-label="Regenerate" data-confirm-class="btn btn--warning">
        <input type="hidden" name="entity_type" value="node">
        <button type="submit" class="btn btn--sm btn--warning">
          <i data-lucide="refresh-cw" class="w-4 h-4"></i> Regenerate All
        </button>
      </form>
    </div>

    <div class="card__body">
      {{-- Token Reference — grouped by category --}}
      <div class="slug-token-bar">
        <span class="slug-token-bar__label">
          <i data-lucide="hash" class="w-3.5 h-3.5"></i> Available Tokens:
        </span>
        <div class="slug-token-groups">
          @php
            $tokenGroups = [
              'Content'   => ['[title]','[type]','[id]','[summary]','[language]'],
              'Author'    => ['[author]','[author:id]','[author:name]'],
              'Published' => ['[year]','[month]','[day]','[week]','[month:name]','[month:short]','[day:name]','[day:short]','[date:iso]','[date:timestamp]'],
              'Created'   => ['[created:year]','[created:month]','[created:day]','[created:week]','[created:month:name]','[created:month:short]','[created:day:name]','[created:day:short]','[created:iso]','[created:timestamp]'],
              'Modified'  => ['[updated:year]','[updated:month]','[updated:day]','[updated:week]','[updated:month:name]','[updated:month:short]','[updated:day:name]','[updated:day:short]','[updated:iso]','[updated:timestamp]'],
            ];
            $nodeTokens = $tokens['node'] ?? [];
          @endphp
          @foreach($tokenGroups as $groupLabel => $groupKeys)
          <div class="slug-token-group">
            <span class="slug-token-group__label">{{ $groupLabel }}</span>
            <div class="slug-token-chips">
              @foreach($groupKeys as $tk)
              @if(isset($nodeTokens[$tk]))
              <button type="button" class="slug-token-chip" data-token="{{ $tk }}" title="{{ $nodeTokens[$tk] }}">
                {{ $tk }}
              </button>
              @endif
              @endforeach
            </div>
          </div>
          @endforeach
        </div>
      </div>

      {{-- Content Type Patterns --}}
      <div class="slug-patterns-list">
        @foreach($contentTypes as $ct)
        @php
          $existing = $patternMap['node:' . $ct->type_id] ?? null;
          $currentPattern = $existing ? $existing->pattern : '[title]';
          $resolvedUrl = $resolvedPatterns[$ct->type_id] ?? '/{slug}';
        @endphp
        <form action="/admin/url-aliases/patterns" method="POST" class="slug-pattern-row" data-type="{{ $ct->type_id }}">
          <input type="hidden" name="entity_type" value="node">
          <input type="hidden" name="bundle" value="{{ $ct->type_id }}">

          <div class="slug-pattern-row__icon">
            <i data-lucide="{{ $ct->icon }}" class="w-5 h-5"></i>
          </div>

          <div class="slug-pattern-row__info">
            <span class="slug-pattern-row__label">{{ $ct->label }}</span>
            <span class="slug-pattern-row__type-id">{{ $ct->type_id }}</span>
          </div>

          <div class="slug-pattern-row__field">
            <div class="slug-pattern-input-wrap">
              <span class="slug-pattern-input-prefix">/</span>
              <input type="text" name="pattern" class="form-input slug-pattern-input"
                     value="{{ $currentPattern }}"
                     placeholder="[title]"
                     data-pattern-preview
                     data-bundle="{{ $ct->type_id }}">
            </div>
          </div>

          <div class="slug-pattern-row__preview">
            <span class="slug-preview-chip" data-preview-output>
              <i data-lucide="link" class="w-3 h-3"></i>
              <span class="slug-preview-text">/{{ str_replace(['[title]', '[type]', '[year]', '[month]', '[day]', '[id]', '[author]', '[author:id]', '[author:name]', '[month:name]', '[month:short]', '[day:name]', '[day:short]', '[date:iso]', '[week]', '[language]'], ['my-example-title', $ct->type_id, date('Y'), date('m'), date('d'), '42', 'admin', '1', 'admin', strtolower(date('F')), strtolower(date('M')), strtolower(date('l')), strtolower(date('D')), date('Y-m-d'), date('W'), 'en'], $currentPattern) }}</span>
            </span>
          </div>

          <div class="slug-pattern-row__actions">
            <button type="submit" class="btn btn--xs btn--primary" title="Save pattern">
              <i data-lucide="save" class="w-3.5 h-3.5"></i>
            </button>
            <form action="/admin/url-aliases/regenerate" method="POST" class="inline-form"
                  data-confirm="Regenerate all {{ $ct->label }} slugs?" data-confirm-title="Regenerate Slugs" data-confirm-label="Regenerate" data-confirm-class="btn btn--warning">
              <input type="hidden" name="entity_type" value="node">
              <input type="hidden" name="bundle" value="{{ $ct->type_id }}">
              <button type="submit" class="btn btn--xs btn--ghost" title="Regenerate slugs">
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
              </button>
            </form>
            @if($existing)
            <form action="/admin/url-aliases/patterns/{{ $existing->id }}/delete" method="POST" class="inline-form"
                  data-confirm="Remove pattern? Reverts to default [title]." data-confirm-title="Remove Pattern">
              <button type="submit" class="btn btn--xs btn--ghost btn--danger-ghost" title="Remove pattern">
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
              </button>
            </form>
            @endif
          </div>
        </form>
        @endforeach
      </div>

      {{-- Taxonomy Token Reference --}}
      <div class="slug-taxonomy-section">
        <div class="slug-taxonomy-header">
          <i data-lucide="hash" class="w-3.5 h-3.5"></i>
          <span>Taxonomy Term Tokens</span>
          <span class="slug-taxonomy-default">Default: <code>[name]</code></span>
        </div>
        <div class="slug-token-chips slug-token-chips--taxonomy">
          @foreach($tokens['term'] ?? [] as $tk => $desc)
          <button type="button" class="slug-token-chip slug-token-chip--term" data-token="{{ $tk }}" title="{{ $desc }}">{{ $tk }}</button>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  {{-- ═══ Section 1b: Taxonomy URL Patterns ═══ --}}
  <div class="card slug-patterns-card slug-patterns-card--taxonomy mb-6">
    <div class="card__header card__header--between">
      <div class="slug-patterns-header">
        <h3 class="card__title">
          <i data-lucide="tag" class="w-5 h-5 card__title-icon"></i> Taxonomy URL Patterns
        </h3>
        <p class="slug-patterns-subtitle">Define how term URLs are generated for each vocabulary</p>
      </div>
      <form action="/admin/url-aliases/regenerate" method="POST" class="inline-form"
            data-confirm="Regenerate ALL term slugs? Existing URLs may change." data-confirm-title="Regenerate Term Slugs" data-confirm-label="Regenerate" data-confirm-class="btn btn--warning">
        <input type="hidden" name="entity_type" value="term">
        <button type="submit" class="btn btn--sm btn--warning">
          <i data-lucide="refresh-cw" class="w-4 h-4"></i> Regenerate All Terms
        </button>
      </form>
    </div>

    <div class="card__body">
      @if(!empty($vocabularies))
      <div class="slug-patterns-list">
        @foreach($vocabularies as $vocab)
        @php
          $existing = $patternMap['term:' . $vocab->machine_name] ?? null;
          $currentPattern = $existing ? $existing->pattern : '[name]';
        @endphp
        <form action="/admin/url-aliases/patterns" method="POST" class="slug-pattern-row slug-pattern-row--term" data-type="{{ $vocab->machine_name }}">
          <input type="hidden" name="entity_type" value="term">
          <input type="hidden" name="bundle" value="{{ $vocab->machine_name }}">

          <div class="slug-pattern-row__icon slug-pattern-row__icon--term">
            <i data-lucide="tags" class="w-5 h-5"></i>
          </div>

          <div class="slug-pattern-row__info">
            <span class="slug-pattern-row__label">{{ $vocab->label }}</span>
            <span class="slug-pattern-row__type-id">{{ $vocab->machine_name }}</span>
          </div>

          <div class="slug-pattern-row__field">
            <div class="slug-pattern-input-wrap">
              <span class="slug-pattern-input-prefix">/</span>
              <input type="text" name="pattern" class="form-input slug-pattern-input"
                     value="{{ $currentPattern }}"
                     placeholder="[name]"
                     data-pattern-preview
                     data-bundle="{{ $vocab->machine_name }}">
            </div>
          </div>

          <div class="slug-pattern-row__preview">
            <span class="slug-preview-chip slug-preview-chip--term" data-preview-output>
              <i data-lucide="link" class="w-3 h-3"></i>
              <span class="slug-preview-text">/{{ str_replace(['[name]', '[vocabulary]', '[id]', '[parent]', '[parent:id]'], ['example-term', $vocab->machine_name, '7', 'parent-term', '3'], $currentPattern) }}</span>
            </span>
          </div>

          <div class="slug-pattern-row__actions">
            <button type="submit" class="btn btn--xs btn--primary" title="Save pattern">
              <i data-lucide="save" class="w-3.5 h-3.5"></i>
            </button>
            <form action="/admin/url-aliases/regenerate" method="POST" class="inline-form"
                  data-confirm="Regenerate all {{ $vocab->label }} term slugs?" data-confirm-title="Regenerate Slugs" data-confirm-label="Regenerate" data-confirm-class="btn btn--warning">
              <input type="hidden" name="entity_type" value="term">
              <input type="hidden" name="bundle" value="{{ $vocab->machine_name }}">
              <button type="submit" class="btn btn--xs btn--ghost" title="Regenerate slugs">
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
              </button>
            </form>
            @if($existing)
            <form action="/admin/url-aliases/patterns/{{ $existing->id }}/delete" method="POST" class="inline-form"
                  data-confirm="Remove pattern? Reverts to default [name]." data-confirm-title="Remove Pattern">
              <button type="submit" class="btn btn--xs btn--ghost btn--danger-ghost" title="Remove pattern">
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
              </button>
            </form>
            @endif
          </div>
        </form>
        @endforeach
      </div>
      @else
      <div class="empty-state py-4">
        <div class="empty-state__icon"><i data-lucide="tag" class="w-8 h-8"></i></div>
        <div class="empty-state__title">No vocabularies yet</div>
        <p class="text-muted text-sm">Create a vocabulary in <a href="/admin/taxonomy" class="text-accent">Taxonomy</a> to configure its URL patterns.</p>
      </div>
      @endif
    </div>
  </div>

  {{-- ═══ Alias Tabs ═══ --}}
  <div class="slug-tabs mb-3" id="aliases">
    <a href="/admin/url-aliases?tab=content#aliases" class="slug-tab {{ ($activeTab ?? 'content') === 'content' ? 'slug-tab--active' : '' }}">
      <i data-lucide="link" class="w-4 h-4"></i> Content Aliases
      <span class="slug-tab__count">{{ $nodePagination['total'] ?? 0 }}</span>
    </a>
    <a href="/admin/url-aliases?tab=terms#aliases" class="slug-tab {{ ($activeTab ?? 'content') === 'terms' ? 'slug-tab--active slug-tab--term' : '' }}">
      <i data-lucide="tags" class="w-4 h-4"></i> Term Aliases
      <span class="slug-tab__count">{{ $termPagination['total'] ?? 0 }}</span>
    </a>
  </div>

  {{-- ═══ Tab: Content Aliases ═══ --}}
  @if(($activeTab ?? 'content') === 'content')
  <div class="card slug-aliases-card mb-6">
    <div class="card__header card__header--between">
      <h3 class="card__title">
        <i data-lucide="link" class="w-5 h-5 card__title-icon"></i> Content Aliases
      </h3>
      <div class="slug-filter">
        <select class="form-select form-select--sm" id="alias-type-filter"
                onchange="window.location.href='/admin/url-aliases?tab=content' + (this.value ? '&type=' + this.value : '')">
          <option value="">All Types</option>
          @foreach($contentTypes as $ct)
          <option value="{{ $ct->type_id }}" {{ ($filterType ?? '') === $ct->type_id ? 'selected' : '' }}>{{ $ct->label }}</option>
          @endforeach
        </select>
        <div class="slug-search-wrap">
          <i data-lucide="search" class="w-4 h-4 slug-search-icon"></i>
          <input type="text" class="form-input form-input--sm slug-search-input" id="alias-search"
                 placeholder="Search aliases..." autocomplete="off">
        </div>
      </div>
    </div>
    <div class="card__body card__body--flush">
      @if(!empty($aliases))
      <table class="data-table slug-table" id="aliases-table">
        <thead>
          <tr>
            <th>Content</th>
            <th style="width:90px">Type</th>
            <th>Slug</th>
            <th>URL</th>
            <th class="text-right" style="width:100px">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($aliases as $alias)
          <tr data-alias-row data-title="{{ strtolower($alias->title) }}" data-slug="{{ $alias->slug }}">
            <td>
              <a href="/admin/content/{{ $alias->id }}/edit" class="slug-alias-title">{{ $alias->title }}</a>
            </td>
            <td>
              <span class="badge badge--type badge--{{ $alias->content_type }}">{{ $alias->content_type }}</span>
            </td>
            <td>
              <div class="slug-cell">
                <code class="slug-code">{{ $alias->slug }}</code>
                <button type="button" class="slug-copy-btn" data-copy="{{ $alias->slug }}" title="Copy slug">
                  <i data-lucide="copy" class="w-3 h-3"></i>
                </button>
              </div>
            </td>
            <td>
              <a href="/{{ $alias->slug }}" target="_blank" class="slug-url-link">
                /{{ $alias->slug }}
                <i data-lucide="external-link" class="w-3 h-3"></i>
              </a>
            </td>
            <td class="text-right">
              <button type="button" class="btn btn--xs btn--ghost" title="Edit slug"
                      onclick="editSlug({{ $alias->id }}, '{{ addslashes($alias->slug) }}', '{{ addslashes($alias->title) }}')">
                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
              </button>
              <a href="/admin/content/{{ $alias->id }}/edit" class="btn btn--xs btn--ghost" title="Edit content">
                <i data-lucide="file-edit" class="w-3.5 h-3.5"></i>
              </a>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @else
      <div class="empty-state py-8">
        <div class="empty-state__icon"><i data-lucide="link-2-off" class="w-10 h-10"></i></div>
        <div class="empty-state__title">No content found</div>
        <p class="text-muted text-sm">Create some content to see URL aliases here.</p>
      </div>
      @endif
    </div>
  </div>

  {{-- Node Pagination --}}
  @if(($nodePagination['pages'] ?? 1) > 1)
  <div class="flex-between mt-4 mb-4">
    <span class="text-sm text-muted">Showing {{ $nodePagination['from'] ?? 0 }}–{{ $nodePagination['to'] ?? 0 }} of {{ $nodePagination['total'] ?? 0 }}</span>
    <div class="pagination">
      @if($nodePagination['has_prev'] ?? false)
      <a href="/admin/url-aliases?tab=content&page={{ ($nodePagination['page'] ?? 1) - 1 }}{{ $filterType ? '&type=' . $filterType : '' }}"
         class="pagination__item">&laquo;</a>
      @endif
      @for($i = 1; $i <= ($nodePagination['pages'] ?? 1); $i++)
      <a href="/admin/url-aliases?tab=content&page={{ $i }}{{ $filterType ? '&type=' . $filterType : '' }}"
         class="pagination__item {{ ($nodePagination['page'] ?? 1) == $i ? 'active' : '' }}">{{ $i }}</a>
      @endfor
      @if($nodePagination['has_next'] ?? false)
      <a href="/admin/url-aliases?tab=content&page={{ ($nodePagination['page'] ?? 1) + 1 }}{{ $filterType ? '&type=' . $filterType : '' }}"
         class="pagination__item">&raquo;</a>
      @endif
    </div>
  </div>
  @endif
  @endif

  {{-- ═══ Tab: Term Aliases ═══ --}}
  @if(($activeTab ?? 'content') === 'terms')
  <div class="card slug-aliases-card">
    <div class="card__header card__header--between">
      <h3 class="card__title">
        <i data-lucide="tags" class="w-5 h-5 card__title-icon"></i> Term Aliases
      </h3>
    </div>
    <div class="card__body card__body--flush">
      @if(!empty($termAliases))
      <table class="data-table slug-table" id="term-aliases-table">
        <thead>
          <tr>
            <th>Term</th>
            <th style="width:120px">Vocabulary</th>
            <th>Slug</th>
            <th class="text-right" style="width:80px">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($termAliases as $ta)
          <tr>
            <td>
              <a href="/admin/taxonomy/{{ $ta->vocabulary_id }}/terms/{{ $ta->id }}/edit" class="slug-alias-title">{{ $ta->name }}</a>
            </td>
            <td>
              <span class="badge badge--type badge--taxonomy">{{ $ta->vocabulary_label }}</span>
            </td>
            <td>
              <div class="slug-cell">
                <code class="slug-code">{{ $ta->slug }}</code>
                <button type="button" class="slug-copy-btn" data-copy="{{ $ta->slug }}" title="Copy slug">
                  <i data-lucide="copy" class="w-3 h-3"></i>
                </button>
              </div>
            </td>
            <td class="text-right">
              <a href="/admin/taxonomy/{{ $ta->vocabulary_id }}/terms/{{ $ta->id }}/edit" class="btn btn--xs btn--ghost" title="Edit term">
                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
              </a>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @else
      <div class="empty-state py-8">
        <div class="empty-state__icon"><i data-lucide="tag" class="w-10 h-10"></i></div>
        <div class="empty-state__title">No terms found</div>
        <p class="text-muted text-sm">Add terms to your vocabularies to see their aliases here.</p>
      </div>
      @endif
    </div>
  </div>

  {{-- Term Pagination --}}
  @if(($termPagination['pages'] ?? 1) > 1)
  <div class="flex-between mt-4 mb-4">
    <span class="text-sm text-muted">Showing {{ $termPagination['from'] ?? 0 }}–{{ $termPagination['to'] ?? 0 }} of {{ $termPagination['total'] ?? 0 }}</span>
    <div class="pagination">
      @if($termPagination['has_prev'] ?? false)
      <a href="/admin/url-aliases?tab=terms&page={{ ($termPagination['page'] ?? 1) - 1 }}"
         class="pagination__item">&laquo;</a>
      @endif
      @for($i = 1; $i <= ($termPagination['pages'] ?? 1); $i++)
      <a href="/admin/url-aliases?tab=terms&page={{ $i }}"
         class="pagination__item {{ ($termPagination['page'] ?? 1) == $i ? 'active' : '' }}">{{ $i }}</a>
      @endfor
      @if($termPagination['has_next'] ?? false)
      <a href="/admin/url-aliases?tab=terms&page={{ ($termPagination['page'] ?? 1) + 1 }}"
         class="pagination__item">&raquo;</a>
      @endif
    </div>
  </div>
  @endif
  @endif

  {{-- Edit Slug Modal --}}
  <div class="slug-modal-overlay" id="edit-slug-modal" hidden>
    <div class="slug-modal">
      <div class="slug-modal__header">
        <div>
          <h4 class="slug-modal__title">Edit Slug</h4>
          <p class="slug-modal__subtitle" id="edit-slug-content-title"></p>
        </div>
        <button type="button" class="slug-modal__close" onclick="closeEditSlug()">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <form id="edit-slug-form" method="POST">
        <div class="slug-modal__body">
          <div class="form-group">
            <label class="form-label">New Slug</label>
            <div class="slug-edit-input-wrap">
              <span class="slug-edit-prefix">/</span>
              <input type="text" name="slug" id="edit-slug-input" class="form-input" required
                     pattern="[a-z0-9][a-z0-9-/]*" placeholder="my-content-slug">
            </div>
            <p class="form-help">Lowercase letters, numbers, hyphens, and slashes only.</p>
          </div>
        </div>
        <div class="slug-modal__footer">
          <button type="button" class="btn btn--ghost" onclick="closeEditSlug()">Cancel</button>
          <button type="submit" class="btn btn--primary">
            <i data-lucide="check" class="w-4 h-4"></i> Update Slug
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('head')
<link rel="stylesheet" href="/themes/core/admin/css/slug.css?v={{ time() }}">
@endpush

@push('scripts')
<script src="/themes/core/admin/js/slug-manager.js?v={{ time() }}"></script>
@endpush
@endsection
