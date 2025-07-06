// General utility functions

/**
 * Displays an error message in a designated element.
 * @param {string} message - The error message to display.
 * @param {HTMLElement} errorElement - The HTML element to display the error in.
 */
function displayError(message, errorElement) {
    const errorText = errorElement.querySelector('p');
    if (errorText) {
        errorText.textContent = message;
    }
    errorElement.classList.remove('hidden');
}

/**
 * Displays a success message in a designated element.
 * @param {string} message - The success message to display.
 * @param {HTMLElement} successElement - The HTML element to display the success message in.
 */
function displaySuccess(message, successElement) {
    const successText = successElement.querySelector('p');
    if (successText) {
        successText.textContent = message;
    }
    successElement.classList.remove('hidden');
}

/**
 * Hides a message element.
 * @param {HTMLElement} element - The element to hide.
 */
function hideMessage(element) {
    element.classList.add('hidden');
}

/**
 * Toggles the loading state of a button.
 * @param {HTMLButtonElement} button - The button element.
 * @param {boolean} isLoading - Whether to show the loading state.
 */
function toggleButtonLoading(button, isLoading) {
    const buttonText = button.querySelector('span');
    const spinner = button.querySelector('div');

    if (isLoading) {
        button.disabled = true;
        buttonText.classList.add('hidden');
        spinner.classList.remove('hidden');
    } else {
        button.disabled = false;
        buttonText.classList.remove('hidden');
        spinner.classList.add('hidden');
    }
}
