<?php
include "includes/connect.php";
include "includes/auth.php";
require_role(['customer', 'manager', 'admin']);

$shop_id = isset($_GET['shop_id']) ? (int)$_GET['shop_id'] : 0;
if ($shop_id <= 0) { header("Location: customer_dashboard.php"); exit(); }

// Verify shop exists
$shop_res = mysqli_query($conn, "SELECT * FROM shops WHERE id = '$shop_id'");
if (mysqli_num_rows($shop_res) === 0) { header("Location: customer_dashboard.php"); exit(); }
$shop = mysqli_fetch_assoc($shop_res);

// Add to Cart
$cart_msg = "";
if (isset($_POST['add_to_cart'])) {
    $product_id  = (int)$_POST['product_id'];
    $customer_id = (int)$_SESSION['id'];

    // Check item belongs to this shop
    $verify = mysqli_query($conn, "SELECT id FROM products WHERE id='$product_id' AND shop_id='$shop_id'");
    if (mysqli_num_rows($verify) === 1) {
        $existing = mysqli_query($conn,
            "SELECT id FROM cart WHERE customer_id='$customer_id' AND product_id='$product_id'");
        if (mysqli_num_rows($existing) > 0) {
            mysqli_query($conn,
                "UPDATE cart SET amount = amount + 1 WHERE customer_id='$customer_id' AND product_id='$product_id'");
        } else {
            mysqli_query($conn,
                "INSERT INTO cart (customer_id, product_id, amount) VALUES ('$customer_id','$product_id',1)");
        }
        $cart_msg = "success";
    }
}

$products = mysqli_query($conn, "SELECT * FROM products WHERE shop_id='$shop_id' ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($shop['name']); ?> - Jeem Mall</title>
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
            <a href="account.php"            class="nav-link text-white">Account</a>
            <?php if ($_SESSION['role'] === 'manager'): ?>
            <a href="manager_dashboard.php"  class="btn btn-warning btn-sm">My Shop</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <a href="customer_dashboard.php" class="text-muted text-decoration-none">&larr; Back to Shops</a>
    <h2 class="my-3"><?php echo htmlspecialchars($shop['name']); ?></h2>

    <?php if ($cart_msg === 'success'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        ✅ Product added to cart! <a href="cart.php" class="alert-link">View Cart</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if (mysqli_num_rows($products) > 0):
              while ($p = mysqli_fetch_assoc($products)): ?>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <?php if (!empty($p['image'])): ?>
                <img src="<?php echo htmlspecialchars($p['image']); ?>"
                     class="card-img-top" style="height:200px;object-fit:cover;" alt="">
                <?php endif; ?>
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($p['name']); ?></h5>
                    <p class="card-text text-muted small"><?php echo htmlspecialchars($p['description']); ?></p>
                    <p class="fw-bold text-success"><?php echo number_format($p['price'], 2); ?> SAR</p>
                    <p class="text-muted small">Stock: <?php echo (int)$p['quantity']; ?></p>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="product.php?product_id=<?php echo (int)$p['id']; ?>"
                       class="btn btn-outline-primary w-50">View Details</a>
                    <?php if ((int)$p['quantity'] > 0): ?>
                    <form action="shop.php?shop_id=<?php echo $shop_id; ?>" method="post" class="w-50">
                        <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                        <button type="submit" name="add_to_cart" class="btn btn-primary w-100">
                            🛒 Cart
                        </button>
                    </form>
                    <?php else: ?>
                    <button class="btn btn-secondary w-50" disabled>Out of Stock</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
        <p class="text-muted">No products in this shop yet.</p>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
