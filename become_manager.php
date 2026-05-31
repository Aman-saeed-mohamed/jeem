<?php
include "includes/connect.php";
include "includes/auth.php";
require_role(['customer']); // Only customers can use this form

$user_id = (int)$_SESSION['id'];

// If already a manager, redirect
$check = mysqli_query($conn, "SELECT id FROM shops WHERE manager_id='$user_id'");
if (mysqli_num_rows($check) > 0) {
    header("Location: manager_dashboard.php");
    exit();
}

$msg   = "";
$error = "";

if (isset($_POST['submit'])) {
    $shop_name = trim($_POST['shop_name']);
    $shop_type = $_POST['shop_type'];
    $location  = trim($_POST['location']);

    $allowed_types = ['coffeeshop','restaurant','clothing_men','clothing_women','clothing_children','clothing_general','electronics'];

    if (empty($shop_name) || empty($location) || !in_array($shop_type, $allowed_types)) {
        $error = "All fields are required.";
    } else {
        $safe_name     = mysqli_real_escape_string($conn, $shop_name);
        $safe_type     = mysqli_real_escape_string($conn, $shop_type);
        $safe_location = mysqli_real_escape_string($conn, $location);

        // BEGIN TRANSACTION: update role + insert shop atomically
        mysqli_begin_transaction($conn);
        $ok = true;

        $ok = $ok && mysqli_query($conn,
            "UPDATE users SET role = 'manager' WHERE id = '$user_id'");

        $ok = $ok && mysqli_query($conn,
            "INSERT INTO shops (name, type, location, manager_id)
             VALUES ('$safe_name', '$safe_type', '$safe_location', '$user_id')");

        if ($ok) {
            mysqli_commit($conn);
            // Update session role
            $_SESSION['role'] = 'manager';
            header("Location: manager_dashboard.php?welcome=1");
            exit();
        } else {
            mysqli_rollback($conn);
            $error = "Setup failed. Please try again.";
        }
    }
}

$type_labels = [
    'coffeeshop'       => 'Coffeeshop',
    'restaurant'       => 'Restaurant',
    'clothing_men'     => 'Clothing — Men',
    'clothing_women'   => 'Clothing — Women',
    'clothing_children'=> 'Clothing — Children',
    'clothing_general' => 'Clothing — General',
    'electronics'      => 'Electronics',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Setup - Jeem Mall</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/customer.css">
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="customer_dashboard.php">🛍 Jeem Mall</a>
        <a href="account.php" class="btn btn-outline-light btn-sm">&larr; Back to Account</a>
    </div>
</nav>

<div class="container mt-5" style="max-width:550px;">
    <div class="card shadow p-4">
        <h3 class="mb-1">🏪 Set Up Your Shop</h3>
        <p class="text-muted mb-4">Fill in the details below to become a Shop Owner on Jeem Mall.</p>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="become_manager.php" method="post">

            <div class="mb-3">
                <label class="form-label fw-semibold">Shop Name *</label>
                <input type="text" name="shop_name" class="form-control" required
                       placeholder="e.g. Ahmed's Electronics"
                       value="<?php echo isset($_POST['shop_name']) ? htmlspecialchars($_POST['shop_name']) : ''; ?>">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Shop Type *</label>
                <select name="shop_type" class="form-select" required>
                    <option value="">— Select a category —</option>
                    <?php foreach ($type_labels as $val => $label): ?>
                    <option value="<?php echo $val; ?>"
                        <?php echo (isset($_POST['shop_type']) && $_POST['shop_type'] === $val) ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Location *</label>
                <input type="text" name="location" class="form-control" required
                       placeholder="e.g. Riyadh, Al Olaya District"
                       value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>">
            </div>

            <button type="submit" name="submit" class="btn btn-primary w-100 py-2">
                🚀 Create My Shop
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
