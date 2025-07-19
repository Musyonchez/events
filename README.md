# USIU Events Management System

A comprehensive university events management platform for USIU Kenya campus, allowing departments and clubs to create, manage, and display campus events. Students can register for events, leave comments, and receive email notifications.

## Project Overview

This system provides a complete solution for university event management with:
- **Event Creation & Management** - Clubs can create and manage events
- **User Registration** - Students can register for events with email notifications  
- **Comment System** - Users can comment on events with moderation
- **Admin Dashboard** - Comprehensive management interface
- **Role-Based Access** - Student, Club Leader, and Admin roles

## Architecture

### Frontend (Client)
- **Technology**: Vanilla JavaScript ES6+, HTML5, CSS3, Tailwind CSS
- **Features**: Single Page Application, responsive design, real-time updates
- **Authentication**: JWT-based with automatic token refresh

### Backend (Server) 
- **Technology**: PHP 8+, MongoDB, AWS S3
- **Features**: RESTful API, file uploads, email notifications
- **Security**: JWT authentication, input validation, role-based authorization

## Key Features

### ✅ Event Management
- Create, edit, publish/unpublish events
- Feature/unfeature events for homepage display
- Event registration with capacity limits
- Banner image uploads to AWS S3

### ✅ User Management  
- Email verification and password reset
- Profile management with image uploads
- Role-based permissions (Student, Club Leader, Admin)

### ✅ Club Management
- Club creation and membership tracking
- Leader assignment and management
- Category-based organization

### ✅ Comment Moderation
- **Enhanced Admin Controls** - Approve/reject toggle, flag/unflag functionality
- **Real-time Status Updates** - Dynamic buttons based on comment state
- **Comprehensive Filtering** - View comments by status (approved, pending, flagged)
- **Event Integration** - Comments display actual event titles and dates

### ✅ Admin Dashboard
- **Statistics Overview** - Total events, users, revenue, active clubs
- **Tabbed Management** - Events, Users, Clubs, Comments in organized tabs
- **Bulk Operations** - Export data to CSV format
- **Date Formatting** - Consistent MongoDB date handling across all sections

## Recent Enhancements

### Comment Moderation System
- **Toggle Functionality**: Approve ↔ Reject, Flag ↔ Unflag buttons
- **Event Information**: Proper event titles instead of "Unknown Event"
- **Date Display**: Fixed "Invalid Date" issues with MongoDB date format
- **Backend Endpoints**: Added `/reject` and `/unflag` actions
- **Data Aggregation**: MongoDB pipeline joins comments with users and events

### Admin Dashboard Improvements  
- **Consistent Date Formatting**: Applied `formatUserDate()` across all sections
- **Enhanced Error Handling**: Better MongoDB aggregation pipeline management
- **UI Polish**: Dynamic button states and visual status indicators

## Quick Start

### Prerequisites
- PHP 8.0+
- MongoDB 5.0+
- Composer
- AWS S3 Account (for file uploads)
- SMTP Server (for email notifications)

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd events
   ```

2. **Backend Setup**
   ```bash
   cd server
   composer install
   cp .env.example .env
   # Configure your environment variables
   ```

3. **Frontend Setup**
   ```bash
   cd client
   # Open index.html in a web server
   ```

4. **Environment Configuration**
   Update `.env` with your:
   - MongoDB connection string
   - AWS S3 credentials  
   - SMTP settings
   - JWT secret

### Testing

- **API Testing**: Use Postman/Insomnia with endpoints in `API_ENDPOINTS.md`
- **Email Testing**: Check Mailtrap for verification and notification emails
- **Admin Access**: Create admin user and access `/pages/admin/admin-dashboard.html`

## Documentation

- **[API Endpoints](server/API_ENDPOINTS.md)** - Complete API reference
- **[Backend Contract](server/BACKEND_CONTRACT.md)** - Frontend integration guide  
- **[Data Schemas](server/SCHEMAS.md)** - Database structure reference
- **[Client README](client/README.md)** - Frontend architecture details
- **[Server README](server/README.md)** - Backend implementation details

## Contributing

This is an academic project for USIU Summer 2025. See the server README for detailed marking criteria and project requirements.

## License

Academic project - USIU University, Kenya Campus.
