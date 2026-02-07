@extends('layouts/admin')

@section('content')
<?php
/**
 * Content Composer - Visual Editor
 * 
 * @var array $composerData Current layout data
 * @var array $blocks Available block types
 * @var array $layouts Available row layouts
 * @var array $fields Content type fields
 * @var string $entityType 'node' or 'block'
 * @var string|null $entityId Entity ID
 * @var string $title Page title
 */

// JSON encode with all flags to make it safe for script tag
$composerConfig = json_encode([
    'data' => $composerData,
    'entityType' => $entityType,
    'entityId' => $entityId,
    'blocks' => $blocks,
    'layouts' => $layouts,
    'fields' => $fields,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

// Alpine bindings as PHP strings to avoid template engine parsing
$bind = [
    'modeDesktop' => "x-bind:class=\"mode === 'desktop' ? 'bg-white shadow' : ''\"",
    'modeTablet' => "x-bind:class=\"mode === 'tablet' ? 'bg-white shadow' : ''\"",
    'modeMobile' => "x-bind:class=\"mode === 'mobile' ? 'bg-white shadow' : ''\"",
    'tabBlocks' => "x-bind:class=\"sidebarTab === 'blocks' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500'\"",
    'tabFields' => "x-bind:class=\"sidebarTab === 'fields' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500'\"",
    'tabSaved' => "x-bind:class=\"sidebarTab === 'saved' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500'\"",
    'previewCanvas' => "x-bind:class=\"previewMode === 'tablet' ? 'max-w-[768px] mx-auto' : (previewMode === 'mobile' ? 'max-w-[375px] mx-auto' : '')\"",
];
?>

<!-- Composer Config Data -->
<script>
    window.COMPOSER_CONFIG = <?= $composerConfig ?>;
</script>

<!-- Page Header -->
<div class="md:flex md:items-center md:justify-between mb-6">
    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-4">
            <a href="/admin/content" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">
                <?= htmlspecialchars($title) ?>
            </h2>
        </div>
    </div>
    <div class="mt-4 flex md:ml-4 md:mt-0 gap-3">
        <!-- Device Preview Buttons -->
        <div class="flex bg-gray-100 rounded-lg p-1" x-data="{ mode: 'desktop' }">
            <button @click="$dispatch('preview-mode', 'desktop')" 
                    <?= $bind['modeDesktop'] ?>
                    @preview-mode.window="mode = $event.detail"
                    class="px-3 py-1 rounded text-sm" title="Desktop">
                🖥️
            </button>
            <button @click="$dispatch('preview-mode', 'tablet')"
                    <?= $bind['modeTablet'] ?>
                    @preview-mode.window="mode = $event.detail"
                    class="px-3 py-1 rounded text-sm" title="Tablet">
                📱
            </button>
            <button @click="$dispatch('preview-mode', 'mobile')"
                    <?= $bind['modeMobile'] ?>
                    @preview-mode.window="mode = $event.detail"
                    class="px-3 py-1 rounded text-sm" title="Mobile">
                📲
            </button>
        </div>
        
        <!-- Save Button -->
        <button id="composer-save-btn" 
                class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
            <span class="save-text">Save Layout</span>
            <span class="saving-text hidden">Saving...</span>
        </button>
    </div>
</div>

<!-- Composer Editor -->
<div x-data="composerEditor(window.COMPOSER_CONFIG)" 
     x-init="init()"
     @preview-mode.window="previewMode = $event.detail"
     class="flex gap-6 -mx-4 sm:-mx-6 lg:-mx-8">
    
    <!-- Block Sidebar -->
    <div class="w-72 bg-white rounded-lg shadow-sm border border-gray-200 flex-shrink-0 self-start sticky top-4">
        <!-- Sidebar Tabs -->
        <div class="flex border-b border-gray-200">
            <button @click="sidebarTab = 'blocks'" 
                    <?= $bind['tabBlocks'] ?>
                    class="flex-1 py-3 text-sm font-medium">
                Blocks
            </button>
            <button @click="sidebarTab = 'fields'" 
                    <?= $bind['tabFields'] ?>
                    class="flex-1 py-3 text-sm font-medium">
                Fields
            </button>
            <button @click="sidebarTab = 'saved'" 
                    <?= $bind['tabSaved'] ?>
                    class="flex-1 py-3 text-sm font-medium">
                Saved
            </button>
        </div>

        <!-- Search -->
        <div class="p-3 border-b border-gray-100">
            <input type="text" x-model="blockSearch" placeholder="Search blocks..."
                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Block Library -->
        <div x-show="sidebarTab === 'blocks'" class="max-h-[60vh] overflow-y-auto p-3">
            <template x-for="(categoryBlocks, category) in filteredBlocks" :key="category">
                <div class="mb-4">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase mb-2" x-text="category"></h3>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="block in categoryBlocks" :key="block.type">
                            <div class="p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-colors"
                                 draggable="true"
                                 @click="addBlockFromSidebar(block)"
                                 @dragstart="onBlockDragStart($event, block)">
                                <div class="text-2xl mb-1" x-text="block.icon"></div>
                                <div class="text-xs font-medium text-gray-700" x-text="block.label"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <!-- Fields Library -->
        <div x-show="sidebarTab === 'fields'" class="max-h-[60vh] overflow-y-auto p-3">
            <p class="text-xs text-gray-500 mb-3">Click or drag fields to add to your layout</p>
            <div class="space-y-2">
                <template x-for="field in fields" :key="field.machine_name">
                    <div class="p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-green-400 hover:bg-green-50 transition-colors flex items-center gap-3"
                         draggable="true"
                         @click="addFieldFromSidebar(field)"
                         @dragstart="onFieldDragStart($event, field)">
                        <span class="text-xl">📋</span>
                        <div>
                            <div class="text-sm font-medium text-gray-700" x-text="field.name || field.label"></div>
                            <div class="text-xs text-gray-400" x-text="field.field_type"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Saved Sections -->
        <div x-show="sidebarTab === 'saved'" class="max-h-[60vh] overflow-y-auto p-3">
            <p class="text-xs text-gray-500 mb-3">Saved sections for reuse</p>
            <template x-if="savedSections.length === 0">
                <p class="text-sm text-gray-400 text-center py-8">No saved sections yet</p>
            </template>
        </div>
    </div>

    <!-- Canvas Area -->
    <div class="flex-1 min-w-0">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 min-h-[70vh]"
             <?= $bind['previewCanvas'] ?>
             @dragover.prevent
             @drop="onCanvasDrop($event)">
            
            <!-- Empty State -->
            <template x-if="data.sections.length === 0">
                <div class="flex flex-col items-center justify-center py-20 text-gray-400 border-2 border-dashed border-gray-200 rounded-lg">
                    <svg class="w-12 h-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                    </svg>
                    <p class="text-lg font-medium mb-2">Start building your layout</p>
                    <p class="text-sm">Drag blocks from the sidebar or click below</p>
                    <button @click="addSection()" class="mt-4 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        + Add Section
                    </button>
                </div>
            </template>

            <!-- Sections -->
            <div id="sections-container" class="space-y-4">
                <template x-for="(section, sectionIndex) in data.sections" :key="section.id">
                    <div class="composer-section group border-2 border-transparent hover:border-blue-300 rounded-lg relative transition-colors"
                         x-bind:data-section-id="section.id"
                         @dragover.prevent="onSectionDragOver($event)"
                         @dragleave="onSectionDragLeave($event)"
                         @drop="onSectionDrop($event, sectionIndex)">
                        
                        <!-- Section Toolbar -->
                        <div class="absolute -top-3 left-4 opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1 bg-white px-2 py-1 rounded shadow text-xs z-10">
                            <span class="text-gray-500 font-medium">Section</span>
                            <button @click="duplicateSection(sectionIndex)" class="p-1 hover:bg-gray-100 rounded" title="Duplicate">📋</button>
                            <button @click="deleteSection(sectionIndex)" class="p-1 hover:bg-gray-100 rounded text-red-500" title="Delete">🗑️</button>
                        </div>

                        <div class="p-4 min-h-[100px]" x-bind:style="getSectionStyles(section)">
                            <!-- Blocks in Section -->
                            <div class="blocks-container space-y-3">
                                <template x-for="(block, blockIndex) in section.blocks" :key="block.id">
                                    <div class="composer-block bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow group/block relative"
                                         x-bind:data-block-id="block.id"
                                         draggable="true"
                                         @dragstart="onBlockItemDragStart($event, sectionIndex, blockIndex)">
                                        
                                        <!-- Block Toolbar -->
                                        <div class="absolute -top-2 right-2 opacity-0 group-hover/block:opacity-100 transition-opacity flex items-center gap-1 bg-gray-800 text-white px-2 py-1 rounded text-xs z-10">
                                            <button @click="moveBlockUp(sectionIndex, blockIndex)" class="p-1 hover:bg-gray-700 rounded" title="Move Up">⬆️</button>
                                            <button @click="moveBlockDown(sectionIndex, blockIndex)" class="p-1 hover:bg-gray-700 rounded" title="Move Down">⬇️</button>
                                            <button @click="duplicateBlock(sectionIndex, blockIndex)" class="p-1 hover:bg-gray-700 rounded" title="Duplicate">📋</button>
                                            <button @click="deleteBlock(sectionIndex, blockIndex)" class="p-1 hover:bg-gray-700 rounded" title="Delete">🗑️</button>
                                        </div>
                                        
                                        <!-- Block Preview -->
                                        <div class="p-3" x-html="getBlockPreview(block)"></div>
                                    </div>
                                </template>

                                <!-- Empty Section Hint -->
                                <template x-if="!section.blocks || section.blocks.length === 0">
                                    <div class="text-center text-gray-400 text-sm py-8 border-2 border-dashed border-gray-200 rounded-lg">
                                        <p class="mb-2">📦 Drop blocks here</p>
                                        <p class="text-xs">Drag from the sidebar or click a block</p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Add Section Button -->
            <template x-if="data.sections.length > 0">
                <button @click="addSection()"
                        class="w-full mt-4 py-3 border-2 border-dashed border-gray-200 rounded-lg text-gray-400 hover:border-blue-300 hover:text-blue-500 transition-colors">
                    + Add Section
                </button>
            </template>
        </div>
    </div>
</div>

<!-- Load SortableJS and Composer JS -->
<script src="/js/sortable.min.js"></script>
<script src="/js/composer.js"></script>
@endsection

