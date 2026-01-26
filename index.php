<?php
session_start();
include('includes/config.php');
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: user/login.php');
    exit;
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$first_name = $_SESSION['first_name'];

// Fetch dashboard data based on user role
$dashboard_data = [];
$upcoming_harvests = [];
$recent_orders = [];
$recent_notifications = [];

try {
    if ($user_role === 'Farmer') {
        // Farmer-specific data
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT fi.inventory_id) as active_listings,
                SUM(fi.quantity) as total_inventory,
                COALESCE(AVG(fi.price), 0) as avg_price,
                (SELECT COUNT(*) FROM orders o 
                 JOIN order_items oi ON o.order_id = oi.order_id 
                 JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id 
                 WHERE ci.farmer_id = ? AND o.order_status = 'Pending') as pending_orders
            FROM crops_inventory fi
            WHERE fi.farmer_id = ?
        ");
        $stmt->bind_param('ii', $user_id, $user_id);
        $stmt->execute();
        $dashboard_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get upcoming harvests
        $stmt = $conn->prepare("
            SELECT c.crop_name, ci.harvest_date, ci.quantity 
            FROM crops_inventory ci
            JOIN crops c ON ci.crop_id = c.crop_id
            WHERE ci.farmer_id = ? AND ci.harvest_date >= CURDATE() 
            ORDER BY ci.harvest_date ASC 
            LIMIT 3
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $upcoming_harvests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Get recent orders
        $stmt = $conn->prepare("
            SELECT o.order_id, o.order_date, o.order_status, 
                   GROUP_CONCAT(DISTINCT c.crop_name SEPARATOR ', ') as crops
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
            JOIN crops c ON ci.crop_id = c.crop_id
            WHERE ci.farmer_id = ?
            GROUP BY o.order_id
            ORDER BY o.order_date DESC
            LIMIT 5
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
    } elseif ($user_role === 'Buyer') {
        // Buyer-specific data
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT o.order_id) as total_orders,
                COALESCE(SUM(ci.price * ci.quantity), 0) as total_spent,
                (SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND order_status = 'Pending') as pending_orders,
                COUNT(DISTINCT ci.farmer_id) as farmers_connected
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
            WHERE o.buyer_id = ?
        ");
        $stmt->bind_param('ii', $user_id, $user_id);
        $stmt->execute();
        $dashboard_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get recent orders
        $stmt = $conn->prepare("
            SELECT o.order_id, o.order_date, o.order_status, 
                   GROUP_CONCAT(DISTINCT c.crop_name SEPARATOR ', ') as crops,
                   COUNT(DISTINCT ci.farmer_id) as farmers_count
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
            JOIN crops c ON ci.crop_id = c.crop_id
            WHERE o.buyer_id = ?
            GROUP BY o.order_id
            ORDER BY o.order_date DESC
            LIMIT 5
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
    } elseif ($user_role === 'Admin') {
        // Admin-specific data
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT user_id) as total_users,
                COUNT(DISTINCT CASE WHEN role = 'Farmer' THEN user_id END) as total_farmers,
                COUNT(DISTINCT CASE WHEN role = 'Buyer' THEN user_id END) as total_buyers,
                COALESCE(SUM(ci.price * ci.quantity), 0) as total_volume,
                (SELECT COUNT(*) FROM deliveries WHERE delivery_status = 'Delivering') as in_transit_deliveries
            FROM users u
            LEFT JOIN crops_inventory ci ON u.user_id = ci.farmer_id
            WHERE u.role IN ('Farmer', 'Buyer')
        ");
        $stmt->execute();
        $dashboard_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get recent orders for admin
        $stmt = $conn->prepare("
            SELECT o.order_id, o.order_date, o.order_status, 
                   u.first_name, u.last_name,
                   GROUP_CONCAT(DISTINCT c.crop_name SEPARATOR ', ') as crops
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
            JOIN crops c ON ci.crop_id = c.crop_id
            JOIN buyer_profiles bp ON o.buyer_id = bp.buyer_id
            JOIN users u ON bp.buyer_id = u.user_id
            GROUP BY o.order_id
            ORDER BY o.order_date DESC
            LIMIT 5
        ");
        $stmt->execute();
        $recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    // Get recent notifications for all users
    $stmt = $conn->prepare("
        SELECT notification_type, message, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $recent_notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ./user/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>BANTAY-ANI | Farm-to-Market System</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap');

        :root {
            --green-dark: #0c5c4c;
            --green: #1f8a70;
            --green-light: #4cb69f;
            --orange: #f28705;
            --beige: #f6f1e9;
            --beige-light: #fcfaf6;
            --text: #1f2933;
            --text-light: #4c5662;
            --border: #e4e7eb;
            --success: #0c5c4c;
            --warning: #f28705;
            --info: #1d4ed8;
            --error: #b91c1c;
            --shadow-sm: 0 2px 8px rgba(12, 92, 76, 0.08);
            --shadow-md: 0 8px 20px rgba(12, 92, 76, 0.12);
            --shadow-lg: 0 25px 60px rgba(12, 92, 76, 0.2);
            --radius-sm: 12px;
            --radius-md: 20px;
            --radius-lg: 28px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Quicksand', 'Segoe UI', sans-serif;
            background: var(--beige-light);
            color: var(--text);
            line-height: 1.5;
        }

        /* Navigation */
        .navbar {
            background: white;
            padding: 16px 32px;
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--green-dark);
        }

        .logo-text span {
            color: var(--orange);
        }

        .nav-links {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .nav-link {
            text-decoration: none;
            color: var(--text-light);
            font-weight: 500;
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            transition: all 0.2s;
        }

        .nav-link:hover {
            color: var(--green);
            background: rgba(12, 92, 76, 0.05);
        }

        .nav-link.active {
            color: var(--green);
            background: rgba(12, 92, 76, 0.1);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--green-light), var(--green));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
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
            color: var(--error);
        }

        /* Main Content */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px;
        }

        .welcome-banner {
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: white;
            padding: 32px;
            border-radius: var(--radius-lg);
            margin-bottom: 32px;
            box-shadow: var(--shadow-md);
        }

        .welcome-banner h1 {
            font-size: 2.5rem;
            margin-bottom: 8px;
        }

        .welcome-banner p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
        }

        .role-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 16px;
            border-radius: 999px;
            font-size: 0.9rem;
            margin-top: 12px;
            font-weight: 600;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 1.5rem;
        }

        .stat-icon.farmer { background: rgba(31, 138, 112, 0.1); color: var(--green); }
        .stat-icon.buyer { background: rgba(242, 135, 5, 0.1); color: var(--orange); }
        .stat-icon.admin { background: rgba(29, 78, 216, 0.1); color: var(--info); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text);
        }

        .stat-label {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        /* Content Sections */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        .section-card {
            background: white;
            border-radius: var(--radius-md);
            padding: 24px;
            box-shadow: var(--shadow-sm);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--beige);
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
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

        /* Tables & Lists */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 12px 0;
            color: var(--text-light);
            font-weight: 600;
            border-bottom: 2px solid var(--beige);
        }

        .data-table td {
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending { background: rgba(242, 135, 5, 0.1); color: var(--orange); }
        .status-confirmed { background: rgba(31, 138, 112, 0.1); color: var(--green); }
        .status-delivered { background: rgba(12, 92, 76, 0.1); color: var(--green-dark); }
        .status-cancelled { background: rgba(185, 28, 28, 0.1); color: var(--error); }
        .status-shipped { background: rgba(29, 78, 216, 0.1); color: var(--info); }

        /* Notification List */
        .notification-list {
            list-style: none;
        }

        .notification-item {
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .notification-icon.weather { background: rgba(29, 78, 216, 0.1); color: var(--info); }
        .notification-icon.order { background: rgba(31, 138, 112, 0.1); color: var(--green); }
        .notification-icon.system { background: rgba(242, 135, 5, 0.1); color: var(--orange); }

        .notification-content {
            flex: 1;
        }

        .notification-message {
            margin-bottom: 4px;
        }

        .notification-time {
            font-size: 0.85rem;
            color: var(--text-light);
        }

        /* Action Cards */
        .action-grid {
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
            transition: all 0.2s;
            border: 2px solid transparent;
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

        /* Footer */
        .footer {
            text-align: center;
            padding: 32px;
            color: var(--text-light);
            font-size: 0.9rem;
            border-top: 1px solid var(--border);
            margin-top: 48px;
        }

        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }

        .quick-stat {
            background: rgba(31, 138, 112, 0.05);
            padding: 16px;
            border-radius: var(--radius-sm);
            border: 1px solid rgba(31, 138, 112, 0.1);
        }

        .quick-stat-label {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-bottom: 4px;
        }

        .quick-stat-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--green);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                padding: 12px 16px;
                flex-direction: column;
                gap: 16px;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 12px;
            }
            
            .container {
                padding: 16px;
            }
            
            .welcome-banner h1 {
                font-size: 2rem;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card, .section-card, .action-card {
            animation: fadeIn 0.3s ease-out;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="logo-container">
            <div class="logo">BA</div>
            <div class="logo-text">BANTAY<span>ANI</span></div>
        </div>
        
        <div class="nav-links">
            <a href="index.php" class="nav-link active">Dashboard</a>
            <?php if ($user_role === 'Farmer'): ?>
                <a href="./farmer/inventory.php" class="nav-link">My Inventory</a>
                <a href="./farmer/orders.php" class="nav-link">Orders</a>
                <a href="./farmer/cooperative.php" class="nav-link">Cooperative</a>
            <?php elseif ($user_role === 'Buyer'): ?>
                <a href="./buyer/marketplace.php" class="nav-link">Marketplace</a>
                <a href="./buyer/orders.php" class="nav-link">My Orders</a>
                <a href="./buyer/farmers.php" class="nav-link">Farmers</a>
            <?php elseif ($user_role === 'Admin'): ?>
                <a href="./admin/users.php" class="nav-link">Users</a>
                <a href="./admin/reports.php" class="nav-link">Reports</a>
                <a href="./admin/system.php" class="nav-link">System</a>
            <?php endif; ?>
        </div>
        
        <div class="user-menu">
            <a href="./user/profile.php" class="user-menu-link">
    <div class="user-menu">
        <div class="user-avatar">
            <?= strtoupper(substr($first_name, 0, 1)) ?>
        </div>
    </div>
</a>

<form method="GET" style="display: inline;">
    <button type="submit" name="logout" value="1" class="logout-btn">Log Out</button>
</form>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h1>Welcome back, <?= htmlspecialchars($first_name) ?>!</h1>
            <p>Manage your farm-to-market activities efficiently with BANTAY-ANI.</p>
            <div class="role-badge"><?= $user_role ?></div>
        </div>

        <!-- Dashboard Stats -->
        <div class="dashboard-grid">
            <?php if ($user_role === 'Farmer'): ?>
                <div class="stat-card">
                    <div class="stat-icon farmer">🌱</div>
                    <div class="stat-value"><?= $dashboard_data['active_listings'] ?? 0 ?></div>
                    <div class="stat-label">Active Listings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon farmer">📦</div>
                    <div class="stat-value"><?= $dashboard_data['total_inventory'] ?? 0 ?></div>
                    <div class="stat-label">Total Inventory (kg)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon farmer">💰</div>
                    <div class="stat-value">₱<?= number_format($dashboard_data['avg_price'] ?? 0, 2) ?></div>
                    <div class="stat-label">Average Price</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon farmer">⏳</div>
                    <div class="stat-value"><?= $dashboard_data['pending_orders'] ?? 0 ?></div>
                    <div class="stat-label">Pending Orders</div>
                </div>
            <?php elseif ($user_role === 'Buyer'): ?>
                <div class="stat-card">
                    <div class="stat-icon buyer">📋</div>
                    <div class="stat-value"><?= $dashboard_data['total_orders'] ?? 0 ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon buyer">💰</div>
                    <div class="stat-value">₱<?= number_format($dashboard_data['total_spent'] ?? 0, 2) ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon buyer">👨‍🌾</div>
                    <div class="stat-value"><?= $dashboard_data['farmers_connected'] ?? 0 ?></div>
                    <div class="stat-label">Farmers Connected</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon buyer">⏳</div>
                    <div class="stat-value"><?= $dashboard_data['pending_orders'] ?? 0 ?></div>
                    <div class="stat-label">Pending Orders</div>
                </div>
            <?php elseif ($user_role === 'Admin'): ?>
                <div class="stat-card">
                    <div class="stat-icon admin">👥</div>
                    <div class="stat-value"><?= $dashboard_data['total_users'] ?? 0 ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon admin">👨‍🌾</div>
                    <div class="stat-value"><?= $dashboard_data['total_farmers'] ?? 0 ?></div>
                    <div class="stat-label">Farmers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon admin">🏪</div>
                    <div class="stat-value"><?= $dashboard_data['total_buyers'] ?? 0 ?></div>
                    <div class="stat-label">Buyers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon admin">🚚</div>
                    <div class="stat-value"><?= $dashboard_data['in_transit_deliveries'] ?? 0 ?></div>
                    <div class="stat-label">In Transit</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Left Column -->
            <div>
                <!-- Recent Orders Section -->
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">Recent Orders</h3>
                        <a href="orders.php" class="view-all">View All →</a>
                    </div>
                    
                    <?php if (!empty($recent_orders)): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Crops</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>#<?= $order['order_id'] ?></td>
                                        <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                                        <td><?= htmlspecialchars($order['crops']) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= strtolower($order['order_status']) ?>">
                                                <?= $order['order_status'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: var(--text-light); text-align: center; padding: 32px;">
                            No recent orders found.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Action Cards -->
                <div class="action-grid">
                    <?php if ($user_role === 'Farmer'): ?>
                        <a href="add-inventory.php" class="action-card">
                            <div class="action-icon">➕</div>
                            <div class="action-title">Add New Crop</div>
                            <div class="action-desc">List your crops for sale</div>
                        </a>
                        <a href="harvest-schedule.php" class="action-card">
                            <div class="action-icon">📅</div>
                            <div class="action-title">Harvest Schedule</div>
                            <div class="action-desc">Plan your harvest dates</div>
                        </a>
                        <a href="cooperative.php" class="action-card">
                            <div class="action-icon">🤝</div>
                            <div class="action-title">Join Cooperative</div>
                            <div class="action-desc">Pool crops with other farmers</div>
                        </a>
                        <a href="reports.php" class="action-card">
                            <div class="action-icon">📊</div>
                            <div class="action-title">View Reports</div>
                            <div class="action-desc">Track your sales and growth</div>
                        </a>
                    <?php elseif ($user_role === 'Buyer'): ?>
                        <a href="marketplace.php" class="action-card">
                            <div class="action-icon">🛒</div>
                            <div class="action-title">Browse Marketplace</div>
                            <div class="action-desc">Find fresh produce</div>
                        </a>
                        <a href="orders.php" class="action-card">
                            <div class="action-icon">📋</div>
                            <div class="action-title">My Orders</div>
                            <div class="action-desc">Track your purchases</div>
                        </a>
                        <a href="farmers.php" class="action-card">
                            <div class="action-icon">👨‍🌾</div>
                            <div class="action-title">Browse Farmers</div>
                            <div class="action-desc">Connect with local farmers</div>
                        </a>
                        <a href="settings.php" class="action-card">
                            <div class="action-icon">⚙️</div>
                            <div class="action-title">Account Settings</div>
                            <div class="action-desc">Update your preferences</div>
                        </a>
                    <?php elseif ($user_role === 'Admin'): ?>
                        <a href="users.php" class="action-card">
                            <div class="action-icon">👥</div>
                            <div class="action-title">Manage Users</div>
                            <div class="action-desc">View and verify users</div>
                        </a>
                        <a href="reports.php" class="action-card">
                            <div class="action-icon">📊</div>
                            <div class="action-title">System Reports</div>
                            <div class="action-desc">Generate system analytics</div>
                        </a>
                        <a href="verification.php" class="action-card">
                            <div class="action-icon">✅</div>
                            <div class="action-title">Verification Queue</div>
                            <div class="action-desc">Approve user registrations</div>
                        </a>
                        <a href="settings.php" class="action-card">
                            <div class="action-icon">⚙️</div>
                            <div class="action-title">System Settings</div>
                            <div class="action-desc">Configure system parameters</div>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Notifications Section -->
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">Notifications</h3>
                        <a href="notifications.php" class="view-all">View All →</a>
                    </div>
                    
                    <?php if (!empty($recent_notifications)): ?>
                        <ul class="notification-list">
                            <?php foreach ($recent_notifications as $notification): ?>
                                <li class="notification-item">
                                    <div class="notification-icon <?= strtolower($notification['notification_type']) ?>">
                                        <?php 
                                        switch($notification['notification_type']) {
                                            case 'Weather': echo '🌤️'; break;
                                            case 'Order': echo '📦'; break;
                                            case 'System': echo '🔔'; break;
                                            default: echo '📢';
                                        }
                                        ?>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-message">
                                            <?= htmlspecialchars($notification['message']) ?>
                                        </div>
                                        <div class="notification-time">
                                            <?= date('M d, h:i A', strtotime($notification['created_at'])) ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="color: var(--text-light); text-align: center; padding: 32px;">
                            No notifications yet.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Upcoming Harvests (Farmer Only) -->
                <?php if ($user_role === 'Farmer' && !empty($upcoming_harvests)): ?>
                    <div class="section-card">
                        <div class="section-header">
                            <h3 class="section-title">Upcoming Harvests</h3>
                            <a href="harvest-schedule.php" class="view-all">View All →</a>
                        </div>
                        
                        <div class="quick-stats">
                            <?php foreach ($upcoming_harvests as $harvest): ?>
                                <div class="quick-stat">
                                    <div class="quick-stat-label"><?= htmlspecialchars($harvest['crop_name']) ?></div>
                                    <div class="quick-stat-value"><?= date('M d', strtotime($harvest['harvest_date'])) ?></div>
                                    <div style="font-size: 0.9rem; color: var(--text-light);">
                                        <?= $harvest['quantity'] ?> kg
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- System Stats (Admin Only) -->
                <?php if ($user_role === 'Admin'): ?>
                    <div class="section-card">
                        <div class="section-header">
                            <h3 class="section-title">System Overview</h3>
                        </div>
                        
                        <div class="quick-stats">
                            <div class="quick-stat">
                                <div class="quick-stat-label">Monthly Volume</div>
                                <div class="quick-stat-value">₱<?= number_format($dashboard_data['total_volume'] ?? 0, 0) ?></div>
                            </div>
                            <div class="quick-stat">
                                <div class="quick-stat-label">Active Farmers</div>
                                <div class="quick-stat-value"><?= $dashboard_data['total_farmers'] ?? 0 ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <p>© <?= date('Y') ?> BANTAY-ANI Farm-to-Market System. All rights reserved.</p>
        <p style="margin-top: 8px; font-size: 0.85rem;">
            Connecting farmers and buyers, reducing waste, supporting local agriculture.
        </p>
    </footer>
</body>
</html>
