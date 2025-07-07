// Centralized HTTP request utility

const API_BASE_URL = 'http://localhost:8000/api'; // TODO: Make configurable for different environments

// Custom error classes for specific authentication issues
export class AccessTokenExpiredError extends Error {
    constructor(message = 'Access token expired', details) {
        super(message);
        this.name = 'AccessTokenExpiredError';
        this.details = details;
    }
}

export class AuthError extends Error {
    constructor(message = 'Authentication or authorization error', details) {
        super(message);
        this.name = 'AuthError';
        this.details = details;
    }
}

/**
 * A generic function to make API requests.
 * @param {string} endpoint - The API endpoint (e.g., '/auth/login').
 * @param {string} method - The HTTP method (e.g., 'GET', 'POST').
 * @param {object} [data=null] - The request body for POST, PUT, PATCH requests, or query params for GET.
 * @param {boolean} [requiresAuth=false] - Whether the request requires an Authorization header.
 * @returns {Promise<any>} - The JSON response from the API.
 * @throws {AccessTokenExpiredError} If the access token has expired.
 * @throws {AuthError} If there's another authentication/authorization error.
 * @throws {Error} For other non-OK HTTP responses or network errors.
 */
export async function request(endpoint, method, data = null, requiresAuth = false) {
    let url = `${API_BASE_URL}${endpoint}`;
    const headers = {
        'Content-Type': 'application/json',
    };

    if (requiresAuth) {
        const token = localStorage.getItem('access_token');
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        } else {
            // If auth is required but no token, throw an error immediately
            throw new AuthError('Authentication required but no access token found.');
        }
    }

    const config = {
        method,
        headers,
    };

    if (method === 'GET') {
        if (data) {
            const queryParams = new URLSearchParams(data).toString();
            url = `${url}${url.includes('?') ? '&' : '?'}${queryParams}`;
        }
    } else if (data) {
        config.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(url, config);
        const responseData = await response.json();

        if (!response.ok) {
            const errorDetails = responseData.details;
            if (response.status === 401 && errorDetails && errorDetails.error_type === 'access_token_expired') {
                throw new AccessTokenExpiredError(responseData.error, errorDetails);
            } else if (response.status === 401 || response.status === 403) {
                throw new AuthError(responseData.error || 'Authentication or authorization failed.', errorDetails);
            } else {
                const error = new Error(responseData.error || 'An unknown error occurred');
                error.status = response.status;
                error.details = errorDetails;
                throw error;
            }
        }

        return responseData;
    } catch (error) {
        // Re-throw the error to be caught by the calling function
        throw error;
    }
}
