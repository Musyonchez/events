import { request, requestWithAuth } from '../http.js';
import { isAuthenticated, getCurrentUser } from '../auth.js';

document.addEventListener('DOMContentLoaded', function() {
    // Check admin authentication
    if (!checkAdminAccess()) {
        return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const editEventId = urlParams.get('edit');
    const isEditing = !!editEventId;

    initializeForm();
    setupEventForm(isEditing, editEventId);
    loadClubs();
    
    if (isEditing) {
        loadEventForEditing(editEventId);
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
    // Registration required toggle
    const registrationRequired = document.getElementById('registration_required');
    const registrationSettings = document.getElementById('registration-settings');
    
    if (registrationRequired && registrationSettings) {
        registrationRequired.addEventListener('change', function() {
            if (this.checked) {
                registrationSettings.classList.remove('hidden');
            } else {
                registrationSettings.classList.add('hidden');
            }
        });
    }

    // Banner image preview
    setupImagePreview();

    // Date validation
    setupDateValidation();
}

function setupImagePreview() {
    const bannerInput = document.getElementById('banner_image');
    const bannerPreview = document.getElementById('banner-preview');
    const bannerPreviewImg = document.getElementById('banner-preview-img');
    const bannerUploadArea = document.getElementById('banner-upload-area');

    if (bannerInput && bannerPreview && bannerPreviewImg && bannerUploadArea) {
        bannerInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    showErrorMessage('Please select a valid image file.');
                    return;
                }

                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    showErrorMessage('Image file size must be less than 5MB.');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    bannerPreviewImg.src = e.target.result;
                    bannerPreview.classList.remove('hidden');
                    bannerUploadArea.classList.add('hidden');
                };
                reader.readAsDataURL(file);
            }
        });
    }
}

function setupDateValidation() {
    const eventDateInput = document.getElementById('event_date');
    const endDateInput = document.getElementById('end_date');
    const registrationDeadlineInput = document.getElementById('registration_deadline');

    function validateDates() {
        const eventDate = new Date(eventDateInput?.value);
        const endDate = new Date(endDateInput?.value);
        const registrationDeadline = new Date(registrationDeadlineInput?.value);
        const now = new Date();

        // Event date should be in the future
        if (eventDateInput?.value && eventDate <= now) {
            eventDateInput.setCustomValidity('Event date must be in the future');
        } else {
            eventDateInput?.setCustomValidity('');
        }

        // End date should be after start date
        if (endDateInput?.value && eventDate >= endDate) {
            endDateInput.setCustomValidity('End date must be after start date');
        } else {
            endDateInput?.setCustomValidity('');
        }

        // Registration deadline should be before event date
        if (registrationDeadlineInput?.value && registrationDeadline >= eventDate) {
            registrationDeadlineInput.setCustomValidity('Registration deadline must be before event start date');
        } else {
            registrationDeadlineInput?.setCustomValidity('');
        }
    }

    eventDateInput?.addEventListener('change', validateDates);
    endDateInput?.addEventListener('change', validateDates);
    registrationDeadlineInput?.addEventListener('change', validateDates);
}

function setupEventForm(isEditing, eventId) {
    const form = document.getElementById('event-form');
    const submitButton = document.getElementById('submit-button');
    const submitText = document.getElementById('submit-text');
    const submitSpinner = document.getElementById('submit-spinner');
    const submitSpinnerText = document.getElementById('submit-spinner-text');
    const saveDraftButton = document.getElementById('save-draft');
    const draftText = document.getElementById('draft-text');
    const draftSpinner = document.getElementById('draft-spinner');

    if (!form) return;

    // Update UI for editing mode
    if (isEditing) {
        updateUIForEditing();
    }

    // Form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        await handleFormSubmit(isEditing, eventId, 'published');
    });

    // Save as draft
    if (saveDraftButton) {
        saveDraftButton.addEventListener('click', async function() {
            await handleFormSubmit(isEditing, eventId, 'draft');
        });
    }

    async function handleFormSubmit(isEditing, eventId, status) {
        // Hide previous messages
        hideMessages();

        // Validate form
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // Determine which button was clicked
        const isDraft = status === 'draft';
        const button = isDraft ? saveDraftButton : submitButton;
        const buttonText = isDraft ? draftText : submitText;
        const buttonSpinner = isDraft ? draftSpinner : submitSpinner;

        // Show loading state
        setButtonLoading(button, buttonText, buttonSpinner, true);

        try {
            const formData = new FormData(form);
            const eventData = await processFormData(formData, status);

            let response;
            if (isEditing) {
                response = await requestWithAuth(`/events/index.php?action=update`, 'PATCH', {
                    id: eventId,
                    ...eventData
                });
            } else {
                response = await requestWithAuth('/events/index.php?action=create', 'POST', eventData);
            }

            // Show success message
            const action = isEditing ? 'updated' : 'created';
            const statusText = status === 'draft' ? 'as draft' : 'and published';
            showSuccessMessage(`Event ${action} successfully ${statusText}!`);

            // Redirect after success
            setTimeout(() => {
                if (!isEditing && response.data?.id) {
                    window.location.href = `../event-details.html?id=${response.data.id}`;
                } else {
                    window.location.href = './admin-dashboard.html';
                }
            }, 2000);

        } catch (error) {
            showErrorMessage(error.message || 'Failed to save event. Please try again.');
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
    const draftText = document.getElementById('draft-text');

    if (pageTitle) pageTitle.textContent = 'Edit Event';
    if (mainTitle) mainTitle.textContent = 'Edit Event';
    if (submitText) submitText.textContent = 'Update Event';
    if (submitSpinnerText) submitSpinnerText.textContent = 'Updating...';
    if (draftText) draftText.textContent = 'Save Changes';
}

async function processFormData(formData, status) {
    const eventData = {
        title: formData.get('title'),
        description: formData.get('description'),
        club_id: formData.get('club_id'),
        event_date: new Date(formData.get('event_date')).toISOString(),
        location: formData.get('location'),
        venue_capacity: parseInt(formData.get('venue_capacity')) || 0,
        category: formData.get('category'),
        registration_required: formData.has('registration_required'),
        featured: formData.has('featured'),
        status: status
    };

    // Add end date if provided
    if (formData.get('end_date')) {
        eventData.end_date = new Date(formData.get('end_date')).toISOString();
    }

    // Add registration settings if required
    if (eventData.registration_required) {
        if (formData.get('registration_deadline')) {
            eventData.registration_deadline = new Date(formData.get('registration_deadline')).toISOString();
        }
        eventData.max_attendees = parseInt(formData.get('max_attendees')) || 0;
        eventData.registration_fee = parseFloat(formData.get('registration_fee')) || 0;
    }

    // Process tags
    const tagsInput = formData.get('tags');
    if (tagsInput) {
        eventData.tags = tagsInput.split(',')
            .map(tag => tag.trim())
            .filter(tag => tag.length > 0)
            .slice(0, 10);
    }

    // Handle file upload for banner image
    const bannerFile = formData.get('banner_image');
    if (bannerFile && bannerFile.size > 0) {
        try {
            eventData.banner_image = await uploadFile(bannerFile);
        } catch (error) {
            console.warn('Failed to upload banner image:', error);
            // Continue without banner image
        }
    }

    return eventData;
}

async function uploadFile(file) {
    const uploadFormData = new FormData();
    uploadFormData.append('file', file);
    uploadFormData.append('type', 'event_banner');

    const response = await requestWithAuth('/upload/index.php', 'POST', uploadFormData);
    return response.data.url;
}

async function loadClubs() {
    const clubSelect = document.getElementById('club_id');
    if (!clubSelect) return;
    
    try {
        const response = await request('/clubs/index.php?action=list&status=active', 'GET');
        const clubs = response.data?.clubs || [];

        clubSelect.innerHTML = '<option value="">Select a club</option>';
        clubs.forEach(club => {
            const option = document.createElement('option');
            option.value = club._id?.$oid || club._id;
            option.textContent = club.name;
            clubSelect.appendChild(option);
        });

    } catch (error) {
        console.error('Error loading clubs:', error);
        clubSelect.innerHTML = '<option value="">Error loading clubs</option>';
        showErrorMessage('Failed to load clubs. Please refresh the page.');
    }
}

async function loadEventForEditing(eventId) {
    try {
        const response = await request(`/events/index.php?action=details&id=${eventId}`, 'GET');
        const event = response.data;

        if (!event) {
            showErrorMessage('Event not found');
            return;
        }

        // Populate basic fields
        setFieldValue('title', event.title);
        setFieldValue('description', event.description);
        setFieldValue('club_id', event.club_id?.$oid || event.club_id);
        setFieldValue('category', event.category);
        setFieldValue('location', event.location);
        setFieldValue('venue_capacity', event.venue_capacity);
        setFieldValue('status', event.status);

        // Date fields
        if (event.event_date) {
            setFieldValue('event_date', formatDateForInput(event.event_date));
        }
        if (event.end_date) {
            setFieldValue('end_date', formatDateForInput(event.end_date));
        }

        // Checkboxes
        setCheckboxValue('registration_required', event.registration_required);
        setCheckboxValue('featured', event.featured);

        // Registration settings
        if (event.registration_required) {
            const registrationSettings = document.getElementById('registration-settings');
            if (registrationSettings) {
                registrationSettings.classList.remove('hidden');
            }
            
            if (event.registration_deadline) {
                setFieldValue('registration_deadline', formatDateForInput(event.registration_deadline));
            }
            setFieldValue('max_attendees', event.max_attendees);
            setFieldValue('registration_fee', event.registration_fee);
        }

        // Tags
        if (event.tags && event.tags.length > 0) {
            setFieldValue('tags', event.tags.join(', '));
        }

        // Banner image preview
        if (event.banner_image) {
            const bannerPreviewImg = document.getElementById('banner-preview-img');
            const bannerPreview = document.getElementById('banner-preview');
            const bannerUploadArea = document.getElementById('banner-upload-area');
            
            if (bannerPreviewImg && bannerPreview && bannerUploadArea) {
                bannerPreviewImg.src = event.banner_image;
                bannerPreview.classList.remove('hidden');
                bannerUploadArea.classList.add('hidden');
            }
        }

    } catch (error) {
        console.error('Error loading event for editing:', error);
        showErrorMessage('Failed to load event details. Please try again.');
    }
}

// Utility functions
function setFieldValue(fieldId, value) {
    const field = document.getElementById(fieldId);
    if (field && value !== undefined && value !== null) {
        field.value = value;
    }
}

function setCheckboxValue(fieldId, value) {
    const field = document.getElementById(fieldId);
    if (field) {
        field.checked = !!value;
    }
}

function formatDateForInput(dateValue) {
    try {
        let date;
        if (typeof dateValue === 'object' && dateValue.$date) {
            // MongoDB date format
            date = new Date(parseInt(dateValue.$date.$numberLong));
        } else {
            date = new Date(dateValue);
        }
        
        if (isNaN(date.getTime())) {
            return '';
        }
        
        // Format for datetime-local input (YYYY-MM-DDTHH:MM)
        return date.toISOString().slice(0, 16);
    } catch (error) {
        console.warn('Error formatting date:', error);
        return '';
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