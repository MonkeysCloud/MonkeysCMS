@extends('layouts/admin')

@section('content')

@push('styles')
<link rel="stylesheet" href="/vendor/easymde/easymde.min.css">
<style>
    .EasyMDEContainer { margin-bottom: 1.5rem; }
</style>
@endpush

@push('scripts')
<script src="/vendor/easymde/easymde.min.js"></script>
@endpush
<div class="max-w-4xl mx-auto py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
    </div>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <form action="{{ $action }}" method="POST">
                <?php if ($method !== 'POST'): ?>
                    <input type="hidden" name="_method" value="{{ $method }}">
                <?php endif; ?>
                
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="rounded-md bg-red-50 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">There were errors with your submission</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                    <!-- Block Type and Region Header -->
                    <div class="sm:col-span-6 bg-gray-50 -mx-4 px-4 py-4 sm:-mx-6 sm:px-6 border-b border-gray-200 mb-2">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Block Type</label>
                                <div class="mt-1 flex items-center gap-3">
                                    <span class="text-base font-medium text-gray-900">
                                        <?php 
                                            $currentType = $types[$block->block_type] ?? null;
                                            echo htmlspecialchars($currentType['label'] ?? $block->block_type);
                                        ?>
                                    </span>
                                    <?php if (!$block->id): ?>
                                        <a href="/admin/blocks/create" class="text-sm text-blue-600 hover:text-blue-500">(change)</a>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="block_type" value="{{ $block->block_type }}">
                                <?php if (!empty($currentType['description'])): ?>
                                    <p class="mt-1 text-sm text-gray-500"><?= htmlspecialchars($currentType['description']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <x-form.select 
                                    name="region" 
                                    label="Region" 
                                    id="region"
                                    :selected="$block->region"
                                >
                                    <option value="">-- None --</option>
                                    <option value="header" <?= ($block->region === 'header') ? 'selected' : '' ?>>Header</option>
                                    <option value="sidebar" <?= ($block->region === 'sidebar') ? 'selected' : '' ?>>Sidebar</option>
                                    <option value="content" <?= ($block->region === 'content') ? 'selected' : '' ?>>Content</option>
                                    <option value="footer" <?= ($block->region === 'footer') ? 'selected' : '' ?>>Footer</option>
                                </x-form.select>
                            </div>
                        </div>
                    </div>

                    <!-- Title Fields -->
                    <div class="sm:col-span-6">
                        <x-form.input 
                            name="admin_title" 
                            label="Admin Title" 
                            value="{{ $block->admin_title ?? '' }}" 
                            required 
                            help="Name used in admin list."
                        />
                    </div>


                    <div class="sm:col-span-6">
                        <x-form.input 
                            name="title" 
                            label="Display Title" 
                            value="{{ $block->title ?? '' }}" 
                            help="Title shown to visitors."
                        />
                    </div>

                    <div class="sm:col-span-6 flex items-end pb-1">
                        <x-form.checkbox 
                            name="show_title" 
                            label="Show Title" 
                            :checked="$block->show_title ?? true"
                            help="Display the title above the block content."
                        />
                    </div>

                    <div class="sm:col-span-6">
                        <label for="body" class="block text-sm font-medium text-gray-700">Content</label>
                        <div class="mt-1">
                            <?= $renderedBodyField['html'] ?? '' ?>
                        </div>

                        <div class="mt-4">
                            <?php 
                                // Use query param if present, otherwise use database value
                                $currentFormat = $_GET['body_format'] ?? ($block->body_format ?? 'html');
                            ?>
                            <label for="body_format" class="block text-sm font-medium text-gray-700 mb-1">Content Format</label>
                            <select 
                                name="body_format" 
                                id="body_format"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2"
                            >
                                <option value="html" <?= $currentFormat === 'html' ? 'selected' : '' ?>>HTML</option>
                                <option value="markdown" <?= $currentFormat === 'markdown' ? 'selected' : '' ?>>Markdown</option>
                                <option value="plain" <?= $currentFormat === 'plain' ? 'selected' : '' ?>>Plain Text</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Changing format will reload the editor</p>
                        </div>
                    </div>
                    
                    <script>
                        document.getElementById('body_format')?.addEventListener('change', function() {
                            const url = new URL(window.location.href);
                            url.searchParams.set('body_format', this.value);
                            window.location.href = url.toString();
                        });
                    </script>

                    <!-- Dynamic Fields -->
                    <?php if (!empty($renderedFields)): ?>
                        <?php foreach ($renderedFields as $field): ?>
                            <div class="sm:col-span-6">
                                <div class="mt-1">
                                    <?= $field['html'] ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="sm:col-span-6 pt-4 border-t border-gray-200">
                        <x-form.checkbox 
                            name="is_published" 
                            label="Published" 
                            :checked="$block->is_published ?? true"
                            help="Make this block visible on the site."
                        />
                    </div>

                    <div class="sm:col-span-6 border-t border-gray-200 pt-6 mt-2">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Visibility Settings</h3>
                        <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                            <div class="sm:col-span-3">
                                <x-form.select 
                                    name="visibility_mode" 
                                    label="Visibility Mode" 
                                    id="visibility_mode"
                                    :selected="$block->visibility_mode ?? 'all'"
                                >
                                    <option value="all" <?= (($block->visibility_mode ?? 'all') === 'all') ? 'selected' : '' ?>>Show on all pages</option>
                                    <option value="show" <?= (($block->visibility_mode ?? '') === 'show') ? 'selected' : '' ?>>Show on listed pages</option>
                                    <option value="hide" <?= (($block->visibility_mode ?? '') === 'hide') ? 'selected' : '' ?>>Hide on listed pages</option>
                                </x-form.select>
                            </div>

                            <div class="sm:col-span-6">
                                <x-form.textarea 
                                    name="visibility_pages" 
                                    label="Pages" 
                                    rows="4"
                                    help="List pages (paths) to match. One per line. Use * for wildcards (e.g. /blog/*). The front page is /"
                                >{{ implode("\n", $block->visibility_pages ?? []) }}</x-form.textarea>
                            </div>

                            <div class="sm:col-span-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Roles</label>
                                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                    <div class="flex items-start">
                                        <div class="flex h-5 items-center">
                                            <input type="checkbox" name="visibility_roles[]" value="anonymous" id="role_anonymous" 
                                                <?= in_array('anonymous', $block->visibility_roles ?? []) ? 'checked' : '' ?>
                                                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="role_anonymous" class="font-medium text-gray-700">Anonymous</label>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <div class="flex h-5 items-center">
                                            <input type="checkbox" name="visibility_roles[]" value="authenticated" id="role_authenticated"
                                                <?= in_array('authenticated', $block->visibility_roles ?? []) ? 'checked' : '' ?>
                                                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="role_authenticated" class="font-medium text-gray-700">Authenticated</label>
                                        </div>
                                    </div>
                                    <!-- In a real app we would loop through all available roles -->
                                </div>
                                <p class="mt-2 text-sm text-gray-500">Leaving all unchecked means visible to everyone (or limited by page only).</p>
                            </div>
                        </div>
                    </div>

                    <div class="sm:col-span-6 border-t border-gray-200 pt-6 mt-2">
                         <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Advanced Settings</h3>
                         <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                            <div class="sm:col-span-6">
                                <x-form.input 
                                    name="css_class" 
                                    label="CSS Classes" 
                                    value="{{ $block->css_class ?? '' }}" 
                                    help="Space separated classes."
                                />
                            </div>
                            <div class="sm:col-span-6">
                                <x-form.input 
                                    name="css_id" 
                                    label="CSS ID" 
                                    value="{{ $block->css_id ?? '' }}" 
                                    help="Unique HTML ID."
                                />
                            </div>
                            <div class="sm:col-span-6">
                                <x-form.input 
                                    name="weight" 
                                    type="number"
                                    label="Weight" 
                                    value="{{ $block->weight ?? 0 }}" 
                                    help="Ordering priority (lower matches first/higher)."
                                />
                            </div>
                         </div>
                    </div>
                </div>

                <div class="pt-5 flex justify-end gap-3">
                    <x-ui.button href="/admin/blocks" color="secondary">
                        Cancel
                    </x-ui.button>
                    <x-ui.button type="submit">
                        Save Block
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
