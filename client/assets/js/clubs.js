
/**
 * Clubs Listing Page Module
 * 
 * Handles the clubs listing page functionality including club display,
 * filtering by category, search functionality, and club joining.
 * Provides grid and list view options for browsing university clubs.
 * 
 * Key Features:
 * - Club grid/list display with category filtering
 * - Search and pagination functionality
 * - Club joining with authentication checks
 * - Responsive design with loading states
 * - Category-based organization
 * 
 * Dependencies: http.js, auth.js
 */

import { request, requestWithAuth } from './http.js';
import { isAuthenticated } from './auth.js';

document.addEventListener('DOMContentLoaded', function() {
    let currentPage = 1;
    let totalPages = 1;
    let currentFilters = {};
    let isLoading = false;
    let currentView = 'grid';

    const clubsGrid = document.getElementById('clubs-grid');
    const clubsList = document.getElementById('clubs-list');
    const resultsCount = document.getElementById('results-count');
    const noResults = document.getElementById('no-results');
    const paginationContainer = document.getElementById('clubs-pagination');
    const searchInput = document.getElementById('search-input');
    const categoryFilter = document.getElementById('category-filter');
    const statusFilter = document.getElementById('status-filter');
    const sizeFilter = document.getElementById('size-filter');
    const sortBy = document.getElementById('sort-by');
    const clearFiltersBtn = document.getElementById('clear-filters');
    const gridViewBtn = document.getElementById('grid-view');
    const listViewBtn = document.getElementById('list-view');

    // Initialize page
    loadClubs();
    setupViewToggle();

    // Club card template for grid view
    function createClubCard(club) {
        const memberSize = club.members_count <= 25 ? 'Small' :
                         club.members_count <= 100 ? 'Medium' : 'Large';
        
        return `
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-200 cursor-pointer" onclick="window.location.href='./club-details.html?id=${club._id.$oid}'">
                <div class="relative">
                    <div class="h-32 bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                        ${club.logo ? 
                            `<img src="${club.logo}" alt="${club.name}" class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-lg">` :
                            `<img src="https://placehold.co/100" alt="${club.name}" class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-lg">`
                        }
                    </div>
                    <div class="absolute top-2 right-2">
                        <span class="bg-blue-600 text-white px-2 py-1 rounded-full text-xs font-semibold">
                            ${club.category || 'General'}
                        </span>
                    </div>
                    ${club.status === 'active' ? '' : '<div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center"><span class="bg-gray-800 text-white px-3 py-1 rounded-full text-sm">Inactive</span></div>'}
                </div>
                
                <div class="p-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-1">
                        ${club.name}
                    </h3>
                    
                    <div class="flex items-center text-sm text-gray-600 mb-2">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        ${club.members_count || 0} members
                        <span class="mx-2">•</span>
                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">${memberSize}</span>
                    </div>
                    
                    <p class="text-gray-600 text-sm mb-4 line-clamp-3">
                        ${club.description}
                    </p>
                    
                    <div class="flex items-center justify-between">
                        <a href="./club-details.html?id=${club._id.$oid}" 
                           class="text-blue-600 hover:text-blue-800 font-medium text-sm" onclick="event.stopPropagation()">
                            Learn More
                        </a>
                        
                        ${club.status === 'active' ? `
                            ${club.is_member ? `
                                <button class="bg-gray-400 text-white px-4 py-2 rounded-md text-sm font-medium cursor-not-allowed" disabled onclick="event.stopPropagation()">Joined</button>
                            ` : `
                                <button onclick="event.stopPropagation(); joinClub('${club._id.$oid}')" 
                                        class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition duration-200">
                                    Join Club
                                </button>
                            `}
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

  // Club list item template for list view
function createClubListItem(club) {
    const memberSize = club.members_count <= 25 ? 'Small' : 
                     club.members_count <= 100 ? 'Medium' : 'Large';
    
    return `
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-200 cursor-pointer" onclick="window.location.href='./club-details.html?id=${club._id.$oid}'">
            <div class="flex items-center justify-between">
                <div class="flex items-center flex-1">
                    <div class="flex-shrink-0">
                        ${club.logo ? 
                            `<img src="${club.logo}" alt="${club.name}" class="w-16 h-16 rounded-full object-cover">` :
                            `<img src="https://placehold.co/100" alt="${club.name}" class="w-16 h-16 rounded-full object-cover">`
                        }
                    </div>
                    <div class="ml-6 flex-1">
                        <div class="flex items-center">
                            <h3 class="text-xl font-semibold text-gray-900">${club.name}</h3>
                            <span class="ml-3 bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">${club.category || 'General'}</span>
                            ${club.status !== 'active' ? '<span class="ml-2 bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-full">Inactive</span>' : ''}
                        </div>
                        <div class="mt-1 flex items-center text-sm text-gray-500">
                            <svg class="mr-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            ${club.members_count || 0} members (${memberSize})
                            <span class="mx-2">•</span>
                            <svg class="mr-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            ${club.contact_email || 'No contact provided'}
                        </div>
                        <p class="mt-2 text-gray-600 line-clamp-2">${club.description}</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="./club-details.html?id=${club._id.$oid}" 
                       class="text-blue-600 hover:text-blue-800 font-medium" onclick="event.stopPropagation()">
                        Learn More
                    </a>
                    ${club.status === 'active' ? `
                        <button onclick="event.stopPropagation(); joinClub('${club._id.$oid}')" 
                                class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition duration-200">
                            Join Club
                        </button>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
}

    // Skeleton template
    function createSkeletonCard() {
        return `
            <div class="animate-pulse">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="h-32 bg-gray-300"></div>
                    <div class="p-4">
                        <div class="h-4 bg-gray-300 rounded w-3/4 mb-2"></div>
                        <div class="h-3 bg-gray-300 rounded w-1/2 mb-2"></div>
                        <div class="h-3 bg-gray-300 rounded w-full"></div>
                    </div>
                </div>
            </div>
        `;
    }

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
        loadClubs(false);
        
        // Scroll to top of clubs grid
        const scrollTarget = currentView === 'grid' ? clubsGrid : clubsList;
        scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    // Show skeletons
    function showSkeletons() {
        clubsGrid.innerHTML = '';
        clubsList.innerHTML = '';
        for (let i = 0; i < 8; i++) { // Show 8 skeleton cards
            const skeleton = document.createElement('div');
            skeleton.innerHTML = createSkeletonCard();
            clubsGrid.appendChild(skeleton.firstElementChild);
        }
        clubsGrid.classList.remove('hidden');
        clubsList.classList.add('hidden');
    }

    // Hide skeletons
    function hideSkeletons() {
        clubsGrid.innerHTML = '';
        clubsList.innerHTML = '';
    }

    // Load clubs function
    async function loadClubs(reset = false) {
        if (isLoading) return;
        isLoading = true;

        // Handle reset scenario (initial load or filter change)
        if (reset) {
            currentPage = 1;
            noResults.classList.add('hidden');
            
            // Display skeleton loading cards
            showSkeletons();
        }

        try {
            const params = new URLSearchParams({
                action: 'list',
                page: currentPage,
                limit: 12
            });

            if (currentFilters.search) {
                params.append('search', currentFilters.search);
            }
            if (currentFilters.category) {
                params.append('category', currentFilters.category);
            }
            if (currentFilters.status) {
                params.append('status', currentFilters.status);
            }
            if (currentFilters.size) {
                // Handle size filter: convert to min/max members
                switch (currentFilters.size) {
                    case 'small':
                        params.append('min_members', '1');
                        params.append('max_members', '25');
                        break;
                    case 'medium':
                        params.append('min_members', '26');
                        params.append('max_members', '100');
                        break;
                    case 'large':
                        params.append('min_members', '101');
                        break;
                }
            }

            if (currentFilters.sort) {
                const [sortByField, sortOrder] = currentFilters.sort.split('-');
                params.append('sort_by', sortByField === 'members' ? 'members_count' : sortByField);
                params.append('sort_order', sortOrder);
            }

            const response = await request(`/clubs/index.php?${params.toString()}`, 'GET');
            console.log('Clubs API Response:', response); // Debug log
            
            const clubs = response.data?.clubs || response.clubs || [];
            const pagination = response.data?.pagination || response.pagination || {};
            const totalClubs = pagination.total_clubs || pagination.total || 0;
            
            // Update pagination state
            totalPages = pagination.total_pages || Math.ceil(totalClubs / 12) || 1;
            
            // Clear content for new results
            clubsGrid.innerHTML = '';
            clubsList.innerHTML = '';
            
            if (clubs.length === 0 && currentPage === 1) {
                noResults.classList.remove('hidden');
                resultsCount.textContent = 'No clubs found';
                paginationContainer.innerHTML = '';
            } else {
                noResults.classList.add('hidden');
                
                // Populate clubs based on current view
                if (currentView === 'grid') {
                    clubs.forEach(club => {
                        const clubCard = document.createElement('div');
                        clubCard.innerHTML = createClubCard(club);
                        clubsGrid.appendChild(clubCard.firstElementChild);
                    });
                    clubsGrid.classList.remove('hidden');
                    clubsList.classList.add('hidden');
                } else {
                    clubs.forEach(club => {
                        const clubItem = document.createElement('div');
                        clubItem.innerHTML = createClubListItem(club);
                        clubsList.appendChild(clubItem.firstElementChild);
                    });
                    clubsList.classList.remove('hidden');
                    clubsGrid.classList.add('hidden');
                }
                
                // Update results count
                const startItem = (currentPage - 1) * 12 + 1;
                const endItem = Math.min(currentPage * 12, totalClubs);
                resultsCount.textContent = `Showing ${startItem}-${endItem} of ${totalClubs} clubs`;
                
                // Render pagination
                renderPagination(currentPage, totalPages);
            }
            
        } catch (error) {
            console.error('Error loading clubs:', error);
            resultsCount.textContent = 'Failed to load clubs. Please check your connection and refresh the page.';
            clubsGrid.innerHTML = '';
            clubsList.innerHTML = '';
            noResults.classList.remove('hidden');
            paginationContainer.innerHTML = '';
        } finally {
            isLoading = false;
        }
    }

    // View toggle functionality
    function setupViewToggle() {
        gridViewBtn.addEventListener('click', function() {
            if (currentView !== 'grid') {
                currentView = 'grid';
                gridViewBtn.classList.remove('bg-white', 'text-gray-700');
                gridViewBtn.classList.add('bg-blue-600', 'text-white');
                listViewBtn.classList.remove('bg-blue-600', 'text-white');
                listViewBtn.classList.add('bg-white', 'text-gray-700');
                
                // Reload current page data in grid view
                loadClubs(false);
            }
        });

        listViewBtn.addEventListener('click', function() {
            if (currentView !== 'list') {
                currentView = 'list';
                listViewBtn.classList.remove('bg-white', 'text-gray-700');
                listViewBtn.classList.add('bg-blue-600', 'text-white');
                gridViewBtn.classList.remove('bg-blue-600', 'text-white');
                gridViewBtn.classList.add('bg-white', 'text-gray-700');
                
                // Reload current page data in list view
                loadClubs(false);
            }
        });
    }

    // Search functionality
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentFilters.search = this.value;
            loadClubs(true);
        }, 500);
    });

    // Filter functionality
    [categoryFilter, statusFilter, sizeFilter, sortBy].forEach(filter => {
        filter.addEventListener('change', function() {
            const filterType = this.id.replace('-filter', '').replace('-by', '');
            if (this.value) {
                currentFilters[filterType] = this.value;
            } else {
                delete currentFilters[filterType];
            }
            loadClubs(true);
        });
    });

    // Clear filters
    clearFiltersBtn.addEventListener('click', function() {
        searchInput.value = '';
        categoryFilter.value = '';
        statusFilter.value = '';
        sizeFilter.value = '';
        sortBy.value = 'name-asc';
        currentFilters = {};
        loadClubs(true);
    });

    // Club joining now refreshes current page instead of full reload

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

    // Join club function
    window.joinClub = async function(clubId) {
        try {
            if (!isAuthenticated()) {
                window.location.href = './login.html';
                return;
            }
            
            await requestWithAuth(`/clubs/index.php?action=join`, 'POST', { club_id: clubId });
            
            showNotification('Successfully joined club!');
            loadClubs(false); // Refresh current page to update join status
            
        } catch (error) {
            showNotification('Failed to join club: ' + error.message, true);
        }
    };

    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuButton) {
        mobileMenuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    }

    // User menu toggle
    const userMenuButton = document.getElementById('user-menu-button');
    const userDropdown = document.getElementById('user-dropdown');
    
    if (userMenuButton) {
        userMenuButton.addEventListener('click', function() {
            userDropdown.classList.toggle('hidden');
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#user-menu')) {
            userDropdown?.classList.add('hidden');
        }
    });
});
