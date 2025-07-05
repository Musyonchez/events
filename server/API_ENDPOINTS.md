# API Endpoints Reference

This document provides a comprehensive list of the API endpoints for the USIU Events application, including their base URLs, required `action` parameters (where applicable), HTTP methods, and typical body formats.

Remember to replace `http://<your_server_address>` with the actual address of your PHP development server (e.g., `http://localhost:8000`).

---

## 1. Authentication Endpoints (`api/auth/index.php`)

**Base URL:** `http://<your_server_address>/api/auth/index.php`

| Action Parameter          | Method | Description                                               | Body (JSON) / Notes                                                                  |
| :------------------------ | :----- | :-------------------------------------------------------- | :----------------------------------------------------------------------------------- |
| `?action=register`        | `POST` | Register a new user.                                      | ```json
{
    "student_id": "USIU12345",
    "first_name": "Jane",
    "last_name": "Doe",
    "email": "jane.doe@usiu.ac.ke",
    "password": "SecurePassword123!",
    "phone": "+254712345678",
    "course": "Computer Science",
    "year_of_study": 3
}
``` |
| `?action=login`           | `POST` | Log in a user and get JWT.                                | ```json
{
    "email": "jane.doe@usiu.ac.ke",
    "password": "SecurePassword123!"
}
``` |
| `?action=logout`          | `POST` | Log out a user (client-side token discard).               | (Empty body usually)                                                                 |
| `?action=refresh_token`   | `POST` | Get a new access token using a refresh token.             | ```json
{
    "refresh_token": "your_refresh_token_here"
}
``` |
| `?action=reset_password`  | `POST` | **Request Link:** Send password reset email.              | ```json
{
    "email": "jane.doe@usiu.ac.ke"
}
``` |
| `?action=reset_password`  | `POST` | **Set New Password:** Reset password with token.          | ```json
{
    "token": "token_from_email",
    "password": "NewSecurePassword456!"
}
``` |
| `(Direct Access)`         | `GET`  | **Email Verification:** Accessed directly via email link. | `http://<your_server_address>/api/auth/verify_email.php?token=...` (No Postman body) |
| `(Direct Access)`         | `POST` | **Resend Verification:** Request a new email verification link. | ```json
{
    "email": "jane.doe@usiu.ac.ke"
}
``` |
| `?action=change_password` | `POST` | Change a user's password.                                 | ```json
{
    "id": "user_id_from_jwt",
    "old_password": "CurrentSecurePassword123!",
    "new_password": "NewSecurePassword456!"
}
``` |

---

## 2. Event Endpoints (`api/events/index.php`)

**Base URL:** `http://<your_server_address>/api/events/index.php`

| Action Parameter   | Method   | Description                                 | Body (JSON) / Notes                                                             |
| :----------------- | :------- | :------------------------------------------ | :------------------------------------------------------------------------------ |
| `?action=create`   | `POST`   | Create a new event.                         | ```json
{
    "title": "Annual Tech Innovation Summit",
    "description": "Join us for a day of groundbreaking discussions and showcases in technology and innovation. Featuring keynote speakers from leading tech companies and interactive workshops.",
    "club_id": "65c7a1b2c3d4e5f6a7b8c9d0",
    "organizer_id": "65c7a1b2c3d4e5f6a7b8c9d1",
    "event_date": "2025-08-15T09:00:00Z",
    "end_date": "2025-08-15T17:00:00Z",
    "location": "USIU Auditorium",
    "venue_capacity": 500,
    "registration_required": true,
    "registration_deadline": "2025-08-10T23:59:59Z",
    "registration_fee": 0,
    "max_attendees": 450,
    "banner_image": "https://example.com/images/tech_summit_banner.jpg",
    "category": "Technology",
    "tags": ["innovation", "tech", "summit", "conference"],
    "status": "published",
    "featured": true
}
``` (Requires Auth) |
| `?action=details`  | `GET`    | Get event details by ID or list all events. | `?id=...` for single event, or `?limit=...&skip=...` for list.                  |
| `?action=update`   | `PATCH`  | Update an existing event.                   | ```json
{
    "description": "Updated description for the tech summit.",
    "location": "New Auditorium",
    "max_attendees": 500
}
``` (Requires Auth)           |
| `?action=delete`   | `DELETE` | Delete an event.                            | `?id=...` in URL. (Requires Auth)                                               |
| `?action=register` | `POST`   | Register a user for an event.               | ```json
{
    "event_id": "65c7a1b2c3d4e5f6a7b8c9d2"
}
``` (Requires Auth)                                           |

---

## 3. Club Endpoints (`api/clubs/index.php`)

**Base URL:** `http://<your_server_address>/api/clubs/index.php`

| Action Parameter  | Method   | Description                               | Body (JSON) / Notes                                                 |
| :---------------- | :------- | :---------------------------------------- | :------------------------------------------------------------------ |
| (No action param) | `GET`    | Get club details by ID or list all clubs. | `?id=...` for single club, or `?limit=...&skip=...` for list.       |
| (No action param) | `POST`   | Create a new club.                        | ```json
{
    "name": "The Robotics Club",
    "description": "A club dedicated to building and programming robots.",
    "category": "Technology",
    "logo": "https://example.com/images/robotics_logo.png",
    "contact_email": "robotics.club@usiu.ac.ke"
}
``` (Requires Auth)        |
| (No action param) | `PATCH`  | Update an existing club.                  | ```json
{
    "description": "Updated description for the robotics club.",
    "contact_email": "new.robotics.email@usiu.ac.ke"
}
``` (Requires Auth) |
| (No action param) | `DELETE` | Delete a club.                            | `?id=...` in URL. (Requires Auth)                                   |

---

## 4. Comment Endpoints (`api/comments/index.php`)

**Base URL:** `http://<your_server_address>/api/comments/index.php`

| Action Parameter  | Method   | Description                                       | Body (JSON) / Notes                                          |
| :---------------- | :------- | :------------------------------------------------ | :----------------------------------------------------------- |
| (No action param) | `GET`    | Get comments by event ID or single comment by ID. | `?event_id=...` or `?id=...`                                 |
| (No action param) | `POST`   | Create a new comment.                             | ```json
{
    "event_id": "65c7a1b2c3d4e5f6a7b8c9d2",
    "user_id": "65c7a1b2c3d4e5f6a7b8c9d1",
    "content": "This is a great event! Looking forward to it."
}
``` (Requires Auth) |
| (No action param) | `DELETE` | Delete a comment.                                 | `?id=...` in URL. (Requires Auth)                            |

---

## 5. User Endpoints (`api/users/index.php`)

**Base URL:** `http://<your_server_address>/api/users/index.php`

| Action Parameter | Method   | Description                                         | Body (JSON) / Notes                                                             |
| :--------------- | :------- | :-------------------------------------------------- | :------------------------------------------------------------------------------ |
| `?action=details` | `GET`    | Get user details by ID.                             | `?id=...`                                                                       |
| `?action=create` | `POST`   | Create a new user.                                  | ```json
{
    "student_id": "USIU54321",
    "first_name": "Bob",
    "last_name": "Smith",
    "email": "bob.smith@usiu.ac.ke",
    "password": "AnotherSecurePass!",
    "phone": "+254723456789",
    "course": "Business Administration",
    "year_of_study": 1
}
``` (Requires Auth) |
| `?action=update` | `PATCH`  | Update an existing user.                            | ```json
{
    "first_name": "Robert",
    "phone": "+254798765432",
    "profile_image": "https://example.com/images/robert_profile.jpg"
}
``` (Requires Auth)       |
| `?action=delete` | `DELETE` | Delete a user.                                      | `?id=...` in URL. (Requires Auth)                                               |
| `?action=events` | `GET`    | Get user-specific events (created or registered).   | `?type=created` or `?type=registered` (Requires Auth)                           |
| `(Direct Access)` | `GET`    | **User Profile:** Get authenticated user's profile. | `http://<your_server_address>/api/users/profile.php` (Requires Auth)            |

---