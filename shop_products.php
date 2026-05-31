<?php
include "includes/connect.php";
include "includes/session.php";

$active_page = "home";
$shop_id     = isset($_GET["shop_id"]) ? (int)$_GET["shop_id"] : 0;
$cart_msg    = "";

if ($shop_id <= 0) {
    header("Location: customer_dashboard.php");
    exit();
}

// Handle Add to Cart
if (isset($_POST["add_to_cart"])) {
    $product_id = (int)$_POST["product_id"];
    $user_id    = (int)$_SESSION["id"];

    // Verify product belongs to this shop
    $verify = mysqli_query($conn,
        "SELECT id FROM products WHERE id = '$product_id' AND shop_id = '$shop_id'");

    if ($verify && mysqli_num_rows($verify) === 1) {
        $check = mysqli_query($conn,
            "SELECT id FROM cart WHERE user_id='$user_id' AND product_id='$product_id'");

        if (mysqli_num_rows($check) > 0) {
            mysqli_query($conn,
                "UPDATE cart SET quantity = quantity + 1
                 WHERE user_id='$user_id' AND product_id='$product_id'");
        } else {
            mysqli_query($conn,
                "INSERT INTO cart (user_id, product_id, quantity)
                 VALUES ('$user_id', '$product_id', 1)");
        }
        $cart_msg = "✅ Product added to cart!";
    }
}

// Get shop info
$shop_res = mysqli_query($conn, "SELECT * FROM shops WHERE id = '$shop_id'");
if (!$shop_res || mysqli_num_rows($shop_res) === 0) {
    header("Location: customer_dashboard.php");
    exit();
}
$shop = mysqli_fetch_assoc($shop_res);

// Get products for this shop
$products_res = mysqli_query($conn,
    "SELECT * FROM products WHERE shop_id = '$shop_id' ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($shop['name']); ?> - Jeem Mall</title>
    <link rel="stylesheet" href="css/customer.css">
</head>
<body>

<?php include "includes/navbar.php"; ?>

<main class="main-content">

    <div class="page-header" style="justify-content:flex-start;gap:1rem;">
        <a href="customer_dashboard.php"
           style="text-decoration:none;color:var(--text-muted);font-size:1.5rem;">&larr;</a>
        <h1 class="page-title"><?php echo htmlspecialchars($shop["name"]); ?> — Products</h1>
    </div>

    <?php if ($cart_msg !== ""): ?>
        <div style="background:rgba(16,185,129,0.1);color:#10b981;padding:0.75rem 1.5rem;
                    border-radius:0.5rem;margin-bottom:1.5rem;font-weight:500;">
            <?php echo $cart_msg; ?>
        </div>
    <?php endif; ?>

    <div class="grid-container">
        <?php if ($products_res && mysqli_num_rows($products_res) > 0):
              while ($product = mysqli_fetch_assoc($products_res)):
                  $pname = htmlspecialchars($product["name"]);

                  // Get pictures from pictures table; fallback to products.image
                  $safe_pname = mysqli_real_escape_string($conn, $product["name"]);
                  $pic_res    = mysqli_query($conn,
                      "SELECT path FROM pictures WHERE product_name = '$safe_pname' LIMIT 10");
                  $pics = [];
                  if ($pic_res) {
                      while ($p = mysqli_fetch_assoc($pic_res)) $pics[] = $p["path"];
                  }
                  // Fallback to products.image
                  if (empty($pics) && !empty($product["image"])) {
                      $pics[] = $product["image"];
                  }
                  if (empty($pics)) {
                      $pics[] = "https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&q=80&w=800";
                  }
                  $main_img  = $pics[0];
                  $imgs_json = json_encode($pics);
        ?>
        <div class="card">
            <img src="<?php echo htmlspecialchars($main_img); ?>"
                 alt="<?php echo $pname; ?>"
                 class="card-img dynamic-img"
                 data-images='<?php echo htmlspecialchars($imgs_json, ENT_QUOTES); ?>'>
            <div class="card-content">
                <h3 class="card-title"><?php echo $pname; ?></h3>
                <p class="card-desc"><?php echo htmlspecialchars($product["description"]); ?></p>
                <div class="card-footer">
                    <span class="price"><?php echo number_format($product["price"], 2); ?> SAR</span>
                    <form action="shop_products.php?shop_id=<?php echo $shop_id; ?>"
                          method="post" style="display:inline;">
                        <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                        <button type="submit" name="add_to_cart" class="btn btn-primary">
                            Add to Cart
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
            <p style="color:var(--text-muted);padding:2rem;">No products found in this shop.</p>
        <?php endif; ?>
    </div>

</main>

<script src="js/customer.js"></script>
<script>
    // Dynamic image rotation (only if multiple images)
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.dynamic-img').forEach(img => {
            let images;
            try { images = JSON.parse(img.getAttribute('data-images')); } catch(e) { return; }
            if (!Array.isArray(images) || images.length <= 1) return;

            let i = 0;
            img.style.transition = "opacity 0.3s ease-in-out";
            setInterval(() => {
                img.style.opacity = "0.5";
                setTimeout(() => {
                    i = (i + 1) % images.length;
                    img.src = images[i];
                    img.style.opacity = "1";
                }, 300);
            }, 3500 + Math.random() * 1000);
        });
    });
</script>
</body>
</html>
