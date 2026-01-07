@extends('layouts.admin')

@section('content')
<div x-data="mediaBulkActions()" x-init="init()">
<div class="page-header mb-4 sm:mb-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 sm:gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Media Library</h1>
            <p class="text-gray-500 text-sm mt-1">Manage images, documents, and other media files.</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex items-center mr-2">
                <label class="inline-flex items-center text-sm text-gray-600 cursor-pointer select-none">
                    <input type="checkbox" 
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 mr-2"
                           x-on:click="toggleAll()"
                           x-bind:checked="allSelected">
                    Select All
                </label>
            </div>

            @if($permissions['can_upload'] ?? true)
            <a href="/admin/media/upload" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg shadow-sm flex items-center gap-2 transition text-sm w-fit">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                </svg>
                Upload
            </a>
            @endif
        </div>
    </div>
</div>

{{-- Mobile: Card Layout --}}
<div class="block sm:hidden space-y-3">
    @foreach($media as $item)
        <div class="media-item bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" 
             data-media-id="{{ $item->id }}">
            <a href="/admin/media/{{ $item->id }}" class="flex items-center p-4 hover:bg-gray-50 transition-colors">
                {{-- Checkbox --}}
                <div class="mr-3" x-on:click.stop.prevent>
                    <input type="checkbox" 
                           value="{{ $item->id }}" 
                           class="media-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 w-5 h-5"
                           x-on:click="toggle({{ $item->id }})">
                </div>
                
                {{-- Thumbnail --}}
                <div class="flex-shrink-0 w-14 h-14 bg-gray-100 rounded-lg overflow-hidden flex items-center justify-center mr-3">
                    @if($item->is_image && $item->getThumbnailUrl())
                        <img src="{{ $item->getThumbnailUrl() }}" alt="{{ $item->title ?? $item->filename }}" class="w-full h-full object-cover">
                    @else
                        <span class="text-gray-400 text-xs font-bold uppercase">{{ $item->extension }}</span>
                    @endif
                </div>
                
                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900 truncate text-sm">{{ $item->title ?? $item->filename }}</p>
                    <div class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                        <span>{{ strtoupper($item->extension) }}</span>
                        <span>•</span>
                        <span>{{ number_format($item->size / 1024, 1) }} KB</span>
                        @if($item->width && $item->height)
                        <span>•</span>
                        <span>{{ $item->width }}×{{ $item->height }}</span>
                        @endif
                    </div>
                </div>
                
                {{-- Arrow --}}
                <svg class="w-5 h-5 text-gray-400 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    @endforeach
    
    @if(empty($media))
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No media files found.</p>
        </div>
    @endif
</div>

{{-- Desktop: Grid Layout with Details --}}
<div class="hidden sm:block">
    @if(!empty($media))
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        @foreach($media as $item)
        <div class="media-item bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg hover:border-gray-300 transition-all group"
             data-media-id="{{ $item->id }}">
            
            {{-- Thumbnail Container --}}
            <a href="/admin/media/{{ $item->id }}" class="block relative aspect-square bg-gray-100">
                {{-- Selection Checkbox (top-left, always visible) --}}
                <div class="absolute top-3 left-3 z-10" x-on:click.stop.prevent>
                    <input type="checkbox" 
                           class="media-checkbox w-5 h-5 rounded border-2 border-white shadow-lg text-blue-600 focus:ring-blue-500 focus:ring-offset-0 cursor-pointer bg-white/80 backdrop-blur-sm"
                           x-on:click="toggle({{ $item->id }})">
                </div>
                
                {{-- Image/Icon --}}
                @if($item->is_image && $item->getThumbnailUrl())
                    <img src="{{ $item->getThumbnailUrl() }}" 
                         alt="{{ $item->title ?? $item->filename }}" 
                         class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" 
                         loading="lazy">
                @else
                    <div class="w-full h-full flex flex-col items-center justify-center text-gray-400">
                        <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="text-sm font-bold uppercase">{{ $item->extension }}</span>
                    </div>
                @endif
            </a>
            
            {{-- File Details --}}
            <div class="p-3 border-t border-gray-100">
                <a href="/admin/media/{{ $item->id }}" class="block group-hover:text-blue-600 transition-colors">
                    <p class="font-medium text-gray-900 text-sm truncate" title="{{ $item->title ?? $item->filename }}">
                        {{ $item->title ?? $item->filename }}
                    </p>
                </a>
                <div class="flex items-center gap-2 mt-1.5 text-xs text-gray-500">
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 font-medium">
                        {{ strtoupper($item->extension) }}
                    </span>
                    <span>{{ number_format($item->size / 1024, 1) }} KB</span>
                    @if($item->width && $item->height)
                    <span class="text-gray-300">|</span>
                    <span>{{ $item->width }}×{{ $item->height }}</span>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    
    {{-- Pagination --}}
    @if(($pagination['total_pages'] ?? 1) > 1)
    <div class="mt-6 flex justify-center">
        <nav class="flex items-center gap-2">
            @if($pagination['page'] > 1)
            <a href="?page={{ $pagination['page'] - 1 }}" class="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">&laquo; Previous</a>
            @endif
            
            <span class="px-3 py-2 text-sm text-gray-600">
                Page {{ $pagination['page'] }} of {{ $pagination['total_pages'] }}
            </span>
            
            @if($pagination['page'] < $pagination['total_pages'])
            <a href="?page={{ $pagination['page'] + 1 }}" class="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">Next &raquo;</a>
            @endif
        </nav>
    </div>
    @endif
    
    @else
    {{-- Empty State --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">No media found</h3>
        <p class="mt-1 text-sm text-gray-500">Get started by uploading files.</p>
        @if($permissions['can_upload'] ?? true)
        <div class="mt-6">
            <a href="/admin/media/upload" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                Upload Files
            </a>
        </div>
        @endif
    </div>
    @endif
</div>

{{-- Floating Action Bar --}}
<template x-if="selected.length > 0">
    <div class="fixed bottom-0 inset-x-0 z-40 bg-white border-t border-gray-200 shadow-lg p-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-4">
                <span class="font-medium text-gray-900" x-text="selected.length + ' items selected'"></span>
                <button x-on:click="selected = []" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
            </div>
            <div class="flex items-center gap-3">
                @if($permissions['can_delete'] ?? true)
                <button x-on:click="confirmBulkDelete()" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 shadow-sm text-sm font-medium transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Delete Selected
                </button>
                @endif
            </div>
        </div>
    </div>
</template>
</div>
@endsection
