status<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Farmer');
require_once __DIR__ . '/../includes/config.php';

$farmer_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$profile_img = null;

// Get user profile image
$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

// Handle order status update (tracks delivery progress)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $order_status = $_POST['order_status'] ?? '';

    // Verify this order contains items from this farmer
    $verify_stmt = $conn->prepare(
        "SELECT o.order_id FROM orders o
         JOIN order_items oi ON o.order_id = oi.order_id
         JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
         WHERE o.order_id = ? AND ci.farmer_id = ? LIMIT 1"
    );
    $verify_stmt->bind_param("ii", $order_id, $farmer_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

   if ($verify_result && $verify_result->num_rows > 0) {

    // Update order status
    $update_stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
    $update_stmt->bind_param("si", $order_status, $order_id);
    $update_stmt->execute();
    $update_stmt->close();

    // If status changed to Confirmed, mark Cash payments as Paid
    if ($order_status === 'Confirmed') {

    // Update Cash payments to Paid
    $payment_stmt = $conn->prepare("
        UPDATE payment
        SET payment_status = 'Paid',
            payment_date = NOW()
        WHERE order_id = ?
        AND payment_method = 'Cash'
    ");
    $payment_stmt->bind_param("i", $order_id);
    $payment_stmt->execute();
    $payment_stmt->close();

    // ALSO update related invoice
    $invoice_stmt = $conn->prepare("
        UPDATE invoices
        SET payment_status = 'Paid'
        WHERE invoice_id = ?
    ");
    $invoice_stmt->bind_param("i", $order_id);
    $invoice_stmt->execute();
    $invoice_stmt->close();
}

    $_SESSION['message'] = 'Order status updated successfully!';
}
    $verify_stmt->close();
}

$sql = "
SELECT DISTINCT
    o.order_id,
    o.order_date,
    o.order_status,
    GROUP_CONCAT(c.crop_name SEPARATOR ', ') as crops,
    SUM(ci.price) as total_price
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
    JOIN crops c ON ci.crop_id = c.crop_id
    WHERE ci.farmer_id = ?
    GROUP BY o.order_id, o.order_date, o.order_status
    ORDER BY o.order_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $farmer_id);
$stmt->execute();
$orders = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Farmer Orders</title>
<link rel="stylesheet" href="assets/css/farmer.css">
</head>
<body>

<nav class="navbar">
    <div class="logo-container">
        <div class="logo">BA</div>
        <div class="logo-text">BANTAY<span>ANI</span></div>
    </div>
    
    <div class="nav-links">
        <a href="../index.php" class="nav-link">Dashboard</a>
        <a href="inventory.php" class="nav-link">Inventory</a>
        <a href="orders.php" class="nav-link active">Orders</a>
        <a href="benchmarking.php" class="nav-link">Benchmarking</a>
        <a href="profile.php" class="nav-link">Profile</a>
        <a href="notifications.php" class="nav-link">Notifications</a>
    </div>
    
    <div class="user-menu">
        <?php
            $profile_img = trim($profile_img ?? '');
            $absolute_path = $_SERVER['DOCUMENT_ROOT'] . '/bantayani/' . $profile_img;
            $public_path   = '/bantayani/' . $profile_img;
        ?>
        <a href="../user/profile.php" title="View Profile">
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

<div class="container" style="max-width:900px; margin:40px auto; padding:0 20px;">
    <div class="card">
        <h1>Orders for My Crops</h1>

        <?php if (!empty($_SESSION['message'])): ?>
            <div style="background:#d4edda; color:#155724; padding:12px; border-radius:8px; margin-bottom:20px;">
                <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <?php if ($orders->num_rows === 0): ?>
            <div class="empty-state">
                <p>No orders yet. Your crops will appear here when customers place orders.</p>
            </div>
        <?php endif; ?>

        <?php while ($row = $orders->fetch_assoc()): ?>
            <div class="order">
                <div class="order-header">
                    <span class="order-id">Order #<?= $row['order_id']; ?></span>
                    <span class="badge <?= $row['order_status'] === 'Pending' ? 'pending' : ''; ?>">
                        <?= $row['order_status']; ?>
                    </span>
                </div>
                <div class="order-details">
                    <strong>Date:</strong> <?= date('F d, Y', strtotime($row['order_date'])); ?><br>
                    <strong>Crops:</strong> <?= htmlspecialchars($row['crops']); ?><br>
                    <strong>Total Price:</strong> ₱<?= number_format($row['total_price'], 2); ?>
                </div>

                <!-- Order status update form -->
                <form method="POST" style="margin-top:8px;">
                    <input type="hidden" name="order_id" value="<?= $row['order_id'] ?>">
                    <input type="hidden" name="update_order_status" value="1">
                    <div style="display:flex; gap:8px; align-items:center;">
                        <select name="order_status" style="padding:6px 12px; border:1px solid #ddd; border-radius:6px; font-family:inherit; font-size:0.9rem; background:white; cursor:pointer;">
                            <option value="Pending" <?= ($row['order_status'] === 'Pending' ? 'selected' : '') ?>>Pending</option>
                            <option value="Confirmed" <?= ($row['order_status'] === 'Confirmed' ? 'selected' : '') ?>>Confirmed</option>
                            <option value="Shipped" <?= ($row['order_status'] === 'Shipped' ? 'selected' : '') ?>>Shipped</option>
                            <option value="Delivered" <?= ($row['order_status'] === 'Delivered' ? 'selected' : '') ?>>Delivered</option>
                            <option value="Cancelled" <?= ($row['order_status'] === 'Cancelled' ? 'selected' : '') ?>>Cancelled</option>
                        </select>
                        <button type="submit" style="padding:6px 12px; background:#34675c; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.85rem;">Update Status</button>
                    </div>
                </form>
            </div>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>
