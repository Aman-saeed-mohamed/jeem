<?php
/**
 * =============================================================
 * JEEM MALL — Customer: Cart  (THE $1,000,000 QA TRAP)
 * =============================================================
 * This file handles THREE responsibilities:
 *
 *  A) POST ACTIONS (cart mutations + checkout):
 *      add_to_cart    → INSERT or UPDATE cart row
 *      update_qty     → UPDATE cart row quantity
 *      remove_item    → DELETE single cart row
 *      clear_cart     → DELETE all cart rows for this user
 *      checkout       → ATOMIC MULTI-VENDOR TRANSACTION (see below)
 *
 *  B) GET VIEW → renders the cart page with all items and totals
 *
 * ────────────────────────────────────────────────────────────
 * CHECKOUT TRANSACTION ALGORITHM  (Multi-Vendor Split)
 * ────────────────────────────────────────────────────────────
 *  1. Fetch cart items JOIN products JOIN shops
 *  2. GROUP items by shop_id in PHP
 *  3. BEGIN TRANSACTION
 *  4. FOR EACH ITEM:
 *       SELECT quantity FROM products WHERE id=? FOR UPDATE
 *       IF requested_qty > available → ROLLBACK, show error
 *  5. FOR EACH SHOP GROUP:
 *       Calculate subtotal = Σ(unit_price × qty)
 *       Calculate tax      = subtotal × 0.08
 *       Calculate total    = subtotal + tax
 *       INSERT INTO orders (customer_id, shop_id, subtotal, tax, total)
 *       FOR EACH ITEM IN GROUP:
 *         INSERT INTO order_line (order_id, product_id, product_name,
 *                                 unit_price, quantity)
 *         UPDATE products SET quantity = quantity - ? WHERE id = ?
 *  6. DELETE FROM cart WHERE user_id = ?
 *  7. COMMIT
 *
 * SECURITY:
 *   - Cart scoped to current_user_id() — users can't touch each other's carts
 *   - FOR UPDATE row lock prevents race condition (two users buying last item)
 *   - Prices read from DB at checkout time — never from $_POST
 *   - product_name + unit_price SNAPSHOTTED into order_line (immutable record)
 *   - CSRF on every POST form
 * =============================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['customer', 'manager']);

$user_id    = current_user_id();
$page_title = 'My Cart';
$active_nav = 'cart';

$message      = '';
$message_type = '';

// ── POST Handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    // ─────────────────────────────────────────────────────────
    // ACTION: Add to Cart
    // ─────────────────────────────────────────────────────────
    if ($action === 'add_to_cart') {

        $product_id   = (int)($_POST['product_id'] ?? 0);
        $qty_to_add   = max(1, (int)($_POST['quantity'] ?? 1));
        $redirect_to  = $_POST['redirect_to'] ?? '';

        // Verify product exists and the shop is active.
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
            /*
             * If the product is already in the cart, increment quantity.
             * Cap at actual available stock so we never over-commit.
             *
             * ON DUPLICATE KEY UPDATE uses the composite UNIQUE key
             * (user_id, product_id) defined in the schema.
             */
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

        // PRG: redirect back to wherever the user was (shop page or product page).
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

    // ─────────────────────────────────────────────────────────
    // ACTION: Update Quantity
    // ─────────────────────────────────────────────────────────
    } elseif ($action === 'update_qty') {

        $product_id = (int)($_POST['product_id'] ?? 0);
        $new_qty    = (int)($_POST['quantity']   ?? 1);

        if ($new_qty < 1) {
            // Treat quantity ≤ 0 as a delete request.
            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param('ii', $user_id, $product_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash_success'] = 'Item removed from cart.';
        } else {
            // Cap at actual stock to avoid over-committing.
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

    // ─────────────────────────────────────────────────────────
    // ACTION: Remove Single Item
    // ─────────────────────────────────────────────────────────
    } elseif ($action === 'remove_item') {

        $product_id = (int)($_POST['product_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param('ii', $user_id, $product_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['flash_success'] = 'Item removed from cart.';
        header('Location: ' . BASE_URL . '/customer/cart.php');
        exit;

    // ─────────────────────────────────────────────────────────
    // ACTION: CHECKOUT — THE $1,000,000 ATOMIC TRANSACTION
    // ─────────────────────────────────────────────────────────
    } elseif ($action === 'checkout') {

        // ── Step 1: Fetch all cart items with live product/shop data ──
        // Prices are read FROM THE DATABASE, never from $_POST.
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

            // ── Step 2: Group items by shop_id ───────────────────────
            $grouped = [];
            foreach ($cart_items as $item) {
                $sid = (int)$item['shop_id'];
                $grouped[$sid][] = $item;
            }

            // ── Step 3: BEGIN ATOMIC TRANSACTION ─────────────────────
            $conn->begin_transaction();

            try {
                // ── Step 4: FOR UPDATE lock + stock validation ────────
                // We lock EVERY product row first, before writing anything.
                // This guarantees that no other transaction can modify
                // stock for these products while we're processing.
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
                        // Stock depleted since customer added to cart.
                        $avail_msg = $live_qty === 0
                            ? 'is now out of stock'
                            : "only has $live_qty unit(s) available";
                        throw new Exception(
                            "\"" . $item['product_name'] . "\" $avail_msg. " .
                            "Please update your cart and try again."
                        );
                    }
                }

                // ── Step 5: CREATE ONE ORDER PER SHOP ────────────────
                $created_order_ids = [];

                foreach ($grouped as $shop_id => $items) {

                    // Calculate order financials for this shop's items.
                    $subtotal = 0.0;
                    foreach ($items as $item) {
                        $subtotal += (float)$item['unit_price'] * (int)$item['requested_qty'];
                    }
                    $tax   = round($subtotal * 0.08, 2);
                    $total = round($subtotal + $tax, 2);
                    $subtotal = round($subtotal, 2);

                    // INSERT the order row.
                    $stmt = $conn->prepare("
                        INSERT INTO orders (customer_id, shop_id, subtotal, tax, total, status)
                        VALUES (?, ?, ?, ?, ?, 'Pending')
                    ");
                    $stmt->bind_param('iiddd', $user_id, $shop_id, $subtotal, $tax, $total);
                    $stmt->execute();
                    $order_id = $conn->insert_id;
                    $stmt->close();

                    $created_order_ids[] = $order_id;

                    // ── Step 6: INSERT order_line rows (SNAPSHOT) ─────
                    // Snapshot product_name and unit_price so the order
                    // record remains accurate even if the product is later
                    // edited or deleted.
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

                        // ── Step 7: Decrement product stock ──────────
                        $stmt = $conn->prepare(
                            "UPDATE products SET quantity = quantity - ? WHERE id = ?"
                        );
                        $stmt->bind_param('ii', $qty, $pid);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                // ── Step 8: DELETE the entire cart ───────────────────
                $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->close();

                // ── Step 9: COMMIT ────────────────────────────────────
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

// ── Read flash messages (PRG pattern) ────────────────────────
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

// ── Fetch current cart for display ────────────────────────────
// LEFT JOIN shops to detect if a shop went inactive since item was added.
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

// Group for display and calculate grand total
$cart_by_shop   = [];
$grand_total    = 0.0;
$grand_subtotal = 0.0;
$grand_tax      = 0.0;
$has_issue      = false; // Flag if any item has stock issues

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

    // Flag issue: out of stock or shop inactive
    if ($row['stock_qty'] < $row['cart_qty'] || $row['shop_status'] !== 'active') {
        $has_issue = true;
    }
}

$grand_tax   = round($grand_subtotal * 0.08, 2);
$grand_total = round($grand_subtotal + $grand_tax, 2);

// Fetch user's default address for checkout display
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

        <!-- LEFT: Cart Items (grouped by shop) -->
        <div>

        <?php foreach ($cart_by_shop as $sid => $group): ?>
        <div class="card" style="margin-bottom:1.2rem;">

            <!-- Shop header -->
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

                <!-- Thumbnail -->
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

                <!-- Name + stock warning -->
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

                <!-- Quantity update form -->
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

                <!-- Line total -->
                <div style="font-weight:700;color:var(--gold);text-align:right;flex-shrink:0;min-width:80px;">
                    <?= number_format($line_total, 2) ?> SAR
                </div>

                <!-- Remove button -->
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

            <!-- Shop subtotal -->
            <div style="text-align:right;padding-top:.6rem;font-size:.88rem;color:var(--text-muted);">
                Shop subtotal: <strong style="color:var(--text-primary);">
                    <?= number_format($group['subtotal'], 2) ?> SAR
                </strong>
            </div>

        </div>
        <?php endforeach; ?>

        </div><!-- /cart items -->

        <!-- RIGHT: Order Summary + Checkout -->
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

                <!-- Delivery address preview -->
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

                <!-- Checkout Form -->
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

    </div><!-- /cart-layout -->
    <?php endif; ?>

</div>

<style>
@media (max-width:750px) {
    .cart-layout { grid-template-columns: 1fr !important; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
