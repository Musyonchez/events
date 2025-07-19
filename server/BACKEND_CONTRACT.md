# Backend Contract for Frontend Developers

This document outlines the essential information a frontend developer needs to interact with the USIU Events API backend. It covers data structures, authentication mechanisms, error handling, and file upload specifics.

**Note:** This document complements `API_ENDPOINTS.md`, which provides the specific URLs for each API action.

---

## 1. Data Structures (Schemas & Models)

All data exchanged with the API (both requests and responses) is in **JSON format**.

### Common Data Types:

*   **IDs:** All MongoDB `_id` fields are represented as **24-character hexadecimal strings**. When sending IDs in requests (e.g., `club_id`, `event_id`, `user_id`), ensure they are valid strings.
*   **Dates/Times:** All date and time values are expected and returned in **ISO 8601 format** (e.g., `YYYY-MM-DDTHH:MM:SSZ`). The `Z` suffix indicates UTC (Coordinated Universal Time). The frontend should handle conversion to/from local time zones for display/input.
*   **Booleans:** Represented as standard JSON `true` or `false`.
*   **Numbers:** Integers and floats as standard JSON numbers.
*   **Arrays:** Standard JSON arrays.

### Key Resource Fields (Examples - refer to backend schemas for full details):

*   **User:**
    *   `_id` (string): User's unique ID.
    *   `student_id` (string): Unique student identifier.
    *   `first_name`, `last_name` (string)
    *   `email` (string): Valid email format, must end with `@usiu.ac.ke`.
    *   `password` (string): Min 8 characters.
    *   `profile_image` (string, URL): URL to user's profile picture.
    *   `role` (string): `student`, `admin`, `club_leader`.
    *   `is_email_verified` (boolean): Indicates if email is verified.
    *   `created_at`, `updated_at` (ISO 8601 string)
*   **Event:**
    *   `_id` (string): Event's unique ID.
    *   `title` (string), `description` (string)
    *   `club_id` (string): ID of the organizing club.
        *   `event_date`, `end_date` (ISO 8601 string)
    *   `location` (string)
    *   `max_attendees` (int): 0 for no limit.
    *   `current_registrations` (int)
    *   `registered_users` (array of strings): Array of user IDs who registered.
    *   `banner_image` (string, URL): URL to event banner.
    *   `status` (string): `draft`, `published`, `cancelled`, `completed`.
*   **Club:**
    *   `_id` (string): Club's unique ID.
    *   `name` (string), `description` (string)
    *   `leader_id` (string): ID of the user leading the club.
    *   `logo` (string, URL): URL to club logo.
    *   `members_count` (int)
    *   `status` (string): `active`, `inactive`.
*   **Comment:**
    *   `_id` (string): Comment's unique ID.
    *   `event_id` (string): ID of the event the comment belongs to.
    *   `user_id` (string): ID of the user who posted the comment.
    *   `content` (string)
    *   `parent_comment_id` (string, nullable): For replies. Only one level of nesting.
    *   `status` (string): `pending`, `approved`, `rejected`.
    *   `flagged` (boolean): Whether the comment has been flagged by moderators.
    *   `created_at`, `updated_at` (ISO 8601 string)
    *   **Admin List Response includes:**
        *   `user` (object): Complete user details (first_name, last_name, profile_image, etc.)
        *   `event` (object): Complete event details (title, description, event_date, etc.)
        *   `event_title` (string): Quick access field for event title

---

## 2. Authentication & Authorization

The API uses **JWT (JSON Web Tokens)** for authentication.

### Flow:

1.  **Login:** Send `email` and `password` to `/api/auth/index.php?action=login`.
2.  **Response:** On success, receive `access_token` (JWT) and `refresh_token`.
3.  **Authenticated Requests:** Include the `access_token` in the `Authorization` header of subsequent requests: `Authorization: Bearer <access_token>`.
4.  **Token Expiry:** Access tokens expire after 1 hour. Refresh tokens expire after 7 days.

### Error Types for Token Management:

When an authenticated request fails due to token issues, the API will return a `401 Unauthorized` or `403 Forbidden` status with a JSON body containing an `error_type` field in the `details` object. The frontend should use this `error_type` to decide the next action:

*   **`access_token_expired`**: The `access_token` has expired.
    *   **Frontend Action:** Attempt to use the `refresh_token` to get a new `access_token`. If successful, retry the original request.
*   **`invalid_signature`**: The `access_token` is invalid (e.g., tampered with, malformed).
    *   **Frontend Action:** Force user logout and re-authentication. The `refresh_token` is also likely compromised or invalid.
*   **`not_yet_valid`**: The `access_token` is not yet valid (should be rare).
    *   **Frontend Action:** Force user logout and re-authentication.
*   **`invalid_token`**: Generic invalid `access_token` error.
    *   **Frontend Action:** Force user logout and re-authentication.
*   **`refresh_token_not_found`**: The provided `refresh_token` does not exist in the database.
    *   **Frontend Action:** Force user logout and re-authentication.
*   **`refresh_token_expired`**: The `refresh_token` has expired.
    *   **Frontend Action:** Force user logout and re-authentication.
*   **`invalid_refresh_token`**: Generic invalid `refresh_token` error.
    *   **Frontend Action:** Force user logout and re-authentication.

### User Roles:

The `role` field in the user object (returned on login and present in the JWT payload) indicates the user's permissions:
*   `student`: Basic user, can register for events, comment.
*   `club_leader`: Can manage events and clubs they lead.
*   `admin`: Full administrative access.

Frontend should use these roles to control UI elements and access to certain features.

---

## 3. Error Handling (General)

All API error responses follow a consistent JSON structure:

```json
{
    "error": "A human-readable error message.",
    "details": {
        // Optional: More specific details about the error,
        // e.g., validation errors for specific fields, or error_type for auth.
    }
}
```

Common HTTP Status Codes and their meanings:

*   `200 OK`: Request successful.
*   `201 Created`: Resource successfully created.
*   `204 No Content`: Request successful, but no content to return (e.g., successful delete).
*   `400 Bad Request`: Invalid request payload, missing required fields, or invalid data format (often includes `details` with validation errors).
*   `401 Unauthorized`: Authentication required or failed (invalid/missing token).
*   `403 Forbidden`: Authenticated, but user does not have permission to perform the action.
*   `404 Not Found`: Resource not found.
*   `405 Method Not Allowed`: HTTP method used is not allowed for the endpoint.
*   `409 Conflict`: Request conflicts with current state of the resource (e.g., duplicate entry).
*   `500 Internal Server Error`: Unexpected server-side error.

---

## 4. File Uploads

Files (e.g., `banner_image`, `logo`, `profile_image`) are uploaded directly to AWS S3.

### How to Upload:

*   Send requests as `multipart/form-data`.
*   The file input field name should match the expected field in the backend (e.g., `banner_image`, `logo`, `profile_image`).
*   The backend expects specific MIME types (JPEG, PNG, GIF, WebP).
*   Max file size is typically 5MB (check backend for exact limits).

### Response:

*   On successful upload, the backend will store the file on S3 and save its **public URL** in the corresponding database field.
*   The API response for the `create` or `update` operation will include this URL in the resource object.

---

## 5. Admin Features & Data Aggregation

### Comment Management with Enhanced Data

The admin comment listing (`/api/comments/index.php?action=list`) uses MongoDB aggregation pipelines to provide enriched data:

**Query Parameters:**
*   `status` (optional): Filter by comment status (`approved`, `pending`, `rejected`, `flagged`)
*   `limit` (optional): Number of comments to return (default: 50)
*   `skip` (optional): Number of comments to skip for pagination (default: 0)

**Enhanced Response Structure:**
```json
{
    "status": "success", 
    "data": {
        "comments": [
            {
                "_id": "...",
                "content": "Great event!",
                "status": "approved",
                "flagged": false,
                "created_at": { "$date": { "$numberLong": "..." } },
                "user": {
                    "_id": "...",
                    "first_name": "John",
                    "last_name": "Doe", 
                    "profile_image": "https://...",
                    "email": "john@usiu.ac.ke"
                },
                "event": {
                    "_id": "...",
                    "title": "Tech Summit 2025",
                    "description": "...",
                    "event_date": { "$date": { "$numberLong": "..." } }
                },
                "event_title": "Tech Summit 2025"
            }
        ]
    }
}
```

### Date Handling Best Practices

**Frontend Date Processing:**
All MongoDB dates are returned in the format `{ "$date": { "$numberLong": "timestamp" } }`. The frontend should use the provided `formatUserDate()` utility function:

```javascript
function formatUserDate(dateObj) {
    if (!dateObj) return 'Unknown';
    
    const getTimestamp = (dateObj) => {
        if (dateObj.$date && dateObj.$date.$numberLong) {
            return parseInt(dateObj.$date.$numberLong);
        }
        return new Date(dateObj).getTime();
    };
    
    return new Date(getTimestamp(dateObj)).toLocaleDateString();
}
```

### Admin Actions

All admin comment moderation actions require `admin` or `club_leader` role:

*   **Approve/Reject Toggle**: Changes comment status between `approved` ↔ `rejected`
*   **Flag/Unflag Toggle**: Changes comment `flagged` field between `true` ↔ `false`
*   **Status Filtering**: Admin dashboard supports filtering by any status
*   **Real-time Updates**: Actions immediately reflect in the admin interface

---

## 6. Environment Variables

The backend uses environment variables for sensitive information (database credentials, JWT secret, AWS keys). The frontend does not directly access these. However, the frontend should be configured with the **base URL of the API** (e.g., `http://localhost:8000` or your deployed domain).

---
