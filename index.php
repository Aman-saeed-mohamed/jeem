<?php
/**
 * =============================================================
 * JEEM MALL — Entry Point (index.php)
 * =============================================================
 * The root URL of the application. Redirects visitors to:
 *   - Their dashboard if already logged in.
 *   - The login page if they are a guest.
 * =============================================================
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

if (!empty($_SESSION['user_id'])) {
    // Logged in — go to the correct dashboard.
    redirect_to_dashboard();
} else {
    // Guest — go to login.
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}
