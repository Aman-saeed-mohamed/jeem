<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_role('admin');

$page_title = 'Manage Users';
$active_nav = 'admin_users';

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
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id < 1) {
        $message      = 'Invalid user ID.';
        $message_type = 'error';

    

    } elseif ($action === 'delete_user') {

        if ($user_id === current_user_id()) {
            

            $message      = '⚠️ You cannot delete your own account.';
            $message_type = 'error';
        } else {
            
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $affected = $conn->affected_rows;
            $stmt->close();

            $message      = $affected > 0 ? 'User deleted successfully.' : 'User not found.';
            $message_type = $affected > 0 ? 'success' : 'error';
        }

    

    } elseif ($action === 'edit_role') {

        if ($user_id === current_user_id()) {
            $message      = '⚠️ You cannot change your own role.';
            $message_type = 'error';
        } else {
            $new_role    = $_POST['new_role'] ?? '';
            $valid_roles = ['customer', 'manager', 'admin'];

            if (!in_array($new_role, $valid_roles, true)) {
                $message      = 'Invalid role selected.';
                $message_type = 'error';
            } else {

                $conn->begin_transaction();

                try {
                    
                    $stmt = $conn->prepare(
                        "UPDATE shops SET manager_id = NULL WHERE manager_id = ?"
                    );
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    $stmt->close();

                    
                    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $stmt->bind_param('si', $new_role, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    
                    if ($new_role === 'manager') {
                        $shop_action = $_POST['shop_action'] ?? ''; 

                        

                        if ($shop_action === 'existing') {
                            $existing_shop_id = (int)($_POST['existing_shop_id'] ?? 0);

                            if ($existing_shop_id < 1) {
                                throw new Exception('Please select a valid available shop.');
                            }

                            
                            $stmt = $conn->prepare(
                                "SELECT id FROM shops
                                 WHERE  id = ? AND manager_id IS NULL
                                 LIMIT  1
                                 FOR UPDATE"
                            );
                            $stmt->bind_param('i', $existing_shop_id);
                            $stmt->execute();
                            $stmt->store_result();

                            if ($stmt->num_rows === 0) {
                                throw new Exception(
                                    'The selected shop is no longer available. ' .
                                    'Another manager may have just been assigned to it.'
                                );
                            }
                            $stmt->close();

                            

                            $stmt = $conn->prepare(
                                "UPDATE shops
                                 SET    manager_id = ?
                                 WHERE  id = ? AND manager_id IS NULL"
                            );
                            $stmt->bind_param('ii', $user_id, $existing_shop_id);
                            $stmt->execute();
                            $stmt->close();

                        

                        } elseif ($shop_action === 'new') {
                            $shop_name     = trim($_POST['shop_name']     ?? '');
                            $shop_type     = $_POST['shop_type']          ?? '';
                            $shop_location = trim($_POST['shop_location'] ?? '');

                            if (empty($shop_name)) {
                                throw new Exception('Shop name is required.');
                            }
                            if (!array_key_exists($shop_type, $shop_types)) {
                                throw new Exception('Please select a valid shop type.');
                            }
                            if (empty($shop_location)) {
                                throw new Exception('Shop location is required.');
                            }

                            $stmt = $conn->prepare(
                                "INSERT INTO shops (manager_id, name, type, location)
                                 VALUES (?, ?, ?, ?)"
                            );
                            $stmt->bind_param('isss', $user_id, $shop_name, $shop_type, $shop_location);
                            $stmt->execute();
                            $stmt->close();
                        }
                        
                    }

                    $conn->commit();
                    $message      = 'User role updated successfully.';
                    $message_type = 'success';

                } catch (Exception $e) {
                    $conn->rollback();
                    $message      = 'Error: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    }
}

$users = $conn->query("
    SELECT  u.id,
            u.name,
            u.email,
            u.role,
            u.created_at,
            s.name AS shop_name
    FROM    users u
    LEFT JOIN shops s ON s.manager_id = u.id
    ORDER BY u.created_at DESC
");

$avail_result    = $conn->query("
    SELECT id, name, type
    FROM   shops
    WHERE  manager_id IS NULL
    ORDER BY name ASC
");
$available_shops = $avail_result->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<div class="sidebar-layout">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="page-content">

        
        <div class="page-header">
            <h1>👥 Manage Users</h1>
            <p>Edit roles, assign shops to managers, and remove users from the platform.</p>
        </div>

        
        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'error' ? 'error' : 'success' ?>">
            <?= e($message) ?>
        </div>
        <?php endif; ?>

        
        <div class="card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Shop (if Manager)</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php if ($users->num_rows === 0): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-icon">👤</div>
                                <p>No users found.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>

                    <?php while ($user = $users->fetch_assoc()):
                        $is_self = ($user['id'] === current_user_id());
                    ?>
                    <tr>
                        <td><?= $user['id'] ?></td>

                        <td>
                            <?= e($user['name']) ?>
                            <?php if ($is_self): ?>
                                <span class="badge" style="font-size:.65rem;background:var(--gold-glow);color:var(--gold);border:1px solid var(--gold);">You</span>
                            <?php endif; ?>
                        </td>

                        <td><?= e($user['email']) ?></td>

                        <td>
                            <?php
                            $role_style = match($user['role']) {
                                'admin'   => 'color:var(--gold);font-weight:700;',
                                'manager' => 'color:var(--status-shipped);font-weight:600;',
                                default   => '',
                            };
                            ?>
                            <span style="<?= $role_style ?>"><?= ucfirst(e($user['role'])) ?></span>
                        </td>

                        <td>
                            <?php if ($user['shop_name']): ?>
                                <?= e($user['shop_name']) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                        <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>

                        <td style="white-space:nowrap;">
                        <?php if (!$is_self): ?>

                            
                            <button
                                class="btn btn-secondary btn-sm btn-edit-role"
                                id="edit-btn-<?= $user['id'] ?>"
                                data-user-id="<?= $user['id'] ?>"
                                data-user-name="<?= e($user['name']) ?>"
                                data-current-role="<?= e($user['role']) ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#editRoleModal"
                            >
                                ✏️ Edit Role
                            </button>

                            
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action"  value="delete_user">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <button
                                    type="submit"
                                    class="btn btn-danger btn-sm"
                                    id="del-btn-<?= $user['id'] ?>"
                                    data-confirm="Permanently delete '<?= e($user['name']) ?>'? All their orders and cart data will also be removed."
                                >
                                    🗑️
                                </button>
                            </form>

                        <?php else: ?>
                            <span class="text-muted" style="font-size:.82rem;">— Protected —</span>
                        <?php endif; ?>
                        </td>

                    </tr>
                    <?php endwhile; ?>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </div>
        

        
        <div class="modal fade" id="editRoleModal" tabindex="-1"
             aria-labelledby="editRoleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content"
                     style="background:var(--bg-card);border:1px solid var(--border-subtle);">

                    
                    <div class="modal-header" style="border-color:var(--border-subtle);">
                        <h5 class="modal-title" id="editRoleModalLabel">
                            ✏️ Edit Role —
                            <span id="modal-user-name" class="text-gold"></span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white"
                                data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    
                    <form method="POST" action="" id="editRoleForm">
                        <div class="modal-body">

                            
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action"  value="edit_role">
                            <input type="hidden" name="user_id" id="modal-user-id" value="">

                            
                            <div class="form-group">
                                <label class="form-label" for="modal-new-role">Assign New Role</label>
                                <select name="new_role" id="modal-new-role" class="form-control">
                                    <option value="customer">Customer</option>
                                    <option value="manager">Manager</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>

                            
                            <div id="manager-section" style="display:none;">
                                <hr style="border-color:var(--border-subtle);margin:1.2rem 0;">
                                <h6 style="color:var(--gold);margin-bottom:1rem;font-size:.95rem;">
                                    🏪 Shop Assignment
                                </h6>

                                
                                <div class="form-group" style="margin-bottom:1rem;">
                                    <div style="display:flex;gap:2rem;flex-wrap:wrap;">

                                        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                                            <input
                                                type="radio"
                                                name="shop_action"
                                                id="radio-existing"
                                                value="existing"
                                                <?= !empty($available_shops) ? 'checked' : '' ?>
                                            >
                                            <span>Assign to an existing available shop</span>
                                        </label>

                                        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                                            <input
                                                type="radio"
                                                name="shop_action"
                                                id="radio-new"
                                                value="new"
                                                <?= empty($available_shops) ? 'checked' : '' ?>
                                            >
                                            <span>Create a new shop for this manager</span>
                                        </label>

                                    </div>
                                </div>

                                
                                <div id="existing-shop-section"
                                     style="display:<?= !empty($available_shops) ? 'block' : 'none' ?>;">
                                    <div class="form-group">
                                        <label class="form-label" for="existing-shop-id">
                                            Available Shops
                                        </label>

                                        <?php if (empty($available_shops)): ?>
                                            <p class="text-muted" style="font-size:.87rem;">
                                                No unassigned shops exist. Use the
                                                <strong>+ Create Shop</strong> button on the Shops page first,
                                                or select "Create a new shop" above.
                                            </p>
                                        <?php else: ?>
                                            <select name="existing_shop_id" id="existing-shop-id"
                                                    class="form-control">
                                                <?php foreach ($available_shops as $as): ?>
                                                <option value="<?= $as['id'] ?>">
                                                    #<?= $as['id'] ?> — <?= e($as['name']) ?>
                                                    (<?= e($shop_types[$as['type']] ?? $as['type']) ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                
                                <div id="new-shop-section"
                                     style="display:<?= empty($available_shops) ? 'block' : 'none' ?>;">

                                    <div class="form-group">
                                        <label class="form-label" for="new-shop-name">Shop Name</label>
                                        <input type="text" id="new-shop-name" name="shop_name"
                                               class="form-control"
                                               placeholder="e.g. Nour Coffee Corner"
                                               maxlength="150">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="new-shop-type">Shop Type</label>
                                        <select id="new-shop-type" name="shop_type" class="form-control">
                                            <?php foreach ($shop_types as $val => $label): ?>
                                            <option value="<?= $val ?>"><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="new-shop-location">Location</label>
                                        <input type="text" id="new-shop-location" name="shop_location"
                                               class="form-control"
                                               placeholder="e.g. Jeddah, Al-Balad District"
                                               maxlength="255">
                                    </div>

                                </div>
                                

                            </div>
                            

                        </div>

                        <div class="modal-footer" style="border-color:var(--border-subtle);">
                            <button type="button" class="btn btn-secondary"
                                    data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="modal-submit-btn">
                                Save Changes
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
        

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

    const roleSelect      = document.getElementById('modal-new-role');
    const managerSection  = document.getElementById('manager-section');
    const existingSection = document.getElementById('existing-shop-section');
    const newSection      = document.getElementById('new-shop-section');

    // ── Populate modal fields when "Edit Role" is clicked ─────
    document.querySelectorAll('.btn-edit-role').forEach(btn => {
        btn.addEventListener('click', function () {
            // Transfer data-* attributes → modal hidden fields.
            document.getElementById('modal-user-id').value         = this.dataset.userId;
            document.getElementById('modal-user-name').textContent = this.dataset.userName;

            // Set the role dropdown to the user's current role.
            roleSelect.value = this.dataset.currentRole;

            // Fire the change event so the manager section
            // shows/hides correctly without extra click.
            roleSelect.dispatchEvent(new Event('change'));
        });
    });

    // ── Show / hide manager section based on selected role ────
    roleSelect.addEventListener('change', function () {
        managerSection.style.display = (this.value === 'manager') ? 'block' : 'none';
    });

    // ── Toggle existing vs new shop sub-sections ──────────────
    document.querySelectorAll('input[name="shop_action"]').forEach(radio => {
        radio.addEventListener('change', function () {
            existingSection.style.display = (this.value === 'existing') ? 'block' : 'none';
            newSection.style.display      = (this.value === 'new')      ? 'block' : 'none';
        });
    });

});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
