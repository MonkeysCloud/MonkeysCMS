function confirmationModal() {
    return {
        isOpen: false,
        title: 'Confirm Action',
        message: 'Are you sure you want to proceed?',
        confirmText: 'Confirm',
        cancelText: 'Cancel',
        isDanger: false,
        iconClass: 'bg-blue-100',
        buttonClass: 'bg-blue-600 hover:bg-blue-500',
        onConfirm: null,

        open(detail) {
            this.title = detail.title || 'Confirm Action';
            this.message = detail.message || 'Are you sure you want to proceed?';
            this.confirmText = detail.confirmText || 'Confirm';
            this.cancelText = detail.cancelText || 'Cancel';
            this.isDanger = detail.isDanger || false;
            this.onConfirm = detail.onConfirm || null;

            // Pre-calculate classes
            this.iconClass = this.isDanger ? 'bg-red-100' : 'bg-blue-100';
            this.buttonClass = this.isDanger ? 'bg-red-600 hover:bg-red-500' : 'bg-blue-600 hover:bg-blue-500';

            this.isOpen = true;
        },

        close() {
            this.isOpen = false;
        },

        confirm() {
            if (this.onConfirm && typeof this.onConfirm === 'function') {
                this.onConfirm();
            }
            this.close();
        }
    };
}

// Auto-register with Alpine when DOM is ready
document.addEventListener('alpine:init', () => {
    Alpine.data('confirmationModal', confirmationModal);
});
