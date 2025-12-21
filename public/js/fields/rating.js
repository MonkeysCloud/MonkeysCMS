/**
 * MonkeysCMS Rating Field Widget JavaScript
 * Interactive star rating functionality
 */

(function() {
    'use strict';

    /**
     * Set rating value
     */
    window.setRating = function(fieldId, value) {
        const input = document.getElementById(fieldId);
        const wrapper = document.getElementById(fieldId + '_wrapper');
        const display = document.getElementById(fieldId + '_display');
        
        if (!input || !wrapper) return;
        
        const stars = wrapper.querySelectorAll('.field-rating__star');
        const maxStars = parseInt(wrapper.querySelector('.field-rating__stars').dataset.max) || 5;
        const step = parseFloat(wrapper.querySelector('.field-rating__stars').dataset.step) || 1;
        
        // Clamp value
        value = Math.max(0, Math.min(maxStars, value));
        
        // Round to step
        value = Math.round(value / step) * step;
        
        // Update input
        input.value = value;
        
        // Update stars display
        updateStars(stars, value);
        
        // Update numeric display
        if (display) {
            display.textContent = step < 1 ? value.toFixed(1) : value.toString();
        }
        
        // Trigger change event
        input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    /**
     * Clear rating
     */
    window.clearRating = function(fieldId) {
        setRating(fieldId, 0);
    };

    /**
     * Update star visual states
     */
    function updateStars(stars, value) {
        stars.forEach((star, index) => {
            const starValue = index + 1;
            const svg = star.querySelector('svg path');
            
            if (!svg) return;
            
            if (value >= starValue) {
                // Fully filled
                svg.setAttribute('fill', '#fbbf24');
                star.classList.remove('field-rating__star--empty', 'field-rating__star--half');
                star.classList.add('field-rating__star--filled');
            } else if (value >= starValue - 0.5) {
                // Half filled
                svg.setAttribute('fill', 'url(#half-gradient)');
                star.classList.remove('field-rating__star--empty', 'field-rating__star--filled');
                star.classList.add('field-rating__star--half');
            } else {
                // Empty
                svg.setAttribute('fill', '#d1d5db');
                star.classList.remove('field-rating__star--filled', 'field-rating__star--half');
                star.classList.add('field-rating__star--empty');
            }
        });
    }

    /**
     * Initialize rating field with hover effects
     */
    window.initRating = function(fieldId, options = {}) {
        const wrapper = document.getElementById(fieldId + '_wrapper');
        const input = document.getElementById(fieldId);
        
        if (!wrapper || !input) return;
        
        const stars = wrapper.querySelectorAll('.field-rating__star');
        const starsContainer = wrapper.querySelector('.field-rating__stars');
        const step = parseFloat(starsContainer?.dataset.step) || 1;
        const allowHalf = step < 1;
        
        // Hover preview
        stars.forEach((star, index) => {
            star.addEventListener('mouseenter', function() {
                const previewValue = allowHalf ? getHoverValue(this, index + 1) : (index + 1);
                previewStars(stars, previewValue);
            });
            
            star.addEventListener('mousemove', function(e) {
                if (allowHalf) {
                    const previewValue = getHoverValue(this, index + 1);
                    previewStars(stars, previewValue);
                }
            });
            
            star.addEventListener('click', function(e) {
                const clickValue = allowHalf ? getHoverValue(this, index + 1) : (index + 1);
                setRating(fieldId, clickValue);
            });
        });
        
        // Reset on mouse leave
        starsContainer?.addEventListener('mouseleave', function() {
            const currentValue = parseFloat(input.value) || 0;
            updateStars(stars, currentValue);
        });
    };

    /**
     * Get hover value (for half-star support)
     */
    function getHoverValue(star, baseValue) {
        const rect = star.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const halfPoint = rect.width / 2;
        
        return x < halfPoint ? baseValue - 0.5 : baseValue;
    }

    /**
     * Preview star display (during hover)
     */
    function previewStars(stars, value) {
        stars.forEach((star, index) => {
            const starValue = index + 1;
            
            if (value >= starValue) {
                star.classList.add('field-rating__star--highlighted');
                star.classList.remove('field-rating__star--half-highlighted');
            } else if (value >= starValue - 0.5) {
                star.classList.add('field-rating__star--half-highlighted');
                star.classList.remove('field-rating__star--highlighted');
            } else {
                star.classList.remove('field-rating__star--highlighted', 'field-rating__star--half-highlighted');
            }
        });
    }

    /**
     * Auto-initialize on DOM ready
     */
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.field-rating').forEach(wrapper => {
            const input = wrapper.querySelector('input[type="hidden"]');
            if (input) {
                initRating(input.id);
            }
        });
    });

})();
