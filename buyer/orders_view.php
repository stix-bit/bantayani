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
        p.payment_status
    FROM orders o
    LEFT JOIN payment p ON o.order_id = p.order_id
    WHERE o.order_id = ? AND o.buyer_id = ?
");
$stmt->bind_param("ii", $order_id, $buyer_id);
$stmt->execute();
$order_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check for optional columns
$has_oi_quantity = false;
$cols = $conn->query("SHOW COLUMNS FROM order_items LIKE 'quantity'");
if ($cols && $cols->num_rows > 0) $has_oi_quantity = true;
$has_pool_price = false;
$cols = $conn->query("SHOW COLUMNS FROM cooperative_pools LIKE 'unit_price'");
if ($cols && $cols->num_rows > 0) $has_pool_price = true;

// Get order items (inventory and cooperative pool)
$sql = "
    SELECT 
        oi.order_item_id,
        oi.inventory_id,
        oi.pool_id,
        COALESCE(c1.crop_id, c2.crop_id) AS crop_id,
        " . ($has_oi_quantity ? "COALESCE(oi.quantity, 1) AS qty" : "1 AS qty") . ",
        COALESCE(c1.crop_name, c2.crop_name) AS crop_name,
        COALESCE(ci.price, p.unit_price) AS unit_price,
        CASE WHEN oi.pool_id IS NOT NULL THEN 1 ELSE 0 END AS is_pool
    FROM order_items oi
    LEFT JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
    LEFT JOIN crops c1 ON ci.crop_id = c1.crop_id
    LEFT JOIN cooperative_pools p ON oi.pool_id = p.pool_id
    LEFT JOIN crops c2 ON p.crop_id = c2.crop_id
    WHERE oi.order_id = ?
";
$stmt = $conn->prepare($sql);
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
            <a href="../marketplace.php" class="nav-link">Marketplace</a>
            <a href="cart.php" class="nav-link">Cart</a>
            <a href="orders.php" class="nav-link active">My Orders</a>
            <a href="../announcements.php" class="nav-link">Announcements</a>
            <a href="../invoices.php" class="nav-link">Invoices</a>
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

<div class="container" style="max-width: 900px; margin: 40px auto; padding: 0 16px;">
    <div class="cart-card">
        <h1>Order #<?= $order_id ?></h1>

        <?php if ($order_info): ?>
            <div class="order-summary" style="margin-bottom:16px;">
                <div class="summary-row" style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <strong>Order Status:</strong>
                    <span class="badge <?= $order_info['order_status'] === 'Pending' ? 'pending' : ''; ?>" style="padding:4px 10px; border-radius:999px;"><?= $order_info['order_status']; ?></span>
                </div>
                <div class="summary-row" style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <strong>Order Date:</strong>
                    <span><?= date('F d, Y g:i A', strtotime($order_info['order_date'])); ?></span>
                </div>
                <div class="summary-row" style="display:flex; justify-content:space-between;">
                    <strong>Payment Status:</strong>
                    <span><?= $order_info['payment_status'] ?? 'Pending'; ?></span>
                </div>
            </div>

            <div class="items-header">Items in this Order</div>

            <?php
            $total = 0;
            while ($row = $items->fetch_assoc()):
                $qty = (float)($row['qty'] ?? 1);
                $unit_price = (float)($row['unit_price'] ?? 0);
                $subtotal = $unit_price * $qty;
                $total += $subtotal;

                $itemImage = '';
                if (!empty($row['inventory_id'])) {
                    $item_stmt = $conn->prepare("SELECT image_path FROM crop_images WHERE inventory_id = ? ORDER BY is_primary DESC, image_id ASC LIMIT 1");
                    $item_stmt->bind_param("i", $row['inventory_id']);
                    $item_stmt->execute();
                    $item_stmt->bind_result($itemImage);
                    $item_stmt->fetch();
                    $item_stmt->close();
                } elseif (!empty($row['crop_id'])) {
                    $item_stmt = $conn->prepare("SELECT ci_img.image_path FROM crop_images ci_img JOIN crops_inventory ci ON ci_img.inventory_id = ci.inventory_id WHERE ci.crop_id = ? ORDER BY ci_img.is_primary DESC, ci_img.image_id ASC LIMIT 1");
                    $item_stmt->bind_param("i", $row['crop_id']);
                    $item_stmt->execute();
                    $item_stmt->bind_result($itemImage);
                    $item_stmt->fetch();
                    $item_stmt->close();
                }
            ?>
                <div class="cart-item-row" style="align-items:flex-start; margin-bottom:12px;">
                    <div class="cart-item-thumbnail">
                        <?php if (!empty($itemImage)): ?>
                            <img src="<?= htmlspecialchars('../' . $itemImage) ?>" alt="<?= htmlspecialchars($row['crop_name']) ?>">
                        <?php else: ?>
                            <div class="cart-item-placeholder">📦</div>
                        <?php endif; ?>
                    </div>
                    <div class="cart-item-content" style="flex:1;">
                        <div class="cart-item-name"><?= htmlspecialchars($row['crop_name']) ?> <?php if (!empty($row['is_pool'])): ?><span class="cart-item-label">(Cooperative)</span><?php endif; ?></div>
                        <div class="cart-item-meta">Qty: <?= number_format($qty, 2) ?> × ₱<?= number_format($unit_price, 2) ?></div>
                    </div>
                    <div class="cart-item-subtotal">₱<?= number_format($subtotal, 2) ?></div>
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
