<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['customer', 'manager']);

$user_id    = current_user_id();
$page_title = 'My Orders';
$active_nav = 'orders';

$message      = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action   = $_POST['action']   ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);

    if ($action === 'cancel_order' && $order_id > 0) {

        $conn->begin_transaction();

        try {
            
            $stmt = $conn->prepare("
                SELECT id, status FROM orders
                WHERE  id = ? AND customer_id = ?
                  AND  status IN ('Pending', 'Accepted')
                LIMIT  1
                FOR UPDATE
            ");
            $stmt->bind_param('ii', $order_id, $user_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$order) {
                throw new Exception(
                    'This order cannot be canceled. It may have already been prepared, ' .
                    'shipped, or delivered.'
                );
            }

            
            $stmt = $conn->prepare(
                "SELECT product_id, quantity FROM order_line WHERE order_id = ?"
            );
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            
            foreach ($lines as $line) {
                if ($line['product_id'] === null) {
                    continue;
                }

                $qty = (int)$line['quantity'];
                $pid = (int)$line['product_id'];

                $stmt = $conn->prepare(
                    "UPDATE products SET quantity = quantity + ? WHERE id = ?"
                );
                $stmt->bind_param('ii', $qty, $pid);
                $stmt->execute();
                $stmt->close();
            }

            
            $stmt = $conn->prepare(
                "UPDATE orders SET status = 'Canceled'
                 WHERE  id = ? AND customer_id = ?"
            );
            $stmt->bind_param('ii', $order_id, $user_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $message      = "Order #$order_id has been canceled. Your inventory has been restored.";
            $message_type = 'success';

        } catch (Exception $e) {
            $conn->rollback();
            $message      = 'Cancellation failed: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

$stmt = $conn->prepare("
    SELECT  o.id          AS order_id,
            o.status,
            o.subtotal,
            o.tax,
            o.total,
            o.created_at,
            s.name        AS shop_name,
            ol.id         AS line_id,
            ol.product_name,
            ol.unit_price,
            ol.quantity   AS line_qty
    FROM    orders      o
    LEFT JOIN shops     s  ON s.id     = o.shop_id
    JOIN    order_line  ol ON ol.order_id = o.id
    WHERE   o.customer_id = ?
    ORDER BY o.created_at DESC, ol.id ASC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$orders = [];
foreach ($raw as $row) {
    $oid = $row['order_id'];
    if (!isset($orders[$oid])) {
        $orders[$oid] = [
            'id'        => $oid,
            'status'    => $row['status'],
            'subtotal'  => $row['subtotal'],
            'tax'       => $row['tax'],
            'total'     => $row['total'],
            'created_at'=> $row['created_at'],
            'shop_name' => $row['shop_name'],
            'items'     => [],
        ];
    }
    $orders[$oid]['items'][] = [
        'product_name' => $row['product_name'],
        'unit_price'   => $row['unit_price'],
        'quantity'     => $row['line_qty'],
    ];
}

$cancelable_statuses = ['Pending', 'Accepted'];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-content" style="max-width:900px;margin:0 auto;">

    <div class="page-header">
        <h1>📦 My Orders</h1>
        <p>Track all your orders. You can cancel orders that are still Pending or Accepted.</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $message_type === 'error' ? 'error' : 'success' ?>">
        <?= e($message) ?>
    </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon">📭</div>
            <p>You haven't placed any orders yet.</p>
            <a href="<?= BASE_URL ?>/customer/customer_dashboard.php" class="btn btn-primary" style="margin-top:.5rem;">
                Start Shopping →
            </a>
        </div>
    </div>

    <?php else: ?>

    <?php foreach ($orders as $order):
        $status = $order['status'];
        $badge  = match($status) {
            'Pending'        => 'badge-pending',
            'Accepted'       => 'badge-accepted',
            'Being Prepared' => 'badge-prepared',
            'Shipped'        => 'badge-shipped',
            'Delivered'      => 'badge-delivered',
            'Canceled'       => 'badge-inactive',
            default          => 'badge-pending',
        };
        $can_cancel = in_array($status, $cancelable_statuses, true);
    ?>
    <div class="card" style="margin-bottom:1.2rem;
         border-left: 3px solid <?= match($status) {
             'Pending'        => 'var(--status-pending)',
             'Accepted'       => 'var(--status-accepted)',
             'Being Prepared' => 'var(--status-prepared)',
             'Shipped'        => 'var(--status-shipped)',
             'Delivered'      => 'var(--status-delivered)',
             'Canceled'       => 'var(--border-subtle)',
             default          => 'var(--border-subtle)'
         } ?>;">

        
        <div class="d-flex justify-between align-center mb-2" style="flex-wrap:wrap;gap:.5rem;">
            <div>
                <span style="font-weight:700;font-size:1rem;">Order #<?= $order['id'] ?></span>
                <span class="badge <?= $badge ?>" style="margin-left:.5rem;"><?= e($status) ?></span>
            </div>
            <div class="text-muted" style="font-size:.82rem;">
                <?= date('M j, Y — g:i A', strtotime($order['created_at'])) ?>
            </div>
        </div>

        
        <div style="font-size:.83rem;color:var(--text-muted);margin-bottom:.75rem;">
            🏪 <?= $order['shop_name'] ? e($order['shop_name']) : '<em>[Shop Removed]</em>' ?>
        </div>

        
        <div class="table-wrapper" style="margin-bottom:.75rem;">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Unit Price</th>
                        <th>Qty</th>
                        <th>Line Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($order['items'] as $item): ?>
                <tr>
                    <td><?= e($item['product_name']) ?></td>
                    <td><?= number_format((float)$item['unit_price'], 2) ?> SAR</td>
                    <td>× <?= $item['quantity'] ?></td>
                    <td><?= number_format((float)$item['unit_price'] * $item['quantity'], 2) ?> SAR</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        
        <div class="d-flex justify-between align-center" style="flex-wrap:wrap;gap:.75rem;">
            <div style="font-size:.88rem;color:var(--text-muted);">
                Subtotal: <?= number_format((float)$order['subtotal'], 2) ?> SAR &nbsp;|&nbsp;
                VAT: <?= number_format((float)$order['tax'], 2) ?> SAR &nbsp;|&nbsp;
                <strong style="color:var(--gold);">
                    Total: <?= number_format((float)$order['total'], 2) ?> SAR
                </strong>
            </div>

            <?php if ($can_cancel): ?>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action"   value="cancel_order">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <button
                    type="submit"
                    class="btn btn-danger btn-sm"
                    data-confirm="Cancel Order #<?= $order['id'] ?>? This cannot be undone and inventory will be restored."
                >
                    ❌ Cancel Order
                </button>
            </form>
            <?php elseif ($status === 'Canceled'): ?>
                <span style="font-size:.8rem;color:var(--text-muted);font-style:italic;">Order canceled</span>
            <?php elseif ($status === 'Delivered'): ?>
                <span style="color:var(--status-delivered);font-size:.82rem;font-weight:600;">✅ Delivered</span>
            <?php else: ?>
                <span style="font-size:.8rem;color:var(--text-muted);font-style:italic;">
                    Cannot cancel — order is <?= e($status) ?>
                </span>
            <?php endif; ?>
        </div>

    </div>
    <?php endforeach; ?>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
