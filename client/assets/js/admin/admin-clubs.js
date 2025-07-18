import { request, requestWithAuth } from '../http.js';
import { isAuthenticated, getCurrentUser } from '../auth.js';

document.addEventListener('DOMContentLoaded', function() {
    // Check admin authentication
    if (!checkAdminAccess()) {
        return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const editClubId = urlParams.get('edit');
    const isEditing = !!editClubId;

    initializeForm();
    setupClubForm(isEditing, editClubId);
    setupLeaderSearch();
    
    if (isEditing) {
        loadClubForEditing(editClubId);
    }
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

function initializeForm() {
    // Logo image preview
    setupImagePreview();

    // Email validation for club email
    setupEmailValidation();
}

function setupImagePreview() {
    const logoInput = document.getElementById('logo');
    const logoPreview = document.getElementById('logo-preview');
    const logoPreviewImg = document.getElementById('logo-preview-img');
    const logoUploadArea = document.getElementById('logo-upload-area');

    if (logoInput && logoPreview && logoPreviewImg && logoUploadArea) {
        logoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    showErrorMessage('Please select a valid image file.');
                    return;
                }

                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    showErrorMessage('Logo file size must be less than 5MB.');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    logoPreviewImg.src = e.target.result;
                    logoPreview.classList.remove('hidden');
                    logoUploadArea.classList.add('hidden');
                };
                reader.readAsDataURL(file);
            }
        });
    }
}

function setupEmailValidation() {
    const emailInput = document.getElementById('contact_email');
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            const email = emailInput.value;
            if (email && (!email.includes('@') || (!email.endsWith('.ac.ke') && !email.endsWith('.com') && !email.endsWith('.org')))) {
                emailInput.setCustomValidity('Please enter a valid email address');
            } else {
                emailInput.setCustomValidity('');
            }
        });
    }
}

function setupClubForm(isEditing, clubId) {
    const form = document.getElementById('club-form');
    const submitButton = document.getElementById('submit-button');
    const submitText = document.getElementById('submit-text');
    const submitSpinner = document.getElementById('submit-spinner');
    const submitSpinnerText = document.getElementById('submit-spinner-text');
    const saveInactiveButton = document.getElementById('save-inactive');
    const inactiveText = document.getElementById('inactive-text');
    const inactiveSpinner = document.getElementById('inactive-spinner');

    if (!form) return;

    // Update UI for editing mode
    if (isEditing) {
        updateUIForEditing();
    }

    // Form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        await handleFormSubmit(isEditing, clubId, 'active');
    });

    // Save as inactive
    if (saveInactiveButton) {
        saveInactiveButton.addEventListener('click', async function() {
            await handleFormSubmit(isEditing, clubId, 'inactive');
        });
    }

    async function handleFormSubmit(isEditing, clubId, status) {
        // Hide previous messages
        hideMessages();

        // Validate form
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // Determine which button was clicked
        const isInactive = status === 'inactive';
        const button = isInactive ? saveInactiveButton : submitButton;
        const buttonText = isInactive ? inactiveText : submitText;
        const buttonSpinner = isInactive ? inactiveSpinner : submitSpinner;

        // Show loading state
        setButtonLoading(button, buttonText, buttonSpinner, true);

        try {
            const formData = new FormData(form);
            const clubData = await processFormData(formData, status);

            let response;
            if (isEditing) {
                response = await requestWithAuth(`/clubs/index.php?action=update`, 'PATCH', {
                    id: clubId,
                    ...clubData
                });
            } else {
                response = await requestWithAuth('/clubs/index.php', 'POST', clubData);
            }

            // Show success message
            const action = isEditing ? 'updated' : 'created';
            const statusText = status === 'inactive' ? 'as inactive' : 'and activated';
            showSuccessMessage(`Club ${action} successfully ${statusText}!`);

            // Redirect after success
            setTimeout(() => {
                window.location.href = './admin-dashboard.html';
            }, 2000);

        } catch (error) {
            showErrorMessage(error.message || 'Failed to save club. Please try again.');
        } finally {
            // Reset button state
            setButtonLoading(button, buttonText, buttonSpinner, false);
        }
    }
}

function updateUIForEditing() {
    const pageTitle = document.getElementById('page-title');
    const mainTitle = document.getElementById('main-title');
    const submitText = document.getElementById('submit-text');
    const submitSpinnerText = document.getElementById('submit-spinner-text');
    const inactiveText = document.getElementById('inactive-text');

    if (pageTitle) pageTitle.textContent = 'Edit Club';
    if (mainTitle) mainTitle.textContent = 'Edit Club';
    if (submitText) submitText.textContent = 'Update Club';
    if (submitSpinnerText) submitSpinnerText.textContent = 'Updating...';
    if (inactiveText) inactiveText.textContent = 'Save Changes';
}

async function processFormData(formData, status) {
    const clubData = {
        name: formData.get('name'),
        description: formData.get('description'),
        category: formData.get('category'),
        contact_email: formData.get('contact_email'),
        leader_id: formData.get('leader_id'),
        status: status
    };

    // Add members count if provided
    const membersCount = formData.get('members_count');
    if (membersCount) {
        clubData.members_count = parseInt(membersCount) || 0;
    }

    // Handle file upload for logo
    const logoFile = formData.get('logo');
    if (logoFile && logoFile.size > 0) {
        try {
            clubData.logo = await uploadFile(logoFile);
        } catch (error) {
            console.warn('Failed to upload logo:', error);
            // Continue without logo
        }
    }

    return clubData;
}

async function uploadFile(file) {
    const uploadFormData = new FormData();
    uploadFormData.append('file', file);
    uploadFormData.append('type', 'club_logo');

    const response = await requestWithAuth('/upload/index.php', 'POST', uploadFormData);
    return response.data.url;
}

function setupLeaderSearch() {
    const leaderSearch = document.getElementById('leader_search');
    const leaderIdHidden = document.getElementById('leader_id');
    const leaderDropdown = document.getElementById('leader_dropdown');
    const leaderResults = document.getElementById('leader_results');
    const leaderLoading = document.getElementById('leader_loading');
    const leaderNoResults = document.getElementById('leader_no_results');
    
    if (!leaderSearch || !leaderIdHidden || !leaderDropdown || !leaderResults) return;
    
    let allUsers = [];
    let searchTimeout = null;
    let selectedLeader = null;
    
    // Load all users initially
    loadAllUsers();
    
    async function loadAllUsers() {
        try {
            const response = await requestWithAuth('/users/index.php?action=list&status=active&limit=1000', 'GET');
            allUsers = response.data?.users || [];
        } catch (error) {
            console.error('Error loading users:', error);
            showErrorMessage('Failed to load users. Please refresh the page.');
        }
    }
    
    // Show dropdown on focus
    leaderSearch.addEventListener('focus', function() {
        if (allUsers.length > 0) {
            showAllUsers();
            leaderDropdown.classList.remove('hidden');
        }
    });
    
    // Hide dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!leaderSearch.contains(e.target) && !leaderDropdown.contains(e.target)) {
            leaderDropdown.classList.add('hidden');
        }
    });
    
    // Search functionality
    leaderSearch.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Clear selection if input is cleared
        if (!query) {
            selectedLeader = null;
            leaderIdHidden.value = '';
            showAllUsers();
            leaderDropdown.classList.remove('hidden');
            return;
        }
        
        // Debounce search
        searchTimeout = setTimeout(() => {
            searchUsers(query);
        }, 300);
    });
    
    function searchUsers(query) {
        if (!query) {
            showAllUsers();
            return;
        }
        
        const filteredUsers = allUsers.filter(user => 
            user.first_name.toLowerCase().includes(query.toLowerCase()) ||
            user.last_name.toLowerCase().includes(query.toLowerCase()) ||
            user.email.toLowerCase().includes(query.toLowerCase()) ||
            (user.student_id && user.student_id.toLowerCase().includes(query.toLowerCase()))
        );
        
        displayUsers(filteredUsers);
        leaderDropdown.classList.remove('hidden');
    }
    
    function showAllUsers() {
        displayUsers(allUsers.slice(0, 20)); // Show first 20 users
    }
    
    function displayUsers(users) {
        leaderLoading.classList.add('hidden');
        
        if (users.length === 0) {
            leaderResults.innerHTML = '';
            leaderNoResults.classList.remove('hidden');
            return;
        }
        
        leaderNoResults.classList.add('hidden');
        
        leaderResults.innerHTML = users.map(user => `
            <div class="leader-option px-4 py-3 cursor-pointer hover:bg-gray-50 border-b border-gray-100 last:border-b-0" 
                 data-user-id="${user._id?.$oid || user._id}" 
                 data-user-name="${user.first_name} ${user.last_name}">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <img src="${user.profile_image || '../../assets/images/avatar.png'}" 
                             alt="${user.first_name} ${user.last_name}" 
                             class="w-8 h-8 rounded-full object-cover">
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-gray-900">${user.first_name} ${user.last_name}</p>
                        <p class="text-xs text-gray-500">${user.email} • ${user.student_id || 'No student ID'} • ${user.role}</p>
                    </div>
                </div>
            </div>
        `).join('');
        
        // Add click handlers to user options
        leaderResults.querySelectorAll('.leader-option').forEach(option => {
            option.addEventListener('click', function() {
                const userId = this.dataset.userId;
                const userName = this.dataset.userName;
                
                selectLeader(userId, userName);
            });
        });
    }
    
    function selectLeader(userId, userName) {
        selectedLeader = { id: userId, name: userName };
        leaderSearch.value = userName;
        leaderIdHidden.value = userId;
        leaderDropdown.classList.add('hidden');
        
        // Remove any validation errors
        leaderSearch.setCustomValidity('');
        leaderIdHidden.setCustomValidity('');
    }
    
    // Validation: ensure a user is selected
    leaderSearch.addEventListener('blur', function() {
        if (this.value && !selectedLeader) {
            this.setCustomValidity('Please select a user from the dropdown');
            leaderIdHidden.setCustomValidity('Please select a user from the dropdown');
        } else if (!this.value) {
            leaderIdHidden.value = '';
            selectedLeader = null;
        }
    });
    
    // Clear validation when user starts typing
    leaderSearch.addEventListener('input', function() {
        if (selectedLeader && this.value !== selectedLeader.name) {
            selectedLeader = null;
            leaderIdHidden.value = '';
        }
        this.setCustomValidity('');
        leaderIdHidden.setCustomValidity('');
    });
}

async function loadClubForEditing(clubId) {
    try {
        const response = await request(`/clubs/index.php?action=details&id=${clubId}`, 'GET');
        const club = response.data;

        if (!club) {
            showErrorMessage('Club not found');
            return;
        }

        // Populate form fields
        setFieldValue('name', club.name);
        setFieldValue('description', club.description);
        setFieldValue('category', club.category);
        setFieldValue('contact_email', club.contact_email);
        // Set club leader with special handling for search component
        if (club.leader_id && club.leader) {
            const leaderId = club.leader_id?.$oid || club.leader_id;
            const leaderName = `${club.leader.first_name} ${club.leader.last_name}`;
            setLeaderValue(leaderId, leaderName);
        }
        setFieldValue('members_count', club.members_count);
        setFieldValue('status', club.status);

        // Logo preview
        if (club.logo) {
            const logoPreviewImg = document.getElementById('logo-preview-img');
            const logoPreview = document.getElementById('logo-preview');
            const logoUploadArea = document.getElementById('logo-upload-area');
            
            if (logoPreviewImg && logoPreview && logoUploadArea) {
                logoPreviewImg.src = club.logo;
                logoPreview.classList.remove('hidden');
                logoUploadArea.classList.add('hidden');
            }
        }

    } catch (error) {
        console.error('Error loading club for editing:', error);
        showErrorMessage('Failed to load club details. Please try again.');
    }
}

// Utility functions
function setFieldValue(fieldId, value) {
    const field = document.getElementById(fieldId);
    if (field && value !== undefined && value !== null) {
        field.value = value;
    }
}

function setLeaderValue(leaderId, leaderName) {
    const leaderSearch = document.getElementById('leader_search');
    const leaderIdHidden = document.getElementById('leader_id');
    
    if (leaderSearch && leaderIdHidden && leaderId && leaderName) {
        leaderSearch.value = leaderName;
        leaderIdHidden.value = leaderId;
    }
}

function setButtonLoading(button, textElement, spinnerElement, isLoading) {
    if (!button) return;

    button.disabled = isLoading;
    
    if (textElement) {
        if (isLoading) {
            textElement.classList.add('hidden');
        } else {
            textElement.classList.remove('hidden');
        }
    }
    
    if (spinnerElement) {
        if (isLoading) {
            spinnerElement.classList.remove('hidden');
        } else {
            spinnerElement.classList.add('hidden');
        }
    }
}

function showErrorMessage(message) {
    const formMessage = document.getElementById('form-message');
    const errorMessage = document.getElementById('error-message');
    const successMessage = document.getElementById('success-message');
    const errorText = document.getElementById('error-text');
    
    if (formMessage) formMessage.classList.remove('hidden');
    if (errorMessage) errorMessage.classList.remove('hidden');
    if (successMessage) successMessage.classList.add('hidden');
    if (errorText) errorText.textContent = message;
    
    // Scroll to message
    if (formMessage) {
        formMessage.scrollIntoView({ behavior: 'smooth' });
    }
}

function showSuccessMessage(message) {
    const formMessage = document.getElementById('form-message');
    const successMessage = document.getElementById('success-message');
    const errorMessage = document.getElementById('error-message');
    const successText = document.getElementById('success-text');
    
    if (formMessage) formMessage.classList.remove('hidden');
    if (successMessage) successMessage.classList.remove('hidden');
    if (errorMessage) errorMessage.classList.add('hidden');
    if (successText) successText.textContent = message;
    
    // Scroll to message
    if (formMessage) {
        formMessage.scrollIntoView({ behavior: 'smooth' });
    }
}

function hideMessages() {
    const formMessage = document.getElementById('form-message');
    const errorMessage = document.getElementById('error-message');
    const successMessage = document.getElementById('success-message');
    
    if (formMessage) formMessage.classList.add('hidden');
    if (errorMessage) errorMessage.classList.add('hidden');
    if (successMessage) successMessage.classList.add('hidden');
}