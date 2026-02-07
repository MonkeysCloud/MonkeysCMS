@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-4">
                <a href="/admin/structure/content-types" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h2 class="text-2xl font-bold text-gray-900">{{ $title }}</h2>
            </div>
        </div>
    </div>

    @if(!empty($type['type_id']))
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex gap-8" aria-label="Tabs">
            <a href="/admin/structure/content-types/{{ $type['type_id'] }}/edit"
               class="border-blue-500 text-blue-600 group inline-flex items-center border-b-2 py-4 px-1 text-sm font-medium" aria-current="page">
               <svg class="-ml-0.5 mr-2 h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                   <path fill-rule="evenodd" d="M11.5 2a.75.75 0 01.75.75L15 6h-2.25a.75.75 0 01-.75-.75V2.5zm-2.25 0a.75.75 0 00-.75.75V6a2.25 2.25 0 002.25 2.25h3.75a.75.75 0 00.75-.75V2.5a.75.75 0 00-.75-.75H9.25zM5 2.25A2.25 2.25 0 002.75 4.5v11A2.25 2.25 0 005 17.75h9.5A2.25 2.25 0 0016.75 15.5V9.5a.75.75 0 00-.75-.75H12a3.75 3.75 0 01-3.75-3.75V2.75a.75.75 0 00-.75-.75H5z" clip-rule="evenodd" />
               </svg>
               <span>Settings</span>
            </a>
            <a href="/admin/structure/content-types/{{ $type['type_id'] }}/fields"
               class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 group inline-flex items-center border-b-2 py-4 px-1 text-sm font-medium">
               <svg class="-ml-0.5 mr-2 h-5 w-5 text-gray-400 group-hover:text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                   <path fill-rule="evenodd" d="M2 3.75A.75.75 0 012.75 3h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 3.75zm0 4.167a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75a.75.75 0 01-.75-.75zm0 4.166a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75a.75.75 0 01-.75-.75zm0 4.167a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75a.75.75 0 01-.75-.75z" clip-rule="evenodd" />
               </svg>
               <span>Manage Fields</span>
            </a>
            <a href="/admin/structure/content-types/{{ $type['type_id'] }}/form-display"
               class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 group inline-flex items-center border-b-2 py-4 px-1 text-sm font-medium">
               <svg class="-ml-0.5 mr-2 h-5 w-5 text-gray-400 group-hover:text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                   <path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" />
               </svg>
               <span>Form Display</span>
            </a>
            <a href="/admin/structure/content-types/{{ $type['type_id'] }}/display"
               class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 group inline-flex items-center border-b-2 py-4 px-1 text-sm font-medium">
               <svg class="-ml-0.5 mr-2 h-5 w-5 text-gray-400 group-hover:text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                   <path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
                   <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
               </svg>
               <span>Display</span>
            </a>
        </nav>
    </div>
    @endif

    <x-ui.card class="max-w-3xl">
        @if(isset($error))
            <div class="rounded-md bg-red-50 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Error</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p>{{ $error }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @php
            $typeLabel = $type['label'] ?? '';
            $typeId = $type['type_id'] ?? '';
            $typeLabelPlural = $type['label_plural'] ?? '';
            $typeDescription = $type['description'] ?? '';
            $typeIcon = $type['icon'] ?? '📄';
            $typePublishable = $type['publishable'] ?? true;
            $typeRevisionable = $type['revisionable'] ?? false;
            $typeTranslatable = $type['translatable'] ?? false;
            $typeHasAuthor = $type['has_author'] ?? true;
            $typeHasTaxonomy = $type['has_taxonomy'] ?? true;
            $typeHasMedia = $type['has_media'] ?? true;
            $typeTitleField = $type['title_field'] ?? 'title';
            $typeSlugField = $type['slug_field'] ?? 'slug';
            $typeUrlPattern = $type['url_pattern'] ?? '';
            $typeEnabled = $type['enabled'] ?? true;
            $typeComposerEnabled = $type['composer_enabled'] ?? false;
            $typeComposerDefault = $type['composer_default'] ?? false;
        @endphp

        <form method="post" action="{{ $action }}">
            
            <h3 class="text-lg font-medium text-gray-900 mb-4">Basic Settings</h3>
            
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label for="label" class="block text-sm font-medium text-gray-700 mb-1">Label</label>
                    <input type="text" name="label" id="label" value="{{ $typeLabel }}" required
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                </div>
                <div>
                    <label for="label_plural" class="block text-sm font-medium text-gray-700 mb-1">Label Plural</label>
                    <input type="text" name="label_plural" id="label_plural" value="{{ $typeLabelPlural }}" placeholder="Auto-generated if empty"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                </div>
            </div>

            <div class="mb-4">
                <label for="type_id" class="block text-sm font-medium text-gray-700 mb-1">Machine Name (ID)</label>
                <input type="text" name="type_id" id="type_id" value="{{ $typeId }}" placeholder="Auto-generated if empty"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2" {{ isset($is_edit) && $is_edit ? 'readonly' : '' }}>
                <p class="mt-1 text-sm text-gray-500">Unique identifier for this content type (e.g. 'article', 'product').</p>
            </div>

            <div class="mb-4">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" id="description" rows="2"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">{{ $typeDescription }}</textarea>
            </div>

            <div class="mb-6">
                <label for="icon" class="block text-sm font-medium text-gray-700 mb-1">Icon</label>
                <input type="text" name="icon" id="icon" value="{{ $typeIcon }}"
                    class="block w-24 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                <p class="mt-1 text-sm text-gray-500">Emoji icon for this content type.</p>
            </div>

            <h3 class="text-lg font-medium text-gray-900 mb-4 pt-4 border-t">Features</h3>

            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="relative flex items-start">
                    <div class="flex h-5 items-center">
                        <input type="hidden" name="publishable" value="0">
                        <input type="checkbox" name="publishable" id="publishable" value="1"
                            {{ $typePublishable ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="publishable" class="font-medium text-gray-700">Publishable</label>
                        <p class="text-gray-500">Content can be published/unpublished</p>
                    </div>
                </div>

                <div class="relative flex items-start">
                    <div class="flex h-5 items-center">
                        <input type="hidden" name="revisionable" value="0">
                        <input type="checkbox" name="revisionable" id="revisionable" value="1"
                            {{ $typeRevisionable ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="revisionable" class="font-medium text-gray-700">Revisions</label>
                        <p class="text-gray-500">Track content revisions</p>
                    </div>
                </div>

                <div class="relative flex items-start">
                    <div class="flex h-5 items-center">
                        <input type="hidden" name="translatable" value="0">
                        <input type="checkbox" name="translatable" id="translatable" value="1"
                            {{ $typeTranslatable ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="translatable" class="font-medium text-gray-700">Translatable</label>
                        <p class="text-gray-500">Support multiple languages</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="relative flex items-start">
                    <div class="flex h-5 items-center">
                        <input type="hidden" name="has_author" value="0">
                        <input type="checkbox" name="has_author" id="has_author" value="1"
                            {{ $typeHasAuthor ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="has_author" class="font-medium text-gray-700">Has Author</label>
                        <p class="text-gray-500">Track content author</p>
                    </div>
                </div>

                <div class="relative flex items-start">
                    <div class="flex h-5 items-center">
                        <input type="hidden" name="has_taxonomy" value="0">
                        <input type="checkbox" name="has_taxonomy" id="has_taxonomy" value="1"
                            {{ $typeHasTaxonomy ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="has_taxonomy" class="font-medium text-gray-700">Has Taxonomy</label>
                        <p class="text-gray-500">Categorize with terms</p>
                    </div>
                </div>

                <div class="relative flex items-start">
                    <div class="flex h-5 items-center">
                        <input type="hidden" name="has_media" value="0">
                        <input type="checkbox" name="has_media" id="has_media" value="1"
                            {{ $typeHasMedia ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="has_media" class="font-medium text-gray-700">Has Media</label>
                        <p class="text-gray-500">Attach media files</p>
                    </div>
                </div>
            </div>

            <h3 class="text-lg font-medium text-gray-900 mb-4 pt-4 border-t">Content Composer</h3>
            <p class="text-sm text-gray-500 mb-4">Enable the visual layout builder for this content type.</p>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="relative flex items-start">
                    <div class="flex h-5 items-center">
                        <input type="hidden" name="composer_enabled" value="0">
                        <input type="checkbox" name="composer_enabled" id="composer_enabled" value="1"
                            {{ $typeComposerEnabled ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="composer_enabled" class="font-medium text-gray-700">Enable Composer</label>
                        <p class="text-gray-500">Allow visual layout editing</p>
                    </div>
                </div>

                <div class="relative flex items-start">
                    <div class="flex h-5 items-center">
                        <input type="hidden" name="composer_default" value="0">
                        <input type="checkbox" name="composer_default" id="composer_default" value="1"
                            {{ $typeComposerDefault ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="composer_default" class="font-medium text-gray-700">Default for New Content</label>
                        <p class="text-gray-500">Use composer by default</p>
                    </div>
                </div>
            </div>

            <h3 class="text-lg font-medium text-gray-900 mb-4 pt-4 border-t">URL & Fields</h3>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="title_field" class="block text-sm font-medium text-gray-700 mb-1">Title Field</label>
                    <input type="text" name="title_field" id="title_field" value="{{ $typeTitleField }}"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                </div>
                <div>
                    <label for="slug_field" class="block text-sm font-medium text-gray-700 mb-1">Slug Field</label>
                    <input type="text" name="slug_field" id="slug_field" value="{{ $typeSlugField }}"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                </div>
            </div>

            <div class="mb-6">
                <label for="url_pattern" class="block text-sm font-medium text-gray-700 mb-1">URL Pattern</label>
                <input type="text" name="url_pattern" id="url_pattern" value="{{ $typeUrlPattern }}" placeholder="/{type}/{slug}"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 font-mono">
                
                <!-- Collapsible Token Reference -->
                <details class="mt-3 group">
                    <summary class="cursor-pointer text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                        <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        Show available tokens
                    </summary>
                    <div class="mt-3 p-4 bg-gradient-to-br from-gray-50 to-slate-50 rounded-lg border border-gray-200">
                        <!-- Tokens Grid -->
                        <div class="mb-4">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Tokens <span class="font-normal normal-case">(click to copy)</span></h4>
                            <div class="flex flex-wrap gap-2" id="token-list">
                                <button type="button" onclick="copyToken('{title}')" class="group inline-flex items-center px-2.5 py-1.5 rounded-md text-xs bg-white border border-gray-300 hover:border-blue-400 hover:bg-blue-50 transition-colors cursor-pointer shadow-sm">
                                    <code class="text-blue-600 font-mono font-medium">{title}</code>
                                    <span class="ml-2 text-gray-400 group-hover:text-gray-600">Title</span>
                                </button>
                                <button type="button" onclick="copyToken('{slug}')" class="group inline-flex items-center px-2.5 py-1.5 rounded-md text-xs bg-white border border-gray-300 hover:border-green-400 hover:bg-green-50 transition-colors cursor-pointer shadow-sm">
                                    <code class="text-green-600 font-mono font-medium">{slug}</code>
                                    <span class="ml-2 text-gray-400 group-hover:text-gray-600">URL Slug</span>
                                </button>
                                <button type="button" onclick="copyToken('{id}')" class="group inline-flex items-center px-2.5 py-1.5 rounded-md text-xs bg-white border border-gray-300 hover:border-purple-400 hover:bg-purple-50 transition-colors cursor-pointer shadow-sm">
                                    <code class="text-purple-600 font-mono font-medium">{id}</code>
                                    <span class="ml-2 text-gray-400 group-hover:text-gray-600">ID</span>
                                </button>
                                <button type="button" onclick="copyToken('{type}')" class="group inline-flex items-center px-2.5 py-1.5 rounded-md text-xs bg-white border border-gray-300 hover:border-orange-400 hover:bg-orange-50 transition-colors cursor-pointer shadow-sm">
                                    <code class="text-orange-600 font-mono font-medium">{type}</code>
                                    <span class="ml-2 text-gray-400 group-hover:text-gray-600">Content Type</span>
                                </button>
                                <button type="button" onclick="copyToken('{author}')" class="group inline-flex items-center px-2.5 py-1.5 rounded-md text-xs bg-white border border-gray-300 hover:border-pink-400 hover:bg-pink-50 transition-colors cursor-pointer shadow-sm">
                                    <code class="text-pink-600 font-mono font-medium">{author}</code>
                                    <span class="ml-2 text-gray-400 group-hover:text-gray-600">Author</span>
                                </button>
                                <button type="button" onclick="copyToken('{created}')" class="group inline-flex items-center px-2.5 py-1.5 rounded-md text-xs bg-white border border-gray-300 hover:border-cyan-400 hover:bg-cyan-50 transition-colors cursor-pointer shadow-sm">
                                    <code class="text-cyan-600 font-mono font-medium">{created}</code>
                                    <span class="ml-2 text-gray-400 group-hover:text-gray-600">Date</span>
                                </button>
                                <button type="button" onclick="copyToken('{category}')" class="group inline-flex items-center px-2.5 py-1.5 rounded-md text-xs bg-white border border-gray-300 hover:border-yellow-400 hover:bg-yellow-50 transition-colors cursor-pointer shadow-sm">
                                    <code class="text-yellow-600 font-mono font-medium">{category}</code>
                                    <span class="ml-2 text-gray-400 group-hover:text-gray-600">Taxonomy</span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Modifiers -->
                        <div class="mb-4">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Modifiers <span class="font-normal normal-case">(click to copy)</span></h4>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" onclick="copyToken('|slug')" class="px-2.5 py-1 rounded text-xs font-mono bg-gray-800 text-gray-100 hover:bg-gray-700 transition-colors cursor-pointer">|slug</button>
                                <button type="button" onclick="copyToken('|lower')" class="px-2.5 py-1 rounded text-xs font-mono bg-gray-800 text-gray-100 hover:bg-gray-700 transition-colors cursor-pointer">|lower</button>
                                <button type="button" onclick="copyToken('|upper')" class="px-2.5 py-1 rounded text-xs font-mono bg-gray-800 text-gray-100 hover:bg-gray-700 transition-colors cursor-pointer">|upper</button>
                                <button type="button" onclick="copyToken('|truncate:')" class="px-2.5 py-1 rounded text-xs font-mono bg-gray-800 text-gray-100 hover:bg-gray-700 transition-colors cursor-pointer">|truncate:N</button>
                                <button type="button" onclick="copyToken('|format:')" class="px-2.5 py-1 rounded text-xs font-mono bg-gray-800 text-gray-100 hover:bg-gray-700 transition-colors cursor-pointer">|format:X</button>
                            </div>
                        </div>
                        
                        <!-- Examples -->
                        <div>
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Examples</h4>
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 text-xs">
                                    <code class="px-2 py-1 bg-white rounded border font-mono text-gray-700">/{type}/{slug}</code>
                                    <span class="text-gray-400">→</span>
                                    <span class="text-gray-600">/blog/my-article</span>
                                </div>
                                <div class="flex items-center gap-2 text-xs">
                                    <code class="px-2 py-1 bg-white rounded border font-mono text-gray-700">/{created|format:Y}/{slug}</code>
                                    <span class="text-gray-400">→</span>
                                    <span class="text-gray-600">/2026/my-article</span>
                                </div>
                                <div class="flex items-center gap-2 text-xs">
                                    <code class="px-2 py-1 bg-white rounded border font-mono text-gray-700">/{author}/{type}/{title|truncate:30}</code>
                                    <span class="text-gray-400">→</span>
                                    <span class="text-gray-600">/john-doe/blog/my-very-long-article-ti</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </details>
            </div>

            <div class="mb-4">
                <div class="relative flex items-start">
                    <div class="flex h-5 items-center">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" id="enabled" value="1"
                            {{ $typeEnabled ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="enabled" class="font-medium text-gray-700">Enabled</label>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-gray-100 flex justify-end">
                <x-ui.button type="submit">
                    Save Content Type
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <script>
    function copyToken(token) {
        const urlInput = document.getElementById('url_pattern');
        
        // Insert at cursor position or append
        if (urlInput && document.activeElement === urlInput) {
            const start = urlInput.selectionStart;
            const end = urlInput.selectionEnd;
            const value = urlInput.value;
            urlInput.value = value.slice(0, start) + token + value.slice(end);
            urlInput.selectionStart = urlInput.selectionEnd = start + token.length;
            urlInput.focus();
        } else {
            // Copy to clipboard
            navigator.clipboard.writeText(token).then(() => {
                showToast('Copied: ' + token);
            });
        }
    }
    
    function showToast(message) {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-4 right-4 bg-gray-900 text-white px-4 py-2 rounded-lg shadow-lg text-sm font-mono z-50 animate-fade-in';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        // Remove after 2 seconds
        setTimeout(() => {
            toast.classList.add('opacity-0', 'transition-opacity');
            setTimeout(() => toast.remove(), 300);
        }, 1500);
    }
    </script>

    <style>
    @keyframes fade-in {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
        animation: fade-in 0.2s ease-out;
    }
    </style>
@endsection
