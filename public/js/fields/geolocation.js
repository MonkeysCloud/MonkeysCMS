/**
 * MonkeysCMS Geolocation Field Widget
 * Provides interactive map with marker placement
 */
(function() {
    'use strict';

    window.CmsGeolocation = {
        maps: {},
        pendingInits: [],

        init: function(fieldId) {
            const hiddenInput = document.getElementById(fieldId);
            if (!hiddenInput) return;

            const wrapper = hiddenInput.closest('.field-geolocation');
            if (!wrapper) return;

            this.setupCoordinateInputs(wrapper, hiddenInput);
            this.setupLocateButton(wrapper, hiddenInput);
        },

        initWithMap: function(fieldId) {
            const self = this;
            const hiddenInput = document.getElementById(fieldId);
            if (!hiddenInput) return;

            const wrapper = hiddenInput.closest('.field-geolocation');
            if (!wrapper) return;

            // Initialize inputs first (idempotent setup)
            this.setupCoordinateInputs(wrapper, hiddenInput);
            this.setupLocateButton(wrapper, hiddenInput);

            // Initialize map - wait for Leaflet if needed
            const mapContainer = wrapper.querySelector('.field-geolocation__map');
            if (mapContainer) {
                // Check if already initialized
                if (mapContainer._leaflet_id) return;

                if (typeof L !== 'undefined') {
                    this.initLeafletMap(wrapper, hiddenInput, mapContainer);
                } else {
                    // Wait for Leaflet to load
                    this.pendingInits.push({ wrapper, hiddenInput, mapContainer });
                    this.waitForLeaflet();
                }
            }
        },

        waitForLeaflet: function() {
            const self = this;
            const checkInterval = setInterval(function() {
                if (typeof L !== 'undefined') {
                    clearInterval(checkInterval);
                    // Initialize all pending maps
                    self.pendingInits.forEach(function(item) {
                        self.initLeafletMap(item.wrapper, item.hiddenInput, item.mapContainer);
                    });
                    self.pendingInits = [];
                }
            }, 100);

            // Timeout after 10 seconds
            setTimeout(function() {
                clearInterval(checkInterval);
                if (self.pendingInits.length > 0) {
                    console.warn('Leaflet failed to load within 10 seconds');
                    self.pendingInits.forEach(function(item) {
                        self.showMapPlaceholder(item.mapContainer, 'Map library failed to load');
                    });
                    self.pendingInits = [];
                }
            }, 10000);
        },

        showMapPlaceholder: function(container, message) {
            const placeholder = document.createElement('div');
            placeholder.className = 'field-geolocation__map-placeholder';
            placeholder.textContent = message;
            container.appendChild(placeholder);
        },

        setupCoordinateInputs: function(wrapper, hiddenInput) {
            const self = this;
            const latInput = wrapper.querySelector('[data-field="lat"]');
            const lngInput = wrapper.querySelector('[data-field="lng"]');

            if (latInput && lngInput) {
                const updateValue = function() {
                    const lat = parseFloat(latInput.value) || 0;
                    const lng = parseFloat(lngInput.value) || 0;
                    
                    hiddenInput.value = JSON.stringify({ lat: lat, lng: lng });
                    
                    // Update map marker if exists
                    const mapData = self.maps[hiddenInput.id];
                    if (mapData && mapData.marker) {
                        mapData.marker.setLatLng([lat, lng]);
                        mapData.map.setView([lat, lng]);
                    }
                };

                latInput.addEventListener('change', updateValue);
                lngInput.addEventListener('change', updateValue);
                latInput.addEventListener('input', updateValue);
                lngInput.addEventListener('input', updateValue);
            }

            // Setup clear button
            this.setupClearButton(wrapper, hiddenInput);
        },

        setupClearButton: function(wrapper, hiddenInput) {
            const self = this;
            const clearBtn = wrapper.querySelector('[data-action="clear"]');
            if (!clearBtn) return;

            clearBtn.addEventListener('click', function() {
                // Clear inputs
                const latInput = wrapper.querySelector('[data-field="lat"]');
                const lngInput = wrapper.querySelector('[data-field="lng"]');
                
                if (latInput) latInput.value = '';
                if (lngInput) lngInput.value = '';
                
                // Clear hidden input
                hiddenInput.value = JSON.stringify({ lat: '', lng: '' });
                
                // Reset map marker to default position
                const mapData = self.maps[hiddenInput.id];
                if (mapData) {
                    const defaultLat = parseFloat(wrapper.dataset.defaultLat) || 0;
                    const defaultLng = parseFloat(wrapper.dataset.defaultLng) || 0;
                    mapData.marker.setLatLng([defaultLat, defaultLng]);
                    mapData.map.setView([defaultLat, defaultLng]);
                }
                
                self.showStatus(wrapper, 'Coordinates cleared', 'success');
            });
        },

        setupLocateButton: function(wrapper, hiddenInput) {
            const self = this;
            const locateBtn = wrapper.querySelector('[data-action="locate"]');
            if (!locateBtn) return;

            locateBtn.addEventListener('click', function() {
                if (!navigator.geolocation) {
                    self.showStatus(wrapper, 'Geolocation is not supported by your browser', 'error');
                    return;
                }

                locateBtn.classList.add('is-loading');
                locateBtn.textContent = '📍 Locating...';

                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;

                        self.setCoordinates(wrapper, hiddenInput, lat, lng);
                        self.showStatus(wrapper, 'Location found!', 'success');

                        locateBtn.classList.remove('is-loading');
                        locateBtn.textContent = '📍 My Location';
                    },
                    function(error) {
                        let message = 'Unable to get location';
                        switch (error.code) {
                            case error.PERMISSION_DENIED:
                                message = 'Location permission denied';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                message = 'Location unavailable';
                                break;
                            case error.TIMEOUT:
                                message = 'Location request timed out';
                                break;
                        }
                        self.showStatus(wrapper, message, 'error');

                        locateBtn.classList.remove('is-loading');
                        locateBtn.textContent = '📍 My Location';
                    },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            });
        },

        initLeafletMap: function(wrapper, hiddenInput, mapContainer) {
            const self = this;
            const defaultZoom = parseInt(wrapper.dataset.defaultZoom) || 10;
            
            // Parse initial coordinates
            let coords = { lat: 0, lng: 0 };
            try {
                coords = JSON.parse(hiddenInput.value) || coords;
            } catch (e) {}

            // Ensure map container has proper height
            if (!mapContainer.style.height || mapContainer.offsetHeight < 50) {
                mapContainer.style.height = '300px';
            }

            // Create map
            const map = L.map(mapContainer, {
                center: [coords.lat, coords.lng],
                zoom: defaultZoom,
                scrollWheelZoom: true
            });

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19
            }).addTo(map);

            // Add draggable marker
            const marker = L.marker([coords.lat, coords.lng], {
                draggable: true
            }).addTo(map);

            // Update coordinates when marker is dragged
            marker.on('dragend', function() {
                const pos = marker.getLatLng();
                self.setCoordinates(wrapper, hiddenInput, pos.lat, pos.lng);
            });

            // Allow clicking on map to place marker
            map.on('click', function(e) {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;
                marker.setLatLng([lat, lng]);
                self.setCoordinates(wrapper, hiddenInput, lat, lng);
            });

            // Store reference
            this.maps[hiddenInput.id] = { map: map, marker: marker };

            // Fix map size after container becomes visible
            setTimeout(function() { map.invalidateSize(); }, 100);
            setTimeout(function() { map.invalidateSize(); }, 500);
        },

        setCoordinates: function(wrapper, hiddenInput, lat, lng) {
            // Round to 6 decimal places
            lat = Math.round(lat * 1000000) / 1000000;
            lng = Math.round(lng * 1000000) / 1000000;

            // Update hidden input
            hiddenInput.value = JSON.stringify({ lat: lat, lng: lng });

            // Update visible inputs
            const latInput = wrapper.querySelector('[data-field="lat"]');
            const lngInput = wrapper.querySelector('[data-field="lng"]');

            if (latInput) latInput.value = lat;
            if (lngInput) lngInput.value = lng;

            // Update map marker
            const mapData = this.maps[hiddenInput.id];
            if (mapData) {
                mapData.marker.setLatLng([lat, lng]);
                mapData.map.setView([lat, lng]);
            }
        },

        showStatus: function(wrapper, message, type) {
            let statusEl = wrapper.querySelector('.field-geolocation__status');
            if (!statusEl) {
                statusEl = document.createElement('div');
                statusEl.className = 'field-geolocation__status';
                wrapper.appendChild(statusEl);
            }

            statusEl.textContent = message;
            statusEl.className = 'field-geolocation__status';
            if (type === 'error') {
                statusEl.classList.add('is-error');
            } else if (type === 'success') {
                statusEl.classList.add('is-success');
            }

            // Clear after 3 seconds
            setTimeout(function() {
                statusEl.textContent = '';
                statusEl.className = 'field-geolocation__status';
            }, 3000);
        },

        // Geocode an address to coordinates (requires Nominatim)
        geocode: function(address) {
            return fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(address))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data && data.length > 0) {
                        return {
                            lat: parseFloat(data[0].lat),
                            lng: parseFloat(data[0].lon)
                        };
                    }
                    throw new Error('Address not found');
                });
        }
    };

    // Initialize all geolocation fields in a context
    function initAll(context) {
        context = context || document;
        context.querySelectorAll('.field-geolocation').forEach(function(wrapper) {
            var hiddenInput = wrapper.querySelector('input[type="hidden"]');
            if (hiddenInput && hiddenInput.id) {
                window.CmsGeolocation.initWithMap(hiddenInput.id);
            }
        });
    }

    // Self-initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { initAll(document); });
    } else {
        initAll(document);
    }

    // Handle dynamically added repeater items 
    document.addEventListener('cms:content-changed', function(e) {
        if (e.detail && e.detail.target) {
            initAll(e.detail.target);
        }
    });

    // Register with global behaviors system (if available)
    if (window.CmsBehaviors) {
        window.CmsBehaviors.register('geolocation', {
            selector: '.field-geolocation',
            attach: initAll
        });
    }
})();
