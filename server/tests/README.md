# Test Folder Rules

## Development Database Testing

**IMPORTANT:** All tests in this folder use the **development database**.

### Rules:
1. **No data cleanup** - Tests should NOT delete or clean up data after execution
2. **Preserve test data** - Leave all created data in the database for inspection
3. **Individual test files** - Each test focuses on a single functionality
4. **No large monolithic files** - Keep tests small and focused
5. **Development environment only** - These tests are for development use only
6. **Test-First Development** - Write the test first, then test existing code. If code doesn't work as expected or meet standards, change the code to pass the test. DO NOT lower test standards to match poor code.

### Folder Structure:
- `auth/` - Authentication related tests (login, register, logout)
- `events/` - Event management tests (create, list, update, register)
- `clubs/` - Club management tests (create, list, join)
- `users/` - User management tests (create, list, profile updates)
- `comments/` - Comment system tests (create, list, moderate)
- `utils/` - Utility function tests (email, upload)
- `middleware/` - Middleware tests (auth, validation)

### Running Tests:
Each test file can be run individually:
```bash
php tests/auth/login_test.php
php tests/events/create_event_test.php
```

### Note:
Exception: Delete operations may remove data as that's their intended functionality, but creation and update tests should preserve their test data.