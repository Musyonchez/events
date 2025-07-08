
import { isAuthenticated, getCurrentUser } from './auth.js';

document.addEventListener('DOMContentLoaded', function() {
    // Check authentication
    // TODO: Implement authentication check
    // if (!isLoggedIn()) {
    //     window.location.href = './login.html';
    //     return;
    // }

    setupTabNavigation();
    loadUserData();
});

function setupTabNavigation() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.id.replace('tab-', '');
            
            // Update active tab
            tabButtons.forEach(btn => {
                btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });
            
            this.classList.add('active', 'border-blue-500', 'text-blue-600');
            this.classList.remove('border-transparent', 'text-gray-500');

            // Show corresponding content
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            
            document.getElementById(`content-${tabId}`).classList.remove('hidden');

            // Load content for the selected tab
            loadTabContent(tabId);
        });
    });
}

async function loadUserData() {
    try {
        if (!isAuthenticated()) {
            window.location.href = './login.html';
            return;
        }

        const user = getCurrentUser();
        if (!user) {
            console.error('User data not found in local storage.');
            window.location.href = './login.html';
            return;
        }

        // Show admin features for admin/club_leader users
        if (user.role === 'admin' || user.role === 'club_leader') {
            document.getElementById('admin-actions').classList.remove('hidden');
            document.getElementById('tab-created').classList.remove('hidden');
            document.getElementById('created-events-card').classList.remove('hidden');
        }

        // Load dashboard stats
        await loadDashboardStats();

    } catch (error) {
        console.error('Error loading user data:', error);
    }
}

async function loadDashboardStats() {
    try {
        // TODO: Implement API calls to get user stats
        // const stats = await getUserStats();
        
        // Mock stats data
        const stats = {
            registeredEvents: 0,
            attendedEvents: 0,
            createdEvents: 0,
            upcomingEvents: 0
        };

        document.getElementById('registered-count').textContent = stats.registeredEvents;
        document.getElementById('attended-count').textContent = stats.attendedEvents;
        document.getElementById('created-count').textContent = stats.createdEvents;
        document.getElementById('upcoming-count').textContent = stats.upcomingEvents;

    } catch (error) {
        console.error('Error loading dashboard stats:', error);
    }
}

function loadTabContent(tabId) {
    switch (tabId) {
        case 'registered':
            loadRegisteredEvents();
            break;
        case 'upcoming':
            loadUpcomingEvents();
            break;
        case 'created':
            loadCreatedEvents();
            break;
        case 'history':
            loadEventHistory();
            break;
    }
}

async function loadRegisteredEvents() {
    const container = document.getElementById('registered-events-list');
    
    try {
        // TODO: Implement API call to get user's registered events
        // const events = await getUserRegisteredEvents();
        
        // Mock data
        const events = [];

        if (events.length === 0) {
            container.innerHTML = `
                <div class="px-6 py-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No registered events</h3>
                    <p class="mt-1 text-sm text-gray-500">You haven't registered for any events yet.</p>
                    <div class="mt-6">
                        <a href="./events.html" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700">
                            Browse Events
                        </a>
                    </div>
                </div>
            `;
        } else {
            container.innerHTML = events.map(createEventListItem).join('');
        }

    } catch (error) {
        console.error('Error loading registered events:', error);
        container.innerHTML = `
            <div class="px-6 py-4 text-center text-red-600">
                Error loading events. Please try again.
            </div>
        `;
    }
}

async function loadUpcomingEvents() {
    const container = document.getElementById('upcoming-events-list');
    container.innerHTML = '<div class="px-6 py-4 text-center text-gray-500">Loading upcoming events...</div>';

    try {
        // TODO: Implement API call to get upcoming events
        // const events = await getUpcomingEvents();
        
        const events = [];

        if (events.length === 0) {
            container.innerHTML = `
                <div class="px-6 py-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No upcoming events</h3>
                    <p class="mt-1 text-sm text-gray-500">Check back later for new events.</p>
                </div>
            `;
        } else {
            container.innerHTML = events.map(createEventListItem).join('');
        }

    } catch (error) {
        console.error('Error loading upcoming events:', error);
        container.innerHTML = `
            <div class="px-6 py-4 text-center text-red-600">
                Error loading events. Please try again.
            </div>
        `;
    }
}

async function loadCreatedEvents() {
    const container = document.getElementById('created-events-list');
    container.innerHTML = '<div class="px-6 py-4 text-center text-gray-500">Loading created events...</div>';

    try {
        // TODO: Implement API call to get user's created events
        // const events = await getUserCreatedEvents();
        
        const events = [];

        if (events.length === 0) {
            container.innerHTML = `
                <div class="px-6 py-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No created events</h3>
                    <p class="mt-1 text-sm text-gray-500">You haven't created any events yet.</p>
                    <div class="mt-6">
                        <a href="./admin/create-event.html" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700">
                            Create Your First Event
                        </a>
                    </div>
                </div>
            `;
        } else {
            container.innerHTML = events.map(event => createEventListItem(event, true)).join('');
        }

    } catch (error) {
        console.error('Error loading created events:', error);
        container.innerHTML = `
            <div class="px-6 py-4 text-center text-red-600">
                Error loading events. Please try again.
            </div>
        `;
    }
}

async function loadEventHistory() {
    const container = document.getElementById('history-events-list');
    container.innerHTML = '<div class="px-6 py-4 text-center text-gray-500">Loading event history...</div>';

    try {
        // TODO: Implement API call to get user's event history
        // const events = await getUserEventHistory();
        
        const events = [];

        if (events.length === 0) {
            container.innerHTML = `
                <div class="px-6 py-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No event history</h3>
                    <p class="mt-1 text-sm text-gray-500">You haven't attended any events yet.</p>
                </div>
            `;
        } else {
            container.innerHTML = events.map(createEventListItem).join('');
        }

    } catch (error) {
        console.error('Error loading event history:', error);
        container.innerHTML = `
            <div class="px-6 py-4 text-center text-red-600">
                Error loading events. Please try again.
            </div>
        `;
    }
}

function createEventListItem(event, showManageActions = false) {
    const eventDate = new Date(event.event_date);
    const now = new Date();
    const isUpcoming = eventDate > now;
    
    return `
        <div class="px-6 py-4 hover:bg-gray-50">
            <div class="flex items-center justify-between">
                <div class="flex items-center flex-1">
                    <div class="flex-shrink-0">
                        <img src="${event.banner_image || '../assets/images/event-placeholder.jpg'}" 
                             alt="${event.title}" 
                             class="w-12 h-12 rounded-lg object-cover">
                    </div>
                    <div class="ml-4 flex-1">
                        <div class="flex items-center">
                            <h4 class="text-sm font-medium text-gray-900">${event.title}</h4>
                            ${event.featured ? '<span class="ml-2 bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">Featured</span>' : ''}
                            ${event.status === 'draft' ? '<span class="ml-2 bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-full">Draft</span>' : ''}
                        </div>
                        <div class="mt-1 flex items-center text-sm text-gray-500">
                            <svg class="mr-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            ${eventDate.toLocaleDateString('en-US', { 
                                weekday: 'short', 
                                year: 'numeric', 
                                month: 'short', 
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            })}
                            <span class="mx-2">•</span>
                            <svg class="mr-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            ${event.location || 'TBA'}
                        </div>
                        ${event.registration_required ? `
                            <div class="mt-1 text-xs text-gray-500">
                                ${event.current_registrations}/${event.max_attendees || '∞'} registered
                                ${event.registration_fee > 0 ? ` • KSh ${event.registration_fee}` : ' • Free'}
                            </div>
                        ` : ''}
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    ${showManageActions ? `
                        <button onclick="editEvent('${event._id}')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            Edit
                        </button>
                        <button onclick="viewEventStats('${event._id}')" class="text-green-600 hover:text-green-800 text-sm font-medium">
                            Stats
                        </button>
                        <button onclick="deleteEvent('${event._id}')" class="text-red-600 hover:text-red-800 text-sm font-medium">
                            Delete
                        </button>
                    ` : `
                        <a href="./event-details.html?id=${event._id}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            View Details
                        </a>
                        ${isUpcoming && event.registration_required ? `
                            <button onclick="unregisterFromEvent('${event._id}')" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                Unregister
                            </button>
                        ` : ''}
                    `}
                </div>
            </div>
        </div>
    `;
}

// Event management functions
window.editEvent = function(eventId) {
    window.location.href = `./admin/create-event.html?edit=${eventId}`;
};

window.viewEventStats = function(eventId) {
    window.location.href = `./admin/event-stats.html?id=${eventId}`;
};

window.deleteEvent = async function(eventId) {
    if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
        try {
            // TODO: Implement delete event API call
            // await deleteEvent(eventId);
            
            alert('Event deleted successfully');
            loadCreatedEvents(); // Reload the list
        } catch (error) {
            alert('Failed to delete event: ' + error.message);
        }
    }
};

window.unregisterFromEvent = async function(eventId) {
    if (confirm('Are you sure you want to unregister from this event?')) {
        try {
            // TODO: Implement unregister API call
            // await unregisterFromEvent(eventId);
            
            alert('Successfully unregistered from event');
            loadRegisteredEvents(); // Reload the list
            loadDashboardStats(); // Update stats
        } catch (error) {
            alert('Failed to unregister: ' + error.message);
        }
    }
};
