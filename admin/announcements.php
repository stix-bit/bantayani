<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../user/login.php');
    exit;
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['first_name'];
$errors = [];
$success = '';

// Get admin profile image
$profile_img = null;
$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

// Handle Delete Announcement
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Get image path before deleting
    $stmt = $conn->prepare("SELECT image_path FROM announcements WHERE announcement_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->bind_result($img_to_delete);
    $stmt->fetch();
    $stmt->close();
    
    // Delete the announcement
    $stmt = $conn->prepare("DELETE FROM announcements WHERE announcement_id = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        // Delete image file if exists
        if ($img_to_delete && file_exists("../" . $img_to_delete)) {
            unlink("../" . $img_to_delete);
        }
        $success = 'Announcement deleted successfully!';
    }
    $stmt->close();
}

// Handle Toggle Active Status
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $toggle_id = (int)$_GET['toggle'];
    $stmt = $conn->prepare("UPDATE announcements SET is_active = NOT is_active WHERE announcement_id = ?");
    $stmt->bind_param("i", $toggle_id);
    $stmt->execute();
    $stmt->close();
    $success = 'Announcement status updated!';
}

// Handle Create/Edit Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $announcement_type = $_POST['announcement_type'] ?? 'General';
    $priority = $_POST['priority'] ?? 'Medium';
    $target_audience = $_POST['target_audience'] ?? 'All';
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : NULL;
    $announcement_id = !empty($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : 0;
    
    if (empty($title) || empty($content)) {
        $errors[] = 'Title and content are required.';
    }
    
    // Handle image upload
    $image_path = null;
    if (!empty($_FILES['announcement_image']['name']) && $_FILES['announcement_image']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['announcement_image']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['announcement_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($ext, $allowed)) {
            $upload_dir = __DIR__ . '/../uploads/announcements';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $new_name = 'announcement_' . uniqid() . '.' . $ext;
            $destination = $upload_dir . '/' . $new_name;
            
            if (move_uploaded_file($tmp, $destination)) {
                $image_path = 'uploads/announcements/' . $new_name;
            }
        } else {
            $errors[] = 'Invalid image type. Only JPG, PNG, and GIF allowed.';
        }
    }
    
    if (empty($errors)) {
        if ($announcement_id > 0) {
            // Update existing announcement
            if ($image_path) {
                $stmt = $conn->prepare("UPDATE announcements SET title=?, content=?, announcement_type=?, priority=?, target_audience=?, expires_at=?, image_path=? WHERE announcement_id=?");
                $stmt->bind_param("sssssssi", $title, $content, $announcement_type, $priority, $target_audience, $expires_at, $image_path, $announcement_id);
            } else {
                $stmt = $conn->prepare("UPDATE announcements SET title=?, content=?, announcement_type=?, priority=?, target_audience=?, expires_at=? WHERE announcement_id=?");
                $stmt->bind_param("ssssssi", $title, $content, $announcement_type, $priority, $target_audience, $expires_at, $announcement_id);
            }
        } else {
            // Create new announcement
            $stmt = $conn->prepare("INSERT INTO announcements (title, content, announcement_type, priority, target_audience, created_by, expires_at, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssiss", $title, $content, $announcement_type, $priority, $target_audience, $admin_id, $expires_at, $image_path);
        }
        
        if ($stmt->execute()) {
            $success = $announcement_id > 0 ? 'Announcement updated successfully!' : 'Announcement created successfully!';
            header("Location: announcements.php?success=" . urlencode($success));
            exit;
        } else {
            $errors[] = 'Database error: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Get announcement to edit
$edit_announcement = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE announcement_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_announcement = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch all announcements with creator info
$announcements_query = "
    SELECT a.*, 
           CONCAT(u.first_name, ' ', u.last_name) as creator_name,
           (SELECT COUNT(*) FROM announcement_views WHERE announcement_id = a.announcement_id) as view_count
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.user_id
    ORDER BY a.created_at DESC
";
$announcements = $conn->query($announcements_query)->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_announcements,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_announcements,
        SUM(CASE WHEN target_audience = 'Farmers' THEN 1 ELSE 0 END) as farmer_announcements,
        SUM(CASE WHEN target_audience = 'Buyers' THEN 1 ELSE 0 END) as buyer_announcements,
        SUM(views_count) as total_views
    FROM announcements
";
$stats = $conn->query($stats_query)->fetch_assoc();

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
    <title>Manage Announcements - BANTAY-ANI</title>
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
            color: #b91c1c;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 32px;
        }

        .page-header h1 {
            font-size: 2.5rem;
            color: var(--green-dark);
            margin-bottom: 8px;
        }

        .page-header p {
            color: var(--text-light);
            font-size: 1.1rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--green);
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Form Section */
        .form-section {
            background: white;
            padding: 32px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            margin-bottom: 32px;
        }

        .form-section h2 {
            color: var(--green-dark);
            margin-bottom: 24px;
            font-size: 1.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text);
        }

        input[type="text"],
        input[type="datetime-local"],
        select,
        textarea {
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.3s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(31, 138, 112, 0.1);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(31, 138, 112, 0.3);
        }

        .btn-secondary {
            background: var(--beige);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: #e8dfd0;
        }

        /* Announcements List */
        .announcements-list {
            background: white;
            padding: 32px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
        }

        .announcements-list h2 {
            color: var(--green-dark);
            margin-bottom: 24px;
            font-size: 1.5rem;
        }

        .announcement-item {
            border: 2px solid var(--border);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s;
        }

        .announcement-item:hover {
            border-color: var(--green-light);
            box-shadow: var(--shadow-sm);
        }

        .announcement-item.inactive {
            opacity: 0.6;
            background: #f9f9f9;
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .announcement-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--green-dark);
            margin-bottom: 8px;
        }

        .announcement-meta {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-general { background: rgba(100, 100, 100, 0.1); color: #666; }
        .badge-weather { background: rgba(59, 130, 246, 0.1); color: #2563eb; }
        .badge-system { background: rgba(168, 85, 247, 0.1); color: #7c3aed; }
        .badge-event { background: rgba(236, 72, 153, 0.1); color: #db2777; }
        .badge-alert { background: rgba(239, 68, 68, 0.1); color: #dc2626; }

        .badge-low { background: rgba(34, 197, 94, 0.1); color: #16a34a; }
        .badge-medium { background: rgba(251, 191, 36, 0.1); color: #d97706; }
        .badge-high { background: rgba(249, 115, 22, 0.1); color: #ea580c; }
        .badge-urgent { background: rgba(220, 38, 38, 0.1); color: #dc2626; }

        .badge-all { background: rgba(31, 138, 112, 0.1); color: var(--green); }
        .badge-farmers { background: rgba(34, 197, 94, 0.1); color: #16a34a; }
        .badge-buyers { background: rgba(242, 135, 5, 0.1); color: var(--orange); }

        .announcement-content {
            color: var(--text-light);
            margin-bottom: 12px;
            line-height: 1.6;
        }

        .announcement-actions {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.9rem;
        }

        .btn-edit {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }

        .btn-toggle {
            background: rgba(251, 191, 36, 0.1);
            color: #d97706;
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        /* Alerts */
        .alert {
            padding: 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 24px;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #16a34a;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #dc2626;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .navbar {
                flex-wrap: wrap;
            }

            .nav-links {
                width: 100%;
                justify-content: center;
                margin-top: 12px;
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
            <a href="index.php" class="nav-link">Dashboard</a>
            <a href="announcements.php" class="nav-link active">Announcements</a>
        </div>
        
        <div class="user-menu">
            <?php
                $profile_img = trim($profile_img ?? '');
                $absolute_path = $_SERVER['DOCUMENT_ROOT'] . '/bantayani/' . $profile_img;
                $public_path   = '/bantayani/' . $profile_img;
            ?>
            <a href="../user/profile.php" title="View Profile">
                <div class="user-avatar">
                    <?php if (!empty($profile_img) && file_exists($absolute_path)): ?>
                        <img src="<?= htmlspecialchars($public_path) ?>"
                             alt="Profile"
                             style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                    <?php else: ?>
                        <?= strtoupper(substr($admin_name, 0, 1)) ?>
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
            <h1>📢 Manage Announcements</h1>
            <p>Create and manage system-wide announcements for farmers and buyers</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success) || isset($_GET['success'])): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($success ?: $_GET['success']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <div>❌ <?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_announcements'] ?? 0 ?></div>
                <div class="stat-label">Total Announcements</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['active_announcements'] ?? 0 ?></div>
                <div class="stat-label">Active Announcements</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['farmer_announcements'] ?? 0 ?></div>
                <div class="stat-label">For Farmers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['buyer_announcements'] ?? 0 ?></div>
                <div class="stat-label">For Buyers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_views'] ?? 0 ?></div>
                <div class="stat-label">Total Views</div>
            </div>
        </div>

        <!-- Create/Edit Form -->
        <div class="form-section">
            <h2><?= $edit_announcement ? '✏️ Edit Announcement' : '➕ Create New Announcement' ?></h2>
            <form method="POST" enctype="multipart/form-data">
                <?php if ($edit_announcement): ?>
                    <input type="hidden" name="announcement_id" value="<?= $edit_announcement['announcement_id'] ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="title">Title *</label>
                        <input type="text" id="title" name="title" 
                               value="<?= htmlspecialchars($edit_announcement['title'] ?? '') ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="announcement_type">Type</label>
                        <select id="announcement_type" name="announcement_type">
                            <option value="General" <?= ($edit_announcement['announcement_type'] ?? '') === 'General' ? 'selected' : '' ?>>General</option>
                            <option value="Weather" <?= ($edit_announcement['announcement_type'] ?? '') === 'Weather' ? 'selected' : '' ?>>Weather</option>
                            <option value="System" <?= ($edit_announcement['announcement_type'] ?? '') === 'System' ? 'selected' : '' ?>>System</option>
                            <option value="Event" <?= ($edit_announcement['announcement_type'] ?? '') === 'Event' ? 'selected' : '' ?>>Event</option>
                            <option value="Alert" <?= ($edit_announcement['announcement_type'] ?? '') === 'Alert' ? 'selected' : '' ?>>Alert</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority">
                            <option value="Low" <?= ($edit_announcement['priority'] ?? '') === 'Low' ? 'selected' : '' ?>>Low</option>
                            <option value="Medium" <?= ($edit_announcement['priority'] ?? 'Medium') === 'Medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="High" <?= ($edit_announcement['priority'] ?? '') === 'High' ? 'selected' : '' ?>>High</option>
                            <option value="Urgent" <?= ($edit_announcement['priority'] ?? '') === 'Urgent' ? 'selected' : '' ?>>Urgent</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="target_audience">Target Audience</label>
                        <select id="target_audience" name="target_audience">
                            <option value="All" <?= ($edit_announcement['target_audience'] ?? 'All') === 'All' ? 'selected' : '' ?>>All Users</option>
                            <option value="Farmers" <?= ($edit_announcement['target_audience'] ?? '') === 'Farmers' ? 'selected' : '' ?>>Farmers Only</option>
                            <option value="Buyers" <?= ($edit_announcement['target_audience'] ?? '') === 'Buyers' ? 'selected' : '' ?>>Buyers Only</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="expires_at">Expires At (Optional)</label>
                        <input type="datetime-local" id="expires_at" name="expires_at" 
                               value="<?= !empty($edit_announcement['expires_at']) ? date('Y-m-d\TH:i', strtotime($edit_announcement['expires_at'])) : '' ?>">
                    </div>

                    <div class="form-group full-width">
                        <label for="content">Content *</label>
                        <textarea id="content" name="content" required><?= htmlspecialchars($edit_announcement['content'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label for="announcement_image">Image (Optional)</label>
                        <input type="file" id="announcement_image" name="announcement_image" accept="image/*">
                        <?php if (!empty($edit_announcement['image_path'])): ?>
                            <small>Current image: <?= basename($edit_announcement['image_path']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary">
                        <?= $edit_announcement ? '💾 Update Announcement' : '📤 Publish Announcement' ?>
                    </button>
                    <?php if ($edit_announcement): ?>
                        <a href="announcements.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Announcements List -->
        <div class="announcements-list">
            <h2>📋 All Announcements</h2>
            
            <?php if (empty($announcements)): ?>
                <p style="text-align: center; color: var(--text-light); padding: 40px;">
                    No announcements yet. Create your first announcement above!
                </p>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-item <?= $announcement['is_active'] ? '' : 'inactive' ?>">
                        <div class="announcement-header">
                            <div style="flex: 1;">
                                <div class="announcement-title">
                                    <?= htmlspecialchars($announcement['title']) ?>
                                    <?php if (!$announcement['is_active']): ?>
                                        <span style="font-size: 0.9rem; color: #999;">(Inactive)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="announcement-meta">
                                    <span class="badge badge-<?= strtolower($announcement['announcement_type']) ?>">
                                        <?= $announcement['announcement_type'] ?>
                                    </span>
                                    <span class="badge badge-<?= strtolower($announcement['priority']) ?>">
                                        <?= $announcement['priority'] ?> Priority
                                    </span>
                                    <span class="badge badge-<?= strtolower($announcement['target_audience']) ?>">
                                        👥 <?= $announcement['target_audience'] ?>
                                    </span>
                                    <span class="badge" style="background: rgba(100, 100, 100, 0.1);">
                                        👁️ <?= $announcement['view_count'] ?> views
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="announcement-content">
                            <?= nl2br(htmlspecialchars(substr($announcement['content'], 0, 200))) ?>
                            <?php if (strlen($announcement['content']) > 200): ?>...<?php endif; ?>
                        </div>

                        <?php if (!empty($announcement['image_path'])): ?>
                            <div style="margin-bottom: 12px;">
                                <img src="../<?= htmlspecialchars($announcement['image_path']) ?>" 
                                     style="max-width: 200px; border-radius: 8px;" alt="Announcement image">
                            </div>
                        <?php endif; ?>

                        <div style="font-size: 0.85rem; color: var(--text-light); margin-bottom: 12px;">
                            By <?= htmlspecialchars($announcement['creator_name']) ?> • 
                            <?= date('M d, Y h:i A', strtotime($announcement['created_at'])) ?>
                            <?php if ($announcement['expires_at']): ?>
                                • Expires: <?= date('M d, Y', strtotime($announcement['expires_at'])) ?>
                            <?php endif; ?>
                        </div>

                        <div class="announcement-actions">
                            <a href="?edit=<?= $announcement['announcement_id'] ?>" class="btn btn-sm btn-edit">
                                ✏️ Edit
                            </a>
                            <a href="?toggle=<?= $announcement['announcement_id'] ?>" 
                               class="btn btn-sm btn-toggle"
                               onclick="return confirm('Toggle announcement status?')">
                                <?= $announcement['is_active'] ? '⏸️ Deactivate' : '▶️ Activate' ?>
                            </a>
                            <a href="?delete=<?= $announcement['announcement_id'] ?>" 
                               class="btn btn-sm btn-delete"
                               onclick="return confirm('Are you sure you want to delete this announcement?')">
                                🗑️ Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
