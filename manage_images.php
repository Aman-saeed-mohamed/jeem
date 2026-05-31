<?php
include "includes/connect.php";
include "includes/auth.php";
require_role(['manager', 'admin']);

// Auto-create pictures table if it doesn't exist
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS pictures (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT DEFAULT NULL,
    path       VARCHAR(500) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$manager_id = (int)$_SESSION['id'];
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($product_id <= 0) { header("Location: manager_products.php"); exit(); }

// Verify this product belongs to the manager's shop
$shop_res = mysqli_query($conn, "SELECT id FROM shops WHERE manager_id='$manager_id'");
if (mysqli_num_rows($shop_res) === 0) { header("Location: customer_dashboard.php"); exit(); }
$shop    = mysqli_fetch_assoc($shop_res);
$shop_id = (int)$shop['id'];

$verify = mysqli_query($conn,
    "SELECT * FROM products WHERE id='$product_id' AND shop_id='$shop_id'");
if (mysqli_num_rows($verify) === 0) { header("Location: manager_products.php"); exit(); }
$product = mysqli_fetch_assoc($verify);

$msg     = "";
$allowed = ['jpg','jpeg','png','gif','webp'];

// Upload new images
if (isset($_POST['upload'])) {
    $count = 0;
    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['name'] as $idx => $fname) {
            if (empty($fname)) continue;
            $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) continue;
            $filename = time() . "_" . $idx . "_" . basename($fname);
            $dest     = "uploads/" . $filename;
            if (move_uploaded_file($_FILES['images']['tmp_name'][$idx], $dest)) {
                $safe_path = mysqli_real_escape_string($conn, $dest);
                mysqli_query($conn,
                    "INSERT INTO pictures (product_id, path) VALUES ('$product_id','$safe_path')");
                $count++;
            }
        }
    }
    // Set first image as main product image if product has none
    if ($count > 0 && empty($product['image'])) {
        $first = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT path FROM pictures WHERE product_id='$product_id' ORDER BY id ASC LIMIT 1"));
        if ($first) {
            $safe_p = mysqli_real_escape_string($conn, $first['path']);
            mysqli_query($conn, "UPDATE products SET image='$safe_p' WHERE id='$product_id'");
        }
    }
    $msg = "success:$count image(s) uploaded.";
}

// Delete a single image
if (isset($_POST['delete_image'])) {
    $img_id = (int)$_POST['image_id'];
    // Get path before deleting
    $img_res = mysqli_query($conn,
        "SELECT path FROM pictures WHERE id='$img_id' AND product_id='$product_id'");
    if (mysqli_num_rows($img_res) === 1) {
        $img     = mysqli_fetch_assoc($img_res);
        $path    = $img['path'];
        mysqli_query($conn, "DELETE FROM pictures WHERE id='$img_id'");
        // Delete file if local
        if (file_exists($path)) @unlink($path);

        // If this was the main product image, update to next available
        if ($product['image'] === $path) {
            $next = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT path FROM pictures WHERE product_id='$product_id' ORDER BY id ASC LIMIT 1"));
            $new_img = $next ? mysqli_real_escape_string($conn, $next['path']) : '';
            mysqli_query($conn, "UPDATE products SET image='$new_img' WHERE id='$product_id'");
        }
        $msg = "success:Image deleted.";
    }
    header("Location: manage_images.php?product_id=$product_id");
    exit();
}

// Set an image as the main product image
if (isset($_POST['set_main'])) {
    $img_id  = (int)$_POST['image_id'];
    $img_res = mysqli_query($conn,
        "SELECT path FROM pictures WHERE id='$img_id' AND product_id='$product_id'");
    if (mysqli_num_rows($img_res) === 1) {
        $img      = mysqli_fetch_assoc($img_res);
        $safe_path = mysqli_real_escape_string($conn, $img['path']);
        mysqli_query($conn, "UPDATE products SET image='$safe_path' WHERE id='$product_id'");
        $msg = "success:Main image updated.";
    }
    header("Location: manage_images.php?product_id=$product_id");
    exit();
}

// Reload product after possible changes
$product_res = mysqli_query($conn, "SELECT * FROM products WHERE id='$product_id'");
$product     = mysqli_fetch_assoc($product_res);

// Get all images
$images_res = mysqli_query($conn,
    "SELECT * FROM pictures WHERE product_id='$product_id' ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Images - <?php echo htmlspecialchars($product['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .img-card { position: relative; }
        .img-card img { width:100%; height:160px; object-fit:cover; border-radius:8px; }
        .main-badge {
            position:absolute; top:6px; left:6px;
            background:#198754; color:#fff; font-size:11px;
            padding:2px 8px; border-radius:20px;
        }
    </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="manager_dashboard.php">🏪 Manager</a>
        <a href="manager_products.php" class="btn btn-outline-light btn-sm">&larr; Back to Products</a>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <h3 class="mb-1">🖼 Manage Images</h3>
    <p class="text-muted mb-4">Product: <strong><?php echo htmlspecialchars($product['name']); ?></strong></p>

    <?php if ($msg):
          [$type, $text] = explode(':', $msg, 2);
          $cls = $type === 'success' ? 'success' : 'danger'; ?>
    <div class="alert alert-<?php echo $cls; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($text); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Upload Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-bold">📤 Upload New Images</div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <input type="file" name="images[]" class="form-control" accept="image/*" multiple required>
                    <div class="form-text">Select one or more images (JPG, PNG, GIF, WebP).</div>
                </div>
                <button type="submit" name="upload" class="btn btn-primary">
                    📤 Upload Images
                </button>
            </form>
        </div>
    </div>

    <!-- Images Grid -->
    <h5 class="mb-3">Current Images (<?php echo mysqli_num_rows($images_res); ?>)</h5>

    <?php if (mysqli_num_rows($images_res) > 0): ?>
    <div class="row g-3">
        <?php while ($img = mysqli_fetch_assoc($images_res)):
              $is_main = ($img['path'] === $product['image']); ?>
        <div class="col-md-3">
            <div class="card shadow-sm img-card">
                <?php if ($is_main): ?>
                <span class="main-badge">⭐ Main</span>
                <?php endif; ?>
                <img src="<?php echo htmlspecialchars($img['path']); ?>" alt="Product Image">
                <div class="card-body p-2 d-flex gap-1">
                    <?php if (!$is_main): ?>
                    <form method="post" class="flex-fill">
                        <input type="hidden" name="image_id" value="<?php echo (int)$img['id']; ?>">
                        <button type="submit" name="set_main" class="btn btn-success btn-sm w-100">
                            Set Main
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="btn btn-success btn-sm w-50 disabled">✅ Main</span>
                    <?php endif; ?>
                    <form method="post" class="flex-fill"
                          onsubmit="return confirm('Delete this image?');">
                        <input type="hidden" name="image_id" value="<?php echo (int)$img['id']; ?>">
                        <button type="submit" name="delete_image" class="btn btn-danger btn-sm w-100">
                            🗑
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php else: ?>
    <p class="text-muted">No images uploaded yet.</p>
    <?php endif; ?>

    <div class="mt-4">
        <a href="product.php?product_id=<?php echo $product_id; ?>"
           class="btn btn-outline-primary">👁 Preview Product Page</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
