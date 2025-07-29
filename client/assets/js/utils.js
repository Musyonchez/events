/**
 * UI Utilities Module
 * 
 * This module provides common utility functions for UI manipulation,
 * message display, and user interface state management across the
 * USIU Events application.
 * 
 * Key Features:
 * - Consistent error and success message styling
 * - Button loading state management
 * - Tailwind CSS class management utilities
 * - Reusable UI interaction patterns
 * 
 * Design Philosophy:
 * - All functions are pure and side-effect focused on DOM manipulation
 * - Consistent Tailwind CSS styling patterns
 * - Accessible color schemes for success/error states
 * - Button state management with loading indicators
 * 
 * Usage Pattern:
 * These utilities are typically used in form handling, API response
 * processing, and user feedback scenarios across all application pages.
 */

/**
 * Error message display utility
 * 
 * Displays an error message in a designated HTML element with
 * consistent Tailwind CSS error styling. Automatically switches
 * from success to error state if needed.
 * 
 * Styling Applied:
 * - Background: Light red (bg-red-100)
 * - Border: Red (border-red-400)
 * - Text: Dark red (text-red-700)
 * - Visibility: Removes 'hidden' class
 * 
 * @param {string} message - The error message to display
 * @param {HTMLElement} element - The HTML element to style and populate
 * 
 * @example
 * const errorDiv = document.getElementById('error-message');
 * displayError('Invalid email format', errorDiv);
 */
export function displayError(message, element) {
    element.textContent = message;
    element.classList.remove('hidden');
    // Apply error styling
    element.classList.add('bg-red-100', 'border-red-400', 'text-red-700');
    // Remove success styling in case of state change
    element.classList.remove('bg-green-100', 'border-green-400', 'text-green-700');
}

/**
 * Success message display utility
 * 
 * Displays a success message in a designated HTML element with
 * consistent Tailwind CSS success styling. Automatically switches
 * from error to success state if needed.
 * 
 * Styling Applied:
 * - Background: Light green (bg-green-100)
 * - Border: Green (border-green-400)
 * - Text: Dark green (text-green-700)
 * - Visibility: Removes 'hidden' class
 * 
 * @param {string} message - The success message to display
 * @param {HTMLElement} element - The HTML element to style and populate
 * 
 * @example
 * const successDiv = document.getElementById('success-message');
 * displaySuccess('Registration successful!', successDiv);
 */
export function displaySuccess(message, element) {
    element.textContent = message;
    element.classList.remove('hidden');
    // Apply success styling
    element.classList.add('bg-green-100', 'border-green-400', 'text-green-700');
    // Remove error styling in case of state change
    element.classList.remove('bg-red-100', 'border-red-400', 'text-red-700');
}

/**
 * Message visibility control utility
 * 
 * Hides a message element by adding the Tailwind CSS 'hidden' class.
 * This is typically used to clear previous success or error messages
 * before displaying new ones or when resetting form states.
 * 
 * @param {HTMLElement} element - The HTML element to hide
 * 
 * @example
 * const messageDiv = document.getElementById('form-message');
 * hideMessage(messageDiv); // Message is now hidden
 */
export function hideMessage(element) {
    element.classList.add('hidden');
}

/**
 * Button loading state management
 * 
 * Toggles a button between normal and loading states with visual feedback.
 * In loading state, the button is disabled and shows a spinner animation.
 * This provides clear user feedback during asynchronous operations.
 * 
 * Loading State Features:
 * - Button becomes disabled to prevent multiple clicks
 * - Visual opacity reduction (opacity-50)
 * - Cursor changes to not-allowed
 * - Font Awesome spinner with animation
 * - Generic "Loading..." text
 * 
 * Normal State Restoration:
 * - Re-enables button functionality
 * - Restores normal styling
 * - Sets appropriate button text based on button ID
 * 
 * Supported Button IDs:
 * - 'register-button': Restores to 'Register'
 * - 'login-button': Restores to 'Login' 
 * - 'submit-button': Restores to 'Submit'
 * 
 * @param {HTMLButtonElement} button - The button element to modify
 * @param {boolean} isLoading - True for loading state, false for normal state
 * 
 * @example
 * const submitBtn = document.getElementById('register-button');
 * toggleButtonLoading(submitBtn, true);  // Show loading
 * // ... perform async operation ...
 * toggleButtonLoading(submitBtn, false); // Restore normal state
 */
export function toggleButtonLoading(button, isLoading) {
    if (isLoading) {
        // Enable loading state
        button.disabled = true;
        button.classList.add('opacity-50', 'cursor-not-allowed');
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Loading...';
    } else {
        // Restore normal state
        button.disabled = false;
        button.classList.remove('opacity-50', 'cursor-not-allowed');
        
        // Restore original button text based on its ID
        if (button.id === 'register-button') {
            button.innerHTML = 'Register';
        } else if (button.id === 'login-button') {
            button.innerHTML = 'Login';
        } else if (button.id === 'submit-button') {
            button.innerHTML = 'Submit';
        }
        // Note: For other button IDs, the text won't be restored automatically.
        // Consider storing original text in a data attribute for more flexibility.
    }
}