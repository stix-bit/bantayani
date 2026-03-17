<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Buyer');
require_once __DIR__ . '/../includes/config.php';

$buyer_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$profile_img = null;
$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

$sql = "
SELECT 
    o.order_id,
    o.order_date,
    o.order_status,
    p.payment_status
FROM orders o
LEFT JOIN payment p ON o.order_id = p.order_id
WHERE o.buyer_id = ?
ORDER BY o.order_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $buyer_id);
$stmt->execute();
$orders = $stmt->get_result();


?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BantayAni | My Orders</title>
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
            <a href="../announcements.php" class="nav-link">Annunsyo</a>
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
        <h1>My Orders</h1>

        <?php if (!empty($_SESSION['message'])): ?>
            <div style="background:#d4edda; color:#155724; padding:12px; border-radius:8px; margin-bottom:20px;">
                <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <?php if ($orders->num_rows === 0): ?>
            <div class="empty-state">
                <p>You have no orders yet. Start shopping at the <a href="marketplace.php" style="color:var(--green);">Marketplace</a>!</p>
            </div>
        <?php endif; ?>

        <?php while ($row = $orders->fetch_assoc()): ?>
            <div class="cart-item-row" style="flex-wrap: wrap; align-items: center; gap: 12px;">
                <div>
                    <div class="cart-item-name">Order #<?= $row['order_id']; ?></div>
                    <div class="cart-item-meta">Date: <?= date('F d, Y', strtotime($row['order_date'])); ?></div>
                    <div class="cart-item-meta">Payment: <?= $row['payment_status'] ?? 'Pending'; ?></div>
                </div>
                <div style="min-width: 220px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end;">
                    <span class="badge <?= $row['order_status'] === 'Pending' ? 'pending' : ($row['order_status'] === 'Cancelled' ? 'cancelled' : '') ;?>" style="padding: 4px 10px; border-radius: 4px; display: inline-flex; align-items: center;"><?= $row['order_status']; ?></span>
                    <a class="btn" href="orders_view.php?id=<?= $row['order_id'] ?>">View</a>
                    <?php if ($row['order_status'] === 'Pending'): ?>
                        <form method="POST" action="cancel_order.php" style="display:inline;">
                            <input type="hidden" name="order_id" value="<?= $row['order_id'] ?>">
                            <button type="submit" class="btn btn-cancel">Cancel</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>
