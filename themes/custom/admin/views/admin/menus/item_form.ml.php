@extends('layouts.admin')

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 mb-2">
            <a href="/admin/menus/{{ $menu->id }}/edit" class="text-sm font-medium text-blue-600 hover:text-blue-500 hover:underline">
                &larr; Back to {{ $menu->name }}
            </a>
        </div>
        <h2 class="text-2xl font-bold text-gray-900">{{ $isNew ? 'Add Menu Item' : 'Edit Menu Item' }}</h2>
    </div>

    <form method="post" action="{{ $isNew ? '/admin/menus/' . $menu->id . '/items' : '/admin/menus/' . $menu->id . '/items/' . $item->id }}">
        
        @php
            $itemTitle = $item->title;
            $itemLinkType = $item->link_type;
            $itemUrl = $item->url;
            $itemParentId = $item->parent_id;
            $itemWeight = $item->weight;
            $itemTarget = $item->target;
            $itemIcon = $item->icon;
            $itemIsPublished = $item->is_published;
            $itemExpanded = $item->expanded;
        @endphp
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Column -->
            <div class="lg:col-span-2 space-y-6">
                <x-ui.card>
                    <x-form.input 
                        name="title" 
                        label="Title" 
                        :value="$itemTitle" 
                        required 
                    />

                    <x-form.select 
                        name="link_type" 
                        label="Link Type" 
                        :selected="$itemLinkType"
                    >
                        <option value="custom">Custom URL</option>
                        <option value="external">External URL</option>
                        <option value="nolink">No Link (Wrapper)</option>
                    </x-form.select>

                    <x-form.input 
                        name="url" 
                        label="URL" 
                        :value="$itemUrl" 
                        placeholder="e.g. /about or https://google.com" 
                        help="The destination path or absolute URL."
                    />
                </x-ui.card>
            </div>

            <!-- Sidebar Column -->
            <div class="space-y-6">
                <x-ui.card class="bg-gray-50 border-gray-200">
                    <x-form.select 
                        name="parent_id" 
                        label="Parent Item"
                        :selected="$itemParentId"
                    >
                        <option value="">-- Root --</option>
                        @foreach($parentOptions as $pId => $pTitle)
                            <option value="{{ $pId }}">{{ $pTitle }}</option>
                        @endforeach
                    </x-form.select>

                    <x-form.input 
                        type="number" 
                        name="weight" 
                        label="Weight (Order)" 
                        :value="$itemWeight" 
                    />

                    <x-form.select 
                        name="target" 
                        label="Target"
                        :selected="$itemTarget"
                    >
                        <option value="_self">Same Window (_self)</option>
                        <option value="_blank">New Window (_blank)</option>
                    </x-form.select>

                    <x-form.input 
                        name="icon" 
                        label="Icon" 
                        :value="$itemIcon" 
                        placeholder="Text or Emoji"
                    />

                    <div class="pt-4 border-t border-gray-200 space-y-2">
                        <x-form.checkbox 
                            name="is_published" 
                            label="Published" 
                            :checked="$itemIsPublished" 
                        />

                        <x-form.checkbox 
                            name="expanded" 
                            label="Expanded" 
                            :checked="$itemExpanded" 
                        />
                    </div>
                </x-ui.card>

                <div class="flex justify-end">
                    <x-ui.button type="submit" class="w-full justify-center">
                        {{ $isNew ? 'Create Item' : 'Save Changes' }}
                    </x-ui.button>
                </div>
            </div>
        </div>
    </form>
@endsection
