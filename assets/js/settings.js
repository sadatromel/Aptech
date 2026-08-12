/* settings.js - Extracted from admin/settings.php */

// Settings Management JavaScript
(function () {
    'use strict';

    const form = document.getElementById('settingsForm');
    const saveBtn = document.getElementById('saveBtn');
    const resetBtn = document.getElementById('resetBtn');
    const unsavedIndicator = document.getElementById('unsavedIndicator');
    let hasUnsavedChanges = false;
    let originalFormData = new FormData(form);

    // Navigation
    document.querySelectorAll('.settings-nav-item').forEach(item => {
        item.addEventListener('click', function (e) {
            e.preventDefault();
            const section = this.dataset.section;

            // Update nav
            document.querySelectorAll('.settings-nav-item').forEach(i => i.classList.remove('active'));
            this.classList.add('active');

            // Update sections
            document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
            document.getElementById('section-' + section).classList.add('active');
        });
    });

    // Change Detection
    form.addEventListener('input', function () {
        hasUnsavedChanges = true;
        unsavedIndicator.classList.add('show');
    });

    // Unsaved Changes Warning
    window.addEventListener('beforeunload', function (e) {
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // Save Settings
    saveBtn.addEventListener('click', async function () {
        if (!hasUnsavedChanges) {
            Swal.fire('No Changes', 'No changes to save', 'info');
            return;
        }

        // Validate form
        if (!validateForm()) {
            Swal.fire('Validation Error', 'Please fix the errors before saving', 'error');
            return;
        }

        saveBtn.classList.add('btn-loading');
        saveBtn.disabled = true;

        try {
            const formData = new FormData(form);
            // Updated endpoint to new MVC route
            const response = await fetch('index.php?page=admin/settings', {
                method: 'POST',
                body: formData
            });

            let result;
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                result = await response.json();
            } else {
                const text = await response.text();
                throw new Error("Server returned non-JSON response: " + text.substring(0, 100) + "...");
            }

            if (result.success) {
                // Update UI updates if necessary
                const logoUrl = formData.get('settings[logo_url]');
                if (logoUrl) {
                    // Update header logo if exists
                    const headerLogo = document.querySelector('.navbar-brand img');
                    if (headerLogo) headerLogo.src = logoUrl;
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Saved!',
                    text: result.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    // Optional: reload to reflect all changes
                    // location.reload();

                    // Remove unsaved changes indicator
                    hasUnsavedChanges = false;
                    unsavedIndicator.classList.remove('show');
                });

                // Update original form data
                originalFormData = new FormData(form);
            } else {
                throw new Error(result.error || 'Unknown error occurred');
            }
        } catch (error) {
            console.error('Save Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Failed to save settings'
            });
        } finally {
            saveBtn.classList.remove('btn-loading');
            saveBtn.disabled = false;
        }
    });

    // Reset Form
    resetBtn.addEventListener('click', function () {
        if (!hasUnsavedChanges) {
            return;
        }

        Swal.fire({
            title: 'Reset Changes?',
            text: 'All unsaved changes will be lost',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Reset',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                form.reset();
                hasUnsavedChanges = false;
                unsavedIndicator.classList.remove('show');
                location.reload();
            }
        });
    });

    // Form Validation
    function validateForm() {
        let isValid = true;

        form.querySelectorAll('[data-validate]').forEach(field => {
            const rules = field.dataset.validate.split(',');

            rules.forEach(rule => {
                if (rule === 'required' && !field.value.trim()) {
                    field.classList.add('is-invalid-custom');
                    isValid = false;
                } else if (rule === 'email' && field.value && !isValidEmail(field.value)) {
                    field.classList.add('is-invalid-custom');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid-custom');
                    field.classList.add('is-valid-custom');
                }
            });
        });

        return isValid;
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // Color Picker Sync
    const colorPicker = document.getElementById('primaryColor');
    const colorHex = document.getElementById('colorHex');
    const colorPreview = document.getElementById('colorPreview');

    if (colorPicker && colorHex) {
        colorPicker.addEventListener('input', function () {
            colorHex.value = this.value;
            colorPreview.style.backgroundColor = this.value;
        });

        colorHex.addEventListener('input', function () {
            if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                colorPicker.value = this.value;
                colorPreview.style.backgroundColor = this.value;
            }
        });
    }

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

})();

// Subsection Toggle
function toggleSubsection(header) {
    const subsection = header.closest('.subsection');
    subsection.classList.toggle('collapsed');
}

// File Upload Handlers
function removeLogo() {
    document.getElementById('logoUrl').value = '';
    document.getElementById('logoPreview').classList.remove('show');
}

function removeFavicon() {
    document.getElementById('faviconUrl').value = '';
    document.getElementById('faviconPreview').classList.remove('show');
}

// Advanced Features JavaScript

// Import/Export Settings
document.getElementById('exportSettingsBtn')?.addEventListener('click', function () {
    // Logic for export update is needed later on controller side
    window.location.href = 'modules/admin/settings_import_export.php?action=export';
});

document.getElementById('importSettingsBtn')?.addEventListener('click', function () {
    Swal.fire({
        title: 'Import Settings',
        html: `
            <input type="file" id="importFile" accept=".json" class="form-control">
            <div class="form-help mt-2">Select a JSON settings export file</div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Import',
        preConfirm: () => {
            const file = document.getElementById('importFile').files[0];
            if (!file) {
                Swal.showValidationMessage('Please select a file');
                return false;
            }
            return file;
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'import');
            formData.append('import_file', result.value);
            // CSRF token is in the form
            const csrf = document.querySelector('input[name="csrf_token"]');
            if (csrf) formData.append('csrf_token', csrf.value);

            try {
                // TODO: Implement this in controller
                const response = await fetch('modules/admin/settings_import_export.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    Swal.fire('Success!', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', data.error, 'error');
                }
            } catch (error) {
                Swal.fire('Error', error.message, 'error');
            }
        }
    });
});

// Theme Presets Logic
function applyTheme(themeName) {
    // Remove active class from all cards
    document.querySelectorAll('.theme-preset-card').forEach(card => card.classList.remove('active'));

    // Add active to selected
    const selectedCard = document.getElementById('theme-' + themeName);
    if (selectedCard) selectedCard.classList.add('active');

    // Update hidden input for persistence
    const hiddenInput = document.getElementById('activeThemePreset');
    if (hiddenInput) {
        hiddenInput.value = themeName;
        // Trigger generic change detection
        triggerUnsavedChanges();
    }

    // Preset definitions
    const themes = {
        'default': { primary: '#667eea', hover: '#5a67d8', gradient_start: '#667eea', gradient_end: '#764ba2' },
        'oceanic': { primary: '#2E3192', hover: '#1BFFFF', gradient_start: '#2E3192', gradient_end: '#1BFFFF' },
        'sunset': { primary: '#ff9a9e', hover: '#fecfef', gradient_start: '#ff9a9e', gradient_end: '#fecfef' },
        'forest': { primary: '#11998e', hover: '#38ef7d', gradient_start: '#11998e', gradient_end: '#38ef7d' },
        'midnight': { primary: '#232526', hover: '#414345', gradient_start: '#232526', gradient_end: '#414345' },
        'royal': { primary: '#834d9b', hover: '#d04ed6', gradient_start: '#834d9b', gradient_end: '#d04ed6' }
    };

    const theme = themes[themeName];
    if (theme) {
        // INSTANTLY Apply CSS Variables
        const root = document.documentElement;
        root.style.setProperty('--primary', theme.primary);
        // Calculate a darker/lighter variant if needed, or just use hover
        root.style.setProperty('--primary-dark', theme.hover);

        // Update Form Inputs
        const primaryInput = document.getElementById('primaryColor');
        const hoverInput = document.getElementById('hoverColor');

        if (primaryInput) {
            primaryInput.value = theme.primary;
            // Update text input sibling
            const textInput = primaryInput.parentElement.querySelector('input[type="text"]');
            if (textInput) textInput.value = theme.primary;
        }

        if (hoverInput) {
            hoverInput.value = theme.hover;
            const textInput = hoverInput.parentElement.querySelector('input[type="text"]');
            if (textInput) textInput.value = theme.hover;
        }
    }
}

function setMode(mode) {
    // Update hidden input
    const modeInput = document.getElementById('themeModeInput');
    if (modeInput) {
        modeInput.value = mode;
        triggerUnsavedChanges();
    }

    // Update buttons
    document.querySelectorAll('.mode-btn').forEach(btn => btn.classList.remove('active'));

    if (mode === 'light') {
        document.getElementById('btn-light').classList.add('active');
    } else if (mode === 'dark') {
        document.getElementById('btn-dark').classList.add('active');
    }
}

// Helper to manual trigger unsaved changes logic since programmatic events usually don't bubble
function triggerUnsavedChanges() {
    // We access the global hasUnsavedChanges variable by dispatching an event to the form
    // or by manually setting it if scope allows, but scoping is tricky here due to IIFE.
    // Simpler approach: Dispatch an 'input' event on a verified field.
    const form = document.getElementById('settingsForm');
    if (form) {
        form.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

// Add event listener for color inputs to update text fields if they exist
document.querySelectorAll('input[type="color"]').forEach(input => {
    input.addEventListener('input', function () {
        const textInput = this.parentElement.querySelector('input[type="text"]');
        if (textInput) textInput.value = this.value;
    });
});
