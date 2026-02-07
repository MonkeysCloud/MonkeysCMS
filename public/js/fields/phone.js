/**
 * Phone Widget Handler
 * Uses intl-tel-input for finding, validating, and formatting phone numbers.
 */
var CmsPhone = {
    instances: {},

    /**
     * Initialize the phone widget
     * @param {string} wrapperId - The ID of the wrapper element
     * @param {object} options - Configuration options
     */
    init: function(wrapperId, options = {}) {
        const wrapper = document.getElementById(wrapperId);
        if (!wrapper) return;

        const input = wrapper.querySelector('input[type="tel"]');
        const hiddenInput = wrapper.querySelector('input[type="hidden"]');
        const errorMsg = wrapper.querySelector('.field-phone__validation-msg');
        
        if (!input || !hiddenInput) return;

        // Default options
        const defaults = {
            utilsScript: "/vendor/intl-tel-input/js/utils.js",
            separateDialCode: true,
            initialCountry: "auto",
            dropdownContainer: document.body,
            geoIpLookup: function(callback) {
                // Use XHR instead of fetch for consistency
                const xhr = new XMLHttpRequest();
                xhr.open('GET', 'https://ipapi.co/json', true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                const data = JSON.parse(xhr.responseText);
                                callback(data.country_code);
                            } catch (e) {
                                callback("US");
                            }
                        } else {
                            callback("US");
                        }
                    }
                };
                xhr.send();
            },
            preferredCountries: ["us", "gb", "ca"],
        };

        // Merge options
        const config = { ...defaults, ...options };

        // Initialize intl-tel-input
        const iti = window.intlTelInput(input, config);
        this.instances[wrapperId] = iti;

        // Validation and Formatting Logic
        const validateAndFormat = () => {
            if (input.value.trim()) {
                if (iti.isValidNumber()) {
                    input.classList.remove('is-invalid');
                    input.classList.add('is-valid');
                    errorMsg.classList.remove('is-visible');
                    
                    // Update hidden input with full international number
                    hiddenInput.value = iti.getNumber();
                } else {
                    input.classList.add('is-invalid');
                    input.classList.remove('is-valid');
                    const errorCode = iti.getValidationError();
                    errorMsg.textContent = this.getErrorMessage(errorCode);
                    errorMsg.classList.add('is-visible');
                    
                    // Even if invalid, save what the user typed? 
                    // Or keep the last valid? Let's verify...
                    // Usually better to clear hidden if invalid to prevent saving bad data
                    // But in a CMS, maybe raw is better. Let's start with saving raw if invalid.
                    hiddenInput.value = input.value; 
                }
            } else {
                input.classList.remove('is-invalid', 'is-valid');
                errorMsg.classList.remove('is-visible');
                hiddenInput.value = '';
            }
        };

        // Event Listeners
        input.addEventListener('blur', validateAndFormat);
        input.addEventListener('change', validateAndFormat);
        input.addEventListener('keyup', () => {
             if (input.classList.contains('is-invalid')) {
                 validateAndFormat();
             }
        });

        // Initial validation if value exists
        if (input.value) {
            // Need to wait for utils script to load for formatting
            if (window.intlTelInputUtils) {
                validateAndFormat();
            } else {
                input.addEventListener("countrychange", validateAndFormat);
            }
        }
    },

    getErrorMessage: function(code) {
        const errorMap = [
            "Invalid number",
            "Invalid country code",
            "Too short",
            "Too long",
            "Invalid number",
        ];
        return errorMap[code] || "Invalid phone number";
    }
};

// Make it global
window.CmsPhone = CmsPhone;
