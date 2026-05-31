<?php
/**
 * =============================================================
 * JEEM MALL — Shared Header Partial
 * =============================================================
 * Include at the top of every protected page AFTER auth checks.
 *
 * Expects:
 *   $page_title  (string) — used in <title> tag
 *   $active_nav  (string) — nav link to mark as active (optional)
 * =============================================================
 */
$page_title = $page_title ?? 'JEEM MALL';
$active_nav = $active_nav ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — JEEM MALL</title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- JEEM MALL custom design system -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

    <!--
      Apply saved theme BEFORE first paint to prevent a flash.
      This tiny inline script runs synchronously in <head>.
    -->
    <script>
        (function() {
            var saved = localStorage.getItem('jeemTheme') || 'dark';
            document.getElementById('html-root').setAttribute('data-theme', saved);
        })();
    </script>
</head>
<body>

<!-- ── Top Navigation Bar ─────────────────────────────────────── -->
<nav class="topnav">
    <a href="<?= BASE_URL ?>/index.php" class="brand brand-gradient">JEEM MALL</a>

    <div class="topnav-links">

        <?php if (has_role('customer')): ?>
            <a href="<?= BASE_URL ?>/customer/customer_dashboard.php"
               class="<?= $active_nav === 'browse' ? 'active' : '' ?>">🏪 Browse</a>
            <a href="<?= BASE_URL ?>/customer/cart.php"
               class="<?= $active_nav === 'cart' ? 'active' : '' ?>">🛒 Cart</a>
            <a href="<?= BASE_URL ?>/customer/customer_orders.php"
               class="<?= $active_nav === 'orders' ? 'active' : '' ?>">📦 Orders</a>
            <a href="<?= BASE_URL ?>/customer/account.php"
               class="<?= $active_nav === 'account' ? 'active' : '' ?>">👤 Account</a>

        <?php elseif (has_role('manager')): ?>
            <!-- Manager: full shopping links so they can shop from other stores -->
            <a href="<?= BASE_URL ?>/customer/customer_dashboard.php"
               class="<?= $active_nav === 'browse'  ? 'active' : '' ?>">🏪 Browse</a>
            <a href="<?= BASE_URL ?>/customer/cart.php"
               class="<?= $active_nav === 'cart'    ? 'active' : '' ?>">🛒 Cart</a>
            <a href="<?= BASE_URL ?>/customer/customer_orders.php"
               class="<?= $active_nav === 'orders'  ? 'active' : '' ?>">📦 Orders</a>
            <a href="<?= BASE_URL ?>/customer/account.php"
               class="<?= $active_nav === 'account' ? 'active' : '' ?>">👤 Account</a>
            <!-- Prominent switch-back button -->
            <a href="<?= BASE_URL ?>/manager/manager_dashboard.php"
               class="btn btn-primary btn-sm"
               style="margin-left:.75rem;white-space:nowrap;">
               🏪 My Shop Dashboard
            </a>

        <?php elseif (has_role('admin')): ?>
            <a href="<?= BASE_URL ?>/admin/admin_dashboard.php"
               class="<?= $active_nav === 'admin_dash' ? 'active' : '' ?>">📊 Dashboard</a>
            <a href="<?= BASE_URL ?>/admin/admin_users.php"
               class="<?= $active_nav === 'admin_users' ? 'active' : '' ?>">👥 Users</a>
            <a href="<?= BASE_URL ?>/admin/admin_shops.php"
               class="<?= $active_nav === 'admin_shops' ? 'active' : '' ?>">🏪 Shops</a>
        <?php endif; ?>

        <!-- ── Theme Toggle ──────────────────────────── -->
        <button id="theme-toggle" onclick="jeemToggleTheme()" title="Switch theme" aria-label="Toggle light/dark mode">
            <span class="toggle-track"><span class="toggle-knob"></span></span>
            <span class="toggle-icon">☀️</span>
            <span class="toggle-label">Light Mode</span>
        </button>

        <!-- Logout is always shown to authenticated users -->
        <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-secondary btn-sm" style="margin-left:.5rem;">
            Sign Out
        </a>
    </div>
</nav>
