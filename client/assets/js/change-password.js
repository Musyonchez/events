import { changePassword, logout } from './auth.js';

document.addEventListener('DOMContentLoaded', function() {
    setupChangeForm();
});

function setupChangeForm() {
    const changeForm = document.getElementById('change-form');
    const changeButton = document.getElementById('change-button');
    const changeText = document.getElementById('change-text');
    const changeSpinner = document.getElementById('change-spinner');
    const changeError = document.getElementById('change-error');
    const changeErrorText = document.getElementById('change-error-text');
    const currentPasswordInput = document.getElementById('current-password');
    const newPasswordInput = document.getElementById('new-password');
    const confirmPasswordInput = document.getElementById('confirm-password');

    // If the form doesn't exist, do nothing.
    if (!changeForm) return;

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

    changeForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Hide previous errors
        changeError.classList.add('hidden');

        // Validate passwords match
        if (newPasswordInput.value !== confirmPasswordInput.value) {
            changeErrorText.textContent = 'Passwords do not match.';
            changeError.classList.remove('hidden');
            return;
        }

        // Show loading state
        changeButton.disabled = true;
        changeText.classList.add('hidden');
        changeSpinner.classList.remove('hidden');

        try {
            await changePassword(currentPasswordInput.value, newPasswordInput.value);

            // Call logout function (clears tokens and redirects) - same as when refresh token fails
            logout();
            
            // Show success message immediately
            document.getElementById('change-step').classList.add('hidden');
            document.getElementById('change-success').classList.remove('hidden');
            
            // Update the success message text to match login pattern
            const successTitle = document.querySelector('#change-success h3');
            const successText = document.querySelector('#change-success p');
            successTitle.textContent = 'Password changed successfully! Redirecting...';
            successText.textContent = 'Your password has been updated. Redirecting to login...';
            
            // Redirect after showing message (like login does)
            setTimeout(() => {
                window.location.href = './login.html';
            }, 3000);

        } catch (error) {
            // Show error message
            changeErrorText.textContent = error.message || 'Failed to change password. Please try again.';
            changeError.classList.remove('hidden');
        } finally {
            // Reset button state
            changeButton.disabled = false;
            changeText.classList.remove('hidden');
            changeSpinner.classList.add('hidden');
        }
    });
}