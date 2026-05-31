<?php
include "includes/connect.php";
include "includes/auth.php";
require_guest();

$msg     = "";
$success = false;

if (isset($_POST["submit"])) {
    $name     = trim($_POST["name"]);
    $email    = trim($_POST["email"]);
    $address  = trim($_POST["address"]);
    $password = $_POST["password"];
    $confirm  = $_POST["confirm"];

    if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
        $msg = "All fields except address are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $msg = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $msg = "Passwords do not match.";
    } else {
        $safe_email = mysqli_real_escape_string($conn, $email);
        $check      = mysqli_query($conn, "SELECT id FROM users WHERE email = '$safe_email'");

        if (mysqli_num_rows($check) > 0) {
            $msg = "This email is already registered. Please log in.";
        } else {
            $safe_name    = mysqli_real_escape_string($conn, $name);
            $safe_address = mysqli_real_escape_string($conn, $address);
            $hash         = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (name, email, password, address, role)
                    VALUES ('$safe_name', '$safe_email', '$hash', '$safe_address', 'customer')";

            if (mysqli_query($conn, $sql)) {
                $success = true;
            } else {
                $msg = "Registration failed. Please try again.";
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
    <title>Register - Jeem Mall</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .error-msg   { background:#ffe0e0; color:#c0392b; border:1px solid #f5c6cb; border-radius:8px; padding:10px 14px; margin-top:15px; font-size:.9rem; }
        .success-msg { background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:8px; padding:10px 14px; margin-top:15px; font-size:.9rem; }
        .right { overflow-y: auto; }
    </style>
</head>
<body>
<div class="container">
    <div class="left">
        <h1 class="logo">JEEM</h1>
        <h2>Join the JEEM Family</h2>
        <p>Create your free account and start shopping from hundreds of stores today.</p>
    </div>
    <div class="right">
        <h2>Create Account</h2>
        <?php if ($success): ?>
            <div class="success-msg">✅ Account created! <a href="login.php" style="color:#155724;font-weight:bold;">Sign In now</a></div>
        <?php else: ?>
            <?php if ($msg): ?><div class="error-msg"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
            <form action="register.php" method="post">
                <label for="name">Full Name</label>
                <input type="text" name="name" id="name" placeholder="Your full name" required
                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">

                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" placeholder="your@email.com" required
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">

                <label for="address">Address (optional)</label>
                <input type="text" name="address" id="address" placeholder="City, Country"
                       value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">

                <label for="password">Password</label>
                <div class="passwordBox">
                    <input type="password" name="password" id="password" placeholder="Min. 6 characters" required>
                    <button type="button" onclick="togglePwd('password',this)">Show</button>
                </div>

                <label for="confirm">Confirm Password</label>
                <div class="passwordBox">
                    <input type="password" name="confirm" id="confirm" placeholder="Repeat password" required>
                    <button type="button" onclick="togglePwd('confirm',this)">Show</button>
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
function togglePwd(id, btn) {
    const f = document.getElementById(id);
    f.type = f.type === "password" ? "text" : "password";
    btn.textContent = f.type === "password" ? "Show" : "Hide";
}
</script>
</body>
</html>
