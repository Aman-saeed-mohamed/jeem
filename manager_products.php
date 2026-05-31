<?php
include "includes/connect.php";
include "includes/auth.php";
require_role(['manager', 'admin']);

// Auto-create pictures table if it doesn't exist (safe to run every time)
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS pictures (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT DEFAULT NULL,
    path       VARCHAR(500) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$manager_id = (int)$_SESSION['id'];
$shop_res   = mysqli_query($conn, "SELECT * FROM shops WHERE manager_id='$manager_id'");
if (mysqli_num_rows($shop_res) === 0) { header("Location: customer_dashboard.php"); exit(); }
$shop    = mysqli_fetch_assoc($shop_res);
$shop_id = (int)$shop['id'];

$msg = "";

// Add Product
if (isset($_POST['add_product'])) {
    $name  = trim($_POST['name']);
    $desc  = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $qty   = (int)$_POST['quantity'];

    if (empty($name) || $price <= 0) {
        $msg = "error:Product name and price are required.";
    } else {
        $safe_name  = mysqli_real_escape_string($conn, $name);
        $safe_desc  = mysqli_real_escape_string($conn, $desc);
        $first_image = "";
        $allowed     = ['jpg','jpeg','png','gif','webp'];

        // Collect all uploaded images
        $uploaded_paths = [];
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['name'] as $idx => $fname) {
                if (empty($fname)) continue;
                $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) continue;
                $filename = time() . "_" . $idx . "_" . basename($fname);
                $dest     = "uploads/" . $filename;
                if (move_uploaded_file($_FILES['images']['tmp_name'][$idx], $dest)) {
                    $uploaded_paths[] = $dest;
                    if ($first_image === "") $first_image = $dest; // first = main image
                }
            }
        }

        $safe_img = mysqli_real_escape_string($conn, $first_image);
        mysqli_query($conn,
            "INSERT INTO products (name, description, price, quantity, shop_id, image)
             VALUES ('$safe_name','$safe_desc','$price','$qty','$shop_id','$safe_img')");
        $new_product_id = mysqli_insert_id($conn);

        // Save all images to pictures table
        foreach ($uploaded_paths as $path) {
            $safe_path = mysqli_real_escape_string($conn, $path);
            mysqli_query($conn,
                "INSERT INTO pictures (product_id, path) VALUES ('$new_product_id','$safe_path')");
        }

        $msg = "success:Product added with " . count($uploaded_paths) . " image(s)!";
    }
}

// Delete Product
if (isset($_POST['delete_product'])) {
    $pid = (int)$_POST['product_id'];
    // Verify product belongs to this shop
    $v = mysqli_query($conn, "SELECT id FROM products WHERE id='$pid' AND shop_id='$shop_id'");
    if (mysqli_num_rows($v) === 1) {
        mysqli_query($conn, "DELETE FROM products WHERE id='$pid'");
    }
    header("Location: manager_products.php");
    exit();
}

// Update Product
if (isset($_POST['update_product'])) {
    $pid   = (int)$_POST['product_id'];
    $name  = trim($_POST['name']);
    $desc  = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $qty   = (int)$_POST['quantity'];

    $v = mysqli_query($conn, "SELECT id FROM products WHERE id='$pid' AND shop_id='$shop_id'");
    if (mysqli_num_rows($v) === 1 && !empty($name) && $price > 0) {
        $safe_name = mysqli_real_escape_string($conn, $name);
        $safe_desc = mysqli_real_escape_string($conn, $desc);
        mysqli_query($conn,
            "UPDATE products SET name='$safe_name', description='$safe_desc',
             price='$price', quantity='$qty' WHERE id='$pid'");
        $msg = "success:Product updated successfully!";
    }
}

$products = mysqli_query($conn, "SELECT * FROM products WHERE shop_id='$shop_id' ORDER BY id ASC");

$edit_product = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $er  = mysqli_query($conn, "SELECT * FROM products WHERE id='$eid' AND shop_id='$shop_id'");
    if (mysqli_num_rows($er) === 1) $edit_product = mysqli_fetch_assoc($er);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - <?php echo htmlspecialchars($shop['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="manager_dashboard.php">🏪 <?php echo htmlspecialchars($shop['name']); ?></a>
        <div class="navbar-nav ms-auto d-flex flex-row gap-3 align-items-center">
            <a href="manager_dashboard.php"  class="nav-link text-white">Home</a>
            <a href="manager_products.php"   class="nav-link text-white active">Products</a>
            <a href="manager_orders.php"     class="nav-link text-white">Orders</a>
            <a href="manager_deliveries.php" class="nav-link text-white">Deliveries</a>
            <a href="customer_dashboard.php" class="btn btn-outline-warning btn-sm">🛍 Shopping Mode</a>
            <a href="logout.php"             class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Products</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            ➕ Add Product
        </button>
    </div>

    <?php if ($msg):
          [$type, $text] = explode(':', $msg, 2);
          $cls = $type === 'success' ? 'success' : 'danger'; ?>
    <div class="alert alert-<?php echo $cls; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($text); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Edit Form (shows if ?edit=ID) -->
    <?php if ($edit_product): ?>
    <div class="card shadow-sm mb-4 border-warning">
        <div class="card-header bg-warning text-dark fw-bold">✏️ Edit Product</div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="product_id" value="<?php echo (int)$edit_product['id']; ?>">
                <div class="row g-2">
                    <div class="col-md-6">
                        <input type="text" name="name" class="form-control" placeholder="Product Name" required
                               value="<?php echo htmlspecialchars($edit_product['name']); ?>">
                    </div>
                    <div class="col-md-3">
                        <input type="number" name="price" class="form-control" placeholder="Price" step="0.01" min="0.01" required
                               value="<?php echo $edit_product['price']; ?>">
                    </div>
                    <div class="col-md-3">
                        <input type="number" name="quantity" class="form-control" placeholder="Stock" min="0"
                               value="<?php echo $edit_product['quantity']; ?>">
                    </div>
                    <div class="col-12">
                        <textarea name="description" class="form-control" rows="2" placeholder="Description"><?php echo htmlspecialchars($edit_product['description']); ?></textarea>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" name="update_product" class="btn btn-warning">Save Changes</button>
                        <a href="manager_products.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <?php if (mysqli_num_rows($products) > 0):
              while ($p = mysqli_fetch_assoc($products)):
                  $pid = (int)$p['id'];
                  $img_count_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM pictures WHERE product_id='$pid'");
                  $img_count     = (int)mysqli_fetch_assoc($img_count_res)['c'];
        ?>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <?php if (!empty($p['image'])): ?>
                <img src="<?php echo htmlspecialchars($p['image']); ?>" class="card-img-top"
                     style="height:160px;object-fit:cover;" alt="">
                <?php else: ?>
                <div class="bg-light d-flex align-items-center justify-content-center"
                     style="height:160px;color:#aaa;">No Image</div>
                <?php endif; ?>
                <div class="card-body">
                    <h6 class="card-title"><?php echo htmlspecialchars($p['name']); ?></h6>
                    <p class="text-muted small mb-1"><?php echo htmlspecialchars($p['description']); ?></p>
                    <p class="fw-bold text-success mb-1"><?php echo number_format($p['price'],2); ?> SAR</p>
                    <p class="text-muted small mb-1">Stock: <?php echo (int)$p['quantity']; ?></p>
                    <small class="text-muted">🖼 <?php echo $img_count; ?> image(s)</small>
                </div>
                <div class="card-footer d-flex gap-2 flex-wrap">
                    <a href="product.php?product_id=<?php echo $pid; ?>"
                       class="btn btn-outline-primary btn-sm">👁 View</a>
                    <a href="manager_products.php?edit=<?php echo $pid; ?>"
                       class="btn btn-warning btn-sm">✏️ Edit</a>
                    <a href="manage_images.php?product_id=<?php echo $pid; ?>"
                       class="btn btn-info btn-sm">🖼 Images</a>
                    <form method="post" onsubmit="return confirm('Delete this product?');">
                        <input type="hidden" name="product_id" value="<?php echo $pid; ?>">
                        <button type="submit" name="delete_product" class="btn btn-danger btn-sm">
                            🗑
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
        <p class="text-muted">No products yet. Add your first product!</p>
        <?php endif; ?>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">➕ Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label">Price (SAR) *</label>
                            <input type="number" name="price" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="col">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="0" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Product Images <small class="text-muted">(Select multiple)</small></label>
                        <input type="file" name="images[]" class="form-control" accept="image/*" multiple>
                        <div class="form-text">You can select multiple images. The first one will be the main image.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
