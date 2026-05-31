<?php
/**
 * =============================================================
 * JEEM MALL — Admin Dashboard
 * =============================================================
 * Displays platform-wide analytics:
 *   - Total Users
 *   - Active Shops
 *   - Total Orders (excluding Canceled, future-proofed)
 *   - Total Sales Revenue (excluding Canceled)
 *   - Pending orders alert
 *   - Last 10 orders quick table
 * =============================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_role('admin');

$page_title = 'Admin Dashboard';
$active_nav = 'admin_dash';

// ── Metric 1: Total Users ─────────────────────────────────────
$result      = $conn->query("SELECT COUNT(*) AS cnt FROM users");
$total_users = (int)$result->fetch_assoc()['cnt'];

// ── Metric 2: Active Shops ────────────────────────────────────
$result       = $conn->query("SELECT COUNT(*) AS cnt FROM shops WHERE status = 'active'");
$active_shops = (int)$result->fetch_assoc()['cnt'];

// ── Metric 3: Total Orders (excluding Canceled) ───────────────
// NOTE: 'Canceled' is not in the current ENUM (Pending, Accepted,
// Being Prepared, Shipped, Delivered). This WHERE clause is
// intentionally future-proof in case 'Canceled' is ever added.
$result       = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE status != 'Canceled'");
$total_orders = (int)$result->fetch_assoc()['cnt'];

// ── Metric 4: Total Sales Revenue (excluding Canceled) ────────
$result        = $conn->query(
    "SELECT COALESCE(SUM(total), 0.00) AS revenue FROM orders WHERE status != 'Canceled'"
);
$total_revenue = (float)$result->fetch_assoc()['revenue'];

// ── Pending Orders Count (for global alert) ───────────────────
$result         = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE status = 'Pending'");
$pending_orders = (int)$result->fetch_assoc()['cnt'];

// ── Recent 10 Orders ─────────────────────────────────────────
$recent_orders = $conn->query("
    SELECT  o.id,
            o.status,
            o.total,
            o.created_at,
            u.name  AS customer_name,
            s.name  AS shop_name
    FROM    orders  o
    JOIN    users   u ON u.id = o.customer_id
    LEFT JOIN shops s ON s.id = o.shop_id
    ORDER BY o.created_at DESC
    LIMIT 10
");

include __DIR__ . '/../includes/header.php';
?>

<div class="sidebar-layout">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="page-content">

        <!-- Page Header -->
        <div class="page-header">
            <h1>📊 Admin Dashboard</h1>
            <p>Platform-wide analytics and real-time overview.</p>
        </div>

        <!-- ── Pending Orders Alert ───────────────────────────── -->
        <?php if ($pending_orders > 0): ?>
        <div class="alert alert-info">
            🔔 There
            <?= $pending_orders === 1 ? 'is' : 'are' ?>
            <strong><?= number_format($pending_orders) ?></strong>
            pending order<?= $pending_orders === 1 ? '' : 's' ?> across the platform
            awaiting manager action.
        </div>
        <?php endif; ?>

        <!-- ── Metrics Grid ───────────────────────────────────── -->
        <div class="metrics-grid">

            <div class="metric-card">
                <div class="metric-icon">👥</div>
                <div class="metric-value"><?= number_format($total_users) ?></div>
                <div class="metric-label">Total Users</div>
            </div>

            <div class="metric-card">
                <div class="metric-icon">🏪</div>
                <div class="metric-value"><?= number_format($active_shops) ?></div>
                <div class="metric-label">Active Shops</div>
            </div>

            <div class="metric-card">
                <div class="metric-icon">📦</div>
                <div class="metric-value"><?= number_format($total_orders) ?></div>
                <div class="metric-label">Total Orders</div>
            </div>

            <div class="metric-card">
                <div class="metric-icon">💰</div>
                <div class="metric-value"><?= number_format($total_revenue, 2) ?></div>
                <div class="metric-label">Revenue (SAR)</div>
            </div>

        </div>
        <!-- ── End Metrics ─────────────────────────────────────── -->

        <!-- ── Recent Orders Table ────────────────────────────── -->
        <div class="card">
            <div class="d-flex justify-between align-center mb-2">
                <h3 style="margin:0;">🕐 Recent Orders</h3>
                <a href="<?= BASE_URL ?>/admin/admin_shops.php" class="btn btn-secondary btn-sm">Manage Shops →</a>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Shop</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_orders->num_rows === 0): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-icon">📭</div>
                                    <p>No orders placed yet.</p>
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
                            <td>
                                <?php if ($row['shop_name']): ?>
                                    <?= e($row['shop_name']) ?>
                                <?php else: ?>
                                    <span class="text-muted">[Shop Deleted]</span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format((float)$row['total'], 2) ?> SAR</td>
                            <td><span class="badge <?= $badge ?>"><?= e($row['status']) ?></span></td>
                            <td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- ── End Recent Orders ──────────────────────────────── -->

    </div><!-- /page-content -->
</div><!-- /sidebar-layout -->

<?php include __DIR__ . '/../includes/footer.php'; ?>
