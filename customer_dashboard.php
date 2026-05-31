<?php
include "includes/connect.php";
include "includes/auth.php";
require_role(['customer', 'manager', 'admin']);

$shops = mysqli_query($conn, "SELECT * FROM shops ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Jeem Mall</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/customer.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="customer_dashboard.php">🛍 Jeem Mall</a>
        <div class="navbar-nav ms-auto d-flex flex-row gap-3 align-items-center">
            <a href="customer_dashboard.php" class="nav-link text-white active">Home</a>
            <a href="cart.php"               class="nav-link text-white">🛒 Cart</a>
            <a href="customer_orders.php"    class="nav-link text-white">Orders</a>
            <a href="account.php"            class="nav-link text-white">Account</a>
            <?php if ($_SESSION['role'] === 'manager'): ?>
            <a href="manager_dashboard.php"  class="btn btn-warning btn-sm">My Shop</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h2 class="mb-4">Available Shops</h2>
    <div class="row g-4">
        <?php while ($shop = mysqli_fetch_assoc($shops)): ?>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($shop['name']); ?></h5>
                    <p class="text-muted small mb-1">
                        📍 <?php echo htmlspecialchars($shop['location']); ?>
                    </p>
                    <p class="text-muted small mb-2">
                        🏷 <?php echo str_replace('_', ' ', ucfirst($shop['type'])); ?>
                    </p>
                    <p class="text-muted small">⭐ Rating: <?php echo number_format($shop['rating'], 1); ?>/5</p>
                </div>
                <div class="card-footer">
                    <a href="shop.php?shop_id=<?php echo (int)$shop['id']; ?>"
                       class="btn btn-primary w-100">View Store</a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
