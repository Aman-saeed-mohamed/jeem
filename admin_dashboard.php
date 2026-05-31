<?php
include "includes/connect.php";
include "includes/auth.php";
require_role(['admin']);

$total_users  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users"))['c'];
$total_shops  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM shops"))['c'];
$total_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status != 'canceled'"))['c'];
$total_sales  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total),0) AS s FROM orders WHERE status != 'canceled'"))['s'];
$pending      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status='pending'"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Jeem Mall</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="admin_dashboard.php">⚙️ Admin Panel</a>
        <div class="navbar-nav ms-auto d-flex flex-row gap-3 align-items-center">
            <a href="admin_dashboard.php" class="nav-link text-white active">Home</a>
            <a href="admin_users.php"     class="nav-link text-white">Manage Users</a>
            <a href="admin_shops.php"     class="nav-link text-white">Manage Shops</a>
            <span class="text-muted small">👤 <?php echo htmlspecialchars($_SESSION['name']); ?></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h2 class="mb-4">Analytics Overview</h2>

    <div class="row g-4">
        <div class="col-md-3">
            <div class="card text-center shadow-sm border-primary">
                <div class="card-body py-4">
                    <h1 class="text-primary display-5 fw-bold"><?php echo $total_users; ?></h1>
                    <p class="text-muted mb-0">Total Users</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center shadow-sm border-success">
                <div class="card-body py-4">
                    <h1 class="text-success display-5 fw-bold"><?php echo $total_shops; ?></h1>
                    <p class="text-muted mb-0">Active Shops</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center shadow-sm border-info">
                <div class="card-body py-4">
                    <h1 class="text-info display-5 fw-bold"><?php echo $total_orders; ?></h1>
                    <p class="text-muted mb-0">Total Orders</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center shadow-sm border-warning">
                <div class="card-body py-4">
                    <h1 class="text-warning display-5 fw-bold"><?php echo number_format($total_sales,0); ?></h1>
                    <p class="text-muted mb-0">Total Sales (SAR)</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($pending > 0): ?>
    <div class="alert alert-warning mt-4">
        ⚠️ There are <strong><?php echo $pending; ?></strong> pending orders across all shops.
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
