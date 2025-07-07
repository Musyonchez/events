// Centralized API interaction logic

import { logout, refreshToken } from './auth.js';

const API_BASE_URL = 'http://localhost:8000/api'; // Adjust if your backend URL is different

/**
 * A generic function to make API requests.
 * @param {string} endpoint - The API endpoint (e.g., '/auth/login').
 * @param {string} method - The HTTP method (e.g., 'GET', 'POST').
 * @param {object} [body=null] - The request body for POST, PUT, PATCH requests.
 * @param {boolean} [requiresAuth=false] - Whether the request requires an Authorization header.
 * @returns {Promise<any>} - The JSON response from the API.
 */
async function request(endpoint, method, data = null, requiresAuth = false) {
    let url = `${API_BASE_URL}${endpoint}`;
    const headers = {
        'Content-Type': 'application/json',
    };

    if (requiresAuth) {
        const token = localStorage.getItem('access_token');
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        } else {
            // Handle cases where auth is required but no token is found
            // For now, we can just let the request fail on the server side
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
            const error = new Error(responseData.error || 'An unknown error occurred');
            error.status = response.status;
            error.details = responseData.details;

            if (error.status === 401 && error.details && error.details.error_type === 'access_token_expired') {
                console.log('Access token expired. Attempting to refresh...');
                try {
                    await refreshToken();
                    console.log('Token refreshed. Retrying original request...');
                    // Retry the original request with the new token
                    return await request(endpoint, method, data, requiresAuth);
                } catch (refreshError) {
                    console.error('Failed to refresh token:', refreshError);
                    logout(); // Force logout if refresh fails
                    throw refreshError; // Re-throw refresh error
                }
            } else if (error.status === 401 || error.status === 403) {
                // This block handles other 401/403 errors, including 'refresh_token_expired'
                // that might originate from the refreshToken() call.
                console.error('Authentication or authorization error:', error.message);
                logout(); // Force logout for other auth/authz errors
                throw error;
            }
            throw error; // Re-throw other non-OK errors
        }

        return responseData;
    } catch (error) {
        // Re-throw the error to be caught by the calling function
        throw error;
    }
}