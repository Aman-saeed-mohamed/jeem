<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['customer', 'manager']);

$user_id    = current_user_id();
$page_title = 'My Cart';
$active_nav = 'cart';

$message      = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    

    

    

    if ($action === 'add_to_cart') {

        $product_id   = (int)($_POST['product_id'] ?? 0);
        $qty_to_add   = max(1, (int)($_POST['quantity'] ?? 1));
        $redirect_to  = $_POST['redirect_to'] ?? '';

        

        $stmt = $conn->prepare("
            SELECT p.id, p.quantity, p.shop_id
            FROM   products p
            JOIN   shops    s ON s.id = p.shop_id
            WHERE  p.id = ? AND s.status = 'active'
            LIMIT  1
        ");
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$prod) {
            $_SESSION['flash_error'] = 'Product not found or shop is unavailable.';
        } elseif ($prod['quantity'] < 1) {
            $_SESSION['flash_error'] = 'Sorry, this product is out of stock.';
        } else {
            
            $stmt = $conn->prepare("
                INSERT INTO cart (user_id, product_id, quantity)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    quantity = LEAST(quantity + VALUES(quantity), ?)
            ");
            $stmt->bind_param('iiii', $user_id, $product_id, $qty_to_add, $prod['quantity']);
            $stmt->execute();
            $stmt->close();

            $_SESSION['flash_success'] = 'Item added to your cart! 🛒';
        }

        

        if (str_starts_with($redirect_to, 'shop&')) {
            parse_str($redirect_to, $params);
            $back = BASE_URL . '/customer/shop.php?id=' . (int)($params['shop_id'] ?? 0);
        } elseif (str_starts_with($redirect_to, 'product&')) {
            parse_str($redirect_to, $params);
            $back = BASE_URL . '/customer/product.php?id=' . (int)($params['product_id'] ?? 0);
        } else {
            $back = BASE_URL . '/customer/cart.php';
        }
        header('Location: ' . $back);
        exit;

    

    

    

    } elseif ($action === 'update_qty') {

        $product_id = (int)($_POST['product_id'] ?? 0);
        $new_qty    = (int)($_POST['quantity']   ?? 1);

        if ($new_qty < 1) {
            

            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param('ii', $user_id, $product_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash_success'] = 'Item removed from cart.';
        } else {
            

            $stmt = $conn->prepare("
                UPDATE cart c
                JOIN   products p ON p.id = c.product_id
                SET    c.quantity = LEAST(?, p.quantity)
                WHERE  c.user_id = ? AND c.product_id = ?
            ");
            $stmt->bind_param('iii', $new_qty, $user_id, $product_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash_success'] = 'Cart updated.';
        }

        header('Location: ' . BASE_URL . '/customer/cart.php');
        exit;

    

    

    

    } elseif ($action === 'remove_item') {

        $product_id = (int)($_POST['product_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param('ii', $user_id, $product_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['flash_success'] = 'Item removed from cart.';
        header('Location: ' . BASE_URL . '/customer/cart.php');
        exit;

    

    

    

    } elseif ($action === 'checkout') {

        

        

        $stmt = $conn->prepare("
            SELECT  c.product_id,
                    c.quantity        AS requested_qty,
                    p.name            AS product_name,
                    p.price           AS unit_price,
                    p.quantity        AS available_qty,
                    p.shop_id
            FROM    cart     c
            JOIN    products p ON p.id = c.product_id
            JOIN    shops    s ON s.id = p.shop_id
            WHERE   c.user_id = ? AND s.status = 'active'
            ORDER BY p.shop_id ASC
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($cart_items)) {
            $message      = 'Your cart is empty or contains items from unavailable shops.';
            $message_type = 'error';
        } else {

            

            $grouped = [];
            foreach ($cart_items as $item) {
                $sid = (int)$item['shop_id'];
                $grouped[$sid][] = $item;
            }

            

            $conn->begin_transaction();

            try {
                

                

                

                

                foreach ($cart_items as $item) {
                    $pid = (int)$item['product_id'];

                    $stmt = $conn->prepare(
                        "SELECT quantity FROM products WHERE id = ? LIMIT 1 FOR UPDATE"
                    );
                    $stmt->bind_param('i', $pid);
                    $stmt->execute();
                    $live_qty = (int)$stmt->get_result()->fetch_assoc()['quantity'];
                    $stmt->close();

                    if ($item['requested_qty'] > $live_qty) {
                        

                        $avail_msg = $live_qty === 0
                            ? 'is now out of stock'
                            : "only has $live_qty unit(s) available";
                        throw new Exception(
                            "\"" . $item['product_name'] . "\" $avail_msg. " .
                            "Please update your cart and try again."
                        );
                    }
                }

                

                $created_order_ids = [];

                foreach ($grouped as $shop_id => $items) {

                    

                    $subtotal = 0.0;
                    foreach ($items as $item) {
                        $subtotal += (float)$item['unit_price'] * (int)$item['requested_qty'];
                    }
                    $tax   = round($subtotal * 0.08, 2);
                    $total = round($subtotal + $tax, 2);
                    $subtotal = round($subtotal, 2);

                    

                    $stmt = $conn->prepare("
                        INSERT INTO orders (customer_id, shop_id, subtotal, tax, total, status)
                        VALUES (?, ?, ?, ?, ?, 'Pending')
                    ");
                    $stmt->bind_param('iiddd', $user_id, $shop_id, $subtotal, $tax, $total);
                    $stmt->execute();
                    $order_id = $conn->insert_id;
                    $stmt->close();

                    $created_order_ids[] = $order_id;

                    

                    

                    

                    

                    foreach ($items as $item) {
                        $pid       = (int)$item['product_id'];
                        $pname     = $item['product_name'];
                        $uprice    = (float)$item['unit_price'];
                        $qty       = (int)$item['requested_qty'];

                        $stmt = $conn->prepare("
                            INSERT INTO order_line
                                (order_id, product_id, product_name, unit_price, quantity)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->bind_param('iisdi', $order_id, $pid, $pname, $uprice, $qty);
                        $stmt->execute();
                        $stmt->close();

                        

                        $stmt = $conn->prepare(
                            "UPDATE products SET quantity = quantity - ? WHERE id = ?"
                        );
                        $stmt->bind_param('ii', $qty, $pid);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                

                $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->close();

                

                $conn->commit();

                $order_count = count($created_order_ids);
                $_SESSION['flash_success'] =
                    "✅ Order placed successfully! " .
                    "$order_count order" . ($order_count > 1 ? 's were' : ' was') .
                    " created (one per shop). Track them in My Orders.";

                header('Location: ' . BASE_URL . '/customer/customer_orders.php');
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                $message      = '❌ Checkout failed: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

if (empty($message)) {
    if (!empty($_SESSION['flash_success'])) {
        $message      = $_SESSION['flash_success'];
        $message_type = 'success';
        unset($_SESSION['flash_success']);
    } elseif (!empty($_SESSION['flash_error'])) {
        $message      = $_SESSION['flash_error'];
        $message_type = 'error';
        unset($_SESSION['flash_error']);
    }
}

$stmt = $conn->prepare("
    SELECT  c.product_id,
            c.quantity        AS cart_qty,
            p.name            AS product_name,
            p.price           AS unit_price,
            p.quantity        AS stock_qty,
            p.shop_id,
            s.name            AS shop_name,
            s.status          AS shop_status,
            (SELECT filename FROM pictures
             WHERE  product_id = p.id AND sort_order = 0 LIMIT 1) AS main_image
    FROM    cart     c
    JOIN    products p ON p.id  = c.product_id
    JOIN    shops    s ON s.id  = p.shop_id
    WHERE   c.user_id = ?
    ORDER BY s.name ASC, p.name ASC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$cart_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$cart_by_shop   = [];
$grand_total    = 0.0;
$grand_subtotal = 0.0;
$grand_tax      = 0.0;
$has_issue      = false; 

foreach ($cart_rows as $row) {
    $sid = $row['shop_id'];
    if (!isset($cart_by_shop[$sid])) {
        $cart_by_shop[$sid] = [
            'shop_name'   => $row['shop_name'],
            'shop_status' => $row['shop_status'],
            'items'       => [],
            'subtotal'    => 0.0,
        ];
    }
    $line_total = (float)$row['unit_price'] * (int)$row['cart_qty'];
    $cart_by_shop[$sid]['items'][]  = $row;
    $cart_by_shop[$sid]['subtotal'] += $line_total;
    $grand_subtotal += $line_total;

    

    if ($row['stock_qty'] < $row['cart_qty'] || $row['shop_status'] !== 'active') {
        $has_issue = true;
    }
}

$grand_tax   = round($grand_subtotal * 0.08, 2);
$grand_total = round($grand_subtotal + $grand_tax, 2);

$stmt = $conn->prepare(
    "SELECT address FROM user_addresses WHERE user_id = ? AND is_default = 1 LIMIT 1"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$default_addr = $stmt->get_result()->fetch_assoc();
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-content" style="max-width:1100px;margin:0 auto;">

    <div class="page-header">
        <h1>🛒 My Cart</h1>
        <p>
            <?= count($cart_rows) ?> item<?= count($cart_rows) !== 1 ? 's' : '' ?>
            from <?= count($cart_by_shop) ?> shop<?= count($cart_by_shop) !== 1 ? 's' : '' ?>
        </p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $message_type === 'error' ? 'error' : 'success' ?>">
        <?= e($message) ?>
    </div>
    <?php endif; ?>

    <?php if (empty($cart_rows)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon">🛒</div>
            <p>Your cart is empty.</p>
            <a href="<?= BASE_URL ?>/customer/customer_dashboard.php" class="btn btn-primary" style="margin-top:.5rem;">
                Browse Shops →
            </a>
        </div>
    </div>

    <?php else: ?>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start;" class="cart-layout">

        
        <div>

        <?php foreach ($cart_by_shop as $sid => $group): ?>
        <div class="card" style="margin-bottom:1.2rem;">

            
            <div style="font-weight:700;font-size:.95rem;color:var(--gold);
                        padding-bottom:.75rem;margin-bottom:.75rem;
                        border-bottom:1px solid var(--border-subtle);">
                🏪 <?= e($group['shop_name']) ?>
                <?php if ($group['shop_status'] !== 'active'): ?>
                    <span class="badge badge-inactive" style="margin-left:.5rem;">Shop Inactive</span>
                <?php endif; ?>
            </div>

            <?php foreach ($group['items'] as $row):
                $line_total   = (float)$row['unit_price'] * (int)$row['cart_qty'];
                $stock_issue  = (int)$row['stock_qty'] < (int)$row['cart_qty'];
                $out_of_stock = (int)$row['stock_qty'] === 0;
            ?>
            <div style="display:flex;gap:1rem;align-items:center;
                        padding:.75rem 0;border-bottom:1px solid var(--border-subtle);">

                
                <?php if ($row['main_image']): ?>
                <img src="<?= BASE_URL ?>/uploads/products/<?= e($row['main_image']) ?>"
                     alt="<?= e($row['product_name']) ?>"
                     style="width:62px;height:62px;object-fit:cover;border-radius:var(--radius-sm);flex-shrink:0;">
                <?php else: ?>
                <div style="width:62px;height:62px;background:var(--bg-elevated);border-radius:var(--radius-sm);
                            display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;">
                    📷
                </div>
                <?php endif; ?>

                
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= e($row['product_name']) ?>
                    </div>
                    <div style="color:var(--text-muted);font-size:.8rem;">
                        <?= number_format((float)$row['unit_price'], 2) ?> SAR each
                    </div>
                    <?php if ($out_of_stock): ?>
                        <div style="color:var(--danger);font-size:.77rem;font-weight:600;margin-top:.2rem;">
                            ❌ Out of stock — please remove
                        </div>
                    <?php elseif ($stock_issue): ?>
                        <div style="color:var(--status-pending);font-size:.77rem;font-weight:600;margin-top:.2rem;">
                            ⚠️ Only <?= $row['stock_qty'] ?> available — update quantity
                        </div>
                    <?php endif; ?>
                </div>

                
                <form method="POST" action="" style="display:flex;align-items:center;gap:.4rem;flex-shrink:0;">
                    <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action"      value="update_qty">
                    <input type="hidden" name="product_id"  value="<?= $row['product_id'] ?>">
                    <input type="number"
                           name="quantity"
                           value="<?= $row['cart_qty'] ?>"
                           min="0"
                           max="<?= $row['stock_qty'] ?>"
                           class="form-control"
                           style="width:70px;padding:.3rem .5rem;"
                           onchange="this.form.submit()">
                </form>

                
                <div style="font-weight:700;color:var(--gold);text-align:right;flex-shrink:0;min-width:80px;">
                    <?= number_format($line_total, 2) ?> SAR
                </div>

                
                <form method="POST" action="" style="flex-shrink:0;">
                    <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action"      value="remove_item">
                    <input type="hidden" name="product_id"  value="<?= $row['product_id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm"
                            title="Remove item"
                            data-confirm="Remove '<?= e($row['product_name']) ?>' from your cart?">
                        🗑️
                    </button>
                </form>

            </div>
            <?php endforeach; ?>

            
            <div style="text-align:right;padding-top:.6rem;font-size:.88rem;color:var(--text-muted);">
                Shop subtotal: <strong style="color:var(--text-primary);">
                    <?= number_format($group['subtotal'], 2) ?> SAR
                </strong>
            </div>

        </div>
        <?php endforeach; ?>

        </div>

        
        <div>
            <div class="card" style="position:sticky;top:1.5rem;">
                <h3 style="margin:0 0 1rem;">🧾 Order Summary</h3>

                <div style="display:flex;justify-content:space-between;margin-bottom:.5rem;font-size:.9rem;">
                    <span>Subtotal</span>
                    <span><?= number_format($grand_subtotal, 2) ?> SAR</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:.5rem;font-size:.9rem;color:var(--text-muted);">
                    <span>VAT (8%)</span>
                    <span><?= number_format($grand_tax, 2) ?> SAR</span>
                </div>
                <div style="display:flex;justify-content:space-between;
                            border-top:1px solid var(--border-subtle);
                            padding-top:.75rem;margin-top:.25rem;
                            font-size:1.1rem;font-weight:700;color:var(--gold);">
                    <span>Total</span>
                    <span><?= number_format($grand_total, 2) ?> SAR</span>
                </div>

                <?php if (count($cart_by_shop) > 1): ?>
                <p style="font-size:.77rem;color:var(--text-muted);margin-top:.75rem;">
                    📌 Your cart has items from <?= count($cart_by_shop) ?> shops.
                    A separate order will be created per shop.
                </p>
                <?php endif; ?>

                
                <div style="margin-top:1rem;padding:.75rem;background:var(--bg-elevated);
                            border-radius:var(--radius-sm);font-size:.83rem;">
                    <div style="font-weight:600;margin-bottom:.3rem;">📍 Delivering to:</div>
                    <?php if ($default_addr): ?>
                        <div style="color:var(--text-muted);"><?= e($default_addr['address']) ?></div>
                    <?php else: ?>
                        <div style="color:var(--status-pending);">
                            No default address set.
                            <a href="<?= BASE_URL ?>/customer/account.php" style="color:var(--gold);">Add one →</a>
                        </div>
                    <?php endif; ?>
                </div>

                
                <form method="POST" action="" style="margin-top:1.2rem;">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action"     value="checkout">
                    <button type="submit"
                            class="btn btn-primary"
                            style="width:100%;font-size:1rem;padding:.8rem;"
                            <?= ($has_issue || !$default_addr) ? 'disabled title="Fix cart issues or add a delivery address first."' : '' ?>>
                        ✅ Place Order<?= count($cart_by_shop) > 1 ? 's (' . count($cart_by_shop) . ')' : '' ?>
                    </button>
                </form>

                <?php if ($has_issue): ?>
                <p style="color:var(--danger);font-size:.78rem;margin-top:.5rem;text-align:center;">
                    ⚠️ Fix stock issues above before checking out.
                </p>
                <?php elseif (!$default_addr): ?>
                <p style="color:var(--status-pending);font-size:.78rem;margin-top:.5rem;text-align:center;">
                    ⚠️ Please add a delivery address in
                    <a href="<?= BASE_URL ?>/customer/account.php" style="color:var(--gold);">Account →</a>
                </p>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>/customer/customer_dashboard.php"
                   style="display:block;text-align:center;margin-top:.75rem;
                          font-size:.83rem;color:var(--text-muted);">
                    ← Continue Shopping
                </a>
            </div>
        </div>

    </div>
    <?php endif; ?>

</div>

<style>
@media (max-width:750px) {
    .cart-layout { grid-template-columns: 1fr !important; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
