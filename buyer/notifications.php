<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Buyer');
include __DIR__ . '/../includes/config.php';

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$profile_img = null;
$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

// Weather Alerts (last 24 hours)
$weather_alerts = [];
$wa = $conn->query("
    SELECT * FROM weather_alerts
    WHERE created_at > NOW() - INTERVAL 1 DAY
    ORDER BY CASE severity WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 ELSE 3 END, created_at DESC
    LIMIT 10
");
if ($wa) $weather_alerts = $wa->fetch_all(MYSQLI_ASSOC);

// Announcements (for Buyers / All, active, not expired)
$announcements = [];
$audience = 'Buyers';
$stmt = $conn->prepare("
    SELECT a.announcement_id, a.title, a.content, a.announcement_type, a.priority, a.created_at,
           CONCAT(u.first_name, ' ', u.last_name) AS creator_name
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.user_id
    WHERE a.is_active = 1
      AND (a.target_audience = 'All' OR a.target_audience = ?)
      AND (a.expires_at IS NULL OR a.expires_at > NOW())
    ORDER BY CASE a.priority WHEN 'Urgent' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END, a.created_at DESC
    LIMIT 10
");
$stmt->bind_param("s", $audience);
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Order Alerts (buyer's recent orders with status)
$order_alerts = [];
$ord = $conn->prepare("
    SELECT o.order_id, o.order_date, o.order_status, p.payment_status
    FROM orders o
    LEFT JOIN payment p ON o.order_id = p.order_id
    WHERE o.buyer_id = ?
    ORDER BY o.order_date DESC
    LIMIT 10
");
$ord->bind_param("i", $user_id);
$ord->execute();
$order_alerts = $ord->get_result()->fetch_all(MYSQLI_ASSOC);
$ord->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - BANTAY-ANI</title>
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
        <a href="marketplace.php" class="nav-link">Marketplace</a>
        <a href="cart.php" class="nav-link">Cart</a>
        <a href="orders.php" class="nav-link">My Orders</a>
        <a href="profile.php" class="nav-link">Profile</a>
        <a href="ratings.php" class="nav-link">Ratings</a>
        <a href="notifications.php" class="nav-link active">Notifications</a>
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

<div class="main-container">
    <div class="page-header">
        <h1>🔔 Notifications</h1>
        <p>Weather alerts, announcements, and order updates</p>
    </div>

    <!-- Weather Alerts -->
    <section class="marketplace-section" style="margin-bottom: 32px;">
        <h2 style="font-size: 1.25rem; margin-bottom: 12px; color: #0c5c4c;">🌤️ Weather Alerts</h2>
        <p style="color: #4c5662; margin-bottom: 16px;">Current weather advisories that may affect delivery or availability.</p>
        <?php if (empty($weather_alerts)): ?>
            <div class="card" style="padding: 24px; text-align: center; color: var(--text-light);">
                No active weather alerts.
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($weather_alerts as $alert): ?>
                    <div class="card" style="border-left: 4px solid <?= $alert['severity'] === 'High' ? '#ef4444' : ($alert['severity'] === 'Medium' ? '#f59e0b' : '#10b981') ?>;">
                        <b style="display: block; margin-bottom: 6px;"><?= htmlspecialchars($alert['title']) ?></b>
                        <p style="margin: 0 0 8px 0;"><?= htmlspecialchars($alert['message']) ?></p>
                        <span class="badge" style="background: <?= $alert['severity'] === 'High' ? 'rgba(239,68,68,0.15)' : ($alert['severity'] === 'Medium' ? 'rgba(245,158,11,0.15)' : 'rgba(16,185,129,0.15)') ?>; color: <?= $alert['severity'] === 'High' ? '#dc2626' : ($alert['severity'] === 'Medium' ? '#d97706' : '#059669') ?>;"><?= htmlspecialchars($alert['severity']) ?></span>
                        <p style="margin: 8px 0 0 0; font-size: 0.85rem; color: var(--text-light);"><?= date('M d, Y h:i A', strtotime($alert['created_at'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Announcements -->
    <section class="marketplace-section" style="margin-bottom: 32px;">
        <h2 style="font-size: 1.25rem; margin-bottom: 12px; color: #0c5c4c;">📢 Announcements</h2>
        <p style="color: #4c5662; margin-bottom: 16px;">Latest news and updates from BANTAY-ANI.</p>
        <?php if (empty($announcements)): ?>
            <div class="card" style="padding: 24px; text-align: center; color: var(--text-light);">
                No announcements at this time.
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($announcements as $ann): ?>
                    <a href="../announcements.php?view=<?= (int)$ann['announcement_id'] ?>" class="card" style="text-decoration: none; color: inherit; display: block;">
                        <b style="display: block; margin-bottom: 6px;"><?= htmlspecialchars($ann['title']) ?></b>
                        <p style="margin: 0 0 8px 0;"><?= nl2br(htmlspecialchars(substr($ann['content'], 0, 200))) ?><?= strlen($ann['content']) > 200 ? '...' : '' ?></p>
                        <span class="badge"><?= htmlspecialchars($ann['announcement_type']) ?></span>
                        <p style="margin: 8px 0 0 0; font-size: 0.85rem; color: var(--text-light);"><?= htmlspecialchars($ann['creator_name']) ?> · <?= date('M d, Y', strtotime($ann['created_at'])) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Order Alerts -->
    <section class="marketplace-section" style="margin-bottom: 32px;">
        <h2 style="font-size: 1.25rem; margin-bottom: 12px; color: #0c5c4c;">📦 Order Alerts</h2>
        <p style="color: #4c5662; margin-bottom: 16px;">Your recent orders and status updates.</p>
        <?php if (empty($order_alerts)): ?>
            <div class="card" style="padding: 24px; text-align: center; color: var(--text-light);">
                No orders yet. <a href="marketplace.php" style="color: var(--green);">Browse the marketplace</a> to place an order.
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($order_alerts as $o): ?>
                    <div class="order">
                        <div class="order-header">
                            <span class="order-id">Order #<?= (int)$o['order_id'] ?></span>
                            <span class="badge <?= $o['order_status'] === 'Pending' ? 'pending' : '' ?>"><?= htmlspecialchars($o['order_status']) ?></span>
                        </div>
                        <div class="order-details">
                            <strong>Date:</strong> <?= date('F d, Y', strtotime($o['order_date'])) ?><br>
                            <strong>Payment:</strong> <?= htmlspecialchars($o['payment_status'] ?? 'Pending') ?>
                        </div>
                        <div class="order-actions">
                            <a class="btn" href="orders_view.php?id=<?= (int)$o['order_id'] ?>">View Order</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<footer class="footer">
    <p>© <?= date('Y') ?> BANTAY-ANI Farm-to-Market System. All rights reserved.</p>
    <p style="margin-top: 8px; font-size: 0.85rem;">Connecting farmers and buyers, reducing waste, supporting local agriculture.</p>
</footer>

</body>
</html>
