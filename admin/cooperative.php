<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Admin');
require_once '../includes/config.php';

// Check if unit_price column exists
$has_unit_price = false;
$cols = $conn->query("SHOW COLUMNS FROM cooperative_pools LIKE 'unit_price'");
if ($cols && $cols->num_rows > 0) $has_unit_price = true;

// Handle cooperative pool and cooperative order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update order status for cooperative orders
    if (isset($_POST['update_order_status'], $_POST['order_id'], $_POST['order_status'])) {
        $order_id = (int) $_POST['order_id'];
        $new_status = trim($_POST['order_status']);
        $valid_status = ['Pending', 'Confirmed', 'Shipped', 'Delivered', 'Cancelled'];
        if ($order_id > 0 && in_array($new_status, $valid_status, true)) {
            // Update order status
            $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
            $stmt->bind_param('si', $new_status, $order_id);
            $stmt->execute();
            $stmt->close();

            // If order is confirmed, mark related payments and invoice as paid
            if ($new_status === 'Confirmed') {
                // Update any payments for this order
                $pay_stmt = $conn->prepare("
                    UPDATE payment
                    SET payment_status = 'Paid',
                        payment_date = NOW()
                    WHERE order_id = ?
                ");
                if ($pay_stmt) {
                    $pay_stmt->bind_param('i', $order_id);
                    $pay_stmt->execute();
                    $pay_stmt->close();
                }

                // Update invoice record, if any
                $inv_stmt = $conn->prepare("
                    UPDATE invoices
                    SET payment_status = 'Paid'
                    WHERE order_id = ?
                ");
                if ($inv_stmt) {
                    $inv_stmt->bind_param('i', $order_id);
                    $inv_stmt->execute();
                    $inv_stmt->close();
                }
            }
        }
        header('Location: cooperative.php');
        exit;
    }

    // Delete cooperative order
    if (isset($_POST['delete_order'], $_POST['order_id'])) {
        $order_id = (int) $_POST['order_id'];
        if ($order_id > 0) {
            $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ? AND pool_id IS NOT NULL");
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: cooperative.php');
        exit;
    }

    // Pool delete action
    if (isset($_POST['action'], $_POST['pool_id']) && $_POST['action'] === 'delete') {
        $pool_id = (int) $_POST['pool_id'];
        $stmt = $conn->prepare("DELETE FROM cooperative_members WHERE pool_id = ?");
        $stmt->bind_param("i", $pool_id);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM cooperative_pools WHERE pool_id = ?");
        $stmt->bind_param("i", $pool_id);
        $stmt->execute();
        $stmt->close();
        header('Location: cooperative.php');
        exit;
    }
}


// Fetch all cooperative pools with member count
$select_extras = ($has_unit_price ? ", p.unit_price" : "");
$sql = "SELECT p.pool_id, p.crop_id, p.total_quantity, p.created_at, c.crop_name, p.unit $select_extras,
        (SELECT COUNT(DISTINCT farmer_id) FROM cooperative_members WHERE pool_id = p.pool_id) AS member_count,
        (SELECT COALESCE(SUM(quantity_contributed), 0) FROM cooperative_members WHERE pool_id = p.pool_id) AS total_contributed
        FROM cooperative_pools p
        JOIN crops c ON p.crop_id = c.crop_id
        ORDER BY c.crop_name ASC, p.pool_id ASC";
$result = $conn->query($sql);
$pools = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Fetch cooperative orders (orders that include pool based items)
$coop_orders_sql = "
    SELECT
        o.order_id,
        o.order_date,
        o.order_status,
        CONCAT(u.first_name, ' ', u.last_name) AS buyer_name,
        u.email AS buyer_email,
        (SELECT COALESCE(SUM(COALESCE(cp.unit_price,0) * oi.quantity), 0)
         FROM order_items oi
         JOIN cooperative_pools cp ON oi.pool_id = cp.pool_id
         WHERE oi.order_id = o.order_id AND oi.pool_id IS NOT NULL) AS order_total
    FROM orders o
    JOIN buyer_profiles bp ON o.buyer_id = bp.buyer_id
    JOIN users u ON bp.buyer_id = u.user_id
    WHERE EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id AND oi.pool_id IS NOT NULL)
    ORDER BY o.order_date DESC
";
$coop_orders_result = $conn->query($coop_orders_sql);
$coop_orders = $coop_orders_result ? $coop_orders_result->fetch_all(MYSQLI_ASSOC) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Cooperatives - BANTAY-ANI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="index.php" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        <?php include '../includes/styles/admin_style.css'; ?>
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="logo-container">
        <a href="http://localhost/bantayani/index.php" class="logo">
            <div class="logo-icon">BA</div>
            <div class="logo-text">BANTAY<span>ANI</span></div>
        </a>
        <div class="admin-badge">Admin</div>
    </div>

    <div class="nav-section">
            <div class="nav-title">Main</div>
            <ul class="nav-links">
                <li><a href="index.php" class="nav-link"><span class="nav-icon">📊</span><span>Dashboard</span></a></li>
                <li><a href="verify_farmers.php" class="nav-link"><span class="nav-icon">✅</span><span>Certificate Verification</span></a></li>
                <li><a href="announcements.php" class="nav-link"><span class="nav-icon">📢</span><span>Announcements</span></a></li>
                <li><a href="../reports.php" class="nav-link"><span class="nav-icon">📈</span><span>Reports</span></a></li>
            </ul>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Management</div>
            <ul class="nav-links">
                <li><a href="users.php" class="nav-link"><span class="nav-icon">👥</span><span>Users</span></a></li>
                <li><a href="orders.php" class="nav-link"><span class="nav-icon">📦</span><span>Orders</span></a></li>
                <li><a href="crop_categories.php" class="nav-link"><span class="nav-icon">📁</span><span>Crop Categories</span></a></li>
                <li><a href="crops.php" class="nav-link"><span class="nav-icon">🌱</span><span>Crops</span></a></li>
                <li><a href="cooperative.php" class="nav-link active"><span class="nav-icon">🤝</span><span>Cooperatives</span></a></li>
                <li><a href="benchmarking.php" class="nav-link"><span class="nav-icon">💰</span><span>Price Benchmarks</span></a></li>
            </ul>
        </div>
</aside>

<main class="main-content">
    <div class="topbar">
        <div class="page-title">
            <h1>Cooperatives Management</h1>
            <p>View and manage cooperative pools</p>
        </div>
    </div>

    <div class="content">
        <div class="table-card">
            <div class="table-header">
                <h3>All Cooperative Pools</h3>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Crop</th>
                        <th>Total Quantity</th>
                        <?php if ($has_unit_price): ?><th>Unit Price</th><?php endif; ?>
                        <th>Contributors</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($pools)): ?>
                    <?php foreach ($pools as $pool): ?>
                        <tr>
                            <td><?= htmlspecialchars($pool['crop_name']) ?> <span class="status-badge" style="font-size:0.75rem;"><?= htmlspecialchars($pool['unit']) ?></span></td>
                            <td><?= number_format((float)($pool['total_quantity'] ?? 0), 2) ?></td>
                            <?php if ($has_unit_price): ?>
                                <td><?= isset($pool['unit_price']) && $pool['unit_price'] != null ? '₱' . number_format((float)$pool['unit_price'], 2) : '—' ?></td>
                            <?php endif; ?>
                            <td><?= (int) $pool['member_count'] ?></td>
                            <td><?= date('M d, Y', strtotime($pool['created_at'])) ?></td>
                            <td>
                                <button type="button" class="icon-btn delete-btn" onclick='openDeleteModal(<?= $pool["pool_id"] ?>, <?= json_encode($pool["crop_name"]) ?>)'>
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $has_unit_price ? 6 : 5 ?>" style="text-align:center; padding:40px;">No cooperative pools found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-card" style="margin-top: 24px;">
            <div class="table-header">
                <h3>Cooperative Orders</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Buyer</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($coop_orders)): ?>
                        <?php foreach ($coop_orders as $order): ?>
                            <tr>
                                <td>#<?= (int)$order['order_id'] ?></td>
                                <td><?= htmlspecialchars($order['buyer_name']) ?><br><small><?= htmlspecialchars($order['buyer_email']) ?></small></td>
                                <td><?= date('M d, Y H:i', strtotime($order['order_date'])) ?></td>
                                <td><span class="status-badge status-<?= strtolower($order['order_status']) ?>"><?= htmlspecialchars($order['order_status']) ?></span></td>
                                <td>₱<?= number_format((float)$order['order_total'], 2) ?></td>
                                <td>
                                    <form method="POST" style="display:inline; margin-right: 8px;">
                                        <input type="hidden" name="update_order_status" value="1">
                                        <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                                        <select name="order_status" style="margin-right:5px;">
                                            <?php foreach(['Pending','Confirmed','Shipped','Delivered','Cancelled'] as $s): ?>
                                                <option value="<?= $s ?>" <?= $order['order_status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="confirm-btn">Update</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="delete_order" value="1">
                                        <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                                        <button type="submit" class="confirm-btn danger" onclick="return confirm('Delete this cooperative order? This cannot be undone.')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:40px;">No cooperative orders found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Delete Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-box">
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete the cooperative pool for <strong id="deletePoolName"></strong>? This will remove all contributor records.</p>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="pool_id" id="deletePoolId">
            <div style="margin-top:15px;">
                <button type="submit" class="confirm-btn danger">Delete</button>
                <button type="button" onclick="closeDeleteModal()" class="confirm-btn">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDeleteModal(poolId, cropName) {
    document.getElementById('deletePoolId').value = poolId;
    document.getElementById('deletePoolName').textContent = cropName;
    document.getElementById('deleteModal').style.display = 'flex';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
</script>
</body>
</html>
