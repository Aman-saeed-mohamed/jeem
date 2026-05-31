<?php
include "includes/connect.php";
include "includes/auth.php";
require_role(['manager', 'admin']);

$manager_id = (int)$_SESSION['id'];
$shop_res   = mysqli_query($conn, "SELECT * FROM shops WHERE manager_id='$manager_id'");
if (mysqli_num_rows($shop_res) === 0) { header("Location: customer_dashboard.php"); exit(); }
$shop    = mysqli_fetch_assoc($shop_res);
$shop_id = (int)$shop['id'];

// Accept or Reject order
if (isset($_POST['action'])) {
    $order_id = (int)$_POST['order_id'];
    $action   = $_POST['action'];

    // Verify this order belongs to this shop
    $v = mysqli_query($conn,
        "SELECT id, status FROM orders WHERE id='$order_id' AND shop_id='$shop_id' AND status='pending'");

    if (mysqli_num_rows($v) === 1) {
        if ($action === 'accept') {
            mysqli_query($conn, "UPDATE orders SET status='accepted' WHERE id='$order_id'");
        } elseif ($action === 'reject') {
            // Restore stock on reject
            mysqli_begin_transaction($conn);
            $ok    = true;
            $lines = mysqli_query($conn, "SELECT * FROM order_line WHERE order_id='$order_id'");
            while ($line = mysqli_fetch_assoc($lines)) {
                if ($line['product_id']) {
                    $ok = $ok && mysqli_query($conn,
                        "UPDATE products SET quantity = quantity + {$line['amount']}
                         WHERE id='{$line['product_id']}'");
                }
            }
            $ok = $ok && mysqli_query($conn,
                "UPDATE orders SET status='canceled' WHERE id='$order_id'");
            if ($ok) mysqli_commit($conn);
            else     mysqli_rollback($conn);
        }
    }
    header("Location: manager_orders.php");
    exit();
}

// Pending orders for THIS shop only
$orders = mysqli_query($conn,
    "SELECT orders.*, users.name AS customer_name
     FROM orders
     LEFT JOIN users ON orders.customer_id = users.id
     WHERE orders.shop_id = '$shop_id' AND orders.status = 'pending'
     ORDER BY orders.order_date ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - <?php echo htmlspecialchars($shop['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="manager_dashboard.php">🏪 <?php echo htmlspecialchars($shop['name']); ?></a>
        <div class="navbar-nav ms-auto d-flex flex-row gap-3 align-items-center">
            <a href="manager_dashboard.php"  class="nav-link text-white">Home</a>
            <a href="manager_products.php"   class="nav-link text-white">Products</a>
            <a href="manager_orders.php"     class="nav-link text-white active">Orders</a>
            <a href="manager_deliveries.php" class="nav-link text-white">Deliveries</a>
            <a href="customer_dashboard.php" class="btn btn-outline-warning btn-sm">🛍 Shopping Mode</a>
            <a href="logout.php"             class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h2 class="mb-4">Pending Orders</h2>

    <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle bg-white">
        <thead class="table-dark">
            <tr>
                <th>Order ID</th><th>Date</th><th>Customer</th>
                <th>Items</th><th>Total</th><th>Accept</th><th>Reject</th>
            </tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($orders) > 0):
              while ($order = mysqli_fetch_assoc($orders)):
                  $oid   = (int)$order['id'];
                  $lines = mysqli_query($conn,
                      "SELECT product_name, amount FROM order_line WHERE order_id='$oid'");
                  $list  = [];
                  while ($l = mysqli_fetch_assoc($lines)) {
                      $list[] = htmlspecialchars($l['product_name']) . " ×" . $l['amount'];
                  }
        ?>
        <tr>
            <td>#<?php echo str_pad($oid,4,'0',STR_PAD_LEFT); ?></td>
            <td><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></td>
            <td><?php echo $order['customer_name'] ? htmlspecialchars($order['customer_name']) : '—'; ?></td>
            <td><small><?php echo implode('<br>', $list); ?></small></td>
            <td><?php echo number_format($order['total'],2); ?> SAR</td>
            <td>
                <form method="post" onsubmit="return confirm('Accept this order?');">
                    <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                    <input type="hidden" name="action"   value="accept">
                    <button type="submit" class="btn btn-success btn-sm">✅ Accept</button>
                </form>
            </td>
            <td>
                <form method="post" onsubmit="return confirm('Reject this order? Stock will be restored.');">
                    <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                    <input type="hidden" name="action"   value="reject">
                    <button type="submit" class="btn btn-danger btn-sm">❌ Reject</button>
                </form>
            </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No pending orders.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
