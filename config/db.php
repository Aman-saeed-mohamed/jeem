<?php
/**
 * =============================================================
 * JEEM MALL — Database Configuration
 * =============================================================
 * Single point of truth for DB credentials and the MySQLi
 * connection object. Every page that needs the DB does:
 *   require_once __DIR__ . '/../config/db.php';
 * and uses $conn directly.
 * =============================================================
 */

// ── Credentials ──────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');            // Default XAMPP password is empty
define('DB_NAME', 'jeem_mall');

// ── Application Base URL ─────────────────────────────────────
// Used for header() redirects throughout the app.
define('BASE_URL', '/jeem mall');

// ── MySQLi Strict Error Mode ──────────────────────────────────
// Throws a mysqli_sql_exception on any query/connection failure
// instead of silently returning false. This surfaces bugs early.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Enforce UTF-8 for all communication with the database.
    $conn->set_charset('utf8mb4');

} catch (mysqli_sql_exception $e) {
    // Log the real error server-side; never expose credentials to the browser.
    error_log('[JEEM MALL DB ERROR] ' . $e->getMessage());

    // Show a friendly message to the user.
    http_response_code(503);
    die('⚠️  Database connection failed. Please contact the administrator.');
}
