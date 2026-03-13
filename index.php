<?php
// No output before authentication check
require_once __DIR__ . '/includes/auth_helper.php';
require_login();
include('includes/config.php');

// Get user information
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$first_name = $_SESSION['first_name'];

// Admins go to admin dashboard
if ($user_role === 'Admin') {
    header('Location: admin/index.php');
    exit;
}

$profile_img = null;

$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

$profile_img_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $profile_img;

// Profile URL by role (for avatar link)
if ($user_role === 'Farmer') {
    $profile_url = './farmer/profile.php';
} elseif ($user_role === 'Buyer') {
    $profile_url = './buyer/profile.php';
} 

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

    $notification_count = count($recent_notifications);
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: user/login.php');
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
            gap: 20px;
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

        /* Search Bar Styles */
        .nav-search-container {
            flex: 1;
            max-width: 500px;
        }

        .nav-search-form {
            position: relative;
            width: 100%;
        }

        .nav-search-input {
            width: 100%;
            padding: 10px 45px 10px 20px;
            border: 2px solid var(--border);
            border-radius: 25px;
            font-size: 0.95rem;
            outline: none;
            font-family: 'Quicksand', sans-serif;
            transition: all 0.3s ease;
            background: white;
        }

        .nav-search-input:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(31, 138, 112, 0.1);
        }

        .nav-search-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--green);
            color: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-search-btn:hover {
            background: var(--green-dark);
            transform: translateY(-50%) scale(1.05);
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
            
            .navbar {
                flex-wrap: wrap;
            }
            
            .nav-search-container {
                order: 3;
                width: 100%;
                max-width: 100%;
                margin-top: 12px;
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

        .notification-icon-wrapper {
    position: relative;
    display: inline-block;
    cursor: pointer;
    transition: transform 0.2s;
}

.notification-icon-wrapper:hover {
    transform: scale(1.1);
}

.notification-bell {
    width: 28px;
    height: 28px;
    color: var(--green-dark);
}

.notification-bubble {
    position: absolute;
    top: -6px;
    right: -6px;
    background: red;
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 3px 6px;
    border-radius: 50%;
    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    animation: pop 0.3s ease-out;
}

/* Optional: little pop animation when notifications appear */
@keyframes pop {
    0% { transform: scale(0); }
    70% { transform: scale(1.2); }
    100% { transform: scale(1); }
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
        
        <!-- Search Bar -->
        <div class="nav-search-container">
            <form action="search.php" method="GET" class="nav-search-form">
                <input 
                    type="text" 
                    name="q" 
                    class="nav-search-input" 
                    placeholder="Search users, farmers, buyers..." 
                    autocomplete="off"
                >
                <button type="submit" class="nav-search-btn" title="Search">🔍</button>
            </form>
        </div>
        
        <div class="nav-links">
            <?php if ($user_role === 'Farmer'): ?>
                <a href="index.php" class="nav-link active">Dashboard</a>
                <a href="marketplace.php" class="nav-link">Marketplace</a>
                <a href="./farmer/inventory.php" class="nav-link">My Inventory</a>
                <a href="./farmer/orders.php" class="nav-link">Orders</a>
                <a href="./farmer/cooperative.php" class="nav-link">Cooperative</a>
                <a href="reports.php" class="nav-link">Reports</a>
                <a href="announcements.php" class="nav-link">Announcements</a>
                <a href="invoices.php" class="nav-link">Invoices</a>
                <a href="./farmer/notifications.php" class="nav-link">Notifications</a>
                
            <?php elseif ($user_role === 'Buyer'): ?>
                <a href="index.php" class="nav-link active">Dashboard</a>
                <a href="marketplace.php" class="nav-link">Marketplace</a>
                <a href="./buyer/cart.php" class="nav-link">Cart</a>
                <a href="./buyer/orders.php" class="nav-link">My Orders</a>
                <a href="announcements.php" class="nav-link">Announcements</a>
                <a href="invoices.php" class="nav-link">Invoices</a>
                <a href="./buyer/notifications.php" class="nav-link">Notifications</a>
            <?php endif; ?>
        </div>
        
        <?php
            $profile_img = trim($profile_img ?? '');
            $absolute_path = $_SERVER['DOCUMENT_ROOT'] . '/bantayani/' . $profile_img;
            $public_path   = '/bantayani/' . $profile_img;
        ?>

        <div class="user-menu">
            <a href="<?= htmlspecialchars($profile_url) ?>" title="View Profile">
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
            <?php endif; ?>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Left Column -->
            <div>
                <!-- Recent Orders Section -->
                <div class="section-card">
                    <div class="section-header">
                        <?php if ($user_role === 'Farmer'): ?>
                            <h3 class="section-title">Recent Orders</h3>
                            <a href="farmer/orders.php" class="view-all">View All →</a>
                        <?php elseif  ($user_role === 'Buyer'): ?>
                            <h3 class="section-title">Recent Orders</h3>
                            <a href="buyer/orders.php" class="view-all">View All →</a>
                            
                        <?php endif; ?>
                        
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
                        <a href="./farmer/inventory.php" class="action-card">
                            <div class="action-icon">➕</div>
                            <div class="action-title">Add New Crop</div>
                            <div class="action-desc">List your crops for sale</div>
                        </a>
                        <a href="./farmer/inventory.php" class="action-card">
                            <div class="action-icon">📅</div>
                            <div class="action-title">Harvest Schedule</div>
                            <div class="action-desc">Plan your harvest dates</div>
                        </a>
                        <a href="./farmer/cooperative.php" class="action-card">
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
                        <a href="./buyer/orders.php" class="action-card">
                            <div class="action-icon">📋</div>
                            <div class="action-title">My Orders</div>
                            <div class="action-desc">Track your purchases</div>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Announcements Widget -->
                <?php include 'includes/announcement_widget.php'; ?>

                <!-- Upcoming Harvests (Farmer Only) -->
                <?php if ($user_role === 'Farmer' && !empty($upcoming_harvests)): ?>
                    <div class="section-card" style="margin-top: 24px;">
                        <div class="section-header">
                            <h3 class="section-title">Upcoming Harvests</h3>
                            <a href="./farmer/inventory.php" class="view-all">View All →</a>
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
