<?php
session_start();
include "includes/config.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: user/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$first_name = $_SESSION['first_name'];

if ($user_role === 'Farmer') {
    $profile_url = './farmer/profile.php';
} elseif ($user_role === 'Buyer') {
    $profile_url = './buyer/profile.php';
} 

// Get profile image
$profile_img = null;
$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

// Get search query
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_results = [];

if (!empty($search_query)) {
    // Search for users (farmers and buyers)
    $search_term = "%{$search_query}%";
    
    $sql = "SELECT 
                u.user_id, 
                u.first_name, 
                u.last_name, 
                u.email, 
                u.role, 
                u.img_path,
                CASE 
                    WHEN u.role = 'Farmer' THEN f.farm_name
                    WHEN u.role = 'Buyer' THEN co.company_name
                    ELSE NULL
                END as profile_name,
                CASE 
                    WHEN u.role = 'Farmer' THEN f.farm_location
                    ELSE u.address
                END as location
            FROM users u
            LEFT JOIN farmer_profiles f ON u.user_id = f.farmer_id
            LEFT JOIN buyer_profiles b ON u.user_id = b.buyer_id
            LEFT JOIN company_buyers c on b.buyer_id = c.buyer_id
            LEFT JOIN companies co ON c.company_id = co.company_id
            WHERE (u.first_name LIKE ? 
                OR u.last_name LIKE ? 
                OR u.email LIKE ?
                OR f.farm_name LIKE ?
                OR co.company_name LIKE ?)
            AND u.role IN ('Farmer', 'Buyer')
            AND u.user_id != ?
            ORDER BY u.first_name ASC
            LIMIT 20";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $search_term, $search_term, $search_term, $search_term, $search_term, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $search_results = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - BANTAY-ANI</title>
    <link rel="stylesheet" href="includes/styles/search.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="logo-container">
            <div class="logo">BA</div>
            <div class="logo-text">BANTAY<span>ANI</span></div>
        </div>
        
        <!-- Search Bar in Navigation -->
        <div class="nav-search-container">
            <form action="search.php" method="GET" class="nav-search-form">
                <input 
                    type="text" 
                    name="q" 
                    class="nav-search-input" 
                    placeholder="Search users, farmers, buyers..." 
                    value="<?= htmlspecialchars($search_query) ?>"
                    autocomplete="off"
                >
                <button type="submit" class="nav-search-btn">🔍</button>
            </form>
        </div>
        
        <div class="nav-links">
            <div class="nav-links">
            <?php if ($user_role === 'Farmer'): ?>
                <a href="index.php" class="nav-link">Dashboard</a>
                <a href="./farmer/inventory.php" class="nav-link">My Inventory</a>
                <a href="./farmer/orders.php" class="nav-link active">Orders</a>
                <a href="./farmer/cooperative.php" class="nav-link">Cooperative</a>
                <a href="./farmer/benchmarking.php" class="nav-link">Pricing</a>
                <a href="./farmer/reports.php" class="nav-link">Reports</a>
                <a href="announcements.php" class="nav-link">Announcements</a>
                <a href="invoices.php" class="nav-link">Invoices</a>
                <a href="./farmer/notifications.php" class="nav-link">Notifications</a>
            <?php elseif ($user_role === 'Buyer'): ?>
                <a href="index.php" class="nav-link active">Dashboard</a>
                <a href="marketplace.php" class="nav-link">Marketplace</a>
                <a href="./buyer/orders.php" class="nav-link">My Orders</a>
                <a href="reports.php" class="nav-link">Reports</a>
                <a href="announcements.php" class="nav-link">Announcements</a>
                <a href="invoices.php" class="nav-link">Invoices</a>
                <a href="./buyer/notifications.php" class="nav-link">Notifications</a>
            <?php elseif ($user_role === 'Admin'): ?>
                <a href="index.php" class="nav-link">Dashboard</a>
                <a href="./admin/users.php" class="nav-link">Users</a>
            <?php endif; ?>
        </div>
        
        <div class="user-menu">
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
            <form method="GET" style="display:inline;">
                <button type="submit" name="logout" value="1" class="logout-btn">
                    Log Out
                </button>
            </form>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <div class="search-header">
            <h1>Search Results</h1>
            <?php if (!empty($search_query)): ?>
                <p>Showing results for "<strong><?= htmlspecialchars($search_query) ?></strong>"</p>
            <?php else: ?>
                <p>Enter a search term to find users, farmers, and buyers</p>
            <?php endif; ?>
        </div>

        <div class="results-container">
            <?php if (!empty($search_query)): ?>
                <?php if (count($search_results) > 0): ?>
                    <div class="results-grid">
                        <?php foreach ($search_results as $user): ?>
                            <?php
                                $is_admin = ($user['role'] === 'Admin');
                                $profile_url = $is_admin ? '#' : './user/view_profile.php?id=' . (int) $user['user_id'];
                            ?>
                            <div class="user-card">
                                <?php if (!$is_admin): ?>
                                <a href="<?= htmlspecialchars($profile_url) ?>" class="user-card-link" aria-label="View <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> profile">
                                <?php endif; ?>
                                <div class="user-card-header">
                                    <div class="user-card-avatar">
                                        <?php if (!empty($user['img_path'])): ?>
                                            <?php
                                                $user_img_path = '/bantayani/' . trim($user['img_path']);
                                            ?>
                                            <img src="<?= htmlspecialchars($user_img_path) ?>"
                                                 alt="<?= htmlspecialchars($user['first_name']) ?>"
                                                 style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                                        <?php else: ?>
                                            <?= strtoupper(substr($user['first_name'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-card-info">
                                        <h3><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                                        <span class="role-badge role-<?= strtolower($user['role']) ?>">
                                            <?= $user['role'] ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="user-card-body">
                                    <?php if (!empty($user['profile_name'])): ?>
                                        <p class="profile-detail">
                                            <span class="detail-icon">🏢</span>
                                            <strong><?= htmlspecialchars($user['profile_name']) ?></strong>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($user['location'])): ?>
                                        <p class="profile-detail">
                                            <span class="detail-icon">📍</span>
                                            <?= htmlspecialchars($user['location']) ?>
                                        </p>
                                    <?php endif; ?>

                                    <p class="profile-detail">
                                        <span class="detail-icon">✉️</span>
                                        <?= htmlspecialchars($user['email']) ?>
                                    </p>
                                </div>
                                <?php if (!$is_admin): ?>
                                </a>
                                <?php endif; ?>

                                <div class="user-card-footer">
                                    <?php if (!$is_admin): ?>
                                        <?php if ($user['role'] === 'Farmer'): ?>
                                            <a href="./user/view_profile.php?id=<?= (int) $user['user_id'] ?>" class="view-btn">
                                                View Products
                                            </a>
                                        <?php elseif ($user['role'] === 'Buyer'): ?>
                                            <a href="./user/view_profile.php?id=<?= (int) $user['user_id'] ?>" class="view-btn">
                                                View Orders
                                            </a>
                                        <?php else: ?>
                                            <a href="./user/view_profile.php?id=<?= (int) $user['user_id'] ?>" class="view-btn">
                                                View Profile
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="results-count">
                        Found <?= count($search_results) ?> result<?= count($search_results) !== 1 ? 's' : '' ?>
                    </div>
                <?php else: ?>
                    <div class="no-results">
                        <div class="no-results-icon">🔍</div>
                        <h2>No results found</h2>
                        <p>Try searching with different keywords or check your spelling</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="search-tips">
                    <h2>Search Tips</h2>
                    <ul>
                        <li>🔹 Search by name, farm name, or business name</li>
                        <li>🔹 Search by email address</li>
                        <li>🔹 Use specific keywords for better results</li>
                        <li>🔹 Results are limited to farmers and buyers</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>© <?= date('Y') ?> BANTAY-ANI Farm-to-Market System. All rights reserved.</p>
        <p style="margin-top: 8px; font-size: 0.85rem;">
            Connecting farmers and buyers, reducing waste, supporting local agriculture.
        </p>
    </footer>
</body>
</html>
