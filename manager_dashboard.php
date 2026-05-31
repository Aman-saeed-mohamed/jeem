<?php
include "includes/connect.php";
include "includes/auth.php";
require_role(['manager', 'admin']);

$manager_id = (int)$_SESSION['id'];
$shop_res   = mysqli_query($conn, "SELECT * FROM shops WHERE manager_id='$manager_id'");
if (mysqli_num_rows($shop_res) === 0) { header("Location: customer_dashboard.php"); exit(); }
$shop    = mysqli_fetch_assoc($shop_res);
$shop_id = (int)$shop['id'];

$total_products = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM products WHERE shop_id='$shop_id'"))['c'];
$total_orders   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE shop_id='$shop_id'"))['c'];
$total_sales    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total),0) AS s FROM orders WHERE shop_id='$shop_id' AND status != 'canceled'"))['s'];
$pending_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE shop_id='$shop_id' AND status='pending'"))['c'];

$welcome = isset($_GET['welcome']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - Jeem Mall</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="manager_dashboard.php">🏪 <?php echo htmlspecialchars($shop['name']); ?></a>
        <div class="navbar-nav ms-auto d-flex flex-row gap-3 align-items-center">
            <a href="manager_dashboard.php"  class="nav-link text-white active">Home</a>
            <a href="manager_products.php"   class="nav-link text-white">Products</a>
            <a href="manager_orders.php"     class="nav-link text-white">Orders</a>
            <a href="manager_deliveries.php" class="nav-link text-white">Deliveries</a>
            <a href="customer_dashboard.php" class="btn btn-outline-warning btn-sm">🛍 Shopping Mode</a>
            <a href="logout.php"             class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <?php if ($welcome): ?>
    <div class="alert alert-success alert-dismissible fade show">
        🎉 Welcome! Your shop <strong><?php echo htmlspecialchars($shop['name']); ?></strong> is live!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <h2 class="mb-1">Dashboard</h2>
    <p class="text-muted mb-4">📍 <?php echo htmlspecialchars($shop['location']); ?> &nbsp;|&nbsp; 🏷 <?php echo str_replace('_',' ',ucfirst($shop['type'])); ?></p>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card text-center shadow-sm border-primary">
                <div class="card-body">
                    <h1 class="text-primary"><?php echo $total_products; ?></h1>
                    <p class="mb-0 text-muted">Products</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center shadow-sm border-warning">
                <div class="card-body">
                    <h1 class="text-warning"><?php echo $pending_orders; ?></h1>
                    <p class="mb-0 text-muted">Pending Orders</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center shadow-sm border-info">
                <div class="card-body">
                    <h1 class="text-info"><?php echo $total_orders; ?></h1>
                    <p class="mb-0 text-muted">Total Orders</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center shadow-sm border-success">
                <div class="card-body">
                    <h1 class="text-success"><?php echo number_format($total_sales, 0); ?></h1>
                    <p class="mb-0 text-muted">Total Sales (SAR)</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($pending_orders > 0): ?>
    <div class="alert alert-warning">
        ⚠️ You have <strong><?php echo $pending_orders; ?></strong> pending order(s).
        <a href="manager_orders.php" class="alert-link">View Orders</a>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
