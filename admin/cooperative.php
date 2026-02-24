<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Admin');
require_once '../includes/config.php';

// Check if unit_price column exists
$has_unit_price = false;
$cols = $conn->query("SHOW COLUMNS FROM cooperative_pools LIKE 'unit_price'");
if ($cols && $cols->num_rows > 0) $has_unit_price = true;

// Handle actions
if (isset($_POST['action'], $_POST['pool_id'])) {
    $pool_id = (int) $_POST['pool_id'];

    if ($_POST['action'] === 'delete') {
        $stmt = $conn->prepare("DELETE FROM cooperative_members WHERE pool_id = ?");
        $stmt->bind_param("i", $pool_id);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM cooperative_pools WHERE pool_id = ?");
        $stmt->bind_param("i", $pool_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: cooperative.php");
    exit;
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
            <li><a href="users.php" class="nav-link"><span class="nav-icon">👥</span><span>Users</span></a></li>
            <li><a href="verify_farmers.php" class="nav-link"><span class="nav-icon">✅</span><span>Verification</span></a></li>
            <li><a href="orders.php" class="nav-link"><span class="nav-icon">📦</span><span>Orders</span></a></li>
            <li><a href="reports.php" class="nav-link"><span class="nav-icon">📈</span><span>Reports</span></a></li>
        </ul>
    </div>

    <div class="nav-section">
        <div class="nav-title">Management</div>
        <ul class="nav-links">
            <li><a href="crops.php" class="nav-link"><span class="nav-icon">🌱</span><span>Crop Categories</span></a></li>
            <li><a href="cooperative.php" class="nav-link active"><span class="nav-icon">🤝</span><span>Cooperatives</span></a></li>
            <li><a href="benchmarking.php" class="nav-link"><span class="nav-icon">💰</span><span>Price Benchmarks</span></a></li>
            <li><a href="announcements.php" class="nav-link"><span class="nav-icon">📢</span><span>Announcements</span></a></li>
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
