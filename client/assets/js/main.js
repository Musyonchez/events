/**
 * Main Application Controller
 * 
 * This module serves as the primary application entry point and global state manager.
 * It handles navbar initialization, authentication state management, and homepage
 * content loading. The module is responsible for coordinating between different
 * UI components and ensuring proper authentication state across the application.
 * 
 * Dependencies:
 * - http.js: API communication and error handling
 * - auth.js: Authentication state management
 * 
 * Key Features:
 * - Dynamic navbar link path resolution based on current page location
 * - Authentication state UI updates (login/logout states)
 * - Homepage content loading (featured events, clubs preview)
 * - Mobile navigation menu handling
 * - User dropdown menu functionality
 * - Automatic token refresh on expired access tokens
 */

import { request, AccessTokenExpiredError } from './http.js';
import { isAuthenticated, getCurrentUser, logout, refreshToken } from './auth.js';

/**
 * Application initialization handler
 * 
 * This event listener waits for the navbar to be loaded before initializing
 * the application. It ensures that all navbar elements are available in the DOM
 * before attempting to set up event listeners and authentication state.
 * 
 * Execution order:
 * 1. Setup navbar event listeners (mobile menu, user dropdown, logout)
 * 2. Check and update authentication state in UI
 * 3. Set correct navigation links based on current page location
 * 4. Load homepage-specific content if on landing page
 */
document.addEventListener('navbarLoaded', function() {
    setupNavbarEventListeners();
    checkAuthState();
    setNavbarLinks();

    // Homepage-specific content loading
    // Only execute if the required DOM elements exist on the current page
    if (document.getElementById('featured-events')) {
        loadFeaturedEvents();
    }
    // Prevent clubs loading on dedicated clubs page to avoid duplication
    if (document.getElementById('clubs-grid') && !window.location.pathname.includes('/clubs.html')) {
        loadClubs();
    }
});

/**
 * Dynamic navigation link path resolver
 * 
 * This function resolves the correct relative paths for navbar links based on
 * the current page location. It handles three directory structures:
 * - Root level (index.html)
 * - Pages level (pages/*.html)  
 * - Admin level (pages/admin/*.html)
 * 
 * The navbar component uses data attributes to specify path types:
 * - data-root-path: Links to root-level resources (assets, images)
 * - data-pages-path: Links to pages directory files
 * - data-admin-path: Links to admin directory files
 * 
 * Path resolution logic:
 * - Root level: Relative paths start with './'
 * - Pages level: Relative paths go up one level '../'
 * - Admin level: Relative paths go up two levels '../../'
 * 
 * @example
 * // From index.html: ./pages/events.html
 * // From pages/events.html: ./events.html  
 * // From pages/admin/dashboard.html: ../events.html
 */
function setNavbarLinks() {
    // Determine current page location context
    const isRoot = window.location.pathname.endsWith('/index.html') || window.location.pathname.endsWith('/');
    const isAdmin = window.location.pathname.includes('/admin/');
    const links = document.querySelectorAll('#navbar-placeholder a');

    links.forEach(link => {
        // Handle root-level resources (assets, images, etc.)
        if (link.dataset.rootPath) {
            if (isRoot) {
                link.href = `./${link.dataset.rootPath}`;
            } else if (isAdmin) {
                link.href = `../../${link.dataset.rootPath}`;
            } else {
                link.href = `../${link.dataset.rootPath}`;
            }
        } 
        // Handle pages directory files
        else if (link.dataset.pagesPath) {
            if (isRoot) {
                link.href = `./pages/${link.dataset.pagesPath}`;
            } else if (isAdmin) {
                link.href = `../${link.dataset.pagesPath}`;
            } else {
                link.href = `./${link.dataset.pagesPath}`;
            }
        } 
        // Handle admin directory files
        else if (link.dataset.adminPath) {
            if (isRoot) {
                link.href = `./pages/${link.dataset.adminPath}`;
            } else if (isAdmin) {
                // Extract filename from admin path for same-directory navigation
                link.href = `./${link.dataset.adminPath.split('/')[1]}`;
            } else {
                link.href = `./${link.dataset.adminPath}`;
            }
        }
    });
}

/**
 * Navbar interaction event handlers setup
 * 
 * Initializes all interactive elements in the navigation bar including:
 * - Mobile hamburger menu toggle
 * - User profile dropdown menu
 * - Logout functionality for both desktop and mobile
 * - Click-outside-to-close behavior for dropdowns
 * 
 * This function is called after the navbar HTML is loaded to ensure
 * all DOM elements are available for event listener attachment.
 * 
 * UI Elements handled:
 * - #mobile-menu-button: Hamburger menu for mobile navigation
 * - #user-menu-button: User avatar/name button for profile dropdown
 * - #logout-btn: Desktop logout button
 * - #mobile-logout-btn: Mobile logout button
 * 
 * Accessibility considerations:
 * - Uses click events rather than hover for better mobile experience
 * - Implements click-outside-to-close for better UX
 * - Provides both desktop and mobile logout options
 */
function setupNavbarEventListeners() {
    // Get references to navbar interactive elements
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const userMenuButton = document.getElementById('user-menu-button');
    const userDropdown = document.getElementById('user-dropdown');
    const logoutBtn = document.getElementById('logout-btn');
    const mobileLogoutBtn = document.getElementById('mobile-logout-btn');

    // Mobile hamburger menu toggle
    // Toggles visibility of mobile navigation menu
    if (mobileMenuButton) {
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    }

    // User profile dropdown toggle
    // Shows/hides user menu with profile options
    if (userMenuButton) {
        userMenuButton.addEventListener('click', () => {
            userDropdown.classList.toggle('hidden');
        });
    }

    // Logout functionality for desktop interface
    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            logout(); // Calls auth.js logout function
        });
    }
    
    // Logout functionality for mobile interface
    if (mobileLogoutBtn) {
        mobileLogoutBtn.addEventListener('click', () => {
            logout(); // Calls auth.js logout function
        });
    }

    // Global click handler to close dropdowns when clicking outside
    // Improves UX by auto-closing menus when user clicks elsewhere
    document.addEventListener('click', function(e) {
        if (userDropdown && !e.target.closest('#user-menu')) {
            userDropdown.classList.add('hidden');
        }
    });
}

/**
 * Authentication state UI synchronization
 * 
 * Updates the navbar UI based on the current user's authentication status.
 * Handles the display/hiding of login buttons vs user menu, populates user
 * information, and manages role-based access control for admin features.
 * 
 * Authentication state changes:
 * - Logged out: Shows login/register buttons, hides user menu
 * - Logged in: Shows user menu with profile info, hides auth buttons
 * - Admin users: Additional admin dashboard link visibility
 * 
 * User data populated:
 * - User first name in dropdown menu
 * - Profile image (custom or default avatar)
 * - Role-based admin link visibility
 * - Both desktop and mobile interface updates
 * 
 * Avatar path resolution:
 * Uses the same dynamic path resolution as setNavbarLinks() to ensure
 * correct relative paths to default avatar based on current page location.
 */
function checkAuthState() {
    // Get navbar UI element references
    const authButtons = document.getElementById('auth-buttons');
    const userMenu = document.getElementById('user-menu');
    const mobileAuthButtons = document.getElementById('mobile-auth-buttons');
    const mobileUserMenu = document.getElementById('mobile-user-menu');

    if (isAuthenticated()) {
        // User is authenticated - show user menu, hide auth buttons
        authButtons.classList.add('hidden');
        userMenu.classList.remove('hidden');
        if (mobileAuthButtons) mobileAuthButtons.classList.add('hidden');
        if (mobileUserMenu) mobileUserMenu.classList.remove('hidden');

        // Populate user information from JWT token
        const user = getCurrentUser();
        if (user) {
            // Update user name display in dropdown
            const userNameElement = document.getElementById('user-name');
            if (userNameElement) userNameElement.textContent = user.first_name;
            
            // Role-based access control for admin dashboard link
            const adminDashboardLink = document.getElementById('admin-dashboard-link');
            const mobileAdminDashboardLink = document.getElementById('mobile-admin-dashboard-link');
            if (user.role === 'admin') {
                // Show admin dashboard link for admin users
                if (adminDashboardLink) adminDashboardLink.classList.remove('hidden');
                if (mobileAdminDashboardLink) mobileAdminDashboardLink.classList.remove('hidden');
            } else {
                // Hide admin dashboard link for non-admin users
                if (adminDashboardLink) adminDashboardLink.classList.add('hidden');
                if (mobileAdminDashboardLink) mobileAdminDashboardLink.classList.add('hidden');
            }
            
            // Desktop avatar setup
            const avatar = document.getElementById('user-avatar');
            if (avatar) {
                if (user.profile_image) {
                    // Use custom profile image if available
                    avatar.src = user.profile_image;
                } else {
                    // Use default avatar with correct relative path
                    const isRoot = window.location.pathname.endsWith('/index.html') || window.location.pathname.endsWith('/');
                    const isAdmin = window.location.pathname.includes('/admin/');
                    if (isRoot) {
                        avatar.src = './assets/images/avatar.png';
                    } else if (isAdmin) {
                        avatar.src = '../../assets/images/avatar.png';
                    } else {
                        avatar.src = '../assets/images/avatar.png';
                    }
                }
            }

            // Mobile interface user info updates
            const mobileUserNameElement = document.getElementById('mobile-user-name');
            if (mobileUserNameElement) mobileUserNameElement.textContent = user.first_name;
            
            // Mobile avatar setup (same logic as desktop)
            const mobileAvatar = document.getElementById('mobile-user-avatar');
            if (mobileAvatar) {
                if (user.profile_image) {
                    mobileAvatar.src = user.profile_image;
                } else {
                    const isRoot = window.location.pathname.endsWith('/index.html') || window.location.pathname.endsWith('/');
                    const isAdmin = window.location.pathname.includes('/admin/');
                    if (isRoot) {
                        mobileAvatar.src = './assets/images/avatar.png';
                    } else if (isAdmin) {
                        mobileAvatar.src = '../../assets/images/avatar.png';
                    } else {
                        mobileAvatar.src = '../assets/images/avatar.png';
                    }
                }
            }
        }
    } else {
        // User is not authenticated - show auth buttons, hide user menu
        authButtons.classList.remove('hidden');
        userMenu.classList.add('hidden');
        if (mobileAuthButtons) mobileAuthButtons.classList.remove('hidden');
        if (mobileUserMenu) mobileUserMenu.classList.add('hidden');
    }
}

/**
 * Homepage featured events loader
 * 
 * Fetches and displays a limited set of featured events on the homepage.
 * Implements automatic token refresh for expired access tokens and handles
 * MongoDB date format conversion for proper display.
 * 
 * API Endpoint: /events/index.php?action=list&status=featured&limit=3
 * 
 * Features:
 * - Automatic token refresh on 401 errors
 * - MongoDB date format handling ($date.$numberLong)
 * - Responsive card layout with hover effects
 * - Placeholder image fallback for missing banners
 * - Error state handling with user-friendly messages
 * 
 * Card Content:
 * - Event banner image (with fallback to placeholder)
 * - Formatted event date (full format with weekday)
 * - Event title and location
 * - Click-through link to event details page
 * 
 * @throws {Error} Network or authentication errors are caught and displayed
 */
async function loadFeaturedEvents() {
    const featuredEventsContainer = document.getElementById('featured-events');
    try {
        // API request with automatic token refresh handling
        const eventsResponse = await (async () => {
            try {
                return await request('/events/index.php?action=list&status=featured&limit=3', 'GET');
            } catch (error) {
                // Handle expired access token with automatic refresh
                if (error instanceof AccessTokenExpiredError) {
                    console.log('Access token expired for featured events. Attempting to refresh...');
                    await refreshToken();
                    console.log('Token refreshed. Retrying featured events request...');
                    return await request('/events/index.php?action=list&status=featured&limit=3', 'GET');
                }
                throw error;
            }
        })();
        
        // Extract events array from API response
        const events = eventsResponse.data.events || [];
        
        // Clear any loading placeholders or previous content
        featuredEventsContainer.innerHTML = '';

        // Handle empty results state
        if (events.length === 0) {
            featuredEventsContainer.innerHTML = '<p class="text-gray-600 col-span-full text-center">No featured events available at the moment.</p>';
            return;
        }

        // Generate and insert event cards
        events.forEach(event => {
            const eventCard = `
                <div class="bg-white rounded-lg shadow-lg overflow-hidden transform hover:-translate-y-2 transition duration-300">
                    <a href="./pages/event-details.html?id=${event._id.$oid}">
                        <img src="${event.banner_image || 'https://placehold.co/400x200'}" alt="${event.title}" class="w-full h-48 object-cover">
                        <div class="p-6">
                            <p class="text-sm text-gray-500 mb-1">${new Date(parseInt(event.event_date.$date.$numberLong)).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">${event.title}</h3>
                            <p class="text-gray-600">${event.location}</p>
                        </div>
                    </a>
                </div>
            `;
            featuredEventsContainer.innerHTML += eventCard;
        });
    } catch (error) {
        // Display user-friendly error message on failure
        featuredEventsContainer.innerHTML = '<p class="text-red-500 col-span-full text-center">Could not load featured events.</p>';
        console.error('Error loading featured events:', error);
    }
}

/**
 * Homepage clubs preview loader
 * 
 * Fetches and displays a limited preview of clubs on the homepage.
 * Similar to loadFeaturedEvents but with different UI styling optimized
 * for club presentation (circular logos, compact cards).
 * 
 * API Endpoint: /clubs/index.php?action=list&limit=4
 * 
 * Features:
 * - Automatic token refresh on authentication errors
 * - Circular club logo display with fallback placeholder
 * - Hover scale animation for interactive feedback
 * - Compact card layout suitable for homepage preview
 * - Error state handling with graceful degradation
 * 
 * Card Content:
 * - Circular club logo (16x16 with fallback to 100x100 placeholder)
 * - Club name as main heading
 * - Club category as subtitle
 * - Click-through link to full club details page
 * 
 * Note: This function is only called on homepage (index.html) to avoid
 * duplication with the dedicated clubs page functionality.
 * 
 * @throws {Error} Network or authentication errors are caught and displayed
 */
async function loadClubs() {
    const clubsGrid = document.getElementById('clubs-grid');
    try {
        // API request with automatic token refresh handling
        const clubsResponse = await (async () => {
            try {
                return await request('/clubs/index.php?action=list&limit=4', 'GET');
            } catch (error) {
                // Handle expired access token with automatic refresh
                if (error instanceof AccessTokenExpiredError) {
                    console.log('Access token expired for clubs. Attempting to refresh...');
                    await refreshToken();
                    console.log('Token refreshed. Retrying clubs request...');
                    return await request('/clubs/index.php?action=list&limit=4', 'GET');
                }
                throw error;
            }
        })();
        
        // Extract clubs array and metadata from API response
        const { clubs, total_clubs } = clubsResponse;
        
        // Clear any loading placeholders or previous content
        clubsGrid.innerHTML = '';

        // Handle empty results state
        if (clubs.length === 0) {
            clubsGrid.innerHTML = '<p class="text-gray-600 col-span-full text-center">No clubs found.</p>';
            return;
        }

        // Generate and insert club cards
        clubs.forEach(club => {
            const clubCard = `
                <div class="bg-white rounded-lg shadow-md p-6 text-center transform hover:scale-105 transition duration-300">
                    <a href="./pages/club-details.html?id=${club._id.$oid}">
                        <img src="${club.logo || 'https://placehold.co/100'}" alt="${club.name}" class="w-16 h-16 rounded-full mx-auto mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">${club.name}</h3>
                        <p class="text-sm text-gray-500">${club.category}</p>
                    </a>
                </div>
            `;
            clubsGrid.innerHTML += clubCard;
        });
    } catch (error) {
        // Display user-friendly error message on failure
        clubsGrid.innerHTML = '<p class="text-red-500 col-span-full text-center">Could not load clubs.</p>';
        console.error('Error loading clubs:', error);
    }
}