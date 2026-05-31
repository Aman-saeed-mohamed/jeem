<?php
include "includes/connect.php";
include "includes/auth.php";
require_role(['customer', 'manager', 'admin']);

$user_id = (int)$_SESSION['id'];
$user_res = mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'");
$user     = mysqli_fetch_assoc($user_res);

// Check if already a manager (has a shop)
$shop_res = mysqli_query($conn, "SELECT id FROM shops WHERE manager_id='$user_id'");
$is_manager = (mysqli_num_rows($shop_res) > 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account - Jeem Mall</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/customer.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="customer_dashboard.php">🛍 Jeem Mall</a>
        <div class="navbar-nav ms-auto d-flex flex-row gap-3 align-items-center">
            <a href="customer_dashboard.php" class="nav-link text-white">Home</a>
            <a href="cart.php"               class="nav-link text-white">🛒 Cart</a>
            <a href="customer_orders.php"    class="nav-link text-white">Orders</a>
            <a href="account.php"            class="nav-link text-white active">Account</a>
            <?php if ($_SESSION['role'] === 'manager'): ?>
            <a href="manager_dashboard.php"  class="btn btn-warning btn-sm">My Shop</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4" style="max-width:700px;">
    <h2 class="mb-4">My Account</h2>

    <div class="card shadow-sm p-4 mb-4">
        <div class="d-flex align-items-center gap-3 mb-3">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=4f46e5&color=fff&size=80"
                 class="rounded-circle" width="80" height="80" alt="Avatar">
            <div>
                <h5 class="mb-0"><?php echo htmlspecialchars($user['name']); ?></h5>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                <span class="badge bg-secondary"><?php echo ucfirst($user['role']); ?></span>
            </div>
        </div>
        <hr>
        <p><strong>Address:</strong> <?php echo $user['address'] ? htmlspecialchars($user['address']) : '—'; ?></p>
        <p><strong>Member Since:</strong> <?php echo date('F d, Y', strtotime($user['created_at'])); ?></p>
    </div>

    <!-- Become a Shop Owner -->
    <?php if (!$is_manager): ?>
    <div class="card shadow-sm p-4 border-2 border-primary">
        <h5>🏪 Want to sell on Jeem Mall?</h5>
        <p class="text-muted">Set up your shop and start selling to thousands of customers. Your customer account stays active.</p>
        <a href="become_manager.php" class="btn btn-primary">🚀 Become a Shop Owner</a>
    </div>
    <?php else: ?>
    <div class="card shadow-sm p-4 border-success">
        <h5 class="text-success">✅ You are a Shop Owner</h5>
        <p class="text-muted">You can manage your shop from the Manager Dashboard.</p>
        <a href="manager_dashboard.php" class="btn btn-success">Go to Manager Dashboard</a>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
