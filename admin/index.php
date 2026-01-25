<?php
session_start();
require_once '../includes/config.php';

// Admin access check
/* if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../user/login.php');
    exit;
}

// Get admin statistics
$stats = [];
$recent_users = [];
$pending_verifications = []; */

try {
    // Total statistics
    $stmt = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM users WHERE role = 'Farmer') as total_farmers,
            (SELECT COUNT(*) FROM users WHERE role = 'Buyer') as total_buyers,
            (SELECT COUNT(*) FROM orders) as total_orders,
            (SELECT COUNT(*) FROM orders WHERE order_status = 'Pending') as pending_orders,
            (SELECT COUNT(*) FROM deliveries WHERE delivery_status = 'Delivering') as in_transit,
            (SELECT COUNT(*) FROM users u JOIN farmer_profiles f ON u.user_id = f.farmer_id WHERE u.is_verified = 0) as pending_verifications,
            (SELECT COALESCE(SUM(ci.price * ci.quantity), 0) FROM crops_inventory ci) as total_inventory_value
    ");
    $stats = $stmt->fetch_assoc();
    
    // Recent users (last 7 days)
    $stmt = $conn->prepare("
        SELECT user_id, first_name, last_name, email, role, created_at 
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Recent orders
    $stmt = $conn->prepare("
        SELECT o.order_id, u.first_name, u.last_name, o.order_status, o.order_date,
               GROUP_CONCAT(DISTINCT c.crop_name SEPARATOR ', ') as crops
        FROM orders o
        JOIN users u ON o.buyer_id = u.user_id
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
        JOIN crops c ON ci.crop_id = c.crop_id
        GROUP BY o.order_id
        ORDER BY o.order_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../user/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BANTAY-ANI</title>
    <style>
       <?php include '../includes/styles/admin_style.css'; // put your existing CSS in a separate file for reuse ?>
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">BA</div>
                <div class="logo-text">BANTAY<span>ANI</span></div>
            </a>
            <div class="admin-badge">Admin</div>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Main</div>
            <ul class="nav-links">
                <li><a href="index.php" class="nav-link active"><span class="nav-icon">📊</span><span>Dashboard</span></a></li>
                <li><a href="users.php" class="nav-link"><span class="nav-icon">👥</span><span>Users</span></a></li>
                <li><a href="verification.php" class="nav-link"><span class="nav-icon">✅</span><span>Verification</span></a></li>
                <li><a href="orders.php" class="nav-link"><span class="nav-icon">📦</span><span>Orders</span></a></li>
                <li><a href="reports.php" class="nav-link"><span class="nav-icon">📈</span><span>Reports</span></a></li>
            </ul>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Management</div>
            <ul class="nav-links">
                <li><a href="crops.php" class="nav-link"><span class="nav-icon">🌱</span><span>Crop Categories</span></a></li>
                <li><a href="pricing.php" class="nav-link"><span class="nav-icon">💰</span><span>Price Benchmarks</span></a></li>
                <li><a href="delivery.php" class="nav-link"><span class="nav-icon">🚚</span><span>Delivery Partners</span></a></li>
                <li><a href="announcements.php" class="nav-link"><span class="nav-icon">📢</span><span>Announcements</span></a></li>
            </ul>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">System</div>
            <ul class="nav-links">
                <li><a href="settings.php" class="nav-link"><span class="nav-icon">⚙️</span><span>Settings</span></a></li>
                <li><a href="logs.php" class="nav-link"><span class="nav-icon">📝</span><span>System Logs</span></a></li>
                <li><a href="backup.php" class="nav-link"><span class="nav-icon">💾</span><span>Backup</span></a></li>
            </ul>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="page-title">
                <h1>Admin Dashboard</h1>
                <p>Welcome back, <?= htmlspecialchars($_SESSION['first_name'] ?? 'Admin') ?>! Here's what's happening.</p>
            </div>
            
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['first_name'] ?? 'A', 0, 1)) ?></div>
                <form method="GET">
                    <button type="submit" name="logout" value="1" class="logout-btn">Log Out</button>
                </form>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card users">
                    <div class="stat-header">
                        <div class="stat-icon">👥</div>
                        <div class="stat-trend trend-up">+12%</div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_users'] ?? 0) ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                
                <div class="stat-card farmers">
                    <div class="stat-header">
                        <div class="stat-icon">👨‍🌾</div>
                        <div class="stat-trend trend-up">+8%</div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_farmers'] ?? 0) ?></div>
                    <div class="stat-label">Farmers</div>
                </div>
                
                <div class="stat-card buyers">
                    <div class="stat-header">
                        <div class="stat-icon">🏪</div>
                        <div class="stat-trend trend-up">+15%</div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_buyers'] ?? 0) ?></div>
                    <div class="stat-label">Buyers</div>
                </div>
                
                <div class="stat-card orders">
                    <div class="stat-header">
                        <div class="stat-icon">📦</div>
                        <div class="stat-trend trend-up">+24%</div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_orders'] ?? 0) ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                
                <div class="stat-card verifications">
                    <div class="stat-header">
                        <div class="stat-icon">⏳</div>
                        <div class="stat-trend trend-down"><?= $stats['pending_verifications'] ?? 0 ?></div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['pending_verifications'] ?? 0) ?></div>
                    <div class="stat-label">Pending Verifications</div>
                </div>
                
                <div class="stat-card inventory">
                    <div class="stat-header">
                        <div class="stat-icon">📊</div>
                        <div class="stat-trend trend-up">+18%</div>
                    </div>
                    <div class="stat-value">₱<?= number_format($stats['total_inventory_value'] ?? 0, 0) ?></div>
                    <div class="stat-label">Inventory Value</div>
                </div>
            </div>

            <!-- Tables Section -->
            <div class="tables-grid">
                <!-- Recent Users Table -->
                <div class="table-card">
                    <div class="table-header">
                        <h3>Recent Users</h3>
                        <a href="users.php" class="view-all">View All →</a>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_users)): ?>
                                <?php foreach ($recent_users as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <span class="user-role role-<?= strtolower($user['role']) ?>">
                                                <?= $user['role'] ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-light); padding: 40px;">
                                        No recent users found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Recent Orders Table -->
                <div class="table-card">
                    <div class="table-header">
                        <h3>Recent Orders</h3>
                        <a href="orders.php" class="view-all">View All →</a>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Crops</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_orders)): ?>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>#<?= $order['order_id'] ?></td>
                                        <td><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></td>
                                        <td title="<?= htmlspecialchars($order['crops']) ?>">
                                            <?= strlen($order['crops']) > 30 ? substr($order['crops'], 0, 30) . '...' : $order['crops'] ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= strtolower($order['order_status']) ?>">
                                                <?= $order['order_status'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-light); padding: 40px;">
                                        No recent orders found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="verification.php" class="action-card">
                    <div class="action-icon">✅</div>
                    <div class="action-title">Review Verifications</div>
                    <div class="action-desc">Approve or reject pending farmer verifications</div>
                </a>
                
                <a href="reports.php" class="action-card">
                    <div class="action-icon">📊</div>
                    <div class="action-title">Generate Reports</div>
                    <div class="action-desc">Create sales, user, and system reports</div>
                </a>
                
                <a href="announcements.php" class="action-card">
                    <div class="action-icon">📢</div>
                    <div class="action-title">Send Announcement</div>
                    <div class="action-desc">Notify all users about important updates</div>
                </a>
                
                <a href="backup.php" class="action-card">
                    <div class="action-icon">💾</div>
                    <div class="action-title">System Backup</div>
                    <div class="action-desc">Create a backup of all system data</div>
                </a>
            </div>

            <!-- Footer -->
            <div class="footer">
                <p>© <?= date('Y') ?> BANTAY-ANI Admin System. All rights reserved.</p>
                <p style="margin-top: 8px; font-size: 0.85rem;">
                    System Version 1.0.0 | Last Updated: <?= date('F d, Y') ?>
                </p>
            </div>
        </div>
    </main>
</body>
</html>
