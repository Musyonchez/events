# USIU Events API

## Mini-Project - Summer 2025

### Assignment Description:

USIU university, Kenyan campus has clubs for students e.g. drama club, Club of Hotel and Tourism, etc. You have been hired to build a dynamic web application that allows university departments or clubs to create, manage, and display campus events. Users (students) can register for events, leave comments, and receive email notifications.

**You Will be required to: -**
*   Realize a functional Frontend using (HTML; CSS; JavaScript)
*   Realize a functional Backend Using PHP
*   Realize a functional Database in MySQL or Your choice
*   Demonstrate where you have applied AJAX
*   Be as creative as possible (attracts 1 mark from each criteria)

[NOTE: Realize is similar to Develop]

### Marking / Assessment Criteria

| Criteria | Description | Marks |
| :------- | :---------- | :---- |
| 1. Dynamic Web Pages | - Use of HTML/CSS and JavaScript to create responsive and interactive UI. - Dynamic content loading without full page refresh (e.g., using AJAX or Fetch API). | 5 |
| 2. Client-Server Communication | - RESTful API calls implemented correctly (GET/POST). - Use of JSON for client-server data exchange. - Error handling on both ends. | 3 |
| 3. Backend Logic & Data Management | - Functional backend handling CRUD operations. - Proper database integration. - Validation and data persistence. | 3 |
| 4. Overall Functionality & Code Quality | - Core features (event listing, registration, and comments) work smoothly. - User actions reflect correctly in the database and frontend. - Organized code structure (separation of concerns). - Proper naming, comments, and readability. | 4 |
| **Total:** | | **15 Marks** |

## Project Overview

This project implements the backend API for the USIU Events application. It is built with PHP and uses MongoDB as its primary database.

### Key Technologies:

*   **PHP:** The core language for the backend logic.
*   **MongoDB:** NoSQL database for data storage.
*   **Composer:** PHP dependency manager.
*   **PHPMailer:** For sending email notifications (e.g., email verification, password reset, event registration confirmations).
*   **Firebase PHP-JWT:** For JSON Web Token (JWT) based authentication.
*   **phpdotenv:** For managing environment variables.

### Project Structure:

*   `api/`: Contains all API endpoints, organized by resource (e.g., `auth`, `events`, `clubs`, `users`, `comments`). The `index.php` files within these subdirectories often act as controllers, routing requests to specific handler files.
*   `config/`: Configuration files for database connection, CORS settings, etc.
*   `middleware/`: Contains middleware for request validation, sanitization, and authentication.
*   `models/`: PHP classes that interact with the MongoDB database, providing CRUD operations for different entities (Users, Events, Clubs, Comments).
*   `schemas/`: Defines the data structure and validation rules for each entity before data is persisted to the database.
*   `utils/`: Utility functions for common tasks like email sending, JWT handling, and standardized API responses.
*   `vendor/`: Composer-managed third-party libraries.
*   `.env`: Environment variables for sensitive information and configuration.

### Features Implemented (Backend):

*   User Authentication (Registration, Login, Logout, Refresh Token)
*   Email Verification for new user registrations.
*   Password Reset functionality with email-based token.
*   Event Management (Create, Read, Update, Delete)
*   User Registration for Events with email notifications.
*   Club Management (Create, Read, Update, Delete)
*   **Enhanced Comment Management** (Create, Read, Update, Delete, Approve, Reject, Flag, Unflag)
*   **Admin Comment Moderation** with MongoDB aggregation pipelines for user/event data joining
*   **Data Export Functionality** - CSV export for all platform data
*   Input Validation and Sanitization.
*   JWT-based Authorization with role-based permissions.

### How to Run:

1.  Ensure you have PHP and Composer installed.
2.  Install MongoDB and configure your connection string in the `.env` file.
3.  Install project dependencies:
    ```bash
    composer install
    ```
4.  Configure your Mailtrap (or other SMTP) credentials in the `.env` file for email functionality.
5.  Start a PHP development server (e.g., `php -S localhost:8000 -t .` from the project root).
6.  Access API endpoints via `http://localhost:8000/api/...`

### Recent Enhancements:

#### Comment Moderation System
*   **New Endpoints**: Added `reject.php` and `unflag.php` for complete comment moderation
*   **MongoDB Aggregation**: `listWithDetails()` method uses aggregation pipelines to join comments with users and events
*   **Enhanced Admin API**: `/api/comments/index.php?action=list` provides enriched comment data with user and event details
*   **Toggle Functionality**: Admin can seamlessly switch between approve/reject and flag/unflag states

#### Admin Dashboard Improvements
*   **Consistent Date Formatting**: All date displays use proper MongoDB date handling across events, users, clubs, and comments
*   **Data Aggregation**: Comments list includes complete user profiles and event information
*   **Export Functionality**: CSV export for comments, events, users, and clubs
*   **Real-time UI Updates**: Admin actions immediately reflect in the interface

#### Technical Improvements
*   **Aggregation Pipeline Optimization**: Conditional `$match` stages to handle empty filters
*   **Error Handling**: Better MongoDB error handling and validation
*   **Data Structure**: Enhanced comment schema with `flagged` field and proper date handling

### Testing:

*   Use a tool like Postman or Insomnia to send requests to the API endpoints.
*   Check your Mailtrap inbox for test emails (verification, password reset, event registration).
*   **Admin Testing**: Access admin dashboard at `/pages/admin/admin-dashboard.html` to test comment moderation features.
*   **Comment Moderation**: Test approve/reject and flag/unflag toggles with different comment statuses.
