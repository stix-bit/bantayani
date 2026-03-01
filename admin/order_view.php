<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Admin');
require_once __DIR__ . '/../includes/config.php';

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) {
    header('Location: orders.php');
    exit;
}

$has_oi_quantity = $conn->query("SHOW COLUMNS FROM order_items LIKE 'quantity'")->num_rows > 0;
$has_pool_price = $conn->query("SHOW COLUMNS FROM cooperative_pools LIKE 'unit_price'")->num_rows > 0;

$stmt = $conn->prepare("
    SELECT o.order_id, o.order_date, o.order_status,
           CONCAT(u.first_name, ' ', u.last_name) AS buyer_name, u.email AS buyer_email
    FROM orders o
    JOIN buyer_profiles bp ON o.buyer_id = bp.buyer_id
    JOIN users u ON bp.buyer_id = u.user_id
    WHERE o.order_id = ?
");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: orders.php');
    exit;
}

$qty_sel = $has_oi_quantity ? 'COALESCE(oi.quantity, 1) AS qty' : '1 AS qty';
$stmt = $conn->prepare("
    SELECT oi.order_item_id, oi.inventory_id, oi.pool_id, $qty_sel,
           COALESCE(c1.crop_name, c2.crop_name) AS crop_name,
           COALESCE(ci.price, p.unit_price, 0) AS unit_price
    FROM order_items oi
    LEFT JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
    LEFT JOIN crops c1 ON ci.crop_id = c1.crop_id
    LEFT JOIN cooperative_pools p ON oi.pool_id = p.pool_id
    LEFT JOIN crops c2 ON p.crop_id = c2.crop_id
    WHERE oi.order_id = ?
");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total = 0;
foreach ($items as $i) {
    $total += (float)($i['qty'] ?? 1) * (float)($i['unit_price'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= $order_id ?> - Admin</title>
    <style>
        <?php include __DIR__ . '/../includes/styles/admin_style.css'; ?>
        .card { background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 24px; margin-bottom: 20px; }
        .back-link { display: inline-block; margin-bottom: 16px; color: var(--green); font-weight: 600; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .detail-item label { font-weight: 600; color: var(--text-light); display: block; margin-bottom: 4px; }
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.data-table th, table.data-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        table.data-table th { background: #f9fafb; }
        .total-row { font-weight: 700; font-size: 1.1rem; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo-container">
            <a href="index.php" class="logo"><div class="logo-icon">BA</div><div class="logo-text">BANTAY<span>ANI</span></div></a>
            <div class="admin-badge">Admin</div>
        </div>
        <div class="nav-section">
            <div class="nav-title">Main</div>
            <ul class="nav-links">
                <li><a href="index.php" class="nav-link">📊 Dashboard</a></li>
                <li><a href="users.php" class="nav-link">👥 Users</a></li>
                <li><a href="verify_farmers.php" class="nav-link">✅ Verification</a></li>
                <li><a href="orders.php" class="nav-link active">📦 Orders</a></li>
                <li><a href="../reports.php" class="nav-link"><span class="nav-icon">📈</span><span>Reports</span></a></li>
            </ul>
        </div>
        <div class="nav-section">
            <div class="nav-title">Management</div>
            <ul class="nav-links">
                <li><a href="crop_categories.php" class="nav-link"><span class="nav-icon">📁</span><span>Crop Categories</span></a></li>
                <li><a href="crops.php" class="nav-link"><span class="nav-icon">🌱</span><span>Crops</span></a></li>
                <li><a href="cooperative.php" class="nav-link">🤝 Cooperatives</a></li>
                <li><a href="benchmarking.php" class="nav-link">💰 Pricing</a></li>
            </ul>
        </div>
    </aside>
    <main class="main-content">
        <div class="topbar">
            <div class="page-title"><h1>Order #<?= $order_id ?></h1><p>Order details</p></div>
            <a href="profile.php">Profile</a>
            <a href="/bantayani/user/logout.php">Log Out</a>
        </div>
        <div class="content">
            <a href="orders.php" class="back-link">← Back to Orders</a>
            <div class="card">
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Buyer</label>
                        <p><?= htmlspecialchars($order['buyer_name']) ?></p>
                        <p style="color: var(--text-light);"><?= htmlspecialchars($order['buyer_email']) ?></p>
                    </div>
                    <div class="detail-item">
                        <label>Date</label>
                        <p><?= date('M d, Y H:i', strtotime($order['order_date'])) ?></p>
                    </div>
                    <div class="detail-item">
                        <label>Status</label>
                        <p><?= htmlspecialchars($order['order_status']) ?></p>
                    </div>
                </div>
                <table class="data-table">
                    <thead><tr><th>Item</th><th>Qty</th><th>Unit price</th><th>Subtotal</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $i): 
                            $qty = (float)($i['qty'] ?? 1);
                            $up = (float)($i['unit_price'] ?? 0);
                            $sub = $qty * $up;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($i['crop_name'] ?? '—') ?></td>
                            <td><?= number_format($qty, 2) ?></td>
                            <td>₱<?= number_format($up, 2) ?></td>
                            <td>₱<?= number_format($sub, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="total-row"><td colspan="3">Total</td><td>₱<?= number_format($total, 2) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
