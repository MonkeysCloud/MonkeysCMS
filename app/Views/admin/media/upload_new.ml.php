@extends('layouts.admin')

@section('content')
@php
    // Define AlpineJS bindings as strings to bypass Template Engine parsing
    $dropZoneBind = "x-bind:class=\"isDragging ? 'border-blue-500 bg-blue-50 ring-4 ring-blue-100' : 'border-gray-300 hover:border-gray-400 bg-white'\"";
    $progressBarBind = "x-bind:class=\"file.status === 'uploading' ? 'bg-blue-600' : (file.status === 'success' ? 'bg-green-500' : (file.status === 'error' ? 'bg-red-500' : 'bg-gray-200'))\"";
    $statusTextBind = "x-bind:class=\"file.status === 'uploading' ? 'text-blue-600' : (file.status === 'success' ? 'text-green-600' : (file.status === 'error' ? 'text-red-600' : 'text-gray-500'))\"";
@endphp

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
            <h3 class="text-lg font-medium text-gray-900">Uploading Files</h3>
            <span class="text-sm text-gray-500" x-text="getCompletedCount() + '/' + uploads.length + ' completed'"></span>
        </div>

        <template x-for="file in uploads" :key="file.id">
            <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm flex items-center gap-4">
                <!-- Icon -->
                <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                    <template x-if="file.type.startsWith('image/')">
                        <svg class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </template>
                    <template x-if="!file.type.startsWith('image/')">
                        <svg class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </template>
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between mb-1">
                        <span class="text-sm font-medium text-gray-900 truncate" x-text="file.name"></span>
                        <span class="text-xs text-gray-500" x-text="formatSize(file.size)"></span>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                        <div 
                            class="h-full rounded-full transition-all duration-300" 
                            {!! $progressBarBind !!}
                            :style="'width: ' + file.progress + '%'"
                        ></div>
                    </div>
                    
                    <!-- Status Text -->
                    <div class="flex justify-between mt-1">
                        <span class="text-xs" 
                            {!! $statusTextBind !!}
                            x-text="file.statusText"></span>
                        
                         <template x-if="file.status === 'uploading'">
                             <button x-on:click="cancelUpload(file.id)" class="text-xs text-red-500 hover:text-red-700">Cancel</button>
                         </template>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>

<script src="/js/media-upload.js"></script>
@endsection

