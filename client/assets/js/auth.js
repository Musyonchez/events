document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('registerForm');
    const loginForm = document.getElementById('loginForm'); // Get login form
    const generalErrorDiv = document.getElementById('general-error');

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
                generalErrorDiv.textContent = 'An unexpected error occurred.';
            }
        } else if (generalErrorDiv) {
            generalErrorDiv.textContent = 'An unknown error occurred.';
        }
    }

    // Function to set button state (loading, success, error, reset)
    function setButtonState(button, buttonText, loader, state, message = '') {
        const originalButtonClasses = button.className;
        const originalButtonText = buttonText.textContent;

        if (!button || !buttonText || !loader) return;

        button.disabled = true; // Disable button during any state change
        button.className = originalButtonClasses; // Reset classes first
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
                button.classList.remove('bg-[#283991]', 'hover:bg-[#ffcb06]', 'hover:text-[#283991]', 'bg-red-500', 'hover:bg-red-600');
                button.classList.add('bg-green-500', 'hover:bg-green-600');
                buttonText.classList.remove('hidden');
                buttonText.textContent = message || 'Success!';
                break;
            case 'error':
                button.classList.remove('bg-[#283991]', 'hover:bg-[#ffcb06]', 'hover:text-[#283991]', 'bg-green-500', 'hover:bg-green-600');
                button.classList.add('bg-red-500', 'hover:bg-red-600');
                buttonText.classList.remove('hidden');
                buttonText.textContent = message || 'Failed!';
                break;
            case 'reset':
                button.disabled = false;
                button.className = originalButtonClasses; // Restore original classes
                buttonText.textContent = originalButtonText;
                break;
        }
    }

    if (registerForm) {
        const registerButton = document.getElementById('registerButton');
        const registerButtonText = registerButton.querySelector('#button-text');
        const registerLoader = registerButton.querySelector('#loader');

        registerForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors(); // Clear errors on new submission
            setButtonState(registerButton, registerButtonText, registerLoader, 'loading'); // Show loader when submission starts

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
                    setButtonState(registerButton, registerButtonText, registerLoader, 'success', 'Registered!');
                    setTimeout(() => {
                        window.location.href = 'login.html'; // Redirect to login page
                    }, 1500); // Show success state for 1.5 seconds
                } else {
                    setButtonState(registerButton, registerButtonText, registerLoader, 'error', 'Error!');
                    setTimeout(() => {
                        setButtonState(registerButton, registerButtonText, registerLoader, 'reset');
                        console.error('Registration error:', result);
                        if (result.details) {
                            displayErrors(result.details);
                        } else {
                            displayErrors({ general: result.error || 'An unexpected error occurred.' });
                        }
                    }, 1500); // Show error state for 1.5 seconds
                }
            } catch (error) {
                setButtonState(registerButton, registerButtonText, registerLoader, 'error', 'Network Error!');
                setTimeout(() => {
                    setButtonState(registerButton, registerButtonText, registerLoader, 'reset');
                    console.error('Network error:', error);
                    displayErrors({ general: 'An error occurred during registration. Please check your network connection.' });
                }, 1500); // Show error state for 1.5 seconds
            }
        });
    }

    if (loginForm) {
        const loginButton = document.getElementById('loginButton');
        const loginButtonText = loginButton.querySelector('#button-text');
        const loginLoader = loginButton.querySelector('#loader');

        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors();
            setButtonState(loginButton, loginButtonText, loginLoader, 'loading');

            const formData = new FormData(loginForm);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch('http://localhost:8000/api/auth/index.php?action=login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (response.ok) {
                    // Store JWT token and redirect
                    localStorage.setItem('jwt_token', result.jwt);
                    setButtonState(loginButton, loginButtonText, loginLoader, 'success', 'Logged in!');
                    setTimeout(() => {
                        window.location.href = 'dashboard.html'; // Redirect to dashboard
                    }, 1500);
                } else {
                    setButtonState(loginButton, loginButtonText, loginLoader, 'error', 'Error!');
                    setTimeout(() => {
                        setButtonState(loginButton, loginButtonText, loginLoader, 'reset');
                        console.error('Login error:', result);
                        if (result.details) {
                            displayErrors(result.details);
                        } else {
                            displayErrors({ general: result.error || 'An unexpected error occurred.' });
                        }
                    }, 1500);
                }
            } catch (error) {
                setButtonState(loginButton, loginButtonText, loginLoader, 'error', 'Network Error!');
                setTimeout(() => {
                    setButtonState(loginButton, loginButtonText, loginLoader, 'reset');
                    console.error('Network error:', error);
                    displayErrors({ general: 'An error occurred during login. Please check your network connection.' });
                }, 1500);
            }
        });
    }
});