<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_role('admin');

$page_title = 'Manage Shops';
$active_nav = 'admin_shops';

$message      = '';
$message_type = '';

$shop_types = [
    'coffeeshop'               => 'Coffee Shop',
    'restaurant'               => 'Restaurant',
    'clothing_men'             => 'Clothing — Men',
    'clothing_women'           => 'Clothing — Women',
    'clothing_kids'            => 'Clothing — Kids',
    'clothing_sports'          => 'Clothing — Sports',
    'electronics_phones'       => 'Electronics — Phones',
    'electronics_laptops'      => 'Electronics — Laptops',
    'electronics_accessories'  => 'Electronics — Accessories',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action  = $_POST['action']  ?? '';
    $shop_id = (int)($_POST['shop_id'] ?? 0);

    

    if ($action === 'toggle_status') {

        if ($shop_id < 1) {
            $message      = 'Invalid shop ID.';
            $message_type = 'error';
        } else {
            
            $stmt = $conn->prepare("
                UPDATE shops
                SET    status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END
                WHERE  id = ?
            ");
            $stmt->bind_param('i', $shop_id);
            $stmt->execute();
            $stmt->close();

            $message      = 'Shop status updated successfully.';
            $message_type = 'success';
        }

    

    } elseif ($action === 'delete_shop') {

        if ($shop_id < 1) {
            $message      = 'Invalid shop ID.';
            $message_type = 'error';
        } else {
            
            $stmt = $conn->prepare("DELETE FROM shops WHERE id = ?");
            $stmt->bind_param('i', $shop_id);
            $stmt->execute();
            $affected = $conn->affected_rows;
            $stmt->close();

            $message      = $affected > 0 ? 'Shop deleted successfully.' : 'Shop not found.';
            $message_type = $affected > 0 ? 'success' : 'error';
        }

    

    } elseif ($action === 'create_shop') {

        $shop_name     = trim($_POST['shop_name']     ?? '');
        $shop_type     = $_POST['shop_type']          ?? '';
        $shop_location = trim($_POST['shop_location'] ?? '');

        

        if (empty($shop_name)) {
            $message      = 'Shop name is required.';
            $message_type = 'error';
        } elseif (!array_key_exists($shop_type, $shop_types)) {
            $message      = 'Please select a valid shop type.';
            $message_type = 'error';
        } elseif (empty($shop_location)) {
            $message      = 'Shop location is required.';
            $message_type = 'error';
        } else {
            
            $stmt = $conn->prepare(
                "INSERT INTO shops (manager_id, name, type, location) VALUES (NULL, ?, ?, ?)"
            );
            $stmt->bind_param('sss', $shop_name, $shop_type, $shop_location);
            $stmt->execute();
            $stmt->close();

            $message      = "Shop '" . e($shop_name) . "' created and marked as available for assignment.";
            $message_type = 'success';
        }
    }
}

$shops = $conn->query("
    SELECT  s.id,
            s.name,
            s.type,
            s.location,
            s.status,
            s.created_at,
            u.name  AS manager_name,
            u.email AS manager_email
    FROM    shops s
    LEFT JOIN users u ON u.id = s.manager_id
    ORDER BY s.created_at DESC
");

include __DIR__ . '/../includes/header.php';
?>

<div class="sidebar-layout">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="page-content">

        
        <div class="page-header d-flex justify-between align-center" style="flex-wrap:wrap;gap:1rem;">
            <div>
                <h1>🏪 Manage Shops</h1>
                <p>Toggle status, delete shops, or create an available shop for future manager assignment.</p>
            </div>
            
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createShopModal">
                + Create Shop
            </button>
        </div>

        
        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'error' ? 'error' : 'success' ?>">
            <?= $message ?>
        </div>
        <?php endif; ?>

        
        <div class="card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Shop Name</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Manager</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php if ($shops->num_rows === 0): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-icon">🏗️</div>
                                    <p>No shops yet. Click <strong>+ Create Shop</strong> to add the first one.</p>
                                </div>
                            </td>
                        </tr>

                        <?php else: ?>
                        <?php while ($shop = $shops->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?= $shop['id'] ?></strong></td>

                            <td><?= e($shop['name']) ?></td>

                            <td style="white-space:nowrap;">
                                <?= e($shop_types[$shop['type']] ?? $shop['type']) ?>
                            </td>

                            <td><?= e($shop['location']) ?></td>

                            <td>
                                <?php if ($shop['manager_name']): ?>
                                    <strong><?= e($shop['manager_name']) ?></strong><br>
                                    <small class="text-muted"><?= e($shop['manager_email']) ?></small>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Unassigned</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="badge <?= $shop['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= ucfirst(e($shop['status'])) ?>
                                </span>
                            </td>

                            <td style="white-space:nowrap;">

                                
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action"  value="toggle_status">
                                    <input type="hidden" name="shop_id" value="<?= $shop['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <?= $shop['status'] === 'active' ? '🔒 Deactivate' : '✅ Activate' ?>
                                    </button>
                                </form>

                                
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action"  value="delete_shop">
                                    <input type="hidden" name="shop_id" value="<?= $shop['id'] ?>">
                                    <button
                                        type="submit"
                                        class="btn btn-danger btn-sm"
                                        data-confirm="Delete '<?= e($shop['name']) ?>'? All products will be removed permanently. Orders will retain a null shop reference."
                                    >
                                        🗑️ Delete
                                    </button>
                                </form>

                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </div>
        

        
        <div class="modal fade" id="createShopModal" tabindex="-1"
             aria-labelledby="createShopLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content"
                     style="background:var(--bg-card);border:1px solid var(--border-subtle);">

                    <div class="modal-header" style="border-color:var(--border-subtle);">
                        <h5 class="modal-title" id="createShopLabel">🏪 Create New Shop</h5>
                        <button type="button" class="btn-close btn-close-white"
                                data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form method="POST" action="">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="create_shop">

                            <p class="text-muted" style="font-size:.87rem;margin-bottom:1.2rem;">
                                A shop created here will have <strong>no manager</strong> —
                                it becomes <em>available</em> for assignment via
                                <strong>Users → Edit Role → Assign Existing Shop</strong>.
                            </p>

                            <div class="form-group">
                                <label class="form-label" for="cs-name">Shop Name</label>
                                <input type="text" id="cs-name" name="shop_name"
                                       class="form-control"
                                       placeholder="e.g. Al-Noor Coffee"
                                       required maxlength="150">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="cs-type">Shop Type</label>
                                <select id="cs-type" name="shop_type" class="form-control" required>
                                    <?php foreach ($shop_types as $val => $label): ?>
                                    <option value="<?= $val ?>"><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="cs-location">Location</label>
                                <input type="text" id="cs-location" name="shop_location"
                                       class="form-control"
                                       placeholder="e.g. Riyadh, Olaya District"
                                       required maxlength="255">
                            </div>
                        </div>

                        <div class="modal-footer" style="border-color:var(--border-subtle);">
                            <button type="button" class="btn btn-secondary"
                                    data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create Shop</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
        

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
