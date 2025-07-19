# USIU Events - Technical Reference for AI Agents

## System Architecture Overview

### Technology Stack
- **Frontend**: Vanilla JavaScript ES6+, HTML5, CSS3, Tailwind CSS
- **Backend**: PHP 8+, MongoDB 6.0+, AWS S3
- **Authentication**: JWT (JSON Web Tokens)
- **Email**: PHPMailer with SMTP
- **Dependencies**: Composer (PHP), Firebase JWT, MongoDB PHP Driver

### File Structure Analysis
```
events/
├── client/                     # Frontend application
│   ├── assets/js/             # JavaScript modules
│   │   ├── main.js           # Core application logic
│   │   ├── auth.js           # Authentication handling
│   │   ├── http.js           # API communication layer
│   │   └── admin/            # Admin-specific JavaScript
│   ├── pages/                # HTML page templates
│   └── components/           # Reusable HTML components
├── server/                    # Backend API
│   ├── api/                  # RESTful API endpoints
│   │   ├── auth/             # Authentication endpoints
│   │   ├── events/           # Event management
│   │   ├── clubs/            # Club management
│   │   ├── users/            # User management
│   │   └── comments/         # Comment system
│   ├── models/               # Database interaction layer
│   ├── schemas/              # Data validation schemas
│   ├── middleware/           # Request/response middleware
│   └── utils/                # Helper utilities
```

### Database Schema (MongoDB Collections)

#### Users Collection
```javascript
{
  _id: ObjectId,
  student_id: String,           // Unique university ID
  first_name: String,
  last_name: String,
  email: String,                // Must end with @usiu.ac.ke
  password: String,             // Hashed with password_hash()
  phone: String?,
  course: String?,
  year_of_study: Number,
  profile_image: String?,       // S3 URL
  role: "student|admin|club_leader",
  status: "active|inactive|suspended",
  last_login: UTCDateTime?,
  refresh_token: String?,
  refresh_token_expires_at: UTCDateTime?,
  email_verification_token: String?,
  email_verified_at: UTCDateTime?,
  is_email_verified: Boolean,
  password_reset_token: String?,
  password_reset_expires_at: UTCDateTime?,
  created_at: UTCDateTime,
  updated_at: UTCDateTime
}
```

#### Events Collection
```javascript
{
  _id: ObjectId,
  title: String,
  description: String,
  club_id: ObjectId,            // Reference to clubs collection
  created_by: ObjectId,         // Reference to users collection
  event_date: UTCDateTime,
  end_date: UTCDateTime?,
  location: String,
  venue_capacity: Number,
  registration_required: Boolean,
  registration_deadline: UTCDateTime?,
  registration_fee: Number,
  max_attendees: Number,
  current_registrations: Number,
  banner_image: String?,        // S3 URL
  gallery: [String],            // Array of S3 URLs
  category: String,
  tags: [String],
  status: "draft|published|cancelled|completed",
  featured: Boolean,
  registered_users: [ObjectId], // Array of user IDs
  social_media: Object,
  created_at: UTCDateTime,
  updated_at: UTCDateTime
}
```

#### Clubs Collection
```javascript
{
  _id: ObjectId,
  name: String,
  description: String,
  category: String,             // See CLUB_CATEGORIES below
  logo: String?,                // S3 URL
  contact_email: String,        // Must end with @usiu.ac.ke
  leader_id: ObjectId,          // Reference to users collection
  members_count: Number,
  members: [ObjectId],          // Array of user IDs
  status: "active|inactive",
  created_at: UTCDateTime,
  updated_at: UTCDateTime
}
```

#### Comments Collection
```javascript
{
  _id: ObjectId,
  event_id: ObjectId,           // Reference to events collection
  user_id: ObjectId,            // Reference to users collection
  content: String,
  parent_comment_id: ObjectId?, // For replies (single-level only)
  status: "pending|approved|rejected",
  flagged: Boolean,
  created_at: UTCDateTime,
  updated_at: UTCDateTime,
  // NOTE: user and event objects populated via aggregation in admin views
  user?: Object,                // Populated user details
  event?: Object,               // Populated event details
  event_title?: String          // Quick access field
}
```

### API Patterns and Conventions

#### Authentication Flow
1. **Registration**: POST `/api/auth/index.php?action=register`
2. **Email Verification**: GET `/api/auth/verify_email.php?token=...`
3. **Login**: POST `/api/auth/index.php?action=login`
4. **Token Refresh**: POST `/api/auth/index.php?action=refresh_token`
5. **Logout**: POST `/api/auth/index.php?action=logout`

#### Request/Response Format
```javascript
// Request Headers
{
  "Content-Type": "application/json",
  "Authorization": "Bearer <jwt_token>"  // For authenticated requests
}

// Success Response
{
  "status": "success",
  "message": "Operation completed",
  "data": { /* response data */ }
}

// Error Response
{
  "error": "Error message",
  "details": {
    "error_type": "specific_error_code",
    "field_errors": { /* validation errors */ }
  }
}
```

#### Error Types for Token Management
- `access_token_expired`: Token expired, use refresh token
- `invalid_signature`: Token tampered/malformed, force logout
- `refresh_token_expired`: Refresh token expired, force logout
- `invalid_token`: Generic token error, force logout

### Key Constants and Enums

#### User Roles
```php
const USER_ROLES = ['student', 'admin', 'club_leader'];
```

#### Club Categories
```php
const CLUB_CATEGORIES = [
  'Arts & Culture',
  'Academic',
  'Sports', 
  'Technology',
  'Business',
  'Community Service',
  'Religious',
  'Professional',
  'Recreation',
  'Special Interest'
];
```

#### Event Statuses
```php
const EVENT_STATUSES = ['draft', 'published', 'cancelled', 'completed'];
```

#### Comment Statuses
```php
const COMMENT_STATUSES = ['pending', 'approved', 'rejected'];
```

### File Upload Configuration

#### AWS S3 Integration
- **Bucket**: Configured via `AWS_S3_BUCKET` environment variable
- **Allowed Types**: JPEG, PNG, GIF, WebP
- **Max Size**: 5MB
- **Paths**:
  - User profiles: `profiles/{user_id}/{filename}`
  - Event banners: `events/{event_id}/banner/{filename}`
  - Club logos: `clubs/{club_id}/logo/{filename}`

### Database Aggregation Patterns

#### Comments with User/Event Data
```javascript
// MongoDB aggregation pipeline used in Comment::listWithDetails()
[
  { $match: { /* filters */ } },
  { 
    $lookup: {
      from: 'events',
      localField: 'event_id',
      foreignField: '_id',
      as: 'event'
    }
  },
  { $unwind: { path: '$event', preserveNullAndEmptyArrays: true } },
  { $addFields: { event_title: '$event.title' } },
  { $sort: { created_at: -1 } },
  { $skip: 0 },
  { $limit: 50 }
]
```

### Frontend Architecture

#### JavaScript Module Pattern
- **ES6 Modules**: All JavaScript uses `type="module"`
- **Async/Await**: Consistent promise handling
- **Error Boundaries**: Centralized error handling in `http.js`

#### State Management
- **Authentication**: Stored in localStorage with automatic cleanup
- **Token Refresh**: Automatic background refresh on 401 errors
- **Real-time Updates**: Dynamic DOM updates without page refresh

#### Responsive Design
- **Tailwind CSS**: Utility-first framework
- **Mobile-First**: Progressive enhancement approach
- **Responsive Patterns**: 
  ```html
  <!-- Standard responsive layout -->
  <div class="flex-col md:flex-row max-md:space-y-3 max-md:items-center">
  ```

### Security Measures

#### Input Validation
- **Server-side**: Schema validation in PHP models
- **Client-side**: Real-time validation feedback
- **Sanitization**: XSS prevention on all inputs

#### Authentication Security
- **JWT Expiry**: Access tokens expire in 1 hour
- **Refresh Tokens**: Expire in 7 days, stored securely
- **Password Hashing**: PHP `password_hash()` with DEFAULT algorithm
- **Email Verification**: Required for all new accounts

#### Role-Based Access Control
```php
// Middleware checks in API endpoints
if (!$currentUser || ($currentUser->role !== 'admin' && $currentUser->role !== 'club_leader')) {
    send_unauthorized('Admin privileges required');
}
```

### Development Patterns

#### Error Handling Best Practices
- **Client-side**: Specific error messages with actionable guidance
- **Server-side**: Detailed logging with user-friendly responses
- **Validation**: Field-specific error messages

#### Code Organization
- **MVC Pattern**: Models handle data, API endpoints handle requests
- **Separation of Concerns**: Clear boundaries between layers
- **Reusable Components**: HTML components, JS utilities, PHP schemas

### Environment Configuration

#### Required Environment Variables
```bash
# Database
MONGODB_URI=mongodb://localhost:27017/usiu_events

# JWT
JWT_SECRET=your_secret_key_here

# AWS S3
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_S3_REGION=us-east-1
AWS_S3_BUCKET=your_bucket_name

# Email
SMTP_HOST=smtp.mailtrap.io
SMTP_PORT=2525
SMTP_USERNAME=your_username
SMTP_PASSWORD=your_password
SMTP_FROM_EMAIL=noreply@usiu.ac.ke
SMTP_FROM_NAME="USIU Events"

# Application
APP_URL=http://localhost:3000
API_URL=http://localhost:8000
```

### Common Operations for AI Agents

#### Finding User by Email
```javascript
// Client-side
const user = await request('GET', '/api/users/profile.php');

// Server-side
$user = $userModel->findByEmail($email);
```

#### Creating Event with Validation
```javascript
// Client-side
const eventData = {
  title: "Event Title",
  description: "Event description",
  club_id: "club_object_id",
  event_date: "2025-12-31T10:00:00Z",
  location: "Event location"
};
const response = await request('POST', '/api/events/index.php?action=create', eventData);
```

#### Admin Comment Moderation
```javascript
// Toggle approval status
await request('PATCH', `/api/comments/index.php?action=approve&id=${commentId}`);
await request('PATCH', `/api/comments/index.php?action=reject&id=${commentId}`);

// Toggle flag status  
await request('PATCH', `/api/comments/index.php?action=flag&id=${commentId}`);
await request('PATCH', `/api/comments/index.php?action=unflag&id=${commentId}`);
```

#### Date Handling (MongoDB Format)
```javascript
// MongoDB returns dates as: { "$date": { "$numberLong": "timestamp" } }
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

### Performance Considerations

#### Database Indexing
- Users: `email`, `student_id`, `role`
- Events: `club_id`, `status`, `featured`, `event_date`
- Comments: `event_id`, `user_id`, `status`
- Clubs: `leader_id`, `status`, `category`

#### Caching Strategy
- Static assets: Browser caching with versioning
- API responses: No server-side caching (real-time data)
- Images: S3 CloudFront distribution recommended

#### Pagination
- Default limit: 50 items
- Max limit: 100 items
- Skip-based pagination: `?limit=50&skip=100`

This technical reference provides comprehensive information for AI agents to understand the system architecture, data structures, API patterns, and common operations within the USIU Events platform.