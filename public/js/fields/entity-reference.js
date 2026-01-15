/**
 * Entity Reference Field Widget
 * MonkeysCMS Field Widget System
 */

(function () {
  "use strict";

  // Store field configurations
  const entityReferences = {};

  /**
   * Initialize Entity Reference Field
   */
  window.initEntityReference = function (fieldId, selectedIds, options) {
    const wrapper = document.getElementById(fieldId + "_wrapper");
    if (!wrapper) return;

    // Try to read initial values from DOM if not provided
    if (!selectedIds) {
      const hiddenInput = document.getElementById(fieldId);
      if (hiddenInput && hiddenInput.value) {
        try {
          const parsed = JSON.parse(hiddenInput.value);
          selectedIds = Array.isArray(parsed) ? parsed : [parsed];
        } catch (e) {
          selectedIds = [hiddenInput.value];
        }
      } else {
        selectedIds = [];
      }
    }

    entityReferences[fieldId] = {
      selectedIds: selectedIds || [],
      options: Object.assign(
        {
          targetType: "content",
          targetBundle: "",
          multiple: false,
          allowCreate: false,
        },
        options
      ),
    };

    const searchInput = document.getElementById(fieldId + "_search");
    const resultsContainer = document.getElementById(fieldId + "_results");

    // Load initial selected items
    loadSelectedEntities(fieldId);

    // Search input handler
    if (searchInput) {
      let searchTimeout;
      searchInput.addEventListener("input", function () {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length < 1) {
          resultsContainer.style.display = "none";
          return;
        }

        searchTimeout = setTimeout(() => {
          searchEntities(fieldId, query);
        }, 300);
      });

      searchInput.addEventListener("focus", function () {
        if (this.value.trim().length >= 1) {
          resultsContainer.style.display = "block";
        }
      });

      // Close results on outside click
      document.addEventListener("click", function (e) {
        if (!wrapper.contains(e.target)) {
          resultsContainer.style.display = "none";
        }
      });
    }
  };

  /**
   * Load Selected Entities
   */
  function loadSelectedEntities(fieldId) {
    const config = entityReferences[fieldId];
    if (!config || !config.selectedIds.length) return;

    const { targetType, targetBundle } = config.options;
    const endpoint = (config.options.lookupEndpoint || `/admin/${targetType}s/lookup`) + `?ids=${config.selectedIds.join(',')}`;

    fetch(endpoint, { headers: { Accept: "application/json" } })
      .then((response) => response.json())
      .then((data) => {
        if (data.success && data.data) {
          renderSelectedEntities(fieldId, data.data);
        }
      })
      .catch((error) => {
        console.error("Error loading entities:", error);
      });
  }

  /**
   * Render Selected Entities
   */
  function renderSelectedEntities(fieldId, entities) {
    const container = document.getElementById(fieldId + "_selected");
    if (!container) return;

    container.innerHTML = entities
      .map(
        (entity) => `
            <div class="field-entity-reference__item" data-id="${entity.id}">
                <span class="field-entity-reference__item-label">${
                  entity.title || entity.name || "Item #" + entity.id
                }</span>
                <button type="button" class="field-entity-reference__item-remove" onclick="window.removeEntityReference('${fieldId}', ${
          entity.id
        })">&times;</button>
            </div>
        `
      )
      .join("");
  }

  /**
   * Search Entities
   */
  function searchEntities(fieldId, query) {
    const config = entityReferences[fieldId];
    if (!config) return;

    const { targetType, targetBundle } = config.options;
    const resultsContainer = document.getElementById(fieldId + "_results");

    let endpoint =
      config.options.searchEndpoint || `/admin/${targetType}s/search`;
    endpoint += `?q=${encodeURIComponent(query)}`;

    if (targetBundle && !config.options.searchEndpoint) {
      endpoint += `&type=${encodeURIComponent(targetBundle)}`;
    }

    fetch(endpoint, { headers: { Accept: "application/json" } })
      .then((response) => response.json())
      .then((data) => {
        if (data.success && data.data) {
          renderSearchResults(fieldId, data.data);
          resultsContainer.style.display = "block";
        } else {
          resultsContainer.innerHTML =
            '<div class="field-entity-reference__no-results">No results found</div>';
          resultsContainer.style.display = "block";
        }
      })
      .catch((error) => {
        console.error("Search error:", error);
        resultsContainer.innerHTML =
          '<div class="field-entity-reference__error">Search error</div>';
        resultsContainer.style.display = "block";
      });
  }

  /**
   * Render Search Results
   */
  function renderSearchResults(fieldId, results) {
    const config = entityReferences[fieldId];
    const resultsContainer = document.getElementById(fieldId + "_results");
    if (!resultsContainer) return;

    // Filter out already selected items
    const filteredResults = results.filter(
      (r) => !config.selectedIds.includes(r.id)
    );

    if (!filteredResults.length) {
      resultsContainer.innerHTML =
        '<div class="field-entity-reference__no-results">No results found</div>';
      return;
    }

    resultsContainer.innerHTML = filteredResults
      .map(
        (entity) => `
            <div class="field-entity-reference__result" onclick="window.selectEntityReference('${fieldId}', ${
          entity.id
        }, '${(entity.title || entity.name || "").replace(/'/g, "\\'")}')">
                <span class="field-entity-reference__result-label">${
                  entity.title || entity.name || "Item #" + entity.id
                }</span>
                ${
                  entity.type
                    ? `<span class="field-entity-reference__result-type">${entity.type}</span>`
                    : ""
                }
            </div>
        `
      )
      .join("");
  }

  /**
   * Select Entity
   */
  window.selectEntityReference = function (fieldId, entityId, entityLabel) {
    const config = entityReferences[fieldId];
    if (!config) return;

    const { multiple } = config.options;
    const selectedContainer = document.getElementById(fieldId + "_selected");
    const resultsContainer = document.getElementById(fieldId + "_results");
    const searchInput = document.getElementById(fieldId + "_search");
    const hidden = document.getElementById(fieldId);
    const wrapper = document.getElementById(fieldId + "_wrapper");

    // Add to selected
    if (multiple) {
      if (!config.selectedIds.includes(entityId)) {
        config.selectedIds.push(entityId);

        // Add visual element
        const item = document.createElement("div");
        item.className = "field-entity-reference__item";
        item.dataset.id = entityId;
        item.innerHTML = `
                    <span class="field-entity-reference__item-label">${entityLabel}</span>
                    <button type="button" class="field-entity-reference__item-remove" onclick="window.removeEntityReference('${fieldId}', ${entityId})">&times;</button>
                `;
        selectedContainer.appendChild(item);

        // Add hidden input
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = hidden.name.replace(/\[\]$/, "") + "[]";
        input.value = entityId;
        input.className = "field-entity-reference__value";
        wrapper.appendChild(input);
      }
    } else {
      config.selectedIds = [entityId];

      // Update display
      selectedContainer.innerHTML = `
                <div class="field-entity-reference__item" data-id="${entityId}">
                    <span class="field-entity-reference__item-label">${entityLabel}</span>
                    <button type="button" class="field-entity-reference__item-remove" onclick="window.removeEntityReference('${fieldId}', ${entityId})">&times;</button>
                </div>
            `;

      // Update hidden input
      hidden.value = entityId;
    }

    // Clear search
    if (searchInput) {
      searchInput.value = "";
    }
    resultsContainer.style.display = "none";
  };

  /**
   * Remove Entity Reference
   */
  window.removeEntityReference = function (fieldId, entityId) {
    const config = entityReferences[fieldId];
    if (!config) return;

    const { multiple } = config.options;
    const selectedContainer = document.getElementById(fieldId + "_selected");
    const hidden = document.getElementById(fieldId);
    const wrapper = document.getElementById(fieldId + "_wrapper");

    // Remove from array
    config.selectedIds = config.selectedIds.filter((id) => id !== entityId);

    // Remove visual element
    const item = selectedContainer.querySelector(`[data-id="${entityId}"]`);
    if (item) item.remove();

    if (multiple) {
      // Remove hidden input
      const input = wrapper.querySelector(
        `.field-entity-reference__value[value="${entityId}"]`
      );
      if (input) input.remove();
    } else {
      hidden.value = "";
    }
  };
})();
