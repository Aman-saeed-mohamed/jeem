<?php

$active_nav = $active_nav ?? '';
?>
<aside class="sidebar">

    <div class="sidebar-section">Overview</div>
    <a href="<?= BASE_URL ?>/admin/admin_dashboard.php"
       class="<?= $active_nav === 'admin_dash' ? 'active' : '' ?>">
        📊 Dashboard
    </a>

    <div class="sidebar-section">Management</div>
    <a href="<?= BASE_URL ?>/admin/admin_users.php"
       class="<?= $active_nav === 'admin_users' ? 'active' : '' ?>">
        👥 Users
    </a>
    <a href="<?= BASE_URL ?>/admin/admin_shops.php"
       class="<?= $active_nav === 'admin_shops' ? 'active' : '' ?>">
        🏪 Shops
    </a>

    <div class="sidebar-section">Account</div>
    <a href="<?= BASE_URL ?>/auth/logout.php" style="color:var(--danger);">
        🚪 Sign Out
    </a>

</aside>
