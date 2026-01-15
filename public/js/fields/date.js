/**
 * MonkeysCMS Date Field Widget
 * Uses Flatpickr for a customizable date picker
 */
window.CmsDate = {
    init: function(fieldId, options = {}) {
        const input = document.getElementById(fieldId);
        if (!input) return;

        let wrapper = input.closest('.field-date');
        if (!wrapper) {
            wrapper = input.closest('.field-datetime');
        }
        if (!wrapper) {
            wrapper = input.closest('.field-time');
        }
        if (!wrapper) return;

        const minDate = options.minDate;
        const maxDate = options.maxDate;

        // Create message element if not present
        let messageEl = wrapper.querySelector('.field-date__message');
        if (!messageEl) {
            messageEl = document.createElement('div');
            // If wrapper is field-datetime, use field-datetime__message, else field-date__message
            let prefix = 'field-date';
            if (wrapper.classList.contains('field-datetime')) prefix = 'field-datetime';
            if (wrapper.classList.contains('field-time')) prefix = 'field-time';

            messageEl.className = `${prefix}__message`;
            wrapper.appendChild(messageEl);
        }

        // Check if Flatpickr is available
        if (typeof flatpickr !== 'undefined') {
            // Change type to text but allow input
            // Change type to text but allow input
            input.type = 'text';
            input.removeAttribute('readonly');
            // Check prefix again as we might be in a different context if not init message
            let prefix = 'field-date';
            if (wrapper.classList.contains('field-datetime')) prefix = 'field-datetime';
            if (wrapper.classList.contains('field-time')) prefix = 'field-time';

            input.classList.add(`${prefix}__input`);
            
            // Add calendar icon button
            const inputWrapper = input.closest(`.${prefix}__input-wrapper`);
            if (inputWrapper && !inputWrapper.querySelector(`.${prefix}__toggle`)) {
                const toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.className = `${prefix}__toggle`;
                toggleBtn.innerHTML = prefix === 'field-time' ? '🕒' : '📅';
                toggleBtn.title = prefix === 'field-time' ? 'Open time picker' : 'Open calendar';
                inputWrapper.appendChild(toggleBtn);
            }
            
            // Initialize Flatpickr
            const flatpickrConfig = Object.assign({
                dateFormat: 'Y-m-d', // Default, can be overridden by options
                altInput: true,
                altFormat: 'F j, Y', // Default, overridden if options has altFormat
                allowInput: true, // Allow typing
                animate: true,
                minDate: minDate || null,
                maxDate: maxDate || null,
                disableMobile: true,
                clickOpens: true,
                wrap: false,
                // Fix timezone issue by using local date parsing
                parseDate: function(datestr, format) {
                    // Handle time-only strings (no-calendar mode)
                    if (options.noCalendar) {
                        // Create a dummy date with the time
                        const dummyDate = new Date();
                        const timeParts = datestr.split(':');
                        if (timeParts.length >= 2) {
                            dummyDate.setHours(parseInt(timeParts[0]), parseInt(timeParts[1]), 0, 0);
                            return dummyDate;
                        }
                    }

                    // Start with basic detection
                    const hasTime = format.includes('H') || format.includes('h');
                    
                    // Parse as local date, not UTC
                    // Simple ISO/Date string parsing manually to avoid UTC conversion
                    // Format YYYY-MM-DD or YYYY-MM-DD HH:MM
                    const parts = datestr.split(/[\sT]+/); // Split by space or T
                    const dateParts = parts[0].split('-');
                    
                    if (dateParts.length === 3) {
                        const year = parseInt(dateParts[0]);
                        const month = parseInt(dateParts[1]) - 1;
                        const day = parseInt(dateParts[2]);
                        
                        let hour = 0, minute = 0, second = 0;
                        
                        if (parts.length > 1) {
                            const timeParts = parts[1].split(':');
                            if (timeParts.length >= 2) {
                                hour = parseInt(timeParts[0]);
                                minute = parseInt(timeParts[1]);
                                if (timeParts.length > 2) second = parseInt(timeParts[2]);
                            }
                        }
                        
                        return new Date(year, month, day, hour, minute, second);
                    }
                    return new Date(datestr);
                },
                onChange: function(selectedDates, dateStr) {
                    if (selectedDates.length > 0) {
                        // Use local date for validation display
                        CmsDate.validateWithDate(input, messageEl, selectedDates[0], { minDate, maxDate, ...options });
                    }
                },
                onReady: function(selectedDates, dateStr, instance) {
                    // Add "Today" button to calendar
                    const todayBtn = document.createElement('button');
                    todayBtn.type = 'button';
                    todayBtn.className = 'flatpickr-today-btn';
                    todayBtn.textContent = 'Today'; // Could be "Now" if time enabled?
                    todayBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        instance.setDate(new Date(), true);
                        instance.close();
                    });
                    
                    // Append to calendar
                    instance.calendarContainer.appendChild(todayBtn);
                    
                    // Connect toggle button
                    const toggle = inputWrapper?.querySelector(`.${prefix}__toggle`);
                    if (toggle) {
                        toggle.addEventListener('click', function(e) {
                            e.preventDefault();
                            instance.toggle();
                        });
                    }
                    
                    // Validate if has value
                    if (selectedDates.length > 0) {
                        CmsDate.validateWithDate(input, messageEl, selectedDates[0], { minDate, maxDate, ...options });
                    }
                }
            }, options);

            const fp = flatpickr(input, flatpickrConfig);

            // Store reference for later
            input._flatpickr = fp;
            
            // Handle manual input
            const altInput = fp.altInput;
            if (altInput) {
                altInput.classList.add(`${prefix}__input`);
                altInput.removeAttribute('readonly');
                
                altInput.addEventListener('blur', function() {
                    // Try to parse manual input
                    const value = this.value.trim();
                    if (value) {
                        const parsed = new Date(value);
                        if (!isNaN(parsed.getTime())) {
                            fp.setDate(parsed, true);
                        }
                    }
                });
            }
        } else {
            // Fallback to native validation
            input.addEventListener('change', function() {
                CmsDate.validate(this, messageEl, { minDate, maxDate });
            });

            input.addEventListener('blur', function() {
                CmsDate.validate(this, messageEl, { minDate, maxDate });
            });

            if (input.value) {
                CmsDate.validate(input, messageEl, { minDate, maxDate });
            }
        }
    },

    validateWithDate: function(input, messageEl, date, options = {}) {
        const { minDate, maxDate } = options;
        
        // Find the visible input (Flatpickr creates an altInput)
        const visibleInput = input._flatpickr ? input._flatpickr.altInput : input;
        
        // Remove existing classes
        visibleInput.classList.remove('is-valid', 'is-invalid');
        // Find wrapper to determine prefix
        const parentWrapper = visibleInput.closest('.field-date') || visibleInput.closest('.field-datetime') || visibleInput.closest('.field-time');
        let prefix = 'field-date';
        if (parentWrapper && parentWrapper.classList.contains('field-datetime')) prefix = 'field-datetime';
        if (parentWrapper && parentWrapper.classList.contains('field-time')) prefix = 'field-time';
        
        messageEl.classList.remove(`${prefix}__message--valid`, `${prefix}__message--invalid`);
        messageEl.textContent = '';

        if (!date) {
            return;
        }

        // Check min date
        if (minDate) {
            const min = new Date(minDate);
            if (date < min) {
                visibleInput.classList.add('is-invalid');
                messageEl.classList.add(`${prefix}__message--invalid`);
                messageEl.textContent = `Date must be on or after ${this.formatDate(min)}`;
                return false;
            }
        }

        // Check max date
        if (maxDate) {
            const max = new Date(maxDate);
            if (date > max) {
                visibleInput.classList.add('is-invalid');
                messageEl.classList.add(`${prefix}__message--invalid`);
                messageEl.textContent = `Date must be on or before ${this.formatDate(max)}`;
                return false;
            }
        }

        // Valid - show the actual selected date
        visibleInput.classList.add('is-valid');
        messageEl.classList.add(`${prefix}__message--valid`);
        
        // Determine format based on options
        let formatOpts = { year: 'numeric', month: 'long', day: 'numeric' };
        if (options.enableTime) {
            formatOpts = { ...formatOpts, hour: '2-digit', minute: '2-digit' };
        }
        if (options.noCalendar) {
            formatOpts = { hour: '2-digit', minute: '2-digit' };
            // For time only, just return the formatted time
             messageEl.textContent = date.toLocaleTimeString('en-US', formatOpts);
             return true;
        }
        
        messageEl.textContent = this.formatDate(date, formatOpts);
        return true;
    },

    validate: function(input, messageEl, options = {}) {
        const value = input.value;
        const { minDate, maxDate } = options;
        
        const visibleInput = input._flatpickr ? input._flatpickr.altInput : input;
        
        visibleInput.classList.remove('is-valid', 'is-invalid');
        visibleInput.classList.remove('is-valid', 'is-invalid');
        visibleInput.classList.remove('is-valid', 'is-invalid');
        const parentWrapper = visibleInput.closest('.field-date') || visibleInput.closest('.field-datetime') || visibleInput.closest('.field-time');
        let prefix = 'field-date';
        if (parentWrapper && parentWrapper.classList.contains('field-datetime')) prefix = 'field-datetime';
        if (parentWrapper && parentWrapper.classList.contains('field-time')) prefix = 'field-time';

        messageEl.classList.remove(`${prefix}__message--valid`, `${prefix}__message--invalid`);
        messageEl.textContent = '';

        if (!value) {
            return;
        }

        // Parse as local date
        const parts = value.split('-');
        let date;
        
        // Check if it looks like a time string (HH:MM or HH:MM:SS)
        if (value.includes(':') && !value.includes('-')) {
             const dummyDate = new Date();
             const timeParts = value.split(':');
             if (timeParts.length >= 2) {
                 dummyDate.setHours(parseInt(timeParts[0]), parseInt(timeParts[1]), 0, 0);
                 date = dummyDate;
             } else {
                 date = new Date("Invalid");
             }
        } else if (parts.length === 3) {
            date = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        } else {
            date = new Date(value);
        }
        
        if (isNaN(date.getTime())) {
            visibleInput.classList.add('is-invalid');
            messageEl.classList.add(`${prefix}__message--invalid`);
            messageEl.textContent = 'Please enter a valid date';
            return false;
        }

        return this.validateWithDate(input, messageEl, date, options);
    },

    formatDate: function(date, formatOptions) {
        // Default options if none info
        const options = formatOptions || { year: 'numeric', month: 'long', day: 'numeric' };
        
        // If the widget has enableTime, we should probably show time in validation messages by default if no format passed
        // However, here we just return locale string based on options
        return date.toLocaleDateString('en-US', options) + (options.hour ? ' ' + date.toLocaleTimeString('en-US', {hour: options.hour, minute: options.minute}) : '');
    },

    isValid: function(value) {
        const date = new Date(value);
        return !isNaN(date.getTime());
    }
};
