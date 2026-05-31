<?php
include "includes/connect.php";

if (session_status() == PHP_SESSION_NONE) session_start();

// Already logged in → redirect
if (!empty($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("Location: customer_dashboard.php");
    exit();
}

$msg     = "";
$success = false;

if (isset($_POST["submit"])) {
    $email    = trim($_POST["email"]);
    $username = trim($_POST["username"]);
    $password = $_POST["password"];
    $confirm  = $_POST["confirm"];

    // --- Validation ---
    if (empty($email) || empty($username) || empty($password) || empty($confirm)) {
        $msg = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Please enter a valid email address.";
    } elseif (strlen($username) < 3) {
        $msg = "Username must be at least 3 characters.";
    } elseif (strlen($password) < 6) {
        $msg = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $msg = "Passwords do not match.";
    } else {
        $safe_email    = mysqli_real_escape_string($conn, $email);
        $safe_username = mysqli_real_escape_string($conn, $username);

        // Check duplicate email
        $check_email = mysqli_query($conn, "SELECT id FROM users WHERE email = '$safe_email'");
        if (mysqli_num_rows($check_email) > 0) {
            $msg = "This email is already registered. Please log in.";
        } else {
            // Check duplicate username
            $check_user = mysqli_query($conn, "SELECT id FROM users WHERE username = '$safe_username'");
            if (mysqli_num_rows($check_user) > 0) {
                $msg = "This username is already taken. Please choose another.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $role = "customer"; // All new registrations default to customer

                $sql = "INSERT INTO users (email, username, password, role)
                        VALUES ('$safe_email', '$safe_username', '$hash', '$role')";

                if (mysqli_query($conn, $sql)) {
                    $success = true;
                } else {
                    $msg = "Registration failed. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Jeem Mall</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .error-msg {
            background: #ffe0e0;
            color: #c0392b;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 10px 14px;
            margin-top: 15px;
            font-size: 0.9rem;
        }
        .success-msg {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 10px 14px;
            margin-top: 15px;
            font-size: 0.9rem;
        }
        /* Override right panel to scroll on small heights */
        .right {
            overflow-y: auto;
        }
    </style>
</head>
<body>

<div class="container">

    <!-- LEFT PANEL -->
    <div class="left">
        <h1 class="logo">JEEM</h1>
        <h2>Join the JEEM Family</h2>
        <p>Create your account to start shopping or open your own digital store in the JEEM marketplace.</p>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right">
        <h2>Create Account</h2>

        <?php if ($success): ?>
            <div class="success-msg">
                ✅ Account created successfully! <a href="login.php" style="color:#155724;font-weight:bold;">Sign In now</a>
            </div>
        <?php endif; ?>

        <?php if ($msg !== ""): ?>
            <div class="error-msg"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form action="signup.php" method="post">

            <label for="email">Email Address</label>
            <input type="email" name="email" id="email"
                   placeholder="your@email.com" required
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">

            <label for="username">Username</label>
            <input type="text" name="username" id="username"
                   placeholder="Choose a username" required
                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">

            <label for="password">Password</label>
            <div class="passwordBox">
                <input type="password" name="password" id="signupPassword"
                       placeholder="Min. 6 characters" required>
                <button type="button" onclick="togglePassword('signupPassword', this)">Show</button>
            </div>

            <label for="confirm">Confirm Password</label>
            <div class="passwordBox">
                <input type="password" name="confirm" id="confirmPassword"
                       placeholder="Repeat your password" required>
                <button type="button" onclick="togglePassword('confirmPassword', this)">Show</button>
            </div>

            <div class="buttons">
                <button type="submit" name="submit">Create Account</button>
                <a href="login.php" class="btn2">Already have an account? Sign In</a>
            </div>

        </form>
        <?php endif; ?>
    </div>

</div>

<script>
    function togglePassword(fieldId, btn) {
        const field = document.getElementById(fieldId);
        if (field.type === "password") {
            field.type = "text";
            btn.textContent = "Hide";
        } else {
            field.type = "password";
            btn.textContent = "Show";
        }
    }
</script>

</body>
</html>
