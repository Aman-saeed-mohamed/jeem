<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_role('manager');

$stmt = $conn->prepare("SELECT id, name FROM shops WHERE manager_id = ? LIMIT 1");
$user_id = current_user_id();
$stmt->bind_param('i', $user_id);
$stmt->execute();
$my_shop = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$my_shop) {
    $page_title = 'Deliveries';
    $active_nav = 'mgr_deliveries';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="page-content" style="padding:2rem;"><div class="alert alert-info">⚠️ No shop assigned. Contact Admin.</div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$shop_id    = (int)$my_shop['id'];
$page_title = 'Deliveries';
$active_nav = 'mgr_deliveries';

$message      = '';
$message_type = '';

$next_status_map = [
    'Accepted'       => 'Being Prepared',
    'Being Prepared' => 'Shipped',
    'Shipped'        => 'Delivered',
];

$next_label_map = [
    'Accepted'       => '👨‍🍳 Start Preparing',
    'Being Prepared' => '📦 Mark as Shipped',
    'Shipped'        => '✅ Mark as Delivered',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action   = $_POST['action']   ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);

    if ($action === 'advance_status' && $order_id > 0) {

        
        $stmt = $conn->prepare(
            "SELECT status FROM orders WHERE id = ? AND shop_id = ? LIMIT 1"
        );
        $stmt->bind_param('ii', $order_id, $shop_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $message      = 'Order not found or access denied.';
            $message_type = 'error';
        } else {
            $current_status = $row['status'];

            

            if (!array_key_exists($current_status, $next_status_map)) {
                $message      = "Order #$order_id is already at its final status: '$current_status'.";
                $message_type = 'error';
            } else {
                $new_status = $next_status_map[$current_status];

                

                $stmt = $conn->prepare(
                    "UPDATE orders
                     SET    status = ?
                     WHERE  id = ? AND shop_id = ? AND status = ?"
                );
                $stmt->bind_param('siis', $new_status, $order_id, $shop_id, $current_status);
                $stmt->execute();
                $affected = $conn->affected_rows;
                $stmt->close();

                if ($affected > 0) {
                    $message      = "Order #$order_id advanced to: $new_status.";
                    $message_type = 'success';
                } else {
                    $message      = "Could not update order #$order_id (status may have changed).";
                    $message_type = 'error';
                }
            }
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
            u.name        AS customer_name,
            u.email       AS customer_email,
            ua.address    AS delivery_address,
            ol.id         AS line_id,
            ol.product_name,
            ol.unit_price,
            ol.quantity   AS line_qty
    FROM    orders       o
    JOIN    users        u  ON u.id  = o.customer_id
    LEFT JOIN user_addresses ua ON ua.user_id = u.id AND ua.is_default = 1
    JOIN    order_line   ol ON ol.order_id = o.id
    WHERE   o.shop_id = ?
      AND   o.status  IN ('Accepted', 'Being Prepared', 'Shipped')
    ORDER BY o.created_at ASC, ol.id ASC
");
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$orders = [];
foreach ($raw as $row) {
    $oid = $row['order_id'];
    if (!isset($orders[$oid])) {
        $orders[$oid] = [
            'id'               => $oid,
            'status'           => $row['status'],
            'subtotal'         => $row['subtotal'],
            'tax'              => $row['tax'],
            'total'            => $row['total'],
            'created_at'       => $row['created_at'],
            'customer_name'    => $row['customer_name'],
            'customer_email'   => $row['customer_email'],
            'delivery_address' => $row['delivery_address'],
            'items'            => [],
        ];
    }
    $orders[$oid]['items'][] = [
        'product_name' => $row['product_name'],
        'unit_price'   => $row['unit_price'],
        'quantity'     => $row['line_qty'],
    ];
}

include __DIR__ . '/../includes/header.php';
?>

<div class="sidebar-layout">
    <?php include __DIR__ . '/../includes/manager_sidebar.php'; ?>

    <div class="page-content">

        <div class="page-header">
            <h1>🚚 Delivery Pipeline</h1>
            <p>
                <?= count($orders) ?> active order<?= count($orders) !== 1 ? 's' : '' ?>
                in the pipeline for <strong class="text-gold"><?= e($my_shop['name']) ?></strong>.
                Advance each order through the delivery stages.
            </p>
        </div>

        
        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;font-size:.83rem;">
            <span class="badge badge-accepted">Accepted</span>
            <span style="color:var(--text-muted);">→</span>
            <span class="badge badge-prepared">Being Prepared</span>
            <span style="color:var(--text-muted);">→</span>
            <span class="badge badge-shipped">Shipped</span>
            <span style="color:var(--text-muted);">→</span>
            <span class="badge badge-delivered">Delivered</span>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'error' ? 'error' : 'success' ?>">
            <?= e($message) ?>
        </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
        <div class="card">
            <div class="empty-state">
                <div class="empty-icon">🚚</div>
                <p>No orders in the delivery pipeline. Accept orders first from the Pending Orders page.</p>
            </div>
        </div>

        <?php else: ?>

        
        <?php foreach ($orders as $order):
            $status      = $order['status'];
            $badge_class = match($status) {
                'Accepted'       => 'badge-accepted',
                'Being Prepared' => 'badge-prepared',
                'Shipped'        => 'badge-shipped',
                default          => 'badge-pending',
            };
            $has_next    = array_key_exists($status, $next_status_map);
            $next_label  = $next_label_map[$status] ?? '';
        ?>
        <div class="card" style="margin-bottom:1.2rem;border-left:3px solid
            <?= match($status) {
                'Accepted'       => 'var(--status-accepted)',
                'Being Prepared' => 'var(--status-prepared)',
                'Shipped'        => 'var(--status-shipped)',
                default          => 'var(--border-subtle)'
            } ?>;">

            
            <div class="d-flex justify-between align-center mb-2" style="flex-wrap:wrap;gap:.5rem;">
                <div>
                    <span style="font-size:1.1rem;font-weight:700;">Order #<?= $order['id'] ?></span>
                    <span class="badge <?= $badge_class ?>" style="margin-left:.5rem;"><?= e($status) ?></span>
                </div>
                <div class="text-muted" style="font-size:.83rem;">
                    🕐 <?= date('M j, Y — g:i A', strtotime($order['created_at'])) ?>
                </div>
            </div>

            
            <div style="background:var(--bg-elevated);border-radius:var(--radius-sm);padding:.7rem 1rem;margin-bottom:1rem;font-size:.87rem;">
                <div><strong>👤</strong> <?= e($order['customer_name']) ?>
                    <span class="text-muted">(<?= e($order['customer_email']) ?>)</span>
                </div>
                <?php if ($order['delivery_address']): ?>
                <div style="margin-top:.25rem;"><strong>📍</strong> <?= e($order['delivery_address']) ?></div>
                <?php endif; ?>
            </div>

            
            <div class="table-wrapper" style="margin-bottom:1rem;">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                    <tr>
                        <td><?= e($item['product_name']) ?></td>
                        <td>× <?= $item['quantity'] ?></td>
                        <td><?= number_format((float)$item['unit_price'] * $item['quantity'], 2) ?> SAR</td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            
            <div class="d-flex justify-between align-center" style="flex-wrap:wrap;gap:.75rem;">
                <div style="font-size:1rem;font-weight:700;color:var(--gold);">
                    Total: <?= number_format((float)$order['total'], 2) ?> SAR
                    <span class="text-muted" style="font-size:.8rem;font-weight:400;">
                        (incl. <?= number_format((float)$order['tax'], 2) ?> SAR tax)
                    </span>
                </div>

                <?php if ($has_next): ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action"   value="advance_status">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <button type="submit" class="btn btn-primary">
                        <?= $next_label ?> →
                    </button>
                </form>
                <?php else: ?>
                    <span class="badge badge-delivered" style="font-size:.85rem;padding:.4rem .9rem;">
                        ✅ Delivered
                    </span>
                <?php endif; ?>
            </div>

        </div>
        <?php endforeach; ?>
        

        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
