<?php
include "includes/connect.php";
include "includes/auth.php";
require_role(['customer', 'manager', 'admin']);

// Auto-create pictures table if it doesn't exist
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS pictures (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT DEFAULT NULL,
    path       VARCHAR(500) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($product_id <= 0) { header("Location: customer_dashboard.php"); exit(); }

// Get product + shop info
$res = mysqli_query($conn,
    "SELECT products.*, shops.name AS shop_name, shops.id AS shop_id
     FROM products
     JOIN shops ON products.shop_id = shops.id
     WHERE products.id = '$product_id'");

if (!$res || mysqli_num_rows($res) === 0) {
    header("Location: customer_dashboard.php"); exit();
}
$product = mysqli_fetch_assoc($res);
$shop_id = (int)$product['shop_id'];

// Get all images (from pictures table, fallback to products.image)
$pics_res = mysqli_query($conn,
    "SELECT path FROM pictures WHERE product_id = '$product_id' ORDER BY id ASC");
$images = [];
while ($pic = mysqli_fetch_assoc($pics_res)) {
    $images[] = $pic['path'];
}
if (empty($images) && !empty($product['image'])) {
    $images[] = $product['image'];
}
if (empty($images)) {
    $images[] = "https://via.placeholder.com/600x400?text=No+Image";
}

// Add to Cart
$cart_msg = "";
if (isset($_POST['add_to_cart'])) {
    $qty         = max(1, (int)$_POST['quantity']);
    $customer_id = (int)$_SESSION['id'];

    $existing = mysqli_query($conn,
        "SELECT id, amount FROM cart WHERE customer_id='$customer_id' AND product_id='$product_id'");

    if (mysqli_num_rows($existing) > 0) {
        $row     = mysqli_fetch_assoc($existing);
        $new_qty = (int)$row['amount'] + $qty;
        mysqli_query($conn,
            "UPDATE cart SET amount='$new_qty' WHERE customer_id='$customer_id' AND product_id='$product_id'");
    } else {
        mysqli_query($conn,
            "INSERT INTO cart (customer_id, product_id, amount) VALUES ('$customer_id','$product_id','$qty')");
    }
    $cart_msg = "success";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Jeem Mall</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/customer.css">
    <style>
        .thumbnail-strip img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border: 2px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .thumbnail-strip img.active,
        .thumbnail-strip img:hover {
            border-color: #4f46e5;
        }
        #mainImage {
            width: 100%;
            height: 420px;
            object-fit: cover;
            border-radius: 12px;
            transition: opacity 0.25s ease;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="customer_dashboard.php">🛍 Jeem Mall</a>
        <div class="navbar-nav ms-auto d-flex flex-row gap-3 align-items-center">
            <a href="customer_dashboard.php" class="nav-link text-white">Home</a>
            <a href="cart.php"               class="nav-link text-white">🛒 Cart</a>
            <a href="customer_orders.php"    class="nav-link text-white">Orders</a>
            <a href="account.php"            class="nav-link text-white">Account</a>
            <?php if ($_SESSION['role'] === 'manager'): ?>
            <a href="manager_dashboard.php"  class="btn btn-warning btn-sm">My Shop</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="customer_dashboard.php">Shops</a></li>
            <li class="breadcrumb-item">
                <a href="shop.php?shop_id=<?php echo $shop_id; ?>">
                    <?php echo htmlspecialchars($product['shop_name']); ?>
                </a>
            </li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['name']); ?></li>
        </ol>
    </nav>

    <?php if ($cart_msg === 'success'): ?>
    <div class="alert alert-success alert-dismissible fade show">
        ✅ Added to cart! <a href="cart.php" class="alert-link">View Cart →</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-5">
        <!-- LEFT: Images -->
        <div class="col-md-6">
            <img id="mainImage"
                 src="<?php echo htmlspecialchars($images[0]); ?>"
                 alt="<?php echo htmlspecialchars($product['name']); ?>">

            <?php if (count($images) > 1): ?>
            <div class="thumbnail-strip d-flex gap-2 mt-3 flex-wrap">
                <?php foreach ($images as $i => $img): ?>
                <img src="<?php echo htmlspecialchars($img); ?>"
                     class="<?php echo $i === 0 ? 'active' : ''; ?>"
                     onclick="switchImage(this, '<?php echo htmlspecialchars($img); ?>')"
                     alt="Image <?php echo $i + 1; ?>">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: Details -->
        <div class="col-md-6">
            <h1 class="fw-bold mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>

            <p class="text-muted mb-2">
                🏪 <a href="shop.php?shop_id=<?php echo $shop_id; ?>" class="text-decoration-none">
                    <?php echo htmlspecialchars($product['shop_name']); ?>
                </a>
            </p>

            <div class="mb-3">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?php echo $i <= round($product['rating']) ? '⭐' : '☆'; ?>
                <?php endfor; ?>
                <span class="text-muted small ms-1">(<?php echo number_format($product['rating'],1); ?>/5)</span>
            </div>

            <h2 class="text-success fw-bold mb-3">
                <?php echo number_format($product['price'], 2); ?> SAR
            </h2>

            <p class="mb-4" style="line-height:1.8;color:#555;">
                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
            </p>

            <!-- Stock -->
            <p class="mb-4">
                <?php if ((int)$product['quantity'] > 0): ?>
                    <span class="badge bg-success fs-6">
                        ✅ In Stock (<?php echo (int)$product['quantity']; ?> available)
                    </span>
                <?php else: ?>
                    <span class="badge bg-danger fs-6">❌ Out of Stock</span>
                <?php endif; ?>
            </p>

            <!-- Add to Cart -->
            <?php if ((int)$product['quantity'] > 0): ?>
            <form action="product.php?product_id=<?php echo $product_id; ?>" method="post">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <label class="fw-semibold">Quantity:</label>
                    <div class="d-flex align-items-center border rounded">
                        <button type="button" class="btn btn-light px-3"
                                onclick="changeQty(-1)">−</button>
                        <input type="number" name="quantity" id="qtyInput"
                               value="1" min="1" max="<?php echo (int)$product['quantity']; ?>"
                               class="form-control border-0 text-center"
                               style="width:60px;">
                        <button type="button" class="btn btn-light px-3"
                                onclick="changeQty(1)">+</button>
                    </div>
                </div>
                <button type="submit" name="add_to_cart"
                        class="btn btn-primary btn-lg w-100 py-3">
                    🛒 Add to Cart
                </button>
            </form>
            <?php else: ?>
            <button class="btn btn-secondary btn-lg w-100 py-3" disabled>
                Out of Stock
            </button>
            <?php endif; ?>

            <a href="shop.php?shop_id=<?php echo $shop_id; ?>"
               class="btn btn-outline-secondary w-100 mt-2">
                &larr; Back to Shop
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchImage(thumb, src) {
    const main = document.getElementById('mainImage');
    main.style.opacity = '0';
    setTimeout(() => {
        main.src = src;
        main.style.opacity = '1';
    }, 250);
    document.querySelectorAll('.thumbnail-strip img').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
}

function changeQty(delta) {
    const input = document.getElementById('qtyInput');
    const max   = parseInt(input.max);
    let val     = parseInt(input.value) + delta;
    if (val < 1)   val = 1;
    if (val > max) val = max;
    input.value = val;
}
</script>
</body>
</html>
