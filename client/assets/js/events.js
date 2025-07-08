
import { request, requestWithAuth } from './http.js';
import { isAuthenticated, logout, refreshToken } from './auth.js';

document.addEventListener('DOMContentLoaded', function() {
    let currentPage = 1;
    let currentFilters = {};
    let allEvents = [];
    let isLoading = false;

    const eventsGrid = document.getElementById('events-grid');
    const resultsCount = document.getElementById('results-count');
    const noResults = document.getElementById('no-results');
    const loadMoreBtn = document.getElementById('load-more-btn');
    const loadMoreText = document.getElementById('load-more-text');
    const loadMoreSpinner = document.getElementById('load-more-spinner');
    const searchInput = document.getElementById('search-input');
    const categoryFilter = document.getElementById('category-filter');
    const dateFilter = document.getElementById('date-filter');
    const statusFilter = document.getElementById('status-filter');
    const sortBy = document.getElementById('sort-by');
    const clearFiltersBtn = document.getElementById('clear-filters');

    // Initialize page
    loadEvents();

    // Event card template
    function createEventCard(event) {
        // Handle complex date objects from MongoDB JSON representation
        const getTimestamp = (dateObj) => {
            if (!dateObj) return null;
            if (dateObj.$date && dateObj.$date.$numberLong) {
                return parseInt(dateObj.$date.$numberLong);
            }
            // Fallback for ISO strings or other direct date formats
            return new Date(dateObj).getTime();
        };

        const eventDate = new Date(getTimestamp(event.event_date));
        const deadline = new Date(getTimestamp(event.registration_deadline));

        const isRegistrationOpen = event.registration_required && 
            !isNaN(deadline.getTime()) && // Check if the date is valid
            deadline > new Date() && 
            event.current_registrations < event.max_attendees;

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

    // Skeleton card template
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

    // Handle card click for navigation
    window.handleCardClick = function(event, eventId) {
        // Do not navigate if the click was on a button, a link, or any element with its own click handler
        if (event.target.closest('button, a')) {
            return;
        }
        window.location.href = `./event-details.html?id=${eventId}`;
    };

    // Load events function
    async function loadEvents(reset = false) {
        if (isLoading) return;
        isLoading = true;

        if (reset) {
            currentPage = 1;
            allEvents = [];
            noResults.classList.add('hidden');
            // Show skeletons on initial load or filter change
            let skeletonHTML = '';
            for (let i = 0; i < 6; i++) {
                skeletonHTML += createSkeletonCard();
            }
            eventsGrid.innerHTML = skeletonHTML;
        }

        try {
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
            const fetchedEvents = response.data.events || [];
            const totalEvents = response.data.total;

            if (currentPage === 1) {
                eventsGrid.innerHTML = ''; // Clear skeletons
            }

            if (fetchedEvents.length === 0 && currentPage === 1) {
                noResults.classList.remove('hidden');
                resultsCount.textContent = 'No events found';
                loadMoreBtn.parentElement.classList.add('hidden');
            } else {
                noResults.classList.add('hidden');
                
                fetchedEvents.forEach(event => {
                    const eventCardHTML = createEventCard(event);
                    eventsGrid.insertAdjacentHTML('beforeend', eventCardHTML);
                });
                
                allEvents = [...allEvents, ...fetchedEvents];
                resultsCount.textContent = `Showing ${allEvents.length} of ${totalEvents} events`;
                
                if (allEvents.length >= totalEvents) {
                    loadMoreBtn.parentElement.classList.add('hidden');
                } else {
                    loadMoreBtn.parentElement.classList.remove('hidden');
                }
            }
            
        } catch (error) {
            console.error('Error loading events:', error);
            resultsCount.textContent = 'Error loading events';
            eventsGrid.innerHTML = ''; // Clear skeletons on error
            noResults.classList.remove('hidden');
        } finally {
            isLoading = false;
            loadMoreText.classList.remove('hidden');
            loadMoreSpinner.classList.add('hidden');
            loadMoreBtn.disabled = false;
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

    // Load more events
    loadMoreBtn.addEventListener('click', function() {
        if (isLoading) return;
        
        loadMoreText.classList.add('hidden');
        loadMoreSpinner.classList.remove('hidden');
        this.disabled = true;
        
        currentPage++;
        loadEvents();
    });

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
            showNotification('Registration failed: ' + (error.message || 'An unknown error occurred.'), true);
        }
    };

    });
