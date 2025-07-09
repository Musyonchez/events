import { requestPasswordReset, resetPassword } from './auth.js';
import { displayError, hideMessage, toggleButtonLoading } from './utils.js';

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');

    if (token) {
        // If there's a token, show the reset form directly
        showResetForm(token);
    } else {
        // If no token, show the request form
        showRequestForm();
    }

    // Setup request new link button
    document.getElementById('request-new-link').addEventListener('click', function() {
        showRequestForm();
    });
});


function hideAllSteps() {
    document.getElementById('page-header').classList.add('hidden');
    document.getElementById('token-loading').classList.add('hidden');
    document.getElementById('token-error').classList.add('hidden');
    document.getElementById('request-step').classList.add('hidden');
    document.getElementById('request-success').classList.add('hidden');
    document.getElementById('reset-step').classList.add('hidden');
    document.getElementById('reset-success').classList.add('hidden');
}

function showRequestForm() {
    // Hide all other steps
    hideAllSteps();
    
    // Show header and request form
    document.getElementById('page-header').classList.remove('hidden');
    document.getElementById('request-step').classList.remove('hidden');
    
    // Setup request form
    setupRequestForm();
}

function showResetForm(token) {
    // Hide all other steps
    hideAllSteps();
    
    // Update header for reset form
    document.getElementById('page-header').classList.remove('hidden');
    const headerTitle = document.querySelector('#page-header h2');
    headerTitle.textContent = 'Reset your password';
    
    // Show reset form
    document.getElementById('reset-step').classList.remove('hidden');
    
    // Setup reset form
    setupResetForm(token);
}

function setupRequestForm() {
    const requestForm = document.getElementById('request-form');
    const requestButton = document.getElementById('request-button');
    const requestText = document.getElementById('request-text');
    const requestSpinner = document.getElementById('request-spinner');
    const requestError = document.getElementById('request-error');
    const requestErrorText = document.getElementById('request-error-text');
    const emailInput = document.getElementById('email');

    // If the form doesn't exist, do nothing
    if (!requestForm) return;

    // Email validation for USIU domain
    emailInput.addEventListener('input', function() {
        const email = emailInput.value;
        if (email && !email.endsWith('@usiu.ac.ke')) {
            emailInput.setCustomValidity('Please use your USIU email address (@usiu.ac.ke)');
        } else {
            emailInput.setCustomValidity('');
        }
    });

    requestForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Hide previous errors
        hideMessage(requestError);
        requestErrorText.textContent = '';
        
        // Show loading state
        requestButton.disabled = true;
        requestText.classList.add('hidden');
        requestSpinner.classList.remove('hidden');
        
        const formData = new FormData(requestForm);
        const email = formData.get('email');

        try {
            const response = await requestPasswordReset(email);
            
            // Show success message
            hideAllSteps();
            document.getElementById('request-success').classList.remove('hidden');
            
        } catch (error) {
            // Show error message
            let message = 'Failed to send reset link. Please try again.';
            if (error.status === 404) {
                message = 'No account found with this email address.';
            } else if (error.message) {
                message = error.message;
            }
            
            requestErrorText.textContent = message;
            requestError.classList.remove('hidden');
        } finally {
            // Reset button state
            requestButton.disabled = false;
            requestText.classList.remove('hidden');
            requestSpinner.classList.add('hidden');
        }
    });
}

function setupResetForm(token) {
    const resetForm = document.getElementById('reset-form');
    const resetButton = document.getElementById('reset-button');
    const resetText = document.getElementById('reset-text');
    const resetSpinner = document.getElementById('reset-spinner');
    const resetError = document.getElementById('reset-error');
    const resetErrorText = document.getElementById('reset-error-text');
    const newPasswordInput = document.getElementById('new-password');
    const confirmPasswordInput = document.getElementById('confirm-new-password');

    // If the form doesn't exist, do nothing
    if (!resetForm) return;

    // Password validation
    function validatePasswords() {
        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        if (newPassword && confirmPassword && newPassword !== confirmPassword) {
            confirmPasswordInput.setCustomValidity('Passwords do not match');
        } else {
            confirmPasswordInput.setCustomValidity('');
        }
    }

    newPasswordInput.addEventListener('input', validatePasswords);
    confirmPasswordInput.addEventListener('input', validatePasswords);

    resetForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Hide previous errors
        hideMessage(resetError);
        resetErrorText.textContent = '';
        
        // Validate passwords match
        if (newPasswordInput.value !== confirmPasswordInput.value) {
            resetErrorText.textContent = 'Passwords do not match.';
            resetError.classList.remove('hidden');
            return;
        }
        
        // Show loading state
        resetButton.disabled = true;
        resetText.classList.add('hidden');
        resetSpinner.classList.remove('hidden');
        
        try {
            await resetPassword(token, newPasswordInput.value);
            
            // Show success message
            hideAllSteps();
            document.getElementById('reset-success').classList.remove('hidden');
            
        } catch (error) {
            // Show error message
            let message = 'Failed to reset password. Please try again.';
            
            if (error.details?.error_type === 'invalid_token') {
                message = 'This password reset link is invalid or has expired. Please request a new one.';
            } else if (error.details?.error_type === 'token_expired') {
                message = 'This password reset link has expired. Please request a new one.';
            } else if (error.message) {
                message = error.message;
            }
            
            resetErrorText.textContent = message;
            resetError.classList.remove('hidden');
        } finally {
            // Reset button state
            resetButton.disabled = false;
            resetText.classList.remove('hidden');
            resetSpinner.classList.add('hidden');
        }
    });
}