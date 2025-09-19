import './bootstrap';
// CSS is already included via @vite in the layout; avoid double-including here
// import '../css/app.css';

// Import Bootstrap JavaScript
import * as bootstrap from 'bootstrap';

// Import jQuery
import $ from 'jquery';
window.$ = window.jQuery = $;

// Import Font Awesome CSS
import '@fortawesome/fontawesome-free/css/all.css';

// Make bootstrap available globally
window.bootstrap = bootstrap;

// Initialize Bootstrap components
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});
