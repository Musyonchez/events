/**
 * Component Loader Module
 * 
 * This module handles dynamic loading of HTML components into pages.
 * Currently focused on navbar loading, but designed to be extensible
 * for other reusable components like footers, modals, etc.
 * 
 * Key Features:
 * - Dynamic HTML component loading via fetch API
 * - Custom event dispatching for component lifecycle
 * - Error handling for missing components or network failures
 * - DOM content loaded event coordination
 * 
 * Architecture Benefits:
 * - Single source of truth for navbar HTML
 * - Consistent navigation across all pages
 * - Easier maintenance and updates
 * - Reduced code duplication
 * 
 * Component Loading Flow:
 * 1. Wait for DOM content to be loaded
 * 2. Fetch component HTML from server
 * 3. Inject HTML into designated placeholder
 * 4. Dispatch custom event for component-specific initialization
 * 5. Other modules listen for custom event and initialize interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    /**
     * Navbar component loader
     * 
     * Loads the navigation bar HTML component and injects it into the page.
     * After successful loading, dispatches a 'navbarLoaded' event that other
     * modules (like main.js) listen for to initialize navbar functionality.
     * 
     * Component Path Strategy:
     * Uses absolute path '/components/navbar.html' which works from any
     * page depth due to server configuration. This avoids the need for
     * relative path calculation based on current page location.
     * 
     * Error Handling:
     * - Network errors: Logged to console, navbar remains empty
     * - Missing placeholder: Logged error, component loading fails gracefully
     * - Server errors: HTTP status checked and error thrown
     * 
     * Custom Event:
     * Dispatches 'navbarLoaded' event on document after successful injection.
     * This allows other modules to wait for navbar DOM availability before
     * setting up event listeners and dynamic content.
     */
    
    // Component path - uses absolute path for server compatibility
    const navbarPath = '/components/navbar.html';

    fetch(navbarPath)
        .then(response => {
            // Check for HTTP errors
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(data => {
            // Find navbar placeholder element
            const navbarPlaceholder = document.getElementById('navbar-placeholder');
            if (navbarPlaceholder) {
                // Inject component HTML into placeholder
                navbarPlaceholder.innerHTML = data;
                
                // Dispatch custom event to signal navbar is ready
                // This event is listened for by main.js and other modules
                document.dispatchEvent(new Event('navbarLoaded'));
            } else {
                console.error("Navbar placeholder not found! Ensure #navbar-placeholder exists in HTML.");
            }
        })
        .catch(error => {
            console.error('Error loading navbar component:', error);
            // Component loading failure is logged but doesn't break the page
        });
});
