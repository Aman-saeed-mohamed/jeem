<?php
/**
 * =============================================================
 * JEEM MALL — Customer: Browse Shops (Dashboard)
 * =============================================================
 * Accessible to both 'customer' and 'manager' roles.
 * Managers can use this as "Shopping Mode".
 *
 * Shows all ACTIVE shops, grouped by type, with product count.
 * =============================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['customer', 'manager']);

$page_title = 'Browse Shops';
$active_nav = 'browse';

// ── Fetch all active shops with product count ─────────────────
$shops_result = $conn->query("
    SELECT  s.id,
            s.name,
            s.type,
            s.location,
            COUNT(p.id) AS product_count
    FROM    shops s
    LEFT JOIN products p ON p.shop_id = s.id
    WHERE   s.status = 'active'
    GROUP BY s.id
    ORDER BY s.name ASC
");
$shops = $shops_result->fetch_all(MYSQLI_ASSOC);

// Friendly display names for shop types
$type_labels = [
    'coffeeshop'              => 'Coffee Shop',
    'restaurant'              => 'Restaurant',
    'clothing_men'            => 'Men\'s Clothing',
    'clothing_women'          => 'Women\'s Clothing',
    'clothing_kids'           => 'Kids\' Clothing',
    'clothing_sports'         => 'Sports Clothing',
    'electronics_phones'      => 'Phones',
    'electronics_laptops'     => 'Laptops',
    'electronics_accessories' => 'Electronics Accessories',
];

// Type emoji icons for visual appeal
$type_icons = [
    'coffeeshop'              => '☕',
    'restaurant'              => '🍽️',
    'clothing_men'            => '👔',
    'clothing_women'          => '👗',
    'clothing_kids'           => '🧸',
    'clothing_sports'         => '🏋️',
    'electronics_phones'      => '📱',
    'electronics_laptops'     => '💻',
    'electronics_accessories' => '🔌',
];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-content" style="max-width:1200px;margin:0 auto;">

    <!-- Page Header -->
    <div class="page-header">
        <h1>🏪 Browse Shops</h1>
        <p>Explore our active marketplace. Click a shop to see its products.</p>
    </div>

    <?php if (empty($shops)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon">🏗️</div>
            <p>No shops are open yet. Check back soon!</p>
        </div>
    </div>

    <?php else: ?>
    <!-- ── Shops Grid ──────────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.2rem;">

        <?php foreach ($shops as $shop):
            $icon  = $type_icons[$shop['type']]  ?? '🏪';
            $label = $type_labels[$shop['type']] ?? ucfirst($shop['type']);
        ?>
        <a href="<?= BASE_URL ?>/customer/shop.php?id=<?= $shop['id'] ?>"
           style="text-decoration:none;" class="shop-card-link">
            <div class="card" style="cursor:pointer;transition:var(--transition);height:100%;">

                <!-- Shop icon header -->
                <div style="font-size:2.8rem;margin-bottom:.75rem;line-height:1;">
                    <?= $icon ?>
                </div>

                <h3 style="margin:0 0 .3rem;font-size:1.1rem;">
                    <?= e($shop['name']) ?>
                </h3>

                <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:.6rem;">
                    <?= e($label) ?> &nbsp;·&nbsp; 📍 <?= e($shop['location']) ?>
                </div>

                <div style="margin-top:auto;padding-top:.75rem;border-top:1px solid var(--border-subtle);
                            font-size:.82rem;color:var(--text-muted);">
                    <?= $shop['product_count'] ?> product<?= $shop['product_count'] !== 1 ? 's' : '' ?> available
                    <span style="float:right;color:var(--gold);">Browse →</span>
                </div>

            </div>
        </a>
        <?php endforeach; ?>

    </div>
    <!-- ── End Shops Grid ─────────────────────────────────── -->
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
