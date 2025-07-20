# Client-Side Files Reference for AI Agents

## Overview
This document catalogs all developer-created files in the client directory for the USIU Events Management System. Each file is categorized by type and purpose, with technical details for AI agent understanding.

## File Categories

### JavaScript Modules (15 files)

#### Core Application Modules
| File | Purpose | Key Functions | Dependencies |
|------|---------|---------------|--------------|
| `assets/js/main.js` | Application initialization, navbar management, global setup | `initializeApp()`, `setupNavbar()`, `handleNavigation()` | auth.js, component-loader.js |
| `assets/js/auth.js` | Authentication state management, JWT handling | `isAuthenticated()`, `getCurrentUser()`, `logout()`, `handleTokenRefresh()` | http.js, utils.js |
| `assets/js/http.js` | API communication layer with error handling | `request()`, `requestWithAuth()`, error classes | auth.js |
| `assets/js/utils.js` | Date formatting, validation utilities, notifications | `formatUserDate()`, `showNotification()`, `validateEmail()` | None |
| `assets/js/component-loader.js` | Dynamic HTML component loading | `loadNavbar()`, path resolution utilities | None |

#### Page-Specific Modules
| File | Purpose | Key Functions | Dependencies |
|------|---------|---------------|--------------|
| `assets/js/events.js` | Event listing, filtering, search, registration | `loadEvents()`, `registerForEvent()`, `filterEvents()` | http.js, auth.js, utils.js |
| `assets/js/event-details.js` | Single event view, comments, registration | `loadEventDetails()`, `loadComments()`, `submitComment()` | http.js, auth.js, utils.js |
| `assets/js/clubs.js` | Club listing, filtering, category management | `loadClubs()`, `filterClubs()`, `joinClub()` | http.js, auth.js, utils.js |
| `assets/js/club-details.js` | Single club view, member management | `loadClubDetails()`, `joinClub()`, `loadClubEvents()` | http.js, auth.js, utils.js |
| `assets/js/dashboard.js` | User dashboard, registered events display | `loadUserDashboard()`, `loadRegisteredEvents()` | http.js, auth.js, utils.js |

#### Form Handling Modules
| File | Purpose | Key Functions | Dependencies |
|------|---------|---------------|--------------|
| `assets/js/login.js` | Login form handling, authentication flow | `handleLogin()`, `validateLoginForm()` | http.js, auth.js, utils.js |
| `assets/js/register.js` | Registration form with validation | `handleRegistration()`, `validateRegistrationForm()` | http.js, utils.js |
| `assets/js/forgot-password.js` | Password reset request flow | `handlePasswordReset()`, `validateEmail()` | http.js, utils.js |
| `assets/js/change-password.js` | Password change form handling | `handlePasswordChange()`, `validatePasswordForm()` | http.js, auth.js, utils.js |

#### Admin Interface Modules
| File | Purpose | Key Functions | Dependencies |
|------|---------|---------------|--------------|
| `assets/js/admin/admin-dashboard.js` | Admin control panel, CRUD operations, comment moderation | `loadAdminData()`, `moderateComment()`, `exportData()` | http.js, auth.js, utils.js, admin-stats.js |
| `assets/js/admin/admin-stats.js` | Statistics calculations for admin dashboard | `calculateStats()`, `updateStatsDisplay()` | utils.js |
| `assets/js/admin/admin-events.js` | Event management in admin interface | `manageEvents()`, `toggleEventStatus()` | http.js, auth.js, utils.js |
| `assets/js/admin/admin-clubs.js` | Club management in admin interface | `manageClubs()`, `toggleClubStatus()` | http.js, auth.js, utils.js |

#### API Communication Module
| File | Purpose | Key Functions | Dependencies |
|------|---------|---------------|--------------|
| `assets/js/api.js` | Legacy API wrapper (consider deprecation) | Various API call wrappers | http.js |

### HTML Files (14 files)

#### Main Application Pages
| File | Purpose | Key Features |
|------|---------|--------------|
| `index.html` | Landing page with hero section | Hero banner, featured events, navigation setup |
| `pages/events.html` | Event listing with search/filter | Search bar, category filters, pagination |
| `pages/event-details.html` | Single event view with registration | Event info, registration form, comments section |
| `pages/clubs.html` | Club listing with categories | Category filters, club grid layout |
| `pages/club-details.html` | Single club view with join functionality | Club info, member list, events list |
| `pages/dashboard.html` | User dashboard with registered events | User profile, registered events, quick actions |

#### Authentication Pages
| File | Purpose | Key Features |
|------|---------|--------------|
| `pages/login.html` | User login form | Email/password form, remember me, forgot password link |
| `pages/register.html` | User registration form | Multi-field validation, USIU email requirement |
| `pages/verify-email.html` | Email verification handler | Token verification, success/error states |
| `pages/forgot-password.html` | Password reset request | Email input, validation, submit handling |
| `pages/change-password.html` | Password change form | Current/new password fields, validation |

#### Admin Interface Pages
| File | Purpose | Key Features |
|------|---------|--------------|
| `pages/admin/admin-dashboard.html` | Main admin control panel | Tabbed interface, statistics, CRUD operations |
| `pages/admin/create-event.html` | Event creation form | Rich form fields, image upload, validation |
| `pages/admin/create-club.html` | Club creation form | Club details, leader assignment, category selection |

#### Reusable Components
| File | Purpose | Key Features |
|------|---------|--------------|
| `components/navbar.html` | Main navigation component | Responsive design, auth state handling, mobile menu |

### CSS and Assets (6 files)

#### Stylesheets
| File | Purpose | Key Features |
|------|---------|--------------|
| `assets/css/style.css` | Custom styles and Tailwind overrides | Utility classes, component styles, responsive design |

#### Images and Icons
| File | Purpose | Usage |
|------|---------|-------|
| `assets/images/logo.png` | USIU Events logo | Navbar, branding |
| `assets/images/hero-bg.jpg` | Homepage hero background | Landing page banner |
| `assets/images/avatar.png` | Default user avatar | Profile placeholders |
| `favicon.ico` | Browser favicon | Tab icon |

## Architecture Patterns

### Module Dependencies
```
main.js → auth.js, component-loader.js
auth.js → http.js, utils.js
All page modules → http.js, auth.js, utils.js
Admin modules → http.js, auth.js, utils.js, admin-stats.js
```

### Data Flow
1. **Authentication Flow**: login.js → auth.js → http.js → API
2. **Page Loading**: main.js → component-loader.js → page-specific.js
3. **API Communication**: page.js → http.js → backend API
4. **Error Handling**: http.js → utils.js (notifications)

### Responsive Design Implementation
- **Mobile-First**: Base styles for mobile, progressive enhancement
- **Breakpoints**: Tailwind CSS responsive utilities (md:, lg:, xl:)
- **Components**: Flexible layouts using Flexbox and CSS Grid

### Security Considerations
- **Input Sanitization**: XSS prevention in form handling
- **Authentication**: JWT token management with automatic refresh
- **Authorization**: Role-based access control for admin features

## Code Quality Standards

### Commenting Requirements
1. **Function Documentation**: Purpose, parameters, return values
2. **Complex Logic**: Step-by-step explanations
3. **API Integration**: Endpoint documentation and error handling
4. **Event Handlers**: User interaction flow documentation
5. **Security Notes**: Authentication and validation explanations

### Performance Considerations
- **Async Operations**: Proper error handling and loading states
- **DOM Manipulation**: Efficient updates and event delegation
- **Image Loading**: Lazy loading and placeholder handling
- **API Calls**: Debounced search, pagination, caching strategies

### Browser Compatibility
- **ES6+ Features**: Modern JavaScript with fallbacks
- **CSS Grid/Flexbox**: Progressive enhancement
- **Fetch API**: Native browser support requirement

This reference provides comprehensive technical details for AI agents to understand the client-side architecture, file relationships, and implementation patterns in the USIU Events Management System.