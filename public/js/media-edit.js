/**
 * Media Edit - Alpine.js Component
 * Uses XHR for save operations
 */
function mediaEdit(initialData) {
    return {
        isEditing: false,
        isSaving: false,
        saveError: null,
        originalData: { ...initialData },
        form: { ...initialData },

        init() {
            this.originalData = { ...initialData };
            this.form = { ...initialData };
        },

        toggleEdit() {
            if (this.isEditing) {
                // Cancel: revert
                this.form = { ...this.originalData };
                this.isEditing = false;
                this.saveError = null;
            } else {
                // Start editing
                this.isEditing = true;
            }
        },

        save() {
            this.isSaving = true;
            this.saveError = null;
            
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || 
                              document.querySelector('meta[name="csrf-token"]')?.content || '';
            
            // Use XHR for the PUT request
            const xhr = new XMLHttpRequest();
            xhr.open('PUT', `/admin/media/${this.originalData.id}`, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.setRequestHeader('HX-Request', 'true');
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            
            const self = this;
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        self.onSaveSuccess();
                    } else {
                        self.onSaveError(xhr.responseText || 'Failed to update media');
                    }
                }
            };
            xhr.send(JSON.stringify(this.form));
        },

        onSaveSuccess() {
            this.originalData = { ...this.form };
            this.isEditing = false;
            this.isSaving = false;
            this.saveError = null;
        },

        onSaveError(message) {
            console.error('Error saving media:', message);
            this.saveError = message;
            this.isSaving = false;
        }
    }
}
