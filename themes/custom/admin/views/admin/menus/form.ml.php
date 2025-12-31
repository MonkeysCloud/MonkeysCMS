@extends('layouts.admin')

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
            <h3 class="text-lg font-bold text-gray-900">Menu Items</h3>
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
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Item Structure</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Destination</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-50">
                            @foreach($menu->items as $item)
                            @php $itemId = $item->id; @endphp
                            <tr class="hover:bg-blue-50/50 transition-colors group">
                                <td class="px-6 py-3">
                                    <div class="flex items-center">
                                        <!-- Indentation visual guide -->
                                        @if($item->depth > 0)
                                            <div class="flex mr-2">
                                                @for($i = 0; $i < $item->depth; $i++)
                                                    <div class="w-4 border-r border-gray-200 h-full mr-2"></div>
                                                @endfor
                                            </div>
                                        @endif
                                        
                                        <div class="flex items-center">
                                            @if($item->icon)
                                                <span class="mr-2 text-gray-400 opacity-70">{{ $item->icon }}</span>
                                            @endif
                                            <span class="font-medium text-gray-900 group-hover:text-blue-700 transition-colors">{{ $item->title }}</span>
                                            
                                            @if(!$item->is_published)
                                                <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500 border border-gray-200">Draft</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-500 font-mono text-xs">
                                    {{ $item->url ?? '-' }}
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                    <x-ui.button :href="'/admin/menus/' . $menuId . '/items/' . $itemId . '/edit'" color="ghost" class="!p-1">
                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </x-ui.button>
                                    <x-ui.button :href="'/admin/menus/' . $menuId . '/items/' . $itemId . '/delete'" color="ghost" class="!p-1 text-red-500 hover:text-red-700" onclick="return confirm('Are you sure?')">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </x-ui.button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    @endif
@endsection
