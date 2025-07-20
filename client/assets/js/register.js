/**
 * Registration Form Module
 * 
 * Handles user registration form functionality including validation,
 * password confirmation, and account creation. Provides comprehensive
 * registration interface with field validation and user feedback.
 * 
 * Key Features:
 * - Multi-field form validation
 * - Password confirmation checking
 * - USIU email domain validation
 * - Real-time feedback and error display
 * - Registration success handling
 * 
 * Dependencies: auth.js, utils.js
 */

import { register } from './auth.js';
import { displayError, displaySuccess, hideMessage, toggleButtonLoading } from './utils.js';

document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('register-form');
    const registerButton = document.getElementById('register-button');
    const errorMessage = document.getElementById('error-message');
    const successMessage = document.getElementById('success-message');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');

    if (!registerForm) return;

    // Password validation
    function validatePasswords() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        if (password && confirmPassword && password !== confirmPassword) {
            confirmPasswordInput.setCustomValidity('Passwords do not match');
        } else {
            confirmPasswordInput.setCustomValidity('');
        }
    }

    passwordInput.addEventListener('input', validatePasswords);
    confirmPasswordInput.addEventListener('input', validatePasswords);

    // Email validation for USIU domain
    const emailInput = document.getElementById('email');
    emailInput.addEventListener('input', function() {
        const email = emailInput.value;
        if (email && !email.endsWith('@usiu.ac.ke')) {
            emailInput.setCustomValidity('Please use your USIU email address (@usiu.ac.ke)');
        } else {
            emailInput.setCustomValidity('');
        }
    });

    registerForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Hide previous messages
        hideMessage(errorMessage);
        hideMessage(successMessage);
        
        // Validate passwords match
        if (passwordInput.value !== confirmPasswordInput.value) {
            displayError('Passwords do not match.', errorMessage);
            return;
        }
        
        // Show loading state
        toggleButtonLoading(registerButton, true);
        
        const formData = new FormData(registerForm);
        const registrationData = {
            student_id: formData.get('student_id'),
            first_name: formData.get('first_name'),
            last_name: formData.get('last_name'),
            email: formData.get('email'),
            password: formData.get('password'),
            phone: formData.get('phone'),
            course: formData.get('course'),
            year_of_study: parseInt(formData.get('year_of_study'))
        };

        try {
            const response = await register(registrationData);
            
            displaySuccess('Account created successfully! Please check your email to verify your account.', successMessage);
            
            // Clear form
            registerForm.reset();
            
            // Optional: Disable the form to prevent re-submission
            registerButton.disabled = true;
            
        } catch (error) {
            let message = 'An error occurred during registration.';
            if (error.details) {
                // Combine all messages from the details object
                message = Object.values(error.details).join('. ');
            } else if (error.message) {
                message = error.message;
            }
            displayError(message, errorMessage);
        } finally {
            // Reset button state
            toggleButtonLoading(registerButton, false);
        }
    });
});




