/**
 * API Service Layer Module
 * 
 * Provides a centralized API service layer with higher-level functions
 * for common operations. This module wraps the basic HTTP functionality
 * with specific business logic and endpoint knowledge.
 * 
 * Key Features:
 * - Centralized API endpoint management
 * - Business logic abstraction over HTTP calls
 * - Consistent parameter handling
 * - Authentication-aware request routing
 * - Error handling at the service level
 * 
 * Note: This module may be considered for deprecation in favor of
 * direct http.js usage to reduce abstraction layers.
 * 
 * Dependencies: http.js
 */

import { request, requestWithAuth } from './http.js';

/**
 * Fetches event details.
 * @param {string} eventId - The ID of the event to fetch.
 * @returns {Promise<any>}
 */
export async function getEventDetails(eventId) {
    return await request(`/events/index.php?action=details&id=${eventId}`, 'GET');
}

/**
 * Creates a new event.
 * @param {object} eventData - The event data.
 * @returns {Promise<any>}
 */
export async function createEvent(eventData) {
    return await requestWithAuth('/events/index.php?action=create', 'POST', eventData);
}

/**
 * Registers a user for an event.
 * @param {string} eventId - The ID of the event to register for.
 * @returns {Promise<any>}
 */
export async function registerForEvent(eventId) {
    return await requestWithAuth('/events/index.php?action=register', 'POST', { event_id: eventId });
}

/**
 * Unregisters a user from an event.
 * @param {string} eventId - The ID of the event to unregister from.
 * @returns {Promise<any>}
 */
export async function unregisterFromEvent(eventId) {
    return await requestWithAuth('/events/index.php?action=unregister', 'POST', { event_id: eventId });
}

/**
 * Fetches club details.
 * @param {string} clubId - The ID of the club to fetch.
 * @returns {Promise<any>}
 */
export async function getClubDetails(clubId) {
    return await request(`/clubs/index.php?action=details&id=${clubId}`, 'GET');
}

/**
 * Creates a new club.
 * @param {object} clubData - The club data.
 * @returns {Promise<any>}
 */
export async function createClub(clubData) {
    return await requestWithAuth('/clubs/index.php?action=create', 'POST', clubData);
}

/**
 * Joins a club.
 * @param {string} clubId - The ID of the club to join.
 * @returns {Promise<any>}
 */
export async function joinClub(clubId) {
    return await requestWithAuth('/clubs/index.php?action=join', 'POST', { club_id: clubId });
}

/**
 * Leaves a club.
 * @param {string} clubId - The ID of the club to leave.
 * @returns {Promise<any>}
 */
export async function leaveClub(clubId) {
    return await requestWithAuth('/clubs/index.php?action=leave', 'POST', { club_id: clubId });
}

/**
 * Fetches comments for an event.
 * @param {string} eventId - The ID of the event.
 * @returns {Promise<any>}
 */
export async function getEventComments(eventId) {
    return await requestWithAuth(`/comments/index.php?action=list&event_id=${eventId}&status=approved`, 'GET');
}

/**
 * Posts a new comment.
 * @param {object} commentData - The comment data.
 * @returns {Promise<any>}
 */
export async function postComment(commentData) {
    return await requestWithAuth('/comments/index.php?action=create', 'POST', commentData);
}
