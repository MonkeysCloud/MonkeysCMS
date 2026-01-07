@extends('layouts.admin')

@php
    // Define AlpineJS bindings as strings to bypass Template Engine parsing
    $dropZoneBind = "x-bind:class=\"isDragging ? 'border-blue-500 bg-blue-50 ring-4 ring-blue-100' : 'border-gray-300 hover:border-gray-400 bg-white'\"";
    $progressBarBind = "x-bind:class=\"file.status === 'uploading' ? 'bg-blue-600' : (file.status === 'success' ? 'bg-green-500' : (file.status === 'error' ? 'bg-red-500' : 'bg-gray-200'))\"";
    $statusTextBind = "x-bind:class=\"file.status === 'uploading' ? 'text-blue-600' : (file.status === 'success' ? 'text-green-600' : (file.status === 'error' ? 'text-red-600' : 'text-gray-500'))\"";
    $descColSpanBind = "x-bind:class=\"!file.isImage ? 'sm:col-span-2' : ''\"";
@endphp

@section('content')

<div class="page-header mb-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Upload Media</h1>
            <p class="text-gray-500 text-sm mt-1">Drag and drop files to upload or select from your computer.</p>
        </div>
        <a href="/admin/media" class="text-gray-600 hover:text-gray-900 flex items-center gap-2 text-sm font-medium">
            &larr; Back to Library
        </a>
    </div>
</div>

<div x-data="mediaUpload()" class="max-w-4xl mx-auto">
    <!-- Drop Zone -->
    <div 
        x-on:dragover.prevent="isDragging = true"
        x-on:dragleave.prevent="isDragging = false"
        x-on:drop.prevent="handleDrop($event)"
        {!! $dropZoneBind !!}
        class="relative border-3 border-dashed rounded-2xl p-12 text-center transition-all duration-200 cursor-pointer group"
        x-on:click="$refs.fileInput.click()"
    >
        <input type="file" x-ref="fileInput" multiple class="hidden" x-on:change="handleFiles($event.target.files)">
        
        <div class="pointer-events-none">
            <div class="w-20 h-20 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:scale-110 transition-transform duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
            </div>
            
            <h3 class="text-lg font-semibold text-gray-900 mb-2">
                Click to upload or drag and drop
            </h3>
            <p class="text-gray-500 text-sm max-w-sm mx-auto">
                SVG, PNG, JPG, GIF, PDF, or MP4 (max. 100MB)
            </p>
        </div>
    </div>

    <!-- Upload Queue -->
    <div x-show="uploads.length > 0" class="mt-8 space-y-4" x-transition>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Upload Queue</h3>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-500" x-text="getCompletedCount() + '/' + uploads.length + ' completed'"></span>
                <button 
                    x-on:click="startAll()"
                    x-show="uploads.some(u => u.status === 'pending')"
                    class="btn btn-primary px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-colors">
                    Upload All
                </button>
            </div>
        </div>

        <template x-for="file in uploads" :key="file.id">
            <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
                <div class="flex items-start gap-4">
                    <!-- Icon -->
                    <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0 mt-1">
                        <template x-if="file.type.startsWith('image/')">
                            <svg class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </template>
                        <template x-if="!file.type.startsWith('image/')">
                            <svg class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </template>
                    </div>

                    <!-- Info & Inputs -->
                    <div class="flex-1 min-w-0 space-y-3">
                        <div class="flex justify-between">
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 truncate" x-text="file.name"></h4>
                                <p class="text-xs text-gray-500" x-text="formatSize(file.size)"></p>
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex items-center gap-2">
                                <template x-if="file.status === 'pending' || file.status === 'error'">
                                    <button x-on:click="startUpload(file.id)" class="text-xs font-medium text-blue-600 hover:text-blue-800 bg-blue-50 px-3 py-1.5 rounded-md transition-colors">
                                        Upload
                                    </button>
                                </template>
                                <template x-if="file.status === 'uploading'">
                                    <button x-on:click="cancelUpload(file.id)" class="text-xs font-medium text-red-500 hover:text-red-700 bg-red-50 px-3 py-1.5 rounded-md transition-colors">Cancel</button>
                                </template>
                                <template x-if="file.status === 'pending'">
                                    <button x-on:click="cancelUpload(file.id)" class="text-gray-400 hover:text-gray-600">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Progress Bar (Visible when uploading or done) -->
                        <div x-show="file.status !== 'pending'" class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                            <div 
                                class="h-full rounded-full transition-all duration-300" 
                                {!! $progressBarBind !!}
                                :style="'width: ' + file.progress + '%'"
                            ></div>
                        </div>
                        
                        <div class="flex justify-between items-center" x-show="file.status !== 'pending'">
                            <span class="text-xs" 
                                {!! $statusTextBind !!}
                                x-text="file.statusText"></span>
                        </div>

                        <!-- Metadata Inputs (Only when pending) -->
                        <div x-show="file.status === 'pending' || file.status === 'error'" class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Title</label>
                                <input type="text" x-model="file.title" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-xs px-3 py-1.5 bg-gray-50">
                            </div>
                            
                            <template x-if="file.isImage">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Alt Text</label>
                                    <input type="text" x-model="file.alt" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-xs px-3 py-1.5 bg-gray-50" placeholder="Describe image...">
                                </div>
                            </template>
                            
                            <div {!! $descColSpanBind !!}>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                                <textarea x-model="file.description" rows="1" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-xs px-3 py-1.5 bg-gray-50" placeholder="Optional description..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>

<script src="/js/media-upload.js"></script>
@endsection


