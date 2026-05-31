<?php
include "connect.php";
include "session.php";

// Handle Add to Cart
if (isset($_POST["add_to_cart"])) {
    $product_id = $_POST["product_id"];
    $user_id    = $_SESSION["id"];

    // Check if already in cart
    $check = "SELECT * FROM cart WHERE user_id = '$user_id' AND product_id = '$product_id'";
    $check_result = mysqli_query($conn, $check);

    if (mysqli_num_rows($check_result) > 0) {
        // Increase quantity
        $update = "UPDATE cart SET quantity = quantity + 1 WHERE user_id = '$user_id' AND product_id = '$product_id'";
        mysqli_query($conn, $update);
    } else {
        // Insert new
        $sql = "INSERT INTO cart (user_id, product_id, quantity) VALUES ('$user_id', '$product_id', 1)";
        mysqli_query($conn, $sql);
    }
}

$sql     = "SELECT * FROM products";
$results = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Jeem Mall</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-dark px-4">
    <span class="navbar-brand fw-bold">Jeem Mall</span>
    <div>
        <span class="text-white me-3">Welcome, <?php echo $_SESSION["username"]; ?></span>
        <a href="cart.php" class="btn btn-outline-light btn-sm me-2">🛒 Cart</a>
        <a href="logout.php" class="btn btn-danger btn-sm">Log Out</a>
    </div>
</nav>

<div class="container mt-4">
    <h2 class="mb-4">Products</h2>
    <div class="row">
        <?php if (mysqli_num_rows($results) > 0):
              while ($product = mysqli_fetch_assoc($results)):
              $product_name = $product["name"];

              // Get pictures for this product
              $pic_sql     = "SELECT * FROM pictures WHERE product_name = '$product_name'";
              $pic_results = mysqli_query($conn, $pic_sql);
        ?>
        <div class="col-md-3 mb-4">
            <div class="card h-100 shadow-sm">

                <!-- Image Carousel -->
                <div id="carousel-<?php echo $product['id']; ?>" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <?php if (mysqli_num_rows($pic_results) > 0):
                              $first = mysqli_fetch_assoc($pic_results); ?>
                            <div class="carousel-item active">
                                <img src="<?php echo $first['path']; ?>" class="d-block w-100"
                                     style="height:180px;object-fit:cover;" alt="product">
                            </div>
                            <?php while ($pic = mysqli_fetch_assoc($pic_results)): ?>
                            <div class="carousel-item">
                                <img src="<?php echo $pic['path']; ?>" class="d-block w-100"
                                     style="height:180px;object-fit:cover;" alt="product">
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="carousel-item active">
                                <img src="https://via.placeholder.com/300x180?text=No+Image"
                                     class="d-block w-100" style="height:180px;object-fit:cover;" alt="no image">
                            </div>
                        <?php endif; ?>
                    </div>
                    <button class="carousel-control-prev" type="button"
                            data-bs-target="#carousel-<?php echo $product['id']; ?>" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                    </button>
                    <button class="carousel-control-next" type="button"
                            data-bs-target="#carousel-<?php echo $product['id']; ?>" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                    </button>
                </div>

                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><?php echo $product["name"]; ?></h5>
                    <p class="card-text text-muted small"><?php echo $product["description"]; ?></p>
                    <p class="fw-bold text-success mt-auto"><?php echo number_format($product["price"], 2); ?> SAR</p>

                    <form action="home.php" method="post">
                        <input type="hidden" name="product_id" value="<?php echo $product["id"]; ?>">
                        <button type="submit" name="add_to_cart" class="btn btn-primary w-100">Add to Cart</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endwhile; endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
