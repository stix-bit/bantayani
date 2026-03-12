<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Farmer');
include __DIR__ . '/../includes/config.php';

$farmer_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$profile_img = null;
$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

$region = null;
$region_stmt = $conn->prepare("SELECT region FROM farmer_profiles WHERE farmer_id = ?");
if ($region_stmt) {
    $region_stmt->bind_param('i', $farmer_id);
    $region_stmt->execute();
    $region_result = $region_stmt->get_result();
    if ($region_result) {
        $row = $region_result->fetch_assoc();
        if ($row && !empty($row['region'])) {
            $region = $row['region'];
        }
    }
    $region_stmt->close();
}

// Latest Weather Alert for Farmer's Region (last 24 hours)
$weather_alerts = [];
$wa = $conn->prepare("
    SELECT * FROM weather_alerts
    WHERE created_at > NOW() - INTERVAL 1 DAY
      AND (region = ? OR region IS NULL)
    ORDER BY created_at DESC, CASE severity WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 ELSE 3 END
    LIMIT 1
");
if ($wa) {
    $wa->bind_param('s', $region);
    $wa->execute();
    $weather_alerts = $wa->get_result()->fetch_all(MYSQLI_ASSOC);
    $wa->close();
}

// Announcements (for Farmers / All, active, not expired)
$announcements = [];
$audience = 'Farmers';
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

// Harvest Alerts (upcoming or due harvests – Scheduled, harvest_date within 14 days)
$harvest_alerts = [];
$harv = $conn->prepare("
    SELECT ci.inventory_id, ci.harvest_date, ci.quantity, ci.harvest_status, c.crop_name, ci.unit
    FROM crops_inventory ci
    JOIN crops c ON ci.crop_id = c.crop_id
    WHERE ci.farmer_id = ?
      AND ci.harvest_date IS NOT NULL
      AND ci.harvest_date != '0000-00-00'
      AND (ci.harvest_status IS NULL OR ci.harvest_status = 'Scheduled')
      AND ci.harvest_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
    ORDER BY ci.harvest_date ASC
    LIMIT 10
");
$harv->bind_param("i", $farmer_id);
$harv->execute();
$harvest_alerts = $harv->get_result()->fetch_all(MYSQLI_ASSOC);
$harv->close();

// Order Alerts (recent orders containing this farmer's crops)
$order_alerts = [];
$ord = $conn->prepare("
    SELECT o.order_id, o.order_date, o.order_status,
           GROUP_CONCAT(c.crop_name SEPARATOR ', ') AS crops,
           SUM(COALESCE(oi.quantity, 1) * ci.price) AS total_price
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
    JOIN crops c ON ci.crop_id = c.crop_id
    WHERE ci.farmer_id = ?
    GROUP BY o.order_id, o.order_date, o.order_status
    ORDER BY o.order_date DESC
    LIMIT 10
");
$ord->bind_param("i", $farmer_id);
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
                <a href="../marketplace.php" class="nav-link">Marketplace</a>
                <a href="inventory.php" class="nav-link">My Inventory</a>
                <a href="orders.php" class="nav-link">Orders</a>
                <a href="cooperative.php" class="nav-link">Cooperative</a>
                <a href="../reports.php" class="nav-link">Reports</a>
                <a href="../announcements.php" class="nav-link">Announcements</a>
                <a href="../invoices.php" class="nav-link">Invoices</a>
                <a href="notifications.php" class="nav-link active">Notifications</a>
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
                    <img src="<?= htmlspecialchars($public_path) ?>" alt="Profile" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                <?php else: ?>
                    <?= strtoupper(substr($first_name, 0, 1)) ?>
                <?php endif; ?>
            </div>
        </a>
        <a href="/bantayani/user/logout.php" class="logout-btn" style="text-decoration:none; display:inline-block;">Log Out</a>
    </div>
</nav>

<div class="container" style="max-width: 900px; margin: 40px auto; padding: 0 20px;">
    <div class="card">
        <h1>🔔 Notifications</h1>
        <p style="color: var(--text-light); margin-bottom: 24px;">Weather alerts, announcements, harvest reminders, and order updates.</p>

        <?php if (!empty($_SESSION['message'])): ?>
            <div style="background:#d4edda; color:#155724; padding:12px; border-radius:8px; margin-bottom:20px;">
                <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <!-- Weather Alerts -->
        <h2 style="font-size: 1.2rem; margin: 24px 0 12px 0; color: var(--green-dark);">🌤️ Weather Alerts</h2>
        <p style="color: var(--text-light); margin-bottom: 12px; font-size: 0.95rem;">Current weather advisories for your area.</p>
        <?php if (empty($weather_alerts)): ?>
            <div class="order" style="padding: 20px; text-align: center; color: var(--text-light);">
                No active weather alerts.
            </div>
        <?php else: ?>
            <?php foreach ($weather_alerts as $alert): ?>
                <div class="order" style="border-left: 4px solid <?= $alert['severity'] === 'High' ? '#ef4444' : ($alert['severity'] === 'Medium' ? '#f59e0b' : '#10b981') ?>;">
                    <div class="order-header">
                        <span class="order-id"><?= htmlspecialchars($alert['title']) ?></span>
                        <span class="badge"><?= htmlspecialchars($alert['severity']) ?></span>
                    </div>
                    <div class="order-details"><?= htmlspecialchars($alert['message']) ?></div>
                    <p style="margin: 8px 0 0 0; font-size: 0.85rem; color: var(--text-light);"><?= date('M d, Y h:i A', strtotime($alert['created_at'])) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Announcements -->
        <h2 style="font-size: 1.2rem; margin: 24px 0 12px 0; color: var(--green-dark);">📢 Announcements</h2>
        <p style="color: var(--text-light); margin-bottom: 12px; font-size: 0.95rem;">Latest news and updates from BANTAY-ANI.</p>
        <?php if (empty($announcements)): ?>
            <div class="order" style="padding: 20px; text-align: center; color: var(--text-light);">
                No announcements at this time.
            </div>
        <?php else: ?>
            <?php foreach ($announcements as $ann): ?>
                <a href="../announcements.php?view=<?= (int)$ann['announcement_id'] ?>" style="text-decoration: none; color: inherit; display: block;">
                    <div class="order">
                        <div class="order-header">
                            <span class="order-id"><?= htmlspecialchars($ann['title']) ?></span>
                            <span class="badge"><?= htmlspecialchars($ann['announcement_type']) ?></span>
                        </div>
                        <div class="order-details"><?= nl2br(htmlspecialchars(substr($ann['content'], 0, 180))) ?><?= strlen($ann['content']) > 180 ? '...' : '' ?></div>
                        <p style="margin: 8px 0 0 0; font-size: 0.85rem; color: var(--text-light);"><?= htmlspecialchars($ann['creator_name']) ?> · <?= date('M d, Y', strtotime($ann['created_at'])) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Harvest Alerts -->
        <h2 style="font-size: 1.2rem; margin: 24px 0 12px 0; color: var(--green-dark);">🌾 Harvest Alerts</h2>
        <p style="color: var(--text-light); margin-bottom: 12px; font-size: 0.95rem;">Upcoming and due harvests. Confirm or cancel in <a href="inventory.php" style="color: var(--green);">Inventory</a>.</p>
        <?php if (empty($harvest_alerts)): ?>
            <div class="order" style="padding: 20px; text-align: center; color: var(--text-light);">
                No upcoming harvests in the next 14 days.
            </div>
        <?php else: ?>
            <?php foreach ($harvest_alerts as $h): ?>
                <?php
                $due = $h['harvest_date'] && $h['harvest_date'] !== '0000-00-00' ? strtotime($h['harvest_date']) : 0;
                $is_past = $due && $due < strtotime('today');
                ?>
                <div class="order" style="border-left: 4px solid <?= $is_past ? '#f59e0b' : 'var(--green)' ?>;">
                    <div class="order-header">
                        <span class="order-id"><?= htmlspecialchars($h['crop_name']) ?></span>
                        <span class="badge"><?= $is_past ? 'Due' : 'Scheduled' ?></span>
                    </div>
                    <div class="order-details">
                        <strong>Harvest date:</strong> <?= $due ? date('F d, Y', $due) : 'Not set' ?><br>
                        <strong>Quantity:</strong> <?= htmlspecialchars($h['quantity'] . ' ' . $h['unit']) ?>
                    </div>
                    <div class="order-actions">
                        <a class="btn" href="inventory.php">View in Inventory</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Order Alerts -->
        <h2 style="font-size: 1.2rem; margin: 24px 0 12px 0; color: var(--green-dark);">📦 Order Alerts</h2>
        <p style="color: var(--text-light); margin-bottom: 12px; font-size: 0.95rem;">Recent orders containing your crops.</p>
        <?php if (empty($order_alerts)): ?>
            <div class="order" style="padding: 20px; text-align: center; color: var(--text-light);">
                No orders yet. Your crops will appear here when buyers place orders.
            </div>
        <?php else: ?>
            <?php foreach ($order_alerts as $o): ?>
                <div class="order">
                    <div class="order-header">
                        <span class="order-id">Order #<?= (int)$o['order_id'] ?></span>
                        <span class="badge <?= $o['order_status'] === 'Pending' ? 'pending' : '' ?>"><?= htmlspecialchars($o['order_status']) ?></span>
                    </div>
                    <div class="order-details">
                        <strong>Date:</strong> <?= date('F d, Y', strtotime($o['order_date'])) ?><br>
                        <strong>Crops:</strong> <?= htmlspecialchars($o['crops']) ?><br>
                        <strong>Total:</strong> ₱<?= number_format((float)$o['total_price'], 2) ?>
                    </div>
                    <div class="order-actions">
                        <a class="btn" href="orders.php">View Orders</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
