<?php
/**
 * =============================================================
 * JEEM MALL — Customer: Account & Address Management
 * =============================================================
 * Sections:
 *   1. Profile — Update name, email, and password
 *   2. Addresses — Add/delete addresses from user_addresses table,
 *                   set one as default (is_default = 1)
 *
 * POST Actions:
 *   update_profile  → UPDATE users SET name, email, [password_hash]
 *   add_address     → INSERT INTO user_addresses
 *   set_default     → UPDATE user_addresses, set is_default flags atomically
 *   delete_address  → DELETE FROM user_addresses (can't delete last address)
 *
 * SECURITY:
 *   - Password only hashed+updated if new password field is non-empty
 *   - Old password verified before allowing password change
 *   - All address actions scope to current_user_id() (IDOR protection)
 *   - CSRF on every POST form
 * =============================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['customer', 'manager']);

$user_id    = current_user_id();
$page_title = 'My Account';
$active_nav = 'account';

$message      = '';
$message_type = '';

// ── POST Handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    // ─────────────────────────────────────────────────────────
    // ACTION: Update Profile
    // ─────────────────────────────────────────────────────────
    if ($action === 'update_profile') {

        $new_name  = trim($_POST['name']         ?? '');
        $new_email = trim($_POST['email']        ?? '');
        $old_pass  = $_POST['current_password']  ?? '';
        $new_pass  = $_POST['new_password']       ?? '';
        $conf_pass = $_POST['confirm_password']   ?? '';

        if (empty($new_name) || empty($new_email)) {
            $message      = 'Name and email are required.';
            $message_type = 'error';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $message      = 'Please enter a valid email address.';
            $message_type = 'error';
        } else {
            // Check if the new email is taken by another user.
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmt->bind_param('si', $new_email, $user_id);
            $stmt->execute();
            $stmt->store_result();
            $email_taken = $stmt->num_rows > 0;
            $stmt->close();

            if ($email_taken) {
                $message      = 'That email address is already in use by another account.';
                $message_type = 'error';
            } else {
                $do_password = !empty($new_pass);

                if ($do_password) {
                    // Verify the current password before allowing a change.
                    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    $current_hash = $stmt->get_result()->fetch_assoc()['password_hash'];
                    $stmt->close();

                    if (!password_verify($old_pass, $current_hash)) {
                        $message      = 'Current password is incorrect.';
                        $message_type = 'error';
                        $do_password  = false; // Abort password change
                    } elseif (strlen($new_pass) < 8) {
                        $message      = 'New password must be at least 8 characters.';
                        $message_type = 'error';
                        $do_password  = false;
                    } elseif ($new_pass !== $conf_pass) {
                        $message      = 'New password and confirmation do not match.';
                        $message_type = 'error';
                        $do_password  = false;
                    }
                }

                if (empty($message)) {
                    if ($do_password) {
                        $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
                        $stmt = $conn->prepare(
                            "UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ?"
                        );
                        $stmt->bind_param('sssi', $new_name, $new_email, $new_hash, $user_id);
                    } else {
                        $stmt = $conn->prepare(
                            "UPDATE users SET name = ?, email = ? WHERE id = ?"
                        );
                        $stmt->bind_param('ssi', $new_name, $new_email, $user_id);
                    }
                    $stmt->execute();
                    $stmt->close();

                    // Refresh the session name (shown in nav).
                    $_SESSION['user_name'] = $new_name;

                    $message      = 'Profile updated successfully' . ($do_password ? ' (including password)' : '') . '.';
                    $message_type = 'success';
                }
            }
        }

    // ─────────────────────────────────────────────────────────
    // ACTION: Add Address
    // ─────────────────────────────────────────────────────────
    } elseif ($action === 'add_address') {

        $new_address  = trim($_POST['address']    ?? '');
        $make_default = isset($_POST['make_default']);

        if (empty($new_address)) {
            $message      = 'Address cannot be empty.';
            $message_type = 'error';
        } else {
            $conn->begin_transaction();
            try {
                if ($make_default) {
                    // Clear any existing default first.
                    $stmt = $conn->prepare(
                        "UPDATE user_addresses SET is_default = 0 WHERE user_id = ?"
                    );
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    $stmt->close();
                }

                // Check if this is the user's first address — make it default automatically.
                $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_addresses WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $addr_count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
                $stmt->close();

                $is_def = ($make_default || $addr_count === 0) ? 1 : 0;

                $stmt = $conn->prepare(
                    "INSERT INTO user_addresses (user_id, address, is_default) VALUES (?, ?, ?)"
                );
                $stmt->bind_param('isi', $user_id, $new_address, $is_def);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $message      = 'Address added successfully.';
                $message_type = 'success';

            } catch (Exception $e) {
                $conn->rollback();
                $message      = 'Failed to add address: ' . $e->getMessage();
                $message_type = 'error';
            }
        }

    // ─────────────────────────────────────────────────────────
    // ACTION: Set Default Address
    // ─────────────────────────────────────────────────────────
    } elseif ($action === 'set_default') {

        $addr_id = (int)($_POST['address_id'] ?? 0);

        // Verify address belongs to this user.
        $stmt = $conn->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param('ii', $addr_id, $user_id);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        if (!$exists) {
            $message      = 'Address not found.';
            $message_type = 'error';
        } else {
            $conn->begin_transaction();
            try {
                // Remove default from all other addresses for this user.
                $stmt = $conn->prepare(
                    "UPDATE user_addresses SET is_default = 0 WHERE user_id = ?"
                );
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->close();

                // Set this address as default.
                $stmt = $conn->prepare(
                    "UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?"
                );
                $stmt->bind_param('ii', $addr_id, $user_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $message      = 'Default delivery address updated.';
                $message_type = 'success';

            } catch (Exception $e) {
                $conn->rollback();
                $message      = 'Update failed: ' . $e->getMessage();
                $message_type = 'error';
            }
        }

    // ─────────────────────────────────────────────────────────
    // ACTION: Delete Address
    // ─────────────────────────────────────────────────────────
    } elseif ($action === 'delete_address') {

        $addr_id = (int)($_POST['address_id'] ?? 0);

        // Count how many addresses this user has.
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_addresses WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $addr_count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        if ($addr_count <= 1) {
            $message      = 'You must keep at least one address on your account.';
            $message_type = 'error';
        } else {
            // Fetch the address to check if it's the default.
            $stmt = $conn->prepare(
                "SELECT is_default FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1"
            );
            $stmt->bind_param('ii', $addr_id, $user_id);
            $stmt->execute();
            $addr_row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$addr_row) {
                $message      = 'Address not found.';
                $message_type = 'error';
            } else {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare(
                        "DELETE FROM user_addresses WHERE id = ? AND user_id = ?"
                    );
                    $stmt->bind_param('ii', $addr_id, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    // If we deleted the default, auto-promote the next available.
                    if ((int)$addr_row['is_default'] === 1) {
                        $stmt = $conn->prepare("
                            UPDATE user_addresses SET is_default = 1
                            WHERE  user_id = ?
                            ORDER BY id ASC
                            LIMIT  1
                        ");
                        $stmt->bind_param('i', $user_id);
                        $stmt->execute();
                        $stmt->close();
                    }

                    $conn->commit();
                    $message      = 'Address deleted.';
                    $message_type = 'success';

                } catch (Exception $e) {
                    $conn->rollback();
                    $message      = 'Delete failed: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    }
}

// ── Fetch current user profile ────────────────────────────────
$stmt = $conn->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Fetch user's addresses ────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$addresses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-content" style="max-width:850px;margin:0 auto;">

    <div class="page-header">
        <h1>👤 My Account</h1>
        <p>Manage your profile information and delivery addresses.</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $message_type === 'error' ? 'error' : 'success' ?>">
        <?= e($message) ?>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════
         SECTION 1: Profile
         ═══════════════════════════════════════════════════════ -->
    <div class="card" style="margin-bottom:1.5rem;">
        <h3 style="margin:0 0 1.2rem;">✏️ Profile Information</h3>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action"     value="update_profile">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label" for="acc-name">Full Name</label>
                    <input type="text" id="acc-name" name="name" class="form-control"
                           value="<?= e($user['name']) ?>" required maxlength="150">
                </div>
                <div class="form-group">
                    <label class="form-label" for="acc-email">Email Address</label>
                    <input type="email" id="acc-email" name="email" class="form-control"
                           value="<?= e($user['email']) ?>" required maxlength="191">
                </div>
            </div>

            <hr style="border-color:var(--border-subtle);margin:1.2rem 0;">
            <p style="font-size:.83rem;color:var(--text-muted);margin-bottom:1rem;">
                🔒 Leave the password fields blank to keep your current password.
            </p>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label" for="acc-cur-pass">Current Password</label>
                    <input type="password" id="acc-cur-pass" name="current_password"
                           class="form-control" placeholder="Required to change password" autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label class="form-label" for="acc-new-pass">New Password</label>
                    <input type="password" id="acc-new-pass" name="new_password"
                           class="form-control" placeholder="Min 8 characters" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label class="form-label" for="acc-conf-pass">Confirm New Password</label>
                    <input type="password" id="acc-conf-pass" name="confirm_password"
                           class="form-control" placeholder="Repeat new password" autocomplete="new-password">
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.5rem;flex-wrap:wrap;gap:.5rem;">
                <div style="font-size:.8rem;color:var(--text-muted);">
                    Member since <?= date('F Y', strtotime($user['created_at'])) ?>
                    &nbsp;·&nbsp; Role: <strong><?= ucfirst(e($user['role'])) ?></strong>
                </div>
                <button type="submit" class="btn btn-primary">💾 Save Profile</button>
            </div>
        </form>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         SECTION 2: Delivery Addresses
         ═══════════════════════════════════════════════════════ -->
    <div class="card">
        <div class="d-flex justify-between align-center mb-2" style="flex-wrap:wrap;gap:.5rem;">
            <h3 style="margin:0;">📍 Delivery Addresses</h3>
            <button class="btn btn-secondary btn-sm"
                    data-bs-toggle="collapse"
                    data-bs-target="#addAddressForm"
                    aria-expanded="false">
                + Add Address
            </button>
        </div>

        <!-- Add Address Form (collapsible) -->
        <div class="collapse" id="addAddressForm">
            <form method="POST" action=""
                  style="background:var(--bg-elevated);border-radius:var(--radius-sm);
                         padding:1rem;margin-bottom:1rem;border:1px solid var(--border-subtle);">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action"     value="add_address">

                <div class="form-group">
                    <label class="form-label" for="new-addr">Street / Full Address</label>
                    <input type="text" id="new-addr" name="address" class="form-control"
                           placeholder="e.g. 123 King Fahd Road, Riyadh 12345"
                           required maxlength="500">
                </div>

                <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.87rem;">
                        <input type="checkbox" name="make_default" value="1">
                        Set as default delivery address
                    </label>
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-left:auto;">
                        Add Address
                    </button>
                </div>
            </form>
        </div>

        <!-- Address List -->
        <?php if (empty($addresses)): ?>
        <div class="empty-state" style="padding:1.5rem 0;">
            <div class="empty-icon">📍</div>
            <p>No addresses yet. Add one above so you can checkout.</p>
        </div>
        <?php else: ?>

        <div style="display:flex;flex-direction:column;gap:.75rem;">
        <?php foreach ($addresses as $addr): ?>
        <div style="display:flex;align-items:center;gap:1rem;
                    padding:.8rem 1rem;border-radius:var(--radius-sm);
                    border: 1px solid <?= $addr['is_default'] ? 'var(--gold)' : 'var(--border-subtle)' ?>;
                    background: <?= $addr['is_default'] ? 'var(--gold-glow)' : 'var(--bg-elevated)' ?>;">

            <!-- Default badge or icon -->
            <div style="flex-shrink:0;font-size:1.2rem;">
                <?= $addr['is_default'] ? '⭐' : '📍' ?>
            </div>

            <!-- Address text -->
            <div style="flex:1;font-size:.88rem;">
                <?= e($addr['address']) ?>
                <?php if ($addr['is_default']): ?>
                <span class="badge" style="font-size:.65rem;margin-left:.4rem;background:var(--gold-glow);color:var(--gold);border:1px solid var(--gold);">
                    Default
                </span>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div style="display:flex;gap:.4rem;flex-shrink:0;">

                <?php if (!$addr['is_default']): ?>
                <!-- Set as Default -->
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token"   value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action"       value="set_default">
                    <input type="hidden" name="address_id"   value="<?= $addr['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm" title="Set as default">
                        ⭐ Set Default
                    </button>
                </form>
                <?php endif; ?>

                <!-- Delete -->
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token"   value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action"       value="delete_address">
                    <input type="hidden" name="address_id"   value="<?= $addr['id'] ?>">
                    <button type="submit"
                            class="btn btn-danger btn-sm"
                            data-confirm="Delete this address?"
                            <?= count($addresses) <= 1 ? 'disabled title="Cannot delete your only address."' : '' ?>>
                        🗑️
                    </button>
                </form>

            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         SECTION 3: Become a Shop Owner
         Only shown to users who are still 'customer' role.
         Once they upgrade to 'manager' this section disappears.
         ═══════════════════════════════════════════════════════ -->
    <?php if (has_role('customer')): ?>
    <div class="card" style="margin-top:1.5rem;border:1px solid var(--gold);
         background:linear-gradient(135deg,var(--bg-card) 70%,rgba(212,175,55,.06) 100%);">

        <div style="display:flex;align-items:center;gap:1.2rem;flex-wrap:wrap;">
            <div style="font-size:2.8rem;flex-shrink:0;">🏪</div>
            <div style="flex:1;min-width:220px;">
                <h3 style="margin:0 0 .3rem;color:var(--gold);">Want to sell on JEEM MALL?</h3>
                <p style="margin:0;color:var(--text-muted);font-size:.88rem;">
                    Open your own shop in under a minute. You keep the same account
                    and can switch between shopping and managing your store any time.
                </p>
            </div>
            <a href="<?= BASE_URL ?>/customer/become_manager.php"
               class="btn btn-primary"
               style="flex-shrink:0;white-space:nowrap;font-weight:700;">
                🚀 Become a Shop Owner
            </a>
        </div>

    </div>
    <?php endif; ?>

</div>

<style>
@media (max-width:650px) {
    form > div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
    form > div[style*="grid-template-columns:1fr 1fr 1fr"] { grid-template-columns: 1fr !important; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
