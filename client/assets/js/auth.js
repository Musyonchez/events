// Authentication-related functions

/**
 * Registers a new user.
 * @param {object} userData - The user data for registration.
 * @returns {Promise<any>}
 */
export async function register(userData) {
    return await request('/auth/index.php?action=register', 'POST', userData);
}

/**
 * Logs in a user.
 * @param {object} credentials - The user's login credentials (email, password).
 * @returns {Promise<any>}
 */
export async function login(credentials) {
    const response = await request('/auth/index.php?action=login', 'POST', credentials);
    if (response.data && response.data.access_token) {
        localStorage.setItem('access_token', response.data.access_token);
        localStorage.setItem('refresh_token', response.data.refresh_token);
        localStorage.setItem('user', JSON.stringify(response.data.user));
    }
    return response;
}

/**
 * Logs out the current user by clearing stored tokens.
 */
export function logout() {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    localStorage.removeItem('user');
    // Redirect to login page or homepage
    window.location.href = './login.html';
}

/**
 * Checks if the user is currently authenticated.
 * @returns {boolean}
 */
export function isAuthenticated() {
    return !!localStorage.getItem('access_token');
}

/**
 * Gets the current user's data from local storage.
 * @returns {object|null}
 */
export function getCurrentUser() {
    const user = localStorage.getItem('user');
    return user ? JSON.parse(user) : null;
}

/**
 * Refreshes the access token using the refresh token.
 * @returns {Promise<any>}
 */
export async function refreshToken() {
    const refreshToken = localStorage.getItem('refresh_token');
    if (!refreshToken) {
        logout(); // Force logout if no refresh token is available
        return;
    }

    try {
        const data = await request('/auth/index.php?action=refresh_token', 'POST', { refresh_token: refreshToken });
        if (data.access_token) {
            localStorage.setItem('access_token', data.access_token);
        }
        return data;
    } catch (error) {
        logout(); // Force logout if refresh fails
        throw error;
    }
}

/**
 * Sends the email verification token to the backend.
 * @param {string} token - The verification token from the URL.
 * @returns {Promise<any>}
 */
export async function verifyEmailToken(token) {
    return await request(`/auth/verify_email.php?token=${token}`, 'GET');
}

/**
 * Requests a new verification email to be sent.
 * @param {string} email - The user's email address.
 * @returns {Promise<any>}
 */
export async function resendVerificationEmail(email) {
    return await request('/auth/resend_verification.php', 'POST', { email });
}