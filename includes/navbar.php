<?php
// includes/navbar.php
// Requires: $active_page variable set by including page (e.g. 'home', 'cart', 'orders', 'account')
$active_page = $active_page ?? '';
?>
<nav class="navbar">
    <div class="nav-container">
        <a href="customer_dashboard.php" class="nav-logo">Jeem Mall</a>
        <div class="nav-links">
            <a href="customer_dashboard.php" class="nav-link <?php echo $active_page === 'home'    ? 'active' : ''; ?>">Home</a>
            <a href="cart.php"               class="nav-link <?php echo $active_page === 'cart'    ? 'active' : ''; ?>">🛒 Cart</a>
            <a href="orders.php"             class="nav-link <?php echo $active_page === 'orders'  ? 'active' : ''; ?>">Orders</a>
            <a href="account.php"            class="nav-link <?php echo $active_page === 'account' ? 'active' : ''; ?>">Account</a>
            <button id="theme-toggle" class="theme-toggle" aria-label="Toggle Dark Mode">🌙</button>
            <span style="color:var(--text-muted);font-size:0.85rem;font-weight:500;">
                👤 <?php echo htmlspecialchars($_SESSION['username']); ?>
            </span>
            <a href="logout.php" class="nav-btn btn-logout">Log Out</a>
        </div>
    </div>
</nav>
