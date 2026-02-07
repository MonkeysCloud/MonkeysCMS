/**
 * Content Composer - AlpineJS Main Component
 * 
 * Handles the composer editor logic including:
 * - Canvas state management
 * - Drag and drop
 * - Undo/redo history
 * - AJAX save
 */

document.addEventListener('alpine:init', () => {
    Alpine.data('composer', (config) => ({
        // Data
        data: config.data || { version: '1.0', sections: [], meta: {} },
        entityType: config.entityType || 'node',
        entityId: config.entityId || null,
        blocks: config.blocks || {},
        layouts: config.layouts || [],
        fields: config.fields || [],
        
        // UI State
        sidebarTab: 'blocks',
        blockSearch: '',
        previewMode: 'desktop',
        settingsPanel: null,
        settingsPanelTitle: '',
        settingsPanelContent: '',
        saving: false,
        
        // History
        history: [],
        historyIndex: -1,
        canUndo: false,
        canRedo: false,
        
        // Saved sections (loaded from API)
        savedSections: [],
        
        // Drag state
        draggedBlock: null,
        draggedField: null,
        dragSource: null,

        init() {
            // Save initial state
            this.pushHistory();
            
            // Load saved sections
            this.loadSavedSections();
            
            // Initialize SortableJS for sections
            this.$nextTick(() => {
                this.initSortable();
            });
        },

        initSortable() {
            const sectionsContainer = document.getElementById('sections-container');
            if (sectionsContainer) {
                new Sortable(sectionsContainer, {
                    animation: 150,
                    handle: '.composer-section-wrapper',
                    ghostClass: 'opacity-50',
                    onEnd: (evt) => {
                        const sections = [...this.data.sections];
                        const [removed] = sections.splice(evt.oldIndex, 1);
                        sections.splice(evt.newIndex, 0, removed);
                        this.data.sections = sections;
                        this.pushHistory();
                    }
                });
            }
        },

        // Computed
        get filteredBlocks() {
            const search = this.blockSearch.toLowerCase();
            const result = {};
            
            for (const [category, blocks] of Object.entries(this.blocks)) {
                const filtered = blocks.filter(b => 
                    b.label.toLowerCase().includes(search) ||
                    b.type.toLowerCase().includes(search)
                );
                if (filtered.length > 0) {
                    result[category] = filtered;
                }
            }
            
            return result;
        },

        // History
        pushHistory() {
            // Remove future states
            this.history = this.history.slice(0, this.historyIndex + 1);
            
            // Add current state
            this.history.push(JSON.parse(JSON.stringify(this.data)));
            this.historyIndex = this.history.length - 1;
            
            // Limit history size
            if (this.history.length > 50) {
                this.history.shift();
                this.historyIndex--;
            }
            
            this.updateHistoryState();
        },

        updateHistoryState() {
            this.canUndo = this.historyIndex > 0;
            this.canRedo = this.historyIndex < this.history.length - 1;
        },

        undo() {
            if (this.canUndo) {
                this.historyIndex--;
                this.data = JSON.parse(JSON.stringify(this.history[this.historyIndex]));
                this.updateHistoryState();
            }
        },

        redo() {
            if (this.canRedo) {
                this.historyIndex++;
                this.data = JSON.parse(JSON.stringify(this.history[this.historyIndex]));
                this.updateHistoryState();
            }
        },

        // Section operations
        addSection() {
            const section = {
                id: 'section-' + this.generateId(),
                settings: {
                    background: { type: 'none', value: null },
                    padding: { top: 40, bottom: 40 },
                    fullWidth: false,
                    cssClass: '',
                    cssId: ''
                },
                rows: [{
                    id: 'row-' + this.generateId(),
                    settings: { layout: '1', gap: 20, verticalAlign: 'top' },
                    columns: [{
                        id: 'col-' + this.generateId(),
                        width: 100,
                        settings: {},
                        blocks: []
                    }]
                }]
            };
            
            this.data.sections.push(section);
            this.pushHistory();
        },

        duplicateSection(index) {
            const section = JSON.parse(JSON.stringify(this.data.sections[index]));
            section.id = 'section-' + this.generateId();
            this.regenerateIds(section);
            this.data.sections.splice(index + 1, 0, section);
            this.pushHistory();
        },

        deleteSection(index) {
            if (confirm('Delete this section?')) {
                this.data.sections.splice(index, 1);
                this.pushHistory();
            }
        },

        openSectionSettings(index) {
            const section = this.data.sections[index];
            this.settingsPanelTitle = 'Section Settings';
            this.settingsPanel = { type: 'section', index };
            this.settingsPanelContent = this.renderSectionSettings(section, index);
        },

        saveAsSection(index) {
            const name = prompt('Section name:');
            if (name) {
                const section = this.data.sections[index];
                fetch('/admin/composer/api/save-section', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, section })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Section saved!');
                        this.loadSavedSections();
                    }
                });
            }
        },

        // Row operations
        addRow(sectionIndex, layout = '1') {
            const widths = this.getLayoutWidths(layout);
            const row = {
                id: 'row-' + this.generateId(),
                settings: { layout, gap: 20, verticalAlign: 'top' },
                columns: widths.map(w => ({
                    id: 'col-' + this.generateId(),
                    width: w,
                    settings: {},
                    blocks: []
                }))
            };
            
            this.data.sections[sectionIndex].rows.push(row);
            this.pushHistory();
        },

        changeRowLayout(sectionIndex, rowIndex) {
            const layouts = ['1', '1-1', '1-2', '2-1', '1-1-1', '1-1-1-1'];
            const current = this.data.sections[sectionIndex].rows[rowIndex].settings.layout;
            const currentIdx = layouts.indexOf(current);
            const nextLayout = layouts[(currentIdx + 1) % layouts.length];
            
            const widths = this.getLayoutWidths(nextLayout);
            const row = this.data.sections[sectionIndex].rows[rowIndex];
            
            // Preserve existing blocks
            const allBlocks = row.columns.flatMap(c => c.blocks);
            
            row.settings.layout = nextLayout;
            row.columns = widths.map((w, i) => ({
                id: 'col-' + this.generateId(),
                width: w,
                settings: {},
                blocks: i === 0 ? allBlocks : []
            }));
            
            this.pushHistory();
        },

        duplicateRow(sectionIndex, rowIndex) {
            const row = JSON.parse(JSON.stringify(this.data.sections[sectionIndex].rows[rowIndex]));
            row.id = 'row-' + this.generateId();
            this.regenerateIds(row);
            this.data.sections[sectionIndex].rows.splice(rowIndex + 1, 0, row);
            this.pushHistory();
        },

        deleteRow(sectionIndex, rowIndex) {
            this.data.sections[sectionIndex].rows.splice(rowIndex, 1);
            this.pushHistory();
        },

        // Block operations
        addBlock(sectionIndex, rowIndex, colIndex, blockType, blockData = {}) {
            const block = {
                id: 'block-' + this.generateId(),
                type: blockType,
                data: blockData,
                settings: {
                    margin: { top: 0, right: 0, bottom: 0, left: 0 },
                    animation: 'none',
                    cssClass: ''
                }
            };
            
            this.data.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks.push(block);
            this.pushHistory();
        },

        duplicateBlock(sectionIndex, rowIndex, colIndex, blockIndex) {
            const block = JSON.parse(JSON.stringify(
                this.data.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks[blockIndex]
            ));
            block.id = 'block-' + this.generateId();
            this.data.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks.splice(blockIndex + 1, 0, block);
            this.pushHistory();
        },

        deleteBlock(sectionIndex, rowIndex, colIndex, blockIndex) {
            this.data.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks.splice(blockIndex, 1);
            this.pushHistory();
        },

        openBlockSettings(sectionIndex, rowIndex, colIndex, blockIndex) {
            const block = this.data.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks[blockIndex];
            this.settingsPanelTitle = 'Block Settings';
            this.settingsPanel = { type: 'block', sectionIndex, rowIndex, colIndex, blockIndex };
            this.settingsPanelContent = this.renderBlockSettings(block);
        },

        // Drag and drop
        onBlockDragStart(event, block) {
            this.draggedBlock = block;
            this.dragSource = 'sidebar';
            event.dataTransfer.effectAllowed = 'copy';
        },

        onFieldDragStart(event, field) {
            this.draggedField = field;
            this.dragSource = 'sidebar';
            event.dataTransfer.effectAllowed = 'copy';
        },

        onBlockItemDragStart(event, sectionIndex, rowIndex, colIndex, blockIndex) {
            this.dragSource = { sectionIndex, rowIndex, colIndex, blockIndex };
            event.dataTransfer.effectAllowed = 'move';
        },

        onColumnDragOver(event) {
            event.currentTarget.classList.add('border-blue-400', 'bg-blue-50');
        },

        onColumnDragLeave(event) {
            event.currentTarget.classList.remove('border-blue-400', 'bg-blue-50');
        },

        onColumnDrop(event, sectionIndex, rowIndex, colIndex) {
            event.currentTarget.classList.remove('border-blue-400', 'bg-blue-50');
            
            if (this.draggedBlock) {
                // Add new block from sidebar
                this.addBlock(sectionIndex, rowIndex, colIndex, this.draggedBlock.type, {});
                this.draggedBlock = null;
            } else if (this.draggedField) {
                // Add field placeholder
                this.addBlock(sectionIndex, rowIndex, colIndex, '_field_placeholder', {
                    field_name: this.draggedField.machine_name,
                    view_mode: 'default',
                    hide_label: false
                });
                this.draggedField = null;
            } else if (this.dragSource && typeof this.dragSource === 'object') {
                // Move existing block
                const { sectionIndex: si, rowIndex: ri, colIndex: ci, blockIndex: bi } = this.dragSource;
                const block = this.data.sections[si].rows[ri].columns[ci].blocks[bi];
                
                // Remove from source
                this.data.sections[si].rows[ri].columns[ci].blocks.splice(bi, 1);
                
                // Add to target
                this.data.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks.push(block);
                
                this.pushHistory();
                this.dragSource = null;
            }
        },

        onCanvasDrop(event) {
            // Handle drop on canvas (add new section)
            if (this.draggedBlock && this.data.sections.length === 0) {
                this.addSection();
                this.$nextTick(() => {
                    this.addBlock(0, 0, 0, this.draggedBlock.type, {});
                });
                this.draggedBlock = null;
            }
        },

        // Saved sections
        loadSavedSections() {
            fetch('/admin/composer/api/saved-sections')
                .then(r => r.json())
                .then(data => {
                    this.savedSections = data.sections || [];
                });
        },

        insertSavedSection(savedSection) {
            const section = JSON.parse(savedSection.data);
            section.id = 'section-' + this.generateId();
            this.regenerateIds(section);
            this.data.sections.push(section);
            this.pushHistory();
        },

        // Save
        async save() {
            this.saving = true;
            
            try {
                const response = await fetch('/admin/composer/save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        entityType: this.entityType,
                        entityId: this.entityId,
                        composerData: this.data
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success message
                    this.showNotification('Layout saved successfully!', 'success');
                } else {
                    this.showNotification('Error: ' + (result.errors?.join(', ') || 'Unknown error'), 'error');
                }
            } catch (error) {
                this.showNotification('Failed to save: ' + error.message, 'error');
            } finally {
                this.saving = false;
            }
        },

        showNotification(message, type) {
            // Simple alert for now, can be replaced with toast
            alert(message);
        },

        // Helpers
        generateId() {
            return Math.random().toString(36).substr(2, 9);
        },

        regenerateIds(obj) {
            if (Array.isArray(obj)) {
                obj.forEach(item => this.regenerateIds(item));
            } else if (obj && typeof obj === 'object') {
                if (obj.id) {
                    obj.id = obj.id.split('-')[0] + '-' + this.generateId();
                }
                Object.values(obj).forEach(v => this.regenerateIds(v));
            }
        },

        getLayoutWidths(layout) {
            const layouts = {
                '1': [100],
                '1-1': [50, 50],
                '1-2': [33.33, 66.67],
                '2-1': [66.67, 33.33],
                '1-1-1': [33.33, 33.33, 33.33],
                '1-1-1-1': [25, 25, 25, 25],
                '1-2-1': [25, 50, 25],
                '1-3': [25, 75],
                '3-1': [75, 25]
            };
            return layouts[layout] || [100];
        },

        getSectionStyles(section) {
            const styles = [];
            const bg = section.settings?.background || {};
            
            if (bg.type === 'color' && bg.value) {
                styles.push(`background-color: ${bg.value}`);
            } else if (bg.type === 'image' && bg.value) {
                styles.push(`background-image: url(${bg.value})`);
                styles.push('background-size: cover');
            }
            
            const padding = section.settings?.padding || {};
            if (padding.top) styles.push(`padding-top: ${padding.top}px`);
            if (padding.bottom) styles.push(`padding-bottom: ${padding.bottom}px`);
            
            return styles.join('; ');
        },

        getRowStyles(row) {
            const gap = row.settings?.gap || 20;
            return `gap: ${gap}px`;
        },

        getColumnStyles(column) {
            const width = column.width || 100;
            return `flex: 0 0 calc(${width}% - 16px); max-width: calc(${width}% - 16px)`;
        },

        getBlockPreview(block) {
            const type = block.type;
            const data = block.data || {};
            
            if (type === '_field_placeholder') {
                return `<div class="text-green-600 flex items-center gap-2">
                    <span>📋</span>
                    <span class="font-medium">Field: ${data.field_name || 'Unknown'}</span>
                </div>`;
            }
            
            switch (type) {
                case 'heading':
                    return `<div class="font-bold">${data.text || 'Heading'}</div>`;
                case 'text':
                    return `<div class="text-gray-500 line-clamp-2">${data.content || 'Text block'}</div>`;
                case 'image':
                    return data.src 
                        ? `<img src="${data.src}" class="h-16 w-full object-cover rounded" />`
                        : '<div class="text-gray-400">🖼️ Image</div>';
                case 'button':
                    return `<span class="inline-block px-3 py-1 bg-blue-500 text-white text-sm rounded">${data.text || 'Button'}</span>`;
                case 'spacer':
                    return `<div class="text-gray-400 text-center">↕️ Spacer (${data.height || 40}px)</div>`;
                case 'divider':
                    return '<hr class="my-2">';
                case 'video':
                    return `<div class="text-gray-400">🎬 Video</div>`;
                default:
                    return `<div class="text-gray-400">${type}</div>`;
            }
        },

        renderSectionSettings(section, index) {
            // Returns HTML for section settings form
            return `
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Background</label>
                        <select 
                            class="w-full border rounded px-3 py-2"
                            @change="data.sections[${index}].settings.background.type = $event.target.value; pushHistory()"
                        >
                            <option value="none" ${section.settings.background?.type === 'none' ? 'selected' : ''}>None</option>
                            <option value="color" ${section.settings.background?.type === 'color' ? 'selected' : ''}>Color</option>
                            <option value="image" ${section.settings.background?.type === 'image' ? 'selected' : ''}>Image</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Padding Top (px)</label>
                        <input type="number" 
                            class="w-full border rounded px-3 py-2"
                            value="${section.settings.padding?.top || 0}"
                            @input="data.sections[${index}].settings.padding.top = parseInt($event.target.value); pushHistory()"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Padding Bottom (px)</label>
                        <input type="number" 
                            class="w-full border rounded px-3 py-2"
                            value="${section.settings.padding?.bottom || 0}"
                            @input="data.sections[${index}].settings.padding.bottom = parseInt($event.target.value); pushHistory()"
                        />
                    </div>
                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" 
                                ${section.settings.fullWidth ? 'checked' : ''}
                                @change="data.sections[${index}].settings.fullWidth = $event.target.checked; pushHistory()"
                            />
                            <span class="text-sm">Full Width</span>
                        </label>
                    </div>
                </div>
            `;
        },

        renderBlockSettings(block) {
            // Returns HTML for block settings form
            return `
                <div class="space-y-4">
                    <div class="text-sm text-gray-500">Block type: ${block.type}</div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CSS Class</label>
                        <input type="text" 
                            class="w-full border rounded px-3 py-2"
                            value="${block.settings?.cssClass || ''}"
                            placeholder="custom-class"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Animation</label>
                        <select class="w-full border rounded px-3 py-2">
                            <option value="none" ${block.settings?.animation === 'none' ? 'selected' : ''}>None</option>
                            <option value="fadeIn" ${block.settings?.animation === 'fadeIn' ? 'selected' : ''}>Fade In</option>
                            <option value="fadeInUp" ${block.settings?.animation === 'fadeInUp' ? 'selected' : ''}>Fade In Up</option>
                            <option value="fadeInLeft" ${block.settings?.animation === 'fadeInLeft' ? 'selected' : ''}>Fade In Left</option>
                            <option value="fadeInRight" ${block.settings?.animation === 'fadeInRight' ? 'selected' : ''}>Fade In Right</option>
                        </select>
                    </div>
                </div>
            `;
        }
    }));
});
