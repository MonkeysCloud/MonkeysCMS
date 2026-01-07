@extends('layouts.admin')

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script src="/js/taxonomy-tree.js"></script>
@endpush

@section('content')
    <div class="mb-6 flex justify-between items-center">
        <div>
            <div class="flex items-center gap-2">
                <a href="/admin/taxonomies" class="text-gray-500 hover:text-gray-700">
                    Vocabularies
                </a>
                <span class="text-gray-300">/</span>
                <h2 class="text-2xl font-bold text-gray-900">{{ $vocabulary->name }} Terms</h2>
            </div>
            <p class="mt-1 text-sm text-gray-500 flex items-center gap-2">
                Drag and drop rows to reorder terms.
                <span class="sortable-status font-medium ml-2"></span>
            </p>
        </div>
        @php
            $createUrl = '/admin/taxonomies/' . $vocabulary->id . '/terms/create';
        @endphp
        <div class="flex gap-2">
            <x-ui.button :href="$createUrl">
                Add Term
            </x-ui.button>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg p-6">
        @if(empty($terms))
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No terms</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by creating a new term.</p>
                <div class="mt-6">
                    <x-ui.button :href="$createUrl">Add Term</x-ui.button>
                </div>
            </div>
        @else
            <div id="taxonomy-tree-container" 
                 data-vocabulary-id="{{ $vocabulary->id }}"
                 data-reorder-url="/admin/taxonomies/{{ $vocabulary->id }}/terms/reorder">
                
                <ul class="nested-sortable space-y-2 root-list">
                    @foreach($terms as $term)
                        @include('admin.taxonomy.terms._term_item', ['term' => $term, 'vocabulary' => $vocabulary])
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    @push('scripts')
    <script src="/themes/custom/admin/assets/js/taxonomy-tree.js"></script>
    @endpush
@endsection
