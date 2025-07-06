// Main application logic

document.addEventListener('navbarLoaded', function() {
    setupNavbarEventListeners();
    checkAuthState();

    // --- Homepage Specific Logic ---
    if (document.getElementById('featured-events')) {
        loadFeaturedEvents();
    }
    if (document.getElementById('clubs-grid')) {
        loadClubs();
    }
});

function setupNavbarEventListeners() {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const userMenuButton = document.getElementById('user-menu-button');
    const userDropdown = document.getElementById('user-dropdown');
    const logoutBtn = document.getElementById('logout-btn');
    const mobileLogoutBtn = document.getElementById('mobile-logout-btn');

    // Mobile menu toggle
    if (mobileMenuButton) {
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    }

    // User menu toggle
    if (userMenuButton) {
        userMenuButton.addEventListener('click', () => {
            userDropdown.classList.toggle('hidden');
        });
    }

    // Logout functionality
    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            logout();
        });
    }
    if (mobileLogoutBtn) {
        mobileLogoutBtn.addEventListener('click', () => {
            logout();
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (userDropdown && !e.target.closest('#user-menu')) {
            userDropdown.classList.add('hidden');
        }
    });
}

/**
 * Checks the user's authentication state and updates the UI accordingly.
 */
function checkAuthState() {
    const authButtons = document.getElementById('auth-buttons');
    const userMenu = document.getElementById('user-menu');
    const mobileAuthButtons = document.getElementById('mobile-auth-buttons');
    const mobileUserMenu = document.getElementById('mobile-user-menu');

    if (isAuthenticated()) {
        // User is logged in
        authButtons.classList.add('hidden');
        userMenu.classList.remove('hidden');
        if (mobileAuthButtons) mobileAuthButtons.classList.add('hidden');
        if (mobileUserMenu) mobileUserMenu.classList.remove('hidden');

        const user = getCurrentUser();
        if (user) {
            const userNameElement = document.getElementById('user-name');
            if (userNameElement) userNameElement.textContent = user.first_name;
            const avatar = document.getElementById('user-avatar');
            if (avatar) {
                if (user.profile_image) {
                    avatar.src = user.profile_image;
                } else {
                    // Determine the correct path to the default avatar based on the current page
                    const isRoot = window.location.pathname.endsWith('/index.html') || window.location.pathname.endsWith('/');
                    avatar.src = isRoot ? './assets/images/avatar.png' : '../assets/images/avatar.png';
                }
            }

            // Update mobile user info
            const mobileUserNameElement = document.getElementById('mobile-user-name');
            if (mobileUserNameElement) mobileUserNameElement.textContent = user.first_name;
            const mobileAvatar = document.getElementById('mobile-user-avatar');
            if (mobileAvatar) {
                if (user.profile_image) {
                    mobileAvatar.src = user.profile_image;
                } else {
                    const isRoot = window.location.pathname.endsWith('/index.html') || window.location.pathname.endsWith('/');
                    mobileAvatar.src = isRoot ? './assets/images/avatar.png' : '../assets/images/avatar.png';
                }
            }
        }
    } else {
        // User is not logged in
        authButtons.classList.remove('hidden');
        userMenu.classList.add('hidden');
        if (mobileAuthButtons) mobileAuthButtons.classList.remove('hidden');
        if (mobileUserMenu) mobileUserMenu.classList.add('hidden');
    }
}

/**
 * Fetches and displays featured events.
 */
async function loadFeaturedEvents() {
    const featuredEventsContainer = document.getElementById('featured-events');
    try {
        // Fetch featured events (assuming an endpoint exists for this)
        const events = await request('/events/index.php?action=details&featured=true&limit=3', 'GET');
        
        featuredEventsContainer.innerHTML = ''; // Clear loading placeholders

        if (events.length === 0) {
            featuredEventsContainer.innerHTML = '<p class="text-gray-600 col-span-full text-center">No featured events available at the moment.</p>';
            return;
        }

        events.forEach(event => {
            const eventCard = `
                <div class="bg-white rounded-lg shadow-lg overflow-hidden transform hover:-translate-y-2 transition duration-300">
                    <a href="./pages/event-details.html?id=${event._id}">
                        <img src="${event.banner_image || 'https://via.placeholder.com/400x200'}" alt="${event.title}" class="w-full h-48 object-cover">
                        <div class="p-6">
                            <p class="text-sm text-gray-500 mb-1">${new Date(event.event_date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">${event.title}</h3>
                            <p class="text-gray-600">${event.location}</p>
                        </div>
                    </a>
                </div>
            `;
            featuredEventsContainer.innerHTML += eventCard;
        });
    } catch (error) {
        featuredEventsContainer.innerHTML = '<p class="text-red-500 col-span-full text-center">Could not load featured events.</p>';
        console.error('Error loading featured events:', error);
    }
}

/**
 * Fetches and displays clubs.
 */
async function loadClubs() {
    const clubsGrid = document.getElementById('clubs-grid');
    try {
        // Fetch clubs
        const clubs = await request('/clubs/index.php?limit=4', 'GET');
        
        clubsGrid.innerHTML = ''; // Clear loading placeholders

        if (clubs.length === 0) {
            clubsGrid.innerHTML = '<p class="text-gray-600 col-span-full text-center">No clubs found.</p>';
            return;
        }

        clubs.forEach(club => {
            const clubCard = `
                <div class="bg-white rounded-lg shadow-md p-6 text-center transform hover:scale-105 transition duration-300">
                    <a href="./pages/club-details.html?id=${club._id}">
                        <img src="${club.logo || 'https://via.placeholder.com/100'}" alt="${club.name}" class="w-16 h-16 rounded-full mx-auto mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">${club.name}</h3>
                        <p class="text-sm text-gray-500">${club.category}</p>
                    </a>
                </div>
            `;
            clubsGrid.innerHTML += clubCard;
        });
    } catch (error) {
        clubsGrid.innerHTML = '<p class="text-red-500 col-span-full text-center">Could not load clubs.</p>';
        console.error('Error loading clubs:', error);
    }
}
