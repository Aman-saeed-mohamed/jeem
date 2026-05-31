<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_role(string|array $role): void
{
    

    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }

    

    $allowed = is_array($role) ? $role : [$role];

    

    if (!in_array($_SESSION['user_role'], $allowed, true)) {
        

        redirect_to_dashboard();
    }
}

function require_guest(): void
{
    if (!empty($_SESSION['user_id'])) {
        redirect_to_dashboard();
    }
}

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
        default: 

            header('Location: ' . BASE_URL . '/customer/customer_dashboard.php');
            break;
    }
    exit;
}

function has_role(string $role): bool
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $stored    = $_SESSION['csrf_token'] ?? '';

    

    if (!hash_equals($stored, $submitted)) {
        http_response_code(403);
        die('<h3 style="font-family:sans-serif;color:#ef4444;padding:2rem;">403 Forbidden — Invalid security token. Please go back and try again.</h3>');
    }
}
