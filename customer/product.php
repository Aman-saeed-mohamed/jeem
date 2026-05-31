<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['customer', 'manager']);

$page_title = 'Product';
$active_nav = 'browse';

$product_id = (int)($_GET['id'] ?? 0);
if ($product_id < 1) {
    header('Location: ' . BASE_URL . '/customer/customer_dashboard.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT  p.*,
            s.name     AS shop_name,
            s.id       AS shop_id,
            s.status   AS shop_status
    FROM    products p
    JOIN    shops    s ON s.id = p.shop_id
    WHERE   p.id = ? AND s.status = 'active'
    LIMIT   1
");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: ' . BASE_URL . '/customer/customer_dashboard.php');
    exit;
}

$page_title = e($product['name']);

$stmt = $conn->prepare("SELECT * FROM pictures WHERE product_id = ? ORDER BY sort_order ASC");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$user_id = current_user_id();
$stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ? LIMIT 1");
$stmt->bind_param('ii', $user_id, $product_id);
$stmt->execute();
$cart_row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$in_cart = $cart_row ? (int)$cart_row['quantity'] : 0;

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

$out_of_stock = (int)$product['quantity'] === 0;

include __DIR__ . '/../includes/header.php';
?>

<div class="page-content" style="max-width:1000px;margin:0 auto;">

    
    <div style="font-size:.83rem;color:var(--text-muted);margin-bottom:1.2rem;">
        <a href="<?= BASE_URL ?>/customer/customer_dashboard.php" style="color:var(--text-muted);">🏪 Shops</a>
        &rsaquo;
        <a href="<?= BASE_URL ?>/customer/shop.php?id=<?= $product['shop_id'] ?>" style="color:var(--text-muted);">
            <?= e($product['shop_name']) ?>
        </a>
        &rsaquo; <span style="color:var(--text-primary);"><?= e($product['name']) ?></span>
    </div>

    <?php if ($flash_msg): ?>
    <div class="alert alert-<?= $flash_type === 'error' ? 'error' : 'success' ?>">
        <?= e($flash_msg) ?>
    </div>
    <?php endif; ?>

    
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;align-items:start;" class="product-layout">

        
        <div>
            <?php if (!empty($images)): ?>
                
                <div style="border-radius:var(--radius);overflow:hidden;margin-bottom:.75rem;
                            border:1px solid var(--border-subtle);">
                    <img id="main-product-img"
                         src="<?= BASE_URL ?>/uploads/products/<?= e($images[0]['filename']) ?>"
                         alt="<?= e($product['name']) ?>"
                         style="width:100%;height:360px;object-fit:cover;display:block;">
                </div>

                
                <?php if (count($images) > 1): ?>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                    <?php foreach ($images as $img): ?>
                    <img src="<?= BASE_URL ?>/uploads/products/<?= e($img['filename']) ?>"
                         alt="Thumbnail"
                         onclick="document.getElementById('main-product-img').src = this.src;"
                         style="width:70px;height:70px;object-fit:cover;border-radius:var(--radius-sm);
                                cursor:pointer;border:2px solid var(--border-subtle);
                                transition:var(--transition);"
                         onmouseover="this.style.borderColor='var(--gold)'"
                         onmouseout="this.style.borderColor='var(--border-subtle)'">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="width:100%;height:320px;background:var(--bg-elevated);border-radius:var(--radius);
                            display:flex;align-items:center;justify-content:center;font-size:5rem;
                            color:var(--text-muted);border:1px solid var(--border-subtle);">
                    📷
                </div>
            <?php endif; ?>
        </div>

        
        <div>
            <h1 style="font-size:1.5rem;margin:0 0 .5rem;"><?= e($product['name']) ?></h1>

            <div style="font-size:.85rem;color:var(--text-muted);margin-bottom:1rem;">
                from
                <a href="<?= BASE_URL ?>/customer/shop.php?id=<?= $product['shop_id'] ?>"
                   style="color:var(--gold);"><?= e($product['shop_name']) ?></a>
            </div>

            <div style="font-size:2rem;font-weight:800;color:var(--gold);margin-bottom:1rem;">
                <?= number_format((float)$product['price'], 2) ?> SAR
            </div>

            <?php if ($product['description']): ?>
            <p style="color:var(--text-muted);line-height:1.6;margin-bottom:1.2rem;font-size:.93rem;">
                <?= e($product['description']) ?>
            </p>
            <?php endif; ?>

            
            <div style="margin-bottom:1.2rem;">
                <?php if ($out_of_stock): ?>
                    <span class="badge badge-inactive" style="font-size:.85rem;">❌ Out of Stock</span>
                <?php elseif ($product['quantity'] <= 5): ?>
                    <span style="color:var(--status-pending);font-weight:600;font-size:.88rem;">
                        ⚠️ Only <?= $product['quantity'] ?> units left!
                    </span>
                <?php else: ?>
                    <span style="color:var(--status-delivered);font-weight:600;font-size:.88rem;">
                        ✅ In Stock (<?= $product['quantity'] ?> available)
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($in_cart > 0): ?>
            <div style="padding:.6rem 1rem;background:var(--bg-elevated);border-radius:var(--radius-sm);
                        margin-bottom:1rem;font-size:.87rem;border:1px solid var(--border-subtle);">
                🛒 You already have <strong><?= $in_cart ?></strong> of this item in your cart.
                <a href="<?= BASE_URL ?>/customer/cart.php" style="color:var(--gold);margin-left:.5rem;">
                    View Cart →
                </a>
            </div>
            <?php endif; ?>

            
            <?php if (!$out_of_stock): ?>
            <form method="POST" action="<?= BASE_URL ?>/customer/cart.php">
                <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action"      value="add_to_cart">
                <input type="hidden" name="product_id"  value="<?= $product['id'] ?>">
                <input type="hidden" name="redirect_to" value="product&product_id=<?= $product['id'] ?>">

                <div style="display:flex;gap:.75rem;align-items:center;margin-bottom:1rem;">
                    <label class="form-label" style="margin:0;white-space:nowrap;">Quantity:</label>
                    <input type="number" name="quantity"
                           class="form-control"
                           value="1" min="1" max="<?= $product['quantity'] ?>"
                           style="width:90px;">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;font-size:1rem;padding:.75rem;">
                    🛒 Add to Cart
                </button>
            </form>
            <?php else: ?>
                <button class="btn btn-secondary" disabled style="width:100%;font-size:1rem;padding:.75rem;cursor:not-allowed;">
                    Out of Stock
                </button>
            <?php endif; ?>

            
            <div style="margin-top:1.5rem;">
                <a href="<?= BASE_URL ?>/customer/shop.php?id=<?= $product['shop_id'] ?>"
                   style="color:var(--text-muted);font-size:.85rem;">
                    ← Back to <?= e($product['shop_name']) ?>
                </a>
            </div>
        </div>

    </div>

</div>

<style>
/* Stack product layout on mobile */
@media (max-width:700px) {
    .product-layout { grid-template-columns: 1fr !important; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
