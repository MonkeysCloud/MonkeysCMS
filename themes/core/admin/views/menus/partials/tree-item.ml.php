{{-- Recursive tree item for menu item display --}}
@php
  $isCode = ($item->attributes['_source'] ?? 'db') === 'code';
  $indent = $depth * 24;
@endphp
<div class="tree-item {{ $isCode ? 'tree-item--code' : '' }}"
     draggable="{{ $isCode ? 'false' : 'true' }}"
     data-item-id="{{ $item->id }}"
     data-parent-id="{{ $item->parent_id }}"
     style="padding-left: {{ $indent + 12 }}px">

  @if(!$isCode)
  <span class="tree-item__drag" title="Drag to reorder">
    <i data-lucide="grip-vertical" class="w-3.5 h-3.5"></i>
  </span>
  @endif

  @if($item->icon)
  <span class="tree-item__icon">
    <i data-lucide="{{ $item->icon }}" class="w-4 h-4"></i>
  </span>
  @endif

  <span class="tree-item__title">{{ $item->title }}</span>

  @if($item->url)
  <span class="tree-item__url">{{ $item->url }}</span>
  @endif

  @if($isCode)
  <span class="tree-item__badge badge badge--xs badge--info">code</span>
  @endif

  @if(!$item->enabled)
  <span class="tree-item__badge badge badge--xs badge--muted">disabled</span>
  @endif

  @if(!$isCode)
  <div class="tree-item__actions">
    <button class="tree-item__delete btn btn--xs btn--ghost btn--danger"
            data-item-id="{{ $item->id }}" title="Delete">
      <i data-lucide="x" class="w-3 h-3"></i>
    </button>
  </div>
  @endif
</div>

@if(!empty($item->children))
  @foreach($item->children as $child)
    @include('admin::menus.partials.tree-item', ['item' => $child, 'depth' => $depth + 1])
  @endforeach
@endif
