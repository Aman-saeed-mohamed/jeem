<?php
include "includes/connect.php";
include "includes/auth.php";
require_role(['admin']);

$msg = "";

// Delete shop
if (isset($_POST['delete_shop'])) {
    $sid = (int)$_POST['shop_id'];
    mysqli_query($conn, "DELETE FROM shops WHERE id='$sid'");
    header("Location: admin_shops.php");
    exit();
}

$shops = mysqli_query($conn,
    "SELECT shops.*, users.name AS manager_name
     FROM shops
     LEFT JOIN users ON shops.manager_id = users.id
     ORDER BY shops.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Shops - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="admin_dashboard.php">⚙️ Admin Panel</a>
        <div class="navbar-nav ms-auto d-flex flex-row gap-3 align-items-center">
            <a href="admin_dashboard.php" class="nav-link text-white">Home</a>
            <a href="admin_users.php"     class="nav-link text-white">Manage Users</a>
            <a href="admin_shops.php"     class="nav-link text-white active">Manage Shops</a>
            <a href="logout.php"          class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h2 class="mb-4">Manage Shops</h2>

    <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle bg-white">
        <thead class="table-dark">
            <tr><th>ID</th><th>Shop Name</th><th>Type</th><th>Location</th><th>Manager</th><th>Rating</th><th>Created</th><th>Delete</th></tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($shops) > 0):
              while ($s = mysqli_fetch_assoc($shops)): ?>
        <tr>
            <td><?php echo (int)$s['id']; ?></td>
            <td><?php echo htmlspecialchars($s['name']); ?></td>
            <td><?php echo str_replace('_',' ', ucfirst($s['type'])); ?></td>
            <td><?php echo htmlspecialchars($s['location']); ?></td>
            <td><?php echo $s['manager_name'] ? htmlspecialchars($s['manager_name']) : '<span class="text-muted">—</span>'; ?></td>
            <td>⭐ <?php echo number_format($s['rating'],1); ?></td>
            <td><?php echo date('M d, Y', strtotime($s['created_at'])); ?></td>
            <td>
                <form method="post" onsubmit="return confirm('Delete this shop? All products will be deleted too.');">
                    <input type="hidden" name="shop_id" value="<?php echo (int)$s['id']; ?>">
                    <button type="submit" name="delete_shop" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No shops registered yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
