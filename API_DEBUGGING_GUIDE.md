# API Debugging Guide for AI Agents

## Quick Diagnosis Commands

### Health Check Endpoints
```bash
# Test database connection
curl -X GET "http://localhost:8000/api/health.php"

# Test authentication flow
curl -X POST "http://localhost:8000/api/auth/index.php?action=login" \
  -H "Content-Type: application/json" \
  -d '{"email":"test@usiu.ac.ke","password":"password"}'
```

### Common Error Patterns and Solutions

#### 1. Authentication Errors

**Error**: `Authorization header missing`
```json
{"error": "Authentication required. Please log in to access this resource."}
```
**Diagnosis**: Missing or malformed Authorization header
**Solution**: Add header: `Authorization: Bearer <jwt_token>`

**Error**: `Session expired or invalid`
```json
{"error": "Session expired or invalid. Please log in again.", "details": {"error_type": "invalid_token"}}
```
**Diagnosis**: JWT token expired or corrupted
**Solution**: Use refresh token or force user to login again

#### 2. Database Connection Issues

**Error**: `Database connection failed`
**Diagnosis Steps**:
1. Check MongoDB service: `systemctl status mongod`
2. Verify connection string in `/server/.env`
3. Test direct connection: `mongo "mongodb://localhost:27017/usiu_events"`

#### 3. Validation Errors

**Pattern**: Field-specific validation messages
```json
{
  "error": "Validation failed",
  "details": {
    "email": "Invalid email format. Please enter a valid USIU email ending with @usiu.ac.ke",
    "password": "Password must be at least 8 characters"
  }
}
```
**Diagnosis**: Client sending invalid data
**Solution**: Check schema requirements in `/server/schemas/`

#### 4. File Upload Errors

**Error**: `Failed to upload file to S3`
**Diagnosis Steps**:
1. Check AWS credentials in `.env`
2. Verify S3 bucket permissions
3. Check file size (max 5MB) and type (JPEG, PNG, GIF, WebP)

### Debug Mode Activation

#### Enable PHP Error Logging
```php
// Add to /server/config/config.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');
```

#### MongoDB Query Debugging
```php
// Add to any model method
error_log("MongoDB Query: " . json_encode($pipeline));
error_log("MongoDB Result: " . json_encode($result));
```

#### Frontend Debug Console
```javascript
// Add to any API call
console.log('Request URL:', url);
console.log('Request Data:', data);
console.log('Response:', response);
```

### API Response Time Analysis

#### Slow Response Indicators
- **Database queries**: Check aggregation pipeline complexity
- **File uploads**: Verify S3 connection speed
- **Email sending**: SMTP server response time

#### Performance Monitoring
```javascript
// Client-side timing
const start = performance.now();
const response = await request('GET', '/api/events/index.php');
const duration = performance.now() - start;
console.log(`API call took ${duration} milliseconds`);
```

### MongoDB Specific Issues

#### Common Aggregation Problems
```javascript
// Problem: Missing $unwind for array fields
// Solution: Add preserveNullAndEmptyArrays: true
{ $unwind: { path: '$user', preserveNullAndEmptyArrays: true } }

// Problem: ObjectId string conversion
// Solution: Ensure proper ObjectId creation
{ user_id: new ObjectId(userId) }
```

#### Index Requirements
```javascript
// Essential indexes for performance
db.users.createIndex({ email: 1 });
db.users.createIndex({ student_id: 1 });
db.events.createIndex({ club_id: 1, status: 1 });
db.comments.createIndex({ event_id: 1, status: 1 });
```

### Frontend Debugging Patterns

#### Authentication State Issues
```javascript
// Check current auth state
console.log('Is Authenticated:', isAuthenticated());
console.log('Current User:', getCurrentUser());
console.log('Token:', localStorage.getItem('access_token'));
```

#### API Communication Debugging
```javascript
// In http.js - add debug logging
async function request(method, url, data = null) {
    console.group(`🌐 ${method} ${url}`);
    console.log('Request Data:', data);
    
    try {
        const response = await fetch(/* ... */);
        console.log('Response Status:', response.status);
        console.log('Response Data:', responseData);
        return responseData;
    } catch (error) {
        console.error('Request Failed:', error);
        throw error;
    } finally {
        console.groupEnd();
    }
}
```

### Environment Variable Checklist

#### Required Server Variables
```bash
# Database
MONGODB_URI=mongodb://localhost:27017/usiu_events

# Authentication
JWT_SECRET=minimum_32_character_secret_key

# AWS S3
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_S3_REGION=us-east-1
AWS_S3_BUCKET=your_bucket_name

# Email (Mailtrap for development)
SMTP_HOST=smtp.mailtrap.io
SMTP_PORT=2525
SMTP_USERNAME=your_mailtrap_username
SMTP_PASSWORD=your_mailtrap_password
SMTP_FROM_EMAIL=noreply@usiu.ac.ke
SMTP_FROM_NAME="USIU Events"

# Application URLs
APP_URL=http://localhost:3000
API_URL=http://localhost:8000
```

### Common Development Issues

#### 1. CORS Errors
**Symptom**: `Access to fetch at 'api_url' from origin 'client_url' has been blocked by CORS policy`
**Solution**: Check `/server/config/cors.php` configuration

#### 2. PHP Fatal Errors
**Location**: Check `/var/log/apache2/error.log` or `/var/log/nginx/error.log`
**Common Causes**:
- Missing `require_once` statements
- Undefined constants
- Class not found errors

#### 3. Database Connection Timeout
**Symptoms**: Long response times, connection refused errors
**Solutions**:
- Increase MongoDB timeout settings
- Check network connectivity
- Verify MongoDB service status

#### 4. File Permission Issues
**Symptoms**: Cannot write to log files, upload failures
**Solution**: Check directory permissions
```bash
chmod 755 /server/logs/
chmod 644 /server/.env
```

### Testing Specific Features

#### User Registration Flow
```bash
# 1. Register user
curl -X POST "http://localhost:8000/api/auth/index.php?action=register" \
  -H "Content-Type: application/json" \
  -d '{"student_id":"TEST123","first_name":"Test","last_name":"User","email":"test@usiu.ac.ke","password":"password123"}'

# 2. Check email verification (Mailtrap)
# 3. Verify email with token
curl -X GET "http://localhost:8000/api/auth/verify_email.php?token=VERIFICATION_TOKEN"
```

#### Event Management Flow
```bash
# 1. Login to get token
TOKEN=$(curl -s -X POST "http://localhost:8000/api/auth/index.php?action=login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@usiu.ac.ke","password":"password"}' | jq -r '.data.access_token')

# 2. Create event
curl -X POST "http://localhost:8000/api/events/index.php?action=create" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"title":"Test Event","description":"Test description","club_id":"CLUB_ID","event_date":"2025-12-31T10:00:00Z"}'
```

### Debugging Specific Modules

#### Comment System
```javascript
// Check comment aggregation pipeline
db.comments.aggregate([
  { $lookup: { from: 'events', localField: 'event_id', foreignField: '_id', as: 'event' } },
  { $unwind: { path: '$event', preserveNullAndEmptyArrays: true } },
  { $addFields: { event_title: '$event.title' } }
]);
```

#### Admin Dashboard
```javascript
// Check stats calculation
console.log('Total Events:', document.getElementById('total-events').textContent);
console.log('Total Users:', document.getElementById('total-users').textContent);
```

### Error Log Analysis

#### PHP Error Patterns
```bash
# Common error patterns to search for
grep "Fatal error" /var/log/apache2/error.log
grep "MongoDB" /var/log/apache2/error.log
grep "JWT" /var/log/apache2/error.log
```

#### Client-side Error Patterns
```javascript
// Browser console error patterns
// - Failed to fetch: Network issues
// - 401 Unauthorized: Authentication problems
// - 500 Internal Server Error: Server-side issues
// - CORS error: Cross-origin policy issues
```

This debugging guide provides systematic approaches to identify and resolve common issues in the USIU Events system, with specific focus on API interactions and data flow debugging.