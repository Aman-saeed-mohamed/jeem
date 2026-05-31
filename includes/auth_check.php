<?php
/**
 * =============================================================
 * JEEM MALL — RBAC Authentication Gatekeeper
 * =============================================================
 * Provides the core security functions used at the top of every
 * protected page. Include this file BEFORE any HTML output.
 *
 * Usage examples:
 *   require_role('admin');               // Admin-only page
 *   require_role('manager');             // Manager-only page
 *   require_role(['customer','manager']); // Multi-role page
 *   require_guest();                     // Login/Register pages
 * =============================================================
 */

// Start the session if it hasn't been started yet.
// Using session_status() prevents "session already active" warnings.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─────────────────────────────────────────────────────────────
// require_role()
// ─────────────────────────────────────────────────────────────
/**
 * Enforces that the current visitor is logged in AND has the
 * expected role(s). Unauthenticated users go to login.
 * Authenticated users with the wrong role go to their own dashboard.
 *
 * @param string|array $role  One role or an array of allowed roles.
 */
function require_role(string|array $role): void
{
    // 1. Must be logged in.
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }

    // 2. Build the allowed-roles list.
    $allowed = is_array($role) ? $role : [$role];

    // 3. Current session role must be in the allowed list.
    if (!in_array($_SESSION['user_role'], $allowed, true)) {
        // Wrong role: bounce them to wherever they belong.
        redirect_to_dashboard();
    }
}

// ─────────────────────────────────────────────────────────────
// require_guest()
// ─────────────────────────────────────────────────────────────
/**
 * Enforces that the current visitor is NOT logged in.
 * Used on login.php and register.php to prevent already-logged-in
 * users from seeing those pages.
 */
function require_guest(): void
{
    if (!empty($_SESSION['user_id'])) {
        redirect_to_dashboard();
    }
}

// ─────────────────────────────────────────────────────────────
// redirect_to_dashboard()
// ─────────────────────────────────────────────────────────────
/**
 * Sends the user to the correct dashboard for their role.
 * Always followed by exit to stop script execution.
 */
function redirect_to_dashboard(): void
{
    $role = $_SESSION['user_role'] ?? 'customer';

    switch ($role) {
        case 'admin':
            header('Location: ' . BASE_URL . '/admin/admin_dashboard.php');
            break;
        case 'manager':
            header('Location: ' . BASE_URL . '/manager/manager_dashboard.php');
            break;
        default: // 'customer'
            header('Location: ' . BASE_URL . '/customer/customer_dashboard.php');
            break;
    }
    exit;
}

// ─────────────────────────────────────────────────────────────
// Convenience helpers
// ─────────────────────────────────────────────────────────────

/**
 * Returns true if the logged-in user has the given role.
 *
 * @param string $role
 * @return bool
 */
function has_role(string $role): bool
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Returns the current user's ID (int) or null if not logged in.
 *
 * @return int|null
 */
function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Sanitizes output to prevent XSS. Wraps htmlspecialchars() with
 * the correct flags for HTML5 documents.
 *
 * @param mixed $value
 * @return string
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ─────────────────────────────────────────────────────────────
// CSRF Protection Helpers
// ─────────────────────────────────────────────────────────────

/**
 * Returns the current session CSRF token, generating one if needed.
 * Call this to output the hidden field value in every POST form.
 *
 * @return string 64-char hex token
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifies the submitted CSRF token against the session token.
 * Terminates with 403 if they do not match.
 * Must be called at the top of every POST handler.
 */
function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $stored    = $_SESSION['csrf_token'] ?? '';

    // hash_equals() prevents timing attacks.
    if (!hash_equals($stored, $submitted)) {
        http_response_code(403);
        die('<h3 style="font-family:sans-serif;color:#ef4444;padding:2rem;">403 Forbidden — Invalid security token. Please go back and try again.</h3>');
    }
}
