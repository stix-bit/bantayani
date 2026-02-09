<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Buyer');
include "../includes/config.php";

// Get user information
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];

$profile_img = null;
$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

// Get products
$sql = "SELECT ci.inventory_id, c.crop_name, c.unit, ci.price, ci.quantity, f.farm_name
        FROM crops_inventory ci
        JOIN crops c ON ci.crop_id = c.crop_id
        JOIN farmer_profiles f ON ci.farmer_id = f.farmer_id
        WHERE ci.quantity > 0";
$result = $conn->query($sql);

 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace - BANTAY-ANI</title>
    <link rel="stylesheet" href="assets/css/buyer.css">
</head>
<body>
    <!-- Navigation Header -->
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
            <p>Browse fresh produce from local farmers</p>
        </div>
        
        <!-- Filter and search section will be added by JavaScript -->
        
        <div class="marketplace">
            <?php if ($result->num_rows > 0) { ?>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <div class="card">
                        <b><?= htmlspecialchars($row['crop_name']) ?></b>
                        <p>Farm: <?= htmlspecialchars($row['farm_name']) ?></p>
                        <p>Price: ₱<?= number_format($row['price'], 2) ?></p>
                        <p>Available: <?= htmlspecialchars($row['quantity']) ?> <?= htmlspecialchars($row['unit']) ?></p>
                        <a class="btn" href="product.php?id=<?= $row['inventory_id'] ?>">View Details</a>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="no-results">
                    😔 No products available at the moment. Check back soon!
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
