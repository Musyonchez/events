import { request, requestWithAuth } from './http.js';
import { isAuthenticated } from './auth.js';
import { getEventDetails, getEventComments, postComment, registerForEvent, getClubDetails } from './api.js';

let currentEventId = null;

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const eventId = urlParams.get('id');
    
    if (!eventId) {
        showErrorState();
        return;
    }

    currentEventId = eventId;
    loadEventDetails(eventId);

    // Comment form submission
    const commentForm = document.getElementById('comment-form');
    if (commentForm) {
        commentForm.addEventListener('submit', handleCommentSubmission);
    }

    // Registration button
    const registerBtn = document.getElementById('register-btn');
    if (registerBtn) {
        registerBtn.addEventListener('click', handleEventRegistration);
    }
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

async function loadEventDetails(eventId) {
    try {
        const response = await getEventDetails(eventId);
        console.log('API Response:', response);
        
        // Check if response has the expected structure
        const event = response.data || response;
        console.log('Event data:', event);
        
        if (!event || !event.title) {
            throw new Error('Event not found or invalid response structure');
        }
        
        populateEventDetails(event);
        loadComments(eventId);
        
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('event-content').classList.remove('hidden');
        
    } catch (error) {
        console.error('Error loading event:', error);
        showErrorState();
    }
}

async function loadClubInfo(clubId) {
    try {
        const response = await getClubDetails(clubId);
        const club = response.data || response;
        
        if (club && club.name) {
            // Show club info section
            const clubInfoSection = document.getElementById('event-club-info');
            const clubNameElement = document.getElementById('event-club-name');
            const clubLinkElement = document.getElementById('event-club-link');
            
            if (clubInfoSection && clubNameElement) {
                clubNameElement.textContent = club.name;
                
                // Add link to club details page
                if (clubLinkElement) {
                    const clubLinkId = club._id?.$oid || club._id;
                    clubLinkElement.href = `./club-details.html?id=${clubLinkId}`;
                }
                
                clubInfoSection.classList.remove('hidden');
            }
        }
    } catch (error) {
        console.error('Error loading club info:', error);
        // Don't show error to user for club info - it's supplementary
    }
}

function populateEventDetails(event) {
    // Update page title
    document.title = `${event.title} - USIU Events`;
    
    // Header
    document.getElementById('event-title').textContent = event.title;
    
    // Handle MongoDB date format
    const getTimestamp = (dateObj) => {
        if (!dateObj) return null;
        if (dateObj.$date && dateObj.$date.$numberLong) {
            return parseInt(dateObj.$date.$numberLong);
        }
        return new Date(dateObj).getTime();
    };

    const eventDate = new Date(getTimestamp(event.event_date));
    document.getElementById('event-date').textContent = eventDate.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    document.getElementById('event-location').textContent = event.location || 'TBA';
    document.getElementById('event-category-badge').textContent = event.category || 'General';
    
    if (event.featured) {
        document.getElementById('event-featured').classList.remove('hidden');
    }
    
    // Banner image
    if (event.banner_image) {
        const banner = document.getElementById('event-banner');
        banner.style.backgroundImage = `url(${event.banner_image})`;
    }
    
    // Description
    document.getElementById('event-description').innerHTML = event.description.replace(/\n/g, '<br>');
    
    // Tags
    if (event.tags && event.tags.length > 0) {
        const tagsSection = document.getElementById('event-tags-section');
        const tagsContainer = document.getElementById('event-tags');
        tagsSection.classList.remove('hidden');
        
        event.tags.forEach(tag => {
            const tagElement = document.createElement('span');
            tagElement.className = 'bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded-full';
            tagElement.textContent = tag;
            tagsContainer.appendChild(tagElement);
        });
    }
    
    // Event info sidebar
    document.getElementById('event-datetime').innerHTML = `
        ${eventDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}<br>
        ${eventDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
        ${event.end_date ? ` - ${new Date(getTimestamp(event.end_date)).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}` : ''}
    `;
    
    document.getElementById('event-location-detail').textContent = event.location || 'TBA';
    
    // Load and display club info if available
    const clubId = event.club_id?.$oid || event.club_id;
    if (clubId) {
        loadClubInfo(clubId);
    }
    
    // Registration section
    if (event.registration_required) {
        populateRegistrationInfo(event);
    } else {
        // Hide registration section for events that don't require registration
        document.getElementById('registration-section').innerHTML = `
            <div class="text-center p-4 bg-green-50 border border-green-200 rounded-md">
                <p class="text-green-800 font-medium">Free Event - No Registration Required</p>
                <p class="text-green-600 text-sm mt-1">Just show up and enjoy!</p>
            </div>
        `;
    }
    
    // Social media sharing section
    populateSocialMediaSharing(event);
}

function populateRegistrationInfo(event) {
    const registrationInfo = document.getElementById('registration-info');
    registrationInfo.classList.remove('hidden');
    
    // Registration fee
    const feeElement = document.getElementById('registration-fee');
    feeElement.textContent = event.registration_fee > 0 ? `KSh ${event.registration_fee}` : 'Free';
    feeElement.className = 'font-semibold text-green-600';
    
    // Capacity and registrations
    document.getElementById('event-capacity').textContent = event.max_attendees || 'Unlimited';
    document.getElementById('current-registrations').textContent = event.current_registrations || 0;
    
    // Registration deadline
    if (event.registration_deadline) {
        const getTimestamp = (dateObj) => {
            if (!dateObj) return null;
            if (dateObj.$date && dateObj.$date.$numberLong) {
                return parseInt(dateObj.$date.$numberLong);
            }
            return new Date(dateObj).getTime();
        };
        
        const deadline = new Date(getTimestamp(event.registration_deadline));
        document.getElementById('registration-deadline').textContent = deadline.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }
    
    // Progress bar
    if (event.max_attendees > 0) {
        const percentage = Math.round((event.current_registrations / event.max_attendees) * 100);
        document.getElementById('registration-percentage').textContent = `${percentage}%`;
        document.getElementById('registration-progress').style.width = `${percentage}%`;
    }
    
    // Registration button state
    updateRegistrationButton(event);
}

function populateSocialMediaSharing(event) {
    const socialMedia = event.social_media || {};
    
    // Update Facebook button
    const facebookBtn = document.getElementById('facebook-share-btn');
    if (facebookBtn) {
        if (socialMedia.facebook) {
            facebookBtn.onclick = () => window.open(socialMedia.facebook, '_blank');
            facebookBtn.disabled = false;
            facebookBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            facebookBtn.onclick = null;
            facebookBtn.disabled = true;
            facebookBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }
    
    // Update Twitter button
    const twitterBtn = document.getElementById('twitter-share-btn');
    if (twitterBtn) {
        if (socialMedia.twitter) {
            twitterBtn.onclick = () => window.open(socialMedia.twitter, '_blank');
            twitterBtn.disabled = false;
            twitterBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            twitterBtn.onclick = null;
            twitterBtn.disabled = true;
            twitterBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }
    
    // Update Instagram button
    const instagramBtn = document.getElementById('instagram-share-btn');
    if (instagramBtn) {
        if (socialMedia.instagram) {
            instagramBtn.onclick = () => window.open(socialMedia.instagram, '_blank');
            instagramBtn.disabled = false;
            instagramBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            instagramBtn.onclick = null;
            instagramBtn.disabled = true;
            instagramBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }
}

function updateRegistrationButton(event) {
    const registerBtn = document.getElementById('register-btn');
    const now = new Date();
    
    const getTimestamp = (dateObj) => {
        if (!dateObj) return null;
        if (dateObj.$date && dateObj.$date.$numberLong) {
            return parseInt(dateObj.$date.$numberLong);
        }
        return new Date(dateObj).getTime();
    };
    
    const deadline = new Date(getTimestamp(event.registration_deadline));
    
    // Check if registration is still open
    if (deadline < now) {
        registerBtn.disabled = true;
        registerBtn.textContent = 'Registration Closed';
        registerBtn.className = 'w-full bg-gray-400 text-white py-3 px-4 rounded-md font-semibold cursor-not-allowed';
    } else if (event.max_attendees > 0 && event.current_registrations >= event.max_attendees) {
        registerBtn.disabled = true;
        registerBtn.textContent = 'Event Full';
        registerBtn.className = 'w-full bg-red-400 text-white py-3 px-4 rounded-md font-semibold cursor-not-allowed';
    }
}

async function handleEventRegistration() {
    const registerBtn = document.getElementById('register-btn');
    const registerText = document.getElementById('register-text');
    const registerSpinner = document.getElementById('register-spinner');
    
    try {
        if (!isAuthenticated()) {
            window.location.href = './login.html';
            return;
        }
        
        // Show loading state
        registerBtn.disabled = true;
        registerText.classList.add('hidden');
        registerSpinner.classList.remove('hidden');
        
        await registerForEvent(currentEventId);
        
        // Show success state
        const registrationStatus = document.getElementById('registration-status');
        registrationStatus.classList.remove('hidden');
        registrationStatus.className = 'text-center p-3 rounded-md bg-green-50 border border-green-200';
        registrationStatus.querySelector('#status-text').textContent = 'Registration successful!';
        registerBtn.classList.add('hidden');
        
        showNotification('Registration successful!');
        
        // Reload event details to update registration count
        loadEventDetails(currentEventId);
        
    } catch (error) {
        showNotification('Registration failed: ' + error.message, true);
        
        // Reset button state
        registerBtn.disabled = false;
        registerText.classList.remove('hidden');
        registerSpinner.classList.add('hidden');
    }
}

async function loadComments(eventId) {
    try {
        const response = await getEventComments(eventId);
        console.log('Comments API Response:', response);
        
        // Handle different response structures
        let comments = [];
        if (response && response.data && response.data.comments && Array.isArray(response.data.comments)) {
            comments = response.data.comments;
        } else if (response && Array.isArray(response)) {
            comments = response;
        } else if (response && response.data && Array.isArray(response.data)) {
            comments = response.data;
        } else if (response && response.comments && Array.isArray(response.comments)) {
            comments = response.comments;
        }
        
        const commentsList = document.getElementById('comments-list');
        
        if (comments.length === 0) {
            commentsList.innerHTML = '<p class="text-gray-500 text-center py-4">No comments yet. Be the first to comment!</p>';
        } else {
            commentsList.innerHTML = comments.map(createCommentHTML).join('');
        }
        
        // Show comment form for logged in users
        if (isAuthenticated()) {
            document.getElementById('comment-form-section').classList.remove('hidden');
            document.getElementById('comment-login-prompt').classList.add('hidden');
        }
        
    } catch (error) {
        console.error('Error loading comments:', error);
        
        // Handle specific error cases
        if (error.message && error.message.includes('404')) {
            document.getElementById('comments-list').innerHTML = '<p class="text-gray-500 text-center py-4">No comments yet. Be the first to comment!</p>';
        } else {
            document.getElementById('comments-list').innerHTML = '<p class="text-red-500 text-center py-4">Unable to load comments. Please try again later.</p>';
        }
    }
}

function createCommentHTML(comment) {
    // Handle MongoDB date format
    const getTimestamp = (dateObj) => {
        if (!dateObj) return new Date();
        if (typeof dateObj === 'string') return new Date(dateObj);
        if (dateObj.$date && dateObj.$date.$numberLong) {
            return new Date(parseInt(dateObj.$date.$numberLong));
        }
        return new Date(dateObj);
    };
    
    const commentDate = getTimestamp(comment.created_at);
    const isPending = comment.status === 'pending';
    
    // Handle cases where user info might not be populated
    const userName = comment.user ? 
        `${comment.user.first_name} ${comment.user.last_name}` : 
        'Anonymous User';
    const userImage = comment.user?.profile_image || '../assets/images/avatar.png';
    const userAlt = comment.user?.first_name || 'User';
    
    return `
        <div class="border-b border-gray-200 py-4 last:border-b-0 ${isPending ? 'bg-yellow-50' : ''}">
            <div class="flex items-start space-x-3">
                <img src="${userImage}" 
                     alt="${userAlt}" 
                     class="w-8 h-8 rounded-full">
                <div class="flex-1">
                    <div class="flex items-center space-x-2">
                        <span class="font-medium text-gray-900">${userName}</span>
                        <span class="text-sm text-gray-500">${commentDate.toLocaleDateString()}</span>
                        ${isPending ? '<span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full">Pending Review</span>' : ''}
                    </div>
                    <p class="mt-1 text-gray-700 ${isPending ? 'text-opacity-80' : ''}">${comment.content}</p>
                </div>
            </div>
        </div>
    `;
}

async function handleCommentSubmission(e) {
    e.preventDefault();
    
    const commentText = document.getElementById('comment-text');
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const submitText = document.getElementById('comment-submit-text');
    const submitSpinner = document.getElementById('comment-submit-spinner');
    
    try {
        // Show loading state
        submitBtn.disabled = true;
        submitText.classList.add('hidden');
        submitSpinner.classList.remove('hidden');
        
        const newComment = await postComment({
            event_id: currentEventId,
            content: commentText.value
        });
        
        // Clear form
        commentText.value = '';
        
        // Refresh comments list to show the new comment
        await loadComments(currentEventId);
        
        showNotification('Comment posted successfully!');
        
    } catch (error) {
        showNotification('Failed to post comment: ' + error.message, true);
    } finally {
        // Reset button state
        submitBtn.disabled = false;
        submitText.classList.remove('hidden');
        submitSpinner.classList.add('hidden');
    }
}

function showErrorState() {
    document.getElementById('loading-state').classList.add('hidden');
    document.getElementById('error-state').classList.remove('hidden');
}

// Copy event link functionality
window.copyEventLink = function() {
    const url = window.location.href;
    navigator.clipboard.writeText(url).then(() => {
        showNotification('Event link copied to clipboard!');
    }).catch(() => {
        showNotification('Failed to copy link', true);
    });
};