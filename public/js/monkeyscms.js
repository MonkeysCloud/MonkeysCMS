/**
 * MonkeysCMS Global JavaScript Library
 * 
 * Provides HTMX configuration and Sortable integration for the entire CMS.
 * Include this file after htmx.min.js and sortable.min.js
 */

(function() {
    'use strict';

    // ═══════════════════════════════════════════════════════════════════
    // HTMX Global Configuration
    // ═══════════════════════════════════════════════════════════════════
    
    if (typeof htmx !== 'undefined') {
        // Configure HTMX defaults
        document.body.addEventListener('htmx:configRequest', function(event) {
            // Add CSRF token if available
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) {
                event.detail.headers['X-CSRF-TOKEN'] = csrfToken;
            }
            // Add JSON content type for POST requests
            if (event.detail.verb !== 'get') {
                event.detail.headers['Content-Type'] = 'application/json';
            }
        });

        // Global loading indicator
        document.body.addEventListener('htmx:beforeRequest', function(event) {
            document.body.classList.add('htmx-request-active');
        });
        
        document.body.addEventListener('htmx:afterRequest', function(event) {
            document.body.classList.remove('htmx-request-active');
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // MonkeysCMS Sortable Integration
    // ═══════════════════════════════════════════════════════════════════
    
    window.MonkeysSortable = {
        instances: new Map(),
        
        init: function(element, options) {
            options = options || {};
            const url = element.dataset.sortableUrl;
            const statusSelector = element.dataset.sortableStatus || '.sortable-status';
            const statusEl = document.querySelector(statusSelector);
            
            if (!url) {
                console.warn('MonkeysSortable: No data-sortable-url found on element');
                return null;
            }
            
            // Prevent double initialization
            if (this.instances.has(element)) {
                return this.instances.get(element);
            }
            
            const sortable = new Sortable(element, {
                animation: 150,
                ghostClass: options.ghostClass || 'bg-blue-50',
                handle: options.handle || null,
                onEnd: function() {
                    const ids = Array.from(element.children)
                        .map(el => el.dataset.id)
                        .filter(id => id);
                    
                    if (statusEl) {
                        statusEl.dataset.status = 'saving';
                        statusEl.textContent = 'Saving...';
                        statusEl.classList.remove('text-green-600', 'text-red-600');
                        statusEl.classList.add('text-gray-500');
                    }
                    
                    // Use XHR instead of fetch for HTMX compatibility
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', url, true);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                    xhr.setRequestHeader('HX-Request', 'true');
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                                       document.querySelector('input[name="csrf_token"]')?.value || '';
                    if (csrfToken) {
                        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                    }
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4) {
                            if (xhr.status === 200) {
                                if (statusEl) {
                                    statusEl.dataset.status = 'saved';
                                    statusEl.textContent = 'Saved!';
                                    statusEl.classList.remove('text-gray-500', 'text-red-600');
                                    statusEl.classList.add('text-green-600');
                                    setTimeout(() => {
                                        statusEl.dataset.status = 'idle';
                                        statusEl.textContent = '';
                                    }, 2000);
                                }
                                
                                // Dispatch custom event
                                element.dispatchEvent(new CustomEvent('sortable:saved', { 
                                    detail: { ids: ids } 
                                }));
                            } else {
                                console.error('MonkeysSortable: Save failed:', xhr.status);
                                if (statusEl) {
                                    statusEl.dataset.status = 'error';
                                    statusEl.textContent = 'Error!';
                                    statusEl.classList.remove('text-gray-500', 'text-green-600');
                                    statusEl.classList.add('text-red-600');
                                }
                                
                                // Dispatch error event
                                element.dispatchEvent(new CustomEvent('sortable:error', { 
                                    detail: { error: new Error('Save failed') } 
                                }));
                            }
                        }
                    };
                    xhr.send(JSON.stringify({ terms: ids }));
                }
            });
            
            this.instances.set(element, sortable);
            return sortable;
        },
        
        initAll: function() {
            document.querySelectorAll('[data-sortable-url]').forEach(el => {
                this.init(el);
            });
        },
        
        destroy: function(element) {
            const instance = this.instances.get(element);
            if (instance) {
                instance.destroy();
                this.instances.delete(element);
            }
        }
    };
    
    // Auto-initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Sortable !== 'undefined') {
            MonkeysSortable.initAll();
        }
    });
    
    // Re-initialize after HTMX swaps
    if (typeof htmx !== 'undefined') {
        document.body.addEventListener('htmx:afterSwap', function(event) {
            if (typeof Sortable !== 'undefined') {
                event.detail.target.querySelectorAll('[data-sortable-url]').forEach(el => {
                    MonkeysSortable.init(el);
                });
            }
        });
    }

})();
