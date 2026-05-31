<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_role('manager');

$stmt = $conn->prepare(
    "SELECT id, name, type, location, status FROM shops WHERE manager_id = ? LIMIT 1"
);
$user_id = current_user_id();
$stmt->bind_param('i', $user_id);
$stmt->execute();
$my_shop = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$my_shop) {
    $page_title = 'Products';
    $active_nav = 'mgr_products';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="page-content" style="padding:2rem;">
            <div class="alert alert-info">⚠️ No shop assigned. Please contact the Admin.</div>
          </div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$shop_id = (int)$my_shop['id'];

$upload_dir = __DIR__ . '/../uploads/products/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
$allowed_exts  = ['jpg', 'jpeg', 'png', 'webp'];

$message       = '';
$message_type  = '';
$upload_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    

    

    

    if ($action === 'add_product') {

        $name        = trim($_POST['name']        ?? '');
        $description = trim($_POST['description'] ?? '');
        $price       = (float)($_POST['price']    ?? 0);
        $quantity    = (int)($_POST['quantity']   ?? 0);

        

        if (empty($name)) {
            $message = 'Product name is required.';
            $message_type = 'error';
        } elseif ($price <= 0) {
            $message = 'Price must be greater than zero.';
            $message_type = 'error';
        } elseif ($quantity < 0) {
            $message = 'Quantity cannot be negative.';
            $message_type = 'error';
        } else {

            $conn->begin_transaction();
            try {
                

                $stmt = $conn->prepare(
                    "INSERT INTO products (shop_id, name, description, price, quantity)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('issdi', $shop_id, $name, $description, $price, $quantity);
                $stmt->execute();
                $new_product_id = $conn->insert_id;
                $stmt->close();

                

                $sort_order = 0;
                if (!empty($_FILES['images']['name'][0])) {
                    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {

                        

                        if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) {
                            $upload_errors[] = "File #{$key}: Upload error code " . $_FILES['images']['error'][$key];
                            continue;
                        }

                        $original_name = $_FILES['images']['name'][$key];
                        $ext           = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                        

                        if (!in_array($ext, $allowed_exts, true)) {
                            $upload_errors[] = "'$original_name': Invalid extension (allowed: jpg, png, webp).";
                            continue;
                        }

                        

                        

                        

                        

                        $image_info = @getimagesize($tmp_name);
                        if ($image_info === false || !in_array($image_info['mime'], $allowed_mimes, true)) {
                            $upload_errors[] = "'$original_name': File is not a valid JPEG, PNG, or WebP image.";
                            continue;
                        }

                        

                        

                        $safe_filename = 'prod_' . $new_product_id . '_' . uniqid('', true) . '.' . $ext;
                        $dest_path     = $upload_dir . $safe_filename;

                        if (!move_uploaded_file($tmp_name, $dest_path)) {
                            $upload_errors[] = "'$original_name': Could not save file to server.";
                            continue;
                        }

                        

                        $stmt = $conn->prepare(
                            "INSERT INTO pictures (product_id, filename, sort_order) VALUES (?, ?, ?)"
                        );
                        $stmt->bind_param('isi', $new_product_id, $safe_filename, $sort_order);
                        $stmt->execute();
                        $stmt->close();
                        $sort_order++;
                    }
                }

                $conn->commit();

                $msg_extra = !empty($upload_errors)
                    ? ' (Some images were skipped: ' . implode('; ', $upload_errors) . ')'
                    : '';

                

                $_SESSION['flash_success'] = "Product '" . htmlspecialchars($name, ENT_QUOTES) . "' added.$msg_extra";
                header('Location: ' . BASE_URL . '/manager/manager_products.php');
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                $message      = 'Failed to add product: ' . $e->getMessage();
                $message_type = 'error';
            }
        }

    

    

    

    } elseif ($action === 'edit_product') {

        $product_id  = (int)($_POST['product_id'] ?? 0);
        $name        = trim($_POST['name']         ?? '');
        $description = trim($_POST['description']  ?? '');
        $price       = (float)($_POST['price']     ?? 0);
        $quantity    = (int)($_POST['quantity']    ?? 0);

        if ($product_id < 1 || empty($name) || $price <= 0 || $quantity < 0) {
            $message      = 'Invalid input. Please check all fields.';
            $message_type = 'error';
        } else {

            

            $stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND shop_id = ? LIMIT 1");
            $stmt->bind_param('ii', $product_id, $shop_id);
            $stmt->execute();
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();

            if (!$exists) {
                $message      = 'Product not found or access denied.';
                $message_type = 'error';
            } else {

                

                $stmt = $conn->prepare(
                    "UPDATE products SET name=?, description=?, price=?, quantity=?
                     WHERE id=? AND shop_id=?"
                );
                $stmt->bind_param('ssdiii', $name, $description, $price, $quantity, $product_id, $shop_id);
                $stmt->execute();
                $stmt->close();

                

                $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_order FROM pictures WHERE product_id = ?");
                $stmt->bind_param('i', $product_id);
                $stmt->execute();
                $sort_order = (int)$stmt->get_result()->fetch_assoc()['next_order'];
                $stmt->close();

                

                if (!empty($_FILES['images']['name'][0])) {
                    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) continue;

                        $original_name = $_FILES['images']['name'][$key];
                        $ext           = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                        if (!in_array($ext, $allowed_exts, true)) { continue; }

                        $image_info = @getimagesize($tmp_name);
                        if ($image_info === false || !in_array($image_info['mime'], $allowed_mimes, true)) { continue; }

                        $safe_filename = 'prod_' . $product_id . '_' . uniqid('', true) . '.' . $ext;
                        if (!move_uploaded_file($tmp_name, $upload_dir . $safe_filename)) { continue; }

                        $stmt = $conn->prepare("INSERT INTO pictures (product_id, filename, sort_order) VALUES (?, ?, ?)");
                        $stmt->bind_param('isi', $product_id, $safe_filename, $sort_order);
                        $stmt->execute();
                        $stmt->close();
                        $sort_order++;
                    }
                }

                $_SESSION['flash_success'] = "Product updated successfully.";
                header('Location: ' . BASE_URL . '/manager/manager_products.php');
                exit;
            }
        }

    

    

    

    } elseif ($action === 'delete_image') {

        $image_id   = (int)($_POST['image_id']   ?? 0);
        $product_id = (int)($_POST['product_id'] ?? 0);

        
        $stmt = $conn->prepare("
            SELECT pi.filename
            FROM   pictures pi
            JOIN   products p ON p.id = pi.product_id
            WHERE  pi.id = ? AND pi.product_id = ? AND p.shop_id = ?
            LIMIT  1
        ");
        $stmt->bind_param('iii', $image_id, $product_id, $shop_id);
        $stmt->execute();
        $img = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($img) {
            

            $file_path = $upload_dir . $img['filename'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            

            $stmt = $conn->prepare("DELETE FROM pictures WHERE id = ?");
            $stmt->bind_param('i', $image_id);
            $stmt->execute();
            $stmt->close();

            $_SESSION['flash_success'] = 'Image deleted.';
        } else {
            $_SESSION['flash_error'] = 'Image not found or access denied.';
        }

        header('Location: ' . BASE_URL . '/manager/manager_products.php?view=edit&id=' . $product_id);
        exit;

    

    

    

    } elseif ($action === 'delete_product') {

        $product_id = (int)($_POST['product_id'] ?? 0);

        

        $stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND shop_id = ? LIMIT 1");
        $stmt->bind_param('ii', $product_id, $shop_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $stmt->close();
            $_SESSION['flash_error'] = 'Product not found or access denied.';
            header('Location: ' . BASE_URL . '/manager/manager_products.php');
            exit;
        }
        $stmt->close();

        

        $stmt = $conn->prepare("SELECT filename FROM pictures WHERE product_id = ?");
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        

        foreach ($images as $img) {
            $file_path = $upload_dir . $img['filename'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        

        

        $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND shop_id = ?");
        $stmt->bind_param('ii', $product_id, $shop_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['flash_success'] = 'Product and all its images deleted successfully.';
        header('Location: ' . BASE_URL . '/manager/manager_products.php');
        exit;
    }
}

if (!empty($_SESSION['flash_success'])) {
    $message      = $_SESSION['flash_success'];
    $message_type = 'success';
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $message      = $_SESSION['flash_error'];
    $message_type = 'error';
    unset($_SESSION['flash_error']);
}

$current_view = $_GET['view'] ?? 'list';

$edit_product = null;
$edit_images  = [];
if ($current_view === 'edit') {
    $edit_id = (int)($_GET['id'] ?? 0);

    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND shop_id = ? LIMIT 1");
    $stmt->bind_param('ii', $edit_id, $shop_id);
    $stmt->execute();
    $edit_product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$edit_product) {
        $_SESSION['flash_error'] = 'Product not found.';
        header('Location: ' . BASE_URL . '/manager/manager_products.php');
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM pictures WHERE product_id = ? ORDER BY sort_order ASC");
    $stmt->bind_param('i', $edit_id);
    $stmt->execute();
    $edit_images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$products = [];
if ($current_view === 'list') {
    $stmt = $conn->prepare("
        SELECT  p.*,
                (SELECT filename FROM pictures
                 WHERE  product_id = p.id AND sort_order = 0
                 LIMIT  1) AS main_image
        FROM    products p
        WHERE   p.shop_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->bind_param('i', $shop_id);
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$page_title = 'Products';
$active_nav = 'mgr_products';
include __DIR__ . '/../includes/header.php';
?>

<div class="sidebar-layout">
    <?php include __DIR__ . '/../includes/manager_sidebar.php'; ?>

    <div class="page-content">

        
        <?php if ($current_view === 'edit' && $edit_product): ?>

        <div class="page-header d-flex justify-between align-center" style="flex-wrap:wrap;gap:1rem;">
            <div>
                <h1>✏️ Edit Product</h1>
                <p>Editing: <strong class="text-gold"><?= e($edit_product['name']) ?></strong></p>
            </div>
            <a href="<?= BASE_URL ?>/manager/manager_products.php" class="btn btn-secondary">
                ← Back to Products
            </a>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'error' ? 'error' : 'success' ?>">
            <?= e($message) ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action"      value="edit_product">
                <input type="hidden" name="product_id"  value="<?= $edit_product['id'] ?>">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;">
                    <div class="form-group">
                        <label class="form-label" for="ep-name">Product Name</label>
                        <input type="text" id="ep-name" name="name" class="form-control"
                               value="<?= e($edit_product['name']) ?>" required maxlength="200">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="ep-price">Price (SAR)</label>
                        <input type="number" id="ep-price" name="price" class="form-control"
                               value="<?= $edit_product['price'] ?>" step="0.01" min="0.01" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="ep-desc">Description</label>
                    <textarea id="ep-desc" name="description" class="form-control" rows="3"><?= e($edit_product['description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="ep-qty">Stock Quantity</label>
                    <input type="number" id="ep-qty" name="quantity" class="form-control"
                           value="<?= $edit_product['quantity'] ?>" min="0" required style="max-width:200px;">
                </div>

                
                <?php if (!empty($edit_images)): ?>
                <div class="form-group">
                    <label class="form-label">Current Images
                        <small class="text-muted">(First image = main thumbnail)</small>
                    </label>
                    <div style="display:flex;flex-wrap:wrap;gap:.75rem;margin-top:.5rem;">
                        <?php foreach ($edit_images as $img): ?>
                        <div style="position:relative;text-align:center;">
                            <img src="<?= BASE_URL ?>/uploads/products/<?= e($img['filename']) ?>"
                                 alt="Product image"
                                 style="width:100px;height:100px;object-fit:cover;border-radius:var(--radius-sm);
                                        border:2px solid <?= $img['sort_order'] === 0 ? 'var(--gold)' : 'var(--border-subtle)' ?>;">
                            <?php if ($img['sort_order'] === 0): ?>
                            <div style="font-size:.65rem;color:var(--gold);font-weight:700;margin-top:.2rem;">MAIN</div>
                            <?php endif; ?>
                            <form method="POST" action="" style="margin-top:.3rem;">
                                <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action"      value="delete_image">
                                <input type="hidden" name="image_id"    value="<?= $img['id'] ?>">
                                <input type="hidden" name="product_id"  value="<?= $edit_product['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"
                                        data-confirm="Delete this image?" style="font-size:.7rem;padding:.2rem .5rem;">
                                    🗑️
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                
                <div class="form-group">
                    <label class="form-label" for="ep-imgs">Add More Images
                        <small class="text-muted">(JPG, PNG, WebP)</small>
                    </label>
                    <input type="file" id="ep-imgs" name="images[]" class="form-control"
                           multiple accept=".jpg,.jpeg,.png,.webp">
                </div>

                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            </form>
        </div>

        <?php else: ?>
        

        <div class="page-header d-flex justify-between align-center" style="flex-wrap:wrap;gap:1rem;">
            <div>
                <h1>📦 Products</h1>
                <p>Manage all products for <strong class="text-gold"><?= e($my_shop['name']) ?></strong>.</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                + Add Product
            </button>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'error' ? 'error' : 'success' ?>">
            <?= e($message) ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-icon">📦</div>
                                <p>No products yet. Click <strong>+ Add Product</strong> to get started.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($products as $prod): ?>
                    <tr>
                        <td>
                            <?php if ($prod['main_image']): ?>
                                <img src="<?= BASE_URL ?>/uploads/products/<?= e($prod['main_image']) ?>"
                                     alt="<?= e($prod['name']) ?>"
                                     style="width:54px;height:54px;object-fit:cover;border-radius:var(--radius-sm);">
                            <?php else: ?>
                                <div style="width:54px;height:54px;background:var(--bg-elevated);border-radius:var(--radius-sm);
                                            display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:var(--text-muted);">
                                    📷
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <strong><?= e($prod['name']) ?></strong>
                            <?php if ($prod['description']): ?>
                            <div style="font-size:.78rem;color:var(--text-muted);margin-top:.15rem;
                                        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;">
                                <?= e($prod['description']) ?>
                            </div>
                            <?php endif; ?>
                        </td>

                        <td><?= number_format((float)$prod['price'], 2) ?> SAR</td>

                        <td>
                            <?php if ($prod['quantity'] === 0): ?>
                                <span class="badge badge-inactive">Out of Stock</span>
                            <?php elseif ($prod['quantity'] <= 5): ?>
                                <span style="color:var(--status-pending);font-weight:600;"><?= $prod['quantity'] ?></span>
                            <?php else: ?>
                                <?= $prod['quantity'] ?>
                            <?php endif; ?>
                        </td>

                        <td><?= date('M j, Y', strtotime($prod['created_at'])) ?></td>

                        <td style="white-space:nowrap;">
                            
                            <a href="<?= BASE_URL ?>/manager/manager_products.php?view=edit&id=<?= $prod['id'] ?>"
                               class="btn btn-secondary btn-sm">✏️ Edit</a>

                            
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token"   value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action"       value="delete_product">
                                <input type="hidden" name="product_id"   value="<?= $prod['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"
                                        data-confirm="Delete '<?= e($prod['name']) ?>'? All images will be permanently removed.">
                                    🗑️ Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="modal fade" id="addProductModal" tabindex="-1"
             aria-labelledby="addProductLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content"
                     style="background:var(--bg-card);border:1px solid var(--border-subtle);">

                    <div class="modal-header" style="border-color:var(--border-subtle);">
                        <h5 class="modal-title" id="addProductLabel">+ Add New Product</h5>
                        <button type="button" class="btn-close btn-close-white"
                                data-bs-dismiss="modal"></button>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action"     value="add_product">

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                                <div class="form-group">
                                    <label class="form-label" for="ap-name">Product Name</label>
                                    <input type="text" id="ap-name" name="name" class="form-control"
                                           placeholder="e.g. Iced Latte" required maxlength="200">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="ap-price">Price (SAR)</label>
                                    <input type="number" id="ap-price" name="price" class="form-control"
                                           placeholder="0.00" step="0.01" min="0.01" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="ap-desc">Description</label>
                                <textarea id="ap-desc" name="description" class="form-control"
                                          rows="2" placeholder="Optional description..."></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="ap-qty">Initial Stock Quantity</label>
                                <input type="number" id="ap-qty" name="quantity" class="form-control"
                                       placeholder="0" min="0" required value="0">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="ap-imgs">
                                    Product Images
                                    <small class="text-muted">(JPG, PNG, WebP — first image becomes the main thumbnail)</small>
                                </label>
                                <input type="file" id="ap-imgs" name="images[]" class="form-control"
                                       multiple accept=".jpg,.jpeg,.png,.webp">
                            </div>
                        </div>

                        <div class="modal-footer" style="border-color:var(--border-subtle);">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Product</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
        

        <?php endif; 
?>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
