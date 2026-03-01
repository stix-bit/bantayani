<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Buyer');
include "../includes/config.php";

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$profile_img = null;
$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

$pool_id = (int)($_GET['pool_id'] ?? 0);
if ($pool_id <= 0) {
    header('Location: marketplace.php');
    exit;
}

$has_unit_price = false;
$cols = $conn->query("SHOW COLUMNS FROM cooperative_pools LIKE 'unit_price'");
if ($cols && $cols->num_rows > 0) $has_unit_price = true;

$select_extras = ($has_unit_price ? ", p.unit_price" : "");
$sql = "SELECT p.pool_id, p.crop_id, p.total_quantity, c.crop_name, p.unit $select_extras
        FROM cooperative_pools p
        JOIN crops c ON p.crop_id = c.crop_id
        WHERE p.pool_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $pool_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header('Location: marketplace.php');
    exit;
}

$available = (float)($row['total_quantity'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cooperative Pool - <?= htmlspecialchars($row['crop_name']) ?></title>
    <link rel="stylesheet" href="assets/css/buyer.css">
</head>
<body>

<nav class="navbar">
    <div class="logo-container">
        <div class="logo">BA</div>
        <div class="logo-text">BANTAY<span>ANI</span></div>
    </div>
    <div class="nav-links">
        <a href="../index.php" class="nav-link">Dashboard</a>
        <a href="marketplace.php" class="nav-link active">Marketplace</a>
        <a href="cart.php" class="nav-link">Cart</a>
        <a href="orders.php" class="nav-link">My Orders</a>
        <a href="profile.php" class="nav-link">Profile</a>
        <a href="ratings.php" class="nav-link">Ratings</a>
        <a href="notifications.php" class="nav-link">Notifications</a>
    </div>
    <div class="user-menu">
        <?php
            $profile_img = trim($profile_img ?? '');
            $absolute_path = $_SERVER['DOCUMENT_ROOT'] . '/bantayani/' . $profile_img;
            $public_path   = '/bantayani/' . $profile_img;
        ?>
        <a href="profile.php" title="View Profile">
            <div class="user-avatar">
                <?php if (!empty($profile_img) && file_exists($absolute_path)): ?>
                    <img src="<?= htmlspecialchars($public_path) ?>" alt="Profile" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                <?php else: ?>
                    <?= strtoupper(substr($first_name, 0, 1)) ?>
                <?php endif; ?>
            </div>
        </a>
        <a href="/bantayani/user/logout.php" class="logout-btn" style="text-decoration:none; display:inline-block;">Log Out</a>
    </div>
</nav>

<div class="card" style="max-width: 480px; margin: 24px auto;">
    <p style="margin: 0 0 8px 0; font-size: 0.9rem; color: #1f8a70;">🤝 Cooperative pool</p>
    <h2><?= htmlspecialchars($row['crop_name']) ?></h2>
    <p>Produce from multiple farmers pooled for large-volume orders.</p>
    <?php if ($has_unit_price && isset($row['unit_price']) && $row['unit_price'] != null): ?>
        <p><strong>Price:</strong> ₱<?= number_format((float)$row['unit_price'], 2) ?> per <?= htmlspecialchars($row['unit']) ?></p>
    <?php endif; ?>
    <p><strong>Available:</strong> <?= number_format($available, 2) ?> <?= htmlspecialchars($row['unit']) ?></p>

    <?php if ($available > 0): ?>
    <form method="post" action="cart.php">
        <input type="hidden" name="pool_id" value="<?= (int)$row['pool_id'] ?>">
        <label for="qty">Quantity (<?= htmlspecialchars($row['unit']) ?>)</label>
        <input type="number" id="qty" name="qty" min="0.01" step="0.01" max="<?= $available ?>" value="1" required style="width: 120px; padding: 8px; margin: 8px 0;">
        <button type="submit" class="btn">Add to Cart</button>
    </form>
    <?php else: ?>
    <p style="color: #6b7280;">This pool is currently empty. Check back later.</p>
    <?php endif; ?>

    <p style="margin-top: 16px;"><a href="marketplace.php" style="color: #1f8a70;">← Back to Marketplace</a></p>
</div>

</body>
</html>
