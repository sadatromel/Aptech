/**
 * Global Theme Manager - Aptech ERP System
 * Handles theme switching and persistence across all pages
 */

class ThemeManager {
    constructor() {
        this.storageKey = 'aptech-theme';
        this.darkClass = 'dark-theme';
        this.init();
    }

    /**
     * Initialize theme manager
     */
    init() {
        // Set initial theme on page load
        this.setInitialTheme();
        
        // Setup theme toggle listeners
        this.setupThemeToggle();
        
        // Listen for system theme changes
        this.setupSystemThemeListener();
    }

    /**
     * Set initial theme based on saved preference or system preference
     */
    setInitialTheme() {
        const savedTheme = this.getSavedTheme();
        const systemTheme = this.getSystemTheme();
        const initialTheme = savedTheme || systemTheme;
        
        this.applyTheme(initialTheme);
        this.updateThemeToggle(initialTheme);
    }

    /**
     * Get saved theme from localStorage
     */
    getSavedTheme() {
        try {
            return localStorage.getItem(this.storageKey);
        } catch (e) {
            console.warn('Unable to access localStorage:', e);
            return null;
        }
    }

    /**
     * Save theme preference to localStorage
     */
    saveTheme(theme) {
        try {
            localStorage.setItem(this.storageKey, theme);
        } catch (e) {
            console.warn('Unable to save theme to localStorage:', e);
        }
    }

    /**
     * Get system preferred theme
     */
    getSystemTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }

    /**
     * Apply theme to the document
     */
    applyTheme(theme) {
        const html = document.documentElement;
        
        // Always set data-theme attribute
        html.setAttribute('data-theme', theme);
        
        // Toggle dark class
        if (theme === 'dark') {
            html.classList.add(this.darkClass);
        } else {
            html.classList.remove(this.darkClass);
        }
        
        // Dispatch custom event for theme change
        this.dispatchThemeChangeEvent(theme);
    }

    /**
     * Toggle between light and dark themes
     */
    toggleTheme() {
        const currentTheme = this.getCurrentTheme();
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        this.applyTheme(newTheme);
        this.saveTheme(newTheme);
        this.updateThemeToggle(newTheme);
        
        return newTheme;
    }

    /**
     * Get current theme
     */
    getCurrentTheme() {
        const html = document.documentElement;
        const theme = html.getAttribute('data-theme');
        return theme === 'dark' ? 'dark' : 'light';
    }

    /**
     * Setup theme toggle button listeners
     */
    setupThemeToggle() {
        const themeToggles = document.querySelectorAll('[data-theme-toggle], #themeToggle');
        
        themeToggles.forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleTheme();
            });
        });
    }

    /**
     * Update theme toggle button UI
     */
    updateThemeToggle(theme) {
        const themeToggles = document.querySelectorAll('[data-theme-toggle], #themeToggle');
        
        themeToggles.forEach(toggle => {
            const icon = theme === 'dark' ? 'fa-sun' : 'fa-moon';
            const text = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
            
            // Update button content
            if (toggle.querySelector('i') && toggle.querySelector('span')) {
                toggle.innerHTML = `<i class="fas ${icon}"></i><span class="d-none d-md-inline">${text}</span>`;
            } else if (toggle.querySelector('i')) {
                toggle.querySelector('i').className = `fas ${icon}`;
            } else {
                toggle.innerHTML = `<i class="fas ${icon}"></i>`;
            }
            
            // Update button title/tooltip
            toggle.title = text;
            toggle.setAttribute('aria-label', text);
        });
    }

    /**
     * Setup system theme change listener
     */
    setupSystemThemeListener() {
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            
            mediaQuery.addEventListener('change', (e) => {
                // Only apply system theme if user hasn't explicitly set a preference
                if (!this.getSavedTheme()) {
                    const systemTheme = e.matches ? 'dark' : 'light';
                    this.applyTheme(systemTheme);
                    this.updateThemeToggle(systemTheme);
                }
            });
        }
    }

    /**
     * Show theme change notification
     */
    showThemeNotification(theme) {
        const message = `Switched to ${theme} mode`;
        
        // Try to use existing toast function if available
        if (typeof window.showToast === 'function') {
            window.showToast(message, 'success');
        } else if (typeof window.Dashboard?.showToast === 'function') {
            window.Dashboard.showToast(message, 'success');
        } else {
            // Fallback notification
            this.showFallbackNotification(message);
        }
    }

    /**
     * Fallback notification method
     */
    showFallbackNotification(message) {
        const notification = document.createElement('div');
        notification.className = 'theme-notification';
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--primary);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateY(0)';
        }, 100);
        
        // Remove after delay
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    /**
     * Dispatch custom event for theme change
     */
    dispatchThemeChangeEvent(theme) {
        const event = new CustomEvent('themechange', {
            detail: { theme: theme }
        });
        document.dispatchEvent(event);
    }

    /**
     * Get theme CSS variables
     */
    getThemeVariables() {
        const styles = getComputedStyle(document.documentElement);
        return {
            primary: styles.getPropertyValue('--primary').trim(),
            secondary: styles.getPropertyValue('--secondary').trim(),
            success: styles.getPropertyValue('--success').trim(),
            warning: styles.getPropertyValue('--warning').trim(),
            danger: styles.getPropertyValue('--danger').trim(),
            dark: styles.getPropertyValue('--dark').trim(),
            light: styles.getPropertyValue('--light').trim(),
            gray: styles.getPropertyValue('--gray').trim(),
            lightGray: styles.getPropertyValue('--light-gray').trim()
        };
    }

    /**
     * Reset theme to system preference
     */
    resetToSystemTheme() {
        localStorage.removeItem(this.storageKey);
        const systemTheme = this.getSystemTheme();
        this.applyTheme(systemTheme);
        this.updateThemeToggle(systemTheme);
    }

    /**
     * Set theme programmatically
     */
    setTheme(theme) {
        if (theme === 'dark' || theme === 'light') {
            this.applyTheme(theme);
            this.saveTheme(theme);
            this.updateThemeToggle(theme);
        }
    }
}

// Initialize theme manager when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.themeManager = new ThemeManager();
    
    // Make theme manager globally available
    window.ThemeManager = ThemeManager;
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ThemeManager;
}
