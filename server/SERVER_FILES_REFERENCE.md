# USIU Events Management System - Server Files Reference

## Overview

This document catalogs all developer-created server-side files in the USIU Events Management System. The backend is built with PHP and uses MongoDB for data storage, implementing a RESTful API architecture with JWT-based authentication.

## Technical Architecture

- **Language**: PHP 8.x
- **Database**: MongoDB with PHP MongoDB Driver
- **Authentication**: JWT tokens with access/refresh token system
- **File Uploads**: Local storage with validation
- **Email**: SMTP integration for verification and notifications
- **CORS**: Configured for client-side integration

## File Structure and Organization

### Root Level Files (3 files)

#### Configuration Files
- **`composer.json`** - PHP dependency management and project configuration
- **`composer.lock`** - Locked dependency versions for consistent environments
- **`index.php`** - Server entry point and routing dispatcher

### Documentation Files (4 files)

#### API Documentation
- **`README.md`** - Server setup, installation, and general documentation
- **`API_ENDPOINTS.md`** - Complete API endpoint reference with examples
- **`BACKEND_CONTRACT.md`** - Backend API contract and integration guide
- **`SCHEMAS.md`** - Data schemas and validation rules documentation

### Configuration Directory (3 files)

#### Core Configuration
- **`config/config.php`** - Application configuration and environment settings
- **`config/cors.php`** - CORS policy configuration for client-server communication
- **`config/database.php`** - MongoDB connection and database configuration

### Middleware Directory (3 files)

#### Request Processing
- **`middleware/auth.php`** - JWT authentication and authorization middleware
- **`middleware/sanitize.php`** - Input sanitization and security filtering
- **`middleware/validate.php`** - Request validation and error handling

### Models Directory (4 files)

#### Data Models
- **`models/User.php`** - User model with authentication and profile management
- **`models/Event.php`** - Event model with registration and management features
- **`models/Club.php`** - Club model with membership and leadership management
- **`models/Comment.php`** - Comment model with moderation and approval features

### Schemas Directory (4 files)

#### Validation Schemas
- **`schemas/User.php`** - User data validation schemas and rules
- **`schemas/Event.php`** - Event data validation schemas and rules
- **`schemas/Club.php`** - Club data validation schemas and rules
- **`schemas/Comment.php`** - Comment data validation schemas and rules

### Utils Directory (5 files)

#### Utility Functions
- **`utils/jwt.php`** - JWT token generation, validation, and management
- **`utils/email.php`** - Email sending functionality for notifications
- **`utils/upload.php`** - File upload handling with validation and security
- **`utils/response.php`** - API response formatting and status code management
- **`utils/exceptions.php`** - Custom exception classes and error handling

### API Directory Structure

The API directory contains organized endpoints grouped by functionality:

#### Authentication Endpoints (8 files)
**`api/auth/`**
- **`index.php`** - Authentication API router and dispatcher
- **`login.php`** - User login with credential validation
- **`register.php`** - User registration with email verification
- **`logout.php`** - Session termination and token invalidation
- **`verify_email.php`** - Email verification token processing
- **`reset_password.php`** - Password reset with email tokens
- **`change_password.php`** - Authenticated password change
- **`refresh_token.php`** - JWT token refresh mechanism
- **`resend_verification.php`** - Resend email verification links
- **`README.md`** - Authentication API documentation

#### Event Management Endpoints (11 files)
**`api/events/`**
- **`index.php`** - Events API router and dispatcher
- **`list.php`** - Public event listings with filtering
- **`details.php`** - Event details with registration information
- **`create.php`** - Event creation for admins and club leaders
- **`update.php`** - Event modification and management
- **`delete.php`** - Event deletion with authorization checks
- **`register.php`** - Event registration for users
- **`unregister.php`** - Event registration cancellation
- **`registered.php`** - User's registered events list
- **`created.php`** - Events created by user/club
- **`history.php`** - Event attendance and history tracking

#### Club Management Endpoints (7 files)
**`api/clubs/`**
- **`index.php`** - Clubs API router and dispatcher
- **`list.php`** - Public club listings with categories
- **`details.php`** - Club details with membership information
- **`create.php`** - Club creation for authorized users
- **`update.php`** - Club modification for leaders and admins
- **`delete.php`** - Club deletion with authorization checks
- **`join.php`** - Club membership management

#### Comment Management Endpoints (9 files)
**`api/comments/`**
- **`index.php`** - Comments API router and dispatcher
- **`create.php`** - Comment creation on events
- **`get.php`** - Retrieve comments for events
- **`list.php`** - Comment listings with moderation status
- **`details.php`** - Individual comment details
- **`delete.php`** - Comment deletion for authors and moderators
- **`approve.php`** - Comment approval for moderators
- **`reject.php`** - Comment rejection for moderators
- **`flag.php`** - Comment flagging for inappropriate content
- **`unflag.php`** - Comment unflagging for moderators

#### User Management Endpoints (9 files)
**`api/users/`**
- **`index.php`** - Users API router and dispatcher
- **`list.php`** - User listings for admin interface
- **`details.php`** - User profile details
- **`profile.php`** - Current user profile management
- **`update.php`** - User profile updates
- **`create.php`** - Admin user creation
- **`delete.php`** - User account deletion
- **`events.php`** - User's event associations
- **`stats.php`** - User activity statistics

## Key Features by File Category

### Authentication System
- JWT-based stateless authentication
- Access and refresh token mechanism
- Email verification workflow
- Password reset functionality
- Session management and security

### Event Management
- Event creation and publication
- Registration system with capacity limits
- Event categories and filtering
- Image upload for event banners
- Event status management (draft/published/cancelled)

### Club Management
- Club creation and leadership assignment
- Membership management
- Club categories and organization
- Logo upload functionality
- Club status and visibility control

### Comment System
- Comment creation on events
- Moderation workflow with approval/rejection
- Flagging system for inappropriate content
- Admin oversight and management
- User notification system

### User Management
- User profiles and authentication
- Role-based access control (user/admin/club leader)
- Activity tracking and statistics
- Administrative user management
- Profile customization

## Security Features

### Input Validation
- Comprehensive input sanitization
- Schema-based validation
- SQL injection prevention
- XSS protection
- File upload security

### Authentication Security
- JWT token expiration and rotation
- Password hashing with secure algorithms
- Email verification requirements
- Rate limiting on sensitive endpoints
- Session invalidation on logout

### Authorization
- Role-based access control
- Resource ownership verification
- Admin privilege enforcement
- Club leader permissions
- API endpoint protection

## Database Integration

### MongoDB Integration
- Modern PHP MongoDB driver
- Document-based data modeling
- Aggregation pipeline usage
- Index optimization
- Connection pooling

### Data Models
- User profiles and authentication
- Event management and registration
- Club organization and membership
- Comment moderation and approval
- File metadata and references

## File Upload System

### Image Processing
- Event banner uploads
- Club logo uploads
- User profile pictures
- File type validation
- Size restrictions and optimization

### Security Measures
- MIME type validation
- File extension restrictions
- Upload directory isolation
- Malicious file detection
- Storage quota management

## Email System

### Notification Types
- Email verification links
- Password reset tokens
- Event registration confirmations
- Club membership notifications
- Administrative alerts

### Email Security
- SMTP authentication
- Template-based emails
- Rate limiting on sends
- Bounce handling
- Spam prevention measures

## API Response Format

### Standardized Responses
- Consistent JSON structure
- HTTP status code alignment
- Error message formatting
- Pagination metadata
- Success confirmation patterns

### Error Handling
- Custom exception classes
- Detailed error messages
- Logging and monitoring
- Client-friendly error codes
- Development vs production responses

## Development and Deployment

### Environment Configuration
- Development/production settings
- Database connection management
- Debug mode configuration
- Error reporting levels
- Performance optimization settings

### Code Organization
- PSR-4 autoloading compliance
- Modular architecture design
- Separation of concerns
- Dependency injection patterns
- Clean code principles

---

**Total Developer-Created Files: 60**
- Root Configuration: 3 files
- Documentation: 4 files  
- Configuration: 3 files
- Middleware: 3 files
- Models: 4 files
- Schemas: 4 files
- Utils: 5 files
- API Endpoints: 34 files

This server architecture provides a robust, secure, and scalable foundation for the USIU Events Management System, implementing modern PHP development practices and comprehensive security measures.