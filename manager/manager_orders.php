<?php
/**
 * =============================================================
 * JEEM MALL — Manager: Pending Orders (THE INVENTORY QA TRAP)
 * =============================================================
 * Shows ONLY orders with status = 'Pending' for THIS manager's shop.
 *
 * Actions:
 *   Accept → UPDATE orders SET status = 'Accepted'
 *   Reject → ATOMIC TRANSACTION:
 *              1. Loop order_line rows for this order
 *              2. UPDATE products SET quantity = quantity + ol.quantity
 *                 (skips deleted products where product_id IS NULL)
 *              3. UPDATE orders SET status = 'Canceled'
 *
 * SCHEMA DEPENDENCY NOTE:
 * ──────────────────────────────────────────────────────────────
 * The Reject action sets status to 'Canceled'. Your orders table
 * ENUM must include this value. If you created the table without
 * 'Canceled', run this once in phpMyAdmin or MySQL CLI:
 *
 *   ALTER TABLE orders
 *     MODIFY COLUMN status
 *     ENUM('Pending','Accepted','Being Prepared','Shipped','Delivered','Canceled')
 *     NOT NULL DEFAULT 'Pending';
 * ──────────────────────────────────────────────────────────────
 *
 * SECURITY:
 *   - All queries scope to $shop_id (manager sees only their shop)
 *   - Status checked in UPDATE (prevents re-processing non-pending orders)
 *   - CSRF protection on all POST forms
 *   - Atomic transaction on Reject ensures inventory is NEVER partially restored
 * =============================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_role('manager');

// ── Get manager's shop ────────────────────────────────────────
$stmt = $conn->prepare("SELECT id, name FROM shops WHERE manager_id = ? LIMIT 1");
$user_id = current_user_id();
$stmt->bind_param('i', $user_id);
$stmt->execute();
$my_shop = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$my_shop) {
    $page_title = 'Orders';
    $active_nav = 'mgr_orders';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="page-content" style="padding:2rem;"><div class="alert alert-info">⚠️ No shop assigned. Contact Admin.</div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$shop_id    = (int)$my_shop['id'];
$page_title = 'Pending Orders';
$active_nav = 'mgr_orders';

$message      = '';
$message_type = '';

// ── POST Handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action   = $_POST['action']   ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);

    if ($order_id < 1) {
        $message      = 'Invalid order ID.';
        $message_type = 'error';

    // ─────────────────────────────────────────────────────────
    // ACTION: Accept — advance status to 'Accepted'
    // ─────────────────────────────────────────────────────────
    } elseif ($action === 'accept') {

        /*
         * The WHERE clause checks both shop_id AND status = 'Pending'.
         * This means even if a malicious POST replays an already-accepted
         * order ID, the UPDATE affects 0 rows and does nothing harmful.
         */
        $stmt = $conn->prepare("
            UPDATE orders
            SET    status = 'Accepted'
            WHERE  id = ? AND shop_id = ? AND status = 'Pending'
        ");
        $stmt->bind_param('ii', $order_id, $shop_id);
        $stmt->execute();
        $affected = $conn->affected_rows;
        $stmt->close();

        $message      = $affected > 0 ? 'Order #' . $order_id . ' accepted.' : 'Could not accept order (already processed?).';
        $message_type = $affected > 0 ? 'success' : 'error';

    // ─────────────────────────────────────────────────────────
    // ACTION: Reject — ATOMIC inventory restore + 'Canceled'
    // ─────────────────────────────────────────────────────────
    } elseif ($action === 'reject') {

        /*
         * CRITICAL LOGIC — must be atomic:
         *
         * If any step fails (e.g., a product was deleted mid-process),
         * we roll back entirely. This ensures inventory is either
         * FULLY restored or NOT AT ALL — never a partial state.
         */
        $conn->begin_transaction();

        try {
            // ── Step 1: Verify the order is Pending & belongs to this shop ──
            $stmt = $conn->prepare("
                SELECT id FROM orders
                WHERE  id = ? AND shop_id = ? AND status = 'Pending'
                LIMIT  1
                FOR UPDATE
            ");
            $stmt->bind_param('ii', $order_id, $shop_id);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 0) {
                throw new Exception('Order not found or is no longer in Pending status.');
            }
            $stmt->close();

            // ── Step 2: Fetch all line items for this order ──────────────
            $stmt = $conn->prepare(
                "SELECT product_id, quantity FROM order_line WHERE order_id = ?"
            );
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // ── Step 3: Restore inventory for each line item ─────────────
            foreach ($lines as $line) {
                /*
                 * product_id can be NULL if the product was deleted after
                 * the order was placed (order_line uses SET NULL on product delete).
                 * In that case, we simply skip it — there is no product to restore.
                 */
                if ($line['product_id'] === null) {
                    continue;
                }

                $qty = (int)$line['quantity'];

                $stmt = $conn->prepare(
                    "UPDATE products SET quantity = quantity + ? WHERE id = ?"
                );
                $stmt->bind_param('ii', $qty, $line['product_id']);
                $stmt->execute();
                $stmt->close();
            }

            // ── Step 4: Mark the order as Canceled ───────────────────────
            $stmt = $conn->prepare(
                "UPDATE orders SET status = 'Canceled' WHERE id = ? AND shop_id = ?"
            );
            $stmt->bind_param('ii', $order_id, $shop_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $message      = 'Order #' . $order_id . ' rejected. Inventory has been restored.';
            $message_type = 'success';

        } catch (Exception $e) {
            $conn->rollback();
            $message      = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ── Fetch all PENDING orders for THIS shop ────────────────────
// Using a single JOIN query + PHP grouping for efficiency.
$stmt = $conn->prepare("
    SELECT  o.id          AS order_id,
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
    JOIN    users        u  ON  u.id  = o.customer_id
    LEFT JOIN user_addresses ua ON ua.user_id = u.id AND ua.is_default = 1
    JOIN    order_line   ol ON ol.order_id = o.id
    WHERE   o.shop_id = ? AND o.status = 'Pending'
    ORDER BY o.created_at ASC, ol.id ASC
");
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Reconstruct into nested array: order → items[]
$orders = [];
foreach ($raw as $row) {
    $oid = $row['order_id'];
    if (!isset($orders[$oid])) {
        $orders[$oid] = [
            'id'               => $oid,
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
            <h1>🛒 Pending Orders</h1>
            <p>
                <?= count($orders) ?> pending order<?= count($orders) !== 1 ? 's' : '' ?>
                for <strong class="text-gold"><?= e($my_shop['name']) ?></strong>.
                Accept to begin preparation or reject to cancel and restore inventory.
            </p>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'error' ? 'error' : 'success' ?>">
            <?= e($message) ?>
        </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
        <div class="card">
            <div class="empty-state">
                <div class="empty-icon">✅</div>
                <p>No pending orders right now. You're all caught up!</p>
            </div>
        </div>

        <?php else: ?>

        <!-- ── Order Cards ─────────────────────────────────── -->
        <?php foreach ($orders as $order): ?>
        <div class="card" style="margin-bottom:1.2rem;">

            <!-- Card Header: Order ID + Customer + Date -->
            <div class="d-flex justify-between align-center mb-2" style="flex-wrap:wrap;gap:.5rem;">
                <div>
                    <span style="font-size:1.1rem;font-weight:700;">Order #<?= $order['id'] ?></span>
                    <span class="badge badge-pending" style="margin-left:.5rem;">Pending</span>
                </div>
                <div class="text-muted" style="font-size:.83rem;">
                    🕐 <?= date('M j, Y — g:i A', strtotime($order['created_at'])) ?>
                </div>
            </div>

            <!-- Customer Info -->
            <div style="background:var(--bg-elevated);border-radius:var(--radius-sm);padding:.75rem 1rem;margin-bottom:1rem;font-size:.88rem;">
                <div><strong>👤 Customer:</strong> <?= e($order['customer_name']) ?>
                    <span class="text-muted">(<?= e($order['customer_email']) ?>)</span></div>
                <?php if ($order['delivery_address']): ?>
                <div style="margin-top:.3rem;"><strong>📍 Deliver to:</strong> <?= e($order['delivery_address']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Order Line Items -->
            <div class="table-wrapper" style="margin-bottom:1rem;">
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

            <!-- Order Totals -->
            <div style="text-align:right;padding:.5rem 0;border-top:1px solid var(--border-subtle);margin-bottom:1rem;font-size:.9rem;">
                <div>Subtotal: <?= number_format((float)$order['subtotal'], 2) ?> SAR</div>
                <div>Tax (8%): <?= number_format((float)$order['tax'], 2) ?> SAR</div>
                <div style="font-size:1.1rem;font-weight:700;color:var(--gold);margin-top:.3rem;">
                    Total: <?= number_format((float)$order['total'], 2) ?> SAR
                </div>
            </div>

            <!-- Accept / Reject Buttons -->
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;">

                <!-- ACCEPT -->
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action"   value="accept">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <button type="submit" class="btn btn-success">
                        ✅ Accept Order
                    </button>
                </form>

                <!-- REJECT (with confirmation warning) -->
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action"   value="reject">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <button
                        type="submit"
                        class="btn btn-danger"
                        data-confirm="Reject Order #<?= $order['id'] ?>? This will cancel the order and restore inventory for all items."
                    >
                        ❌ Reject & Restore Inventory
                    </button>
                </form>

            </div>

        </div>
        <?php endforeach; ?>
        <!-- ── End Order Cards ─────────────────────────────── -->

        <?php endif; ?>

    </div><!-- /page-content -->
</div><!-- /sidebar-layout -->

<?php include __DIR__ . '/../includes/footer.php'; ?>
