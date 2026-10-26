# Theme System - Quick Reference

## Basic Page Template

```php
<?php
session_start();
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../core/legacy_helpers.php';

$page_title = 'Your Page Title - RMG PRO ERP';
require_once __DIR__ . '/../includes/page_header.php';
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <!-- Your content here -->
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/page_footer.php'; ?>
```

## JavaScript Theme Control

```javascript
// Set theme
window.themeManager.setTheme('dark');  // or 'light'

// Toggle theme
window.themeManager.toggleTheme();

// Get current theme
const theme = window.themeManager.getCurrentTheme();

// Listen for theme changes
document.addEventListener('themechange', (e) => {
    console.log('Theme:', e.detail.theme);
});
```

## CSS Theme Variables

```css
/* Use these variables in your CSS */
var(--primary)      /* Primary color */
var(--secondary)    /* Secondary color */
var(--success)      /* Success color */
var(--danger)       /* Danger color */
var(--warning)      /* Warning color */
var(--light)        /* Light background */
var(--dark)         /* Dark text/background */
var(--gray)         /* Gray text */
var(--light-gray)   /* Light gray borders */
```

## Dark Mode Specific Styles

```css
/* Light mode (default) */
.my-element {
    background: white;
    color: black;
}

/* Dark mode override */
[data-theme="dark"] .my-element {
    background: #1a1d23;
    color: #e9ecef;
}
```

## File Locations

- Navigation Bar: `includes/header.php`
- Sidebar: `includes/sidebar.php`
- Theme Switcher: `includes/theme_switcher.php`
- Theme CSS: `assets/css/theme.css`
- Theme JS: `assets/js/theme.js`
- Page Header: `includes/page_header.php`
- Page Footer: `includes/page_footer.php`
- Demo Page: `core/theme_demo.php`

## Access Demo Page

Navigate to: `index.php?page=theme_demo` (after adding route to index.php)
