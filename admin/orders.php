<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Admin');
require_once __DIR__ . '/../includes/config.php';

$message = '';
$error = '';

// Optional columns
$has_oi_quantity = false;
$cols = $conn->query("SHOW COLUMNS FROM order_items LIKE 'quantity'");
if ($cols && $cols->num_rows > 0) $has_oi_quantity = true;
$has_pool_price = false;
$cols = $conn->query("SHOW COLUMNS FROM cooperative_pools LIKE 'unit_price'");
if ($cols && $cols->num_rows > 0) $has_pool_price = true;

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = trim($_POST['order_status'] ?? '');
    $valid = ['Pending', 'Confirmed', 'Shipped', 'Delivered', 'Cancelled'];
    if ($order_id > 0 && in_array($new_status, $valid)) {
        $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
        $stmt->bind_param('si', $new_status, $order_id);
        $stmt->execute();
        $stmt->close();
        $redirect_params = [];
        if (isset($_GET['status']) && $_GET['status'] !== '') $redirect_params['status'] = $_GET['status'];
        if (isset($_GET['sort']) && $_GET['sort'] !== '') $redirect_params['sort'] = $_GET['sort'];
        $redirect_params['updated'] = $order_id;
        header('Location: orders.php?' . http_build_query($redirect_params));
        exit;
    } else {
        $error = 'Invalid order or status.';
    }
}
if (isset($_GET['updated'])) {
    $message = 'Order #' . (int)$_GET['updated'] . ' status updated successfully.';
}

// Filter & sort from GET
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_desc';
$allowed_sort = ['date_desc', 'date_asc', 'id_asc', 'id_desc', 'status', 'buyer'];
if (!in_array($sort, $allowed_sort)) $sort = 'date_desc';

// Build total subquery (works with or without order_items.quantity and pool unit_price)
$qty_expr = $has_oi_quantity ? 'COALESCE(oi2.quantity, 1)' : '1';
$price_join = "LEFT JOIN crops_inventory ci ON oi2.inventory_id = ci.inventory_id
               LEFT JOIN cooperative_pools cp ON oi2.pool_id = cp.pool_id";
$price_expr = $has_pool_price
    ? "COALESCE(ci.price, cp.unit_price, 0)"
    : "COALESCE(ci.price, 0)";
$total_subquery = "(SELECT COALESCE(SUM($qty_expr * $price_expr), 0) FROM order_items oi2 $price_join WHERE oi2.order_id = o.order_id)";

$sql = "
    SELECT 
        o.order_id,
        o.order_date,
        o.order_status,
        CONCAT(u.first_name, ' ', u.last_name) AS buyer_name,
        u.email AS buyer_email,
        $total_subquery AS order_total
    FROM orders o
    JOIN buyer_profiles bp ON o.buyer_id = bp.buyer_id
    JOIN users u ON bp.buyer_id = u.user_id
";
$params = [];
$types = '';
if ($filter_status !== '') {
    $sql .= " WHERE o.order_status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

switch ($sort) {
    case 'date_asc':
        $sql .= " ORDER BY o.order_date ASC";
        break;
    case 'id_asc':
        $sql .= " ORDER BY o.order_id ASC";
        break;
    case 'id_desc':
        $sql .= " ORDER BY o.order_id DESC";
        break;
    case 'status':
        $sql .= " ORDER BY o.order_status ASC, o.order_date DESC";
        break;
    case 'buyer':
        $sql .= " ORDER BY buyer_name ASC, o.order_date DESC";
        break;
    default:
        $sql .= " ORDER BY o.order_date DESC";
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $orders = [];
} else {
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
}
$orders = $stmt ? $stmt->get_result()->fetch_all(MYSQLI_ASSOC) : [];
if ($stmt) $stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Admin - BANTAY-ANI</title>
    <style>
        <?php include __DIR__ . '/../includes/styles/admin_style.css'; ?>
        .toolbar { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; margin-bottom: 24px; }
        .toolbar label { font-weight: 600; color: var(--text-light); }
        .toolbar select { padding: 8px 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-family: 'Quicksand'; min-width: 140px; }
        .toolbar .btn-apply { padding: 8px 16px; background: var(--green); color: white; border: none; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; }
        .toolbar .btn-apply:hover { background: var(--green-dark); }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 14px 16px; background: #f9fafb; color: var(--text-light); font-weight: 600; border-bottom: 2px solid var(--border); }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid var(--border); }
        .data-table tr:hover { background: #f9fafb; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #b45309; }
        .status-confirmed { background: #dbeafe; color: #1d4ed8; }
        .status-shipped { background: #e0e7ff; color: #4338ca; }
        .status-delivered { background: #d1fae5; color: #047857; }
        .status-cancelled { background: #fee2e2; color: #b91c1c; }
        .order-status-form { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .order-status-form select { padding: 6px 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; }
        .order-status-form button { padding: 6px 12px; background: var(--green); color: white; border: none; border-radius: 8px; font-size: 0.85rem; cursor: pointer; }
        .order-status-form button:hover { background: var(--green-dark); }
        .alert-success { padding: 12px 16px; background: #d1fae5; color: #047857; border-radius: var(--radius-sm); margin-bottom: 16px; }
        .alert-error { padding: 12px 16px; background: #fee2e2; color: #b91c1c; border-radius: var(--radius-sm); margin-bottom: 16px; }
        .card { background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 24px; }
        .card-header { margin-bottom: 20px; }
        .card-header h2 { font-size: 1.35rem; color: var(--text); }
        .empty-state { text-align: center; padding: 48px 24px; color: var(--text-light); }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo-container">
            <a href="<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/bantayani/admin/index.php' ?>" class="logo">
                <div class="logo-icon">BA</div>
                <div class="logo-text">BANTAY<span>ANI</span></div>
            </a>
            <div class="admin-badge">Admin</div>
        </div>
        <div class="nav-section">
            <div class="nav-title">Main</div>
            <ul class="nav-links">
                <li><a href="index.php" class="nav-link"><span class="nav-icon">📊</span><span>Dashboard</span></a></li>
                <li><a href="users.php" class="nav-link"><span class="nav-icon">👥</span><span>Users</span></a></li>
                <li><a href="verify_farmers.php" class="nav-link"><span class="nav-icon">✅</span><span>Verification</span></a></li>
                <li><a href="orders.php" class="nav-link active"><span class="nav-icon">📦</span><span>Orders</span></a></li>
                <li><a href="reports.php" class="nav-link"><span class="nav-icon">📈</span><span>Reports</span></a></li>
            </ul>
        </div>
        <div class="nav-section">
            <div class="nav-title">Management</div>
            <ul class="nav-links">
                <li><a href="crops.php" class="nav-link"><span class="nav-icon">🌱</span><span>Crop Categories</span></a></li>
                <li><a href="cooperative.php" class="nav-link"><span class="nav-icon">🤝</span><span>Cooperatives</span></a></li>
                <li><a href="benchmarking.php" class="nav-link"><span class="nav-icon">💰</span><span>Price Benchmarks</span></a></li>
                <li><a href="announcements.php" class="nav-link"><span class="nav-icon">📢</span><span>Announcements</span></a></li>
            </ul>
        </div>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <div class="page-title">
                <h1>Orders</h1>
                <p>View and manage all orders</p>
            </div>
            <div class="user-info">
                <a href="profile.php" title="Profile">Profile</a>
                <a href="/bantayani/user/logout.php" class="logout-btn" style="text-decoration:none;">Log Out</a>
            </div>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2>All Orders</h2>
                </div>

                <form method="GET" class="toolbar">
                    <label>Status:</label>
                    <select name="status">
                        <option value="">All</option>
                        <option value="Pending"   <?= $filter_status === 'Pending'   ? 'selected' : '' ?>>Pending</option>
                        <option value="Confirmed" <?= $filter_status === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="Shipped"   <?= $filter_status === 'Shipped'   ? 'selected' : '' ?>>Shipped</option>
                        <option value="Delivered" <?= $filter_status === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="Cancelled" <?= $filter_status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                    <label>Sort by:</label>
                    <select name="sort">
                        <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Date (newest first)</option>
                        <option value="date_asc"  <?= $sort === 'date_asc'  ? 'selected' : '' ?>>Date (oldest first)</option>
                        <option value="id_desc"   <?= $sort === 'id_desc'   ? 'selected' : '' ?>>Order ID (high to low)</option>
                        <option value="id_asc"   <?= $sort === 'id_asc'   ? 'selected' : '' ?>>Order ID (low to high)</option>
                        <option value="status"    <?= $sort === 'status'    ? 'selected' : '' ?>>Status</option>
                        <option value="buyer"     <?= $sort === 'buyer'     ? 'selected' : '' ?>>Buyer name</option>
                    </select>
                    <button type="submit" class="btn-apply">Apply</button>
                </form>

                <?php if (empty($orders)): ?>
                    <div class="empty-state">No orders found.</div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Buyer</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Change status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $row): 
                                $status_class = 'status-' . strtolower($row['order_status']);
                            ?>
                            <tr>
                                <td>
                                    <a href="order_view.php?id=<?= (int)$row['order_id'] ?>" style="color: var(--green); font-weight: 600;">#<?= (int)$row['order_id'] ?></a>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['buyer_name']) ?>
                                    <br><small style="color: var(--text-light);"><?= htmlspecialchars($row['buyer_email']) ?></small>
                                </td>
                                <td><?= date('M d, Y H:i', strtotime($row['order_date'])) ?></td>
                                <td><span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($row['order_status']) ?></span></td>
                                <td>₱<?= number_format((float)$row['order_total'], 2) ?></td>
                                <td>
                                    <form method="POST" class="order-status-form">
                                        <input type="hidden" name="update_status" value="1">
                                        <input type="hidden" name="order_id" value="<?= (int)$row['order_id'] ?>">
                                        <select name="order_status">
                                            <option value="Pending"   <?= $row['order_status'] === 'Pending'   ? 'selected' : '' ?>>Pending</option>
                                            <option value="Confirmed" <?= $row['order_status'] === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                            <option value="Shipped"   <?= $row['order_status'] === 'Shipped'   ? 'selected' : '' ?>>Shipped</option>
                                            <option value="Delivered" <?= $row['order_status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                            <option value="Cancelled" <?= $row['order_status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                        <button type="submit">Update</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
