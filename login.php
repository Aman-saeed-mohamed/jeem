<?php
include "includes/connect.php";
include "includes/auth.php";
require_guest();

$msg = "";

if (isset($_POST["submit"])) {
    $email    = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {
        $msg = "Please fill in all fields.";
    } else {
        $safe_email = mysqli_real_escape_string($conn, $email);
        $result     = mysqli_query($conn, "SELECT * FROM users WHERE email = '$safe_email'");

        if ($result && mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            if (password_verify($password, $user["password"])) {
                $_SESSION["id"]       = $user["id"];
                $_SESSION["name"]     = $user["name"];
                $_SESSION["email"]    = $user["email"];
                $_SESSION["role"]     = $user["role"];
                $_SESSION["loggedin"] = true;

                if ($user["role"] === "admin")         header("Location: admin_dashboard.php");
                elseif ($user["role"] === "manager")   header("Location: manager_dashboard.php");
                else                                   header("Location: customer_dashboard.php");
                exit();
            } else {
                $msg = "Incorrect password.";
            }
        } else {
            $msg = "No account found with that email.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Jeem Mall</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .error-msg { background:#ffe0e0; color:#c0392b; border:1px solid #f5c6cb; border-radius:8px; padding:10px 14px; margin-top:15px; font-size:.9rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="left">
        <h1 class="logo">JEEM</h1>
        <h2>Welcome Back</h2>
        <p>Your trusted digital marketplace. Explore shops, manage stores, and connect with thousands of customers.</p>
    </div>
    <div class="right">
        <h2>Sign In</h2>
        <?php if ($msg): ?><div class="error-msg"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
        <form action="login.php" method="post">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" placeholder="your@email.com" required
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">

            <label for="password">Password</label>
            <div class="passwordBox">
                <input type="password" name="password" id="loginPassword" placeholder="Your password" required>
                <button type="button" onclick="togglePwd('loginPassword',this)">Show</button>
            </div>

            <div class="buttons">
                <button type="submit" name="submit">Sign In</button>
                <a href="register.php" class="btn2">Create a Customer Account</a>
            </div>
        </form>
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
