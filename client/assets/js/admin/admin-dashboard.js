import { request, requestWithAuth } from '../http.js';
import { isAuthenticated, getCurrentUser } from '../auth.js';

document.addEventListener('DOMContentLoaded', function() {
    // Check admin authentication
    if (!checkAdminAccess()) {
        return;
    }

    initializeAdminDashboard();
    setupTabNavigation();
    loadDashboardData();
});

function checkAdminAccess() {
    if (!isAuthenticated()) {
        window.location.href = '../login.html';
        return false;
    }

    const user = getCurrentUser();
    if (!user || (user.role !== 'admin' && user.role !== 'club_leader')) {
        alert('Access denied. Admin privileges required.');
        window.location.href = '../dashboard.html';
        return false;
    }

    return true;
}

function initializeAdminDashboard() {
    // Export data functionality
    const exportDataBtn = document.getElementById('export-data-btn');
    if (exportDataBtn) {
        exportDataBtn.addEventListener('click', exportPlatformData);
    }

    // Filter event listeners
    setupFilterEventListeners();
}

function setupFilterEventListeners() {
    const eventsFilter = document.getElementById('events-filter');
    const usersFilter = document.getElementById('users-filter');
    const clubsFilter = document.getElementById('clubs-filter');

    if (eventsFilter) {
        eventsFilter.addEventListener('change', function() {
            loadAllEvents(this.value);
        });
    }

    if (usersFilter) {
        usersFilter.addEventListener('change', function() {
            loadAllUsers(this.value);
        });
    }

    if (clubsFilter) {
        clubsFilter.addEventListener('change', function() {
            loadAllClubs(this.value);
        });
    }
}

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
            
            const targetContent = document.getElementById(`content-${tabId}`);
            if (targetContent) {
                targetContent.classList.remove('hidden');
            }

            // Load content for the selected tab
            loadTabContent(tabId);
        });
    });
}

async function loadDashboardData() {
    try {
        const response = await requestWithAuth('/users/index.php?action=stats', 'GET');
        const stats = response.data || {};

        // Update dashboard stats
        updateStatCard('total-events', stats.total_events || 0);
        updateStatCard('total-users', stats.total_users || 0);
        updateStatCard('total-revenue', `KSh ${(stats.total_revenue || 0).toLocaleString()}`);
        updateStatCard('active-clubs', stats.active_clubs || 0);

        // Load initial tab content (events)
        loadAllEvents();

    } catch (error) {
        console.error('Error loading dashboard data:', error);
        showErrorMessage('Failed to load dashboard statistics');
        
        // Set default values on error
        updateStatCard('total-events', 0);
        updateStatCard('total-users', 0);
        updateStatCard('total-revenue', 'KSh 0');
        updateStatCard('active-clubs', 0);
    }
}

function updateStatCard(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = value;
    }
}

function loadTabContent(tabId) {
    switch (tabId) {
        case 'events':
            loadAllEvents();
            break;
        case 'users':
            loadAllUsers();
            break;
        case 'clubs':
            loadAllClubs();
            break;
        case 'analytics':
            loadAnalytics();
            break;
    }
}

async function loadAllEvents(statusFilter = '') {
    const container = document.getElementById('events-list');
    if (!container) return;

    showLoadingState(container, 'Loading events...');
    
    try {
        const params = new URLSearchParams({
            action: 'list',
            limit: 50
        });

        if (statusFilter) {
            params.append('status', statusFilter);
        }

        const response = await requestWithAuth(`/events/index.php?${params.toString()}`, 'GET');
        const events = response.data?.events || [];

        if (events.length === 0) {
            container.innerHTML = createEmptyState(
                'No events found',
                'Get started by creating a new event.',
                '<a href="./create-event.html" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700">Create Event</a>'
            );
        } else {
            container.innerHTML = events.map(event => createEventAdminItem(event)).join('');
        }

    } catch (error) {
        console.error('Error loading events:', error);
        showErrorState(container, 'Error loading events. Please try again.');
    }
}

async function loadAllUsers(roleFilter = '') {
    const container = document.getElementById('users-list');
    if (!container) return;

    showLoadingState(container, 'Loading users...');

    try {
        const params = new URLSearchParams({
            action: 'list',
            limit: 50
        });

        if (roleFilter) {
            params.append('role', roleFilter);
        }

        const response = await requestWithAuth(`/users/index.php?${params.toString()}`, 'GET');
        const users = response.data?.users || [];

        if (users.length === 0) {
            container.innerHTML = createEmptyState('No users found', 'No users match the selected criteria.');
        } else {
            container.innerHTML = users.map(user => createUserAdminItem(user)).join('');
        }

    } catch (error) {
        console.error('Error loading users:', error);
        showErrorState(container, 'Error loading users. Please try again.');
    }
}

async function loadAllClubs(statusFilter = '') {
    const container = document.getElementById('clubs-list');
    if (!container) return;

    showLoadingState(container, 'Loading clubs...');

    try {
        const params = new URLSearchParams({
            action: 'list',
            limit: 50
        });

        if (statusFilter) {
            params.append('status', statusFilter);
        }

        const response = await requestWithAuth(`/clubs/index.php?${params.toString()}`, 'GET');
        const clubs = response.data?.clubs || [];

        if (clubs.length === 0) {
            container.innerHTML = createEmptyState(
                'No clubs found',
                'Get started by creating a new club.',
                '<button id="create-club-btn" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700">Create Club</button>'
            );
            
            // Add event listener for create club button
            const createClubBtn = document.getElementById('create-club-btn');
            if (createClubBtn) {
                createClubBtn.addEventListener('click', () => {
                    window.location.href = './create-club.html';
                });
            }
        } else {
            container.innerHTML = clubs.map(club => createClubAdminItem(club)).join('');
        }

    } catch (error) {
        console.error('Error loading clubs:', error);
        showErrorState(container, 'Error loading clubs. Please try again.');
    }
}

async function loadAnalytics() {
    try {
        const response = await requestWithAuth('/users/index.php?action=analytics', 'GET');
        const analytics = response.data || {};

        // Update analytics data
        updateAnalyticsCard('events-this-month', analytics.events_this_month || 0);
        updateAnalyticsCard('avg-attendance', `${analytics.avg_attendance || 0}%`);
        updateAnalyticsCard('popular-category', analytics.popular_category || 'N/A');
        updateAnalyticsCard('new-users-month', analytics.new_users_month || 0);
        updateAnalyticsCard('active-users', analytics.active_users || 0);
        updateAnalyticsCard('verification-rate', `${analytics.verification_rate || 0}%`);

        // Load recent activity
        loadRecentActivity();

    } catch (error) {
        console.error('Error loading analytics:', error);
        showErrorMessage('Failed to load analytics data');
    }
}

function updateAnalyticsCard(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = value;
    }
}

async function loadRecentActivity() {
    const container = document.getElementById('recent-activity');
    if (!container) return;
    
    try {
        const response = await requestWithAuth('/users/index.php?action=recent-activity', 'GET');
        const activities = response.data?.activities || [];

        if (activities.length === 0) {
            container.innerHTML = createEmptyState('No recent activity', 'No recent activity to display.');
        } else {
            container.innerHTML = activities.map(activity => createActivityItem(activity)).join('');
        }

    } catch (error) {
        console.error('Error loading recent activity:', error);
        showErrorState(container, 'Error loading recent activity.');
    }
}

// Template functions
function createEventAdminItem(event) {
    const eventDate = new Date(event.event_date);
    const eventId = event._id?.$oid || event._id;
    const statusColors = {
        published: 'bg-green-100 text-green-800',
        draft: 'bg-gray-100 text-gray-800',
        cancelled: 'bg-red-100 text-red-800',
        completed: 'bg-blue-100 text-blue-800'
    };

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
                            <span class="ml-2 ${statusColors[event.status] || 'bg-gray-100 text-gray-800'} text-xs px-2 py-1 rounded-full">${event.status}</span>
                            ${event.featured ? '<span class="ml-2 bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">Featured</span>' : ''}
                        </div>
                        <div class="mt-1 flex items-center text-sm text-gray-500">
                            <span>${eventDate.toLocaleDateString()}</span>
                            <span class="mx-2">•</span>
                            <span>${event.location || 'TBA'}</span>
                            <span class="mx-2">•</span>
                            <span>${event.current_registrations || 0} registrations</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <a href="../event-details.html?id=${eventId}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        View
                    </a>
                    <button onclick="editEvent('${eventId}')" class="text-green-600 hover:text-green-800 text-sm font-medium">
                        Edit
                    </button>
                    <button onclick="toggleEventStatus('${eventId}', '${event.status}')" class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">
                        ${event.status === 'published' ? 'Unpublish' : 'Publish'}
                    </button>
                    <button onclick="deleteEvent('${eventId}')" class="text-red-600 hover:text-red-800 text-sm font-medium">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    `;
}

function createUserAdminItem(user) {
    const userId = user._id?.$oid || user._id;
    const statusColors = {
        active: 'bg-green-100 text-green-800',
        inactive: 'bg-gray-100 text-gray-800',
        suspended: 'bg-red-100 text-red-800'
    };

    const roleColors = {
        student: 'bg-blue-100 text-blue-800',
        club_leader: 'bg-purple-100 text-purple-800',
        admin: 'bg-red-100 text-red-800'
    };

    return `
        <div class="px-6 py-4 hover:bg-gray-50">
            <div class="flex items-center justify-between">
                <div class="flex items-center flex-1">
                    <div class="flex-shrink-0">
                        <img src="${user.profile_image || '../assets/images/avatar.png'}" 
                             alt="${user.first_name}" 
                             class="w-10 h-10 rounded-full">
                    </div>
                    <div class="ml-4 flex-1">
                        <div class="flex items-center">
                            <h4 class="text-sm font-medium text-gray-900">${user.first_name} ${user.last_name}</h4>
                            <span class="ml-2 ${roleColors[user.role]} text-xs px-2 py-1 rounded-full">${user.role}</span>
                            <span class="ml-2 ${statusColors[user.status]} text-xs px-2 py-1 rounded-full">${user.status}</span>
                            ${!user.is_email_verified ? '<span class="ml-2 bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">Unverified</span>' : ''}
                        </div>
                        <div class="mt-1 text-sm text-gray-500">
                            <span>${user.email}</span>
                            <span class="mx-2">•</span>
                            <span>${user.student_id}</span>
                            <span class="mx-2">•</span>
                            <span>${user.course || 'No course'}</span>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            Joined: ${new Date(user.created_at).toLocaleDateString()}
                            ${user.last_login ? ` • Last login: ${new Date(user.last_login).toLocaleDateString()}` : ''}
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="viewUserDetails('${userId}')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        View
                    </button>
                    <button onclick="editUser('${userId}')" class="text-green-600 hover:text-green-800 text-sm font-medium">
                        Edit
                    </button>
                    <button onclick="toggleUserStatus('${userId}', '${user.status}')" class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">
                        ${user.status === 'suspended' ? 'Activate' : 'Suspend'}
                    </button>
                    <button onclick="deleteUser('${userId}')" class="text-red-600 hover:text-red-800 text-sm font-medium">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    `;
}

function createClubAdminItem(club) {
    const clubId = club._id?.$oid || club._id;
    const statusColors = {
        active: 'bg-green-100 text-green-800',
        inactive: 'bg-gray-100 text-gray-800'
    };

    return `
        <div class="px-6 py-4 hover:bg-gray-50">
            <div class="flex items-center justify-between">
                <div class="flex items-center flex-1">
                    <div class="flex-shrink-0">
                        <img src="${club.logo || '../assets/images/club-placeholder.jpg'}" 
                             alt="${club.name}" 
                             class="w-12 h-12 rounded-lg object-cover">
                    </div>
                    <div class="ml-4 flex-1">
                        <div class="flex items-center">
                            <h4 class="text-sm font-medium text-gray-900">${club.name}</h4>
                            <span class="ml-2 ${statusColors[club.status]} text-xs px-2 py-1 rounded-full">${club.status}</span>
                        </div>
                        <div class="mt-1 text-sm text-gray-500">
                            <span>${club.category}</span>
                            <span class="mx-2">•</span>
                            <span>${club.members_count} members</span>
                            <span class="mx-2">•</span>
                            <span>Leader: ${club.leader?.first_name} ${club.leader?.last_name}</span>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            ${club.contact_email}
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="viewClubDetails('${clubId}')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        View
                    </button>
                    <button onclick="editClub('${clubId}')" class="text-green-600 hover:text-green-800 text-sm font-medium">
                        Edit
                    </button>
                    <button onclick="toggleClubStatus('${clubId}', '${club.status}')" class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">
                        ${club.status === 'active' ? 'Deactivate' : 'Activate'}
                    </button>
                    <button onclick="deleteClub('${clubId}')" class="text-red-600 hover:text-red-800 text-sm font-medium">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    `;
}

function createActivityItem(activity) {
    const activityIcons = {
        user_registered: '👤',
        event_created: '📅',
        event_registration: '✅',
        club_created: '🏛️',
        admin_action: '⚙️'
    };

    return `
        <div class="px-6 py-4">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <span class="text-2xl">${activityIcons[activity.type] || '📋'}</span>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm text-gray-900">${activity.description}</p>
                    <p class="text-xs text-gray-500 mt-1">${new Date(activity.created_at).toLocaleString()}</p>
                </div>
            </div>
        </div>
    `;
}

// Utility functions
function createEmptyState(title, message, actionButton = '') {
    return `
        <div class="px-6 py-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">${title}</h3>
            <p class="mt-1 text-sm text-gray-500">${message}</p>
            ${actionButton ? `<div class="mt-6">${actionButton}</div>` : ''}
        </div>
    `;
}

function showLoadingState(container, message) {
    container.innerHTML = `
        <div class="px-6 py-4 text-center text-gray-500">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
            ${message}
        </div>
    `;
}

function showErrorState(container, message) {
    container.innerHTML = `
        <div class="px-6 py-4 text-center text-red-600">
            <svg class="mx-auto h-12 w-12 text-red-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
            ${message}
        </div>
    `;
}

function showErrorMessage(message) {
    const errorElement = document.getElementById('error-message');
    const errorText = document.getElementById('error-text');
    
    if (errorElement && errorText) {
        errorText.textContent = message;
        errorElement.classList.remove('hidden');
        
        setTimeout(() => {
            errorElement.classList.add('hidden');
        }, 5000);
    }
}

function showSuccessMessage(message) {
    const successElement = document.getElementById('success-message');
    const successText = document.getElementById('success-text');
    
    if (successElement && successText) {
        successText.textContent = message;
        successElement.classList.remove('hidden');
        
        setTimeout(() => {
            successElement.classList.add('hidden');
        }, 5000);
    }
}

// Admin action functions - Global scope for onclick handlers
window.editEvent = function(eventId) {
    window.location.href = `./create-event.html?edit=${eventId}`;
};

window.toggleEventStatus = async function(eventId, currentStatus) {
    const newStatus = currentStatus === 'published' ? 'draft' : 'published';
    try {
        await requestWithAuth(`/events/index.php?action=update`, 'PATCH', { 
            id: eventId, 
            status: newStatus 
        });
        showSuccessMessage(`Event ${newStatus === 'published' ? 'published' : 'unpublished'} successfully`);
        loadAllEvents();
    } catch (error) {
        showErrorMessage('Failed to update event status: ' + error.message);
    }
};

window.deleteEvent = async function(eventId) {
    if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
        try {
            await requestWithAuth(`/events/index.php?action=delete`, 'DELETE', { id: eventId });
            showSuccessMessage('Event deleted successfully');
            loadAllEvents();
            loadDashboardData(); // Refresh stats
        } catch (error) {
            showErrorMessage('Failed to delete event: ' + error.message);
        }
    }
};

window.viewUserDetails = function(userId) {
    // TODO: Implement user details modal or page
    showErrorMessage('User details feature coming soon');
};

window.editUser = function(userId) {
    // TODO: Implement user edit modal or page
    showErrorMessage('User edit feature coming soon');
};

window.toggleUserStatus = async function(userId, currentStatus) {
    const newStatus = currentStatus === 'suspended' ? 'active' : 'suspended';
    if (confirm(`Are you sure you want to ${newStatus === 'suspended' ? 'suspend' : 'activate'} this user?`)) {
        try {
            await requestWithAuth(`/users/index.php?action=update`, 'PATCH', { 
                id: userId, 
                status: newStatus 
            });
            showSuccessMessage(`User ${newStatus === 'suspended' ? 'suspended' : 'activated'} successfully`);
            loadAllUsers();
        } catch (error) {
            showErrorMessage('Failed to update user status: ' + error.message);
        }
    }
};

window.deleteUser = async function(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        try {
            await requestWithAuth(`/users/index.php?action=delete`, 'DELETE', { id: userId });
            showSuccessMessage('User deleted successfully');
            loadAllUsers();
            loadDashboardData(); // Refresh stats
        } catch (error) {
            showErrorMessage('Failed to delete user: ' + error.message);
        }
    }
};

window.viewClubDetails = function(clubId) {
    window.location.href = `../club-details.html?id=${clubId}`;
};

window.editClub = function(clubId) {
    window.location.href = `./create-club.html?edit=${clubId}`;
};

window.toggleClubStatus = async function(clubId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    try {
        await requestWithAuth(`/clubs/index.php?action=update`, 'PATCH', { 
            id: clubId, 
            status: newStatus 
        });
        showSuccessMessage(`Club ${newStatus === 'active' ? 'activated' : 'deactivated'} successfully`);
        loadAllClubs();
    } catch (error) {
        showErrorMessage('Failed to update club status: ' + error.message);
    }
};

window.deleteClub = async function(clubId) {
    if (confirm('Are you sure you want to delete this club? This action cannot be undone.')) {
        try {
            await requestWithAuth(`/clubs/index.php?action=delete`, 'DELETE', { id: clubId });
            showSuccessMessage('Club deleted successfully');
            loadAllClubs();
            loadDashboardData(); // Refresh stats
        } catch (error) {
            showErrorMessage('Failed to delete club: ' + error.message);
        }
    }
};

async function exportPlatformData() {
    try {
        showSuccessMessage('Preparing data export...');
        
        const response = await requestWithAuth('/admin/export.php', 'GET');
        
        // Create and trigger download
        const blob = new Blob([response.data], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `usiu-events-data-${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showSuccessMessage('Data exported successfully');
    } catch (error) {
        showErrorMessage('Failed to export data: ' + error.message);
    }
}