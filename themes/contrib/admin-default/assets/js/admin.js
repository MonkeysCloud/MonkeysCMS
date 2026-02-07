/**
 * MonkeysCMS Admin Theme JavaScript
 */

(function() {
    'use strict';

    /**
     * Initialize admin functionality
     */
    function init() {
        initSidebar();
        initAlerts();
        initModals();
        initTables();
        initForms();
    }

    /**
     * Sidebar toggle functionality
     */
    function initSidebar() {
        const sidebar = document.getElementById('admin-sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const mobileToggle = document.getElementById('mobile-menu-toggle');

        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('is-collapsed');
                localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('is-collapsed'));
            });

            // Restore state from localStorage
            if (localStorage.getItem('sidebar-collapsed') === 'true') {
                sidebar.classList.add('is-collapsed');
            }
        }

        if (mobileToggle && sidebar) {
            mobileToggle.addEventListener('click', function() {
                sidebar.classList.toggle('is-open');
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 1024 && 
                    !sidebar.contains(e.target) && 
                    !mobileToggle.contains(e.target)) {
                    sidebar.classList.remove('is-open');
                }
            });
        }
    }

    /**
     * Alert auto-dismiss and close buttons
     */
    function initAlerts() {
        const alerts = document.querySelectorAll('.alert');

        alerts.forEach(function(alert) {
            const closeBtn = alert.querySelector('.alert-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    dismissAlert(alert);
                });
            }

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                dismissAlert(alert);
            }, 5000);
        });
    }

    /**
     * Dismiss alert with animation
     */
    function dismissAlert(alert) {
        alert.style.opacity = '0';
        alert.style.transform = 'translateX(20px)';
        setTimeout(function() {
            alert.remove();
        }, 200);
    }

    /**
     * Modal functionality
     */
    function initModals() {
        // Open modal triggers
        document.querySelectorAll('[data-modal-open]').forEach(function(trigger) {
            trigger.addEventListener('click', function() {
                const modalId = this.getAttribute('data-modal-open');
                const modal = document.getElementById(modalId);
                if (modal) {
                    openModal(modal);
                }
            });
        });

        // Close modal triggers
        document.querySelectorAll('[data-modal-close]').forEach(function(trigger) {
            trigger.addEventListener('click', function() {
                const modal = this.closest('.modal-overlay');
                if (modal) {
                    closeModal(modal);
                }
            });
        });

        // Close on overlay click
        document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    closeModal(overlay);
                }
            });
        });

        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal-overlay.is-active');
                if (activeModal) {
                    closeModal(activeModal);
                }
            }
        });
    }

    function openModal(modal) {
        modal.classList.add('is-active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modal) {
        modal.classList.remove('is-active');
        document.body.style.overflow = '';
    }

    /**
     * Table functionality
     */
    function initTables() {
        // Select all checkbox
        document.querySelectorAll('.select-all').forEach(function(selectAll) {
            selectAll.addEventListener('change', function() {
                const table = this.closest('table');
                const checkboxes = table.querySelectorAll('tbody input[type="checkbox"]');
                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = selectAll.checked;
                });
                updateBulkActions();
            });
        });

        // Row checkboxes
        document.querySelectorAll('.admin-table tbody input[type="checkbox"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', updateBulkActions);
        });

        // Sortable columns
        document.querySelectorAll('[data-sort]').forEach(function(header) {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                const column = this.getAttribute('data-sort');
                const currentUrl = new URL(window.location.href);
                const currentSort = currentUrl.searchParams.get('sort');
                const currentDirection = currentUrl.searchParams.get('direction') || 'asc';

                if (currentSort === column) {
                    currentUrl.searchParams.set('direction', currentDirection === 'asc' ? 'desc' : 'asc');
                } else {
                    currentUrl.searchParams.set('sort', column);
                    currentUrl.searchParams.set('direction', 'asc');
                }

                window.location.href = currentUrl.toString();
            });
        });
    }

    function updateBulkActions() {
        const selected = document.querySelectorAll('.admin-table tbody input[type="checkbox"]:checked');
        const bulkActions = document.querySelector('.bulk-actions');
        
        if (bulkActions) {
            if (selected.length > 0) {
                bulkActions.classList.add('is-visible');
                const countEl = bulkActions.querySelector('.selected-count');
                if (countEl) {
                    countEl.textContent = selected.length;
                }
            } else {
                bulkActions.classList.remove('is-visible');
            }
        }
    }

    /**
     * Form enhancements
     */
    function initForms() {
        // Form validation styling
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const invalidFields = form.querySelectorAll(':invalid');
                if (invalidFields.length > 0) {
                    e.preventDefault();
                    invalidFields.forEach(function(field) {
                        field.classList.add('is-invalid');
                    });
                    invalidFields[0].focus();
                }
            });
        });

        // Remove invalid class on input
        document.querySelectorAll('.form-control').forEach(function(input) {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });

        // Character counter for textareas
        document.querySelectorAll('textarea[maxlength]').forEach(function(textarea) {
            const maxLength = textarea.getAttribute('maxlength');
            const counter = document.createElement('div');
            counter.className = 'form-text';
            counter.textContent = '0 / ' + maxLength;
            textarea.parentNode.appendChild(counter);

            textarea.addEventListener('input', function() {
                counter.textContent = this.value.length + ' / ' + maxLength;
            });
        });

        // Slug generator
        document.querySelectorAll('[data-slug-source]').forEach(function(slugField) {
            const sourceField = document.querySelector(slugField.getAttribute('data-slug-source'));
            if (sourceField) {
                sourceField.addEventListener('input', function() {
                    if (!slugField.dataset.edited) {
                        slugField.value = generateSlug(this.value);
                    }
                });

                slugField.addEventListener('input', function() {
                    this.dataset.edited = 'true';
                });
            }
        });
    }

    /**
     * Generate URL-friendly slug
     */
    function generateSlug(text) {
        return text
            .toLowerCase()
            .trim()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_-]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    /**
     * Confirm action helper
     */
    window.confirmAction = function(message, callback) {
        if (confirm(message || 'Are you sure?')) {
            callback();
        }
    };

    /**
     * Toast notification
     */
    window.showToast = function(message, type) {
        type = type || 'info';
        const toast = document.createElement('div');
        toast.className = 'alert alert-' + type;
        toast.innerHTML = '<span class="alert-message">' + message + '</span><button class="alert-close">&times;</button>';
        
        const container = document.querySelector('.admin-alerts') || document.querySelector('.admin-content');
        container.insertBefore(toast, container.firstChild);

        toast.querySelector('.alert-close').addEventListener('click', function() {
            dismissAlert(toast);
        });

        setTimeout(function() {
            dismissAlert(toast);
        }, 5000);
    };

    /**
     * API helper - uses XHR for consistency
     */
    window.adminApi = {
        request: function(method, url, data) {
            return new Promise(function(resolve, reject) {
                const xhr = new XMLHttpRequest();
                xhr.open(method, url, true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('HX-Request', 'true');
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                                  document.querySelector('input[name="csrf_token"]')?.value || '';
                if (csrfToken && method !== 'GET') {
                    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                }

                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        try {
                            const result = JSON.parse(xhr.responseText);
                            if (xhr.status >= 200 && xhr.status < 300) {
                                resolve(result);
                            } else {
                                showToast(result.error || 'Request failed', 'danger');
                                reject(new Error(result.error || 'Request failed'));
                            }
                        } catch (error) {
                            showToast('Request failed', 'danger');
                            reject(error);
                        }
                    }
                };

                if (data && method !== 'GET') {
                    xhr.send(JSON.stringify(data));
                } else {
                    xhr.send();
                }
            });
        },

        get: function(url) {
            return this.request('GET', url);
        },

        post: function(url, data) {
            return this.request('POST', url, data);
        },

        put: function(url, data) {
            return this.request('PUT', url, data);
        },

        delete: function(url) {
            return this.request('DELETE', url);
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
