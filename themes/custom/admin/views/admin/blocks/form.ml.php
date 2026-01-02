@extends('layouts/admin')

@section('content')
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
                    <div class="sm:col-span-4">
                        <x-form.input 
                            name="admin_title" 
                            label="Admin Title" 
                            value="{{ $block->admin_title ?? '' }}" 
                            required 
                            help="Name used in admin list."
                        />
                    </div>

                    <div class="sm:col-span-4">
                        <x-form.input 
                            name="machine_name" 
                            label="Machine Name" 
                            value="{{ $block->machine_name ?? '' }}" 
                            class="font-mono bg-gray-50" 
                            help="Unique identifier (a-z, 0-9, _). Leave empty to generate from title."
                        />
                    </div>

                    <div class="sm:col-span-4">
                        <x-form.input 
                            name="title" 
                            label="Display Title" 
                            value="{{ $block->title ?? '' }}" 
                        />
                    </div>

                    <div class="sm:col-span-6">
                        <x-form.checkbox 
                            name="show_title" 
                            label="Show Title" 
                            :checked="$block->show_title ?? true"
                            help="Display the title above the block content."
                        />
                    </div>

                    <div class="sm:col-span-3">
                        <x-form.select 
                            name="block_type" 
                            label="Block Type" 
                            id="block_type"
                            :selected="$block->block_type"
                        >
                            <option value="content" <?= ($block->block_type === 'content') ? 'selected' : '' ?>>Content Block</option>
                            <option value="html" <?= ($block->block_type === 'html') ? 'selected' : '' ?>>Raw HTML</option>
                            <?php foreach ($types as $typeId => $typeData): ?>
                                <?php if ($typeId !== 'content' && $typeId !== 'html'): ?>
                                    <option value="<?= htmlspecialchars($typeId) ?>" <?= ($block->block_type === (string)$typeId) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($typeData['label']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </x-form.select>
                    </div>

                    <div class="sm:col-span-3">
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

                    <div class="sm:col-span-6">
                        <x-form.textarea 
                            name="body" 
                            label="Content" 
                            id="editor" 
                            rows="10"
                        >{{ $block->body ?? '' }}</x-form.textarea>

                        <div class="mt-4">
                            <x-form.select 
                                name="body_format" 
                                label="Content Format" 
                                id="body_format"
                                :selected="$block->body_format ?? 'html'"
                            >
                                <option value="html" <?= (($block->body_format ?? 'html') === 'html') ? 'selected' : '' ?>>HTML</option>
                                <option value="markdown" <?= (($block->body_format ?? '') === 'markdown') ? 'selected' : '' ?>>Markdown</option>
                                <option value="plain" <?= (($block->body_format ?? '') === 'plain') ? 'selected' : '' ?>>Plain Text</option>
                            </x-form.select>
                        </div>
                    </div>

                    <!-- Dynamic Fields -->
                    <?php if (!empty($renderedFields)): ?>
                    <div class="sm:col-span-6 border-t border-gray-200 pt-6 mt-2">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Block Settings</h3>
                        <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                            <?php foreach ($renderedFields as $field): ?>
                                <div class="sm:col-span-6">
                                    <label for="<?= htmlspecialchars($field['machine_name']) ?>" class="block text-sm font-medium text-gray-700">
                                        <?= htmlspecialchars($field['label']) ?>
                                    </label>
                                    <div class="mt-1">
                                        <?= $field['html'] ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="sm:col-span-6">
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
                            <div class="sm:col-span-3">
                                <x-form.input 
                                    name="css_class" 
                                    label="CSS Classes" 
                                    value="{{ $block->css_class ?? '' }}" 
                                    help="Space separated classes."
                                />
                            </div>
                            <div class="sm:col-span-3">
                                <x-form.input 
                                    name="css_id" 
                                    label="CSS ID" 
                                    value="{{ $block->css_id ?? '' }}" 
                                    help="Unique HTML ID."
                                />
                            </div>
                            <div class="sm:col-span-2">
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

@push('scripts')
<!-- Explicitly include CKEditor if not auto-loaded -->
<?php if (isset($assets) && $assets->hasLibrary('ckeditor')): ?>
    <!-- Loaded via AssetManager -->
<?php else: ?>
    <!-- Fallback if not loaded via asset manager -->
    <!-- <script src="https://cdn.ckeditor.com/ckeditor5/40.0.0/classic/ckeditor.js"></script> -->
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // CKEditor initialization
        if (typeof ClassicEditor !== 'undefined') {
            ClassicEditor
                .create(document.querySelector('#editor'), {
                    toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'undo', 'redo']
                })
                .catch(error => {
                    console.error(error);
                });
        } else {
             console.warn('CKEditor not loaded');
        }

        // Block Type change handler
        const blockTypeSelect = document.getElementById('block_type');
        if (blockTypeSelect) {
            blockTypeSelect.addEventListener('change', function() {
                const url = new URL(window.location.href);
                // Only reload if we are creating a new block to load specific fields
                // Simple check for 'create' in path or empty ID
                if (url.pathname.endsWith('/create')) {
                    url.searchParams.set('type', this.value);
                    window.location.href = url.toString();
                }
            });
        }
    });
</script>
@endpush
