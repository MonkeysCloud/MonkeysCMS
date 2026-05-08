/**
 * MonkeysCMS — Mosaic Editor (Full-Screen Edition)
 *
 * A modern block-based page builder with Shadow DOM WYSIWYG preview.
 * Features: Undo/Redo, Device Preview, Drag & Drop, Auto-save, Theme Switching, Zoom.
 */
(function () {
  'use strict';

  // ── Shadow DOM Custom Element ──────────────────────────────────
  class MosaicCanvasElement extends HTMLElement {
    connectedCallback() {
      this.shadow = this.attachShadow({ mode: 'open' });
      this._content = document.createElement('div');
      this._content.className = 'mosaic-canvas-root';
      this.shadow.appendChild(this._content);
    }

    /** Load an array of CSS URLs into the shadow DOM */
    loadStyles(cssUrls) {
      // Remove existing stylesheets
      this.shadow.querySelectorAll('link[rel="stylesheet"]').forEach(l => l.remove());
      // Prepend before content
      cssUrls.forEach(url => {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = url + (url.includes('?') ? '&' : '?') + 'v=' + Date.now();
        this.shadow.insertBefore(link, this._content);
      });
      // Always load editor chrome CSS last
      const chrome = document.createElement('link');
      chrome.rel = 'stylesheet';
      chrome.href = '/themes/core/admin/css/canvas-chrome.css?v=' + Date.now();
      this.shadow.insertBefore(chrome, this._content);
    }

    get contentRoot() { return this._content; }
  }
  if (!customElements.get('mosaic-canvas')) {
    customElements.define('mosaic-canvas', MosaicCanvasElement);
  }

  // ── State ───────────────────────────────────────────────────────
  const API = '/api/cms/mosaic';
  let state = {
    nodeId: 0,
    contentType: '',
    sections: [],
    blockTypes: {},
    groupedBlocks: {},
    layouts: {},
    nodeFields: {},
    revision: 0,
    history: [],
    historyIndex: -1,
    selected: { type: null, sectionIdx: null, regionName: null, blockIdx: null, blockId: null },
    device: 'desktop',
    zoom: 100,
    isDirty: false,
    saveTimeout: null,
    dragData: null,
    previewCache: {},
    frontCss: [],
    activeTheme: 'front',
  };

  /** Get the shadow DOM content root for canvas rendering */
  function getCanvasRoot() {
    const el = document.getElementById('mosaic-canvas-content');
    return el?.shadowRoot?.querySelector('.mosaic-canvas-root') || el;
  }

  // ── Initialization ──────────────────────────────────────────────
  function init() {
    const el = document.getElementById('mosaic-editor');
    if (!el) return;

    state.nodeId = parseInt(el.dataset.nodeId, 10);
    state.contentType = el.dataset.contentType || 'page';

    try {
      state.sections = JSON.parse(el.dataset.sections || '[]');
      state.blockTypes = JSON.parse(el.dataset.blockTypes || '{}');
      state.groupedBlocks = JSON.parse(el.dataset.groupedBlocks || '{}');
      state.layouts = JSON.parse(el.dataset.layouts || '{}');
      state.nodeFields = JSON.parse(el.dataset.nodeFields || '{}');
      state.revision = parseInt(el.dataset.revision || '0', 10);
      state.frontCss = JSON.parse(el.dataset.frontCss || '[]');
      state.activeTheme = el.dataset.activeTheme || 'front';
    } catch (e) {
      console.error('Mosaic: Failed to parse initial data', e);
    }

    // Load front-end CSS into the shadow DOM canvas
    const canvasEl = document.getElementById('mosaic-canvas-content');
    if (canvasEl && canvasEl.loadStyles) {
      canvasEl.loadStyles(state.frontCss);
    } else {
      // Wait for custom element upgrade
      customElements.whenDefined('mosaic-canvas').then(() => {
        canvasEl?.loadStyles?.(state.frontCss);
      });
    }

    pushHistory(true);
    renderSidebar();
    renderCanvas();
    updateSaveIndicator('saved');
    initLucide();
    bindKeyboardShortcuts();
    closeRight();
  }

  // ── History (Undo/Redo) ─────────────────────────────────────────
  function pushHistory(isInitial = false) {
    if (!isInitial) {
      state.isDirty = true;
      debounceAutosave();
    }
    
    if (state.historyIndex < state.history.length - 1) {
      state.history = state.history.slice(0, state.historyIndex + 1);
    }
    
    state.history.push(JSON.stringify(state.sections));
    if (state.history.length > 50) state.history.shift();
    state.historyIndex = state.history.length - 1;
    updateToolbarButtons();
  }

  function undo() {
    if (state.historyIndex > 0) {
      state.historyIndex--;
      state.sections = JSON.parse(state.history[state.historyIndex]);
      state.selected = { type: null };
      closeRight();
      renderCanvas();
      updateToolbarButtons();
      state.isDirty = true;
      debounceAutosave();
    }
  }

  function redo() {
    if (state.historyIndex < state.history.length - 1) {
      state.historyIndex++;
      state.sections = JSON.parse(state.history[state.historyIndex]);
      state.selected = { type: null };
      closeRight();
      renderCanvas();
      updateToolbarButtons();
      state.isDirty = true;
      debounceAutosave();
    }
  }

  function updateToolbarButtons() {
    const btnUndo = document.getElementById('btn-undo');
    const btnRedo = document.getElementById('btn-redo');
    if (btnUndo) btnUndo.disabled = state.historyIndex <= 0;
    if (btnRedo) btnRedo.disabled = state.historyIndex >= state.history.length - 1;
  }

  // ── Canvas Rendering ────────────────────────────────────────────
  async function renderCanvas() {
    const canvas = getCanvasRoot();
    if (!canvas) return;

    let html = '';
    let blockCount = 0;

    for (let sIdx = 0; sIdx < state.sections.length; sIdx++) {
      const section = state.sections[sIdx];
      const layoutDef = state.layouts[section.layout] || state.layouts['full'];
      const regionNames = layoutDef ? layoutDef.regions : ['main'];
      
      const isSelected = state.selected.type === 'section' && state.selected.sectionIdx === sIdx;
      const sectionClasses = ['me-section'];
      if (isSelected) sectionClasses.push('is-selected');

      html += `<div class="${sectionClasses.join(' ')}" data-section-idx="${sIdx}" id="sec-${section.id}">`;
      
      // Header
      html += `<div class="me-section__header" onclick="MosaicEditor.selectSection(${sIdx})">`;
      html += `<span class="me-section__grip" draggable="true" ondragstart="MosaicEditor.onSectionDragStart(event, ${sIdx})" ondragover="MosaicEditor.onSectionDragOver(event)" ondrop="MosaicEditor.onSectionDrop(event, ${sIdx})"><i data-lucide="grip-vertical" class="w-4 h-4"></i></span>`;
      html += `<span class="me-section__layout-badge"><i data-lucide="${layoutDef?.icon || 'square'}" class="w-3.5 h-3.5"></i> ${layoutDef?.label || section.layout}</span>`;
      html += `<div class="me-section__actions">`;
      html += `<button class="me-section__btn" onclick="event.stopPropagation(); MosaicEditor.moveSection(${sIdx}, -1)" title="Move up"><i data-lucide="chevron-up" class="w-4 h-4"></i></button>`;
      html += `<button class="me-section__btn" onclick="event.stopPropagation(); MosaicEditor.moveSection(${sIdx}, 1)" title="Move down"><i data-lucide="chevron-down" class="w-4 h-4"></i></button>`;
      html += `<button class="me-section__btn" onclick="event.stopPropagation(); MosaicEditor.duplicateSection(${sIdx})" title="Duplicate"><i data-lucide="copy" class="w-4 h-4"></i></button>`;
      html += `<button class="me-section__btn me-section__btn--danger" onclick="event.stopPropagation(); MosaicEditor.removeSection(${sIdx})" title="Remove"><i data-lucide="trash-2" class="w-4 h-4"></i></button>`;
      html += `</div></div>`;

      // Apply Section Settings Styles
      let styleStr = '';
      if (section.settings?.background) styleStr += `background-color: ${section.settings.background};`;
      if (section.settings?.padding) styleStr += `padding: ${section.settings.padding};`;
      
      html += `<div class="me-regions me-regions--${section.layout}" style="${styleStr}">`;
      
      for (const regionName of regionNames) {
        const blocks = (section.regions && section.regions[regionName]) || [];
        html += `<div class="me-region" data-section-idx="${sIdx}" data-region="${regionName}" ondragover="MosaicEditor.onRegionDragOver(event)" ondragleave="MosaicEditor.onRegionDragLeave(event)" ondrop="MosaicEditor.onRegionDrop(event)">`;
        
        if (blocks.length === 0) {
          html += `<div class="me-region__empty"><i data-lucide="plus" class="w-4 h-4"></i> Drop blocks</div>`;
        }

        for (let bIdx = 0; bIdx < blocks.length; bIdx++) {
          const block = blocks[bIdx];
          blockCount++;
          const type = state.blockTypes[block.blockType] || {};
          const isBlockSelected = state.selected.type === 'block' && state.selected.blockId === block.id;
          const blockClasses = ['me-block'];
          if (isBlockSelected) blockClasses.push('is-selected');

          // Drop indicator before each block
          html += `<div class="me-drop-indicator" data-section-idx="${sIdx}" data-region="${regionName}" data-drop-idx="${bIdx}"></div>`;

          html += `<div class="${blockClasses.join(' ')}" data-block-id="${block.id}" data-section-idx="${sIdx}" data-region="${regionName}" data-block-idx="${bIdx}">`;
          
          // Block Header
          html += `<div class="me-block__header" onclick="MosaicEditor.selectBlock('${block.id}', ${sIdx}, '${regionName}', ${bIdx})">`;
          html += `<span class="me-block__grip" draggable="true" ondragstart="MosaicEditor.onBlockDragStart(event)"><i data-lucide="grip-vertical" class="w-3.5 h-3.5"></i></span>`;
          html += `<span class="me-block__icon"><i data-lucide="${type.icon || 'box'}" class="w-4 h-4"></i></span>`;
          html += `<span class="me-block__label">${type.label || block.blockType}</span>`;
          html += `<div class="me-block__actions">`;
          html += `<button class="me-block__btn" onclick="event.stopPropagation(); MosaicEditor.duplicateBlock(${sIdx}, '${regionName}', ${bIdx})" title="Duplicate"><i data-lucide="copy" class="w-3.5 h-3.5"></i></button>`;
          html += `<button class="me-block__btn me-block__btn--del" onclick="event.stopPropagation(); MosaicEditor.removeBlock(${sIdx}, '${regionName}', ${bIdx})" title="Delete"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>`;
          html += `</div></div>`;

          // Block Preview — inline-editable for text/heading, API-rendered for others
          const isInlinable = ['text', 'heading'].includes(block.blockType);
          if (isInlinable) {
            const fieldKey = block.blockType === 'heading' ? 'text' : 'body';
            const content = (block.data && block.data[fieldKey]) || '';
            const tag = block.blockType === 'heading' ? 'h2' : 'div';
            html += `<div class="me-block__preview me-block__preview--editable">`;
            html += `<${tag} contenteditable="true" class="me-inline-edit" data-block-id="${block.id}" data-section-idx="${sIdx}" data-region="${regionName}" data-block-idx="${bIdx}" data-field="${fieldKey}" onblur="MosaicEditor.onInlineBlur(this)">${content}</${tag}>`;
            html += `</div>`;
          } else {
            const cached = state.previewCache[block.id];
            html += `<div class="me-block__preview" id="preview-${block.id}">`;
            html += cached || '<div class="me-block__preview-loading">Loading...</div>';
            html += `</div>`;
          }
          html += `</div>`;
        }
        // Trailing drop indicator
        html += `<div class="me-drop-indicator" data-section-idx="${sIdx}" data-region="${regionName}" data-drop-idx="${blocks.length}"></div>`;
        html += `</div>`;
      }
      html += `</div></div>`;
    }

    // Add Section button (inside shadow DOM)
    html += `<div class="me-canvas__add-section" onclick="MosaicEditor.promptAddSection()">`;
    html += `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="16"/><line x1="8" x2="16" y1="12" y2="12"/></svg>`;
    html += `<span>Add Section</span></div>`;

    canvas.innerHTML = html;
    initLucideShadow();
    
    document.getElementById('statusbar-sections').innerText = state.sections.length;
    document.getElementById('statusbar-blocks').innerText = blockCount;

    // Fetch previews asynchronously
    for (let sIdx = 0; sIdx < state.sections.length; sIdx++) {
      const section = state.sections[sIdx];
      for (const regionName of Object.keys(section.regions || {})) {
        for (let bIdx = 0; bIdx < section.regions[regionName].length; bIdx++) {
          fetchBlockPreview(section.regions[regionName][bIdx]);
        }
      }
    }
  }

  async function fetchBlockPreview(block) {
    if (['text', 'heading'].includes(block.blockType)) return; // inline-editable
    const canvas = getCanvasRoot();
    const el = canvas?.querySelector(`#preview-${block.id}`);
    if (!el) return;
    try {
      const res = await CMS.fetch(`${API}/blocks/render`, {
        method: 'POST',
        body: JSON.stringify({ blockType: block.blockType, data: block.data || {}, settings: block.settings || {} })
      });
      const json = await res.json();
      if (res.ok && json.html) {
        state.previewCache[block.id] = json.html;
        el.innerHTML = json.html;
      }
      else el.innerHTML = `<div class="me-block__preview--placeholder">${block.blockType}</div>`;
    } catch (e) {
      el.innerHTML = `<div class="me-block__preview--placeholder">Error rendering</div>`;
    }
  }

  // ── Inline Text Editing ──────────────────────────────────────────
  function onInlineBlur(el) {
    const sIdx = parseInt(el.dataset.sectionIdx);
    const region = el.dataset.region;
    const bIdx = parseInt(el.dataset.blockIdx);
    const field = el.dataset.field;
    const block = state.sections[sIdx]?.regions?.[region]?.[bIdx];
    if (!block) return;
    const newValue = el.innerHTML;
    if (block.data[field] !== newValue) {
      block.data[field] = newValue;
      pushHistory();
    }
  }

  // ── Left Sidebar ────────────────────────────────────────────────
  function renderSidebar() {
    const secPanel = document.getElementById('sections-panel-body');
    const blkPanel = document.getElementById('blocks-panel-body');
    
    if (secPanel) {
      let html = '<div class="me-category"><div class="me-category__label">Layouts</div>';
      for (const [layoutId, def] of Object.entries(state.layouts)) {
        html += `<div class="me-layout-card" draggable="true" ondragstart="MosaicEditor.onLayoutDragStart(event, '${layoutId}')" onclick="MosaicEditor.addSection('${layoutId}')">`;
        html += `<div class="me-layout-card__icon"><i data-lucide="${def.icon || 'square'}" class="w-5 h-5"></i></div>`;
        html += `<div class="me-layout-card__info"><div class="me-layout-card__name">${def.label}</div><div class="me-layout-card__regions">${def.regions.join(', ')}</div></div></div>`;
      }
      html += '</div>';
      secPanel.innerHTML = html;
    }

    if (blkPanel) {
      let html = '';
      const fieldEntries = Object.entries(state.nodeFields);
      if (fieldEntries.length > 0) {
        html += `<div class="me-category"><div class="me-category__label">Content Fields</div>`;
        fieldEntries.forEach(([fieldName, meta]) => {
          html += `<div class="me-block-type me-block-type--field" draggable="true" ondragstart="MosaicEditor.onTypeDragStart(event, 'field', '${fieldName}')">`;
          html += `<div class="me-block-type__icon me-block-type__icon--field"><i data-lucide="${meta.icon || 'database'}" class="w-4 h-4"></i></div>`;
          html += `<div class="me-block-type__info"><div class="me-block-type__name">${meta.label}</div><div class="me-block-type__desc">${fieldName}</div></div></div>`;
        });
        html += `</div>`;
      }

      for (const [category, types] of Object.entries(state.groupedBlocks)) {
        const filtered = types.filter(t => t.id !== 'field');
        if (filtered.length === 0) continue;
        html += `<div class="me-category"><div class="me-category__label">${category}</div>`;
        filtered.forEach(type => {
          html += `<div class="me-block-type" draggable="true" ondragstart="MosaicEditor.onTypeDragStart(event, '${type.id}')">`;
          html += `<div class="me-block-type__icon"><i data-lucide="${type.icon}" class="w-4 h-4"></i></div>`;
          html += `<div class="me-block-type__info"><div class="me-block-type__name">${type.label}</div><div class="me-block-type__desc">${type.description}</div></div></div>`;
        });
        html += `</div>`;
      }
      blkPanel.innerHTML = html;
    }
  }

  function switchLeftTab(tabId) {
    document.querySelectorAll('.me-left__tab').forEach(el => el.classList.remove('is-active'));
    document.querySelector(`.me-left__tab[data-tab="${tabId}"]`)?.classList.add('is-active');
    document.querySelectorAll('.me-left__panel').forEach(el => el.classList.remove('is-active'));
    document.getElementById(`panel-${tabId}`)?.classList.add('is-active');
  }

  function filterBlocks(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('.me-block-type').forEach(item => {
      const name = item.querySelector('.me-block-type__name')?.textContent?.toLowerCase() || '';
      const desc = item.querySelector('.me-block-type__desc')?.textContent?.toLowerCase() || '';
      item.style.display = (!q || name.includes(q) || desc.includes(q)) ? '' : 'none';
    });
  }

  // ── Right Sidebar (Inspector) ───────────────────────────────────
  function openRight(title) {
    document.getElementById('me-right-sidebar')?.classList.add('is-open');
    if (title) document.getElementById('right-title').innerText = title;
  }

  function closeRight() {
    document.getElementById('me-right-sidebar')?.classList.remove('is-open');
    state.selected = { type: null };
    renderCanvas();
  }

  async function selectBlock(blockId, sIdx, regionName, bIdx) {
    state.selected = { type: 'block', blockId, sectionIdx: sIdx, regionName, blockIdx: bIdx };
    renderCanvas();
    openRight('Block Inspector');
    
    const body = document.getElementById('right-body');
    body.innerHTML = `<div class="me-right__empty">Loading form…</div>`;

    const block = state.sections[sIdx].regions[regionName][bIdx];
    try {
      const res = await CMS.fetch(`${API}/blocks/form`, {
        method: 'POST',
        body: JSON.stringify({ blockType: block.blockType, data: block.data || {}, settings: block.settings || {}, sectionIdx: sIdx, regionName: regionName, blockIdx: bIdx })
      });
      const json = await res.json();
      if (res.ok) body.innerHTML = json.html;
      else body.innerHTML = `<div class="me-right__empty">Error loading form</div>`;
    } catch (e) {
      body.innerHTML = `<div class="me-right__empty">Error loading form</div>`;
    }
  }

  function selectSection(sIdx) {
    state.selected = { type: 'section', sectionIdx: sIdx };
    renderCanvas();
    openRight('Section Settings');
    
    const body = document.getElementById('right-body');
    const section = state.sections[sIdx];
    const settings = section.settings || {};
    
    let html = `<div class="me-section-settings__group"><div class="me-section-settings__group-label">Layout</div><select class="mosaic-field__input" onchange="MosaicEditor.setSectionLayout(${sIdx}, this.value)">`;
    for (const [layoutId, def] of Object.entries(state.layouts)) {
      html += `<option value="${layoutId}" ${section.layout === layoutId ? 'selected' : ''}>${def.label}</option>`;
    }
    html += `</select></div>`;
    html += `<div class="me-section-settings__group"><div class="me-section-settings__group-label">Background Color</div><input type="color" class="mosaic-field__input" style="height:36px;padding:2px" value="${settings.background || '#ffffff'}" onchange="MosaicEditor.updateSectionSetting(${sIdx}, 'background', this.value)"></div>`;
    html += `<div class="me-section-settings__group"><div class="me-section-settings__group-label">Padding (CSS)</div><input type="text" class="mosaic-field__input" placeholder="e.g. 2rem 0" value="${settings.padding || ''}" onchange="MosaicEditor.updateSectionSetting(${sIdx}, 'padding', this.value)"></div>`;
    html += `<div class="me-section-settings__group"><div class="me-section-settings__group-label">CSS Class</div><input type="text" class="mosaic-field__input" value="${settings.css_class || ''}" onchange="MosaicEditor.updateSectionSetting(${sIdx}, 'css_class', this.value)"></div>`;
    html += `<button class="me-right__delete-btn" onclick="MosaicEditor.removeSection(${sIdx})">Delete Section</button>`;
    body.innerHTML = html;
  }

  function updateBlockField(sIdx, regionName, bIdx, fieldName, value) {
    const block = state.sections[sIdx].regions[regionName][bIdx];
    if (!block) return;
    if (!block.data) block.data = {};
    block.data[fieldName] = value;
    fetchBlockPreview(block);
    pushHistory();
  }
  
  function updateBlockSetting(sIdx, regionName, bIdx, key, value) {
    const block = state.sections[sIdx].regions[regionName][bIdx];
    if (!block) return;
    if (!block.settings) block.settings = {};
    block.settings[key] = value;
    pushHistory();
  }

  function updateSectionSetting(sIdx, key, value) {
    const section = state.sections[sIdx];
    if (!section) return;
    if (!section.settings) section.settings = {};
    section.settings[key] = value;
    renderCanvas();
    pushHistory();
  }

  function setSectionLayout(sIdx, layoutId) {
    const section = state.sections[sIdx];
    if (!section) return;
    section.layout = layoutId;
    const def = state.layouts[layoutId] || state.layouts['full'];
    (def.regions || ['main']).forEach(r => { if (!section.regions[r]) section.regions[r] = []; });
    renderCanvas();
    pushHistory();
  }

  // ── Operations ──────────────────────────────────────────────────
  function addSection(layoutId = 'full') {
    const layoutDef = state.layouts[layoutId] || state.layouts['full'];
    const regions = {};
    (layoutDef?.regions || ['main']).forEach(r => { regions[r] = []; });
    state.sections.push({ id: generateId(), layout: layoutId, settings: {}, regions: regions });
    pushHistory();
    renderCanvas();
    selectSection(state.sections.length - 1);
  }

  function removeSection(sIdx) {
    if (!confirm('Remove this section?')) return;
    state.sections.splice(sIdx, 1);
    closeRight();
    pushHistory();
    renderCanvas();
  }

  function duplicateSection(sIdx) {
    const clone = JSON.parse(JSON.stringify(state.sections[sIdx]));
    clone.id = generateId();
    for (const r in clone.regions) clone.regions[r].forEach(b => b.id = generateId());
    state.sections.splice(sIdx + 1, 0, clone);
    pushHistory();
    renderCanvas();
  }

  function moveSection(sIdx, dir) {
    const nIdx = sIdx + dir;
    if (nIdx < 0 || nIdx >= state.sections.length) return;
    const tmp = state.sections[sIdx];
    state.sections[sIdx] = state.sections[nIdx];
    state.sections[nIdx] = tmp;
    pushHistory();
    renderCanvas();
  }

  function addBlock(sIdx, regionName, blockType, preData = {}) {
    const type = state.blockTypes[blockType] || {};
    const data = {};
    for (const [k, v] of Object.entries(type.fields || {})) data[k] = v.default !== undefined ? v.default : '';
    Object.assign(data, preData);
    const block = { id: generateId(), blockType, data, settings: {} };
    if (!state.sections[sIdx].regions[regionName]) state.sections[sIdx].regions[regionName] = [];
    state.sections[sIdx].regions[regionName].push(block);
    pushHistory();
    renderCanvas();
    selectBlock(block.id, sIdx, regionName, state.sections[sIdx].regions[regionName].length - 1);
  }

  function removeBlock(sIdx, regionName, bIdx) {
    state.sections[sIdx].regions[regionName].splice(bIdx, 1);
    closeRight();
    pushHistory();
    renderCanvas();
  }

  function duplicateBlock(sIdx, regionName, bIdx) {
    const clone = JSON.parse(JSON.stringify(state.sections[sIdx].regions[regionName][bIdx]));
    clone.id = generateId();
    state.sections[sIdx].regions[regionName].splice(bIdx + 1, 0, clone);
    pushHistory();
    renderCanvas();
  }

  // ── Drag & Drop ─────────────────────────────────────────────────
  function onTypeDragStart(e, blockType, fieldName) {
    state.dragData = { mode: 'new_block', blockType, fieldName };
    e.dataTransfer.effectAllowed = 'copy';
    e.dataTransfer.setData('text/plain', blockType);
  }

  function onBlockDragStart(e) {
    const el = e.target.closest('.me-block');
    if (!el) return;
    state.dragData = { mode: 'move_block', sIdx: parseInt(el.dataset.sectionIdx), region: el.dataset.region, bIdx: parseInt(el.dataset.blockIdx) };
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', el.dataset.blockId);
    setTimeout(() => el.classList.add('is-dragging'), 0);
  }

  function onRegionDragOver(e) {
    e.preventDefault();
    if (!state.dragData || state.dragData.mode.includes('section')) return;
    e.dataTransfer.dropEffect = state.dragData.mode === 'new_block' ? 'copy' : 'move';
    e.currentTarget.classList.add('drag-over');
  }

  function onRegionDragLeave(e) { e.currentTarget.classList.remove('drag-over'); }

  function onRegionDrop(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-over');
    if (!state.dragData || state.dragData.mode.includes('section')) return;

    const targetSIdx = parseInt(e.currentTarget.dataset.sectionIdx);
    const targetRegion = e.currentTarget.dataset.region;

    if (state.dragData.mode === 'new_block') {
      addBlock(targetSIdx, targetRegion, state.dragData.blockType, state.dragData.fieldName ? { field_name: state.dragData.fieldName } : {});
    } else if (state.dragData.mode === 'move_block') {
      const { sIdx, region, bIdx } = state.dragData;
      const block = state.sections[sIdx].regions[region].splice(bIdx, 1)[0];
      if (!state.sections[targetSIdx].regions[targetRegion]) state.sections[targetSIdx].regions[targetRegion] = [];
      state.sections[targetSIdx].regions[targetRegion].push(block);
      pushHistory();
      renderCanvas();
    }
    state.dragData = null;
  }

  function onLayoutDragStart(e, layoutId) {
    state.dragData = { mode: 'new_section', layoutId };
    e.dataTransfer.effectAllowed = 'copy';
    e.dataTransfer.setData('text/plain', layoutId);
  }
  
  function onSectionDragStart(e, sIdx) {
    const canvas = getCanvasRoot();
    const el = canvas?.querySelector(`#sec-${state.sections[sIdx].id}`);
    if(!el) return;
    state.dragData = { mode: 'move_section', sIdx };
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', sIdx);
    setTimeout(() => el.classList.add('is-dragging'), 0);
  }
  
  function onSectionDragOver(e) {
    e.preventDefault();
    if (!state.dragData || !state.dragData.mode.includes('section')) return;
    e.dataTransfer.dropEffect = state.dragData.mode === 'new_section' ? 'copy' : 'move';
    e.currentTarget.closest('.me-section').classList.add('drag-over');
  }
  
  function onSectionDrop(e, targetSIdx) {
    e.preventDefault();
    const canvas = getCanvasRoot();
    canvas?.querySelectorAll('.me-section').forEach(el => el.classList.remove('drag-over'));
    if (!state.dragData || !state.dragData.mode.includes('section')) return;
    
    if (state.dragData.mode === 'new_section') {
      const layoutId = state.dragData.layoutId;
      const layoutDef = state.layouts[layoutId] || state.layouts['full'];
      const regions = {};
      (layoutDef?.regions || ['main']).forEach(r => { regions[r] = []; });
      state.sections.splice(targetSIdx, 0, { id: generateId(), layout: layoutId, settings: {}, regions: regions });
      pushHistory();
      renderCanvas();
    } else if (state.dragData.mode === 'move_section') {
      const srcIdx = state.dragData.sIdx;
      if (srcIdx === targetSIdx) return;
      const sec = state.sections.splice(srcIdx, 1)[0];
      state.sections.splice(srcIdx < targetSIdx ? targetSIdx - 1 : targetSIdx, 0, sec);
      pushHistory();
      renderCanvas();
    }
    state.dragData = null;
  }

  // ── Device Preview ──────────────────────────────────────────────
  function setDevice(device) {
    state.device = device;
    document.querySelectorAll('.me-toolbar__btn[data-device]').forEach(el => el.classList.remove('is-active'));
    document.querySelector(`.me-toolbar__btn[data-device="${device}"]`)?.classList.add('is-active');
    document.getElementById('canvas-device-frame').className = `me-canvas__device-frame is-${device}`;
  }

  // ── API & Saving ────────────────────────────────────────────────
  function debounceAutosave() {
    if (state.saveTimeout) clearTimeout(state.saveTimeout);
    state.saveTimeout = setTimeout(autosave, 2000);
  }

  async function autosave() {
    if (!state.isDirty) return;
    updateSaveIndicator('saving');
    try {
      const res = await CMS.fetch(`${API}/${state.nodeId}/autosave`, {
        method: 'POST',
        body: JSON.stringify({ content_type: state.contentType, sections: state.sections })
      });
      if (res.ok) { state.isDirty = false; updateSaveIndicator('saved'); }
      else updateSaveIndicator('unsaved');
    } catch (e) { updateSaveIndicator('unsaved'); }
  }
  
  async function save() {
    updateSaveIndicator('saving');
    try {
      const res = await CMS.fetch(`${API}/${state.nodeId}`, {
        method: 'PUT',
        body: JSON.stringify({ content_type: state.contentType, sections: state.sections })
      });
      const json = await res.json();
      if (res.ok) {
        state.revision = json.meta?.revision || state.revision + 1;
        state.isDirty = false;
        updateSaveIndicator('saved');
        document.getElementById('mosaic-revision-label').innerText = `Rev ${state.revision}`;
      } else updateSaveIndicator('unsaved');
    } catch (e) { updateSaveIndicator('unsaved'); }
  }

  function updateSaveIndicator(status) {
    const el = document.getElementById('mosaic-save-status');
    if (!el) return;
    const icons = {
      saved: '<span class="me-status-dot me-status-dot--saved"></span> Saved',
      saving: '<span class="me-status-dot me-status-dot--saving"></span> Saving...',
      unsaved: '<span class="me-status-dot me-status-dot--unsaved"></span> Unsaved'
    };
    el.innerHTML = icons[status] || status;
  }

  // ── Revisions ───────────────────────────────────────────────────
  async function toggleRevisions() {
    const el = document.getElementById('revisions-dropdown');
    if (!el) return;
    if (el.classList.contains('is-open')) { el.classList.remove('is-open'); return; }
    
    el.classList.add('is-open');
    const list = document.getElementById('revisions-list');
    list.innerHTML = '<div class="me-revisions-dropdown__loading">Loading…</div>';
    try {
      const res = await CMS.fetch(`${API}/${state.nodeId}/revisions`);
      const json = await res.json();
      if (res.ok && json.data) {
        let html = '';
        json.data.forEach(rev => {
          html += `<div class="me-revisions-dropdown__item" onclick="MosaicEditor.revert(${rev.revision})"><span class="me-revisions-dropdown__item-rev">Rev ${rev.revision}</span><span class="me-revisions-dropdown__item-date">${rev.created_at}</span></div>`;
        });
        list.innerHTML = html || '<div class="me-revisions-dropdown__loading">No history</div>';
      }
    } catch (e) { list.innerHTML = '<div class="me-revisions-dropdown__loading">Error loading</div>'; }
  }

  async function revert(revNumber) {
    if (!confirm(`Revert to revision ${revNumber}? Current changes will be lost.`)) return;
    document.getElementById('revisions-dropdown')?.classList.remove('is-open');
    try {
      const res = await CMS.fetch(`${API}/${state.nodeId}/revert`, {
        method: 'POST',
        body: JSON.stringify({ revision: revNumber })
      });
      const json = await res.json();
      if (res.ok && json.data) {
        state.sections = json.data.sections || [];
        state.revision = json.data.revision;
        document.getElementById('mosaic-revision-label').innerText = `Rev ${state.revision}`;
        pushHistory(true);
        renderCanvas();
        closeRight();
      }
    } catch (e) { alert('Failed to revert'); }
  }

  // ── Utils ───────────────────────────────────────────────────────
  function generateId() { return Math.random().toString(36).substring(2, 10); }
  function initLucide() { if (window.lucide) window.lucide.createIcons(); }

  /** Initialize Lucide icons inside the Shadow DOM canvas */
  function initLucideShadow() {
    const canvas = getCanvasRoot();
    if (!canvas || !window.lucide) return;
    // Lucide's createIcons scans document by default; we need to scan shadow DOM
    canvas.querySelectorAll('[data-lucide]').forEach(el => {
      const name = el.getAttribute('data-lucide');
      const icon = window.lucide.icons?.[name];
      if (!icon) return;
      const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('width', el.getAttribute('class')?.includes('w-5') ? '20' : el.getAttribute('class')?.includes('w-4') ? '16' : '14');
      svg.setAttribute('height', svg.getAttribute('width'));
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('fill', 'none');
      svg.setAttribute('stroke', 'currentColor');
      svg.setAttribute('stroke-width', '2');
      svg.setAttribute('stroke-linecap', 'round');
      svg.setAttribute('stroke-linejoin', 'round');
      svg.innerHTML = icon[1] || '';
      // Copy classes
      if (el.className) svg.setAttribute('class', el.className);
      el.replaceWith(svg);
    });
  }

  // ── Theme Switching ─────────────────────────────────────────────
  async function switchTheme(themeName) {
    try {
      const res = await CMS.fetch(`${API}/preview-assets?theme=${encodeURIComponent(themeName)}`);
      const json = await res.json();
      if (res.ok && json.css) {
        state.frontCss = json.css;
        state.activeTheme = json.themeName;
        const canvasEl = document.getElementById('mosaic-canvas-content');
        canvasEl?.loadStyles?.(state.frontCss);
        // Clear preview cache so blocks re-render with new theme context
        state.previewCache = {};
        renderCanvas();
      }
    } catch (e) {
      console.error('Mosaic: Failed to switch theme', e);
    }
  }

  // ── Zoom ────────────────────────────────────────────────────────
  function setZoom(percent) {
    state.zoom = parseInt(percent, 10) || 100;
    const canvas = getCanvasRoot();
    if (canvas) {
      const scale = state.zoom / 100;
      canvas.style.transform = scale === 1 ? '' : `scale(${scale})`;
      canvas.style.transformOrigin = 'top center';
      // Compensate parent height so scrolling still works
      canvas.style.width = scale === 1 ? '' : `${100 / scale}%`;
    }
  }

  // ── Compound Fields (link, etc.) ───────────────────────────────
  function updateBlockCompoundField(sIdx, regionName, bIdx, fieldName, subKey, value) {
    const block = state.sections[sIdx].regions[regionName][bIdx];
    if (!block) return;
    if (!block.data) block.data = {};
    if (!block.data[fieldName] || typeof block.data[fieldName] !== 'object') {
      block.data[fieldName] = {};
    }
    block.data[fieldName][subKey] = value;
    fetchBlockPreview(block);
    pushHistory();
  }

  // ── Repeater Operations ────────────────────────────────────────
  function updateRepeaterField(sIdx, regionName, bIdx, fieldName, itemIdx, subField, value) {
    const block = state.sections[sIdx].regions[regionName][bIdx];
    if (!block) return;
    if (!block.data[fieldName]) block.data[fieldName] = [];
    if (!block.data[fieldName][itemIdx]) block.data[fieldName][itemIdx] = {};
    block.data[fieldName][itemIdx][subField] = value;
    fetchBlockPreview(block);
    pushHistory();
  }

  function addRepeaterItem(sIdx, regionName, bIdx, fieldName) {
    const block = state.sections[sIdx].regions[regionName][bIdx];
    if (!block) return;
    if (!block.data[fieldName]) block.data[fieldName] = [];
    block.data[fieldName].push({});
    pushHistory();
    // Re-open the form to show the new item
    selectBlock(block.id, sIdx, regionName, bIdx);
  }

  function removeRepeaterItem(sIdx, regionName, bIdx, fieldName, itemIdx) {
    const block = state.sections[sIdx].regions[regionName][bIdx];
    if (!block || !block.data[fieldName]) return;
    block.data[fieldName].splice(itemIdx, 1);
    pushHistory();
    selectBlock(block.id, sIdx, regionName, bIdx);
  }

  function moveRepeaterItem(sIdx, regionName, bIdx, fieldName, itemIdx, direction) {
    const block = state.sections[sIdx].regions[regionName][bIdx];
    if (!block || !block.data[fieldName]) return;
    const arr = block.data[fieldName];
    const newIdx = itemIdx + direction;
    if (newIdx < 0 || newIdx >= arr.length) return;
    [arr[itemIdx], arr[newIdx]] = [arr[newIdx], arr[itemIdx]];
    pushHistory();
    selectBlock(block.id, sIdx, regionName, bIdx);
  }

  // ── Slot Operations (nested components) ────────────────────────
  function addSlotBlock(sIdx, regionName, bIdx, fieldName, blockType) {
    if (!blockType) return;
    const block = state.sections[sIdx].regions[regionName][bIdx];
    if (!block) return;
    if (!block.data[fieldName]) block.data[fieldName] = [];
    const type = state.blockTypes[blockType] || {};
    const data = {};
    for (const [k, v] of Object.entries(type.fields || {})) data[k] = v.default !== undefined ? v.default : '';
    block.data[fieldName].push({ id: generateId(), blockType, data, settings: {} });
    pushHistory();
    selectBlock(block.id, sIdx, regionName, bIdx);
  }

  function removeSlotBlock(sIdx, regionName, bIdx, fieldName, slotIdx) {
    const block = state.sections[sIdx].regions[regionName][bIdx];
    if (!block || !block.data[fieldName]) return;
    block.data[fieldName].splice(slotIdx, 1);
    pushHistory();
    selectBlock(block.id, sIdx, regionName, bIdx);
  }

  function editSlotBlock(sIdx, regionName, bIdx, fieldName, slotIdx) {
    // TODO: Open a sub-inspector for the nested block
    const block = state.sections[sIdx].regions[regionName][bIdx];
    if (!block || !block.data[fieldName] || !block.data[fieldName][slotIdx]) return;
    alert('Slot block editing coming soon. Block type: ' + block.data[fieldName][slotIdx].blockType);
  }

  // ── Entity Reference Autocomplete ──────────────────────────────
  let _acDebounce = null;
  function entityAutocomplete(inputEl) {
    clearTimeout(_acDebounce);
    _acDebounce = setTimeout(async () => {
      const url = inputEl.dataset.autocompleteUrl;
      const q = inputEl.value.trim();
      const resultsEl = inputEl.nextElementSibling;
      if (!q || q.length < 2) { resultsEl.style.display = 'none'; return; }

      try {
        const res = await CMS.fetch(url + encodeURIComponent(q));
        const json = await res.json();
        const items = json.data || json.results || [];

        if (!items.length) {
          resultsEl.innerHTML = '<div class="mosaic-autocomplete__no-results">No results</div>';
        } else {
          resultsEl.innerHTML = items.map(item => {
            const id = item.id || item.nid || item.tid;
            const label = item.title || item.name || item.label || `#${id}`;
            return `<div class="mosaic-autocomplete__result" onclick="MosaicEditor.selectEntityRef(this, ${id}, '${inputEl.dataset.field}', ${inputEl.dataset.section}, '${inputEl.dataset.region}', ${inputEl.dataset.block}, ${inputEl.dataset.cardinality})">${label}</div>`;
          }).join('');
        }
        resultsEl.style.display = 'block';
      } catch (e) {
        resultsEl.style.display = 'none';
      }
    }, 300);
  }

  function selectEntityRef(el, id, fieldName, sIdx, region, bIdx, cardinality) {
    const block = state.sections[sIdx].regions[region][bIdx];
    if (!block) return;
    if (!block.data) block.data = {};

    if (cardinality === 1 || cardinality === '1') {
      block.data[fieldName] = id;
    } else {
      if (!Array.isArray(block.data[fieldName])) block.data[fieldName] = [];
      if (!block.data[fieldName].includes(id)) block.data[fieldName].push(id);
    }

    pushHistory();
    selectBlock(block.id, sIdx, region, bIdx);
  }

  function removeEntityRef(sIdx, region, bIdx, fieldName, id) {
    const block = state.sections[sIdx].regions[region][bIdx];
    if (!block || !block.data) return;

    if (Array.isArray(block.data[fieldName])) {
      block.data[fieldName] = block.data[fieldName].filter(v => v !== id);
    } else {
      block.data[fieldName] = '';
    }

    pushHistory();
    selectBlock(block.id, sIdx, region, bIdx);
  }

  // ── Language Tab Switching ─────────────────────────────────────
  function switchLangTab(tabGroupId, langCode) {
    const group = document.getElementById(tabGroupId);
    if (!group) return;
    const container = group.closest('.mosaic-field--translatable');
    if (!container) return;

    // Switch active tab
    group.querySelectorAll('.mosaic-lang-tab').forEach(t => {
      t.classList.toggle('mosaic-lang-tab--active', t.dataset.lang === langCode);
    });

    // Switch panels
    container.querySelectorAll('.mosaic-lang-panel').forEach(p => {
      p.style.display = p.dataset.lang === langCode ? '' : 'none';
    });
  }

  function bindKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
      // Skip when in form inputs or inline editing
      if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;
      if (e.target.isContentEditable) {
        // Only intercept Cmd+S while editing
        if ((e.metaKey || e.ctrlKey) && e.key === 's') { e.preventDefault(); save(); }
        return;
      }
      if ((e.metaKey || e.ctrlKey) && e.key === 's') { e.preventDefault(); save(); }
      else if ((e.metaKey || e.ctrlKey) && e.key === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
      else if ((e.metaKey || e.ctrlKey) && e.key === 'z' && e.shiftKey) { e.preventDefault(); redo(); }
      else if (e.key === 'Escape') closeRight();
      else if ((e.key === 'Backspace' || e.key === 'Delete') && state.selected.type) {
        e.preventDefault();
        if (state.selected.type === 'block') removeBlock(state.selected.sectionIdx, state.selected.regionName, state.selected.blockIdx);
        else if (state.selected.type === 'section') removeSection(state.selected.sectionIdx);
      }
      else if (e.key === 'd' && state.selected.type === 'block') { e.preventDefault(); duplicateBlock(state.selected.sectionIdx, state.selected.regionName, state.selected.blockIdx); }
    });
  }

  window.MosaicEditor = {
    init, undo, redo, save, setDevice, setZoom, switchTheme, switchLeftTab, filterBlocks,
    closeRight, selectBlock, selectSection,
    addSection, removeSection, duplicateSection, moveSection,
    updateSectionSetting, setSectionLayout,
    addBlock, removeBlock, duplicateBlock,
    updateBlockField, updateBlockSetting, updateBlockCompoundField,
    // Repeater
    updateRepeaterField, addRepeaterItem, removeRepeaterItem, moveRepeaterItem,
    // Slot
    addSlotBlock, removeSlotBlock, editSlotBlock,
    // Entity reference
    entityAutocomplete, selectEntityRef, removeEntityRef,
    // Language tabs
    switchLangTab,
    // Existing
    onInlineBlur, onTypeDragStart, onLayoutDragStart, onBlockDragStart, onSectionDragStart,
    onRegionDragOver, onRegionDragLeave, onRegionDrop, onSectionDragOver, onSectionDrop,
    toggleRevisions, revert,
    promptAddSection: () => addSection('full'),
  };
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('click', (e) => { if (!e.target.closest('#revision-trigger')) document.getElementById('revisions-dropdown')?.classList.remove('is-open'); });
  window.addEventListener('beforeunload', (e) => { if (state.isDirty) { save(); e.preventDefault(); e.returnValue = ''; } });
})();
