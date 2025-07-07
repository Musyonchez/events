// Helper functions for UI manipulation and common tasks

/**
 * Displays an error message in a designated HTML element.
 * @param {string} message - The error message to display.
 * @param {HTMLElement} element - The HTML element to display the message in.
 */
export function displayError(message, element) {
    element.textContent = message;
    element.classList.remove('hidden');
    element.classList.add('bg-red-100', 'border-red-400', 'text-red-700');
    element.classList.remove('bg-green-100', 'border-green-400', 'text-green-700');
}

/**
 * Displays a success message in a designated HTML element.
 * @param {string} message - The success message to display.
 * @param {HTMLElement} element - The HTML element to display the message in.
 */
export function displaySuccess(message, element) {
    element.textContent = message;
    element.classList.remove('hidden');
    element.classList.add('bg-green-100', 'border-green-400', 'text-green-700');
    element.classList.remove('bg-red-100', 'border-red-400', 'text-red-700');
}

/**
 * Hides a message in a designated HTML element.
 * @param {HTMLElement} element - The HTML element to hide the message from.
 */
export function hideMessage(element) {
    element.classList.add('hidden');
}

/**
 * Toggles the loading state of a button, disabling it and showing a spinner.
 * @param {HTMLButtonElement} button - The button element to modify.
 * @param {boolean} isLoading - True to show loading state, false to revert.
 */
export function toggleButtonLoading(button, isLoading) {
    if (isLoading) {
        button.disabled = true;
        button.classList.add('opacity-50', 'cursor-not-allowed');
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Loading...';
    } else {
        button.disabled = false;
        button.classList.remove('opacity-50', 'cursor-not-allowed');
        // Restore original button text based on its ID
        if (button.id === 'register-button') {
            button.innerHTML = 'Register';
        } else if (button.id === 'login-button') {
            button.innerHTML = 'Login';
        } else if (button.id === 'submit-button') { // Generic submit button
            button.innerHTML = 'Submit';
        }
    }
}