/**
 * MonkeysCMS Default Theme JavaScript
 */

(function() {
    'use strict';

    /**
     * Initialize theme functionality
     */
    function init() {
        initMobileMenu();
        initDropdownMenus();
        initSmoothScroll();
        initFlashMessages();
    }

    /**
     * Mobile menu toggle
     */
    function initMobileMenu() {
        const menuToggle = document.querySelector('.menu-toggle');
        const mainNav = document.querySelector('.main-navigation');

        if (menuToggle && mainNav) {
            menuToggle.addEventListener('click', function() {
                mainNav.classList.toggle('is-open');
                this.setAttribute(
                    'aria-expanded',
                    mainNav.classList.contains('is-open')
                );
            });
        }
    }

    /**
     * Dropdown menu hover/focus handling
     */
    function initDropdownMenus() {
        const menuItems = document.querySelectorAll('.menu-item.has-children');

        menuItems.forEach(function(item) {
            const link = item.querySelector('a');
            const submenu = item.querySelector('.submenu');

            if (!link || !submenu) return;

            // Keyboard navigation
            link.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    submenu.classList.toggle('is-visible');
                }
            });

            // Close on escape
            item.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    submenu.classList.remove('is-visible');
                    link.focus();
                }
            });

            // Close when clicking outside
            document.addEventListener('click', function(e) {
                if (!item.contains(e.target)) {
                    submenu.classList.remove('is-visible');
                }
            });
        });
    }

    /**
     * Smooth scroll for anchor links
     */
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#') return;

                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    /**
     * Auto-dismiss flash messages
     */
    function initFlashMessages() {
        const alerts = document.querySelectorAll('.flash-messages .alert');

        alerts.forEach(function(alert) {
            // Add close button
            const closeBtn = document.createElement('button');
            closeBtn.className = 'alert-close';
            closeBtn.innerHTML = '&times;';
            closeBtn.setAttribute('aria-label', 'Close');
            closeBtn.addEventListener('click', function() {
                dismissAlert(alert);
            });
            alert.appendChild(closeBtn);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                dismissAlert(alert);
            }, 5000);
        });
    }

    /**
     * Dismiss an alert with animation
     */
    function dismissAlert(alert) {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(function() {
            alert.remove();
        }, 300);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose theme utilities globally
    window.MonkeysCMSTheme = {
        dismissAlert: dismissAlert
    };

})();
