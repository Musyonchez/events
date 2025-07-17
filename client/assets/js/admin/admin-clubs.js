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
    loadUsers();
    
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

async function loadUsers() {
    const leaderSelect = document.getElementById('leader_id');
    if (!leaderSelect) return;
    
    try {
        const response = await requestWithAuth('/users/index.php?action=list&role=student&status=active', 'GET');
        const users = response.data?.users || [];

        leaderSelect.innerHTML = '<option value="">Select a club leader</option>';
        users.forEach(user => {
            const option = document.createElement('option');
            option.value = user._id?.$oid || user._id;
            option.textContent = `${user.first_name} ${user.last_name} (${user.student_id})`;
            leaderSelect.appendChild(option);
        });

    } catch (error) {
        console.error('Error loading users:', error);
        leaderSelect.innerHTML = '<option value="">Error loading users</option>';
        showErrorMessage('Failed to load users. Please refresh the page.');
    }
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
        setFieldValue('leader_id', club.leader_id?.$oid || club.leader_id);
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