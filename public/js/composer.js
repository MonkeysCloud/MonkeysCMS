/**
 * Content Composer - Alpine.js Component
 * 
 * Visual page builder for MonkeysCMS
 * Uses real CMS block types with frontend-like previews
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('composerEditor', (config) => ({
        // State
        data: config.data || { version: '1.0', sections: [], meta: [] },
        entityType: config.entityType,
        entityId: config.entityId,
        blocks: config.blocks || {},
        layouts: config.layouts || [],
        fields: config.fields || [],
        
        // UI State
        previewMode: 'desktop',
        sidebarTab: 'blocks',
        blockSearch: '',
        saving: false,
        savedSections: [],
        
        // Block preview cache
        blockPreviews: {},
        loadingPreviews: new Set(),
        
        init() {
            console.log('Composer initialized', this.data);
            console.log('Available blocks:', this.blocks);
            console.log('Content type fields:', this.fields);
            
            // Wire up save button
            document.getElementById('composer-save-btn')?.addEventListener('click', () => this.save());
            
            // Load previews for existing blocks
            this.loadExistingBlockPreviews();
        },
        
        // Load previews for blocks already in the layout
        async loadExistingBlockPreviews() {
            for (const section of this.data.sections) {
                for (const block of section.blocks || []) {
                    if (block.blockType) {
                        await this.loadBlockPreview(block);
                    }
                }
            }
        },
        
        // Computed
        get filteredBlocks() {
            if (!this.blockSearch) return this.blocks;
            const search = this.blockSearch.toLowerCase();
            const filtered = {};
            for (const [category, items] of Object.entries(this.blocks)) {
                const matching = items.filter(b => 
                    b.label.toLowerCase().includes(search) || 
                    b.type.toLowerCase().includes(search)
                );
                if (matching.length) filtered[category] = matching;
            }
            return filtered;
        },
        
        // Section operations (simplified - one section contains blocks directly)
        addSection() {
            const section = {
                id: 'section-' + Date.now(),
                settings: { 
                    background: { type: 'none' }, 
                    padding: { top: 20, bottom: 20 },
                    width: 'container', // container or full
                },
                blocks: [] // Direct blocks in section, no rows/columns for simplicity
            };
            this.data.sections.push(section);
        },
        
        deleteSection(index) {
            if (confirm('Delete this section?')) {
                this.data.sections.splice(index, 1);
            }
        },
        
        duplicateSection(index) {
            const copy = JSON.parse(JSON.stringify(this.data.sections[index]));
            copy.id = 'section-' + Date.now();
            // Regenerate block IDs
            copy.blocks = copy.blocks.map(b => ({...b, id: 'block-' + Date.now() + Math.random().toString(36).substr(2, 9)}));
            this.data.sections.splice(index + 1, 0, copy);
        },
        
        // Block operations
        addBlock(sectionIndex, blockDef) {
            const block = {
                id: 'block-' + Date.now(),
                type: blockDef.type,
                blockType: blockDef.blockType || null, // CMS block type ID
                label: blockDef.label,
                icon: blockDef.icon,
                settings: {},
                content: {}
            };
            this.data.sections[sectionIndex].blocks.push(block);
            
            // Load preview for new block
            if (block.blockType) {
                this.loadBlockPreview(block);
            }
        },
        
        // Add block from sidebar click (adds to last section, or creates new section)
        addBlockFromSidebar(blockDef) {
            // If no sections, create one first
            if (this.data.sections.length === 0) {
                this.addSection();
            }
            // Add to last section
            const lastSectionIndex = this.data.sections.length - 1;
            this.addBlock(lastSectionIndex, blockDef);
        },
        
        // Add field from sidebar click
        addFieldFromSidebar(field) {
            if (this.data.sections.length === 0) {
                this.addSection();
            }
            const lastSectionIndex = this.data.sections.length - 1;
            const block = {
                id: 'block-' + Date.now(),
                type: '_field',
                blockType: null,
                label: field.name || field.label,
                icon: '📋',
                settings: { field: field.machine_name },
                content: {}
            };
            this.data.sections[lastSectionIndex].blocks.push(block);
        },
        
        deleteBlock(sectionIndex, blockIndex) {
            this.data.sections[sectionIndex].blocks.splice(blockIndex, 1);
        },
        
        duplicateBlock(sectionIndex, blockIndex) {
            const blocks = this.data.sections[sectionIndex].blocks;
            const copy = JSON.parse(JSON.stringify(blocks[blockIndex]));
            copy.id = 'block-' + Date.now();
            blocks.splice(blockIndex + 1, 0, copy);
        },
        
        moveBlockUp(sectionIndex, blockIndex) {
            if (blockIndex > 0) {
                const blocks = this.data.sections[sectionIndex].blocks;
                [blocks[blockIndex - 1], blocks[blockIndex]] = [blocks[blockIndex], blocks[blockIndex - 1]];
            }
        },
        
        moveBlockDown(sectionIndex, blockIndex) {
            const blocks = this.data.sections[sectionIndex].blocks;
            if (blockIndex < blocks.length - 1) {
                [blocks[blockIndex], blocks[blockIndex + 1]] = [blocks[blockIndex + 1], blocks[blockIndex]];
            }
        },
        
        // Drag and drop
        onBlockDragStart(event, block) {
            event.dataTransfer.setData('text/plain', JSON.stringify({ type: 'new-block', block }));
            event.dataTransfer.effectAllowed = 'copy';
        },
        
        onFieldDragStart(event, field) {
            event.dataTransfer.setData('text/plain', JSON.stringify({ type: 'field', field }));
            event.dataTransfer.effectAllowed = 'copy';
        },
        
        onBlockItemDragStart(event, sectionIndex, blockIndex) {
            event.dataTransfer.setData('text/plain', JSON.stringify({ 
                type: 'move-block', 
                sectionIndex, 
                blockIndex 
            }));
            event.dataTransfer.effectAllowed = 'move';
        },
        
        onSectionDragOver(event) {
            event.preventDefault();
            event.currentTarget.classList.add('border-blue-500', 'bg-blue-50');
        },
        
        onSectionDragLeave(event) {
            event.currentTarget.classList.remove('border-blue-500', 'bg-blue-50');
        },
        
        onSectionDrop(event, sectionIndex) {
            event.preventDefault();
            event.currentTarget.classList.remove('border-blue-500', 'bg-blue-50');
            
            try {
                const data = JSON.parse(event.dataTransfer.getData('text/plain'));
                
                if (data.type === 'new-block') {
                    this.addBlock(sectionIndex, data.block);
                } else if (data.type === 'field') {
                    // Add field placeholder block
                    const block = {
                        id: 'block-' + Date.now(),
                        type: '_field',
                        blockType: null,
                        label: data.field.name || data.field.label,
                        icon: '📋',
                        settings: { field: data.field.machine_name },
                        content: {}
                    };
                    this.data.sections[sectionIndex].blocks.push(block);
                } else if (data.type === 'move-block') {
                    // Move block between sections
                    if (data.sectionIndex !== sectionIndex) {
                        const block = this.data.sections[data.sectionIndex].blocks.splice(data.blockIndex, 1)[0];
                        this.data.sections[sectionIndex].blocks.push(block);
                    }
                }
            } catch (e) {
                console.error('Drop error:', e);
            }
        },
        
        onCanvasDrop(event) {
            if (this.data.sections.length === 0) {
                this.addSection();
            }
        },
        
        // Load block preview from API
        async loadBlockPreview(block) {
            if (!block.blockType || this.loadingPreviews.has(block.id)) return;
            
            this.loadingPreviews.add(block.id);
            
            try {
                const response = await fetch('/admin/composer/api/block-preview', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        blockType: block.blockType,
                        blockData: {
                            title: block.label,
                            body: block.content?.body || '',
                            settings: block.settings || {}
                        }
                    })
                });
                
                if (response.ok) {
                    const result = await response.json();
                    this.blockPreviews[block.id] = result.html;
                }
            } catch (e) {
                console.error('Preview load error:', e);
            } finally {
                this.loadingPreviews.delete(block.id);
            }
        },
        
        // Get block preview HTML
        getBlockPreview(block) {
            // If we have a cached preview, use it
            if (this.blockPreviews[block.id]) {
                return this.blockPreviews[block.id];
            }
            
            // Show block type info with icon
            const icon = block.icon || '📦';
            const label = block.label || block.blockType || block.type;
            
            // For field placeholders
            if (block.type === '_field') {
                return `<div class="py-3 px-4 bg-green-50 border border-green-200 rounded flex items-center gap-2">
                    <span class="text-xl">📋</span>
                    <span class="font-medium text-green-700">Field: ${block.settings?.field || 'Unknown'}</span>
                </div>`;
            }
            
            // Default preview for CMS blocks
            return `<div class="py-3 px-4 bg-gray-50 border border-gray-200 rounded flex items-center gap-2">
                <span class="text-xl">${icon}</span>
                <span class="font-medium text-gray-700">${label}</span>
                ${block.blockType ? '<span class="text-xs text-gray-400">(' + block.blockType + ')</span>' : ''}
            </div>`;
        },
        
        // Styles
        getSectionStyles(section) {
            let styles = '';
            if (section.settings?.padding?.top) styles += 'padding-top: ' + section.settings.padding.top + 'px;';
            if (section.settings?.padding?.bottom) styles += 'padding-bottom: ' + section.settings.padding.bottom + 'px;';
            if (section.settings?.background?.color) styles += 'background-color: ' + section.settings.background.color + ';';
            return styles;
        },
        
        // Save
        async save() {
            if (this.entityType === 'demo') {
                alert('Demo mode - data not saved. JSON logged to console.');
                console.log('Composer data:', JSON.stringify(this.data, null, 2));
                return;
            }
            
            const btn = document.getElementById('composer-save-btn');
            btn.querySelector('.save-text').classList.add('hidden');
            btn.querySelector('.saving-text').classList.remove('hidden');
            btn.disabled = true;
            
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
                if (!response.ok) throw new Error('Save failed');
                
                // Show success briefly
                btn.querySelector('.saving-text').textContent = 'Saved!';
                setTimeout(() => {
                    btn.querySelector('.saving-text').textContent = 'Saving...';
                    btn.querySelector('.saving-text').classList.add('hidden');
                    btn.querySelector('.save-text').classList.remove('hidden');
                    btn.disabled = false;
                }, 1500);
            } catch (error) {
                alert('Error saving: ' + error.message);
                btn.querySelector('.saving-text').classList.add('hidden');
                btn.querySelector('.save-text').classList.remove('hidden');
                btn.disabled = false;
            }
        }
    }));
});
