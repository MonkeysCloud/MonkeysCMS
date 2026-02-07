@extends('layouts.admin')

@push('scripts')
<script src="/js/sortable.min.js"></script>
<script src="/js/menu-tree.js"></script>
@endpush

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-4">
                <a href="/admin/menus" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h2 class="text-2xl font-bold text-gray-900">{{ $isNew ? 'Create Menu' : 'Edit Menu' }}</h2>
            </div>
        </div>
    </div>

    <!-- Main Menu Form -->
    <x-ui.card class="max-w-3xl mb-8">
        @php
            $menuName = $menu->name;
            $menuMachineName = $menu->machine_name;
            $menuDescription = $menu->description;
            $formAction = $isNew ? '/admin/menus' : '/admin/menus/' . $menu->id;
        @endphp
        <form method="post" action="{{ $formAction }}">
            
            <x-form.input 
                name="name" 
                label="Name" 
                :value="$menuName" 
                required 
            />

            <x-form.input 
                name="machine_name" 
                label="Machine Name" 
                :value="$menuMachineName" 
                placeholder="Auto-generated if empty"
                help="Unique identifier for this menu (e.g. 'main', 'footer')."
            />

            <x-form.textarea 
                name="description" 
                label="Description" 
                rows="3"
            >{{ $menu->description }}</x-form.textarea>

            <input type="hidden" name="location" value="{{ $menu->location ?? 'custom' }}">

            <div class="pt-4 border-t border-gray-100 flex justify-end">
                <x-ui.button type="submit">
                    {{ $isNew ? 'Create Menu' : 'Save Changes' }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <!-- Menu Items Section -->
    @if(!$isNew)
        @php $menuId = $menu->id; @endphp
        <div class="flex items-center justify-between mb-4 mt-8 max-w-4xl">
            <div class="flex items-center gap-4">
                <h3 class="text-lg font-bold text-gray-900">Menu Items</h3>
                <span class="sortable-status text-sm font-medium transition-colors duration-300"></span>
            </div>
            <x-ui.button :href="'/admin/menus/' . $menuId . '/items/create'" color="secondary" size="sm">
                + Add Item
            </x-ui.button>
        </div>

        <x-ui.card class="max-w-4xl overflow-hidden p-0">
            @if(empty($menu->items))
                <div class="text-center py-12 text-gray-500">
                    <p>No items found. Add one to get started.</p>
                </div>
            @else
                <div id="menu-tree-container" 
                     data-reorder-url="/admin/menus/{{ $menu->id }}/items/reorder">
                    
                    <ul class="nested-sortable space-y-2 root-list">
                        @foreach($menu->items as $item)
                            @include('admin.menus._menu_item', ['item' => $item, 'menu' => $menu])
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-ui.card>
    @endif

    @push('scripts')
    <script src="/themes/custom/admin/assets/js/menu-tree.js"></script>
    @endpush
@endsection
