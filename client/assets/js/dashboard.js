
/**
 * User Dashboard Module
 * 
 * Handles the user dashboard page functionality including user profile display,
 * registered events management, and account information. Provides a personalized
 * interface for users to manage their event participation.
 * 
 * Key Features:
 * - User profile information display
 * - Registered events listing and management
 * - Tab-based navigation for different sections
 * - Event registration status tracking
 * - Authentication-protected content
 * 
 * Dependencies: auth.js, http.js
 */

import { isAuthenticated, getCurrentUser } from './auth.js';
import { request, requestWithAuth } from './http.js';

// Pagination state management for user dashboard
const dashboardPaginationState = {
    registered: { currentPage: 1, totalPages: 1, limit: 10 },
    upcoming: { currentPage: 1, totalPages: 1, limit: 10 },
    created: { currentPage: 1, totalPages: 1, limit: 10 },
    history: { currentPage: 1, totalPages: 1, limit: 10 }
};

document.addEventListener('DOMContentLoaded', function() {
    // Authentication is handled by getCurrentUser() check in loadUserData()

    setupTabNavigation();
    loadUserData();
    
    // Load content for the initially active tab
    loadTabContent('registered', 1);
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
            loadTabContent(tabId, 1);
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
            document.getElementById('created-events-card').classList.remove('hidden');
        }
        
        // Always show Created Events tab for all users (they might have created events)
        document.getElementById('tab-created').classList.remove('hidden');

        // Load dashboard stats
        await loadDashboardStats();

    } catch (error) {
        console.error('Error loading user data:', error);
    }
}

async function loadDashboardStats() {
    try {
        const response = await requestWithAuth('/users/index.php?action=stats', 'GET');
        const stats = response.data;

        document.getElementById('registered-count').textContent = stats.registered_events || 0;
        document.getElementById('attended-count').textContent = stats.attended_events || 0;
        document.getElementById('created-count').textContent = stats.created_events || 0;
        document.getElementById('upcoming-count').textContent = stats.upcoming_events || 0;

    } catch (error) {
        console.error('Error loading dashboard stats:', error);
        showErrorMessage('Failed to load dashboard statistics');
        // Set default values
        document.getElementById('registered-count').textContent = '0';
        document.getElementById('attended-count').textContent = '0';
        document.getElementById('created-count').textContent = '0';
        document.getElementById('upcoming-count').textContent = '0';
    }
}

function loadTabContent(tabId, page = 1) {
    switch (tabId) {
        case 'registered':
            loadRegisteredEvents(page);
            break;
        case 'upcoming':
            loadUpcomingEvents(page);
            break;
        case 'created':
            loadCreatedEvents(page);
            break;
        case 'history':
            loadEventHistory(page);
            break;
    }
}

async function loadRegisteredEvents(page = 1) {
    const container = document.getElementById('registered-events-list');
    
    try {
        const limit = dashboardPaginationState.registered.limit;
        const skip = (page - 1) * limit;
        const params = new URLSearchParams({
            action: 'registered',
            limit: limit,
            skip: skip
        });

        const response = await requestWithAuth(`/events/index.php?${params.toString()}`, 'GET');
        const events = response.data?.events || [];
        const pagination = response.data?.pagination || {};

        // Update pagination state
        dashboardPaginationState.registered.currentPage = page;
        dashboardPaginationState.registered.totalPages = pagination.total_pages || 1;

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
            container.innerHTML = events.map(event => createEventListItem(event)).join('');
        }

        // Render pagination
        renderDashboardPagination('registered', dashboardPaginationState.registered, (newPage) => loadRegisteredEvents(newPage));

    } catch (error) {
        console.error('Error loading registered events:', error);
        container.innerHTML = `
            <div class="px-6 py-4 text-center text-red-600">
                Error loading events. Please try again.
            </div>
        `;
        showErrorMessage('Failed to load registered events');
    }
}

async function loadUpcomingEvents(page = 1) {
    const container = document.getElementById('upcoming-events-list');
    container.innerHTML = '<div class="px-6 py-4 text-center text-gray-500">Loading upcoming events...</div>';

    try {
        const limit = dashboardPaginationState.upcoming.limit;
        const params = new URLSearchParams({
            action: 'list',
            date: 'upcoming',
            limit: limit,
            page: page
        });

        const response = await request(`/events/index.php?${params.toString()}`);
        const events = response.data?.events || [];
        const pagination = response.data?.pagination || {};

        // Update pagination state
        dashboardPaginationState.upcoming.currentPage = page;
        dashboardPaginationState.upcoming.totalPages = pagination.total_pages || 1;

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
            container.innerHTML = events.map(event => createEventListItem(event)).join('');
        }

        // Render pagination
        renderDashboardPagination('upcoming', dashboardPaginationState.upcoming, (newPage) => loadUpcomingEvents(newPage));

    } catch (error) {
        console.error('Error loading upcoming events:', error);
        container.innerHTML = `
            <div class="px-6 py-4 text-center text-red-600">
                Error loading events. Please try again.
            </div>
        `;
        showErrorMessage('Failed to load upcoming events');
    }
}

async function loadCreatedEvents(page = 1) {
    const container = document.getElementById('created-events-list');
    container.innerHTML = '<div class="px-6 py-4 text-center text-gray-500">Loading manageable events...</div>';

    try {
        const currentUser = getCurrentUser();
        const userRole = currentUser?.role || 'user';
        const limit = dashboardPaginationState.created.limit;
        
        let response;
        if (userRole === 'admin') {
            // Admins see all events with pagination
            const params = new URLSearchParams({
                action: 'list',
                limit: limit,
                page: page,
                sort: 'recent'
            });
            response = await request(`/events/index.php?${params.toString()}`, 'GET');
        } else {
            // Others see events they created with pagination
            const skip = (page - 1) * limit;
            const params = new URLSearchParams({
                action: 'created',
                limit: limit,
                skip: skip
            });
            response = await requestWithAuth(`/events/index.php?${params.toString()}`, 'GET');
        }
        
        let events = response.data?.events || [];
        const pagination = response.data?.pagination || {};
        
        // For non-admins, filter events they can actually manage
        if (userRole !== 'admin') {
            events = events.filter(event => canUserManageEvent(currentUser, event));
        }

        // Update pagination state
        dashboardPaginationState.created.currentPage = page;
        dashboardPaginationState.created.totalPages = pagination.total_pages || 1;

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

        // Render pagination
        renderDashboardPagination('created', dashboardPaginationState.created, (newPage) => loadCreatedEvents(newPage));

    } catch (error) {
        console.error('Error loading created events:', error);
        container.innerHTML = `
            <div class="px-6 py-4 text-center text-red-600">
                Error loading events. Please try again.
            </div>
        `;
        showErrorMessage('Failed to load created events');
    }
}

async function loadEventHistory(page = 1) {
    const container = document.getElementById('history-events-list');
    container.innerHTML = '<div class="px-6 py-4 text-center text-gray-500">Loading event history...</div>';

    try {
        const limit = dashboardPaginationState.history.limit;
        const skip = (page - 1) * limit;
        const params = new URLSearchParams({
            action: 'history',
            limit: limit,
            skip: skip
        });

        const response = await requestWithAuth(`/events/index.php?${params.toString()}`, 'GET');
        const events = response.data?.events || [];
        const pagination = response.data?.pagination || {};

        // Update pagination state
        dashboardPaginationState.history.currentPage = page;
        dashboardPaginationState.history.totalPages = pagination.total_pages || 1;

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
            container.innerHTML = events.map(event => createEventListItem(event)).join('');
        }

        // Render pagination
        renderDashboardPagination('history', dashboardPaginationState.history, (newPage) => loadEventHistory(newPage));

    } catch (error) {
        console.error('Error loading event history:', error);
        container.innerHTML = `
            <div class="px-6 py-4 text-center text-red-600">
                Error loading events. Please try again.
            </div>
        `;
        showErrorMessage('Failed to load event history');
    }
}

function createEventListItem(event, showManageActions = false) {
    const eventDate = new Date(event.event_date);
    const now = new Date();
    const isUpcoming = eventDate > now;
    const eventId = event._id?.$oid || event._id;
    
    // Check if user can manage this event
    const currentUser = getCurrentUser();
    const canManage = canUserManageEvent(currentUser, event);
    
    // Override showManageActions based on permissions
    if (showManageActions && !canManage) {
        showManageActions = false;
    }
    
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
                        <button onclick="editEvent('${eventId}')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            Edit
                        </button>
                        <button onclick="viewEventStats('${eventId}')" class="text-green-600 hover:text-green-800 text-sm font-medium">
                            Stats
                        </button>
                        <button onclick="deleteEvent('${eventId}')" class="text-red-600 hover:text-red-800 text-sm font-medium">
                            Delete
                        </button>
                    ` : `
                        <a href="./event-details.html?id=${eventId}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            View Details
                        </a>
                        ${isUpcoming && event.registration_required ? `
                            <button onclick="unregisterFromEvent('${eventId}')" class="text-red-600 hover:text-red-800 text-sm font-medium">
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
            await requestWithAuth(`/events/index.php?action=delete&id=${eventId}`, 'DELETE');
            
            showSuccessMessage('Event deleted successfully');
            loadCreatedEvents(dashboardPaginationState.created.currentPage); // Reload the list
            loadDashboardStats(); // Update stats
        } catch (error) {
            showErrorMessage('Failed to delete event: ' + error.message);
        }
    }
};

window.unregisterFromEvent = async function(eventId) {
    if (confirm('Are you sure you want to unregister from this event?')) {
        try {
            await requestWithAuth(`/events/index.php?action=unregister`, 'POST', { event_id: eventId });
            
            showSuccessMessage('Successfully unregistered from event');
            loadRegisteredEvents(dashboardPaginationState.registered.currentPage); // Reload the list
            loadDashboardStats(); // Update stats
        } catch (error) {
            showErrorMessage('Failed to unregister: ' + error.message);
        }
    }
};

// Permission check function
function canUserManageEvent(user, event) {
    if (!user) return false;
    
    const userId = user.id || user._id?.$oid || user._id;
    const userRole = user.role || 'user';
    const eventCreatedBy = event.created_by?.$oid || event.created_by;
    const eventClubId = event.club_id?.$oid || event.club_id;
    
    // Check: user.role === 'admin' OR created_by === user.id OR club_leader_of_club === event.club_id
    
    // 1. Admin can manage all events
    if (userRole === 'admin') {
        return true;
    }
    
    // 2. Creator can manage their own events
    if (eventCreatedBy && eventCreatedBy === userId) {
        return true;
    }
    
    // 3. Club leader can manage events from their clubs
    if (userRole === 'club_leader' && eventClubId) {
        // TODO: We need to check if this user is the leader of the event's club
        // For now, we'll assume club leaders can manage events from their clubs
        // This would require an API call to check club leadership
        return true;
    }
    return false;
}

// Utility functions for showing messages
function showErrorMessage(message) {
    hideMessages();
    const errorElement = document.getElementById('error-message');
    const errorText = document.getElementById('error-text');
    errorText.textContent = message;
    errorElement.classList.remove('hidden');
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        errorElement.classList.add('hidden');
    }, 5000);
}

function showSuccessMessage(message) {
    hideMessages();
    const successElement = document.getElementById('success-message');
    const successText = document.getElementById('success-text');
    successText.textContent = message;
    successElement.classList.remove('hidden');
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        successElement.classList.add('hidden');
    }, 5000);
}

function hideMessages() {
    document.getElementById('error-message').classList.add('hidden');
    document.getElementById('success-message').classList.add('hidden');
}

// Pagination rendering function for user dashboard
function renderDashboardPagination(section, state, onPageChange) {
    const container = document.getElementById(`${section}-pagination`);
    if (!container) return;

    const { currentPage, totalPages } = state;
    
    if (totalPages <= 1) {
        container.innerHTML = '';
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
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-700">
                    Page ${currentPage} of ${totalPages}
                </span>
            </div>
            <div class="flex items-center space-x-1">
    `;

    // Previous button
    if (currentPage > 1) {
        paginationHTML += `
            <button onclick="changeDashboardPage${section.charAt(0).toUpperCase() + section.slice(1)}(${currentPage - 1})" 
                    class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                Previous
            </button>
        `;
    }

    // Page numbers
    for (let i = startPage; i <= endPage; i++) {
        const isActive = i === currentPage;
        paginationHTML += `
            <button onclick="changeDashboardPage${section.charAt(0).toUpperCase() + section.slice(1)}(${i})" 
                    class="px-3 py-2 text-sm font-medium ${isActive 
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
            <button onclick="changeDashboardPage${section.charAt(0).toUpperCase() + section.slice(1)}(${currentPage + 1})" 
                    class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                Next
            </button>
        `;
    }

    paginationHTML += `
            </div>
        </div>
    `;

    container.innerHTML = paginationHTML;

    // Store the callback for global access
    window[`changeDashboardPage${section.charAt(0).toUpperCase() + section.slice(1)}`] = onPageChange;
}
