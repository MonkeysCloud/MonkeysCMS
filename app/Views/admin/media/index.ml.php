@extends('layouts.admin')

@section('title', 'Media Library')

@section('breadcrumbs')
    <a href="/admin">Dashboard</a>
    <span>/</span>
    <span>Media</span>
@endsection

@section('page_title', 'Media Library')

@section('page_actions')
    <button class="btn btn-primary" id="upload-btn">
        📤 Upload Files
    </button>
@endsection

@section('content')
<div class="media-library">
    {{-- Toolbar --}}
    <div class="media-toolbar">
        <div class="toolbar-left">
            <div class="view-toggle">
                <button class="btn btn-sm btn-outline active" data-view="grid" title="Grid view">▦</button>
                <button class="btn btn-sm btn-outline" data-view="list" title="List view">☰</button>
            </div>
            
            <select class="form-control" id="filter-type" style="width: auto;">
                <option value="">All Types</option>
                <option value="image">🖼️ Images</option>
                <option value="video">🎬 Videos</option>
                <option value="audio">🎵 Audio</option>
                <option value="document">📄 Documents</option>
                <option value="archive">📦 Archives</option>
            </select>
            
            <select class="form-control" id="filter-folder" style="width: auto;">
                <option value="">All Folders</option>
                <!-- Populated by JS -->
            </select>
        </div>
        
        <div class="toolbar-right">
            <input type="text" class="form-control" id="media-search" placeholder="Search media..." style="width: 250px;">
            
            <div class="bulk-actions" id="bulk-actions" style="display: none;">
                <span class="selected-count">0 selected</span>
                <button class="btn btn-sm btn-outline" id="bulk-move">Move</button>
                <button class="btn btn-sm btn-danger" id="bulk-delete">Delete</button>
            </div>
        </div>
    </div>
    
    {{-- Main Content --}}
    <div class="media-container">
        {{-- Folders Sidebar --}}
        <aside class="media-sidebar" id="media-sidebar">
            <h4>Folders</h4>
            <ul class="folder-tree" id="folder-tree">
                <li class="active" data-folder="">
                    <span class="folder-icon">📁</span>
                    <span class="folder-name">All Files</span>
                    <span class="folder-count"></span>
                </li>
                <!-- Populated by JS -->
            </ul>
            
            <button class="btn btn-sm btn-outline" id="new-folder-btn" style="margin-top: 1rem; width: 100%;">
                + New Folder
            </button>
        </aside>
        
        {{-- Media Grid/List --}}
        <main class="media-main">
            <div class="media-grid" id="media-grid">
                <div class="loading">Loading media...</div>
            </div>
            
            <div class="pagination-wrapper" id="pagination"></div>
        </main>
    </div>
</div>

{{-- Upload Modal --}}
<div class="modal-overlay" id="upload-modal">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title">Upload Files</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body">
            <div class="upload-dropzone" id="upload-dropzone">
                <div class="dropzone-content">
                    <span class="dropzone-icon">📤</span>
                    <p>Drag & drop files here</p>
                    <p class="form-text">or</p>
                    <button class="btn btn-primary" id="select-files-btn">Select Files</button>
                    <input type="file" id="file-input" multiple hidden>
                </div>
            </div>
            
            <div class="upload-options" style="margin-top: 1rem;">
                <div class="form-group">
                    <label class="form-label">Upload to folder</label>
                    <select class="form-control" id="upload-folder">
                        <option value="">Default (by date)</option>
                        <!-- Populated by JS -->
                    </select>
                </div>
            </div>
            
            <div class="upload-queue" id="upload-queue" style="display: none;">
                <h4>Upload Queue</h4>
                <div class="queue-list" id="queue-list">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-modal-close>Close</button>
            <button type="button" class="btn btn-primary" id="start-upload" disabled>Upload Files</button>
        </div>
    </div>
</div>

{{-- Media Details Modal --}}
<div class="modal-overlay" id="details-modal">
    <div class="modal" style="max-width: 900px;">
        <div class="modal-header">
            <h3 class="modal-title">Media Details</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body">
            <div class="media-details-grid">
                <div class="media-preview" id="media-preview">
                    <!-- Preview content -->
                </div>
                <div class="media-info">
                    <form id="media-edit-form">
                        <input type="hidden" id="media-id">
                        
                        <div class="form-group">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" id="media-title" name="title">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Alt Text</label>
                            <input type="text" class="form-control" id="media-alt" name="alt">
                            <span class="form-text">Describe the image for accessibility</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="media-description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">File Info</label>
                            <div class="file-info" id="file-info">
                                <!-- Populated by JS -->
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">URL</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="media-url" readonly>
                                <button type="button" class="btn btn-outline" id="copy-url">Copy</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-danger" id="delete-media" style="margin-right: auto;">Delete</button>
            <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
            <button type="button" class="btn btn-primary" id="save-media">Save Changes</button>
        </div>
    </div>
</div>

{{-- Image Editor Modal --}}
<div class="modal-overlay" id="editor-modal">
    <div class="modal" style="max-width: 1000px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Image</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body">
            <div class="image-editor">
                <div class="editor-canvas" id="editor-canvas">
                    <img id="editor-image" src="">
                </div>
                <div class="editor-tools">
                    <button class="btn btn-sm btn-outline" data-action="rotate-left" title="Rotate Left">↺</button>
                    <button class="btn btn-sm btn-outline" data-action="rotate-right" title="Rotate Right">↻</button>
                    <button class="btn btn-sm btn-outline" data-action="crop" title="Crop">✂️</button>
                    <button class="btn btn-sm btn-outline" data-action="regenerate" title="Regenerate Variants">🔄</button>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
            <button type="button" class="btn btn-primary" id="save-edit">Save Changes</button>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.media-library {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 180px);
}

.media-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--admin-card-bg);
    border-radius: var(--admin-border-radius);
    margin-bottom: 1rem;
    gap: 1rem;
    flex-wrap: wrap;
}

.toolbar-left, .toolbar-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.view-toggle {
    display: flex;
    gap: 0.25rem;
}

.view-toggle button.active {
    background: var(--admin-primary);
    color: white;
    border-color: var(--admin-primary);
}

.bulk-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-left: 1rem;
    border-left: 1px solid var(--admin-border);
}

.selected-count {
    font-size: 0.875rem;
    color: var(--admin-text-muted);
}

.media-container {
    display: flex;
    flex: 1;
    gap: 1rem;
    overflow: hidden;
}

.media-sidebar {
    width: 220px;
    background: var(--admin-card-bg);
    border-radius: var(--admin-border-radius);
    padding: 1rem;
    overflow-y: auto;
}

.media-sidebar h4 {
    margin-bottom: 0.75rem;
    font-size: 0.875rem;
    text-transform: uppercase;
    color: var(--admin-text-muted);
}

.folder-tree {
    list-style: none;
    padding: 0;
    margin: 0;
}

.folder-tree li {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    border-radius: var(--admin-border-radius-sm);
    cursor: pointer;
    transition: background 0.15s;
}

.folder-tree li:hover {
    background: var(--admin-bg);
}

.folder-tree li.active {
    background: var(--admin-primary);
    color: white;
}

.folder-count {
    margin-left: auto;
    font-size: 0.75rem;
    opacity: 0.7;
}

.media-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.media-grid {
    flex: 1;
    overflow-y: auto;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
    padding: 1rem;
    background: var(--admin-card-bg);
    border-radius: var(--admin-border-radius);
}

.media-grid.list-view {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.media-item {
    position: relative;
    aspect-ratio: 1;
    border-radius: var(--admin-border-radius-sm);
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;
    background: var(--admin-bg);
}

.media-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--admin-shadow);
}

.media-item.selected {
    outline: 3px solid var(--admin-primary);
}

.media-item .media-thumb {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.media-item .media-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    font-size: 3rem;
}

.media-item .media-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 0.5rem;
    background: linear-gradient(transparent, rgba(0,0,0,0.7));
    color: white;
    font-size: 0.75rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    opacity: 0;
    transition: opacity 0.15s;
}

.media-item:hover .media-overlay {
    opacity: 1;
}

.media-item .select-checkbox {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    opacity: 0;
    transition: opacity 0.15s;
}

.media-item:hover .select-checkbox,
.media-item.selected .select-checkbox {
    opacity: 1;
}

/* List view */
.list-view .media-item {
    aspect-ratio: unset;
    display: flex;
    align-items: center;
    padding: 0.75rem;
    border-bottom: 1px solid var(--admin-border);
    border-radius: 0;
}

.list-view .media-item .media-thumb,
.list-view .media-item .media-icon {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    border-radius: var(--admin-border-radius-sm);
}

.list-view .media-item .media-icon {
    font-size: 1.5rem;
}

.list-view .media-item .media-info {
    flex: 1;
    margin-left: 1rem;
}

.list-view .media-item .media-overlay {
    position: static;
    background: none;
    color: inherit;
    opacity: 1;
    padding: 0;
}

/* Upload dropzone */
.upload-dropzone {
    border: 2px dashed var(--admin-border);
    border-radius: var(--admin-border-radius);
    padding: 3rem;
    text-align: center;
    transition: border-color 0.15s, background 0.15s;
}

.upload-dropzone.drag-over {
    border-color: var(--admin-primary);
    background: rgba(59, 130, 246, 0.05);
}

.dropzone-icon {
    font-size: 3rem;
    display: block;
    margin-bottom: 1rem;
}

.upload-queue {
    margin-top: 1.5rem;
}

.queue-list {
    max-height: 200px;
    overflow-y: auto;
}

.queue-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    background: var(--admin-bg);
    border-radius: var(--admin-border-radius-sm);
    margin-bottom: 0.5rem;
}

.queue-item .file-name {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.queue-item .file-size {
    color: var(--admin-text-muted);
    font-size: 0.875rem;
}

.queue-item .progress-bar {
    width: 100px;
    height: 4px;
    background: var(--admin-border);
    border-radius: 2px;
    overflow: hidden;
}

.queue-item .progress-fill {
    height: 100%;
    background: var(--admin-primary);
    transition: width 0.3s;
}

.queue-item .status-icon {
    font-size: 1rem;
}

/* Media details */
.media-details-grid {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 1.5rem;
}

.media-preview {
    background: var(--admin-bg);
    border-radius: var(--admin-border-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 300px;
    overflow: hidden;
}

.media-preview img,
.media-preview video {
    max-width: 100%;
    max-height: 400px;
    object-fit: contain;
}

.file-info {
    background: var(--admin-bg);
    padding: 0.75rem;
    border-radius: var(--admin-border-radius-sm);
    font-size: 0.875rem;
}

.file-info div {
    display: flex;
    justify-content: space-between;
    padding: 0.25rem 0;
}

.file-info div + div {
    border-top: 1px solid var(--admin-border);
}

/* Image editor */
.image-editor {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.editor-canvas {
    background: var(--admin-bg);
    border-radius: var(--admin-border-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 400px;
    overflow: hidden;
}

.editor-canvas img {
    max-width: 100%;
    max-height: 400px;
}

.editor-tools {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
}

@media (max-width: 768px) {
    .media-sidebar {
        display: none;
    }
    
    .media-grid {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    }
    
    .media-details-grid {
        grid-template-columns: 1fr;
    }
}
</style>
@endpush

@push('scripts')
<script>
(function() {
    'use strict';

    let mediaItems = [];
    let folders = {};
    let selectedItems = new Set();
    let currentPage = 1;
    let totalPages = 1;
    let currentView = 'grid';
    let currentFolder = '';
    let currentType = '';
    let uploadQueue = [];
    let editingMedia = null;

    // =========================================================================
    // Initialize
    // =========================================================================

    async function init() {
        await loadFolders();
        await loadMedia();
        setupEventListeners();
    }

    // =========================================================================
    // Data Loading
    // =========================================================================

    async function loadFolders() {
        try {
            const response = await adminApi.get('/admin/media/folders');
            folders = response.data;
            renderFolders();
        } catch (error) {
            console.error('Failed to load folders:', error);
        }
    }

    async function loadMedia(page = 1) {
        const params = new URLSearchParams();
        params.set('page', page);
        params.set('per_page', 24);
        
        if (currentType) params.set('type', currentType);
        if (currentFolder) params.set('folder', currentFolder);
        
        const search = document.getElementById('media-search').value;
        if (search) params.set('q', search);

        try {
            const response = await adminApi.get('/admin/media?' + params.toString());
            mediaItems = response.data;
            currentPage = response.meta.page;
            totalPages = response.meta.total_pages;
            renderMedia();
            renderPagination();
        } catch (error) {
            showToast('Failed to load media', 'danger');
        }
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    function renderFolders() {
        const container = document.getElementById('folder-tree');
        let html = `
            <li class="${currentFolder === '' ? 'active' : ''}" data-folder="">
                <span class="folder-icon">📁</span>
                <span class="folder-name">All Files</span>
            </li>
        `;
        
        Object.entries(folders).forEach(([folder, count]) => {
            html += `
                <li class="${currentFolder === folder ? 'active' : ''}" data-folder="${escapeHtml(folder)}">
                    <span class="folder-icon">📂</span>
                    <span class="folder-name">${escapeHtml(folder)}</span>
                    <span class="folder-count">${count}</span>
                </li>
            `;
        });
        
        container.innerHTML = html;
        
        // Also populate folder selects
        const uploadFolder = document.getElementById('upload-folder');
        const filterFolder = document.getElementById('filter-folder');
        
        const folderOptions = '<option value="">All Folders</option>' + 
            Object.keys(folders).map(f => `<option value="${escapeHtml(f)}">${escapeHtml(f)}</option>`).join('');
        
        filterFolder.innerHTML = folderOptions;
        uploadFolder.innerHTML = '<option value="">Default (by date)</option>' +
            Object.keys(folders).map(f => `<option value="${escapeHtml(f)}">${escapeHtml(f)}</option>`).join('');
    }

    function renderMedia() {
        const container = document.getElementById('media-grid');
        
        if (mediaItems.length === 0) {
            container.innerHTML = '<div class="empty-state">No media found</div>';
            return;
        }
        
        container.innerHTML = mediaItems.map(media => {
            const isSelected = selectedItems.has(media.id);
            
            let preview;
            if (media.is_image) {
                const thumbUrl = media.variants?.thumbnail?.url || media.url;
                preview = `<img class="media-thumb" src="${thumbUrl}" alt="${escapeHtml(media.title)}" loading="lazy">`;
            } else {
                preview = `<div class="media-icon">${getMediaIcon(media.media_type)}</div>`;
            }
            
            return `
                <div class="media-item ${isSelected ? 'selected' : ''}" data-id="${media.id}">
                    <input type="checkbox" class="select-checkbox" ${isSelected ? 'checked' : ''}>
                    ${preview}
                    <div class="media-overlay">${escapeHtml(media.title || media.filename)}</div>
                </div>
            `;
        }).join('');
        
        // Update view class
        container.classList.toggle('list-view', currentView === 'list');
    }

    function renderPagination() {
        const container = document.getElementById('pagination');
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let html = '<ul class="pagination">';
        
        if (currentPage > 1) {
            html += `<li><a href="#" data-page="${currentPage - 1}">&laquo;</a></li>`;
        }
        
        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                html += `<li class="active"><span>${i}</span></li>`;
            } else {
                html += `<li><a href="#" data-page="${i}">${i}</a></li>`;
            }
        }
        
        if (currentPage < totalPages) {
            html += `<li><a href="#" data-page="${currentPage + 1}">&raquo;</a></li>`;
        }
        
        html += '</ul>';
        container.innerHTML = html;
    }

    // =========================================================================
    // Event Listeners
    // =========================================================================

    function setupEventListeners() {
        // View toggle
        document.querySelectorAll('.view-toggle button').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.view-toggle button').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentView = this.dataset.view;
                renderMedia();
            });
        });
        
        // Filters
        document.getElementById('filter-type').addEventListener('change', function() {
            currentType = this.value;
            loadMedia(1);
        });
        
        document.getElementById('filter-folder').addEventListener('change', function() {
            currentFolder = this.value;
            loadMedia(1);
        });
        
        // Search
        let searchTimeout;
        document.getElementById('media-search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => loadMedia(1), 300);
        });
        
        // Folder selection
        document.getElementById('folder-tree').addEventListener('click', function(e) {
            const li = e.target.closest('li');
            if (li) {
                currentFolder = li.dataset.folder;
                document.querySelectorAll('#folder-tree li').forEach(l => l.classList.remove('active'));
                li.classList.add('active');
                loadMedia(1);
            }
        });
        
        // Media item click
        document.getElementById('media-grid').addEventListener('click', function(e) {
            const checkbox = e.target.closest('.select-checkbox');
            const item = e.target.closest('.media-item');
            
            if (!item) return;
            
            const id = parseInt(item.dataset.id);
            
            if (checkbox || e.ctrlKey || e.metaKey) {
                // Toggle selection
                e.preventDefault();
                if (selectedItems.has(id)) {
                    selectedItems.delete(id);
                    item.classList.remove('selected');
                } else {
                    selectedItems.add(id);
                    item.classList.add('selected');
                }
                updateBulkActions();
            } else {
                // Open details
                openMediaDetails(id);
            }
        });
        
        // Pagination
        document.getElementById('pagination').addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (link) {
                e.preventDefault();
                loadMedia(parseInt(link.dataset.page));
            }
        });
        
        // Upload button
        document.getElementById('upload-btn').addEventListener('click', () => {
            document.getElementById('upload-modal').classList.add('is-active');
        });
        
        // File input
        document.getElementById('select-files-btn').addEventListener('click', () => {
            document.getElementById('file-input').click();
        });
        
        document.getElementById('file-input').addEventListener('change', handleFileSelect);
        
        // Drag and drop
        const dropzone = document.getElementById('upload-dropzone');
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('drag-over');
        });
        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('drag-over');
        });
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('drag-over');
            handleFileDrop(e.dataTransfer.files);
        });
        
        // Start upload
        document.getElementById('start-upload').addEventListener('click', startUpload);
        
        // Bulk actions
        document.getElementById('bulk-delete').addEventListener('click', bulkDelete);
        
        // Media details
        document.getElementById('save-media').addEventListener('click', saveMediaDetails);
        document.getElementById('delete-media').addEventListener('click', deleteMedia);
        document.getElementById('copy-url').addEventListener('click', copyUrl);
    }

    // =========================================================================
    // Upload Handling
    // =========================================================================

    function handleFileSelect(e) {
        addFilesToQueue(e.target.files);
    }

    function handleFileDrop(files) {
        addFilesToQueue(files);
    }

    function addFilesToQueue(files) {
        for (const file of files) {
            uploadQueue.push({
                file: file,
                status: 'pending',
                progress: 0
            });
        }
        renderUploadQueue();
        document.getElementById('start-upload').disabled = uploadQueue.length === 0;
    }

    function renderUploadQueue() {
        const container = document.getElementById('upload-queue');
        const list = document.getElementById('queue-list');
        
        if (uploadQueue.length === 0) {
            container.style.display = 'none';
            return;
        }
        
        container.style.display = 'block';
        
        list.innerHTML = uploadQueue.map((item, index) => `
            <div class="queue-item" data-index="${index}">
                <span class="file-name">${escapeHtml(item.file.name)}</span>
                <span class="file-size">${formatSize(item.file.size)}</span>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${item.progress}%"></div>
                </div>
                <span class="status-icon">${getStatusIcon(item.status)}</span>
            </div>
        `).join('');
    }

    async function startUpload() {
        const folder = document.getElementById('upload-folder').value;
        const startBtn = document.getElementById('start-upload');
        startBtn.disabled = true;
        
        for (let i = 0; i < uploadQueue.length; i++) {
            const item = uploadQueue[i];
            if (item.status !== 'pending') continue;
            
            item.status = 'uploading';
            renderUploadQueue();
            
            try {
                const formData = new FormData();
                formData.append('file', item.file);
                if (folder) formData.append('folder', folder);
                
                const response = await fetch('/admin/media/upload', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    item.status = 'success';
                    item.progress = 100;
                } else {
                    item.status = 'error';
                    item.error = data.error;
                }
            } catch (error) {
                item.status = 'error';
                item.error = error.message;
            }
            
            renderUploadQueue();
        }
        
        // Refresh media list
        await loadMedia(1);
        await loadFolders();
        
        // Clear queue after delay
        setTimeout(() => {
            uploadQueue = [];
            renderUploadQueue();
            startBtn.disabled = true;
        }, 2000);
    }

    // =========================================================================
    // Media Details
    // =========================================================================

    async function openMediaDetails(id) {
        try {
            const response = await adminApi.get('/admin/media/' + id);
            editingMedia = response.data;
            
            // Populate form
            document.getElementById('media-id').value = editingMedia.id;
            document.getElementById('media-title').value = editingMedia.title || '';
            document.getElementById('media-alt').value = editingMedia.alt || '';
            document.getElementById('media-description').value = editingMedia.description || '';
            document.getElementById('media-url').value = editingMedia.url;
            
            // Preview
            const preview = document.getElementById('media-preview');
            if (editingMedia.is_image) {
                preview.innerHTML = `<img src="${editingMedia.url}" alt="${escapeHtml(editingMedia.title)}">`;
            } else if (editingMedia.media_type === 'video') {
                preview.innerHTML = `<video src="${editingMedia.url}" controls></video>`;
            } else {
                preview.innerHTML = `<div class="media-icon" style="font-size: 5rem;">${getMediaIcon(editingMedia.media_type)}</div>`;
            }
            
            // File info
            document.getElementById('file-info').innerHTML = `
                <div><span>Filename:</span><span>${escapeHtml(editingMedia.filename)}</span></div>
                <div><span>Type:</span><span>${escapeHtml(editingMedia.mime_type)}</span></div>
                <div><span>Size:</span><span>${editingMedia.formatted_size}</span></div>
                ${editingMedia.dimensions ? `<div><span>Dimensions:</span><span>${editingMedia.dimensions}</span></div>` : ''}
                <div><span>Uploaded:</span><span>${formatDate(editingMedia.created_at)}</span></div>
            `;
            
            document.getElementById('details-modal').classList.add('is-active');
        } catch (error) {
            showToast('Failed to load media details', 'danger');
        }
    }

    async function saveMediaDetails() {
        if (!editingMedia) return;
        
        const data = {
            title: document.getElementById('media-title').value,
            alt: document.getElementById('media-alt').value,
            description: document.getElementById('media-description').value
        };
        
        try {
            await adminApi.put('/admin/media/' + editingMedia.id, data);
            showToast('Media updated successfully', 'success');
            document.getElementById('details-modal').classList.remove('is-active');
            loadMedia(currentPage);
        } catch (error) {
            showToast(error.message, 'danger');
        }
    }

    async function deleteMedia() {
        if (!editingMedia) return;
        
        if (!confirm('Are you sure you want to delete this media?')) return;
        
        try {
            await adminApi.delete('/admin/media/' + editingMedia.id);
            showToast('Media deleted successfully', 'success');
            document.getElementById('details-modal').classList.remove('is-active');
            loadMedia(currentPage);
        } catch (error) {
            showToast(error.message, 'danger');
        }
    }

    function copyUrl() {
        const url = document.getElementById('media-url').value;
        navigator.clipboard.writeText(url).then(() => {
            showToast('URL copied to clipboard', 'success');
        });
    }

    // =========================================================================
    // Bulk Actions
    // =========================================================================

    function updateBulkActions() {
        const container = document.getElementById('bulk-actions');
        const count = selectedItems.size;
        
        if (count > 0) {
            container.style.display = 'flex';
            container.querySelector('.selected-count').textContent = count + ' selected';
        } else {
            container.style.display = 'none';
        }
    }

    async function bulkDelete() {
        if (selectedItems.size === 0) return;
        
        if (!confirm(`Are you sure you want to delete ${selectedItems.size} items?`)) return;
        
        try {
            await adminApi.post('/admin/media/bulk-delete', {
                ids: Array.from(selectedItems)
            });
            
            showToast('Items deleted successfully', 'success');
            selectedItems.clear();
            updateBulkActions();
            loadMedia(currentPage);
        } catch (error) {
            showToast(error.message, 'danger');
        }
    }

    // =========================================================================
    // Utilities
    // =========================================================================

    function getMediaIcon(type) {
        const icons = {
            'image': '🖼️',
            'video': '🎬',
            'audio': '🎵',
            'document': '📄',
            'archive': '📦',
            'other': '📎'
        };
        return icons[type] || '📎';
    }

    function getStatusIcon(status) {
        const icons = {
            'pending': '⏳',
            'uploading': '🔄',
            'success': '✅',
            'error': '❌'
        };
        return icons[status] || '⏳';
    }

    function formatSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unit = 0;
        while (size >= 1024 && unit < units.length - 1) {
            size /= 1024;
            unit++;
        }
        return size.toFixed(1) + ' ' + units[unit];
    }

    function formatDate(dateStr) {
        return new Date(dateStr).toLocaleString();
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Initialize
    init();
})();
</script>
@endpush
