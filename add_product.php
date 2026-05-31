<?php
include "includes/connect.php";
include "includes/session.php";

// Only managers and admins can add products
if ($_SESSION["role"] !== "manager" && $_SESSION["role"] !== "admin") {
    header("Location: customer_dashboard.php");
    exit();
}

$msg = "";

if (isset($_POST["submit"])) {
    $p_name  = trim($_POST["p_name"]);
    $desc    = trim($_POST["desc"]);
    $price   = (float)$_POST["price"];
    $stock   = (int)$_POST["stock"];
    $shop_id = (int)$_POST["shop_id"];

    if (empty($p_name) || $price <= 0 || $shop_id <= 0) {
        $msg = "error:Please fill in all required fields correctly.";
    } else {
        $safe_name = mysqli_real_escape_string($conn, $p_name);
        $safe_desc = mysqli_real_escape_string($conn, $desc);

        // Insert product
        mysqli_query($conn,
            "INSERT INTO products (name, description, price, stock, shop_id)
             VALUES ('$safe_name', '$safe_desc', '$price', '$stock', '$shop_id')");

        // Handle uploaded images
        if (!empty($_FILES["images"]["name"][0])) {
            $pictures = $_FILES["images"];
            for ($i = 0; $i < count($pictures["name"]); $i++) {
                $file_name = basename($pictures["name"][$i]);
                $file_tmp  = $pictures["tmp_name"][$i];
                $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed   = ["jpg", "jpeg", "png", "gif", "webp"];

                if (in_array($ext, $allowed) && is_uploaded_file($file_tmp)) {
                    $destination = "uploads/" . time() . "_" . $file_name;
                    if (move_uploaded_file($file_tmp, $destination)) {
                        $safe_dest = mysqli_real_escape_string($conn, $destination);
                        mysqli_query($conn,
                            "INSERT INTO pictures (product_name, path)
                             VALUES ('$safe_name', '$safe_dest')");
                    }
                }
            }
        }

        $msg = "success:Product \"$p_name\" added successfully!";
    }
}

// Get shops for dropdown
$shops_res = mysqli_query($conn, "SELECT * FROM shops ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Jeem Mall</title>
    <link rel="stylesheet" href="css/customer.css">
</head>
<body>

<?php
$active_page = "";
include "includes/navbar.php";
?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">Add New Product</h1>
    </div>

    <?php if ($msg !== ""):
          [$type, $text] = explode(":", $msg, 2);
          $is_success    = ($type === "success");
          $bg_color      = $is_success ? "rgba(16,185,129,0.1)" : "rgba(239,68,68,0.1)";
          $txt_color     = $is_success ? "#10b981" : "#ef4444";
    ?>
    <div style="background:<?php echo $bg_color; ?>;color:<?php echo $txt_color; ?>;
                padding:1rem 1.5rem;border-radius:0.5rem;margin-bottom:1.5rem;font-weight:500;">
        <?php echo htmlspecialchars($text); ?>
    </div>
    <?php endif; ?>

    <div style="max-width:600px;">
        <div class="account-card" style="max-width:100%;">
            <form action="add_product.php" method="post" enctype="multipart/form-data">

                <div class="form-group">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="p_name" class="form-input"
                           placeholder="e.g. Wireless Headphones" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="desc" class="form-input" rows="3"
                              placeholder="Describe the product..."></textarea>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Price (SAR) *</label>
                        <input type="number" name="price" class="form-input"
                               step="0.01" min="0.01" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" name="stock" class="form-input"
                               min="0" value="0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Shop *</label>
                    <select name="shop_id" class="form-input" required>
                        <option value="">— Select a Shop —</option>
                        <?php if ($shops_res):
                              while ($shop = mysqli_fetch_assoc($shops_res)): ?>
                        <option value="<?php echo (int)$shop['id']; ?>">
                            <?php echo htmlspecialchars($shop['name']); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Product Images</label>
                    <input type="file" name="images[]" class="form-input"
                           multiple accept="image/*">
                    <small style="color:var(--text-muted);">
                        Accepted: JPG, PNG, GIF, WebP. You can select multiple.
                    </small>
                </div>

                <button type="submit" name="submit" class="btn btn-primary"
                        style="width:100%;padding:0.875rem;">
                    ➕ Add Product
                </button>
            </form>
        </div>
    </div>
</main>

<script src="js/customer.js"></script>
</body>
</html>
