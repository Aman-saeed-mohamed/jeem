<?php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

if (!empty($_SESSION['user_id'])) {
    

    redirect_to_dashboard();
} else {
    

    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}
