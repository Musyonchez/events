
import { request, requestWithAuth } from './http.js';
import { isAuthenticated } from './auth.js';

document.addEventListener('DOMContentLoaded', function() {
    let currentPage = 1;
    let currentFilters = {};
    let allClubs = [];
    let isLoading = false;
    let currentView = 'grid';

    const clubsGrid = document.getElementById('clubs-grid');
    const clubsList = document.getElementById('clubs-list');
    const resultsCount = document.getElementById('results-count');
    const noResults = document.getElementById('no-results');
    const loadMoreBtn = document.getElementById('load-more-btn');
    const loadMoreText = document.getElementById('load-more-text');
    const loadMoreSpinner = document.getElementById('load-more-spinner');
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
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-200">
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
                           class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                            Learn More
                        </a>
                        
                        ${club.status === 'active' ? `
                            ${club.is_member ? `
                                <button class="bg-gray-400 text-white px-4 py-2 rounded-md text-sm font-medium cursor-not-allowed" disabled>Joined</button>
                            ` : `
                                <button onclick="joinClub('${club._id.$oid}')" 
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
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-200">
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
                       class="text-blue-600 hover:text-blue-800 font-medium">
                        Learn More
                    </a>
                    ${club.status === 'active' ? `
                        <button onclick="joinClub('${club._id.$oid}')" 
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

        if (reset) {
            currentPage = 1;
            allClubs = [];
            clubsGrid.innerHTML = '';
            clubsList.innerHTML = '';
            noResults.classList.add('hidden');
            showSkeletons(); // Show skeletons when resetting/loading
        }

        try {
            const params = {
                page: currentPage,
                limit: 12,
            };

            if (currentFilters.search) {
                params.search = currentFilters.search;
            }
            if (currentFilters.category) {
                params.category = currentFilters.category;
            }
            if (currentFilters.status) {
                params.status = currentFilters.status;
            }
            if (currentFilters.size) {
                // Handle size filter: convert to min/max members
                switch (currentFilters.size) {
                    case 'small':
                        params.min_members = 1;
                        params.max_members = 25;
                        break;
                    case 'medium':
                        params.min_members = 26;
                        params.max_members = 100;
                        break;
                    case 'large':
                        params.min_members = 101;
                        break;
                }
            }

            if (currentFilters.sort) {
                const [sortByField, sortOrder] = currentFilters.sort.split('-');
                params.sort_by = sortByField === 'members' ? 'members_count' : sortByField;
                params.sort_order = sortOrder;
            }

            const response = await request(`/clubs/index.php?action=list`, 'GET', params);
            const { clubs, total_clubs } = response;
            
            if (reset) {
                clubsGrid.innerHTML = '';
                clubsList.innerHTML = '';
            }
            hideSkeletons();
            
            if (clubs.length === 0 && currentPage === 1) {
                noResults.classList.remove('hidden');
                resultsCount.textContent = 'No clubs found';
                loadMoreBtn.parentElement.classList.add('hidden');
            } else {
                noResults.classList.add('hidden');
                allClubs = [...allClubs, ...clubs];
                
                if (currentView === 'grid') {
                    clubs.forEach(club => {
                        const clubCard = document.createElement('div');
                        clubCard.innerHTML = createClubCard(club);
                        clubsGrid.appendChild(clubCard.firstElementChild);
                    });
                } else {
                    clubs.forEach(club => {
                        const clubItem = document.createElement('div');
                        clubItem.innerHTML = createClubListItem(club);
                        clubsList.appendChild(clubItem.firstElementChild);
                    });
                }
                
                resultsCount.textContent = `Showing ${allClubs.length} of ${total_clubs} clubs`;
                
                if (allClubs.length >= total_clubs) {
                    loadMoreBtn.parentElement.classList.add('hidden');
                } else {
                    loadMoreBtn.parentElement.classList.remove('hidden');
                }
            }
            
        } catch (error) {
            console.error('Error loading clubs:', error);
            resultsCount.textContent = 'Error loading clubs';
        } finally {
            isLoading = false;
            loadMoreText.classList.remove('hidden');
            loadMoreSpinner.classList.add('hidden');
            loadMoreBtn.disabled = false;
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
                
                clubsGrid.classList.remove('hidden');
                clubsList.classList.add('hidden');
                
                // Repopulate grid view
                clubsGrid.innerHTML = '';
                allClubs.forEach(club => {
                    const clubCard = document.createElement('div');
                    clubCard.innerHTML = createClubCard(club);
                    clubsGrid.appendChild(clubCard.firstElementChild);
                });
            }
        });

        listViewBtn.addEventListener('click', function() {
            if (currentView !== 'list') {
                currentView = 'list';
                listViewBtn.classList.remove('bg-white', 'text-gray-700');
                listViewBtn.classList.add('bg-blue-600', 'text-white');
                gridViewBtn.classList.remove('bg-blue-600', 'text-white');
                gridViewBtn.classList.add('bg-white', 'text-gray-700');
                
                clubsList.classList.remove('hidden');
                clubsGrid.classList.add('hidden');
                
                // Repopulate list view
                clubsList.innerHTML = '';
                allClubs.forEach(club => {
                    const clubItem = document.createElement('div');
                    clubItem.innerHTML = createClubListItem(club);
                    clubsList.appendChild(clubItem.firstElementChild);
                });
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

    // Load more clubs
    loadMoreBtn.addEventListener('click', function() {
        if (isLoading) return;
        
        loadMoreText.classList.add('hidden');
        loadMoreSpinner.classList.remove('hidden');
        this.disabled = true;
        
        currentPage++;
        loadClubs();
    });

    // Join club function
    window.joinClub = async function(clubId) {
        try {
            if (!isAuthenticated()) {
                window.location.href = './login.html';
                return;
            }
            
            await requestWithAuth(`/clubs/index.php?action=join`, 'POST', { club_id: clubId });
            
            alert('Successfully joined club!');
            loadClubs(true);
            
        } catch (error) {
            alert('Failed to join club: ' + error.message);
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
