function mediaBulkActions() {
    return {
        selected: [],
        items: [],
        
        init() {
            // Find all media items in the DOM and populate items array
            this.items = Array.from(document.querySelectorAll('[data-media-id]'))
                .map(el => parseInt(el.dataset.mediaId));
            
            // Watch for changes in selected array to update visual state
            this.$watch('selected', (value) => {
                this.updateVisualState();
            });
        },

        updateVisualState() {
            // Update all media items visual state
            document.querySelectorAll('[data-media-id]').forEach(el => {
                const id = parseInt(el.dataset.mediaId);
                const checkbox = el.querySelector('.media-checkbox');
                
                if (this.selected.includes(id)) {
                    el.classList.add('ring-2', 'ring-blue-500', 'border-blue-500');
                    if (checkbox) checkbox.checked = true;
                } else {
                    el.classList.remove('ring-2', 'ring-blue-500', 'border-blue-500');
                    if (checkbox) checkbox.checked = false;
                }
            });
        },

        toggle(id) {
            if (this.selected.includes(id)) {
                this.selected = this.selected.filter(i => i !== id);
            } else {
                this.selected = [...this.selected, id];
            }
            // Immediately update visual state
            this.updateVisualState();
        },

        toggleAll() {
            if (this.selected.length === this.items.length) {
                this.selected = [];
            } else {
                this.selected = [...this.items];
            }
        },
        
        get allSelected() {
            return this.items.length > 0 && this.selected.length === this.items.length;
        },

        confirmBulkDelete() {
            const count = this.selected.length;
            if (count === 0) return;

            window.dispatchEvent(new CustomEvent('open-confirmation-modal', {
                detail: {
                    title: 'Delete ' + count + ' Items?',
                    message: 'Are you sure you want to delete ' + count + ' media items? This action cannot be undone.',
                    isDanger: true,
                    confirmText: 'Delete ' + count + ' Items',
                    onConfirm: () => this.executeBulkDelete()
                }
            }));
        },

        async executeBulkDelete() {
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                                  document.querySelector('input[name="csrf_token"]')?.value;
                
                const response = await fetch('/admin/media/bulk-delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ ids: this.selected })
                });

                if (response.ok) {
                    // Reload to reflect changes
                    window.location.reload();
                } else {
                    const data = await response.json();
                    alert('Failed to delete items: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Bulk delete error:', error);
                alert('An error occurred while deleting items.');
            }
        }
    }
}
