<?php
session_start();
require_once __DIR__ . '/includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: user/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$first_name = $_SESSION['first_name'];

// Get profile image
$profile_img = null;
$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

// Mark announcement as viewed if ID is provided
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $announcement_id = (int)$_GET['view'];
    
    // Insert or update view
    $stmt = $conn->prepare("
        INSERT INTO announcement_views (announcement_id, user_id)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE viewed_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param("ii", $announcement_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Update views count
    $conn->query("UPDATE announcements SET views_count = views_count + 1 WHERE announcement_id = $announcement_id");
}

// Fetch active announcements for this user's role
$query = "
    SELECT a.*, 
           CONCAT(u.first_name, ' ', u.last_name) as creator_name,
           (SELECT COUNT(*) FROM announcement_views WHERE announcement_id = a.announcement_id AND user_id = ?) as is_viewed
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.user_id
    WHERE a.is_active = 1
    AND (a.target_audience = 'All' OR a.target_audience = ?)
    AND (a.expires_at IS NULL OR a.expires_at > NOW())
    ORDER BY 
        CASE a.priority 
            WHEN 'Urgent' THEN 1
            WHEN 'High' THEN 2
            WHEN 'Medium' THEN 3
            ELSE 4
        END,
        a.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("is", $user_id, $user_role);
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get single announcement if viewing
$viewing_announcement = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $view_id = (int)$_GET['view'];
    $stmt = $conn->prepare("
        SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as creator_name
        FROM announcements a
        LEFT JOIN users u ON a.created_by = u.user_id
        WHERE a.announcement_id = ?
    ");
    $stmt->bind_param("i", $view_id);
    $stmt->execute();
    $viewing_announcement = $stmt->get_result()->fetch_assoc();
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
    <title>Announcements - BANTAY-ANI</title>
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
            --shadow-sm: 0 2px 8px rgba(12, 92, 76, 0.08);
            --shadow-md: 0 8px 20px rgba(12, 92, 76, 0.12);
            --shadow-lg: 0 25px 60px rgba(12, 92, 76, 0.2);
            --radius-sm: 12px;
            --radius-md: 20px;
            --radius-lg: 28px;
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
            line-height: 1.5;
            min-height: 100vh;
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
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(31, 138, 112, 0.3);
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
            color: #b91c1c;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px;
        }

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 2.5rem;
            color: var(--green-dark);
            margin-bottom: 8px;
        }

        .page-header p {
            font-size: 1.1rem;
            color: var(--text-light);
        }

        /* Announcements Grid */
        .announcements-grid {
            display: grid;
            gap: 24px;
        }

        .announcement-card {
            background: white;
            border-radius: var(--radius-md);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s;
            border: 2px solid transparent;
            position: relative;
            cursor: pointer;
        }

        .announcement-card:hover {
            border-color: var(--green-light);
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }

        .announcement-card.unread {
            border-left: 4px solid var(--orange);
        }

        .announcement-card.urgent {
            border: 2px solid #dc2626;
            background: rgba(239, 68, 68, 0.02);
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .announcement-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--green-dark);
            margin-bottom: 8px;
        }

        .announcement-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-general { background: rgba(100, 100, 100, 0.1); color: #666; }
        .badge-weather { background: rgba(59, 130, 246, 0.1); color: #2563eb; }
        .badge-system { background: rgba(168, 85, 247, 0.1); color: #7c3aed; }
        .badge-event { background: rgba(236, 72, 153, 0.1); color: #db2777; }
        .badge-alert { background: rgba(239, 68, 68, 0.1); color: #dc2626; }

        .badge-urgent { 
            background: rgba(220, 38, 38, 0.1); 
            color: #dc2626;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        .announcement-content {
            color: var(--text);
            line-height: 1.7;
            margin-bottom: 16px;
        }

        .announcement-image {
            width: 100%;
            max-height: 300px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            margin-bottom: 16px;
        }

        .announcement-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid var(--border);
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .unread-badge {
            background: var(--orange);
            color: white;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Modal for full announcement view */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 2000;
            padding: 20px;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: var(--radius-lg);
            padding: 40px;
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--beige);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .modal-close:hover {
            background: #e8dfd0;
            transform: rotate(90deg);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .empty-state h2 {
            color: var(--green-dark);
            margin-bottom: 12px;
        }

        .empty-state p {
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
            background: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                flex-wrap: wrap;
            }

            .nav-links {
                width: 100%;
                justify-content: center;
                margin-top: 12px;
            }

            .container {
                padding: 16px;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .modal-content {
                padding: 24px;
            }
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
            <?php if ($user_role === 'Farmer'): ?>
                <a href="index.php" class="nav-link">Dashboard</a>
                <a href="marketplace.php" class="nav-link">Marketplace</a>
                <a href="./farmer/inventory.php" class="nav-link">My Inventory</a>
                <a href="./farmer/orders.php" class="nav-link">Orders</a>
                <a href="./farmer/cooperative.php" class="nav-link">Cooperative</a>
                <a href="reports.php" class="nav-link">Reports</a>
                <a href="announcements.php" class="nav-link active">Announcements</a>
                <a href="invoices.php" class="nav-link">Invoices</a>
                <a href="./farmer/notifications.php" class="nav-link">Notifications</a>
            <?php elseif ($user_role === 'Buyer'): ?>
                <a href="index.php" class="nav-link">Dashboard</a>
                <a href="marketplace.php" class="nav-link">Marketplace</a>
                <a href="./buyer/cart.php" class="nav-link">Cart</a>
                <a href="./buyer/orders.php" class="nav-link">My Orders</a>
                <a href="announcements.php" class="nav-link active">Announcements</a>
                <a href="invoices.php" class="nav-link">Invoices</a>
                <a href="./buyer/notifications.php" class="nav-link">Notifications</a>
            <?php endif; ?>
        </div>
        
        <div class="user-menu">
            <?php
                $profile_img = trim($profile_img ?? '');
                $absolute_path = $_SERVER['DOCUMENT_ROOT'] . '/bantayani/' . $profile_img;
                $public_path   = '/bantayani/' . $profile_img;
            ?>
            <a href="./user/profile.php" title="View Profile">
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
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>📢 Announcements</h1>
            <p>Stay updated with the latest news and information</p>
        </div>

        <!-- Announcements Grid -->
        <?php if (empty($announcements)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h2>No Announcements</h2>
                <p>There are currently no announcements to display.</p>
            </div>
        <?php else: ?>
            <div class="announcements-grid">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-card <?= $announcement['is_viewed'] ? '' : 'unread' ?> <?= $announcement['priority'] === 'Urgent' ? 'urgent' : '' ?>"
                         onclick="window.location.href='?view=<?= $announcement['announcement_id'] ?>'">
                        
                        <div class="announcement-header">
                            <div style="flex: 1;">
                                <div class="announcement-title">
                                    <?= htmlspecialchars($announcement['title']) ?>
                                </div>
                                <div class="announcement-meta">
                                    <span class="badge badge-<?= strtolower($announcement['announcement_type']) ?>">
                                        <?= $announcement['announcement_type'] ?>
                                    </span>
                                    <?php if ($announcement['priority'] === 'Urgent' || $announcement['priority'] === 'High'): ?>
                                        <span class="badge badge-<?= strtolower($announcement['priority']) ?>">
                                            <?= $announcement['priority'] ?> Priority
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!$announcement['is_viewed']): ?>
                                <span class="unread-badge">NEW</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($announcement['image_path'])): ?>
                            <img src="<?= htmlspecialchars($announcement['image_path']) ?>" 
                                 class="announcement-image" 
                                 alt="Announcement">
                        <?php endif; ?>

                        <div class="announcement-content">
                            <?= nl2br(htmlspecialchars(substr($announcement['content'], 0, 250))) ?>
                            <?php if (strlen($announcement['content']) > 250): ?>...<?php endif; ?>
                        </div>

                        <div class="announcement-footer">
                            <span>By <?= htmlspecialchars($announcement['creator_name']) ?></span>
                            <span><?= date('M d, Y h:i A', strtotime($announcement['created_at'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal for Full View -->
    <?php if ($viewing_announcement): ?>
    <div class="modal active" id="announcementModal">
        <div class="modal-content">
            <button class="modal-close" onclick="window.location.href='announcements.php'">✕</button>
            
            <div class="announcement-title" style="margin-bottom: 16px;">
                <?= htmlspecialchars($viewing_announcement['title']) ?>
            </div>

            <div class="announcement-meta" style="margin-bottom: 24px;">
                <span class="badge badge-<?= strtolower($viewing_announcement['announcement_type']) ?>">
                    <?= $viewing_announcement['announcement_type'] ?>
                </span>
                <span class="badge badge-<?= strtolower($viewing_announcement['priority']) ?>">
                    <?= $viewing_announcement['priority'] ?> Priority
                </span>
            </div>

            <?php if (!empty($viewing_announcement['image_path'])): ?>
                <img src="<?= htmlspecialchars($viewing_announcement['image_path']) ?>" 
                     class="announcement-image" 
                     alt="Announcement">
            <?php endif; ?>

            <div class="announcement-content">
                <?= nl2br(htmlspecialchars($viewing_announcement['content'])) ?>
            </div>

            <div class="announcement-footer" style="margin-top: 24px;">
                <span>By <?= htmlspecialchars($viewing_announcement['creator_name']) ?></span>
                <span><?= date('M d, Y h:i A', strtotime($viewing_announcement['created_at'])) ?></span>
            </div>

            <?php if ($viewing_announcement['expires_at']): ?>
                <div style="margin-top: 16px; padding: 12px; background: rgba(251, 191, 36, 0.1); border-radius: 8px; font-size: 0.9rem; color: #d97706;">
                    ⏰ This announcement expires on <?= date('M d, Y h:i A', strtotime($viewing_announcement['expires_at'])) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <p>© <?= date('Y') ?> BANTAY-ANI Farm-to-Market System. All rights reserved.</p>
        <p style="margin-top: 8px; font-size: 0.85rem;">
            Connecting farmers and buyers, reducing waste, supporting local agriculture.
        </p>
    </footer>
</body>
</html>
