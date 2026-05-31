<?php
include "includes/connect.php";
include "includes/auth.php";
require_role(['admin']);

$msg = "";

// Delete user (cannot delete self)
if (isset($_POST['delete_user'])) {
    $uid = (int)$_POST['user_id'];
    if ($uid !== (int)$_SESSION['id']) {
        mysqli_query($conn, "DELETE FROM users WHERE id='$uid'");
    }
    header("Location: admin_users.php");
    exit();
}

// Edit user role
if (isset($_POST['edit_role'])) {
    $uid  = (int)$_POST['user_id'];
    $role = $_POST['role'];
    if (in_array($role, ['customer','manager','admin']) && $uid !== (int)$_SESSION['id']) {
        mysqli_query($conn, "UPDATE users SET role='$role' WHERE id='$uid'");
        $msg = "success:Role updated successfully.";
    }
}

$users = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="admin_dashboard.php">⚙️ Admin Panel</a>
        <div class="navbar-nav ms-auto d-flex flex-row gap-3 align-items-center">
            <a href="admin_dashboard.php" class="nav-link text-white">Home</a>
            <a href="admin_users.php"     class="nav-link text-white active">Manage Users</a>
            <a href="admin_shops.php"     class="nav-link text-white">Manage Shops</a>
            <a href="logout.php"          class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h2 class="mb-4">Manage Users</h2>

    <?php if ($msg):
          [$type, $text] = explode(':', $msg, 2);
          $cls = $type === 'success' ? 'success' : 'danger'; ?>
    <div class="alert alert-<?php echo $cls; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($text); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle bg-white">
        <thead class="table-dark">
            <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Edit Role</th><th>Delete</th></tr>
        </thead>
        <tbody>
        <?php while ($u = mysqli_fetch_assoc($users)):
              $is_self = ((int)$u['id'] === (int)$_SESSION['id']); ?>
        <tr>
            <td><?php echo (int)$u['id']; ?></td>
            <td><?php echo htmlspecialchars($u['name']); ?></td>
            <td><?php echo htmlspecialchars($u['email']); ?></td>
            <td><span class="badge bg-<?php echo $u['role']==='admin'?'danger':($u['role']==='manager'?'warning text-dark':'primary'); ?>">
                <?php echo ucfirst($u['role']); ?></span></td>
            <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
            <td>
                <?php if (!$is_self): ?>
                <form method="post" class="d-flex gap-1">
                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                    <select name="role" class="form-select form-select-sm">
                        <option value="customer"<?php echo $u['role']==='customer'?' selected':''; ?>>Customer</option>
                        <option value="manager" <?php echo $u['role']==='manager' ?' selected':''; ?>>Manager</option>
                        <option value="admin"   <?php echo $u['role']==='admin'   ?' selected':''; ?>>Admin</option>
                    </select>
                    <button type="submit" name="edit_role" class="btn btn-warning btn-sm">Save</button>
                </form>
                <?php else: ?>
                <small class="text-muted">You</small>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!$is_self): ?>
                <form method="post" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                    <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Delete</button>
                </form>
                <?php else: ?>
                <small class="text-muted">—</small>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
