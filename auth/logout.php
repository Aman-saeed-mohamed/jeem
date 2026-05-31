<?php
/**
 * =============================================================
 * JEEM MALL — Logout
 * =============================================================
 * Properly destroys the session:
 *   1. Unsets all session variables.
 *   2. Destroys the server-side session data.
 *   3. Expires the session cookie in the browser.
 *   4. Redirects to login.
 * =============================================================
 */

// Session must be started before it can be destroyed.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php'; // for BASE_URL

// 1. Clear all session variables.
$_SESSION = [];

// 2. Expire the session cookie immediately.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,        // Set expiry in the past
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3. Destroy the session on the server.
session_destroy();

// 4. Redirect to login.
header('Location: ' . BASE_URL . '/auth/login.php?logged_out=1');
exit;
