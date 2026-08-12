/**
 * Sidebar JavaScript
 * Handles sidebar menu functionality using Bootstrap 5 events
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar functionality
    initSidebar();
    
    // Set active menu item based on current page
    setActiveMenuItem();
});

/**
 * Initialize sidebar
 */
function initSidebar() {
    // Handle sidebar toggle on mobile
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
        });
    }

    // Initialize Bootstrap collapses
    const collapseElements = document.querySelectorAll('.sidebar .collapse');
    collapseElements.forEach(collapseEl => {
        // Add event listeners for Bootstrap collapse events
        collapseEl.addEventListener('show.bs.collapse', function (e) {
            // Stop propagation to prevent parent collapses from reacting if nested
            e.stopPropagation();
            
            const submenuId = this.id;
            const toggleLink = document.querySelector(`a[data-bs-target="#${submenuId}"]`);
            
            if (toggleLink) {
                toggleLink.classList.add('active');
                toggleLink.setAttribute('aria-expanded', 'true');
                
                // Rotate icon
                const icon = toggleLink.querySelector('.dropdown-icon');
                if (icon) {
                    icon.style.transform = 'rotate(180deg)';
                }
            }

            // Optional: Accordion behavior - close other submenus at the same level
            const parent = this.parentElement; // li.nav-item.has-submenu
            if (parent) {
                const siblings = parent.parentElement.querySelectorAll('.has-submenu .submenu.show');
                siblings.forEach(sibling => {
                    if (sibling !== this) {
                        const bsCollapse = bootstrap.Collapse.getInstance(sibling);
                        if (bsCollapse) {
                            bsCollapse.hide();
                        } else {
                            // Fallback if instance not found
                            sibling.classList.remove('show');
                        }
                    }
                });
            }
        });

        collapseEl.addEventListener('hide.bs.collapse', function (e) {
            e.stopPropagation();
            
            const submenuId = this.id;
            const toggleLink = document.querySelector(`a[data-bs-target="#${submenuId}"]`);
            
            if (toggleLink) {
                // Only remove active if it's not the current page's parent
                // But for the toggle link itself (the header of the submenu), we usually want it inactive if closed
                toggleLink.classList.remove('active');
                toggleLink.setAttribute('aria-expanded', 'false');
                
                // Reset icon
                const icon = toggleLink.querySelector('.dropdown-icon');
                if (icon) {
                    icon.style.transform = 'rotate(0)';
                }
            }
        });
    });
}

/**
 * Set active menu item based on current page
 */
function setActiveMenuItem() {
    const currentUrl = window.location.href;
    const searchParams = new URLSearchParams(window.location.search);
    const page = searchParams.get('page');
    
    // 1. Find the specific link
    let activeLink = null;
    
    // Try matching by exact page parameter (most accurate)
    if (page) {
        activeLink = document.querySelector(`.sidebar .nav-link[href*="page=${page}"]`);
    }
    
    // Fallback: Try matching href
    if (!activeLink) {
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            if (link.href === currentUrl || currentUrl.startsWith(link.href)) {
                activeLink = link;
            }
        });
    }

    // 2. Activate it
    if (activeLink) {
        activeLink.classList.add('active');
        
        // 3. Expand parent submenu(s)
        let parent = activeLink.closest('.submenu');
        while (parent) {
            const bsCollapse = new bootstrap.Collapse(parent, { toggle: false });
            bsCollapse.show();
            
            // Highlight the parent toggle
            const parentId = parent.id;
            const parentToggle = document.querySelector(`a[data-bs-target="#${parentId}"]`);
            if (parentToggle) {
                parentToggle.classList.add('active');
                parentToggle.setAttribute('aria-expanded', 'true');
            }
            
            // Go up to next level
            parent = parent.parentElement.closest('.submenu');
        }
    }
}

// Global export
window.Sidebar = {
    initSidebar,
    setActiveMenuItem
};
