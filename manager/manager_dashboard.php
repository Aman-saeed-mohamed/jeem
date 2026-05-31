<?php
/**
 * =============================================================
 * JEEM MALL — Manager Dashboard
 * =============================================================
 * Displays 4 shop-scoped metrics:
 *   1. Total products in THEIR shop
 *   2. Pending orders for THEIR shop
 *   3. Total orders for THEIR shop
 *   4. Total Sales SAR (excluding 'Canceled')
 *
 * Shows a Bootstrap alert if any orders are Pending.
 * =============================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_role('manager');

// ── Get this manager's assigned shop ──────────────────────────
// If no shop is assigned yet (edge case: admin set role but no shop),
// we show a friendly error instead of broken metrics.
$stmt = $conn->prepare(
    "SELECT id, name, type, location, status FROM shops WHERE manager_id = ? LIMIT 1"
);
$user_id = current_user_id();
$stmt->bind_param('i', $user_id);
$stmt->execute();
$my_shop = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$my_shop) {
    $page_title = 'Manager Dashboard';
    $active_nav = 'mgr_dash';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="page-content" style="padding:2rem;">
            <div class="alert alert-info">
                ⚠️ <strong>No shop assigned.</strong>
                Please contact the Admin to assign a shop to your account before you can manage orders or products.
            </div>
          </div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$shop_id    = (int)$my_shop['id'];
$page_title = 'Manager Dashboard';
$active_nav = 'mgr_dash';

// ── Metric 1: Total Products in THEIR shop ────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM products WHERE shop_id = ?");
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$total_products = (int)$stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// ── Metric 2: Pending Orders for THEIR shop ───────────────────
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt FROM orders WHERE shop_id = ? AND status = 'Pending'"
);
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$pending_orders = (int)$stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// ── Metric 3: Total Orders for THEIR shop ────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE shop_id = ?");
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$total_orders = (int)$stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// ── Metric 4: Total Sales SAR (excluding Canceled) ───────────
// NOTE: 'Canceled' is referenced here for future-proofing.
// If your orders ENUM does not include 'Canceled', this query
// simply returns the sum of ALL orders, which is still correct.
$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(total), 0.00) AS revenue
     FROM   orders
     WHERE  shop_id = ? AND status != 'Canceled'"
);
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$total_revenue = (float)$stmt->get_result()->fetch_assoc()['revenue'];
$stmt->close();

// ── Recent 8 Orders for this shop ────────────────────────────
$stmt = $conn->prepare("
    SELECT  o.id,
            o.status,
            o.total,
            o.created_at,
            u.name AS customer_name
    FROM    orders o
    JOIN    users  u ON u.id = o.customer_id
    WHERE   o.shop_id = ?
    ORDER BY o.created_at DESC
    LIMIT 8
");
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$recent_orders = $stmt->get_result();
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<div class="sidebar-layout">
    <?php include __DIR__ . '/../includes/manager_sidebar.php'; ?>

    <div class="page-content">

        <!-- Page Header -->
        <div class="page-header">
            <h1>📊 Manager Dashboard</h1>
            <p>Overview for <strong class="text-gold"><?= e($my_shop['name']) ?></strong>
               — <?= e(str_replace('_', ' ', ucfirst($my_shop['type']))) ?>,
               <?= e($my_shop['location']) ?></p>
        </div>

        <!-- ── PENDING ORDERS ALERT ────────────────────────── -->
        <?php if ($pending_orders > 0): ?>
        <div class="alert alert-info" role="alert">
            🔔 You have <strong><?= $pending_orders ?></strong>
            pending order<?= $pending_orders > 1 ? 's' : '' ?> waiting for your action!
            <a href="<?= BASE_URL ?>/manager/manager_orders.php" class="btn btn-primary btn-sm" style="margin-left:1rem;">
                View Orders →
            </a>
        </div>
        <?php endif; ?>

        <!-- ── Metrics Grid ───────────────────────────────── -->
        <div class="metrics-grid">

            <div class="metric-card">
                <div class="metric-icon">📦</div>
                <div class="metric-value"><?= number_format($total_products) ?></div>
                <div class="metric-label">Total Products</div>
            </div>

            <div class="metric-card">
                <div class="metric-icon">🕐</div>
                <div class="metric-value" style="color:<?= $pending_orders > 0 ? 'var(--status-pending)' : 'var(--gold)' ?>;">
                    <?= number_format($pending_orders) ?>
                </div>
                <div class="metric-label">Pending Orders</div>
            </div>

            <div class="metric-card">
                <div class="metric-icon">🛒</div>
                <div class="metric-value"><?= number_format($total_orders) ?></div>
                <div class="metric-label">Total Orders</div>
            </div>

            <div class="metric-card">
                <div class="metric-icon">💰</div>
                <div class="metric-value"><?= number_format($total_revenue, 2) ?></div>
                <div class="metric-label">Sales (SAR)</div>
            </div>

        </div>
        <!-- ── End Metrics ────────────────────────────────── -->

        <!-- ── Recent Orders Table ────────────────────────── -->
        <div class="card">
            <div class="d-flex justify-between align-center mb-2">
                <h3 style="margin:0;">🕐 Recent Orders</h3>
                <div style="display:flex;gap:.5rem;">
                    <a href="<?= BASE_URL ?>/manager/manager_orders.php"    class="btn btn-secondary btn-sm">Pending</a>
                    <a href="<?= BASE_URL ?>/manager/manager_deliveries.php" class="btn btn-secondary btn-sm">Deliveries</a>
                </div>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($recent_orders->num_rows === 0): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <div class="empty-icon">📭</div>
                                    <p>No orders yet for your shop.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($row = $recent_orders->fetch_assoc()):
                            $badge = match($row['status']) {
                                'Pending'        => 'badge-pending',
                                'Accepted'       => 'badge-accepted',
                                'Being Prepared' => 'badge-prepared',
                                'Shipped'        => 'badge-shipped',
                                'Delivered'      => 'badge-delivered',
                                default          => 'badge-pending',
                            };
                        ?>
                        <tr>
                            <td><strong>#<?= $row['id'] ?></strong></td>
                            <td><?= e($row['customer_name']) ?></td>
                            <td><?= number_format((float)$row['total'], 2) ?> SAR</td>
                            <td><span class="badge <?= $badge ?>"><?= e($row['status']) ?></span></td>
                            <td><?= date('M j, Y g:i A', strtotime($row['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- ── End Recent Orders ─────────────────────────── -->

    </div><!-- /page-content -->
</div><!-- /sidebar-layout -->

<?php include __DIR__ . '/../includes/footer.php'; ?>
