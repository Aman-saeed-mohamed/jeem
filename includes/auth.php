<?php
// includes/auth.php — RBAC Middleware
// Include this at the top of every protected page.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not authenticated
function require_login() {
    if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header("Location: login.php");
        exit();
    }
}

// Restrict page to specific roles. Unauthorized users go to their own dashboard.
function require_role($allowed_roles) {
    require_login();
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        $role = $_SESSION['role'];
        if ($role === 'admin')        header("Location: admin_dashboard.php");
        elseif ($role === 'manager')  header("Location: manager_dashboard.php");
        else                          header("Location: customer_dashboard.php");
        exit();
    }
}

// Redirect already-logged-in users away from login/register pages
function require_guest() {
    if (!empty($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        $role = $_SESSION['role'] ?? 'customer';
        if ($role === 'admin')       header("Location: admin_dashboard.php");
        elseif ($role === 'manager') header("Location: manager_dashboard.php");
        else                         header("Location: customer_dashboard.php");
        exit();
    }
}
?>
