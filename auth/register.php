<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

require_guest();

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    

    $name            = trim($_POST['name']             ?? '');
    $email           = trim($_POST['email']            ?? '');
    $password        = trim($_POST['password']         ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $address         = trim($_POST['address']          ?? '');

    

    

    if (empty($name)) {
        $errors[] = 'Full name is required.';
    } elseif (strlen($name) > 100) {
        $errors[] = 'Name must not exceed 100 characters.';
    }

    

    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (strlen($email) > 150) {
        $errors[] = 'Email must not exceed 150 characters.';
    }

    

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    

    if (empty($address)) {
        $errors[] = 'Address is required.';
    }

    

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = 'This email address is already registered. Please log in.';
        }
        $stmt->close();
    }

    

    if (empty($errors)) {
        
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        
        $conn->begin_transaction();

        try {
            

            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)"
            );
            $stmt->bind_param('sss', $name, $email, $hashed);
            $stmt->execute();
            $new_user_id = $conn->insert_id;
            $stmt->close();

            

            $is_default = 1;
            $stmt = $conn->prepare(
                "INSERT INTO user_addresses (user_id, address, is_default)
                 VALUES (?, ?, ?)"
            );
            $stmt->bind_param('isi', $new_user_id, $address, $is_default);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            

            header('Location: ' . BASE_URL . '/auth/login.php?registered=1');
            exit;

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            error_log('[JEEM MALL REGISTER ERROR] ' . $e->getMessage());
            $errors[] = 'Registration failed due to a server error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create your JEEM MALL account and start shopping today.">
    <title>Create Account — JEEM MALL</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script>(function(){var s=localStorage.getItem('jeemTheme')||'dark';document.getElementById('html-root').setAttribute('data-theme',s);})();</script>
</head>
<body>

<button id="theme-toggle" onclick="jeemToggleTheme()"
        style="position:fixed;top:1rem;right:1rem;z-index:999;"
        title="Toggle light/dark mode" aria-label="Toggle theme">
    <span class="toggle-track"><span class="toggle-knob"></span></span>
    <span class="toggle-icon">☀️</span>
    <span class="toggle-label">Light Mode</span>
</button>

<div class="auth-page">
    <div class="auth-card" style="max-width:480px;">

        
        <div class="auth-brand">
            <div class="logo-text brand-gradient">JEEM MALL</div>
            <div class="tagline">Your premium marketplace destination</div>
        </div>

        
        <h1 class="auth-title">Create an account</h1>
        <p class="auth-subtitle">Join thousands of shoppers on JEEM MALL.</p>

        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error" role="alert">
                <strong>Please fix the following:</strong>
                <ul style="margin:0.5rem 0 0 1.2rem; padding:0;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        
        <form method="POST" action="" novalidate>

            <div class="form-group">
                <label class="form-label" for="name">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control"
                    placeholder="Ahmed Al-Rashidi"
                    value="<?= e($_POST['name'] ?? '') ?>"
                    required
                    autocomplete="name"
                    maxlength="100"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="you@example.com"
                    value="<?= e($_POST['email'] ?? '') ?>"
                    required
                    autocomplete="email"
                    maxlength="150"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="At least 6 characters"
                    required
                    autocomplete="new-password"
                    minlength="6"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    class="form-control"
                    placeholder="Repeat your password"
                    required
                    autocomplete="new-password"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="address">Default Delivery Address</label>
                <textarea
                    id="address"
                    name="address"
                    class="form-control"
                    placeholder="Building, Street, District, City"
                    required
                    rows="3"
                ><?= e($_POST['address'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg" id="btn-register">
                Create Account →
            </button>
        </form>

        <div class="auth-divider"></div>

        
        <div class="auth-footer">
            Already have an account?
            <a href="<?= BASE_URL ?>/auth/login.php">Sign in</a>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function jeemToggleTheme(){var h=document.getElementById('html-root'),c=h.getAttribute('data-theme')||'dark',n=c==='dark'?'light':'dark';h.setAttribute('data-theme',n);localStorage.setItem('jeemTheme',n);var i=document.querySelector('#theme-toggle .toggle-icon'),l=document.querySelector('#theme-toggle .toggle-label');if(i)i.textContent=n==='light'?'🌙':'☀️';if(l)l.textContent=n==='light'?'Dark Mode':'Light Mode';}
(function(){var s=localStorage.getItem('jeemTheme')||'dark';var i=document.querySelector('#theme-toggle .toggle-icon'),l=document.querySelector('#theme-toggle .toggle-label');if(i)i.textContent=s==='light'?'🌙':'☀️';if(l)l.textContent=s==='light'?'Dark Mode':'Light Mode';})();
</script>
</body>
</html>
