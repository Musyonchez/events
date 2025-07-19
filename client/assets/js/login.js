import { login } from './auth.js';
import { AuthError } from './http.js';
import { displayError, displaySuccess, hideMessage, toggleButtonLoading } from './utils.js';

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('login-form');
    const loginButton = document.getElementById('login-button');
    const errorMessage = document.getElementById('error-message');
    const successMessage = document.getElementById('success-message');

    // If the form doesn't exist, do nothing.
    if (!loginForm) return;

    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Hide previous messages
        hideMessage(errorMessage);
        hideMessage(successMessage);
        
        // Show loading state
        toggleButtonLoading(loginButton, true);
        
        const formData = new FormData(loginForm);
        const loginData = {
            email: formData.get('email'),
            password: formData.get('password')
        };

        try {
            const response = await login(loginData);
            
            displaySuccess('Login successful! Redirecting...', successMessage);
            
            setTimeout(() => {
                window.location.href = './dashboard.html';
            }, 1000);
            
        } catch (error) {
            let message = 'Login failed. Please check your email and password and try again.';
            if (error instanceof AuthError) {
                if (error.details?.error_type === 'email_not_verified') {
                    message = 'Please verify your email before logging in.';
                } else {
                    message = error.message;
                }
            } else if (error.message) {
                message = error.message;
            }
            displayError(message, errorMessage);
        } finally {
            // Reset button state
            toggleButtonLoading(loginButton, false);
        }
    });

    // Handle enter key on form inputs
    const inputs = loginForm.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                loginForm.dispatchEvent(new Event('submit'));
            }
        });
    });
});
