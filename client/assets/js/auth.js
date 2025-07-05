document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('registerForm');
    const generalErrorDiv = document.getElementById('general-error');
    const registerButton = document.getElementById('registerButton');
    const buttonText = document.getElementById('button-text');
    const loader = document.getElementById('loader');

    // Store original button classes for resetting
    const originalButtonClasses = registerButton ? registerButton.className : '';
    const originalButtonText = buttonText ? buttonText.textContent : '';

    // Function to clear all previous error messages
    function clearErrors() {
        document.querySelectorAll('.text-red-500').forEach(span => {
            span.textContent = '';
        });
        if (generalErrorDiv) {
            generalErrorDiv.textContent = '';
        }
    }

    // Function to display error messages
    function displayErrors(errors) {
        clearErrors(); // Clear previous errors first

        if (errors) {
            // Display field-specific errors
            for (const field in errors) {
                const errorSpan = document.getElementById(`${field}-error`);
                if (errorSpan) {
                    errorSpan.textContent = errors[field];
                }
            }
            // Display general error if available and not field-specific
            if (errors.general && generalErrorDiv) {
                generalErrorDiv.textContent = errors.general;
            } else if (Object.keys(errors).length === 0 && generalErrorDiv) {
                // If there are errors but no specific details, show a generic message
                generalErrorDiv.textContent = 'Registration failed due to invalid data.';
            }
        } else if (generalErrorDiv) {
            generalErrorDiv.textContent = 'An unknown error occurred during registration.';
        }
    }

    // Function to set button state (loading, success, error, reset)
    function setButtonState(state, message = '') {
        if (!registerButton || !buttonText || !loader) return;

        registerButton.disabled = true; // Disable button during any state change
        registerButton.className = originalButtonClasses; // Reset classes first
        buttonText.classList.remove('hidden');
        loader.classList.add('hidden');
        buttonText.textContent = originalButtonText;

        switch (state) {
            case 'loading':
                buttonText.classList.add('hidden');
                loader.classList.remove('hidden');
                loader.style.borderColor = 'white';
                loader.style.borderTopColor = 'transparent';
                break;
            case 'success':
                registerButton.classList.remove('bg-[#283991]', 'hover:bg-[#ffcb06]', 'hover:text-[#283991]');
                registerButton.classList.add('bg-green-500', 'hover:bg-green-600');
                buttonText.classList.remove('hidden');
                buttonText.textContent = message || 'Success!';
                break;
            case 'error':
                registerButton.classList.remove('bg-[#283991]', 'hover:bg-[#ffcb06]', 'hover:text-[#283991]');
                registerButton.classList.add('bg-red-500', 'hover:bg-red-600');
                buttonText.classList.remove('hidden');
                buttonText.textContent = message || 'Failed!';
                break;
            case 'reset':
                registerButton.disabled = false;
                break;
        }
    }

    if (registerForm) {
        registerForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors(); // Clear errors on new submission
            setButtonState('loading'); // Show loader when submission starts

            const formData = new FormData(registerForm);
            const data = Object.fromEntries(formData.entries());

            // Convert year_of_study to integer
            if (data.year_of_study) {
                data.year_of_study = parseInt(data.year_of_study, 10);
            }

            try {
                const response = await fetch('http://localhost:8000/api/auth/index.php?action=register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (response.ok) {
                    setButtonState('success', 'Registered!');
                    setTimeout(() => {
                        window.location.href = 'login.html'; // Redirect to login page
                    }, 1500); // Show success state for 1.5 seconds
                } else {
                    setButtonState('error', 'Error!');
                    setTimeout(() => {
                        setButtonState('reset');
                        console.error('Registration error:', result);
                        if (result.details) {
                            displayErrors(result.details);
                        } else {
                            displayErrors({ general: result.error || 'An unexpected error occurred.' });
                        }
                    }, 1500); // Show error state for 1.5 seconds
                }
            } catch (error) {
                setButtonState('error', 'Network Error!');
                setTimeout(() => {
                    setButtonState('reset');
                    console.error('Network error:', error);
                    displayErrors({ general: 'An error occurred during registration. Please check your network connection.' });
                }, 1500); // Show error state for 1.5 seconds
            }
        });
    }
});