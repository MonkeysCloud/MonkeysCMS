/**
 * Field Repeater for Multiple Values
 * Handles cloning templates for multiple field inputs.
 */
window.fieldRepeater = (config) => ({
  items: [],
  nextIndex: 0,
  init() {
    // Count existing items to offset the index
    const container = document.getElementById(config.containerId);
    if (container) {
      this.nextIndex = container.children.length;
    }
  },
  add() {
    const template = document.getElementById(config.templateId);
    const container = document.getElementById(config.containerId);

    if (!template || !container) return;

    // Clone content
    const clone = template.content.cloneNode(true);
    const root = clone.firstElementChild;

    // Generate unique ID based on timestamp and index
    const uniqueId = Date.now() + "_" + this.nextIndex;

    // Replace placeholders in attributes
    // We assume the PHP renderer put 'VAR_INDEX' in the template
    this.replacePlaceholders(root, uniqueId);

    // Append to container
    container.appendChild(clone);
    this.nextIndex++;

    // Trigger event for widgets that might need to initialize
    document.dispatchEvent(
      new CustomEvent("cms:content-changed", {
        detail: { target: root },
      })
    );
  },
  remove(el) {
    // Remove the specific item row
    el.closest(".field-repeater-item").remove();
  },
  replacePlaceholders(el, index) {
    // Replace in ID, Name, For attributes
    ["id", "name", "for", "data-target"].forEach((attr) => {
      if (el.hasAttribute(attr)) {
        el.setAttribute(
          attr,
          el.getAttribute(attr).replace(/__INDEX__/g, index)
        );
      }
    });

    // Recursively replace in children
    Array.from(el.children).forEach((child) =>
      this.replacePlaceholders(child, index)
    );
  },
});
