## Authentication Module Access Guidelines

This directory (`api/auth/`) contains the backend logic for user authentication, including registration, login, password reset, and email verification.

### Access Patterns:

1.  **Primary Entry Point (`index.php`):**
    *   Most authentication actions (e.g., `register`, `login`, `logout`, `refresh_token`) should be routed through `api/auth/index.php` using the `?action=` query parameter (e.g., `/api/auth/index.php?action=login`).
    *   This file acts as a central controller, validating requests and dispatching to the appropriate handler script.

2.  **Directly Accessible Endpoints (Exceptions):**
    *   `verify_email.php`
    *   `reset_password.php`
    *   These two files are designed to be accessed directly via unique, time-limited links sent to users' email addresses. Their security relies on the embedded tokens, not on being routed through `index.php`.

3.  **Internal-Only Files:**
    *   All other `.php` files within this directory (e.g., `login.php`, `register.php`, `logout.php`, `refresh_token.php`) are **internal handler scripts**.
    *   They are **NOT** meant to be accessed directly by external requests.
    *   They should only be included (`require_once`) by `api/auth/index.php`.

### Enforcement:

*   A PHP-level check using the `IS_AUTH_ROUTE` constant is implemented in internal files to prevent direct access. If an internal file is accessed directly, it will return a `403 Forbidden` error.

### Best Practices:

*   When adding new authentication features, consider whether they are a primary action (route through `index.php`) or a token-based, direct-access endpoint (like email verification).
*   Always ensure sensitive operations are protected by appropriate validation and authentication mechanisms.
