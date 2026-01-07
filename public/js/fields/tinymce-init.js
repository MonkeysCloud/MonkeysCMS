/**
 * TinyMCE Initialization Script
 *
 * Reusable WYSIWYG editor initialization for any textarea.
 * Usage: Add data-tinymce attribute to any textarea element.
 *
 * Options (data attributes):
 * - data-tinymce: Enable TinyMCE on this element
 * - data-tinymce-height: Editor height (default: 400)
 * - data-tinymce-toolbar: Toolbar preset ('minimal', 'simple', 'full', or custom)
 * - data-tinymce-menubar: Show menubar (true/false, default: false)
 * - data-tinymce-format-select: ID of a select element that controls format
 */
(function () {
  "use strict";

  // Default toolbar presets
  var toolbarPresets = {
    minimal: "undo redo | bold italic | bullist numlist",
    simple:
      "undo redo | formatselect | bold italic underline | bullist numlist | link",
    full: "undo redo | formatselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright | bullist numlist outdent indent | link image media table | code removeformat",
    default:
      "undo redo | formatselect | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image | code",
  };

  // Track initialized editors
  var editors = {};

  /**
   * Initialize TinyMCE on a textarea
   */
  function initEditor(textarea) {
    if (!textarea || editors[textarea.id]) return;

    var height = textarea.dataset.tinymceHeight || 400;
    var toolbarKey = textarea.dataset.tinymceToolbar || "default";
    var toolbar = toolbarPresets[toolbarKey] || toolbarKey;
    var menubar = textarea.dataset.tinymceMenubar === "true";
    var plugins = [
      "link",
      "image",
      "lists",
      "table",
      "code",
      "media",
      "autolink",
    ];

    tinymce.init({
      selector: "#" + textarea.id,
      height: parseInt(height, 10),
      menubar: menubar,
      plugins: plugins,
      toolbar: toolbar,
      content_style:
        'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 16px; line-height: 1.6; }',
      branding: false,
      promotion: false,
      setup: function (editor) {
        editors[textarea.id] = editor;
        editor.on("change", function () {
          editor.save();
        });
      },
    });
  }

  /**
   * Remove TinyMCE from a textarea
   */
  function removeEditor(textarea) {
    if (!textarea || !editors[textarea.id]) return;

    tinymce.remove("#" + textarea.id);
    delete editors[textarea.id];
  }

  /**
   * Toggle editor based on format selector
   */
  function setupFormatToggle(textarea) {
    var formatSelectId = textarea.dataset.tinymceFormatSelect;
    if (!formatSelectId) return;

    var formatSelect = document.getElementById(formatSelectId);
    if (!formatSelect) return;

    function toggle() {
      if (formatSelect.value === "html") {
        initEditor(textarea);
      } else {
        removeEditor(textarea);
      }
    }

    // Initial check
    toggle();

    // Listen for changes
    formatSelect.addEventListener("change", toggle);
  }

  /**
   * Initialize all TinyMCE editors on the page
   */
  function initAll() {
    if (typeof tinymce === "undefined") {
      console.warn("TinyMCE not loaded");
      return;
    }

    var textareas = document.querySelectorAll("[data-tinymce]");
    textareas.forEach(function (textarea) {
      var formatSelectId = textarea.dataset.tinymceFormatSelect;
      if (formatSelectId) {
        // Has format toggle - use conditional initialization
        setupFormatToggle(textarea);
      } else {
        // No format toggle - initialize immediately
        initEditor(textarea);
      }
    });
  }

  // Initialize on DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAll);
  } else {
    initAll();
  }

  // Expose API for dynamic use
  window.MonkeysTinyMCE = {
    init: initEditor,
    remove: removeEditor,
    initAll: initAll,
    editors: editors,
  };
})();
