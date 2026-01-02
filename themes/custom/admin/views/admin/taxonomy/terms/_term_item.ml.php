@php
    $hasChildren = !empty($term->children);
    $itemClasses = "bg-white border rounded-lg mb-2 shadow-sm transition-all duration-200 group";
@endphp

<li class="{{ $itemClasses }}" data-id="{{ $term->id }}">
    <div class="flex items-center justify-between p-3 hover:bg-gray-50 handle cursor-move">
        <div class="flex items-center gap-3">
            <!-- Drag Handle Icon -->
            <svg class="w-5 h-5 text-gray-400 group-hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
            </svg>
            
            <div class="flex flex-col">
                <div class="flex items-center gap-2">
                    <span class="font-medium text-gray-900">{{ $term->name }}</span>
                    @if(!$term->is_published)
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500 border border-gray-200">Draft</span>
                    @endif
                </div>
                <div class="text-xs text-gray-500 font-mono">{{ $term->slug }}</div>
            </div>
        </div>

        <div class="flex items-center gap-2">
             <a href="/admin/taxonomies/{{$vocabulary->id}}/terms/{{ $term->id }}/edit" class="p-1 text-gray-400 hover:text-blue-600 rounded">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
             </a>
             <a href="/admin/taxonomies/{{$vocabulary->id}}/terms/{{ $term->id }}/delete" onclick="return confirm('Are you sure?')" class="p-1 text-gray-400 hover:text-red-600 rounded">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
             </a>
        </div>
    </div>

    <ul class="pl-8 pb-2 pr-2 nested-sortable min-h-[10px] space-y-2 {{ $hasChildren ? '' : 'empty' }}">
        @if($hasChildren)
            @foreach($term->children as $child)
                @include('admin.taxonomy.terms._term_item', ['term' => $child, 'vocabulary' => $vocabulary])
            @endforeach
        @endif
    </ul>
</li>
