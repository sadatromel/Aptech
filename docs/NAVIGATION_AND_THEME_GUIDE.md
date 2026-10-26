# Navigation Bar and Theme System - Developer Guide

## Overview

The RMG PRO ERP system includes a comprehensive navigation bar and dark/light theme switching system that works consistently across all pages. This guide explains how to properly integrate these features into your pages.

## Quick Start

### Method 1: Using Page Layout Components (Recommended)

This is the easiest and most consistent way to add navigation and theme support to your pages:

```php
<?php
session_start();
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../core/legacy_helpers.php';

// Set page title
$page_title = 'Your Page Title - RMG PRO ERP';

// Include page header (HTML head with theme initialization)
require_once __DIR__ . '/../includes/page_header.php';
?>

<!-- Include Navigation Bar -->
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Include Sidebar -->
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <h1>Your Page Content</h1>
            <!-- Your content here -->
        </main>
    </div>
</div>

<?php
// Include page footer (closing scripts and tags)
require_once __DIR__ . '/../includes/page_footer.php';
?>
```

### Method 2: Manual Integration

If you need more control over the HTML structure:

```php
<?php
session_start();
require_once __DIR__ . '/../config/paths.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Page Title</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Theme CSS -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/theme.css">
    
    <!-- Theme initialization (prevents flash) -->
    <script>
    (function() {
        const savedTheme = localStorage.getItem('aptech-theme');
        const systemTheme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        const theme = savedTheme || systemTheme;
        
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.documentElement.classList.add('dark-theme');
        }
    })();
    </script>
</head>
<body>

<!-- Include Navigation Bar -->
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<!-- Your content -->

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= ASSETS_URL ?>/js/theme.js"></script>
</body>
</html>
```

## Components

### 1. Navigation Bar (`includes/header.php`)

The navigation bar includes:
- **Brand Logo**: Links to dashboard
- **Quick Access Menu**: Dropdown with frequently used pages
- **Theme Switcher**: Toggle between light and dark modes
- **User Menu**: Profile and logout options

**Features:**
- Responsive design (mobile-friendly)
- Automatic user detection
- Dynamic base URL handling
- Bootstrap 5 dropdowns

### 2. Sidebar (`includes/sidebar.php`)

The sidebar provides:
- Hierarchical navigation menu
- Module-based organization
- Collapsible submenus
- Active state highlighting

### 3. Theme System

#### Theme CSS (`assets/css/theme.css`)

Provides CSS variables and styles for both light and dark themes:

```css
/* Light theme (default) */
:root {
    --primary: #4361ee;
    --light: #f8f9fa;
    --dark: #212529;
    /* ... more variables */
}

/* Dark theme */
[data-theme="dark"] {
    --primary: #5a7fd8;
    --light: #1a1d23;
    --dark: #e9ecef;
    /* ... more variables */
}
```

#### Theme JavaScript (`assets/js/theme.js`)

The `ThemeManager` class handles:
- Theme initialization
- Theme switching
- LocalStorage persistence
- System preference detection
- Theme change events

**Available Methods:**

```javascript
// Access the theme manager
window.themeManager

// Set theme programmatically
window.themeManager.setTheme('dark');  // or 'light'

// Toggle theme
window.themeManager.toggleTheme();

// Reset to system preference
window.themeManager.resetToSystemTheme();

// Get current theme
const currentTheme = window.themeManager.getCurrentTheme();

// Listen for theme changes
document.addEventListener('themechange', function(e) {
    console.log('Theme changed to:', e.detail.theme);
});
```

#### Theme Switcher (`includes/theme_switcher.php`)

A reusable button component that can be included anywhere:

```php
<?php include_once __DIR__ . '/includes/theme_switcher.php'; ?>
```

## Styling Your Components

### Using Theme Variables

Always use CSS variables for colors to ensure proper theme support:

```css
/* Good - Uses theme variables */
.my-component {
    background: var(--light);
    color: var(--dark);
    border: 1px solid var(--light-gray);
}

/* Bad - Hard-coded colors */
.my-component {
    background: #f8f9fa;
    color: #212529;
}
```

### Dark Mode Specific Styles

Add dark mode overrides when needed:

```css
.my-component {
    background: white;
    color: black;
}

[data-theme="dark"] .my-component {
    background: #1a1d23;
    color: #e9ecef;
}
```

### Pre-styled Components

The theme system includes styles for:
- Cards
- Tables
- Forms
- Buttons
- Modals
- Alerts
- Dropdowns
- Sidebar
- Navbar

## Best Practices

### 1. Always Include Theme Initialization

Place this script in the `<head>` section before any visible content to prevent flash of unstyled content:

```html
<script>
(function() {
    const savedTheme = localStorage.getItem('aptech-theme');
    const systemTheme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    const theme = savedTheme || systemTheme;
    
    if (theme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        document.documentElement.classList.add('dark-theme');
    }
})();
</script>
```

### 2. Load CSS in Correct Order

```html
<!-- 1. Bootstrap -->
<link href="bootstrap.min.css" rel="stylesheet">

<!-- 2. Font Awesome -->
<link href="font-awesome.min.css" rel="stylesheet">

<!-- 3. Theme CSS -->
<link href="assets/css/theme.css" rel="stylesheet">

<!-- 4. Your custom CSS -->
<link href="your-custom.css" rel="stylesheet">
```

### 3. Load JavaScript in Correct Order

```html
<!-- 1. Bootstrap (includes Popper) -->
<script src="bootstrap.bundle.min.js"></script>

<!-- 2. jQuery (if needed) -->
<script src="jquery.min.js"></script>

<!-- 3. Theme JavaScript -->
<script src="assets/js/theme.js"></script>

<!-- 4. Your custom JavaScript -->
<script src="your-custom.js"></script>
```

### 4. Test Both Themes

Always test your pages in both light and dark modes:

```javascript
// Quick testing in browser console
window.themeManager.setTheme('dark');
window.themeManager.setTheme('light');
```

## Common Issues and Solutions

### Issue: Theme doesn't persist across pages

**Solution:** Ensure `theme.js` is loaded on every page and the theme initialization script is in the `<head>`.

### Issue: Flash of unstyled content on page load

**Solution:** Add the theme initialization script in the `<head>` section before any visible content.

### Issue: Custom components don't respect theme

**Solution:** Use CSS variables instead of hard-coded colors:

```css
/* Instead of */
background: #ffffff;

/* Use */
background: var(--light);
```

### Issue: Dropdown menus not working

**Solution:** Ensure Bootstrap JavaScript is loaded before theme.js:

```html
<script src="bootstrap.bundle.min.js"></script>
<script src="assets/js/theme.js"></script>
```

## File Structure

```
Aptech/
├── includes/
│   ├── header.php           # Navigation bar
│   ├── sidebar.php          # Sidebar navigation
│   ├── theme_switcher.php   # Theme toggle button
│   ├── page_header.php      # HTML head wrapper
│   └── page_footer.php      # Scripts and closing tags
├── assets/
│   ├── css/
│   │   └── theme.css        # Theme styles
│   └── js/
│       └── theme.js         # Theme manager
└── core/
    └── theme_demo.php       # Example implementation
```

## Example Pages

### Simple Page with Navigation and Theme

See `core/theme_demo.php` for a complete working example.

### Page Without Sidebar

```php
<?php
$page_title = 'Simple Page';
require_once __DIR__ . '/../includes/page_header.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <h1>Page Content</h1>
    <!-- Your content -->
</div>

<?php require_once __DIR__ . '/../includes/page_footer.php'; ?>
```

## Customization

### Adding Items to Quick Access Menu

Edit `includes/header.php` and add items to the Quick Access dropdown:

```php
<li><a class="dropdown-item" href="<?= $app_base_url ?>/index.php?page=your_page">
    <i class="fas fa-your-icon me-2"></i>Your Page</a></li>
```

### Customizing Theme Colors

Edit `assets/css/theme.css` and modify the CSS variables:

```css
:root {
    --primary: #your-color;
    --secondary: #your-color;
}
```

## Support

For issues or questions, refer to:
- `core/theme_demo.php` - Working example
- `assets/js/theme.js` - Theme manager source code
- `assets/css/theme.css` - Theme styles

## Version History

- **v1.0** (2025-01-08): Initial implementation with navigation bar and theme system
