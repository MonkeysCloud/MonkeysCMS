/**
 * MonkeysCMS — Mosaic Editor
 *
 * Visual page builder component powered by MonkeysJS.
 * Handles: section management, block drag-and-drop, inline editing,
 * autosave, and live preview via the Mosaic API.
 */

import {
  reactive,
  ref,
  watch,
  computed,
  http,
  createClient,
  debounce,
  uuid,
} from 'monkeysjs';

// ─── API Client ─────────────────────────────────────────────────────────────
const api = createClient({
  baseURL: '/admin/api/mosaic',
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// ─── Mosaic Editor State ────────────────────────────────────────────────────
export function createMosaicEditor(nodeId, contentType, initialSections = []) {
  const state = reactive({
    nodeId,
    contentType,
    sections: initialSections,
    blockTypes: {},
    blockTypesGrouped: {},
    layouts: {},

    // UI state
    activeSection: null,
    activeBlock: null,
    blockPickerOpen: false,
    blockPickerTarget: null, // { sectionId, regionId }
    settingsPanelOpen: false,
    isDirty: false,
    saving: false,
    lastSaved: null,
    previewHtml: '',
    previewMode: false,
    dragState: null,
  });

  // ── Load block types and layouts ──────────────────────────────────────
  async function init() {
    try {
      const [blocksRes, layoutsRes] = await Promise.all([
        api.get('/blocks/types'),
        api.get('/sections/layouts'),
      ]);

      state.blockTypes = blocksRes.data?.data || {};
      state.blockTypesGrouped = blocksRes.data?.grouped || {};
      state.layouts = layoutsRes.data?.data || {};
    } catch (err) {
      console.error('[Mosaic] Failed to load block types:', err);
    }
  }

  // ── Section Operations ────────────────────────────────────────────────
  function addSection(layout = 'full') {
    const layoutDef = state.layouts[layout];
    const regions = {};

    if (layoutDef?.regions) {
      layoutDef.regions.forEach(r => { regions[r] = []; });
    } else {
      regions.main = [];
    }

    state.sections.push({
      id: 'sec_' + uuid().slice(0, 12),
      layout,
      settings: { gap: '1rem', padding: '1rem' },
      regions,
    });

    state.isDirty = true;
  }

  function removeSection(sectionId) {
    const idx = state.sections.findIndex(s => s.id === sectionId);
    if (idx !== -1) {
      state.sections.splice(idx, 1);
      state.isDirty = true;
    }
  }

  function moveSectionUp(sectionId) {
    const idx = state.sections.findIndex(s => s.id === sectionId);
    if (idx > 0) {
      [state.sections[idx - 1], state.sections[idx]] = [state.sections[idx], state.sections[idx - 1]];
      state.isDirty = true;
    }
  }

  function moveSectionDown(sectionId) {
    const idx = state.sections.findIndex(s => s.id === sectionId);
    if (idx < state.sections.length - 1) {
      [state.sections[idx], state.sections[idx + 1]] = [state.sections[idx + 1], state.sections[idx]];
      state.isDirty = true;
    }
  }

  function changeSectionLayout(sectionId, newLayout) {
    const section = state.sections.find(s => s.id === sectionId);
    if (!section) return;

    const layoutDef = state.layouts[newLayout];
    if (!layoutDef) return;

    // Preserve existing blocks, redistribute into new regions
    const allBlocks = [];
    Object.values(section.regions).forEach(blocks => {
      allBlocks.push(...blocks);
    });

    const newRegions = {};
    layoutDef.regions.forEach((r, i) => {
      newRegions[r] = i === 0 ? allBlocks : [];
    });

    section.layout = newLayout;
    section.regions = newRegions;
    state.isDirty = true;
  }

  // ── Block Operations ──────────────────────────────────────────────────
  function openBlockPicker(sectionId, regionId) {
    state.blockPickerTarget = { sectionId, regionId };
    state.blockPickerOpen = true;
  }

  function closeBlockPicker() {
    state.blockPickerOpen = false;
    state.blockPickerTarget = null;
  }

  function addBlock(blockType) {
    if (!state.blockPickerTarget) return;

    const { sectionId, regionId } = state.blockPickerTarget;
    const section = state.sections.find(s => s.id === sectionId);
    if (!section || !section.regions[regionId]) return;

    const typeDef = state.blockTypes[blockType];
    const defaultData = {};

    // Initialize with default field values
    if (typeDef?.fields) {
      Object.entries(typeDef.fields).forEach(([key, field]) => {
        defaultData[key] = field.default ?? '';
      });
    }

    section.regions[regionId].push({
      id: 'blk_' + uuid().slice(0, 12),
      blockType,
      data: defaultData,
      settings: {},
      preview: '',
    });

    state.isDirty = true;
    closeBlockPicker();
  }

  function removeBlock(sectionId, regionId, blockId) {
    const section = state.sections.find(s => s.id === sectionId);
    if (!section || !section.regions[regionId]) return;

    const idx = section.regions[regionId].findIndex(b => b.id === blockId);
    if (idx !== -1) {
      section.regions[regionId].splice(idx, 1);
      state.isDirty = true;

      if (state.activeBlock?.id === blockId) {
        state.activeBlock = null;
        state.settingsPanelOpen = false;
      }
    }
  }

  function editBlock(sectionId, regionId, blockId) {
    const section = state.sections.find(s => s.id === sectionId);
    if (!section || !section.regions[regionId]) return;

    const block = section.regions[regionId].find(b => b.id === blockId);
    if (block) {
      state.activeBlock = { ...block, sectionId, regionId };
      state.settingsPanelOpen = true;
    }
  }

  function updateBlockData(blockId, field, value) {
    for (const section of state.sections) {
      for (const blocks of Object.values(section.regions)) {
        const block = blocks.find(b => b.id === blockId);
        if (block) {
          block.data[field] = value;
          state.isDirty = true;
          return;
        }
      }
    }
  }

  function closeSettings() {
    state.activeBlock = null;
    state.settingsPanelOpen = false;
  }

  // ── Drag & Drop ───────────────────────────────────────────────────────
  function onDragStart(e, sectionId, regionId, blockIdx) {
    state.dragState = { sectionId, regionId, blockIdx };
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', '');
  }

  function onDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
  }

  function onDrop(e, targetSectionId, targetRegionId, targetIdx) {
    e.preventDefault();
    if (!state.dragState) return;

    const { sectionId: srcSec, regionId: srcReg, blockIdx: srcIdx } = state.dragState;
    const srcSection = state.sections.find(s => s.id === srcSec);
    const tgtSection = state.sections.find(s => s.id === targetSectionId);

    if (!srcSection || !tgtSection) return;

    const [block] = srcSection.regions[srcReg].splice(srcIdx, 1);
    tgtSection.regions[targetRegionId].splice(targetIdx ?? tgtSection.regions[targetRegionId].length, 0, block);

    state.dragState = null;
    state.isDirty = true;
  }

  function onDragEnd() {
    state.dragState = null;
  }

  // ── Save & Preview ────────────────────────────────────────────────────
  async function save() {
    if (state.saving) return;
    state.saving = true;

    try {
      const res = await api.put(`/${state.nodeId}`, {
        content_type: state.contentType,
        sections: state.sections,
      });

      state.isDirty = false;
      state.lastSaved = new Date().toLocaleTimeString();

      return res.data;
    } catch (err) {
      console.error('[Mosaic] Save failed:', err);
      throw err;
    } finally {
      state.saving = false;
    }
  }

  async function preview() {
    try {
      const res = await api.post(`/${state.nodeId}/preview`, {
        sections: state.sections,
      });
      state.previewHtml = res.data?.html || '';
      state.previewMode = true;
    } catch (err) {
      console.error('[Mosaic] Preview failed:', err);
    }
  }

  function closePreview() {
    state.previewMode = false;
    state.previewHtml = '';
  }

  // ── Autosave (debounced) ──────────────────────────────────────────────
  const autosave = debounce(() => {
    if (state.isDirty && !state.saving) {
      save().catch(() => {});
    }
  }, 30000); // 30s debounce

  // Watch for changes and trigger autosave
  watch(() => state.sections, () => {
    if (state.isDirty) autosave();
  }, { deep: true });

  // ── Initialize ────────────────────────────────────────────────────────
  init();

  return {
    state,
    // Section ops
    addSection,
    removeSection,
    moveSectionUp,
    moveSectionDown,
    changeSectionLayout,
    // Block ops
    openBlockPicker,
    closeBlockPicker,
    addBlock,
    removeBlock,
    editBlock,
    updateBlockData,
    closeSettings,
    // Drag & Drop
    onDragStart,
    onDragOver,
    onDrop,
    onDragEnd,
    // Persistence
    save,
    preview,
    closePreview,
  };
}
