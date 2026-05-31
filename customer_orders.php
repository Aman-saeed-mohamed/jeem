<?php
include "includes/connect.php";
include "includes/auth.php";
require_role(['customer', 'manager', 'admin']);

$customer_id = (int)$_SESSION['id'];

// Cancel order (only if pending or accepted)
if (isset($_POST['cancel'])) {
    $order_id = (int)$_POST['order_id'];
    $check    = mysqli_query($conn,
        "SELECT id, status FROM orders WHERE id='$order_id' AND customer_id='$customer_id'");

    if (mysqli_num_rows($check) === 1) {
        $order = mysqli_fetch_assoc($check);
        if (in_array($order['status'], ['pending', 'accepted'])) {
            mysqli_begin_transaction($conn);
            $ok = true;

            // Restore stock
            $lines = mysqli_query($conn, "SELECT * FROM order_line WHERE order_id='$order_id'");
            while ($line = mysqli_fetch_assoc($lines)) {
                if ($line['product_id']) {
                    $ok = $ok && mysqli_query($conn,
                        "UPDATE products SET quantity = quantity + {$line['amount']}
                         WHERE id = '{$line['product_id']}'");
                }
            }
            $ok = $ok && mysqli_query($conn,
                "UPDATE orders SET status='canceled' WHERE id='$order_id'");

            if ($ok) mysqli_commit($conn);
            else     mysqli_rollback($conn);
        }
    }
    header("Location: customer_orders.php");
    exit();
}

$ordered = isset($_GET['ordered']);
$orders  = mysqli_query($conn,
    "SELECT orders.*, shops.name AS shop_name
     FROM orders
     LEFT JOIN shops ON orders.shop_id = shops.id
     WHERE orders.customer_id = '$customer_id'
     ORDER BY orders.order_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Jeem Mall</title>
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
            <a href="customer_orders.php"    class="nav-link text-white active">Orders</a>
            <a href="account.php"            class="nav-link text-white">Account</a>
            <?php if ($_SESSION['role'] === 'manager'): ?>
            <a href="manager_dashboard.php"  class="btn btn-warning btn-sm">My Shop</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h2 class="mb-4">My Orders</h2>

    <?php if ($ordered): ?>
    <div class="alert alert-success">✅ Your order was placed successfully!</div>
    <?php endif; ?>

    <?php $badge = ['pending'=>'warning','accepted'=>'primary','delivered'=>'success','canceled'=>'danger']; ?>

    <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>Order ID</th><th>Date</th><th>Shop</th>
                <th>Items</th><th>Total</th><th>Tax</th><th>Status</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($orders) > 0):
              while ($order = mysqli_fetch_assoc($orders)):
                  $oid = (int)$order['id'];
                  $lines_res = mysqli_query($conn,
                      "SELECT product_name, amount FROM order_line WHERE order_id='$oid'");
                  $lines = [];
                  while ($l = mysqli_fetch_assoc($lines_res)) {
                      $lines[] = htmlspecialchars($l['product_name']) . " ×" . $l['amount'];
                  }
                  $can_cancel = in_array($order['status'], ['pending', 'accepted']);
        ?>
        <tr>
            <td>#<?php echo str_pad($oid, 4, '0', STR_PAD_LEFT); ?></td>
            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
            <td><?php echo $order['shop_name'] ? htmlspecialchars($order['shop_name']) : '—'; ?></td>
            <td><small><?php echo implode('<br>', $lines); ?></small></td>
            <td><?php echo number_format($order['total'], 2); ?> SAR</td>
            <td><?php echo number_format($order['tax'],   2); ?> SAR</td>
            <td><span class="badge bg-<?php echo $badge[$order['status']]; ?>">
                <?php echo ucfirst($order['status']); ?></span></td>
            <td>
                <?php if ($can_cancel): ?>
                <form method="post" onsubmit="return confirm('Cancel this order?');">
                    <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                    <button type="submit" name="cancel" class="btn btn-danger btn-sm">Cancel</button>
                </form>
                <?php else: ?>
                <button class="btn btn-secondary btn-sm" disabled>Cancel</button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No orders yet. <a href="customer_dashboard.php">Start shopping!</a></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
