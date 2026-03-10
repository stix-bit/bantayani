<?php
require_once __DIR__ . '/includes/auth_helper.php';
require_login();
include('includes/config.php');

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$first_name = $_SESSION['first_name'];

$profile_img = null;

$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

$profile_img_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $profile_img;

// Fetch data
// For top crops sold
$crops_result = $crops_result ?? [];
$farmers_result = $farmers_result ?? [];
$months = $months ?? [];
$income_data = $income_data ?? [];
$top_crops = [];
// For Farmer: fetch crops sold with total quantity and income
if ($user_role === 'Farmer') {
    $stmt = $conn->prepare("
        SELECT c.crop_name,
               IFNULL(SUM(oi.quantity),0) AS total_quantity,
               IFNULL(SUM(oi.quantity * ci.price),0) AS total_income
        FROM crops c
        LEFT JOIN crops_inventory ci ON c.crop_id = ci.crop_id AND ci.farmer_id = ?
        LEFT JOIN order_items oi ON ci.inventory_id = oi.inventory_id
        LEFT JOIN orders o ON oi.order_id = o.order_id AND o.order_status IN ('Delivered','Confirmed')
        GROUP BY c.crop_id
        ORDER BY total_quantity DESC
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $crops_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
 } elseif ($user_role === 'Admin') {

    // 1️⃣ Get farmer income
    $stmt = $conn->prepare("
        SELECT u.user_id, u.first_name, u.last_name,
               IFNULL(SUM(oi.quantity * ci.price), 0) AS total_income
        FROM users u
        LEFT JOIN crops_inventory ci ON u.user_id = ci.farmer_id
        LEFT JOIN order_items oi ON ci.inventory_id = oi.inventory_id
        LEFT JOIN orders o 
            ON oi.order_id = o.order_id 
            AND o.order_status IN ('Delivered','Confirmed')
        WHERE u.role = 'Farmer'
        GROUP BY u.user_id
        ORDER BY total_income DESC
    ");
    $stmt->execute();
    $farmers_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();


    // 2️⃣ Get ALL crops sold (THIS WAS MISSING)
    $stmt = $conn->prepare("
        SELECT c.crop_name,
               IFNULL(SUM(oi.quantity),0) AS total_quantity,
               IFNULL(SUM(oi.quantity * ci.price),0) AS total_income
        FROM crops c
        LEFT JOIN crops_inventory ci ON c.crop_id = ci.crop_id
        LEFT JOIN order_items oi ON ci.inventory_id = oi.inventory_id
        LEFT JOIN orders o 
            ON oi.order_id = o.order_id 
            AND o.order_status IN ('Delivered','Confirmed')
        GROUP BY c.crop_id
        ORDER BY total_quantity DESC
    ");
    $stmt->execute();
    $crops_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}


// For top sellers (Admin only)
$top_sellers = [];
if ($user_role === 'Admin') {
    $stmt = $conn->prepare("
        SELECT u.first_name, u.last_name, SUM(oi.quantity * ci.price) as total_income
        FROM order_items oi
        JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
        JOIN orders o ON oi.order_id = o.order_id
        JOIN users u ON ci.farmer_id = u.user_id
        WHERE o.order_status IN ('Delivered','Confirmed')
        GROUP BY ci.farmer_id
        ORDER BY total_income DESC
    ");
    $stmt->execute();
    $top_sellers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Monthly income chart
$months = [];
$income_data = [];

if ($user_role === 'Farmer') {
    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(o.order_date,'%Y-%m') as month, SUM(oi.quantity * ci.price) as income
        FROM order_items oi
        JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE ci.farmer_id = ? AND o.order_status IN ('Delivered','Confirmed')
        GROUP BY month
        ORDER BY month ASC
    ");
    $stmt->bind_param('i', $user_id);
} else {
    // Admin: total sales per month
    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(o.order_date,'%Y-%m') as month, SUM(oi.quantity * ci.price) as income
        FROM order_items oi
        JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_status IN ('Delivered','Confirmed')
        GROUP BY month
        ORDER BY month ASC
    ");
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $months[] = $row['month'];
    $income_data[] = round($row['income'], 2);
}
$stmt->close();
?>



<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports | BANTAY-ANI</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <a href="index.php" class="nav-link">Dashboard</a>
                <a href="marketplace.php" class="nav-link">Marketplace</a>
                <a href="./farmer/inventory.php" class="nav-link">My Inventory</a>
                <a href="./farmer/orders.php" class="nav-link">Orders</a>
                <a href="./farmer/cooperative.php" class="nav-link">Cooperative</a>
                <a href="reports.php" class="nav-link active">Reports</a>
                <a href="announcements.php" class="nav-link">Announcements</a>
                <a href="invoices.php" class="nav-link">Invoices</a>
                <a href="./farmer/notifications.php" class="nav-link">Notifications</a>
            <?php elseif ($user_role === 'Admin'): ?>
                <a href="./admin/index.php" class="nav-link">Dashboard</a>
                <a href="./admin/orders.php" class="nav-link">Orders</a>
                <a href="reports.php" class="nav-link active">Reports</a>
                <a href="./admin/announcements.php" class="nav-link">Announcements</a>
            <?php endif; ?>
        </div>
        
        <div class="user-menu"
            <?php
                $profile_img = trim($profile_img ?? '');
                $absolute_path = $_SERVER['DOCUMENT_ROOT'] . '/bantayani/' . $profile_img;
                $public_path   = '/bantayani/' . $profile_img;
            ?>

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

<main class="container">
    <div class="welcome-banner">
        <h1>Reports</h1>
        <p>Insights and charts for <?= htmlspecialchars($first_name) ?> (<?= $user_role ?>)</p>
    </div>

    <div class="dashboard-grid">
        <?php if ($user_role === 'Admin'): ?>
            <div class="stat-card">
                <div class="stat-icon admin">👨‍🌾</div>
                <div class="stat-value"><?= count($farmers_result) ?></div>
                <div class="stat-label">Farmers with Sales</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon admin">🌾</div>
                <div class="stat-value"><?= count($crops_result) ?></div>
                <div class="stat-label">Crops Sold</div>
            </div>
        <?php else: ?>
            <div class="stat-card">
                <div class="stat-icon farmer">🌾</div>
                <div class="stat-label">Crops Sold</div>
                <div class="stat-value"><?= count($crops_result) ?> units </div>
                
            </div>
        <?php endif; ?>
    </div>

    <div class="content-grid">
        <?php if ($user_role === 'Admin' && !empty($farmers_result)): ?>
            <div class="section-card">
                <div class="section-header"><h3 class="section-title">Farmers Income (₱)</h3></div>
                <canvas id="farmersChart"></canvas>
            </div>
        <?php endif; ?>

        <?php if (!empty($crops_result)): ?>
            <div class="section-card" style="margin-top:24px;">
                <div class="section-header"><h3 class="section-title">Crops Sold</h3></div>
                <div class="stat-value"><?= count($crops_result) ?> units </div>
                <canvas id="cropsChart"></canvas>
            </div>
        <?php endif; ?>

        <!-- New Monthly Income / Sales Chart -->
        <div class="section-card" style="margin-top:24px;">
            <div class="section-header">
                <h3 class="section-title"><?= $user_role === 'Farmer' ? 'Your Monthly Income (₱)' : 'Monthly Sales (₱)' ?></h3>
            </div>
            <canvas id="monthlyIncomeChart"></canvas>
        </div>
    </div>
</main>

<script>
<?php if ($user_role === 'Admin' && !empty($farmers_result)): ?>
const farmersCtx = document.getElementById('farmersChart').getContext('2d');
new Chart(farmersCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($f)=>$f['first_name'].' '.$f['last_name'],$farmers_result)) ?>,
        datasets: [{
            label: 'Income (₱)',
            data: <?= json_encode(array_column($farmers_result,'total_income')) ?>,
            backgroundColor: 'rgba(31,138,112,0.7)'
        }]
    },
    options: {
        responsive:true,
        plugins:{legend:{display:false}},
        scales:{y:{beginAtZero:true}}
    }
});
<?php endif; ?>

<?php if (!empty($crops_result)): ?>
const cropsCtx = document.getElementById('cropsChart').getContext('2d');
new Chart(cropsCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($crops_result,'crop_name')) ?>,
        datasets: [{
            label: 'Quantity Sold (kg)',
            data: <?= json_encode(array_column($crops_result,'total_quantity')) ?>,
            backgroundColor: 'rgba(255,159,64,0.7)'
        },{
            label: 'Income (₱)',
            data: <?= json_encode(array_column($crops_result,'total_income')) ?>,
            backgroundColor: 'rgba(31,138,112,0.7)'
        }]
    },
    options:{
        responsive:true,
        plugins:{legend:{display:true}},
        scales:{y:{beginAtZero:true}}
    }
});
<?php endif; ?>

<?php if(!empty($months) && !empty($income_data)): ?>
const monthlyCtx = document.getElementById('monthlyIncomeChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($months) ?>,
        datasets:[{
            label: '<?= $user_role === 'Farmer' ? 'Income (₱)' : 'Sales (₱)' ?>',
            data: <?= json_encode($income_data) ?>,
            backgroundColor: 'rgba(31,138,112,0.7)'
        }]
    },
    options:{
        responsive:true,
        plugins:{legend:{display:false}},
        scales:{y:{beginAtZero:true}}
    }
});
<?php endif; ?>
</script>

<footer class="footer">
    <p>© <?= date('Y') ?> BANTAY-ANI Farm-to-Market System. All rights reserved.</p>
</footer>
</body>
</html>