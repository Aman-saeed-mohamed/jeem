<?php
/**
 * =============================================================
 * JEEM MALL — Manager Sidebar Partial
 * =============================================================
 * Included inside .sidebar-layout on all manager pages.
 * $active_nav is set by the parent page to highlight the correct link.
 * =============================================================
 */
$active_nav = $active_nav ?? '';
// Pass the shop name into the sidebar if available
$_sidebar_shop = $my_shop ?? null;
?>
<aside class="sidebar">

    <!-- Shop identity -->
    <?php if ($_sidebar_shop): ?>
    <div style="padding:1rem 1.2rem 0.5rem; border-bottom:1px solid var(--border-subtle); margin-bottom:0.5rem;">
        <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.25rem;">Managing</div>
        <div style="font-weight:700;font-size:.95rem;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            <?= e($_sidebar_shop['name']) ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="sidebar-section">My Shop</div>
    <a href="<?= BASE_URL ?>/manager/manager_dashboard.php"
       class="<?= $active_nav === 'mgr_dash' ? 'active' : '' ?>">
        📊 Dashboard
    </a>
    <a href="<?= BASE_URL ?>/manager/manager_products.php"
       class="<?= $active_nav === 'mgr_products' ? 'active' : '' ?>">
        📦 Products
    </a>
    <a href="<?= BASE_URL ?>/manager/manager_orders.php"
       class="<?= $active_nav === 'mgr_orders' ? 'active' : '' ?>">
        🛒 Pending Orders
    </a>
    <a href="<?= BASE_URL ?>/manager/manager_deliveries.php"
       class="<?= $active_nav === 'mgr_deliveries' ? 'active' : '' ?>">
        🚚 Deliveries
    </a>

    <div class="sidebar-section">Customer Mode</div>
    <a href="<?= BASE_URL ?>/customer/customer_dashboard.php"
       style="color:var(--status-shipped);">
        🛍️ Switch to Shopping Mode
    </a>

    <div class="sidebar-section">Account</div>
    <a href="<?= BASE_URL ?>/auth/logout.php" style="color:var(--danger);">
        🚪 Sign Out
    </a>

</aside>
