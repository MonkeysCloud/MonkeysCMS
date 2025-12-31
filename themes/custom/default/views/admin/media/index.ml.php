@extends('layouts.admin')

@section('content')
<div class="page-header mb-4 sm:mb-6">
    {{-- Responsive header --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 sm:gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Media Library</h1>
            <p class="text-gray-500 text-sm mt-1">Manage images, documents, and other media files.</p>
        </div>
        @if($permissions['can_upload'] ?? true)
        <a href="/admin/media/upload" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg shadow-sm flex items-center gap-2 transition text-sm w-fit">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
            </svg>
            Upload Files
        </a>
        @endif
    </div>
</div>

{{-- Mobile: Card Layout --}}
<div class="block sm:hidden space-y-3">
    @foreach($media as $item)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-start gap-3">
                {{-- Thumbnail --}}
                <div class="flex-shrink-0 w-16 h-16 bg-gray-100 rounded-lg overflow-hidden flex items-center justify-center">
                    @if($item->is_image && $item->getThumbnailUrl())
                        <img src="{{ $item->getThumbnailUrl() }}" alt="{{ $item->title ?? $item->filename }}" class="w-full h-full object-cover">
                    @else
                        <span class="text-gray-400 text-xs font-bold uppercase">{{ $item->extension }}</span>
                    @endif
                </div>
                
                {{-- Media Info --}}
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900 truncate">{{ $item->title ?? $item->filename }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $item->mime_type }}</p>
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                            {{ number_format($item->size / 1024, 1) }} KB
                        </span>
                        @if($item->width && $item->height)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                            {{ $item->width }}×{{ $item->height }}
                        </span>
                        @endif
                    </div>
                </div>
                
                {{-- Actions --}}
                <div class="flex flex-col gap-2">
                    <a href="/admin/media/{{ $item->id }}" class="text-blue-600 hover:text-blue-900 p-2 -m-2" title="View">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </a>
                    @if($permissions['can_delete'] ?? true)
                    <form action="/admin/media/{{ $item->id }}" method="POST" onsubmit="return confirm('Delete this file permanently?')">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="text-red-600 hover:text-red-900 p-2 -m-2" title="Delete">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
    
    @if(empty($media))
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No media found</h3>
            <p class="mt-1 text-sm text-gray-500">Get started by uploading files.</p>
            @if($permissions['can_upload'] ?? true)
            <div class="mt-4">
                <a href="/admin/media/upload" class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                    Upload Files
                </a>
            </div>
            @endif
        </div>
    @endif
</div>

{{-- Desktop: Grid Layout --}}
<div class="hidden sm:block">
    @if(!empty($media))
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
        @foreach($media as $item)
        <a href="/admin/media/{{ $item->id }}" class="group relative aspect-square bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-all">
            {{-- Thumbnail --}}
            <div class="absolute inset-0 flex items-center justify-center bg-gray-100">
                @if($item->is_image && $item->getThumbnailUrl())
                    <img src="{{ $item->getThumbnailUrl() }}" alt="{{ $item->title ?? $item->filename }}" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy">
                @else
                    <span class="text-gray-400 font-medium uppercase text-sm">{{ $item->extension }}</span>
                @endif
            </div>
            
            {{-- Info Overlay --}}
            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent p-3 opacity-0 group-hover:opacity-100 transition-opacity">
                <p class="text-white text-xs truncate">{{ $item->title ?? $item->filename }}</p>
                <p class="text-gray-300 text-[10px]">{{ number_format($item->size / 1024, 1) }} KB</p>
            </div>
        </a>
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
@endsection
