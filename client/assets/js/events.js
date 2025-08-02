
/**
 * Events Listing and Management Module
 * 
 * This module handles the events listing page functionality including
 * event display, filtering, searching, pagination, and user registration.
 * It provides a comprehensive interface for browsing and interacting with
 * university events.
 * 
 * Key Features:
 * - Event grid display with card-based layout
 * - Advanced filtering (search, category, date, status)
 * - Pagination with "Load More" functionality
 * - Real-time event registration
 * - Responsive design with skeleton loading states
 * - MongoDB date format handling
 * - Authentication-aware registration buttons
 * 
 * Dependencies:
 * - http.js: API communication and authentication
 * - auth.js: User authentication state management
 * 
 * Page Elements:
 * - Event grid container for displaying event cards
 * - Filter controls (search, category, date, status, sort)
 * - Pagination controls and loading indicators
 * - Registration buttons with authentication checks
 * 
 * Data Flow:
 * 1. Load events from API with current filters
 * 2. Render event cards with registration status
 * 3. Handle user interactions (filters, registration)
 * 4. Update UI state based on authentication status
 */

import { request, requestWithAuth } from './http.js';
import { isAuthenticated, logout, refreshToken } from './auth.js';

document.addEventListener('DOMContentLoaded', function() {
    // Application state management
    let currentPage = 1;           // Current pagination page
    let totalPages = 1;            // Total number of pages
    let currentFilters = {};       // Active filter criteria
    let isLoading = false;        // Loading state prevention

    // DOM element references for events page functionality
    const eventsGrid = document.getElementById('events-grid');
    const resultsCount = document.getElementById('results-count');
    const noResults = document.getElementById('no-results');
    const paginationContainer = document.getElementById('events-pagination');
    
    // Filter and search controls
    const searchInput = document.getElementById('search-input');
    const categoryFilter = document.getElementById('category-filter');
    const dateFilter = document.getElementById('date-filter');
    const statusFilter = document.getElementById('status-filter');
    const sortBy = document.getElementById('sort-by');
    const clearFiltersBtn = document.getElementById('clear-filters');

    // Initialize page with initial event loading
    loadEvents();

    /**
     * Event card HTML generator
     * 
     * Creates a responsive event card with all relevant event information
     * including registration status, featured badges, and interactive elements.
     * 
     * Card Features:
     * - Featured event badge for promoted events
     * - Event banner image with fallback placeholder
     * - Event category badge overlay
     * - Date and location information with icons
     * - Registration information and pricing
     * - Registration button with status checking
     * - Click-to-navigate functionality
     * 
     * Registration Logic:
     * - Checks if registration is required and open
     * - Validates registration deadline
     * - Compares current vs maximum attendees
     * - Shows appropriate button or status message
     * 
     * @param {Object} event - Event data from API
     * @returns {string} HTML string for event card
     */
    function createEventCard(event) {
        /**
         * MongoDB date format handler
         * 
         * Converts MongoDB's complex date format to JavaScript timestamp.
         * Handles both $date.$numberLong format and ISO string fallbacks.
         * 
         * @param {Object|string} dateObj - MongoDB date object or ISO string
         * @returns {number|null} JavaScript timestamp or null if invalid
         */
        const getTimestamp = (dateObj) => {
            if (!dateObj) return null;
            // Handle MongoDB's $date.$numberLong format
            if (dateObj.$date && dateObj.$date.$numberLong) {
                return parseInt(dateObj.$date.$numberLong);
            }
            // Fallback for ISO strings or other direct date formats
            return new Date(dateObj).getTime();
        };

        // Convert event dates to JavaScript Date objects
        const eventDate = new Date(getTimestamp(event.event_date));
        const deadline = new Date(getTimestamp(event.registration_deadline));

        // Determine if registration is currently open
        const isRegistrationOpen = event.registration_required && 
            !isNaN(deadline.getTime()) &&              // Valid deadline date
            deadline > new Date() &&                   // Deadline not passed
            event.current_registrations < event.max_attendees; // Space available

        return `
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-200 cursor-pointer" onclick="handleCardClick(event, '${event._id.$oid}')">
                ${event.featured ? '<div class="bg-yellow-500 text-white px-3 py-1 text-xs font-semibold">FEATURED</div>' : ''}
                
                <div class="relative">
                    <img src="${event.banner_image || 'https://placehold.co/400x250'}" 
                         alt="${event.title}" 
                         class="w-full h-48 object-cover">
                    <div class="absolute top-2 right-2">
                        <span class="bg-blue-600 text-white px-2 py-1 rounded-full text-xs font-semibold">
                            ${event.category || 'General'}
                        </span>
                    </div>
                </div>
                
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">
                        ${event.title}
                    </h3>
                    
                    <div class="flex items-center text-sm text-gray-600 mb-2">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        ${eventDate.toLocaleDateString('en-US', { 
                            weekday: 'short', 
                            year: 'numeric', 
                            month: 'short', 
                            day: 'numeric' 
                        })}
                    </div>
                    
                    <div class="flex items-center text-sm text-gray-600 mb-3">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        ${event.location || 'TBA'}
                    </div>
                    
                    <p class="text-gray-600 text-sm mb-4 line-clamp-3">
                        ${event.description}
                    </p>
                    
                    ${event.registration_required ? `
                        <div class="flex items-center justify-between text-sm mb-4">
                            <span class="text-gray-600">
                                ${event.current_registrations}/${event.max_attendees || '∞'} registered
                            </span>
                            ${event.registration_fee > 0 ? `<span class="text-green-600 font-semibold">KSh ${event.registration_fee}</span>` : '<span class="text-green-600 font-semibold">Free</span>'}
                        </div>
                    ` : ''}
                    
                    <div class="flex items-center justify-between">
                        <a href="./event-details.html?id=${event._id.$oid}" 
                           class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                            View Details
                        </a>
                        
                        ${isRegistrationOpen ? `
                            <button onclick="registerForEvent('${event._id.$oid}')" 
                                    class="register-button bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition duration-200">
                                Register
                            </button>
                        ` : event.registration_required ? `
                            <span class="text-gray-500 text-sm">Registration Closed</span>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Loading skeleton card generator
     * 
     * Creates a skeleton loading card that mimics the structure of a real
     * event card while content is loading. Uses Tailwind's animate-pulse
     * for smooth loading animation.
     * 
     * Skeleton Structure:
     * - Card container with pulse animation
     * - Gray placeholder for banner image
     * - Gray bars representing title and metadata
     * - Multiple lines representing description text
     * 
     * @returns {string} HTML string for skeleton loading card
     */
    function createSkeletonCard() {
        return `
            <div class="animate-pulse">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="h-48 bg-gray-300"></div>
                    <div class="p-6">
                        <div class="h-4 bg-gray-300 rounded w-3/4 mb-2"></div>
                        <div class="h-4 bg-gray-300 rounded w-1/2 mb-4"></div>
                        <div class="space-y-2">
                            <div class="h-3 bg-gray-300 rounded w-full"></div>
                            <div class="h-3 bg-gray-300 rounded w-2/3"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Event card click handler
     * 
     * Handles navigation when users click on event cards. Implements
     * smart click detection to avoid conflicts with buttons and links
     * within the card.
     * 
     * Click Logic:
     * - Checks if click target is a button or link (or child of one)
     * - If interactive element clicked: Does nothing (lets element handle)
     * - If card background clicked: Navigates to event details page
     * 
     * This allows cards to be clickable while preserving button functionality
     * for registration and "View Details" links.
     * 
     * @param {Event} event - The click event object
     * @param {string} eventId - The MongoDB ObjectId of the event
     */
    window.handleCardClick = function(event, eventId) {
        // Prevent navigation if user clicked on interactive elements
        if (event.target.closest('button, a')) {
            return;
        }
        // Navigate to event details page
        window.location.href = `./event-details.html?id=${eventId}`;
    };

    /**
     * Pagination rendering function
     * 
     * Creates numbered pagination with Previous/Next buttons and page numbers.
     * Shows up to 5 page numbers with intelligent range calculation.
     * 
     * @param {number} currentPage - Current active page
     * @param {number} totalPages - Total number of pages
     */
    function renderPagination(currentPage, totalPages) {
        if (!paginationContainer) return;

        if (totalPages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        // Calculate page range to show
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        // Adjust startPage if we're near the end
        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }

        let paginationHTML = `
            <div class="flex items-center justify-center space-x-1">
        `;

        // Previous button
        if (currentPage > 1) {
            paginationHTML += `
                <button onclick="changePage(${currentPage - 1})" 
                        class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition duration-200">
                    Previous
                </button>
            `;
        }

        // Page numbers
        for (let i = startPage; i <= endPage; i++) {
            const isActive = i === currentPage;
            paginationHTML += `
                <button onclick="changePage(${i})" 
                        class="px-3 py-2 text-sm font-medium transition duration-200 ${isActive 
                            ? 'text-blue-600 bg-blue-50 border border-blue-300 rounded-md' 
                            : 'text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50'
                        }">
                    ${i}
                </button>
            `;
        }

        // Next button
        if (currentPage < totalPages) {
            paginationHTML += `
                <button onclick="changePage(${currentPage + 1})" 
                        class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition duration-200">
                    Next
                </button>
            `;
        }

        paginationHTML += `
            </div>
        `;

        paginationContainer.innerHTML = paginationHTML;
    }

    /**
     * Page change handler
     * 
     * @param {number} page - Page number to navigate to
     */
    window.changePage = function(page) {
        if (page < 1 || page > totalPages || page === currentPage || isLoading) return;
        currentPage = page;
        loadEvents(false);
        
        // Scroll to top of events grid
        eventsGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    /**
     * Main events loading function
     * 
     * Fetches events from the API based on current filters and pagination.
     * Handles both initial loading and page navigation with appropriate
     * loading states and error handling.
     * 
     * Loading States:
     * - Initial/Filter: Shows skeleton cards and resets pagination
     * - Page Navigation: Shows loading spinner and maintains grid
     * - Prevents multiple simultaneous requests
     * 
     * @param {boolean} reset - If true, resets pagination and shows skeletons
     */
    async function loadEvents(reset = false) {
        // Prevent multiple simultaneous requests
        if (isLoading) return;
        isLoading = true;

        // Handle reset scenario (initial load or filter change)
        if (reset) {
            currentPage = 1;
            noResults.classList.add('hidden');
            
            // Display skeleton loading cards
            let skeletonHTML = '';
            for (let i = 0; i < 12; i++) {
                skeletonHTML += createSkeletonCard();
            }
            eventsGrid.innerHTML = skeletonHTML;
        }

        try {
            // Build API request parameters
            const params = new URLSearchParams({
                action: 'list',
                page: currentPage,
                limit: 12,
            });

            if (currentFilters.search) params.append('search', currentFilters.search);
            if (currentFilters.category) params.append('category', currentFilters.category);
            if (currentFilters.date) params.append('date', currentFilters.date);
            if (currentFilters.status) params.append('status', currentFilters.status);
            if (currentFilters.sort) params.append('sort', currentFilters.sort);
            
            const response = await request(`/events/index.php?${params.toString()}`, 'GET');
            const fetchedEvents = response.data?.events || [];
            const pagination = response.data?.pagination || {};
            
            // Update pagination state
            totalPages = pagination.total_pages || 1;
            const totalEvents = pagination.total || 0;

            // Clear grid for new content
            eventsGrid.innerHTML = '';

            if (fetchedEvents.length === 0 && currentPage === 1) {
                noResults.classList.remove('hidden');
                resultsCount.textContent = 'No events found';
                paginationContainer.innerHTML = '';
            } else {
                noResults.classList.add('hidden');
                
                fetchedEvents.forEach(event => {
                    const eventCardHTML = createEventCard(event);
                    eventsGrid.insertAdjacentHTML('beforeend', eventCardHTML);
                });
                
                // Update results count
                const startItem = (currentPage - 1) * 12 + 1;
                const endItem = Math.min(currentPage * 12, totalEvents);
                resultsCount.textContent = `Showing ${startItem}-${endItem} of ${totalEvents} events`;
                
                // Render pagination
                renderPagination(currentPage, totalPages);
            }
            
        } catch (error) {
            console.error('Failed to load events from server:', error);
            resultsCount.textContent = 'Failed to load events from server. Please check your connection and refresh the page.';
            eventsGrid.innerHTML = ''; // Clear loading state on error
            noResults.classList.remove('hidden');
            paginationContainer.innerHTML = '';
        } finally {
            isLoading = false;
        }
    }

    // Search functionality
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentFilters.search = this.value;
            loadEvents(true);
        }, 500);
    });

    // Filter functionality
    [categoryFilter, dateFilter, statusFilter, sortBy].forEach(filter => {
        filter.addEventListener('change', function() {
            const filterType = this.id.replace('-filter', '').replace('-by', '');
            if (this.value) {
                currentFilters[filterType] = this.value;
            } else {
                delete currentFilters[filterType];
            }
            loadEvents(true);
        });
    });

    // Clear filters
    clearFiltersBtn.addEventListener('click', function() {
        searchInput.value = '';
        categoryFilter.value = '';
        dateFilter.value = '';
        statusFilter.value = '';
        sortBy.value = 'date-asc';
        currentFilters = {};
        loadEvents(true);
    });

    // Event registration now handles pagination refresh
    // No more "Load More" functionality - replaced with numbered pagination

    // Show notification function
    function showNotification(message, isError = false) {
        const errorMessage = document.getElementById('error-message');
        const successMessage = document.getElementById('success-message');
        
        if (isError) {
            document.getElementById('error-text').textContent = message;
            errorMessage.classList.remove('hidden');
            successMessage.classList.add('hidden');
        } else {
            document.getElementById('success-text').textContent = message;
            successMessage.classList.remove('hidden');
            errorMessage.classList.add('hidden');
        }
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            errorMessage.classList.add('hidden');
            successMessage.classList.add('hidden');
        }, 5000);
    }

    // Register for event function
    window.registerForEvent = async function(eventId) {
        try {
            if (!isAuthenticated()) {
                window.location.href = './login.html';
                return;
            }
            
            await requestWithAuth('/events/index.php?action=register', 'POST', { event_id: eventId });
            
            showNotification('Registration successful!');
            loadEvents(true);
            
        } catch (error) {
            showNotification('Registration failed: ' + (error.message || 'Unable to register for this event. Please try again or contact support.'), true);
        }
    };

    });
