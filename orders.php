<?php
include "includes/connect.php";
include "includes/session.php";

$active_page = "orders";
$user_id     = (int)$_SESSION["id"];

// Fetch all orders for this user, newest first
$orders_res = mysqli_query($conn,
    "SELECT * FROM orders WHERE user_id = '$user_id' ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Jeem Mall</title>
    <link rel="stylesheet" href="css/customer.css">
</head>
<body>

<?php include "includes/navbar.php"; ?>

<main class="main-content">

    <div class="page-header">
        <h1 class="page-title">My Orders</h1>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($orders_res && mysqli_num_rows($orders_res) > 0):
                      while ($order = mysqli_fetch_assoc($orders_res)):
                          $oid = (int)$order["id"];

                          // Get items for this order
                          $lines_res  = mysqli_query($conn,
                              "SELECT order_line.quantity, products.name
                               FROM order_line
                               JOIN products ON order_line.product_id = products.id
                               WHERE order_line.order_id = '$oid'");

                          $lines = [];
                          if ($lines_res) {
                              while ($ln = mysqli_fetch_assoc($lines_res)) {
                                  $lines[] = htmlspecialchars($ln["name"]) . " ×" . (int)$ln["quantity"];
                              }
                          }
                          $items_text = !empty($lines) ? implode(", ", $lines) : "—";

                          // Badge mapping
                          $badge_map = [
                              "pending"   => "badge-pending",
                              "accepted"  => "badge-accepted",
                              "delivered" => "badge-delivered",
                              "canceled"  => "badge-canceled",
                          ];
                          $badge = $badge_map[$order["status"]] ?? "badge-pending";
                ?>
                <tr>
                    <td>#ORD-<?php echo str_pad($oid, 4, "0", STR_PAD_LEFT); ?></td>
                    <td><?php echo date("M d, Y", strtotime($order["created_at"])); ?></td>
                    <td style="max-width:300px;font-size:0.85rem;"><?php echo $items_text; ?></td>
                    <td><?php echo number_format((float)$order["total_price"], 2); ?> SAR</td>
                    <td><span class="badge <?php echo $badge; ?>">
                        <?php echo ucfirst($order["status"]); ?>
                    </span></td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="5" style="text-align:center;color:var(--text-muted);padding:2rem;">
                        You have no orders yet.
                        <a href="customer_dashboard.php">Start shopping!</a>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<script src="js/customer.js"></script>
</body>
</html>
