/**
 * MonkeysCMS Form Engine — Client-side form tools.
 *
 * Uses MonkeysJS for:
 *   - CSRF token injection (reads <meta name="csrf-token">)
 *   - Conditional field visibility (data-show-when / data-show-value)
 *   - Form validation before submit
 *   - Repeater fields (add/remove rows)
 *   - Driver panel toggle (storage settings pattern)
 *
 * Requires: MonkeysJS (loaded globally via core/monkeysjs library)
 */
(function () {
  'use strict';

  // ── CSRF Token Helpers ────────────────────────────────────────────────

  /**
   * Get the CSRF token from <meta> tag (auto-injected by FormSecurityMiddleware).
   */
  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  /**
   * Auto-inject CSRF token into MonkeysJS HTTP client defaults.
   */
  function setupMonkeysJsCsrf() {
    if (typeof MonkeysJS === 'undefined') return;

    var token = getCsrfToken();
    if (!token) return;

    // If MonkeysJS has createClient, configure default headers
    if (MonkeysJS.createClient) {
      window._mjs_client = MonkeysJS.createClient({
        headers: {
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json',
        },
      });
    }
  }

  // ── Conditional Fields ────────────────────────────────────────────────

  /**
   * Handle data-show-when / data-show-value for conditional visibility.
   *
   * Usage in HTML:
   *   <div class="form-group" data-show-when="driver" data-show-value="s3">
   *     ...S3 settings...
   *   </div>
   *
   * The div is hidden unless the field named "driver" has value "s3".
   */
  function initConditionalFields() {
    var conditionals = document.querySelectorAll('[data-show-when]');
    if (!conditionals.length) return;

    conditionals.forEach(function (el) {
      var fieldName = el.dataset.showWhen;
      var targetValue = el.dataset.showValue;

      // Find the controlling field
      var field = document.querySelector('[name="' + fieldName + '"]');
      if (!field) return;

      function update() {
        var currentVal = field.type === 'checkbox' ? (field.checked ? '1' : '0') : field.value;
        var match = (currentVal === targetValue);
        el.hidden = !match;
        el.style.display = match ? '' : 'none';
      }

      field.addEventListener('change', update);
      field.addEventListener('input', update);
      update(); // initial state
    });
  }

  // ── Driver Panel Toggle ───────────────────────────────────────────────

  /**
   * Toggle storage driver settings panels.
   * Generic version of toggleDriverSettings for any prefix pattern.
   */
  function initDriverToggle() {
    var driverSelect = document.querySelector('[data-driver-toggle]');
    if (!driverSelect) return;

    var prefix = driverSelect.dataset.driverToggle || 'driver';

    function toggle() {
      var val = driverSelect.value;
      document.querySelectorAll('[data-driver-panel]').forEach(function (panel) {
        panel.hidden = (panel.dataset.driverPanel !== val);
      });
    }

    driverSelect.addEventListener('change', toggle);
    toggle();
  }

  // ── Repeater Fields ───────────────────────────────────────────────────

  /**
   * Repeater field — add/remove dynamic rows.
   *
   * Usage:
   *   <div data-repeater="items">
   *     <div data-repeater-list>
   *       <div data-repeater-item>
   *         <input name="items[0][key]" ...>
   *         <button data-repeater-remove>×</button>
   *       </div>
   *     </div>
   *     <button data-repeater-add>+ Add Item</button>
   *   </div>
   */
  function initRepeaters() {
    document.querySelectorAll('[data-repeater]').forEach(function (repeater) {
      var listEl = repeater.querySelector('[data-repeater-list]');
      var addBtn = repeater.querySelector('[data-repeater-add]');
      var fieldName = repeater.dataset.repeater;

      if (!listEl || !addBtn) return;

      // Get the template from the first item
      var items = listEl.querySelectorAll('[data-repeater-item]');
      if (!items.length) return;

      var template = items[0].cloneNode(true);
      var counter = items.length;

      addBtn.addEventListener('click', function (e) {
        e.preventDefault();
        var newItem = template.cloneNode(true);

        // Update field names with new index
        newItem.querySelectorAll('[name]').forEach(function (input) {
          input.name = input.name.replace(/\[\d+\]/, '[' + counter + ']');
          input.value = '';
        });

        listEl.appendChild(newItem);
        counter++;

        // Bind remove button
        bindRemoveButtons(listEl);
      });

      bindRemoveButtons(listEl);
    });
  }

  function bindRemoveButtons(listEl) {
    listEl.querySelectorAll('[data-repeater-remove]').forEach(function (btn) {
      btn.onclick = function (e) {
        e.preventDefault();
        var item = btn.closest('[data-repeater-item]');
        if (item && listEl.querySelectorAll('[data-repeater-item]').length > 1) {
          item.remove();
        }
      };
    });
  }

  // ── Client-side Validation ────────────────────────────────────────────

  /**
   * Simple client-side validation for required fields.
   * Uses MonkeysJS useDebouncedValidation when available.
   */
  function initValidation() {
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        var valid = true;

        // Clear previous errors
        form.querySelectorAll('.form-error').forEach(function (err) { err.remove(); });
        form.querySelectorAll('.form-input--error').forEach(function (inp) {
          inp.classList.remove('form-input--error');
        });

        // Check required fields
        form.querySelectorAll('[required]').forEach(function (field) {
          if (!field.value || field.value.trim() === '') {
            valid = false;
            field.classList.add('form-input--error');

            var label = form.querySelector('label[for="' + field.id + '"]');
            var errorMsg = document.createElement('span');
            errorMsg.className = 'form-error';
            errorMsg.textContent = (label ? label.textContent.replace('*', '').trim() : field.name) + ' is required.';
            field.parentNode.appendChild(errorMsg);
          }
        });

        if (!valid) {
          e.preventDefault();
          // Scroll to first error
          var firstError = form.querySelector('.form-input--error');
          if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
          }
        }
      });
    });
  }

  // ── Range Slider Value Display ────────────────────────────────────────

  function initRangeSliders() {
    document.querySelectorAll('input[type="range"]').forEach(function (range) {
      var display = range.parentNode.querySelector('.form-hint, .form-range-value');
      if (!display) return;

      range.addEventListener('input', function () {
        display.textContent = range.value + (display.dataset.suffix || '%');
      });
    });
  }

  // ── Init ──────────────────────────────────────────────────────────────

  function init() {
    setupMonkeysJsCsrf();
    initConditionalFields();
    initDriverToggle();
    initRepeaters();
    initValidation();
    initRangeSliders();
  }

  // Run on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Expose for programmatic usage
  window.CmsFormEngine = {
    getCsrfToken: getCsrfToken,
    initConditionalFields: initConditionalFields,
    initRepeaters: initRepeaters,
    initValidation: initValidation,
    setupMonkeysJsCsrf: setupMonkeysJsCsrf,
  };
})();
