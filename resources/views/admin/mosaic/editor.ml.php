@extends('layouts.admin')

@section('title', $title ?? 'Mosaic Editor')

@section('head')
<style>
  .mosaic-editor-wrapper { display: flex; height: calc(100vh - var(--cms-toolbar-height)); }
  .mosaic-editor-main { flex: 1; overflow-y: auto; padding: 1.5rem; }
  .mosaic-editor-sidebar { width: 320px; border-left: 1px solid var(--cms-border); background: var(--cms-bg-surface); overflow-y: auto; transition: width 200ms ease; }
  .mosaic-editor-sidebar.closed { width: 0; overflow: hidden; }
</style>
@endsection

@section('content')
<div id="mosaic-app" class="mosaic-editor-wrapper">

  {{-- ═══ Main Editor Area ═══ --}}
  <div class="mosaic-editor-main">

    {{-- Toolbar --}}
    <div class="mosaic-editor__toolbar" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem;">
      <div>
        <h2 style="font-size:1.2rem; font-weight:600; color:var(--cms-text-heading);">
          {{ $node->title ?? 'Untitled' }}
        </h2>
        <span style="font-size:0.8rem; color:var(--cms-text-muted);">Mosaic Editor</span>
      </div>
      <div style="display:flex; gap:0.5rem; align-items:center;">
        <span $m-show="state.isDirty && !state.saving" class="badge badge--draft">Unsaved changes</span>
        <span $m-show="state.saving" class="badge badge--draft">Saving...</span>
        <span $m-show="state.lastSaved" class="badge badge--published" style="font-size:0.75rem;"
              $m-text="'Saved ' + state.lastSaved"></span>
        <button class="btn btn-secondary btn-sm" $m-on:click="preview()">Preview</button>
        <button class="btn btn-primary btn-sm" $m-on:click="save()">Save</button>
      </div>
    </div>

    {{-- Sections --}}
    <div class="mosaic-sections">
      <template $m-for="(section, sIdx) in state.sections">
        <div class="mosaic-section" :key="section.id">

          {{-- Section Toolbar --}}
          <div class="mosaic-section__toolbar">
            <span style="font-size:0.8rem; font-weight:600; color:var(--cms-text-muted);">
              Section
            </span>
            <select class="form-select" style="width:auto; padding:0.25rem 0.5rem; font-size:0.8rem;"
                    $m-model="section.layout"
                    $m-on:change="changeSectionLayout(section.id, section.layout)">
              @foreach($layouts as $layoutId => $layout)
              <option value="{{ $layoutId }}">{{ $layout['label'] }}</option>
              @endforeach
            </select>
            <div style="margin-left:auto; display:flex; gap:0.25rem;">
              <button class="btn btn-secondary btn-sm" $m-on:click="moveSectionUp(section.id)" title="Move Up">↑</button>
              <button class="btn btn-secondary btn-sm" $m-on:click="moveSectionDown(section.id)" title="Move Down">↓</button>
              <button class="btn btn-danger btn-sm" $m-on:click="removeSection(section.id)" title="Remove">✕</button>
            </div>
          </div>

          {{-- Regions Grid --}}
          <div class="mosaic-regions" :class="'layout--' + section.layout">
            <template $m-for="(blocks, regionId) in section.regions">
              <div class="mosaic-region"
                   :key="regionId"
                   $m-on:dragover="onDragOver($event)"
                   $m-on:drop="onDrop($event, section.id, regionId)">

                {{-- Region Label --}}
                <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--cms-text-muted); margin-bottom:0.5rem;"
                     $m-text="regionId"></div>

                {{-- Blocks --}}
                <template $m-for="(block, bIdx) in blocks">
                  <div class="mosaic-block"
                       :key="block.id"
                       draggable="true"
                       $m-on:dragstart="onDragStart($event, section.id, regionId, bIdx)"
                       $m-on:dragend="onDragEnd()">
                    <div class="mosaic-block__header">
                      <span>
                        <span $m-text="state.blockTypes[block.blockType]?.icon || '🧱'"></span>
                        <span $m-text="state.blockTypes[block.blockType]?.label || block.blockType"></span>
                      </span>
                      <div style="display:flex; gap:0.25rem;">
                        <button class="btn btn-secondary btn-sm"
                                $m-on:click="editBlock(section.id, regionId, block.id)"
                                title="Edit">✏️</button>
                        <button class="btn btn-danger btn-sm"
                                $m-on:click="removeBlock(section.id, regionId, block.id)"
                                title="Remove">✕</button>
                      </div>
                    </div>
                    {{-- Block preview --}}
                    <div class="mosaic-block__preview"
                         style="font-size:0.85rem; color:var(--cms-text-muted); max-height:80px; overflow:hidden;"
                         $m-html="block.preview || Object.values(block.data).filter(v => typeof v === 'string').join(' ').slice(0, 120) || '<em>Empty block</em>'">
                    </div>
                  </div>
                </template>

                {{-- Add Block Button --}}
                <button class="mosaic-add-block"
                        $m-on:click="openBlockPicker(section.id, regionId)">
                  + Add Block
                </button>
              </div>
            </template>
          </div>
        </div>
      </template>
    </div>

    {{-- Add Section Button --}}
    <button class="mosaic-add-section" $m-on:click="addSection('full')">
      + Add Section
    </button>
  </div>

  {{-- ═══ Block Picker Modal ═══ --}}
  <div $m-show="state.blockPickerOpen"
       style="position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:200; display:flex; align-items:center; justify-content:center;"
       $m-on:click.self="closeBlockPicker()">
    <div style="background:var(--cms-bg-surface); border:1px solid var(--cms-border); border-radius:var(--cms-radius-lg); width:500px; max-height:70vh; overflow-y:auto; padding:1.5rem;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h3 style="font-size:1.1rem; font-weight:600; color:var(--cms-text-heading);">Add Block</h3>
        <button class="btn btn-secondary btn-sm" $m-on:click="closeBlockPicker()">✕</button>
      </div>
      <template $m-for="(types, category) in state.blockTypesGrouped">
        <div style="margin-bottom:1rem;">
          <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--cms-text-muted); margin-bottom:0.5rem;"
               $m-text="category"></div>
          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.5rem;">
            <template $m-for="bt in types">
              <button style="display:flex; align-items:center; gap:0.5rem; padding:0.75rem; background:var(--cms-bg-card); border:1px solid var(--cms-border); border-radius:var(--cms-radius-sm); cursor:pointer; text-align:left; color:var(--cms-text); transition:all 200ms;"
                      $m-on:click="addBlock(bt.id)">
                <span style="font-size:1.5rem;" $m-text="bt.icon"></span>
                <div>
                  <div style="font-weight:600; font-size:0.875rem;" $m-text="bt.label"></div>
                  <div style="font-size:0.75rem; color:var(--cms-text-muted);" $m-text="bt.description"></div>
                </div>
              </button>
            </template>
          </div>
        </div>
      </template>
    </div>
  </div>

  {{-- ═══ Settings Sidebar ═══ --}}
  <div class="mosaic-editor-sidebar" :class="{ closed: !state.settingsPanelOpen }">
    <div $m-show="state.settingsPanelOpen && state.activeBlock" style="padding:1rem;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h3 style="font-size:1rem; font-weight:600; color:var(--cms-text-heading);"
            $m-text="state.blockTypes[state.activeBlock?.blockType]?.label + ' Settings' || 'Block Settings'"></h3>
        <button class="btn btn-secondary btn-sm" $m-on:click="closeSettings()">✕</button>
      </div>

      {{-- Dynamic block fields --}}
      <template $m-if="state.activeBlock">
        <template $m-for="(fieldDef, fieldKey) in (state.blockTypes[state.activeBlock.blockType]?.fields || {})">
          <div class="form-group">
            <label class="form-label" $m-text="fieldDef.label"></label>

            {{-- Text input --}}
            <template $m-if="fieldDef.type === 'string' || fieldDef.type === 'url'">
              <input class="form-input" type="text"
                     :value="state.activeBlock.data[fieldKey] || ''"
                     $m-on:input="updateBlockData(state.activeBlock.id, fieldKey, $event.target.value)">
            </template>

            {{-- HTML / Textarea --}}
            <template $m-if="fieldDef.type === 'html' || fieldDef.type === 'code'">
              <textarea class="form-textarea"
                        rows="6"
                        $m-on:input="updateBlockData(state.activeBlock.id, fieldKey, $event.target.value)"
                        $m-text="state.activeBlock.data[fieldKey] || ''"></textarea>
            </template>

            {{-- Select --}}
            <template $m-if="fieldDef.type === 'select'">
              <select class="form-select"
                      $m-on:change="updateBlockData(state.activeBlock.id, fieldKey, $event.target.value)">
                <template $m-for="(optLabel, optVal) in (fieldDef.options || {})">
                  <option :value="optVal" $m-text="optLabel"></option>
                </template>
              </select>
            </template>

            {{-- Media --}}
            <template $m-if="fieldDef.type === 'media'">
              <input class="form-input" type="number" placeholder="Media ID"
                     :value="state.activeBlock.data[fieldKey] || ''"
                     $m-on:input="updateBlockData(state.activeBlock.id, fieldKey, $event.target.value)">
              <span style="font-size:0.75rem; color:var(--cms-text-muted);">Enter media library ID</span>
            </template>
          </div>
        </template>
      </template>
    </div>
  </div>

  {{-- ═══ Preview Modal ═══ --}}
  <div $m-show="state.previewMode"
       style="position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:300; display:flex; flex-direction:column;">
    <div style="display:flex; justify-content:space-between; align-items:center; padding:1rem; background:var(--cms-bg-surface); border-bottom:1px solid var(--cms-border);">
      <h3 style="font-weight:600; color:var(--cms-text-heading);">Preview</h3>
      <button class="btn btn-secondary btn-sm" $m-on:click="closePreview()">Close Preview</button>
    </div>
    <div style="flex:1; overflow-y:auto; background:#fff; padding:2rem;">
      <div $m-html="state.previewHtml"></div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script type="module">
  import { createApp } from 'monkeysjs';
  import { createMosaicEditor } from '/build/assets/mosaic-editor.js';

  const editor = createMosaicEditor(
    {{ $node->id ?? 0 }},
    '{{ $node->content_type ?? 'page' }}',
    {!! json_encode($sections ?? []) !!}
  );

  const app = createApp({
    state: editor.state,
    ...editor,
  });

  app.mount('#mosaic-app');
</script>
@endpush
