<?php
// No output before authentication check
require_once __DIR__ . '/includes/auth_helper.php';
require_login();
include "includes/config.php";

// Get user information
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];

$role = $_SESSION['role'];
$isBuyer = ($role === 'Buyer');

$search_query = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$profile_img = null;
$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

// Build search filter conditions for inventory and pool separately
$inventoryFilter = ["ci.quantity > 0", "ci.harvest_status = 'Confirmed'"];
$poolFilter = ["(p.total_quantity IS NULL OR p.total_quantity > 0)"];
if ($search_query !== '') {
    $escapedSearch = $conn->real_escape_string($search_query);
    $inventoryFilter[] = "c.crop_name LIKE '%$escapedSearch%'";
    $poolFilter[] = "c.crop_name LIKE '%$escapedSearch%'";
}

// Determine sort order; for inventory and pool we apply same direction if possible
$inventorySort = 'ci.inventory_id DESC';
$poolSort = 'p.pool_id DESC';
if ($sort === 'price_asc') {
    $inventorySort = 'ci.price ASC';
    $poolSort = 'p.unit_price ASC';
} elseif ($sort === 'price_desc') {
    $inventorySort = 'ci.price DESC';
    $poolSort = 'p.unit_price DESC';
} elseif ($sort === 'name_asc') {
    $inventorySort = 'c.crop_name ASC';
    $poolSort = 'c.crop_name ASC';
} elseif ($sort === 'name_desc') {
    $inventorySort = 'c.crop_name DESC';
    $poolSort = 'c.crop_name DESC';
}

$inventoryWhereClause = implode(' AND ', $inventoryFilter);
$poolWhereClause = implode(' AND ', $poolFilter);

// Get products (individual farmer inventory)
$sql = "SELECT 
            ci.inventory_id, 
            ci.farmer_id,
            c.crop_name, 
            ci.unit, 
            ci.price, 
            ci.quantity, 
            ci.deleted_at,
            f.farm_name,
            u.first_name AS farmer_first_name,
            u.last_name  AS farmer_last_name,
            GROUP_CONCAT(ci_img.image_path ORDER BY ci_img.is_primary DESC) as images,
            ROUND(AVG(r.rating),1) as avg_rating,
            COUNT(r.rating_id) as total_ratings
        FROM crops_inventory ci
        JOIN crops c ON ci.crop_id = c.crop_id
        JOIN farmer_profiles f ON ci.farmer_id = f.farmer_id
        JOIN users u ON ci.farmer_id = u.user_id
        LEFT JOIN crop_images ci_img ON ci.inventory_id = ci_img.inventory_id
        LEFT JOIN ratings r ON ci.inventory_id = r.inventory_id
        WHERE $inventoryWhereClause AND ci.deleted_at IS NULL
        GROUP BY ci.inventory_id
        ORDER BY $inventorySort";
$result = $conn->query($sql);


// Check if cooperative_pools has unit_price (from migration)
$has_pool_price = false;
$cols = $conn->query("SHOW COLUMNS FROM cooperative_pools LIKE 'unit_price'");
if ($cols && $cols->num_rows > 0) $has_pool_price = true;

// Get cooperative pools (large volume – any buyer can see and order)
$pool_sql = "
    SELECT 
        p.pool_id, 
        p.crop_id, 
        p.total_quantity, 
        p.unit
        " . ($has_pool_price ? ", p.unit_price" : "") . ",
        c.crop_name,
        p.deleted_at,
        (
            SELECT ci_img.image_path
            FROM crops_inventory ci
            JOIN crop_images ci_img 
                ON ci.inventory_id = ci_img.inventory_id
            WHERE ci.crop_id = p.crop_id
            ORDER BY ci_img.is_primary DESC, ci_img.image_id ASC
            LIMIT 1
        ) AS pool_image
    FROM cooperative_pools p
    JOIN crops c ON p.crop_id = c.crop_id
    WHERE $poolWhereClause AND p.deleted_at IS NULL
    ORDER BY $poolSort
";
$pools_result = $conn->query($pool_sql);
$pools = $pools_result ? $pools_result->fetch_all(MYSQLI_ASSOC) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace - BANTAY-ANI</title>
    <link rel="stylesheet" href="buyer/assets/css/buyer.css">
</head>
<body>
    <!-- Navigation Header -->
    <nav class="navbar">
        <div class="logo-container">
            <div class="logo">BA</div>
            <div class="logo-text">BANTAY<span>ANI</span></div>
        </div>
        
        <div class="nav-links">
            <?php if (!$isBuyer): ?>
                <a href="index.php" class="nav-link">Dashboard</a>
                <a href="marketplace.php" class="nav-link active">Marketplace</a>
                <a href="./farmer/inventory.php" class="nav-link">My Inventory</a>
                <a href="./farmer/orders.php" class="nav-link">Orders</a>
                <a href="./farmer/cooperative.php" class="nav-link">Cooperative</a>
                <a href="reports.php" class="nav-link">Reports</a>
                <a href="announcements.php" class="nav-link">Annunsyo</a>
                <a href="invoices.php" class="nav-link">Invoices</a>
                <a href="./farmer/notifications.php" class="nav-link">Notifications</a>
                
            <?php elseif ($isBuyer): ?>
                <a href="index.php" class="nav-link">Dashboard</a>
                <a href="marketplace.php" class="nav-link active">Marketplace</a>
                <a href="./buyer/cart.php" class="nav-link">Cart</a>
                <a href="./buyer/orders.php" class="nav-link">My Orders</a>
                <a href="reports.php" class="nav-link">Reports</a>
                <a href="announcements.php" class="nav-link">Annunsyo</a>
                <a href="invoices.php" class="nav-link">Invoices</a>
                <a href="./buyer/notifications.php" class="nav-link">Notifications</a>
            <?php endif; ?>
        </div>
        
        <div class="user-menu">
            <?php
                $profile_img = trim($profile_img ?? '');
                $absolute_path = $_SERVER['DOCUMENT_ROOT'] . '/bantayani/' . $profile_img;
                $public_path   = '/bantayani/' . $profile_img;
            ?>
            <a href="<?= $isBuyer ? 'buyer/profile.php' : 'farmer/profile.php' ?>" title="View Profile">
                <div class="user-avatar">
                    <?php if (!empty($profile_img) && file_exists($absolute_path)): ?>
                        <img src="<?= htmlspecialchars($public_path) ?>"
                             alt="Profile"
                             style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                    <?php else: ?>
                        <?= strtoupper(substr($first_name, 0, 1)) ?>
                    <?php endif; ?>
                </div>
            </a>
            <a href="/bantayani/user/logout.php" class="logout-btn" style="text-decoration:none; display:inline-block;">
                Log Out
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <div class="page-header">
            <h1>🌾 Marketplace</h1>
            <p>Mag-browse ng mga sariwang ani mula sa mga lokal na magsasaka at mga pool ng kooperatiba</p>
        </div>

        <div class="filter-section" style="max-width: 900px; margin: 0 auto 24px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <form method="GET" action="marketplace.php" style="display:flex; flex:1; gap:10px; flex-wrap: wrap;">
                <input type="text" name="q" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search crops!" style="flex:1; padding:10px 14px; border:1px solid var(--border); border-radius:12px;">
                <select name="sort" style="padding:10px 14px; border:1px solid var(--border); border-radius:12px;">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                    <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
                    <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price Low-High</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price High-Low</option>
                </select>
                <button type="submit" class="btn" style="padding:10px 14px;">Search</button>
            </form>
        </div>

        <?php if (!empty($pools)): ?>
        <section class="marketplace-section" style="margin-bottom: 32px;">
            <h2 style="font-size: 1.25rem; margin-bottom: 12px; color: #0c5c4c;">🤝 Cooperative Pools (Large Volume)</h2>
            <p style="color: #4c5662; margin-bottom: 16px;">Mag-order nang maramihan mula sa pinagsama-samang ani—natutupad ang malalaking order na hindi kayang ibigay ng mga indibidwal na magsasaka nang mag-isa.</p>
            <div class="marketplace" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px;">
                <?php foreach ($pools as $p): ?>
                <div class="card" style="border-left: 4px solid #1f8a70;">
                    <?php if (!empty($p['pool_image'])): ?>
                        <div style="width: 100%; height: 120px; margin-bottom: 12px; overflow: hidden; border-radius: 8px;">
                            <img src="<?= htmlspecialchars($p['pool_image']) ?>" 
                                alt="<?= htmlspecialchars($p['crop_name']) ?>" 
                                style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    <?php else: ?>
                        <div style="width: 100%; height: 120px; background: #f9fafb; border-radius: 8px; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; color: #666;">
                            <i class="fa-solid fa-image" style="font-size: 2rem;"></i>
                        </div>
                    <?php endif; ?>
                    <b><?= htmlspecialchars($p['crop_name']) ?></b>
                    <p style="margin: 6px 0;">Cooperative pool</p>
                    <?php if ($has_pool_price && isset($p['unit_price']) && $p['unit_price'] != null): ?>
                    <p>Price: ₱<?= number_format((float)$p['unit_price'], 2) ?> / <?= htmlspecialchars($p['unit']) ?></p>
                    <?php endif; ?>
                    <p>Available: <?= number_format((float)($p['total_quantity'] ?? 0), 2) ?> <?= htmlspecialchars($p['unit']) ?></p>
                    <?php if ($isBuyer): ?>
                        <a class="btn" href="buyer/product_pool.php?pool_id=<?= (int)$p['pool_id'] ?>">View &amp; Order</a>
                    <?php else: ?>
                        <button class="btn" style="background:#ccc; cursor:not-allowed;" disabled>View Only</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <h2 style="font-size: 1.25rem; margin-bottom: 12px; color: #0c5c4c;">From individual farmers</h2>
        <div class="marketplace">
            <?php if ($result->num_rows > 0) { ?>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <div class="card">
                        <?php 
                        $images = $row['images'] ? explode(',', $row['images']) : [];
                        $primary_image = !empty($images) ? $images[0] : null;
                        ?>
                        <?php if ($primary_image): ?>
                            <div style="width: 100%; height: 120px; margin-bottom: 12px; overflow: hidden; border-radius: 8px;">
                                <img src="<?= htmlspecialchars($primary_image) ?>" 
                                     alt="<?= htmlspecialchars($row['crop_name']) ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                        <?php else: ?>
                            <div style="width: 100%; height: 120px; background: #f9fafb; border-radius: 8px; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; color: #666;">
                                <i class="fa-solid fa-image" style="font-size: 2rem;"></i>
                            </div>
                        <?php endif; ?>
                        <b><?= htmlspecialchars($row['crop_name']) ?></b>
                        <p>Farm: <?= htmlspecialchars($row['farm_name']) ?></p>
                        <p>
                            Farmer: 
                            <a href="./user/view_profile.php?id=<?= (int) $row['farmer_id'] ?>">
                                <?= htmlspecialchars(trim($row['farmer_first_name'] . ' ' . $row['farmer_last_name'])) ?>
                            </a>
                        </p>
                        <p>Price: ₱<?= number_format($row['price'], 2) ?></p>
                        <p>Available: <?= htmlspecialchars($row['quantity']) ?> <?= htmlspecialchars($row['unit']) ?></p>
                        <p>Rating: <?php if ($row['avg_rating']): ?>⭐ <?= $row['avg_rating'] ?> / 5 (<?= $row['total_ratings'] ?> reviews)<?php else: ?>No ratings yet<?php endif; ?></p>

                        <?php if ($isBuyer): ?>
                            <!-- <form action="buyer/submit_rating.php" method="POST" style="margin-top:8px;">
                                <input type="hidden" name="inventory_id" value="<?= $row['inventory_id'] ?>">
                                <select name="rating" required>
                                    <option value="">Rate</option>
                                    <option value="5">⭐⭐⭐⭐⭐ (5)</option>
                                    <option value="4">⭐⭐⭐⭐ (4)</option>
                                    <option value="3">⭐⭐⭐ (3)</option>
                                    <option value="2">⭐⭐ (2)</option>
                                    <option value="1">⭐ (1)</option>
                                </select>

                                <input type="text" 
                                    name="comment" 
                                    placeholder="Leave comment (optional)" 
                                    style="width:100%; margin-top:4px;">

                                <button type="submit" style="margin-top:4px;">
                                    Submit
                                </button>
                            </form> -->

                            <a class="btn" href="buyer/product.php?id=<?= $row['inventory_id'] ?>">View Product</a>
                        <?php else: ?>
                            <button class="btn" style="background:#ccc; cursor:not-allowed;" disabled>
                                View Only
                            </button>
                        <?php endif; ?>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="no-results">
                    Walang mga indibidwal na listahan sa ngayon. Tingnan ang mga cooperative pool sa itaas o bumalik kaagad!
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>© <?= date('Y') ?> BANTAY-ANI Farm-to-Market System. All rights reserved.</p>
        <p style="margin-top: 8px; font-size: 0.85rem;">
            Connecting farmers and buyers, reducing waste, supporting local agriculture.
        </p>
    </footer>
    
    <script src="assets/js/marketplace.js"></script>
</body>
</html>
