@extends('layouts/admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Content Types</h2>
            <p class="mt-1 text-sm text-gray-500">Manage content types for your site</p>
        </div>
        <x-ui.button href="/admin/structure/content-types/create">
            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Create Content Type
        </x-ui.button>
    </div>

    @if(empty($types))
        <x-ui.card class="bg-white shadow">
            <div class="px-6 py-12 text-center text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="mt-2">No content types found.</p>
                <div class="mt-6">
                    <x-ui.button href="/admin/structure/content-types/create" size="sm">
                        Create Content Type
                    </x-ui.button>
                </div>
            </div>
        </x-ui.card>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($types as $type)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg hover:border-blue-300 transition-all duration-200 group">
                    {{-- Card Header with gradient accent --}}
                    <div class="h-1.5 bg-gradient-to-r from-blue-500 to-indigo-500"></div>
                    
                    <div class="p-6">
                        {{-- Top row: Icon + Title + Source badge --}}
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center text-2xl">
                                    {{ $type['icon'] ?? '📄' }}
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 group-hover:text-blue-600 transition-colors">
                                        {{ $type['label'] }}
                                    </h3>
                                    <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded text-gray-500 font-mono"><?= $type['id'] ?></code>
                                </div>
                            </div>
                            @if($type['source'] === 'code')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                    <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                                    </svg>
                                    Code
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-600">
                                    <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                                    </svg>
                                    Database
                                </span>
                            @endif
                        </div>
                        
                        {{-- Description --}}
                        @if($type['description'] ?? '')
                            <p class="text-sm text-gray-500 mb-3 line-clamp-2">{{ $type['description'] }}</p>
                        @endif
                        
                        {{-- Tags/badges --}}
                        <div class="flex flex-wrap gap-1.5 mb-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-600">
                                <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                </svg>
                                {{ count($type['fields'] ?? []) }} fields
                            </span>
                            @if($type['publishable'] ?? false)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-green-50 text-green-700">
                                    <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Publishable
                                </span>
                            @endif
                            @if($type['revisionable'] ?? false)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-purple-50 text-purple-700">
                                    <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Revisions
                                </span>
                            @endif
                            @if($type['translatable'] ?? false)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-amber-50 text-amber-700">
                                    <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
                                    </svg>
                                    i18n
                                </span>
                            @endif
                        </div>

                        {{-- Primary Action: Add Content --}}
                        <a href="/admin/content/{{ $type['id'] }}/add" 
                           class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add {{ $type['label'] }}
                        </a>
                    </div>
                    
                    {{-- Card Footer --}}
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                        <div class="flex items-center justify-between">
                            <a href="/admin/structure/content-types/{{ $type['id'] }}/fields" class="text-sm text-gray-600 hover:text-blue-600 font-medium inline-flex items-center">
                                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                </svg>
                                Manage Fields
                            </a>
                            <div class="flex items-center gap-4">
                                @if($type['source'] === 'database' && !($type['entity']->is_system ?? false))
                                    <a href="/admin/structure/content-types/{{ $type['id'] }}/edit" class="text-sm text-gray-500 hover:text-blue-600 inline-flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        Edit
                                    </a>
                                    <form action="/admin/structure/content-types/{{ $type['id'] }}/delete" method="POST" class="inline-flex" onsubmit="return confirm('Delete this content type?');">
                                        <button type="submit" class="text-sm text-red-500 hover:text-red-700 inline-flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            Delete
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-400 italic">Code-defined</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
