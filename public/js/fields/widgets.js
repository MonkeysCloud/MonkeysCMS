/**
 * MonkeysCMS Field Widget JavaScript
 *
 * Core functionality for all field widgets including initialization,
 * event handling, and API interactions.
 */

(function (window) {
  "use strict";

  // =============================================================================
  // Core CMS Namespace
  // =============================================================================

  const CmsFields = {
    initialized: false,
    widgets: {},

    /**
     * Initialize all field widgets on the page
     */
    /**
     * Initialize all field widgets on the page or within a container
     */
    init(container = document) {
      if (this.initialized && container === document) return;

      // Auto-initialize widgets with data attributes
      const selector = "[data-field-id]";
      const elements =
        container === document
          ? container.querySelectorAll(selector)
          : container.matches(selector)
          ? [container, ...container.querySelectorAll(selector)]
          : container.querySelectorAll(selector);

      elements.forEach((el) => {
        // Match field-widget--{type} pattern (e.g., field-widget--code, field-widget--content)
        const widgetType =
          el.className.match(/field-widget--(\w+)/)?.[1] ||
          el.className.match(/field-(\w+)/)?.[1];
        // Map widget types to global objects
        const TypeMap = {
          code: "CmsCode",
          geolocation: "CmsGeolocation",
          checkboxes: "CmsCheckboxes", // Ensure this exists if mapped
          repeater: "CmsRepeater",
        };

        const globalName =
          TypeMap[widgetType] ||
          "Cms" +
            (widgetType
              ? widgetType.charAt(0).toUpperCase() + widgetType.slice(1)
              : "");

        if (
          window[globalName] &&
          typeof window[globalName].init === "function"
        ) {
          // Pass only fieldId, let widget handle the rest via data attributes
          window[globalName].init(el.dataset.fieldId);
        } else if (this.widgets[widgetType]) {
          this.widgets[widgetType](el.dataset.fieldId);
        }
      });

      if (container === document) {
        this.initialized = true;
        this.setupObserver();
      }
    },

    setupObserver() {
      document.addEventListener("cms:content-changed", (e) => {
        if (e.detail && e.detail.target) {
          this.init(e.detail.target);
        }
      });
    },

    /**
     * Register a widget initializer
     */
    register(name, initializer) {
      this.widgets[name] = initializer;
    },
  };

  // =============================================================================
  // Textarea Widget
  // =============================================================================

  const CmsTextarea = {
    init(fieldId) {
      const textarea = document.getElementById(fieldId);
      if (!textarea) return;

      const wrapper = textarea.closest(".field-textarea");
      const counter = wrapper?.querySelector(".field-textarea__counter");
      const maxLength = parseInt(textarea.dataset.maxLength) || 0;

      if (counter && maxLength) {
        const update = () => {
          const remaining = maxLength - textarea.value.length;
          counter.textContent = `${textarea.value.length}/${maxLength}`;

          counter.classList.remove(
            "field-textarea__counter--warning",
            "field-textarea__counter--error"
          );
          if (remaining < 0) {
            counter.classList.add("field-textarea__counter--error");
          } else if (remaining < maxLength * 0.1) {
            counter.classList.add("field-textarea__counter--warning");
          }
        };

        textarea.addEventListener("input", update);
        update();
      }
    },
  };

  // =============================================================================
  // JSON Editor Widget
  // =============================================================================

  const CmsJson = {
    init(fieldId) {
      const textarea = document.getElementById(fieldId);
      if (!textarea) return;

      const wrapper = textarea.closest(".field-json");
      const status = document.getElementById(fieldId + "_status");
      const formatBtn = wrapper?.querySelector('[data-action="format"]');

      const validate = () => {
        try {
          if (textarea.value.trim()) {
            JSON.parse(textarea.value);
          }
          if (status) {
            status.textContent = "Valid JSON";
            status.className = "field-json__status field-json__status--valid";
          }
          return true;
        } catch (e) {
          if (status) {
            status.textContent = "Invalid: " + e.message;
            status.className = "field-json__status field-json__status--error";
          }
          return false;
        }
      };

      textarea.addEventListener("input", validate);

      if (formatBtn) {
        formatBtn.addEventListener("click", () => {
          try {
            const obj = JSON.parse(textarea.value);
            textarea.value = JSON.stringify(obj, null, 2);
            validate();
          } catch (e) {
            // Can't format invalid JSON
          }
        });
      }

      validate();
    },
  };

  // =============================================================================
  // Password Widget
  // =============================================================================

  const CmsPassword = {
    init(fieldId) {
      const input = document.getElementById(fieldId);
      if (!input) return;

      const wrapper = input.closest(".field-password");
      const toggle = wrapper?.querySelector(".field-password__toggle");
      const strength = document.getElementById(fieldId + "_strength");

      if (toggle) {
        toggle.addEventListener("click", () => {
          const type = input.type === "password" ? "text" : "password";
          input.type = type;
          toggle.textContent = type === "password" ? "👁️" : "🙈";
        });
      }

      if (strength) {
        const checkStrength = (password) => {
          let score = 0;
          if (password.length >= 8) score++;
          if (password.length >= 12) score++;
          if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
          if (/\d/.test(password)) score++;
          if (/[^a-zA-Z0-9]/.test(password)) score++;
          return score;
        };

        input.addEventListener("input", () => {
          const score = checkStrength(input.value);
          const labels = ["Very Weak", "Weak", "Fair", "Strong", "Very Strong"];
          const classes = [
            "very-weak",
            "weak",
            "fair",
            "strong",
            "very-strong",
          ];

          strength.textContent = input.value ? labels[Math.min(score, 4)] : "";
          strength.className =
            "field-password__strength field-password__strength--" +
            classes[Math.min(score, 4)];
        });
      }
    },
  };

  // =============================================================================
  // Color Widget
  // =============================================================================

  const CmsColor = {
    init(fieldId) {
      const colorInput = document.getElementById(fieldId);
      if (!colorInput) return;

      const hexInput = document.getElementById(fieldId + "_hex");
      const preview = document.getElementById(fieldId + "_preview");

      if (hexInput) {
        colorInput.addEventListener("input", function () {
          hexInput.value = this.value;
          if (preview) preview.style.backgroundColor = this.value;
        });

        hexInput.addEventListener("input", function () {
          if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
            colorInput.value = this.value;
            if (preview) preview.style.backgroundColor = this.value;
          }
        });
      }
    },
  };

  // =============================================================================
  // Slug Widget
  // =============================================================================

  const CmsSlug = {
    init(fieldId) {
      const slugInput = document.getElementById(fieldId);
      if (!slugInput) return;

      const wrapper = slugInput.closest(".field-slug");
      const sourceField = slugInput.dataset.source;
      const generateBtn = wrapper?.querySelector(".field-slug__generate");

      const slugify = (text) => {
        return text
          .toLowerCase()
          .normalize("NFD")
          .replace(/[\u0300-\u036f]/g, "")
          .replace(/[^a-z0-9]+/g, "-")
          .replace(/(^-|-$)/g, "");
      };

      if (sourceField && generateBtn) {
        const formId = fieldId.split("_")[0];
        const sourceInput = document.getElementById(formId + "_" + sourceField);

        generateBtn.addEventListener("click", () => {
          if (sourceInput) {
            slugInput.value = slugify(sourceInput.value);
          }
        });

        // Auto-generate if slug is empty
        if (sourceInput && !slugInput.value) {
          sourceInput.addEventListener("blur", function () {
            if (!slugInput.value) {
              slugInput.value = slugify(this.value);
            }
          });
        }
      }
    },
  };

  // =============================================================================
  // Range Widget
  // =============================================================================

  const CmsRange = {
    init(fieldId) {
      const input = document.getElementById(fieldId);
      if (!input) return;

      const valueDisplay = document.getElementById(fieldId + "_value");

      if (valueDisplay) {
        const update = () => {
          valueDisplay.textContent = input.value;
        };

        input.addEventListener("input", update);
        update();
      }
    },
  };

  // =============================================================================
  // Address Widget
  // =============================================================================

  const CmsAddress = {
    init(fieldId) {
      const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
      if (!wrapper) return;

      const hiddenInput = document.getElementById(fieldId);
      const fields = wrapper.querySelectorAll("[data-field]");

      const updateValue = () => {
        const address = {};
        fields.forEach((field) => {
          address[field.dataset.field] = field.value;
        });
        hiddenInput.value = JSON.stringify(address);
      };

      fields.forEach((field) => {
        field.addEventListener("input", updateValue);
        field.addEventListener("change", updateValue);
      });
    },
  };

  // =============================================================================
  // Geolocation Widget
  // =============================================================================

  const CmsGeolocation = {
    init(fieldId) {
      this.initWithMap(fieldId, false);
    },

    initWithMap(fieldId, showMap = true) {
      const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
      if (!wrapper) return;

      const hiddenInput = document.getElementById(fieldId);
      const latInput = document.getElementById(fieldId + "_lat");
      const lngInput = document.getElementById(fieldId + "_lng");
      const locateBtn = wrapper.querySelector('[data-action="locate"]');
      const mapContainer = document.getElementById(fieldId + "_map");

      let map = null;
      let marker = null;

      const updateValue = () => {
        const coords = {
          lat: parseFloat(latInput.value) || 0,
          lng: parseFloat(lngInput.value) || 0,
        };
        hiddenInput.value = JSON.stringify(coords);

        if (marker && map) {
          marker.setLatLng([coords.lat, coords.lng]);
          map.setView([coords.lat, coords.lng]);
        }
      };

      latInput.addEventListener("input", updateValue);
      lngInput.addEventListener("input", updateValue);

      // Get current location
      if (locateBtn) {
        locateBtn.addEventListener("click", () => {
          if (navigator.geolocation) {
            locateBtn.disabled = true;
            locateBtn.textContent = "⏳ Locating...";

            navigator.geolocation.getCurrentPosition(
              (position) => {
                latInput.value = position.coords.latitude.toFixed(6);
                lngInput.value = position.coords.longitude.toFixed(6);
                updateValue();
                locateBtn.disabled = false;
                locateBtn.textContent = "📍 My Location";
              },
              (error) => {
                alert("Unable to get location: " + error.message);
                locateBtn.disabled = false;
                locateBtn.textContent = "📍 My Location";
              }
            );
          }
        });
      }

      // Initialize map if Leaflet is available
      if (showMap && mapContainer && typeof L !== "undefined") {
        const defaultZoom = parseInt(wrapper.dataset.defaultZoom) || 10;
        const lat = parseFloat(latInput.value) || 0;
        const lng = parseFloat(lngInput.value) || 0;

        map = L.map(mapContainer).setView([lat, lng], defaultZoom);

        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
          attribution: "© OpenStreetMap contributors",
        }).addTo(map);

        marker = L.marker([lat, lng], { draggable: true }).addTo(map);

        marker.on("dragend", () => {
          const pos = marker.getLatLng();
          latInput.value = pos.lat.toFixed(6);
          lngInput.value = pos.lng.toFixed(6);
          updateValue();
        });

        map.on("click", (e) => {
          latInput.value = e.latlng.lat.toFixed(6);
          lngInput.value = e.latlng.lng.toFixed(6);
          updateValue();
        });
      }
    },
  };

  // =============================================================================
  // Link Widget
  // =============================================================================

  const CmsLink = {
    init(fieldId) {
      const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
      if (!wrapper) return;

      const hiddenInput = document.getElementById(fieldId);
      const urlInput = document.getElementById(fieldId + "_url");
      const titleInput = document.getElementById(fieldId + "_title");
      const externalCheckbox = document.getElementById(fieldId + "_external");

      const updateValue = () => {
        const link = {
          url: urlInput?.value || "",
          title: titleInput?.value || "",
          target: externalCheckbox?.checked ? "_blank" : "_self",
        };
        hiddenInput.value = JSON.stringify(link);
      };

      urlInput?.addEventListener("input", updateValue);
      titleInput?.addEventListener("input", updateValue);
      externalCheckbox?.addEventListener("change", updateValue);
    },
  };

  // =============================================================================
  // Media Widgets
  // =============================================================================

  const CmsMedia = {
    initImage(fieldId) {
      const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
      if (!wrapper) return;

      const hiddenInput = document.getElementById(fieldId);
      const fileInput = wrapper.querySelector(".field-image__file");
      const preview = wrapper.querySelector(".field-image__preview");
      const removeBtn = wrapper.querySelector(".field-image__remove");
      const browseBtn = wrapper.querySelector(".field-image__browse");

      const updatePreview = (url) => {
        if (url) {
          preview.innerHTML = `<img src="${url}" alt="Preview" class="field-image__img">`;
          removeBtn.style.display = "";
        } else {
          preview.innerHTML =
            '<div class="field-image__placeholder">No image selected</div>';
          removeBtn.style.display = "none";
        }
        hiddenInput.value = url || "";
      };

      fileInput?.addEventListener("change", async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        // Check file size
        const maxSize = parseInt(fileInput.dataset.maxSize) || 5242880;
        if (file.size > maxSize) {
          alert(
            `File too large. Maximum size is ${Math.round(
              maxSize / 1024 / 1024
            )}MB`
          );
          return;
        }

        // Upload file using XHR with HTMX headers
        const formData = new FormData();
        formData.append("file", file);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/media/upload', true);
        xhr.setRequestHeader('HX-Request', 'true');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                          document.querySelector('input[name="csrf_token"]')?.value || '';
        if (csrfToken) xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        xhr.onreadystatechange = function() {
          if (xhr.readyState === 4) {
            if (xhr.status === 200 || xhr.status === 201) {
              try {
                const data = JSON.parse(xhr.responseText);
                updatePreview(data.url);
              } catch (e) {
                console.error("Upload parse failed:", e);
                alert("Upload failed. Please try again.");
              }
            } else {
              console.error("Upload failed:", xhr.status);
              alert("Upload failed. Please try again.");
            }
          }
        };
        xhr.send(formData);
      });

      removeBtn?.addEventListener("click", () => {
        updatePreview(null);
        if (fileInput) fileInput.value = "";
      });

      browseBtn?.addEventListener("click", () => {
        // Open media library modal
        CmsMedia.openLibrary((url) => {
          updatePreview(url);
        });
      });
    },

    initFile(fieldId) {
      const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
      if (!wrapper) return;

      const hiddenInput = document.getElementById(fieldId);
      const dropzone = wrapper.querySelector(".field-file__dropzone");
      const fileInput = wrapper.querySelector(".field-file__input");
      const info = wrapper.querySelector(".field-file__info");
      const removeBtn = wrapper.querySelector(".field-file__remove");

      const updateInfo = (url, filename) => {
        if (url && filename) {
          const icon = this.getFileIcon(filename);
          info.innerHTML = `
                        <span class="field-file__icon">${icon}</span>
                        <span class="field-file__name">${filename}</span>
                        <a href="${url}" target="_blank" class="field-file__download">Download</a>
                    `;
          info.style.display = "";
          removeBtn.style.display = "";
        } else {
          info.style.display = "none";
          removeBtn.style.display = "none";
        }
        hiddenInput.value = url || "";
      };

      // Drag and drop
      dropzone?.addEventListener("dragover", (e) => {
        e.preventDefault();
        dropzone.classList.add("field-file__dropzone--dragover");
      });

      dropzone?.addEventListener("dragleave", () => {
        dropzone.classList.remove("field-file__dropzone--dragover");
      });

      dropzone?.addEventListener("drop", async (e) => {
        e.preventDefault();
        dropzone.classList.remove("field-file__dropzone--dragover");

        const file = e.dataTransfer.files[0];
        if (file) {
          await this.uploadFile(file, fileInput, updateInfo);
        }
      });

      dropzone?.addEventListener("click", () => {
        fileInput?.click();
      });

      fileInput?.addEventListener("change", async (e) => {
        const file = e.target.files[0];
        if (file) {
          await this.uploadFile(file, fileInput, updateInfo);
        }
      });

      removeBtn?.addEventListener("click", () => {
        updateInfo(null, null);
        if (fileInput) fileInput.value = "";
      });
    },

    initGallery(fieldId) {
      const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
      if (!wrapper) return;

      const hiddenInput = document.getElementById(fieldId);
      const grid = wrapper.querySelector(".field-gallery__grid");
      const addBtn = wrapper.querySelector(".field-gallery__add");
      const maxItems = parseInt(wrapper.dataset.maxItems) || 0;

      let images = [];
      try {
        images = JSON.parse(hiddenInput.value) || [];
      } catch (e) {}

      const updateValue = () => {
        hiddenInput.value = JSON.stringify(images);

        if (maxItems > 0) {
          addBtn.disabled = images.length >= maxItems;
        }
      };

      const renderGrid = () => {
        grid.innerHTML = images
          .map(
            (url, index) => `
                    <div class="field-gallery__item" data-index="${index}" draggable="true">
                        <img src="${url}" alt="">
                        <button type="button" class="field-gallery__remove" data-action="remove">×</button>
                    </div>
                `
          )
          .join("");

        // Add drag and drop
        this.initDragDrop(grid, images, updateValue, renderGrid);

        // Add remove handlers
        grid.querySelectorAll('[data-action="remove"]').forEach((btn) => {
          btn.addEventListener("click", (e) => {
            const index = parseInt(
              e.target.closest(".field-gallery__item").dataset.index
            );
            images.splice(index, 1);
            updateValue();
            renderGrid();
          });
        });
      };

      addBtn?.addEventListener("click", () => {
        this.openLibrary((urls) => {
          if (!Array.isArray(urls)) urls = [urls];
          images = images.concat(urls);

          if (maxItems > 0) {
            images = images.slice(0, maxItems);
          }

          updateValue();
          renderGrid();
        }, true);
      });

      renderGrid();
    },

    initVideo(fieldId) {
      const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
      if (!wrapper) return;

      const input = document.getElementById(fieldId);
      const preview = document.getElementById(fieldId + "_preview");

      const updatePreview = () => {
        const url = input.value;
        if (!url) {
          preview.innerHTML = "";
          return;
        }

        // YouTube
        let match = url.match(
          /(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/
        );
        if (match) {
          preview.innerHTML = `<iframe src="https://www.youtube.com/embed/${match[1]}" frameborder="0" allowfullscreen></iframe>`;
          return;
        }

        // Vimeo
        match = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
        if (match) {
          preview.innerHTML = `<iframe src="https://player.vimeo.com/video/${match[1]}" frameborder="0" allowfullscreen></iframe>`;
          return;
        }

        // Direct video
        if (/\.(mp4|webm|ogg)$/i.test(url)) {
          preview.innerHTML = `<video src="${url}" controls></video>`;
          return;
        }

        preview.innerHTML = "";
      };

      input?.addEventListener("input", updatePreview);
      updatePreview();
    },

    async uploadFile(file, fileInput, callback) {
      const maxSize = parseInt(fileInput?.dataset.maxSize) || 10485760;

      if (file.size > maxSize) {
        alert(
          `File too large. Maximum size is ${Math.round(
            maxSize / 1024 / 1024
          )}MB`
        );
        return;
      }

      const formData = new FormData();
      formData.append("file", file);

      // Use XHR with HTMX headers
      const xhr = new XMLHttpRequest();
      xhr.open('POST', '/api/media/upload', true);
      xhr.setRequestHeader('HX-Request', 'true');
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                        document.querySelector('input[name="csrf_token"]')?.value || '';
      if (csrfToken) xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
          if (xhr.status === 200 || xhr.status === 201) {
            try {
              const data = JSON.parse(xhr.responseText);
              callback(data.url, file.name);
            } catch (e) {
              console.error("Upload parse failed:", e);
              alert("Upload failed. Please try again.");
            }
          } else {
            console.error("Upload failed:", xhr.status);
            alert("Upload failed. Please try again.");
          }
        }
      };
      xhr.send(formData);
    },

    getFileIcon(filename) {
      const ext = filename.split(".").pop().toLowerCase();
      const icons = {
        pdf: "📕",
        doc: "📘",
        docx: "📘",
        xls: "📗",
        xlsx: "📗",
        ppt: "📙",
        pptx: "📙",
        zip: "📦",
        rar: "📦",
        "7z": "📦",
        txt: "📄",
        csv: "📊",
      };
      return icons[ext] || "📎";
    },

    openLibrary(callback, multiple = false) {
      // Implement media library modal
      // For now, use file picker
      const input = document.createElement("input");
      input.type = "file";
      input.multiple = multiple;
      input.accept = "image/*";

      input.addEventListener("change", () => {
        // In a real implementation, upload files and return URLs
        const files = Array.from(input.files);
        const urls = files.map((f) => URL.createObjectURL(f));
        callback(multiple ? urls : urls[0]);
      });

      input.click();
    },

    initDragDrop(container, items, onUpdate, onRender) {
      let draggedIndex = null;

      container.querySelectorAll('[draggable="true"]').forEach((item) => {
        item.addEventListener("dragstart", (e) => {
          draggedIndex = parseInt(item.dataset.index);
          item.classList.add("dragging");
        });

        item.addEventListener("dragend", () => {
          item.classList.remove("dragging");
        });

        item.addEventListener("dragover", (e) => {
          e.preventDefault();
        });

        item.addEventListener("drop", (e) => {
          e.preventDefault();
          const dropIndex = parseInt(item.dataset.index);

          if (draggedIndex !== dropIndex) {
            const [moved] = items.splice(draggedIndex, 1);
            items.splice(dropIndex, 0, moved);
            onUpdate();
            onRender();
          }
        });
      });
    },
  };

  // =============================================================================
  // Entity Reference Widget
  // =============================================================================

  const CmsEntityReference = {
    init(fieldId, apiUrl) {
      const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
      if (!wrapper) return;

      const hiddenInput = document.getElementById(fieldId);
      const searchInput = wrapper.querySelector(
        ".field-entity-reference__input"
      );
      const dropdown = document.getElementById(fieldId + "_dropdown");
      const selected = wrapper.querySelector(
        ".field-entity-reference__selected"
      );
      const multiple = wrapper.dataset.multiple === "true";

      let values = [];
      try {
        values = multiple
          ? JSON.parse(hiddenInput.value)
          : hiddenInput.value
          ? [hiddenInput.value]
          : [];
      } catch (e) {}

      const updateValue = () => {
        hiddenInput.value = multiple ? JSON.stringify(values) : values[0] || "";
      };

      const renderSelected = () => {
        selected.innerHTML = values
          .map(
            (v) => `
                    <div class="field-entity-reference__item" data-value="${v}">
                        <span class="field-entity-reference__item-label">${v}</span>
                        <button type="button" class="field-entity-reference__item-remove" data-action="remove">×</button>
                    </div>
                `
          )
          .join("");

        selected.querySelectorAll('[data-action="remove"]').forEach((btn) => {
          btn.addEventListener("click", (e) => {
            const value = e.target.closest(".field-entity-reference__item")
              .dataset.value;
            values = values.filter((v) => v !== value);
            updateValue();
            renderSelected();
          });
        });
      };

      let debounceTimer;
      searchInput?.addEventListener("input", () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(async () => {
          const query = searchInput.value.trim();
          if (query.length < 2) {
            dropdown.classList.remove("field-entity-reference__dropdown--open");
            return;
          }

          // Use XHR with HTMX headers
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `${apiUrl}?q=${encodeURIComponent(query)}`, true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('HX-Request', 'true');
            xhr.onreadystatechange = function() {
              if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                  try {
                    const data = JSON.parse(xhr.responseText);

                    dropdown.innerHTML =
                      data.results
                        .map(
                          (item) => `
                                    <div class="field-entity-reference__option" data-value="${item.id}" data-label="${item.label}">
                                        ${item.label}
                                    </div>
                                `
                        )
                        .join("") ||
                      '<div class="field-entity-reference__empty">No results found</div>';

                    dropdown.classList.add("field-entity-reference__dropdown--open");

                    dropdown
                      .querySelectorAll(".field-entity-reference__option")
                      .forEach((opt) => {
                        opt.addEventListener("click", () => {
                          const value = opt.dataset.value;

                          if (!multiple) {
                            values = [value];
                          } else if (!values.includes(value)) {
                            values.push(value);
                          }

                          updateValue();
                          renderSelected();
                          searchInput.value = "";
                          dropdown.classList.remove(
                            "field-entity-reference__dropdown--open"
                          );
                        });
                      });
                  } catch (error) {
                    console.error("Search parse failed:", error);
                  }
                } else {
                  console.error("Search failed:", xhr.status);
                }
              }
            };
            xhr.send();
        }, 300);
      });

      // Close dropdown on outside click
      document.addEventListener("click", (e) => {
        if (!wrapper.contains(e.target)) {
          dropdown?.classList.remove("field-entity-reference__dropdown--open");
        }
      });

      renderSelected();
    },
  };

  // =============================================================================
  // Taxonomy Widget
  // =============================================================================

  const CmsTaxonomy = {
    init(fieldId) {
      const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
      if (!wrapper) return;

      const hiddenInput = document.getElementById(fieldId);
      const checkboxes = wrapper.querySelectorAll('input[type="checkbox"]');

      const updateValue = () => {
        const values = Array.from(checkboxes)
          .filter((cb) => cb.checked)
          .map((cb) => cb.value);
        hiddenInput.value = JSON.stringify(values);
      };

      checkboxes.forEach((cb) => {
        cb.addEventListener("change", updateValue);
      });
    },

    initTags(fieldId, apiUrl) {
      const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
      if (!wrapper) return;

      const hiddenInput = document.getElementById(fieldId);
      const tagsContainer = wrapper.querySelector(".field-taxonomy__tags");
      const tagInput = document.getElementById(fieldId + "_input");
      const suggestions = document.getElementById(fieldId + "_suggestions");
      const allowNew = wrapper.dataset.allowNew === "true";

      let tags = [];
      try {
        tags = JSON.parse(hiddenInput.value) || [];
      } catch (e) {}

      const updateValue = () => {
        hiddenInput.value = JSON.stringify(tags);
      };

      const renderTags = () => {
        tagsContainer.innerHTML = tags
          .map(
            (tag) => `
                    <span class="field-taxonomy__tag" data-value="${tag}">
                        ${tag}
                        <button type="button" class="field-taxonomy__tag-remove">×</button>
                    </span>
                `
          )
          .join("");

        tagsContainer
          .querySelectorAll(".field-taxonomy__tag-remove")
          .forEach((btn) => {
            btn.addEventListener("click", (e) => {
              const tag = e.target.closest(".field-taxonomy__tag").dataset
                .value;
              tags = tags.filter((t) => t !== tag);
              updateValue();
              renderTags();
            });
          });
      };

      const addTag = (tag) => {
        tag = tag.trim();
        if (tag && !tags.includes(tag)) {
          tags.push(tag);
          updateValue();
          renderTags();
        }
        tagInput.value = "";
        suggestions.classList.remove("field-taxonomy__suggestions--open");
      };

      tagInput?.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          if (allowNew) {
            addTag(tagInput.value);
          }
        }
      });

      // Autocomplete
      let debounceTimer;
      tagInput?.addEventListener("input", () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(async () => {
          const query = tagInput.value.trim();
          if (query.length < 2) {
            suggestions.classList.remove("field-taxonomy__suggestions--open");
            return;
          }

          // Use XHR with HTMX headers
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `${apiUrl}?q=${encodeURIComponent(query)}`, true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('HX-Request', 'true');
            xhr.onreadystatechange = function() {
              if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                  const data = JSON.parse(xhr.responseText);

                  const filtered = data.terms.filter((t) => !tags.includes(t.name));

                  suggestions.innerHTML = filtered
                    .map(
                      (term) => `
                                <div class="field-taxonomy__suggestion" data-value="${term.name}">
                                    ${term.name}
                                </div>
                            `
                    )
                    .join("");

                  if (filtered.length) {
                    suggestions.classList.add("field-taxonomy__suggestions--open");
                  }

                  suggestions
                    .querySelectorAll(".field-taxonomy__suggestion")
                    .forEach((opt) => {
                      opt.addEventListener("click", () => {
                        addTag(opt.dataset.value);
                        suggestions.classList.remove(
                          "field-taxonomy__suggestions--open"
                        );
                      });
                    });
                } catch (error) {
                  console.error("Taxonomy search failed:", error);
                }
              } else if (xhr.readyState === 4) {
                console.error("Taxonomy search failed:", xhr.status);
              }
            };
            xhr.send();
        }, 300);
      });

      renderTags();
    },
  };

  // =============================================================================
  // Repeater Widget
  // =============================================================================

  const CmsRepeater = {
    init(fieldId) {
      const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
      if (!wrapper) return;

      const itemsContainer = wrapper.querySelector(".field-repeater__items");
      const addBtn = wrapper.querySelector(".field-repeater__add");
      const template = wrapper.querySelector(".field-repeater__template");
      const minItems = parseInt(wrapper.dataset.minItems) || 0;
      const maxItems = parseInt(wrapper.dataset.maxItems) || 0;
      const collapsible = wrapper.dataset.collapsible === "true";
      const confirmDelete = wrapper.dataset.confirmDelete === "true";

      let itemCount = itemsContainer.querySelectorAll(
        ".field-repeater__item"
      ).length;

      const updateIndices = () => {
        const items = itemsContainer.querySelectorAll(".field-repeater__item");
        items.forEach((item, index) => {
          item.dataset.index = index;

          // Update form field names
          item.querySelectorAll("[name]").forEach((field) => {
            field.name = field.name.replace(/\[\d+\]/, `[${index}]`);
          });

          // Update IDs
          item.querySelectorAll("[id]").forEach((field) => {
            field.id = field.id.replace(/_\d+_/, `_${index}_`);
          });

          // Update item number
          const numberEl = item.querySelector(".field-repeater__item-number");
          if (numberEl) {
            numberEl.textContent = `#${index + 1}`;
          }
        });

        itemCount = items.length;

        // Update add button state
        if (maxItems > 0) {
          addBtn.disabled = itemCount >= maxItems;
        }
      };

      const addItem = () => {
        if (maxItems > 0 && itemCount >= maxItems) return;

        const html = template.innerHTML.replace(
          /__INDEX__/g,
          itemCount.toString()
        );
        itemsContainer.insertAdjacentHTML("beforeend", html);

        const newItem = itemsContainer.lastElementChild;
        initItem(newItem);
        updateIndices();

        // Initialize any nested widgets
        newItem.querySelectorAll("[data-field-id]").forEach((el) => {
          CmsFields.init();
        });
      };

      const removeItem = (item) => {
        if (
          confirmDelete &&
          !confirm("Are you sure you want to remove this item?")
        ) {
          return;
        }

        const items = itemsContainer.querySelectorAll(".field-repeater__item");
        if (items.length <= minItems) {
          alert(`Minimum ${minItems} items required`);
          return;
        }

        item.remove();
        updateIndices();
      };

      const initItem = (item) => {
        const header = item.querySelector(".field-repeater__item-header");
        const toggleBtn = item.querySelector(".field-repeater__toggle");
        const removeBtn = item.querySelector(".field-repeater__remove");

        // Toggle collapse
        if (collapsible && toggleBtn) {
          toggleBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            item.classList.toggle("field-repeater__item--collapsed");
          });

          header?.addEventListener("click", () => {
            item.classList.toggle("field-repeater__item--collapsed");
          });
        }

        // Remove item
        removeBtn?.addEventListener("click", (e) => {
          e.stopPropagation();
          removeItem(item);
        });
      };

      // Initialize existing items
      itemsContainer
        .querySelectorAll(".field-repeater__item")
        .forEach(initItem);

      // Add button
      addBtn?.addEventListener("click", addItem);

      // Drag and drop reordering
      this.initDragDrop(itemsContainer, updateIndices);
    },

    initDragDrop(container, onUpdate) {
      let draggedItem = null;

      container.addEventListener("dragstart", (e) => {
        if (e.target.classList.contains("field-repeater__item")) {
          draggedItem = e.target;
          e.target.classList.add("dragging");
        }
      });

      container.addEventListener("dragend", (e) => {
        if (e.target.classList.contains("field-repeater__item")) {
          e.target.classList.remove("dragging");
          draggedItem = null;
          onUpdate();
        }
      });

      container.addEventListener("dragover", (e) => {
        e.preventDefault();
        const afterElement = getDragAfterElement(container, e.clientY);

        if (draggedItem) {
          if (afterElement == null) {
            container.appendChild(draggedItem);
          } else {
            container.insertBefore(draggedItem, afterElement);
          }
        }
      });

      function getDragAfterElement(container, y) {
        const draggableElements = [
          ...container.querySelectorAll(".field-repeater__item:not(.dragging)"),
        ];

        return draggableElements.reduce(
          (closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
              return { offset: offset, element: child };
            } else {
              return closest;
            }
          },
          { offset: Number.NEGATIVE_INFINITY }
        ).element;
      }
    },
  };

  // =============================================================================
  // Markdown Widget
  // =============================================================================

  const CmsMarkdown = {
    init(fieldId) {
      const wrapper =
        document.querySelector(`[data-field-id="${fieldId}"]`) ||
        document.getElementById(fieldId)?.closest(".field-markdown");
      if (!wrapper) return;

      const textarea = document.getElementById(fieldId);
      const preview = document.getElementById(fieldId + "_preview");
      const toolbar = wrapper.querySelector(".field-markdown__toolbar");

      // Toolbar actions
      toolbar?.querySelectorAll(".field-markdown__btn").forEach((btn) => {
        btn.addEventListener("click", () => {
          const action = btn.dataset.action;

          if (action === "toggle-preview") {
            textarea.style.display =
              textarea.style.display === "none" ? "" : "none";
            preview.style.display =
              preview.style.display === "none" ? "" : "none";

            if (
              preview.style.display !== "none" &&
              typeof marked !== "undefined"
            ) {
              preview.innerHTML = marked.parse(textarea.value);
            }
            return;
          }

          const insert = btn.dataset.insert;
          if (insert) {
            this.insertText(textarea, insert);
          }
        });
      });
    },

    insertText(textarea, text) {
      const start = textarea.selectionStart;
      const end = textarea.selectionEnd;
      const selected = textarea.value.substring(start, end);

      let newText;
      if (text.includes("text")) {
        newText = text.replace("text", selected || "text");
      } else {
        newText = text + selected;
      }

      textarea.setRangeText(newText, start, end, "end");
      textarea.focus();
    },
  };

  // =============================================================================
  // Form Handling
  // =============================================================================

  const CmsForm = {
    init(formId) {
      const form = document.getElementById(formId);
      if (!form) return;

      const submitBtn = form.querySelector(".field-form__submit");

      form.addEventListener("submit", async (e) => {
        if (form.dataset.hxSubmit === "true") {
          e.preventDefault();

          submitBtn.disabled = true;
          submitBtn.textContent = "Saving...";

          // Use XHR with HTMX headers
          const formData = new FormData(form);
          const xhr = new XMLHttpRequest();
          xhr.open(form.method || "POST", form.action, true);
          xhr.setRequestHeader('HX-Request', 'true');
          const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                            document.querySelector('input[name="csrf_token"]')?.value || '';
          if (csrfToken) xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
          
          const self = this;
          xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
              submitBtn.disabled = false;
              submitBtn.textContent = "Save";
              
              if (xhr.status === 200) {
                try {
                  const data = JSON.parse(xhr.responseText);

                  if (data.errors) {
                    self.showErrors(form, data.errors);
                  } else if (data.redirect) {
                    window.location.href = data.redirect;
                  } else {
                    self.showSuccess(form, data.message || "Saved successfully");
                  }
                } catch (error) {
                  console.error("Form parse failed:", error);
                  self.showErrors(form, {
                    _form: ["An error occurred. Please try again."],
                  });
                }
              } else {
                console.error("Form submission failed:", xhr.status);
                self.showErrors(form, {
                  _form: ["An error occurred. Please try again."],
                });
              }
            }
          };
          xhr.send(formData);
        }
      });
    },

    showErrors(form, errors) {
      // Clear existing errors
      form.querySelectorAll(".field-widget--has-error").forEach((el) => {
        el.classList.remove("field-widget--has-error");
      });
      form.querySelectorAll(".field-widget__errors").forEach((el) => {
        el.innerHTML = "";
      });

      // Show form-level errors
      if (errors._form) {
        const formErrors = form.querySelector(".field-form__errors");
        if (formErrors) {
          formErrors.innerHTML = errors._form
            .map((e) => `<div>${e}</div>`)
            .join("");
          formErrors.style.display = "";
        }
      }

      // Show field-level errors
      Object.entries(errors).forEach(([field, messages]) => {
        if (field === "_form") return;

        const widget = form
          .querySelector(`[data-field="${field}"]`)
          ?.closest(".field-widget");
        if (widget) {
          widget.classList.add("field-widget--has-error");
          const errorsContainer = widget.querySelector(".field-widget__errors");
          if (errorsContainer) {
            errorsContainer.innerHTML = messages
              .map((m) => `<div class="field-widget__error">${m}</div>`)
              .join("");
          }
        }
      });
    },

    showSuccess(form, message) {
      // You could show a toast notification here
      alert(message);
    },
  };

  // =============================================================================
  // Export to Global Scope
  // =============================================================================

  window.CmsFields = CmsFields;
  window.CmsTextarea = CmsTextarea;
  window.CmsJson = CmsJson;
  window.CmsPassword = CmsPassword;
  window.CmsColor = CmsColor;
  window.CmsSlug = CmsSlug;
  window.CmsRange = CmsRange;
  window.CmsAddress = CmsAddress;
  window.CmsGeolocation = CmsGeolocation;
  window.CmsLink = CmsLink;
  window.CmsMedia = CmsMedia;
  window.CmsEntityReference = CmsEntityReference;
  window.CmsTaxonomy = CmsTaxonomy;
  window.CmsRepeater = CmsRepeater;
  window.CmsMarkdown = CmsMarkdown;
  window.CmsForm = CmsForm;

  // Auto-initialize on DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => CmsFields.init());
  } else {
    CmsFields.init();
  }
})(window);
