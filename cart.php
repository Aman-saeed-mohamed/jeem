<?php
include "includes/connect.php";
include "includes/auth.php";
require_role(['customer', 'manager', 'admin']);

$customer_id = (int)$_SESSION['id'];
$msg         = "";

// Remove item
if (isset($_POST['remove'])) {
    $cart_id = (int)$_POST['cart_id'];
    mysqli_query($conn, "DELETE FROM cart WHERE id='$cart_id' AND customer_id='$customer_id'");
    header("Location: cart.php");
    exit();
}

// Confirm Order — split by shop_id
if (isset($_POST['place_order'])) {
    $cart_res = mysqli_query($conn,
        "SELECT cart.id AS cart_id, cart.amount,
                products.id AS product_id, products.name, products.price, products.shop_id
         FROM cart
         JOIN products ON cart.product_id = products.id
         WHERE cart.customer_id = '$customer_id'");

    if (mysqli_num_rows($cart_res) === 0) {
        $msg = "Your cart is empty.";
    } else {
        // Group items by shop
        $grouped = [];
        while ($row = mysqli_fetch_assoc($cart_res)) {
            $grouped[$row['shop_id']][] = $row;
        }

        mysqli_begin_transaction($conn);
        $ok = true;

        foreach ($grouped as $shop_id => $items) {
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += $item['price'] * $item['amount'];
            }
            $tax = round($subtotal * 0.08, 2);

            $ok = $ok && mysqli_query($conn,
                "INSERT INTO orders (status, customer_id, shop_id, total, tax)
                 VALUES ('pending', '$customer_id', '$shop_id', '$subtotal', '$tax')");

            $order_id = mysqli_insert_id($conn);

            foreach ($items as $item) {
                $line_total = round($item['price'] * $item['amount'], 2);
                $safe_name  = mysqli_real_escape_string($conn, $item['name']);
                $ok = $ok && mysqli_query($conn,
                    "INSERT INTO order_line (order_id, product_id, product_name, unit_price, amount, total_price)
                     VALUES ('$order_id', '{$item['product_id']}', '$safe_name', '{$item['price']}', '{$item['amount']}', '$line_total')");

                // Reduce stock
                $ok = $ok && mysqli_query($conn,
                    "UPDATE products SET quantity = quantity - {$item['amount']}
                     WHERE id = '{$item['product_id']}' AND quantity >= {$item['amount']}");
            }
        }

        if ($ok) {
            mysqli_query($conn, "DELETE FROM cart WHERE customer_id='$customer_id'");
            mysqli_commit($conn);
            header("Location: customer_orders.php?ordered=1");
            exit();
        } else {
            mysqli_rollback($conn);
            $msg = "Order failed. Please try again.";
        }
    }
}

// Fetch cart
$cart_res = mysqli_query($conn,
    "SELECT cart.id AS cart_id, cart.amount,
            products.id AS product_id, products.name, products.price, products.image,
            shops.name AS shop_name
     FROM cart
     JOIN products ON cart.product_id = products.id
     JOIN shops    ON products.shop_id = shops.id
     WHERE cart.customer_id = '$customer_id'
     ORDER BY shops.name, products.name");

$items    = [];
$subtotal = 0;
while ($row = mysqli_fetch_assoc($cart_res)) {
    $items[]  = $row;
    $subtotal += $row['price'] * $row['amount'];
}
$tax         = round($subtotal * 0.08, 2);
$grand_total = round($subtotal + $tax, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart - Jeem Mall</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/customer.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="customer_dashboard.php">🛍 Jeem Mall</a>
        <div class="navbar-nav ms-auto d-flex flex-row gap-3 align-items-center">
            <a href="customer_dashboard.php" class="nav-link text-white">Home</a>
            <a href="cart.php"               class="nav-link text-white active">🛒 Cart</a>
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
    <h2 class="mb-4">🛒 Shopping Cart</h2>

    <?php if ($msg): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <?php if (!empty($items)): ?>
    <div class="row">
        <div class="col-md-8">
            <?php foreach ($items as $item):
                  $line_total = $item['price'] * $item['amount']; ?>
            <div class="card mb-3 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                        <small class="text-muted">Shop: <?php echo htmlspecialchars($item['shop_name']); ?></small><br>
                        <small>Qty: <?php echo (int)$item['amount']; ?> × <?php echo number_format($item['price'],2); ?> SAR</small>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-success"><?php echo number_format($line_total,2); ?> SAR</div>
                        <form action="cart.php" method="post" class="mt-2"
                              onsubmit="return confirm('Remove this item?');">
                            <input type="hidden" name="cart_id" value="<?php echo (int)$item['cart_id']; ?>">
                            <button type="submit" name="remove" class="btn btn-danger btn-sm">Remove</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm p-3">
                <h5>Order Summary</h5>
                <hr>
                <div class="d-flex justify-content-between"><span>Subtotal</span><span><?php echo number_format($subtotal,2); ?> SAR</span></div>
                <div class="d-flex justify-content-between"><span>Tax (8%)</span><span><?php echo number_format($tax,2); ?> SAR</span></div>
                <hr>
                <div class="d-flex justify-content-between fw-bold fs-5">
                    <span>Total</span><span><?php echo number_format($grand_total,2); ?> SAR</span>
                </div>
                <form action="cart.php" method="post" class="mt-3"
                      onsubmit="return confirm('Confirm your order?');">
                    <button type="submit" name="place_order" class="btn btn-success w-100">
                        ✅ Confirm Order
                    </button>
                </form>
                <small class="text-muted mt-2 d-block text-center">
                    Items from different shops will create separate orders.
                </small>
            </div>
        </div>
    </div>

    <?php else: ?>
    <div class="text-center py-5 text-muted">
        <p class="fs-5">Your cart is empty.</p>
        <a href="customer_dashboard.php" class="btn btn-primary">Start Shopping</a>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
