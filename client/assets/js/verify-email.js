/**
 * Email Verification Module
 * 
 * Handles email verification functionality for the USIU Events system.
 * Supports both token-based verification from email links and manual
 * verification email resending.
 */

import { request } from './http.js';
import { resendVerificationEmail, verifyEmailToken } from './auth.js';
import { displayError, hideMessage, toggleButtonLoading } from './utils.js';

document.addEventListener('DOMContentLoaded', function() {
    // Get token from URL if present
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');

    if (token) {
        // If there's a token, attempt verification
        verifyEmail(token);
    } else {
        // If no token, show resend form
        showResendForm();
    }

    // Setup resend verification button (from error state)
    const resendButton = document.getElementById('resend-verification');
    if (resendButton) {
        resendButton.addEventListener('click', function() {
            showResendForm();
        });
    }

    // Setup resend form submission
    setupResendForm();

    /**
     * Verify email using token
     */
    async function verifyEmail(token) {
        try {
            await verifyEmailToken(token);

            // Show success state
            document.getElementById('loading-state').classList.add('hidden');
            document.getElementById('success-state').classList.remove('hidden');

        } catch (error) {
            // Show error state
            document.getElementById('loading-state').classList.add('hidden');
            
            const errorMessage = document.getElementById('error-message');
            
            // Handle different error types based on error.details?.error_type
            if (error.details?.error_type === 'token_expired') {
                errorMessage.textContent = 'This verification link has expired. Please request a new one.';
            } else if (error.details?.error_type === 'invalid_token') {
                errorMessage.textContent = 'This verification link is invalid. Please request a new one.';
            } else if (error.details?.error_type === 'already_verified') {
                errorMessage.textContent = 'Your email has already been verified. You can now log in.';
                // Hide resend button for already verified emails
                const resendBtn = document.getElementById('resend-verification');
                if (resendBtn) resendBtn.classList.add('hidden');
            } else if (error.details?.error_type === 'verification_failed') {
                errorMessage.textContent = 'Email verification failed due to an unexpected issue. Please try again.';
            } else {
                // Fallback for other errors or generic messages from the backend
                errorMessage.textContent = error.message || 'An unexpected error occurred during verification. Please try again.';
            }

            document.getElementById('error-state').classList.remove('hidden');
        }
    }

    /**
     * Show resend form state
     */
    function showResendForm() {
        // Hide all other states
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('success-state').classList.add('hidden');
        document.getElementById('error-state').classList.add('hidden');
        
        // Show resend form
        document.getElementById('resend-form-state').classList.remove('hidden');
    }

    /**
     * Setup resend verification form
     */
    function setupResendForm() {
        const resendForm = document.getElementById('resend-form');
        const resendFormButton = document.getElementById('resend-form-button');
        const resendErrorMessageDiv = document.getElementById('resend-error-message');
        const resendSuccessMessageDiv = document.getElementById('resend-success-message');
        const resendErrorText = document.getElementById('resend-error-text');

        if (!resendForm) return; // Form might not exist on this page

        resendForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            // Hide previous messages and clear error text
            hideMessage(resendErrorMessageDiv);
            hideMessage(resendSuccessMessageDiv);
            if (resendErrorText) resendErrorText.textContent = '';

            // Show loading state
            toggleButtonLoading(resendFormButton, true);

            const formData = new FormData(resendForm);
            const email = formData.get('email');

            try {
                const response = await resendVerificationEmail(email);

                // Check the specific message from the backend
                if (response.message === 'If an account with that email exists, a new verification link has been sent.') {
                    if (resendErrorText) resendErrorText.textContent = 'If an account with that email exists, a new verification link has been sent.';
                    resendErrorMessageDiv.classList.remove('hidden');
                    hideMessage(resendSuccessMessageDiv);
                } else {
                    // Show success message and ensure error is hidden
                    resendSuccessMessageDiv.classList.remove('hidden');
                    hideMessage(resendErrorMessageDiv);
                }

                // Clear form
                resendForm.reset();

            } catch (error) {
                // Show error message and ensure success is hidden
                hideMessage(resendSuccessMessageDiv);

                // Handle different error types
                let message = 'Failed to send verification email. Please try again.';
                if (error.status === 404) {
                    message = 'No account found with this email address.';
                } else if (error.status === 400 && error.details?.error_type === 'already_verified') {
                    message = 'This email is already verified. You can log in now.';
                } else if (error.message) {
                    message = error.message;
                }
                displayError(message, resendErrorMessageDiv);
            } finally {
                // Reset button state
                toggleButtonLoading(resendFormButton, false);
            }
        });

        // Email validation for USIU domain
        const emailInput = document.getElementById('email');
        if (emailInput) {
            emailInput.addEventListener('input', function() {
                const email = emailInput.value;
                if (email && !email.endsWith('@usiu.ac.ke')) {
                    emailInput.setCustomValidity('Please use your USIU email address (@usiu.ac.ke)');
                } else {
                    emailInput.setCustomValidity('');
                }
            });
        }
    }
});