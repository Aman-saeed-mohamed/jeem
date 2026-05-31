<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

require_guest();

$error   = '';
$success = '';

if (!empty($_GET['registered'])) {
    $success = '✅ Account created successfully! Please sign in.';
}
if (!empty($_GET['logged_out'])) {
    $success = 'You have been signed out. See you soon!';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        
        $stmt = $conn->prepare(
            "SELECT id, name, role, password_hash FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password_hash'])) {
            
            session_regenerate_id(true);

            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            redirect_to_dashboard();
        } else {
            

            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Log in to JEEM MALL — your premium multi-brand shopping destination.">
    <title>Login — JEEM MALL</title>
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
    <div class="auth-card">

        
        <div class="auth-brand">
            <div class="logo-text brand-gradient">JEEM MALL</div>
            <div class="tagline">Your premium marketplace destination</div>
        </div>

        
        <h1 class="auth-title">Welcome back</h1>
        <p class="auth-subtitle">Sign in to continue to your account.</p>

        
        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                ⚠️ <?= e($error) ?>
            </div>
        <?php endif; ?>

        
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        
        <form method="POST" action="" novalidate>

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
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg" id="btn-login">
                Sign In →
            </button>
        </form>

        <div class="auth-divider"></div>

        
        <div class="auth-footer">
            Don't have an account?
            <a href="<?= BASE_URL ?>/auth/register.php">Create one</a>
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
