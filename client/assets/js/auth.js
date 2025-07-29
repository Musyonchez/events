/**
 * Authentication Management Module
 * 
 * This module provides a comprehensive authentication layer for the USIU Events
 * application. It handles user registration, login, logout, token management,
 * email verification, and password reset functionality.
 * 
 * Key Features:
 * - JWT token-based authentication with automatic refresh
 * - Secure localStorage token management
 * - Email verification and password reset workflows
 * - User session state management
 * - Automatic logout on authentication failures
 * 
 * Dependencies:
 * - http.js: API communication layer with error handling
 * 
 * Token Management Strategy:
 * - Access tokens: Short-lived (1 hour), used for API authentication
 * - Refresh tokens: Long-lived (7 days), used to obtain new access tokens
 * - User data: Stored locally for UI personalization
 * 
 * Security Considerations:
 * - Tokens are cleared on logout and authentication failures
 * - Automatic token refresh prevents session interruption
 * - All password operations use secure backend endpoints
 */

import { request, requestWithAuth, AccessTokenExpiredError, AuthError } from './http.js';

/**
 * User registration handler
 * 
 * Registers a new user account with the USIU Events system.
 * The backend validates all data against schema requirements including
 * USIU email domain validation and password complexity rules.
 * 
 * @param {Object} userData - User registration data
 * @param {string} userData.student_id - Unique university student ID
 * @param {string} userData.first_name - User's first name
 * @param {string} userData.last_name - User's last name  
 * @param {string} userData.email - USIU email address (@usiu.ac.ke)
 * @param {string} userData.password - Password (min 8 chars)
 * @param {string} [userData.phone] - Optional phone number
 * @param {string} [userData.course] - Optional course of study
 * @param {number} [userData.year_of_study] - Optional year (1-6)
 * 
 * @returns {Promise<Object>} API response with registration status
 * @throws {Error} Validation errors or network failures
 */
export async function register(userData) {
    return await request('/auth/index.php?action=register', 'POST', userData);
}

/**
 * User login authentication
 * 
 * Authenticates user credentials and establishes a secure session.
 * On successful login, stores JWT tokens and user data in localStorage
 * for subsequent authenticated requests.
 * 
 * Authentication Flow:
 * 1. Send credentials to backend for validation
 * 2. Receive access token, refresh token, and user data
 * 3. Store tokens securely in localStorage
 * 4. Store user data for UI personalization
 * 
 * @param {Object} credentials - User login credentials
 * @param {string} credentials.email - User's email address
 * @param {string} credentials.password - User's password
 * 
 * @returns {Promise<Object>} API response with tokens and user data
 * @throws {Error} Authentication failures or network errors
 * 
 * @example
 * const result = await login({ email: 'user@usiu.ac.ke', password: 'password123' });
 * // User is now authenticated and can access protected resources
 */
export async function login(credentials) {
    try {
        const response = await request('/auth/index.php?action=login', 'POST', credentials);
        
        // Extract and store authentication tokens and user data
        if (response.data && response.data.access_token) {
            localStorage.setItem('access_token', response.data.access_token);
            localStorage.setItem('refresh_token', response.data.refresh_token);
            localStorage.setItem('user', JSON.stringify(response.data.user));
        }
        return response;
    } catch (error) {
        console.error('Login failed:', error);
        throw error;
    }
}

/**
 * User logout and session cleanup
 * 
 * Securely logs out the current user by clearing all authentication
 * data from localStorage and redirecting to the login page.
 * 
 * Security Actions:
 * - Removes access token to prevent further API requests
 * - Removes refresh token to prevent session restoration
 * - Removes user data to clear UI personalization
 * - Redirects to login page to complete logout flow
 * 
 * This function is called in multiple scenarios:
 * - User clicks logout button
 * - Authentication failures (expired refresh tokens)
 * - Security violations or token tampering
 * 
 * @example
 * logout(); // User is logged out and redirected to login page
 */
export function logout() {
    // Clear all authentication-related data from localStorage
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    localStorage.removeItem('user');
    
    // Redirect to login page to complete logout flow
    window.location.href = window.location.origin + '/pages/login.html';
}

/**
 * Authentication status checker
 * 
 * Checks if the user has a valid access token in localStorage.
 * This is a basic check that only verifies token presence, not validity.
 * The actual token validation happens on the backend during API requests.
 * 
 * Note: This function does not validate token expiration or integrity.
 * Expired or invalid tokens are handled by the http.js module during
 * API requests, which triggers automatic refresh or logout as needed.
 * 
 * @returns {boolean} True if access token exists, false otherwise
 * 
 * @example
 * if (isAuthenticated()) {
 *     // Show authenticated user interface
 * } else {
 *     // Show login interface
 * }
 */
export function isAuthenticated() {
    return !!localStorage.getItem('access_token');
}

/**
 * Current user data retrieval
 * 
 * Retrieves the authenticated user's data from localStorage.
 * User data is stored during login and contains profile information
 * extracted from the JWT token payload.
 * 
 * User Data Structure:
 * - _id: User's unique MongoDB ObjectId
 * - student_id: University student ID
 * - first_name, last_name: User's name
 * - email: USIU email address
 * - role: User role (student, admin, club_leader)
 * - profile_image: Optional profile image URL
 * 
 * @returns {Object|null} User data object or null if not authenticated
 * 
 * @example
 * const user = getCurrentUser();
 * if (user) {
 *     console.log(`Welcome, ${user.first_name}!`);
 *     if (user.role === 'admin') {
 *         // Show admin features
 *     }
 * }
 */
export function getCurrentUser() {
    const user = localStorage.getItem('user');
    return user ? JSON.parse(user) : null;
}

/**
 * Automatic token refresh mechanism
 * 
 * Refreshes the access token using the stored refresh token.
 * This function is called automatically by the http.js module when
 * an API request receives a 401 Unauthorized response.
 * 
 * Token Refresh Flow:
 * 1. Check for valid refresh token in localStorage
 * 2. Send refresh token to backend for validation
 * 3. Receive new access token if refresh token is valid
 * 4. Update localStorage with new access token
 * 5. Original API request is retried with new token
 * 
 * Security Handling:
 * - Forces logout if no refresh token is available
 * - Forces logout if refresh token is expired or invalid
 * - Logs refresh token errors for debugging
 * 
 * @returns {Promise<Object>} API response with new access token
 * @throws {Error} Refresh token failures trigger logout
 * 
 * @example
 * // This function is typically called automatically by http.js
 * try {
 *     await refreshToken();
 *     // New access token is now available for API requests
 * } catch (error) {
 *     // User has been logged out due to invalid refresh token
 * }
 */
export async function refreshToken() {
    const refreshToken = localStorage.getItem('refresh_token');
    if (!refreshToken) {
        logout(); // Force logout if no refresh token is available
        throw new Error('No refresh token found.');
    }

    try {
        const response = await request('/auth/index.php?action=refresh_token', 'POST', { refresh_token: refreshToken });
        console.log('Refresh token response:', response);
        
        // Backend returns: { "message": "...", "data": { "access_token": "..." } }
        if (response.data && response.data.access_token) {
            localStorage.setItem('access_token', response.data.access_token);
        }
        return response;
    } catch (error) {
        if (error instanceof AccessTokenExpiredError || error instanceof AuthError) {
            console.error('Refresh token failed:', error.message);
        }
        throw error;
    }
}

/**
 * Email verification token validation
 * 
 * Validates an email verification token received via email link.
 * This function is called when users click verification links sent
 * to their email after registration.
 * 
 * Verification Process:
 * 1. User registers account (email marked as unverified)
 * 2. Backend sends verification email with unique token
 * 3. User clicks email link containing token
 * 4. Frontend calls this function with token from URL
 * 5. Backend validates token and marks email as verified
 * 
 * @param {string} token - Verification token from email URL
 * @returns {Promise<Object>} API response with verification status
 * @throws {Error} Invalid or expired token errors
 * 
 * @example
 * // Extract token from URL: /verify-email.html?token=abc123
 * const urlParams = new URLSearchParams(window.location.search);
 * const token = urlParams.get('token');
 * await verifyEmailToken(token);
 */
export async function verifyEmailToken(token) {
    return await request(`/auth/verify_email.php?token=${token}`, 'GET');
}

/**
 * Resend email verification request
 * 
 * Requests a new verification email to be sent to the user.
 * This is useful when the original verification email is lost,
 * expired, or not received by the user.
 * 
 * @param {string} email - User's email address to resend verification
 * @returns {Promise<Object>} API response confirming email sent
 * @throws {Error} Invalid email or rate limiting errors
 * 
 * @example
 * await resendVerificationEmail('user@usiu.ac.ke');
 * // New verification email sent to user
 */
export async function resendVerificationEmail(email) {
    return await request('/auth/resend_verification.php', 'POST', { email });
}

/**
 * Password reset request initiation
 * 
 * Initiates the password reset process by sending a reset email
 * to the user's registered email address. The backend generates
 * a secure reset token and sends it via email.
 * 
 * Password Reset Flow:
 * 1. User enters email on forgot password page
 * 2. This function sends email to backend
 * 3. Backend validates email and generates reset token
 * 4. Reset email sent with token-containing link
 * 5. User clicks link to access password reset form
 * 
 * @param {string} email - User's registered email address
 * @returns {Promise<Object>} API response confirming reset email sent
 * @throws {Error} Invalid email or user not found errors
 * 
 * @example
 * await requestPasswordReset('user@usiu.ac.ke');
 * // Password reset email sent to user
 */
export async function requestPasswordReset(email) {
    return await request('/auth/index.php?action=reset_password', 'POST', { email });
}

/**
 * Password reset completion
 * 
 * Completes the password reset process using a valid reset token
 * and the user's new password. This function is called from the
 * password reset form after the user clicks the email link.
 * 
 * Reset Completion Flow:
 * 1. User clicks reset link from email (contains token)
 * 2. User enters new password on reset form
 * 3. This function sends token and new password to backend
 * 4. Backend validates token and updates user's password
 * 5. User can login with new password
 * 
 * @param {string} token - Password reset token from email URL
 * @param {string} newPassword - User's new password
 * @returns {Promise<Object>} API response confirming password reset
 * @throws {Error} Invalid/expired token or password validation errors
 * 
 * @example
 * // Extract token from URL and get new password from form
 * const token = new URLSearchParams(window.location.search).get('token');
 * const newPassword = document.getElementById('new-password').value;
 * await resetPassword(token, newPassword);
 */
export async function resetPassword(token, newPassword) {
    return await request('/auth/index.php?action=reset_password', 'POST', { token, password: newPassword });
}

/**
 * Authenticated password change
 * 
 * Changes the password for an authenticated user. Unlike password reset,
 * this requires the user to be logged in and know their current password.
 * 
 * Security Features:
 * - Requires valid authentication (uses requestWithAuth)
 * - Validates current password before allowing change
 * - User identity extracted from JWT token
 * - New password must meet complexity requirements
 * 
 * Change Password Flow:
 * 1. User accesses change password form (requires login)
 * 2. User enters current password and new password
 * 3. This function sends both passwords to backend
 * 4. Backend validates current password against stored hash
 * 5. If valid, backend updates password and confirms change
 * 
 * @param {string} oldPassword - User's current password
 * @param {string} newPassword - User's desired new password
 * @returns {Promise<Object>} API response confirming password change
 * @throws {Error} Authentication, current password, or validation errors
 * 
 * @example
 * await changePassword('currentPass123', 'newSecurePass456');
 * // Password successfully changed
 */
export async function changePassword(oldPassword, newPassword) {
    // User identity is extracted from JWT token, no need to send user ID
    return await requestWithAuth('/auth/index.php?action=change_password', 'POST', {
        old_password: oldPassword,
        new_password: newPassword
    });
}
