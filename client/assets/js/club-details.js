import { request, requestWithAuth } from './http.js';
import { isAuthenticated, getCurrentUser } from './auth.js';

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const clubId = urlParams.get('id');
    
    if (!clubId) {
        showErrorState();
        return;
    }

    loadClubDetails(clubId);

    // Join club button
    const joinBtn = document.getElementById('join-btn');
    if (joinBtn) {
        joinBtn.addEventListener('click', handleClubJoin);
    }
});

async function loadClubDetails(clubId) {
    try {
        const response = await request(`/clubs/index.php?action=details&id=${clubId}`);
        const club = response.data;
        
        if (club && club.leader_id) {
            try {
                const leaderId = club.leader_id.$oid || club.leader_id;
                const leaderResponse = await requestWithAuth(`/users/index.php?id=${leaderId}`);
                club.leader = leaderResponse.data;
            } catch (error) {
                console.warn('Failed to load club leader:', error);
                // Continue without leader data
            }
        }

        populateClubDetails(club);
        loadClubEvents(club._id?.$oid || club._id);
        loadClubLeadership(club);
        
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('club-content').classList.remove('hidden');
        
    } catch (error) {
        console.error('Error loading club:', error);
        showErrorState();
    }
}

function populateClubDetails(club) {
    // Update page title
    document.title = `${club.name} - USIU Events`;
    
    // Breadcrumb
    document.getElementById('club-breadcrumb').textContent = club.name;
    
    // Header
    document.getElementById('club-name').textContent = club.name;
    document.getElementById('club-category-badge').textContent = club.category || 'General';
    document.getElementById('club-members-count').textContent = `${club.members_count || 0} members`;
    
    // Status badge
    const statusBadge = document.getElementById('club-status-badge');
    if (club.status === 'active') {
        statusBadge.textContent = 'Active';
        statusBadge.className = 'bg-green-500 text-white px-3 py-1 rounded-full text-sm font-semibold';
    } else {
        statusBadge.textContent = 'Inactive';
        statusBadge.className = 'bg-gray-500 text-white px-3 py-1 rounded-full text-sm font-semibold';
    }
    
    // Description
    if (club.description) {
        const shortDesc = club.description.length > 150 ? 
            club.description.substring(0, 150) + '...' : club.description;
        document.getElementById('club-description-short').textContent = shortDesc;
        document.getElementById('club-description').innerHTML = club.description.replace(/\n/g, '<br>');
    }
    
    // Logo
    document.getElementById('club-logo').src = club.logo || 'https://placehold.co/100x100';
    
    // Sidebar info
    document.getElementById('club-category-detail').textContent = club.category || 'General';
    document.getElementById('club-contact').textContent = club.contact_email || 'Not provided';
    document.getElementById('club-members-detail').textContent = `${club.members_count || 0} members`;
    
    if (club.leader) {
        document.getElementById('club-leader').textContent = `${club.leader.first_name} ${club.leader.last_name}`;
    } else {
        document.getElementById('club-leader').textContent = 'TBA';
    }
    
    if (club.created_at) {
        const createdDate = new Date(club.created_at);
        document.getElementById('club-created').textContent = createdDate.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long'
        });
    }
    
    // Check if user can manage club
    // TODO: Check if user is club leader or admin
    // if (isClubLeaderOrAdmin(club._id)) {
    //     document.getElementById('admin-actions').classList.remove('hidden');
    // }
    
    // Update join button state
    updateJoinButton(club);
}

function updateJoinButton(club) {
    const joinBtn = document.getElementById('join-btn');
    const joinStatus = document.getElementById('join-status');
    const currentUser = getCurrentUser();
    const isLoggedIn = isAuthenticated();

    if (club.status !== 'active') {
        joinBtn.disabled = true;
        joinBtn.textContent = 'Club Inactive';
        joinBtn.className = 'w-full bg-gray-400 text-white py-3 px-4 rounded-md font-semibold cursor-not-allowed';
        return;
    }
    
    if (!isLoggedIn) {
        joinBtn.textContent = 'Login to Join';
        joinBtn.onclick = () => { window.location.href = './login.html'; };
        return;
    }

    // Check if user is already a member
    const userId = currentUser._id?.$oid || currentUser._id;
    const isMember = club.members && club.members.some(memberId => {
        const memberIdStr = memberId.$oid || memberId;
        return memberIdStr === userId;
    });

    if (isMember) {
        joinStatus.classList.remove('hidden');
        joinStatus.className = 'text-center p-3 rounded-md bg-green-50 border border-green-200';
        joinStatus.querySelector('#status-text').textContent = 'You are a member of this club';
        joinBtn.classList.add('hidden');
    } else {
        joinBtn.classList.remove('hidden');
        joinStatus.classList.add('hidden');
        joinBtn.textContent = 'Join Club';
        joinBtn.disabled = false;
        joinBtn.className = 'w-full bg-blue-600 text-white py-3 px-4 rounded-md font-semibold hover:bg-blue-700 transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed';
    }
}

async function handleClubJoin() {
    const joinBtn = document.getElementById('join-btn');
    const joinText = document.getElementById('join-text');
    const joinSpinner = document.getElementById('join-spinner');
    const clubId = new URLSearchParams(window.location.search).get('id');
    
    try {
        if (!isAuthenticated()) {
            window.location.href = './login.html';
            return;
        }
        
        // Show loading state
        joinBtn.disabled = true;
        joinText.classList.add('hidden');
        joinSpinner.classList.remove('hidden');
        
        await requestWithAuth(`/clubs/index.php?action=join`, 'POST', { club_id: clubId });
        
        // Show success state
        const joinStatus = document.getElementById('join-status');
        joinStatus.classList.remove('hidden');
        joinStatus.className = 'text-center p-3 rounded-md bg-green-50 border border-green-200';
        joinStatus.querySelector('#status-text').textContent = 'Welcome to the club!';
        joinBtn.classList.add('hidden');
        
        // Update member count
        const membersCount = document.getElementById('club-members-count');
        const currentCount = parseInt(membersCount.textContent);
        membersCount.textContent = `${currentCount + 1} members`;
        document.getElementById('club-members-detail').textContent = `${currentCount + 1} members`;
        
    } catch (error) {
        alert('Failed to join club: ' + error.message);
        
        // Reset button state
        joinBtn.disabled = false;
        joinText.classList.remove('hidden');
        joinSpinner.classList.add('hidden');
    }
}

async function loadClubEvents(clubId) {
    const container = document.getElementById('club-events');
    
    try {
        const response = await request(`/events/index.php?action=list&club_id=${clubId}&limit=3&sort=date-asc`);
        const events = response.data.events;
        
        if (events.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <p class="mt-2">No events yet</p>
                    <p class="text-sm">This club hasn't organized any events recently.</p>
                </div>
            `;
        } else {
            container.innerHTML = events.map(createEventCard).join('');
        }
        
    } catch (error) {
        console.error('Error loading club events:', error);
        container.innerHTML = '<p class="text-red-500 text-center py-4">Error loading events.</p>';
    }
}

async function loadClubLeadership(club) {
    const container = document.getElementById('club-leadership');
    
    try {
        // Check if leader data is already loaded
        if (club.leader) {
            const leadership = [
                {
                    name: `${club.leader.first_name} ${club.leader.last_name}`,
                    role: "Club Leader",
                    profile_image: club.leader.profile_image || null,
                    email: club.leader.email
                }
            ];
            container.innerHTML = leadership.map(createLeadershipCard).join('');
            return;
        }
        
        if (!club.leader_id) {
            container.innerHTML = '<p class="text-gray-500">No leadership information available.</p>';
            return;
        }

        try {
            const leaderId = club.leader_id.$oid || club.leader_id;
            const leaderResponse = await requestWithAuth(`/users/index.php?id=${leaderId}`);
            const leaderData = leaderResponse.data;

            const leadership = [
                {
                    name: `${leaderData.first_name} ${leaderData.last_name}`,
                    role: "Club Leader",
                    profile_image: leaderData.profile_image || null,
                    email: leaderData.email
                }
            ];
            
            container.innerHTML = leadership.map(createLeadershipCard).join('');
        } catch (error) {
            console.warn('Failed to load club leader details:', error);
            container.innerHTML = '<p class="text-gray-500">Leadership information unavailable.</p>';
        }
        
    } catch (error) {
        console.error('Error loading club leadership:', error);
        container.innerHTML = '<p class="text-red-500">Error loading leadership information.</p>';
    }
}

function createEventCard(event) {
    const eventDate = new Date(event.event_date);
    const isUpcoming = eventDate > new Date();
    
    return `
        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
            <div class="flex items-start space-x-4">
                <div class="flex-shrink-0">
                    <img src="${event.banner_image || 'https://placehold.co/400x300'}" 
                         alt="${event.title}" 
                         class="w-16 h-16 rounded-lg object-cover">
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900 truncate">${event.title}</h3>
                        ${isUpcoming ? '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">Upcoming</span>' : '<span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-full">Past</span>'}
                    </div>
                    <div class="mt-1 flex items-center text-sm text-gray-500">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        ${eventDate.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })}
                        <span class="mx-2">•</span>
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        ${event.location || 'TBA'}
                    </div>
                    <p class="mt-2 text-sm text-gray-600 line-clamp-2">${event.description}</p>
                    <div class="mt-3">
                        <a href="./event-details.html?id=${event._id?.$oid || event._id}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            View Details →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function createLeadershipCard(leader) {
    return `
        <div class="flex items-center space-x-4 p-4 bg-gray-50 rounded-lg">
            <div class="flex-shrink-0">
                <img src="${leader.profile_image || 'https://placehold.co/100x100'}" 
                     alt="${leader.name}" 
                     class="w-12 h-12 rounded-full object-cover">
            </div>
            <div class="flex-1">
                <h4 class="text-lg font-medium text-gray-900">${leader.name}</h4>
                <p class="text-sm text-blue-600 font-medium">${leader.role}</p>
                <p class="text-sm text-gray-500">${leader.email}</p>
            </div>
        </div>
    `;
}

function showErrorState() {
    document.getElementById('loading-state').classList.add('hidden');
    document.getElementById('error-state').classList.remove('hidden');
}

// Club action functions
window.shareClub = function() {
    const url = window.location.href;
    const title = document.getElementById('club-name').textContent;
    
    if (navigator.share) {
        navigator.share({
            title: title,
            url: url
        });
    } else {
        // Fallback to copying to clipboard
        navigator.clipboard.writeText(url).then(() => {
            alert('Club link copied to clipboard!');
        });
    }
};

window.reportClub = function() {
    // TODO: Implement report functionality
    alert('Report functionality coming soon');
};

window.editClub = function() {
    const urlParams = new URLSearchParams(window.location.search);
    const clubId = urlParams.get('id');
    window.location.href = `./admin/create-club.html?edit=${clubId}`;
};

window.manageMembers = function() {
    // TODO: Implement member management
    alert('Member management functionality coming soon');
};
