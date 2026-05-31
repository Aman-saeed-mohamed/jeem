<?php
// Run this file ONCE to update the pictures table
// Then delete it for security
include "includes/connect.php";

$steps = [];

// Step 1: Check if product_id column already exists
$check = mysqli_query($conn, "SHOW COLUMNS FROM pictures LIKE 'product_id'");
if (mysqli_num_rows($check) === 0) {
    if (mysqli_query($conn, "ALTER TABLE pictures ADD COLUMN product_id INT DEFAULT NULL")) {
        $steps[] = "✅ Added product_id column";
    } else {
        $steps[] = "❌ Failed to add product_id: " . mysqli_error($conn);
    }
} else {
    $steps[] = "ℹ️ product_id column already exists";
}

// Step 2: Check if product_name column exists and drop it
$check2 = mysqli_query($conn, "SHOW COLUMNS FROM pictures LIKE 'product_name'");
if (mysqli_num_rows($check2) > 0) {
    // Drop FK first if any, then column
    mysqli_query($conn, "ALTER TABLE pictures DROP FOREIGN KEY fk_pic_product");
    if (mysqli_query($conn, "ALTER TABLE pictures DROP COLUMN product_name")) {
        $steps[] = "✅ Removed old product_name column";
    } else {
        $steps[] = "⚠️ product_name column not removed (may not exist): " . mysqli_error($conn);
    }
} else {
    $steps[] = "ℹ️ product_name column not found (already clean)";
}

// Step 3: Add FK constraint
$result = mysqli_query($conn,
    "ALTER TABLE pictures ADD CONSTRAINT fk_pic_product
     FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE");
if ($result) {
    $steps[] = "✅ FK constraint added";
} else {
    $steps[] = "ℹ️ FK: " . mysqli_error($conn);
}

echo "<h2>Database Migration</h2><ul>";
foreach ($steps as $s) echo "<li>$s</li>";
echo "</ul>";
echo "<p><strong>Done! Now delete this file.</strong></p>";
echo "<p><a href='login.php'>Go to Login</a></p>";
?>
