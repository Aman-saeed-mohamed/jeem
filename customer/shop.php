<?php
/**
 * =============================================================
 * JEEM MALL — Customer: Shop Product Listing
 * =============================================================
 * Displays all products for a given active shop.
 * Each product card shows price, stock badge, and an
 * "Add to Cart" button (disabled if out of stock).
 *
 * Cart is DB-backed (cart table), not session-based.
 * =============================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['customer', 'manager']);

$page_title = 'Shop';
$active_nav = 'browse';

$shop_id = (int)($_GET['id'] ?? 0);

if ($shop_id < 1) {
    header('Location: ' . BASE_URL . '/customer/customer_dashboard.php');
    exit;
}

// ── Fetch the shop (must be active) ──────────────────────────
$stmt = $conn->prepare("SELECT * FROM shops WHERE id = ? AND status = 'active' LIMIT 1");
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$shop = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$shop) {
    // Shop inactive or doesn't exist — redirect away.
    header('Location: ' . BASE_URL . '/customer/customer_dashboard.php');
    exit;
}

$page_title = e($shop['name']);

// ── Fetch all products with their main image ──────────────────
$stmt = $conn->prepare("
    SELECT  p.*,
            (SELECT filename FROM pictures
             WHERE  product_id = p.id AND sort_order = 0 LIMIT 1) AS main_image
    FROM    products p
    WHERE   p.shop_id = ?
    ORDER BY p.name ASC
");
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Fetch cart quantities for THIS user for quick "in cart" UI ─
// We show how many units of each product are already in the cart.
$user_id = current_user_id();
$cart_qtys = []; // product_id → qty in cart

$stmt = $conn->prepare("SELECT product_id, quantity FROM cart WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$cart_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($cart_rows as $row) {
    $cart_qtys[(int)$row['product_id']] = (int)$row['quantity'];
}

$type_labels = [
    'coffeeshop'              => 'Coffee Shop',
    'restaurant'              => 'Restaurant',
    'clothing_men'            => 'Men\'s Clothing',
    'clothing_women'          => 'Women\'s Clothing',
    'clothing_kids'           => 'Kids\' Clothing',
    'clothing_sports'         => 'Sports Clothing',
    'electronics_phones'      => 'Phones',
    'electronics_laptops'     => 'Laptops',
    'electronics_accessories' => 'Electronics Accessories',
];

// ── Handle flash messages from add-to-cart redirect ──────────
$flash_msg  = '';
$flash_type = '';
if (!empty($_SESSION['flash_success'])) {
    $flash_msg  = $_SESSION['flash_success'];
    $flash_type = 'success';
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $flash_msg  = $_SESSION['flash_error'];
    $flash_type = 'error';
    unset($_SESSION['flash_error']);
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-content" style="max-width:1200px;margin:0 auto;">

    <!-- Breadcrumb -->
    <div style="font-size:.83rem;color:var(--text-muted);margin-bottom:1rem;">
        <a href="<?= BASE_URL ?>/customer/customer_dashboard.php" style="color:var(--text-muted);">
            🏪 Shops
        </a>
        &rsaquo; <span style="color:var(--text-primary);"><?= e($shop['name']) ?></span>
    </div>

    <!-- Shop Header -->
    <div class="page-header">
        <h1><?= e($shop['name']) ?></h1>
        <p>
            <?= e($type_labels[$shop['type']] ?? $shop['type']) ?>
            &nbsp;·&nbsp; 📍 <?= e($shop['location']) ?>
            &nbsp;·&nbsp; <?= count($products) ?> product<?= count($products) !== 1 ? 's' : '' ?>
        </p>
    </div>

    <?php if ($flash_msg): ?>
    <div class="alert alert-<?= $flash_type === 'error' ? 'error' : 'success' ?>">
        <?= e($flash_msg) ?>
    </div>
    <?php endif; ?>

    <?php if (empty($products)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon">📦</div>
            <p>This shop has no products listed yet.</p>
        </div>
    </div>

    <?php else: ?>
    <!-- ── Products Grid ──────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1.2rem;">

        <?php foreach ($products as $prod):
            $in_cart     = $cart_qtys[(int)$prod['id']] ?? 0;
            $out_of_stock = (int)$prod['quantity'] === 0;
        ?>
        <div class="card" style="display:flex;flex-direction:column;padding:0;overflow:hidden;">

            <!-- Product image -->
            <a href="<?= BASE_URL ?>/customer/product.php?id=<?= $prod['id'] ?>"
               style="display:block;text-decoration:none;">
                <?php if ($prod['main_image']): ?>
                    <img src="<?= BASE_URL ?>/uploads/products/<?= e($prod['main_image']) ?>"
                         alt="<?= e($prod['name']) ?>"
                         style="width:100%;height:180px;object-fit:cover;">
                <?php else: ?>
                    <div style="width:100%;height:180px;background:var(--bg-elevated);
                                display:flex;align-items:center;justify-content:center;
                                font-size:3rem;color:var(--text-muted);">
                        📷
                    </div>
                <?php endif; ?>
            </a>

            <!-- Product info -->
            <div style="padding:1rem;flex:1;display:flex;flex-direction:column;gap:.4rem;">
                <a href="<?= BASE_URL ?>/customer/product.php?id=<?= $prod['id'] ?>"
                   style="text-decoration:none;color:var(--text-primary);">
                    <h4 style="margin:0;font-size:.95rem;"><?= e($prod['name']) ?></h4>
                </a>

                <?php if ($prod['description']): ?>
                <p style="font-size:.78rem;color:var(--text-muted);margin:0;
                           overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
                    <?= e($prod['description']) ?>
                </p>
                <?php endif; ?>

                <div style="margin-top:auto;padding-top:.5rem;">
                    <div style="font-size:1.1rem;font-weight:700;color:var(--gold);margin-bottom:.5rem;">
                        <?= number_format((float)$prod['price'], 2) ?> SAR
                    </div>

                    <?php if ($out_of_stock): ?>
                        <span class="badge badge-inactive">Out of Stock</span>
                    <?php elseif ($prod['quantity'] <= 5): ?>
                        <span style="font-size:.75rem;color:var(--status-pending);">
                            Only <?= $prod['quantity'] ?> left!
                        </span>
                    <?php endif; ?>

                    <!-- Add to Cart form -->
                    <form method="POST"
                          action="<?= BASE_URL ?>/customer/cart.php"
                          style="margin-top:.6rem;">
                        <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action"      value="add_to_cart">
                        <input type="hidden" name="product_id"  value="<?= $prod['id'] ?>">
                        <input type="hidden" name="redirect_to" value="shop&shop_id=<?= $shop_id ?>">
                        <button type="submit"
                                class="btn btn-primary btn-sm"
                                style="width:100%;"
                                <?= $out_of_stock ? 'disabled' : '' ?>>
                            <?php if ($out_of_stock): ?>
                                Out of Stock
                            <?php elseif ($in_cart > 0): ?>
                                🛒 In Cart (<?= $in_cart ?>) — Add More
                            <?php else: ?>
                                🛒 Add to Cart
                            <?php endif; ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
    <!-- ── End Products Grid ──────────────────────────────── -->
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
