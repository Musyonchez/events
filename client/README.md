# Client-Side Application Architecture

## Overview for AI Agents
This directory contains a vanilla JavaScript single-page application using ES6 modules, Tailwind CSS, and component-based architecture. The application communicates with a PHP/MongoDB backend via RESTful APIs.

## Architecture Patterns

### Module System
- **Type**: ES6 Modules (`type="module"`)
- **Import/Export**: Standard ES6 syntax
- **Async/Await**: Consistent promise handling throughout
- **Error Boundaries**: Centralized error handling in `http.js`

### State Management
- **Authentication**: JWT tokens in localStorage with automatic refresh
- **UI State**: DOM manipulation without virtual DOM
- **Real-time Updates**: Event-driven updates using custom events

## Directory Structure

```
client/
├── index.html                 # Entry point with hero section
├── assets/
│   ├── css/
│   │   └── style.css         # Custom styles + Tailwind overrides
│   ├── images/               # Static assets (logos, placeholders)
│   └── js/
│       ├── main.js           # App initialization, navbar, global setup
│       ├── auth.js           # Authentication state management
│       ├── http.js           # API communication layer with error handling
│       ├── utils.js          # Date formatting, validation utilities
│       ├── component-loader.js # Dynamic HTML component loading
│       ├── events.js         # Event listing, filtering, registration
│       ├── event-details.js  # Single event view, comments
│       ├── clubs.js          # Club listing and filtering
│       ├── club-details.js   # Single club view, join functionality
│       ├── dashboard.js      # User dashboard, registered events
│       ├── login.js          # Login form handling
│       ├── register.js       # Registration form with validation
│       ├── forgot-password.js # Password reset flow
│       ├── change-password.js # Password change form
│       └── admin/
│           ├── admin-dashboard.js # Admin panel with tabs, CRUD operations
│           ├── admin-stats.js     # Statistics calculations
│           ├── create-event.js    # Event creation form
│           └── create-club.js     # Club creation form
├── components/
│   ├── navbar.html           # Main navigation with auth state
│   ├── footer.html           # Site footer
│   └── modals/               # Reusable modal dialogs
├── pages/
│   ├── events.html           # Event listing with search/filter
│   ├── event-details.html    # Event details with registration
│   ├── clubs.html            # Club listing with categories
│   ├── club-details.html     # Club details with join button
│   ├── dashboard.html        # User dashboard
│   ├── login.html            # Login form
│   ├── register.html         # Registration form
│   ├── forgot-password.html  # Password reset request
│   ├── change-password.html  # Password change form
│   └── admin/
│       ├── admin-dashboard.html # Admin control panel
│       ├── create-event.html    # Event creation interface
│       └── create-club.html     # Club creation interface
```

## Core JavaScript Modules

### 1. Authentication Flow (`auth.js`)
```javascript
// Key functions available to other modules
isAuthenticated()           // Check if user has valid token
getCurrentUser()           // Get user object from JWT payload
getAuthHeaders()           // Get Authorization header for API calls
handleTokenRefresh()       // Automatic token refresh on 401 errors
logout()                   // Clear authentication state
```

### 2. API Communication (`http.js`)
```javascript
// Centralized request handling with error management
request(method, url, data)  // Main API communication function
requestWithAuth()          // Authenticated requests with auto-refresh
// Error classes: AuthError, AccessTokenExpiredError
```

### 3. Utility Functions (`utils.js`)
```javascript
formatUserDate(dateObj)     // Handle MongoDB date format conversion
showNotification(message, isError) // Toast notifications
validateEmail(email)        // Email format validation
debounce(func, wait)       // Performance optimization for search
```

### 4. Component Loading (`component-loader.js`)
```javascript
loadNavbar()               // Load and initialize navigation
// Handles path resolution for different directory levels
```

## Data Flow Patterns

### Authentication Workflow
1. **Login**: POST to `/api/auth/index.php?action=login`
2. **Token Storage**: Store `access_token` and `refresh_token` in localStorage
3. **Auto-Refresh**: On 401 errors, attempt refresh before failing
4. **Logout**: Clear localStorage and redirect to login

### Event Registration Flow
1. **Authentication Check**: Verify user is logged in
2. **API Call**: POST to `/api/events/index.php?action=register`
3. **UI Update**: Update button state and registration count
4. **Notification**: Show success/error message

### Admin Operations Pattern
```javascript
// Standard CRUD pattern used throughout admin interface
async function updateEntity(id, data) {
    try {
        showLoading();
        const response = await requestWithAuth('PATCH', `/api/entity/${id}`, data);
        updateUIState(response.data);
        showSuccess('Updated successfully');
    } catch (error) {
        showError(error.message);
    } finally {
        hideLoading();
    }
}
```

## Responsive Design Implementation

### Tailwind CSS Patterns
```html
<!-- Standard responsive layout -->
<div class="flex-col md:flex-row max-md:space-y-3 max-md:items-center">
    <!-- Content stacks on mobile, side-by-side on desktop -->
</div>

<!-- Responsive grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <!-- 1 column mobile, 2 tablet, 3 desktop -->
</div>
```

### Mobile-First Approach
- Base styles for mobile devices
- `md:` prefix for tablet and up (768px+)
- `lg:` prefix for desktop (1024px+)

## Error Handling Strategy

### Client-Side Error Types
1. **Network Errors**: Connection failures, timeouts
2. **Authentication Errors**: Invalid tokens, expired sessions
3. **Validation Errors**: Form input validation failures
4. **Authorization Errors**: Insufficient permissions

### Error Display Patterns
```javascript
// Consistent error messaging
showNotification(error.message || 'Operation failed. Please try again.', true);

// Form-specific errors
displayFieldErrors(error.response?.data?.details || {});
```

## Performance Optimizations

### Image Handling
- Placeholder URLs for missing images: `https://placehold.co/400x200`
- Lazy loading for event banners and club logos
- S3 URLs for uploaded content

### Search and Filtering
- Debounced search input (300ms delay)
- Client-side filtering for immediate feedback
- Server-side filtering for large datasets

### DOM Updates
- Minimal DOM manipulation
- Event delegation for dynamic content
- Efficient list rendering with document fragments

## Security Considerations

### XSS Prevention
- Input sanitization before DOM insertion
- Use of `textContent` instead of `innerHTML` where possible
- CSP headers for script execution control

### Authentication Security
- JWT tokens with short expiration (1 hour)
- Automatic token cleanup on logout
- Secure token storage considerations

## Development Workflow

### Local Development Setup
1. **Prerequisites**: Modern browser with ES6 support
2. **Static Server**: Use any HTTP server (Python's http.server, Node's serve, etc.)
3. **API Backend**: Ensure backend is running on `http://localhost:8000`
4. **Environment**: Update API URLs in JavaScript files if needed

### Testing Patterns
```javascript
// Manual testing helpers
console.log('Current User:', getCurrentUser());
console.log('Auth Token:', localStorage.getItem('access_token'));

// API testing
const response = await request('GET', '/api/events/index.php');
console.log('Events:', response.data);
```

### Browser Compatibility
- **Modern Browsers**: Chrome 61+, Firefox 60+, Safari 11+, Edge 16+
- **Required Features**: ES6 modules, async/await, fetch API
- **Polyfills**: None required for target browsers

## Integration Points

### Backend API Communication
- **Base URL**: Configurable in each JavaScript module
- **Headers**: `Content-Type: application/json`, `Authorization: Bearer <token>`
- **Response Format**: Standardized JSON with `status`, `message`, `data` fields

### External Services
- **AWS S3**: File upload handling via backend
- **Email**: All email operations handled by backend
- **Notifications**: Browser notifications for real-time updates (future enhancement)

This architecture provides a solid foundation for a modern web application while maintaining simplicity and avoiding heavy frameworks.
