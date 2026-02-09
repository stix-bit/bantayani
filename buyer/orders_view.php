<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Buyer');
require_once __DIR__ . '/../includes/config.php';

$order_id = $_GET['id'] ?? 0;
$buyer_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$profile_img = null;

$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

// Get order details
$stmt = $conn->prepare("
    SELECT 
        o.order_id,
        o.order_date,
        o.order_status,
        d.delivery_status,
        p.payment_status
    FROM orders o
    LEFT JOIN deliveries d ON o.order_id = d.order_id
    LEFT JOIN payment p ON o.order_id = p.order_id
    WHERE o.order_id = ? AND o.buyer_id = ?
");
$stmt->bind_param("ii", $order_id, $buyer_id);
$stmt->execute();
$order_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get order items
$stmt = $conn->prepare("
    SELECT 
        c.crop_name,
        ci.price
    FROM order_items oi
    JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
    JOIN crops c ON ci.crop_id = c.crop_id
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Details</title>
<link rel="stylesheet" href="assets/css/buyer.css">

<style>

</style>
</head>
<body>

<nav class="navbar">
    <div class="logo-container">
        <div class="logo">BA</div>
        <div class="logo-text">BANTAY<span>ANI</span></div>
    </div>
    
    <div class="nav-links">
        <a href="../index.php" class="nav-link">Dashboard</a>
        <a href="marketplace.php" class="nav-link">Marketplace</a>
        <a href="cart.php" class="nav-link">Cart</a>
        <a href="orders.php" class="nav-link active">My Orders</a>
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

<div class="container">
    <div class="card">
        <h1>Order #<?= $order_id ?></h1>

        <?php if ($order_info): ?>
            <div class="order-summary">
                <div class="summary-row">
                    <strong>Order Status:</strong>
                    <span class="badge <?= $order_info['order_status'] === 'Pending' ? 'pending' : ''; ?>">
                        <?= $order_info['order_status']; ?>
                    </span>
                </div>
                <div class="summary-row">
                    <strong>Order Date:</strong>
                    <span><?= date('F d, Y g:i A', strtotime($order_info['order_date'])); ?></span>
                </div>
                <div class="summary-row">
                    <strong>Delivery Status:</strong>
                    <span><?= $order_info['delivery_status'] ?? 'Pending'; ?></span>
                </div>
                <div class="summary-row">
                    <strong>Payment Status:</strong>
                    <span><?= $order_info['payment_status'] ?? 'Pending'; ?></span>
                </div>
            </div>

            <div class="items-header">Items in this Order</div>

            <?php 
            $total = 0;
            while ($row = $items->fetch_assoc()): 
                $subtotal = $row['price'];
                $total += $subtotal;
            ?>
                <div class="item">
                    <div class="item-row">
                        <span class="item-name"><?= htmlspecialchars($row['crop_name']) ?></span>
                        <span style="font-weight:600;">₱<?= number_format($subtotal, 2) ?></span>
                    </div>
                </div>
            <?php endwhile; ?>

            <div style="background:var(--beige); padding:16px; border-radius:12px; margin-top:20px; text-align:right;">
                <strong style="font-size:1.1rem; color:var(--green-dark);">
                    Total: ₱<?= number_format($total, 2) ?>
                </strong>
            </div>

            <a href="orders.php" class="back-link">← Back to My Orders</a>
        <?php else: ?>
            <p style="color:#999; padding:20px 0;">Order not found or you do not have permission to view this order.</p>
            <a href="orders.php" class="back-link">← Back to My Orders</a>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
