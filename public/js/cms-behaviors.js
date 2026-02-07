/**
 * MonkeysCMS Global Widget Behaviors System
 * 
 * This is a Drupal-like behaviors system that allows widgets to register
 * themselves for initialization. When new content is added to the DOM
 * (e.g., via repeater "Add Another"), all registered behaviors are
 * automatically triggered on the new content.
 * 
 * Usage for custom widgets:
 * 
 * CmsBehaviors.register('myWidget', {
 *     selector: '.my-widget, [data-my-widget]',
 *     attach: function(context, settings) {
 *         context.querySelectorAll(this.selector).forEach(function(el) {
 *             if (el.dataset.initialized) return; // Prevent double init
 *             el.dataset.initialized = 'true';
 *             // Your initialization code here
 *         });
 *     },
 *     detach: function(context, settings) {
 *         // Optional cleanup when element is removed
 *     }
 * });
 */
(function(window) {
    'use strict';

    const CmsBehaviors = {
        behaviors: {},
        settings: {},

        /**
         * Register a behavior
         * @param {string} name - Unique name for the behavior
         * @param {object} behavior - Behavior object with attach/detach methods
         * @param {string} behavior.selector - CSS selector for matching elements
         * @param {function} behavior.attach - Called when content is added (context, settings)
         * @param {function} [behavior.detach] - Called when content is removed (optional)
         */
        register: function(name, behavior) {
            if (!behavior.attach || typeof behavior.attach !== 'function') {
                console.warn('CmsBehaviors: Behavior "' + name + '" must have an attach function');
                return;
            }
            this.behaviors[name] = behavior;
        },

        /**
         * Attach all behaviors to a context
         * @param {Element|Document} context - DOM element to search within
         * @param {object} [settings] - Optional settings to pass to behaviors
         */
        attach: function(context, settings) {
            context = context || document;
            settings = settings || this.settings;

            Object.keys(this.behaviors).forEach(function(name) {
                var behavior = CmsBehaviors.behaviors[name];
                try {
                    behavior.attach(context, settings);
                } catch (e) {
                    console.error('CmsBehaviors: Error in behavior "' + name + '"', e);
                }
            });
        },

        /**
         * Detach all behaviors from a context
         * @param {Element} context - DOM element being removed
         * @param {object} [settings] - Optional settings to pass to behaviors
         */
        detach: function(context, settings) {
            settings = settings || this.settings;

            Object.keys(this.behaviors).forEach(function(name) {
                var behavior = CmsBehaviors.behaviors[name];
                if (behavior.detach && typeof behavior.detach === 'function') {
                    try {
                        behavior.detach(context, settings);
                    } catch (e) {
                        console.error('CmsBehaviors: Error detaching behavior "' + name + '"', e);
                    }
                }
            });
        },

        /**
         * Initialize the system - attach to document and listen for changes
         */
        init: function() {
            // Attach behaviors to entire document on page load
            this.attach(document);

            // Listen for dynamically added content (from repeaters, HTMX, etc.)
            document.addEventListener('cms:content-changed', function(e) {
                if (e.detail && e.detail.target) {
                    CmsBehaviors.attach(e.detail.target);
                }
            });

            // Listen for content being removed (optional cleanup)
            document.addEventListener('cms:content-removed', function(e) {
                if (e.detail && e.detail.target) {
                    CmsBehaviors.detach(e.detail.target);
                }
            });
        }
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            CmsBehaviors.init();
        });
    } else {
        CmsBehaviors.init();
    }

    // Expose globally
    window.CmsBehaviors = CmsBehaviors;

})(window);
