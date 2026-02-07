@extends('layouts.admin')

@section('content')
<div class="space-y-6" x-data="mediaEdit({
    id: {{ $media->id }},
    title: '{{ addslashes($media->title) }}',
    alt: '{{ addslashes($media->alt ?? '') }}',
    description: '{{ addslashes($media->description ?? '') }}'
})">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="/admin/media" class="p-2 -ml-2 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <!-- Title Display / Edit -->
                <template x-if="!isEditing">
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight" x-text="originalData.title"></h1>
                </template>
                <template x-if="isEditing">
                    <input type="text" x-model="form.title" class="text-2xl font-bold text-gray-900 tracking-tight border-b-2 border-primary-500 focus:outline-none bg-transparent w-full" placeholder="Enter title...">
                </template>
                
                <p class="text-sm text-gray-500">
                    Added on {{ $media->created_at->format('F j, Y \a\t g:i a') }} by 
                    <span class="font-medium text-gray-700">Author #{{ $media->author_id ?? 'Unknown' }}</span>
                </p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <!-- Edit / Save Buttons -->
             <button 
                type="button"
                @click="toggleEdit()"
                x-show="!isEditing"
                class="btn btn-secondary flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 rounded-lg border border-gray-300 shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </button>

             <button 
                type="button"
                @click="save()"
                x-show="isEditing"
                class="btn btn-primary flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-colors"
                :disabled="isSaving">
                <span x-show="isSaving" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
                <span x-text="isSaving ? 'Saving...' : 'Save Changes'"></span>
            </button>
            <button 
                type="button"
                @click="toggleEdit()"
                x-show="isEditing"
                class="btn btn-secondary px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 bg-transparent hover:bg-gray-100 rounded-lg transition-colors"
                :disabled="isSaving">
                Cancel
            </button>

            @if($permissions['can_delete'] ?? false)
                <!-- Hide Delete when editing to reduce clutter -->
                <form x-show="!isEditing" action="/admin/media/{{ $media->id }}" method="POST" hx-disable @submit.prevent="$dispatch('open-confirmation-modal', { 
                    title: 'Delete Media', 
                    message: 'Are you sure you want to permanently delete this media file? This action cannot be undone.', 
                    confirmText: 'Delete Forever',
                    isDanger: true,
                    onConfirm: () => $el.submit() 
                })">
                    <input type="hidden" name="_method" value="DELETE">
                    <input type="hidden" name="{{ $csrf_token_name ?? 'csrf_token' }}" value="{{ $csrf_token ?? '' }}">
                    <button 
                        type="submit"
                        class="btn btn-danger flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors border border-transparent focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Delete
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Preview Column -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Preview Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-2 overflow-hidden">
                <div class="aspect-video w-full bg-gray-50 rounded-xl flex items-center justify-center overflow-hidden relative group">
                    @if($media->isImage())
                        <img src="{{ $media->getUrl() }}" :alt="form.alt" class="max-w-full max-h-full object-contain shadow-sm">
                    @elseif($media->media_type === 'video')
                        <video controls class="max-w-full max-h-full">
                            <source src="{{ $media->getUrl() }}" type="{{ $media->mime_type }}">
                            Your browser does not support the video tag.
                        </video>
                    @elseif($media->media_type === 'audio')
                        <div class="w-full px-12">
                            <audio controls class="w-full">
                                <source src="{{ $media->getUrl() }}" type="{{ $media->mime_type }}">
                                Your browser does not support the audio element.
                            </audio>
                        </div>
                    @else
                        <!-- Generic File Icon -->
                        <div class="text-center p-8">
                            <div class="mx-auto w-24 h-24 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center mb-4">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900" x-text="originalData.title || '{{ $media->filename }}'"></h3>
                            <p class="text-gray-500">{{ $media->getFormattedSize() }}</p>
                        </div>
                    @endif

                    <!-- Overlay with quick actions -->
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-4">
                         <a href="{{ $media->getUrl() }}" target="_blank" class="p-3 bg-white/10 backdrop-blur-md hover:bg-white/20 text-white rounded-full transition-colors" title="Open in new tab">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                         </a>
                    </div>
                </div>
            </div>

            <!-- Description / Details -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <!-- Description Header -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Description</h3>
                </div>

                <!-- Display Mode -->
                <template x-if="!isEditing">
                    <div class="space-y-4">
                        @if($media->isImage())
                            <div x-show="originalData.alt">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Alt Text</h4>
                                <p class="text-gray-700" x-text="originalData.alt"></p>
                            </div>
                        @endif

                        <div>
                             <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Description</h4>
                             <p x-show="originalData.description" class="text-gray-600 leading-relaxed" x-text="originalData.description"></p>
                             <p x-show="!originalData.description" class="text-gray-400 italic">No description provided.</p>
                        </div>
                    </div>
                </template>

                <!-- Edit Mode -->
                <template x-if="isEditing">
                    <div class="space-y-4">
                        @if($media->isImage())
                        <label class="block">
                             <span class="block text-sm font-medium text-gray-700 mb-1">Alt Text</span>
                             <input type="text" x-model="form.alt" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm px-4 py-2 bg-gray-50" placeholder="Alternative text for accessibility">
                        </label>
                        @endif
                        <label class="block">
                            <span class="block text-sm font-medium text-gray-700 mb-1">Description</span>
                            <textarea x-model="form.description" rows="4" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm px-4 py-2 bg-gray-50" placeholder="Enter a detailed description..."></textarea>
                        </label>
                    </div>
                </template>
            </div>
        </div>

        <!-- Sidebar / Metadata Column -->
        <div class="space-y-6">
            <!-- File Info -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                    <h3 class="font-semibold text-gray-900">File Information</h3>
                </div>
                <div class="divide-y divide-gray-100">
                     <div class="px-6 py-3 flex justify-between items-center group hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-medium text-gray-500">Type</span>
                        <span class="text-sm text-gray-900 font-medium capitalize bg-gray-100 px-2.5 py-0.5 rounded-full">{{ $media->media_type }}</span>
                    </div>
                    <div class="px-6 py-3 flex justify-between items-center group hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-medium text-gray-500">Size</span>
                        <span class="text-sm text-gray-900 font-mono">{{ $media->getFormattedSize() }}</span>
                    </div>
                     <div class="px-6 py-3 flex justify-between items-center group hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-medium text-gray-500">Extension</span>
                        <span class="text-sm text-gray-900 font-mono uppercase">{{ $media->getExtension() }}</span>
                    </div>
                    @if($media->getDimensions())
                    <div class="px-6 py-3 flex justify-between items-center group hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-medium text-gray-500">Dimensions</span>
                        <span class="text-sm text-gray-900 font-mono">{{ $media->getDimensions() }} px</span>
                    </div>
                    @endif
                     <div class="px-6 py-3 flex justify-between items-center group hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-medium text-gray-500">MIME Type</span>
                        <span class="text-sm text-gray-900 font-mono text-xs" title="{{ $media->mime_type }}">{{ mb_strimwidth($media->mime_type, 0, 25, '...') }}</span>
                    </div>
                    <div class="px-6 py-3 flex justify-between items-center group hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-medium text-gray-500">Updated</span>
                        <span class="text-sm text-gray-900">{{ $media->updated_at?->format('Y-m-d H:i') ?? 'Never' }}</span>
                    </div>
                </div>
            </div>

            <!-- Public Link -->
             <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                    <h3 class="font-semibold text-gray-900">Public Link</h3>
                </div>
                <div class="p-6">
                    <div class="flex rounded-lg shadow-sm">
                        <input type="text" readonly value="{{ $media->getUrl() }}" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-l-lg border border-gray-300 text-sm text-gray-500 bg-gray-50 focus:ring-blue-500 focus:border-blue-500">
                        <button 
                            onclick="navigator.clipboard.writeText('{{ $media->getUrl() }}').then(() => alert('Copied to clipboard!'))"
                            class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 rounded-r-lg bg-gray-50 hover:bg-gray-100 text-gray-700 text-sm font-medium transition-colors">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </div>
                </div>
             </div>
             
             <!-- Technical Details -->
             <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                    <h3 class="font-semibold text-gray-900">Technical Details</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    <div class="px-6 py-3 flex flex-col gap-1">
                        <span class="text-xs font-medium text-gray-500 uppercase">UUID</span>
                        <span class="text-sm font-mono text-gray-700 break-all">{{ $media->uuid }}</span>
                    </div>
                     <div class="px-6 py-3 flex flex-col gap-1">
                        <span class="text-xs font-medium text-gray-500 uppercase">Disk</span>
                        <span class="text-sm text-gray-700">{{ $media->disk }}</span>
                    </div>
                     <div class="px-6 py-3 flex flex-col gap-1">
                        <span class="text-xs font-medium text-gray-500 uppercase">Folder</span>
                        <span class="text-sm text-gray-700">{{ $media->folder ?? '/' }}</span>
                    </div>
                     <div class="px-6 py-3 flex flex-col gap-1">
                        <span class="text-xs font-medium text-gray-500 uppercase">Path</span>
                        <span class="text-sm font-mono text-gray-700 break-all text-xs">{{ $media->path }}</span>
                    </div>
                </div>
             </div>
        </div>
    </div>
</div>
@endsection
