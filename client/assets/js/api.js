// Centralized API interaction logic

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
            // Create a custom error object to pass more info
            const error = new Error(responseData.error || 'An unknown error occurred');
            error.status = response.status;
            error.details = responseData.details;
            throw error;
        }

        return responseData;
    } catch (error) {
        // Re-throw the error to be caught by the calling function
        throw error;
    }
}
