/**
 * Navbar JavaScript
 * Handles navbar functionality across all pages
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize navbar functionality
    initNavbar();
    
    // Handle theme switcher
    initThemeSwitcher();
    
    // Handle user dropdown
    initUserDropdown();
});

/**
 * Initialize navbar
 */
function initNavbar() {
    // Add scroll effect to navbar
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 50) {
            navbar.classList.add('navbar-scrolled');
        } else {
            navbar.classList.remove('navbar-scrolled');
        }
    });
    
    // Handle mobile menu collapse
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(e) {
        if (navbarCollapse.classList.contains('show')) {
            if (!navbarCollapse.contains(e.target) && !navbarToggler.contains(e.target)) {
                bootstrap.Collapse.getInstance(navbarCollapse).hide();
            }
        }
    });
    
    // Close mobile menu when clicking on a link
    const navLinks = navbarCollapse.querySelectorAll('.nav-link:not(.dropdown-toggle)');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                bootstrap.Collapse.getInstance(navbarCollapse).hide();
            }
        });
    });
}

/**
 * Initialize theme switcher
 */
function initThemeSwitcher() {
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    
    if (themeToggle) {
        // Load saved theme or default to light
        const savedTheme = localStorage.getItem('aptech-theme') || 'light';
        setTheme(savedTheme);
        
        // Update icon based on current theme
        updateThemeIcon();
        
        themeToggle.addEventListener('click', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            // Add switching class for smooth transition
            document.body.classList.add('theme-switching');
            
            // Set new theme
            setTheme(newTheme);
            
            // Update icon
            updateThemeIcon();
            
            // Save preference
            localStorage.setItem('aptech-theme', newTheme);
            
            // Add animation effect
            themeToggle.style.transform = 'rotate(360deg)';
            
            // Remove switching class after transition
            setTimeout(() => {
                document.body.classList.remove('theme-switching');
                themeToggle.style.transform = '';
            }, 300);
        });
    }
}

/**
 * Set theme
 * @param {string} theme - Theme name ('light' or 'dark')
 */
function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    
    // Update dark theme class
    if (theme === 'dark') {
        document.documentElement.classList.add('dark-theme', 'dark-mode');
        document.body.classList.add('dark-theme', 'dark-mode');
    } else {
        document.documentElement.classList.remove('dark-theme', 'dark-mode');
        document.body.classList.remove('dark-theme', 'dark-mode');
    }
}

/**
 * Update theme icon based on current theme
 */
function updateThemeIcon() {
    const themeIcon = document.getElementById('themeIcon');
    const currentTheme = document.documentElement.getAttribute('data-theme');
    
    if (themeIcon) {
        if (currentTheme === 'dark') {
            themeIcon.classList.remove('fa-moon');
            themeIcon.classList.add('fa-sun');
        } else {
            themeIcon.classList.remove('fa-sun');
            themeIcon.classList.add('fa-moon');
        }
    }
}

/**
 * Initialize user dropdown
 */
function initUserDropdown() {
    const userDropdown = document.getElementById('userDropdown');
    
    if (userDropdown) {
        // Add hover effect for desktop
        if (window.innerWidth >= 992) {
            const dropdown = new bootstrap.Dropdown(userDropdown);
            
            userDropdown.addEventListener('mouseenter', function() {
                dropdown.show();
            });
            
            const dropdownMenu = userDropdown.nextElementSibling;
            dropdownMenu.addEventListener('mouseleave', function() {
                dropdown.hide();
            });
            
            userDropdown.addEventListener('mouseleave', function() {
                setTimeout(() => {
                    if (!dropdownMenu.matches(':hover')) {
                        dropdown.hide();
                    }
                }, 100);
            });
        }
    }
}

/**
 * Show notification in navbar
 * @param {string} message - Message to display
 * @param {string} type - Type of notification (success, error, warning, info)
 */
function showNavbarNotification(message, type = 'info') {
    const navbar = document.querySelector('.navbar');
    const notification = document.createElement('div');
    notification.className = `navbar-notification alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 70px; right: 20px; z-index: 1050; min-width: 300px; animation: slideInRight 0.3s ease;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 150);
    }, 5000);
}

/**
 * Update navbar user info
 * @param {Object} userInfo - User information object
 */
function updateNavbarUser(userInfo) {
    const userDropdown = document.getElementById('userDropdown');
    if (userDropdown) {
        const userSpan = userDropdown.querySelector('span');
        if (userSpan) {
            userSpan.textContent = userInfo.full_name || userInfo.username || 'User';
        }
    }
}

// Export functions for global access
window.Navbar = {
    showNotification: showNavbarNotification,
    updateNavbarUser: updateNavbarUser,
    updateThemeIcon: updateThemeIcon,
    setTheme: setTheme
};
