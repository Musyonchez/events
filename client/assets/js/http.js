
/**
 * HTTP Communication Layer
 * 
 * This module provides a centralized API communication layer for the USIU Events
 * application. It handles all HTTP requests, authentication, error management,
 * and automatic token refresh functionality.
 * 
 * Key Features:
 * - Centralized API base URL configuration
 * - Custom error classes for specific authentication scenarios
 * - Automatic JWT token attachment for authenticated requests
 * - Automatic token refresh on expiration
 * - Comprehensive error handling and user-friendly error messages
 * - Support for both authenticated and non-authenticated requests
 * 
 * Dependencies:
 * - auth.js: Token refresh and logout functionality
 * 
 * Error Handling Strategy:
 * - Network errors: Thrown as generic Error objects
 * - Authentication errors: Custom AuthError and AccessTokenExpiredError classes
 * - Validation errors: Include detailed field-specific error information
 * - HTTP errors: Include status codes and descriptive messages
 */

import { refreshToken, logout } from './auth.js';

// API configuration - configurable for different environments
const API_BASE_URL = 'http://localhost:8000/api'; // TODO: Make configurable for different environments

/**
 * Custom error class for expired access tokens
 * 
 * This error is thrown when the backend returns a 401 Unauthorized response
 * with error_type: 'access_token_expired'. It triggers automatic token refresh
 * in the requestWithAuth function.
 * 
 * @extends Error
 */
export class AccessTokenExpiredError extends Error {
    constructor(message = 'Access token expired', details) {
        super(message);
        this.name = 'AccessTokenExpiredError';
        this.details = details;
    }
}

/**
 * Custom error class for authentication and authorization failures
 * 
 * This error is thrown for various authentication problems including:
 * - Invalid credentials
 * - Missing authentication token
 * - Insufficient permissions
 * - Invalid or tampered tokens
 * 
 * @extends Error
 */
export class AuthError extends Error {
    constructor(message = 'Authentication or authorization error', details) {
        super(message);
        this.name = 'AuthError';
        this.details = details;
    }
}

/**
 * Generic API request handler
 * 
 * This is the core HTTP request function that handles all non-authenticated
 * API calls. It supports GET requests with query parameters and POST/PUT/PATCH
 * requests with JSON body data.
 * 
 * Request Processing:
 * 1. Constructs full URL from API base and endpoint
 * 2. Handles GET query parameters and POST/PUT/PATCH JSON bodies
 * 3. Makes fetch request with appropriate headers
 * 4. Processes response and handles errors appropriately
 * 
 * Error Classification:
 * - 401 with access_token_expired: AccessTokenExpiredError (for token refresh)
 * - 401/403 other: AuthError (authentication/authorization failures)
 * - Other HTTP errors: Generic Error with status and details
 * - Network errors: Re-thrown as-is
 * 
 * @param {string} endpoint - API endpoint path (e.g., '/auth/login')
 * @param {string} method - HTTP method ('GET', 'POST', 'PUT', 'PATCH', 'DELETE')
 * @param {Object} [data=null] - Request data (query params for GET, JSON body for others)
 * @returns {Promise<Object>} Parsed JSON response from API
 * @throws {AccessTokenExpiredError} When access token has expired
 * @throws {AuthError} For authentication/authorization failures
 * @throws {Error} For other HTTP errors or network failures
 * 
 * @example
 * // GET request with query parameters
 * const events = await request('/events/index.php', 'GET', { limit: 10, status: 'published' });
 * 
 * // POST request with JSON body
 * const result = await request('/auth/index.php?action=login', 'POST', { email, password });
 */
export async function request(endpoint, method, data = null) {
    // Construct full API URL
    let url = `${API_BASE_URL}${endpoint}`;
    const headers = {
        'Content-Type': 'application/json',
    };

    const config = {
        method,
        headers,
    };

    // Handle request data based on HTTP method
    if (method === 'GET') {
        // Convert data object to URL query parameters
        if (data) {
            const queryParams = new URLSearchParams(data).toString();
            url = `${url}${url.includes('?') ? '&' : '?'}${queryParams}`;
        }
    } else if (data) {
        // Add JSON body for POST/PUT/PATCH requests
        config.body = JSON.stringify(data);
    }

    try {
        // Make HTTP request
        const response = await fetch(url, config);
        const responseData = await response.json();

        // Handle non-successful HTTP status codes
        if (!response.ok) {
            const errorDetails = responseData.details;
            console.log('Error response:', { status: response.status, responseData, errorDetails });
            
            // Specific handling for expired access tokens
            if (response.status === 401 && errorDetails && errorDetails.error_type === 'access_token_expired') {
                throw new AccessTokenExpiredError(responseData.error, errorDetails);
            } 
            // General authentication/authorization errors
            else if (response.status === 401 || response.status === 403) {
                throw new AuthError(responseData.error || 'Authentication or authorization failed.', errorDetails);
            } 
            // Other HTTP errors (validation, server errors, etc.)
            else {
                const error = new Error(responseData.error || `HTTP ${response.status} ${response.statusText}: Please check your request and try again.`);
                error.status = response.status;
                error.details = errorDetails;
                error.response = { data: responseData }; // Include full response for validation errors
                throw error;
            }
        }

        return responseData;
    } catch (error) {
        // Re-throw error to be handled by calling function
        throw error;
    }
}

/**
 * Authenticated API request handler with automatic token refresh
 * 
 * This function wraps the basic request functionality with authentication
 * token management and automatic refresh capabilities. It's used for all
 * API calls that require user authentication.
 * 
 * Authentication Flow:
 * 1. Retrieves access token from localStorage
 * 2. Attaches token as Authorization Bearer header
 * 3. Makes API request with authentication
 * 4. If token expired, automatically refreshes and retries
 * 5. If refresh fails, logs user out
 * 
 * Token Refresh Process:
 * - On AccessTokenExpiredError: Calls refreshToken() from auth.js
 * - If refresh succeeds: Retries original request with new token
 * - If refresh fails: Throws AuthError to trigger logout
 * 
 * Error Handling:
 * - Same error classification as request() function
 * - Additional handling for token refresh failures
 * - Transparent retry after successful token refresh
 * 
 * @param {string} endpoint - API endpoint path
 * @param {string} method - HTTP method
 * @param {Object} [data=null] - Request data
 * @returns {Promise<Object>} API response data
 * @throws {AuthError} For authentication failures or missing tokens
 * @throws {Error} For other HTTP errors or network failures
 * 
 * @example
 * // Authenticated GET request
 * const userProfile = await requestWithAuth('/users/profile.php', 'GET');
 * 
 * // Authenticated POST request
 * const event = await requestWithAuth('/events/index.php?action=create', 'POST', eventData);
 */
export async function requestWithAuth(endpoint, method, data = null) {
    /**
     * Internal function to make authenticated requests
     * This is separated to allow retry after token refresh
     */
    const makeAuthenticatedRequest = async () => {
        // Construct full API URL
        let url = `${API_BASE_URL}${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
        };

        // Retrieve and validate access token
        const token = localStorage.getItem('access_token');
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        } else {
            throw new AuthError('Authentication required but no access token found.');
        }

        const config = {
            method,
            headers,
        };

        // Handle request data based on HTTP method
        if (method === 'GET') {
            // Convert data object to URL query parameters
            if (data) {
                const queryParams = new URLSearchParams(data).toString();
                url = `${url}${url.includes('?') ? '&' : '?'}${queryParams}`;
            }
        } else if (data) {
            // Add JSON body for POST/PUT/PATCH requests
            config.body = JSON.stringify(data);
        }

        // Make authenticated HTTP request
        const response = await fetch(url, config);
        const responseData = await response.json();

        // Handle non-successful HTTP status codes
        if (!response.ok) {
            const errorDetails = responseData.details;
            console.log('Error response:', { status: response.status, responseData, errorDetails });
            
            // Specific handling for expired access tokens
            if (response.status === 401 && errorDetails && errorDetails.error_type === 'access_token_expired') {
                throw new AccessTokenExpiredError(responseData.error, errorDetails);
            } 
            // General authentication/authorization errors
            else if (response.status === 401 || response.status === 403) {
                throw new AuthError(responseData.error || 'Authentication or authorization failed.', errorDetails);
            } 
            // Other HTTP errors (validation, server errors, etc.)
            else {
                const error = new Error(responseData.error || `HTTP ${response.status} ${response.statusText}: Please check your request and try again.`);
                error.status = response.status;
                error.details = errorDetails;
                error.response = { data: responseData }; // Include full response for validation errors
                throw error;
            }
        }

        return responseData;
    };

    try {
        // Attempt the authenticated request
        return await makeAuthenticatedRequest();
    } catch (error) {
        // Handle access token expiration with automatic refresh
        if (error instanceof AccessTokenExpiredError) {
            console.log('Access token expired. Attempting to refresh...');
            try {
                // Refresh the access token
                await refreshToken();
                console.log('Token refreshed. Retrying original request...');
                // Retry the original request with the new token
                return await makeAuthenticatedRequest();
            } catch (refreshError) {
                console.error('Failed to refresh token:', refreshError);
                // Refresh failed, session is truly expired
                throw new AuthError('Session expired. Please log in again.');
            }
        }
        // Re-throw other errors unchanged
        throw error;
    }
}

