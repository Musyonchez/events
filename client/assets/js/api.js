// Centralized API interaction logic

import { request, requestWithAuth } from './http.js';

// Example API calls using the request function

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
 * Fetches club details.
 * @param {string} clubId - The ID of the club to fetch.
 * @returns {Promise<any>}
 */
export async function getClubDetails(clubId) {
    return await request(`/clubs/index.php?id=${clubId}`, 'GET');
}

/**
 * Creates a new club.
 * @param {object} clubData - The club data.
 * @returns {Promise<any>}
 */
export async function createClub(clubData) {
    return await requestWithAuth('/clubs/index.php', 'POST', clubData);
}

/**
 * Fetches comments for an event.
 * @param {string} eventId - The ID of the event.
 * @returns {Promise<any>}
 */
export async function getEventComments(eventId) {
    return await request(`/comments/index.php?event_id=${eventId}`, 'GET');
}

/**
 * Posts a new comment.
 * @param {object} commentData - The comment data.
 * @returns {Promise<any>}
 */
export async function postComment(commentData) {
    return await requestWithAuth('/comments/index.php', 'POST', commentData);
}
