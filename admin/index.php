<?php
session_start();
require_once '../includes/config.php';

/*// Admin access check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../login.php');
    exit;
}

// Get admin statistics
$stats = [];
$recent_users = [];
$pending_verifications = [];*/

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
    header('Location: ../login.php');
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
        @import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap');

        :root {
            --green-dark: #0c5c4c;
            --green: #1f8a70;
            --green-light: #4cb69f;
            --orange: #f28705;
            --red: #b91c1c;
            --blue: #1d4ed8;
            --purple: #7c3aed;
            --beige: #f6f1e9;
            --beige-light: #fcfaf6;
            --text: #1f2933;
            --text-light: #4c5662;
            --border: #e4e7eb;
            --sidebar-width: 260px;
            --shadow-sm: 0 2px 8px rgba(12, 92, 76, 0.08);
            --shadow-md: 0 8px 20px rgba(12, 92, 76, 0.12);
            --radius-sm: 12px;
            --radius-md: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Quicksand', 'Segoe UI', sans-serif;
            background: var(--beige-light);
            color: var(--text);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            border-right: 1px solid var(--border);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: var(--shadow-sm);
        }

        .logo-container {
            padding: 24px;
            border-bottom: 1px solid var(--border);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .logo-text {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--green-dark);
        }

        .logo-text span {
            color: var(--orange);
        }

        .admin-badge {
            display: inline-block;
            background: rgba(12, 92, 76, 0.1);
            color: var(--green-dark);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 8px;
        }

        .nav-section {
            padding: 20px 0;
        }

        .nav-title {
            padding: 0 24px 12px;
            font-size: 0.85rem;
            color: var(--text-light);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .nav-links {
            list-style: none;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 24px;
            color: var(--text);
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            background: rgba(12, 92, 76, 0.05);
            color: var(--green);
        }

        .nav-link.active {
            background: rgba(12, 92, 76, 0.1);
            color: var(--green);
            border-left-color: var(--green);
        }

        .nav-icon {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .topbar {
            background: white;
            padding: 20px 32px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .page-title h1 {
            font-size: 1.8rem;
            color: var(--text);
        }

        .page-title p {
            color: var(--text-light);
            margin-top: 4px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--green-light), var(--green));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .logout-btn {
            background: var(--beige);
            color: var(--text-light);
            border: none;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: rgba(185, 28, 28, 0.1);
            color: var(--red);
        }

        /* Content Area */
        .content {
            padding: 32px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border-top: 4px solid transparent;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-card.users { border-top-color: var(--green); }
        .stat-card.farmers { border-top-color: var(--blue); }
        .stat-card.buyers { border-top-color: var(--purple); }
        .stat-card.orders { border-top-color: var(--orange); }
        .stat-card.verifications { border-top-color: var(--red); }
        .stat-card.inventory { border-top-color: #10b981; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-card.users .stat-icon { background: rgba(31, 138, 112, 0.1); color: var(--green); }
        .stat-card.farmers .stat-icon { background: rgba(29, 78, 216, 0.1); color: var(--blue); }
        .stat-card.buyers .stat-icon { background: rgba(124, 58, 237, 0.1); color: var(--purple); }
        .stat-card.orders .stat-icon { background: rgba(242, 135, 5, 0.1); color: var(--orange); }
        .stat-card.verifications .stat-icon { background: rgba(185, 28, 28, 0.1); color: var(--red); }
        .stat-card.inventory .stat-icon { background: rgba(16, 185, 129, 0.1); color: #10b981; }

        .stat-trend {
            font-size: 0.85rem;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        .trend-up { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .trend-down { background: rgba(185, 28, 28, 0.1); color: var(--red); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        /* Tables Section */
        .tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        @media (max-width: 1200px) {
            .tables-grid {
                grid-template-columns: 1fr;
            }
        }

        .table-card {
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            font-size: 1.2rem;
            color: var(--text);
        }

        .view-all {
            color: var(--green);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 16px 24px;
            color: var(--text-light);
            font-weight: 600;
            border-bottom: 2px solid var(--beige);
        }

        .data-table td {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .user-role {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .role-farmer { background: rgba(31, 138, 112, 0.1); color: var(--green); }
        .role-buyer { background: rgba(124, 58, 237, 0.1); color: var(--purple); }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending { background: rgba(242, 135, 5, 0.1); color: var(--orange); }
        .status-confirmed { background: rgba(31, 138, 112, 0.1); color: var(--green); }
        .status-delivered { background: rgba(12, 92, 76, 0.1); color: var(--green-dark); }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 32px;
        }

        .action-card {
            background: white;
            padding: 20px;
            border-radius: var(--radius-md);
            text-decoration: none;
            color: var(--text);
            box-shadow: var(--shadow-sm);
            border: 2px solid transparent;
            transition: all 0.2s;
        }

        .action-card:hover {
            border-color: var(--green);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .action-icon {
            font-size: 2rem;
            margin-bottom: 12px;
            color: var(--green);
        }

        .action-title {
            font-weight: 600;
            margin-bottom: 8px;
        }

        .action-desc {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar .logo-text,
            .sidebar .nav-title,
            .sidebar .nav-link span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .logo-container {
                padding: 20px;
            }
            
            .nav-link {
                padding: 16px;
                justify-content: center;
            }
            
            .tables-grid {
                grid-template-columns: 1fr;
            }
            
            .content {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 24px;
            color: var(--text-light);
            font-size: 0.9rem;
            border-top: 1px solid var(--border);
            margin-top: 32px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo-container">
            <a href="dashboard.php" class="logo">
                <div class="logo-icon">BA</div>
                <div class="logo-text">BANTAY<span>ANI</span></div>
            </a>
            <div class="admin-badge">Admin</div>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Main</div>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="nav-link active"><span class="nav-icon">📊</span><span>Dashboard</span></a></li>
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
