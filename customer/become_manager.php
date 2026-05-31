<?php
/**
 * =============================================================
 * JEEM MALL — Customer Self-Onboarding: Become a Shop Owner
 * =============================================================
 * Accessible ONLY to users with role = 'customer'.
 * Managers already own a shop; admins use the admin panel.
 *
 * On valid POST — ATOMIC TRANSACTION:
 *   1. UPDATE users SET role = 'manager' WHERE id = ?
 *   2. INSERT INTO shops (manager_id, name, type, location, status)
 *   3. Update $_SESSION['user_role'] = 'manager'
 *   4. Redirect to manager_dashboard.php with a success flash
 *
 * SECURITY:
 *   - require_role('customer') blocks managers & admins
 *   - CSRF token on the POST form
 *   - All DB writes inside a single atomic transaction
 *   - Shop type validated against whitelist (never trust SELECT input)
 * =============================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// ── Strictly customer-only ────────────────────────────────────
// Managers already have a shop. Admins use the admin panel.
require_role('customer');

$user_id    = current_user_id();
$page_title = 'Become a Shop Owner';
$active_nav = 'account';

$errors = [];

// ── Valid shop types (must match the ENUM in the DB) ──────────
$shop_types = [
    'coffeeshop'               => '☕ Coffee Shop',
    'restaurant'               => '🍽️ Restaurant',
    'clothing_men'             => '👔 Clothing — Men',
    'clothing_women'           => '👗 Clothing — Women',
    'clothing_kids'            => '🧸 Clothing — Kids',
    'clothing_sports'          => '🏋️ Clothing — Sports',
    'electronics_phones'       => '📱 Electronics — Phones',
    'electronics_laptops'      => '💻 Electronics — Laptops',
    'electronics_accessories'  => '🔌 Electronics — Accessories',
];

// ── POST Handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $shop_name     = trim($_POST['shop_name']     ?? '');
    $shop_type     = $_POST['shop_type']          ?? '';
    $shop_location = trim($_POST['shop_location'] ?? '');

    // ── Validate ──────────────────────────────────────────────
    if (empty($shop_name)) {
        $errors[] = 'Shop name is required.';
    } elseif (strlen($shop_name) > 150) {
        $errors[] = 'Shop name must not exceed 150 characters.';
    }

    // Whitelist validation — never trust a <select> value directly.
    if (!array_key_exists($shop_type, $shop_types)) {
        $errors[] = 'Please select a valid shop type.';
    }

    if (empty($shop_location)) {
        $errors[] = 'Shop location is required.';
    } elseif (strlen($shop_location) > 255) {
        $errors[] = 'Location must not exceed 255 characters.';
    }

    // ── Atomic Transaction ────────────────────────────────────
    if (empty($errors)) {

        $conn->begin_transaction();

        try {
            // ── Step 1: Promote user from customer → manager ───
            $stmt = $conn->prepare(
                "UPDATE users SET role = 'manager' WHERE id = ? AND role = 'customer'"
            );
            $stmt->bind_param('i', $user_id);
            $stmt->execute();

            // Double-check the UPDATE actually changed a row.
            // (Protects against a double-submit or concurrent request.)
            if ($conn->affected_rows === 0) {
                throw new Exception('Your account is already a manager or could not be updated.');
            }
            $stmt->close();

            // ── Step 2: Create the new shop ────────────────────
            // status = 'active' immediately — self-onboarded shops
            // are live right away. Admins can deactivate if needed.
            $stmt = $conn->prepare("
                INSERT INTO shops (manager_id, name, type, location, status)
                VALUES (?, ?, ?, ?, 'active')
            ");
            $stmt->bind_param('isss', $user_id, $shop_name, $shop_type, $shop_location);
            $stmt->execute();
            $stmt->close();

            // ── Step 3: COMMIT ─────────────────────────────────
            $conn->commit();

            // ── Step 4: Update session — no re-login needed ────
            // Our auth system stores the role in $_SESSION['user_role'].
            // Updating it here immediately grants manager-level access.
            $_SESSION['user_role'] = 'manager';

            // ── Step 5: Redirect with flash message ────────────
            $_SESSION['flash_success'] =
                "🎉 Welcome, Shop Owner! Your shop \"" .
                htmlspecialchars($shop_name, ENT_QUOTES) .
                "\" is live. Add your first product below!";

            header('Location: ' . BASE_URL . '/manager/manager_dashboard.php');
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Onboarding failed: ' . $e->getMessage();
        }
    }
}

// ── Sticky form values (re-populate after validation error) ───
$prev_name     = e($_POST['shop_name']     ?? '');
$prev_type     = $_POST['shop_type']       ?? '';
$prev_location = e($_POST['shop_location'] ?? '');

include __DIR__ . '/../includes/header.php';
?>

<div class="page-content" style="max-width:680px;margin:0 auto;">

    <!-- Back link -->
    <div style="margin-bottom:1.2rem;font-size:.85rem;">
        <a href="<?= BASE_URL ?>/customer/account.php"
           style="color:var(--text-muted);">← Back to My Account</a>
    </div>

    <!-- Hero header -->
    <div style="text-align:center;margin-bottom:2rem;">
        <div style="font-size:3.5rem;margin-bottom:.5rem;">🏪</div>
        <h1 style="margin:0 0 .4rem;font-size:1.8rem;">Become a Shop Owner</h1>
        <p style="color:var(--text-muted);max-width:480px;margin:0 auto;font-size:.92rem;">
            Open your store on JEEM MALL in under a minute.
            You can still shop from other stores using the same account.
        </p>
    </div>

    <!-- Perks row -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2rem;text-align:center;">
        <?php foreach ([
            ['🚀', 'Instant Activation', 'Your shop goes live immediately'],
            ['🛍️', 'Keep Shopping',      'Switch between modes any time'],
            ['📊', 'Sales Dashboard',    'Track orders and revenue'],
        ] as [$icon, $title, $desc]): ?>
        <div class="card" style="padding:1rem .75rem;">
            <div style="font-size:1.6rem;margin-bottom:.35rem;"><?= $icon ?></div>
            <div style="font-weight:700;font-size:.85rem;margin-bottom:.2rem;"><?= $title ?></div>
            <div style="font-size:.75rem;color:var(--text-muted);"><?= $desc ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Validation errors -->
    <?php if (!empty($errors)): ?>
    <div class="alert alert-error" style="margin-bottom:1.2rem;">
        <strong>Please fix the following:</strong>
        <ul style="margin:.4rem 0 0 1.2rem;padding:0;">
            <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- ── Shop Registration Form ──────────────────────────── -->
    <div class="card">
        <h3 style="margin:0 0 1.4rem;font-size:1.1rem;">📝 Set Up Your Shop</h3>

        <form method="POST" action="" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <!-- Shop Name -->
            <div class="form-group">
                <label class="form-label" for="bm-name">
                    Shop Name <span style="color:var(--danger);">*</span>
                </label>
                <input
                    type="text"
                    id="bm-name"
                    name="shop_name"
                    class="form-control"
                    placeholder="e.g. Al-Noor Coffee House"
                    value="<?= $prev_name ?>"
                    required
                    maxlength="150"
                    autofocus
                >
            </div>

            <!-- Shop Type -->
            <div class="form-group">
                <label class="form-label" for="bm-type">
                    Shop Category <span style="color:var(--danger);">*</span>
                </label>
                <select id="bm-type" name="shop_type" class="form-control" required>
                    <option value="" disabled <?= $prev_type === '' ? 'selected' : '' ?>>
                        — Select a category —
                    </option>
                    <?php foreach ($shop_types as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $prev_type === $val ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Location -->
            <div class="form-group">
                <label class="form-label" for="bm-location">
                    Location <span style="color:var(--danger);">*</span>
                </label>
                <input
                    type="text"
                    id="bm-location"
                    name="shop_location"
                    class="form-control"
                    placeholder="e.g. Riyadh, Olaya District — Mall of Arabia"
                    value="<?= $prev_location ?>"
                    required
                    maxlength="255"
                >
            </div>

            <!-- Agreement note -->
            <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:1.2rem;
                      padding:.65rem .85rem;background:var(--bg-elevated);
                      border-radius:var(--radius-sm);border:1px solid var(--border-subtle);">
                ℹ️ By clicking <strong>Open My Shop</strong>, your account role will be upgraded from
                <em>Customer</em> to <em>Manager</em>. You can still browse and buy from other shops.
                An Admin can adjust or deactivate your shop at any time.
            </p>

            <button type="submit" class="btn btn-primary"
                    style="width:100%;font-size:1rem;padding:.8rem;font-weight:700;">
                🚀 Open My Shop
            </button>
        </form>
    </div>

</div>

<style>
@media (max-width:580px) {
    div[style*="grid-template-columns:repeat(3,1fr)"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
