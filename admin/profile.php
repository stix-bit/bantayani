<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Admin');
require_once __DIR__ . '/../includes/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

/* ============================
   FETCH USER DATA
============================ */
$stmt = $conn->prepare("
    SELECT u.first_name, u.middle_name, u.last_name, u.email,
           u.contact_number, u.address, u.img_path, u.is_verified, u.created_at
    FROM users u
    WHERE u.user_id = ?
    LIMIT 1
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ============================
   FETCH PLATFORM STATISTICS
============================ */
$platform_stats = [
    'total_users' => 0,
    'total_farmers' => 0,
    'total_buyers' => 0,
    'pending_orders' => 0,
    'pending_verifications' => 0,
];
try {
    $stmt = $conn->query("
        SELECT
            (SELECT COUNT(*) FROM users) AS total_users,
            (SELECT COUNT(*) FROM users WHERE role = 'Farmer') AS total_farmers,
            (SELECT COUNT(*) FROM users WHERE role = 'Buyer') AS total_buyers,
            (SELECT COUNT(*) FROM orders WHERE order_status = 'Pending') AS pending_orders
    ");
    if ($stmt && $row = $stmt->fetch_assoc()) {
        $platform_stats['total_users'] = (int)($row['total_users'] ?? 0);
        $platform_stats['total_farmers'] = (int)($row['total_farmers'] ?? 0);
        $platform_stats['total_buyers'] = (int)($row['total_buyers'] ?? 0);
        $platform_stats['pending_orders'] = (int)($row['pending_orders'] ?? 0);
    }
    $stmt->close();

    $stmt = $conn->query("SELECT COUNT(*) AS n FROM farmer_verification WHERE status = 'Pending'");
    if ($stmt && $row = $stmt->fetch_assoc()) {
        $platform_stats['pending_verifications'] = (int)($row['n'] ?? 0);
    }
    if ($stmt) $stmt->close();
} catch (Exception $e) {
    error_log("Admin profile stats: " . $e->getMessage());
}

/* ============================
   PROFILE IMAGE PATHS
============================ */
$profile_img = $user['img_path'] ?? '';
$default_avatar = '../images/default-avatar.png';
$public_profile_path = !empty($profile_img)
    ? '../' . ltrim($profile_img, '/')
    : $default_avatar;

/* ============================
   HANDLE FORM SUBMISSION
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_personal') {
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($first_name === '' || $last_name === '') {
            $errors[] = 'Please fill out all required fields correctly.';
        }

        if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['avatar']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($ext, $allowed)) {
                $errors[] = 'Invalid image type.';
            } else {
                $uploadDir = __DIR__ . '/../images/uploads/profiles';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $newName = 'profile_' . uniqid() . '.' . $ext;
                $destination = $uploadDir . '/' . $newName;
                if (move_uploaded_file($tmp, $destination)) {
                    $avatar_path = 'images/uploads/profiles/' . $newName;
                } else {
                    $errors[] = 'Failed to upload profile image.';
                }
            }
        }

        if (empty($errors)) {
            $sql = "UPDATE users SET first_name=?, middle_name=?, last_name=?, contact_number=?, address=?";
            if (isset($avatar_path)) {
                $sql .= ", img_path=?";
            }
            $sql .= " WHERE user_id=?";
            $stmt = $conn->prepare($sql);
            if (isset($avatar_path)) {
                $stmt->bind_param('ssssssi', $first_name, $middle_name, $last_name, $contact_number, $address, $avatar_path, $user_id);
            } else {
                $stmt->bind_param('sssssi', $first_name, $middle_name, $last_name, $contact_number, $address, $user_id);
            }
            $stmt->execute();
            $stmt->close();
            $_SESSION['first_name'] = $first_name;
            $user['first_name'] = $first_name;
            $user['middle_name'] = $middle_name;
            $user['last_name'] = $last_name;
            $user['contact_number'] = $contact_number;
            $user['address'] = $address;
            if (isset($avatar_path)) {
                $user['img_path'] = $avatar_path;
            }
            $success = 'Profile updated successfully!';
            header("Location: profile.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BantayAni | Admin Profile</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600&display=swap');

        :root {
            --green-dark: #0c5c4c;
            --green: #1f8a70;
            --beige: #f6f1e9;
            --text: #1f2933;
            --error: #b91c1c;
            --warning: #f59e0b;
            --success: #10b981;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Quicksand', 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, rgba(12, 92, 76, 0.08), rgba(242, 135, 5, 0.15));
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(12, 92, 76, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--green-dark);
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 25px;
            transition: background 0.3s ease;
        }

        .nav-links a:hover {
            background: rgba(31, 138, 112, 0.1);
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(12, 92, 76, 0.1);
        }

        .card h2 {
            color: var(--green-dark);
            margin-top: 0;
            margin-bottom: 20px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--green);
        }

        .profile-info h3 {
            margin: 0;
            color: var(--green-dark);
        }

        .profile-info p {
            margin: 5px 0;
            color: #666;
        }

        .verification-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-top: 10px;
        }

        .status-verified {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-item label {
            display: block;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 5px;
        }

        .info-item p {
            margin: 0;
            color: #666;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            text-align: center;
            padding: 20px;
            background: rgba(31, 138, 112, 0.05);
            border-radius: 15px;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 600;
            color: var(--green-dark);
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: var(--green);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .btn:hover {
            background: var(--green-dark);
        }

        .btn-secondary {
            background: #e5e7eb;
            color: var(--text);
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 16px;
        }

        .form-group input[type="file"] {
            padding: 10px;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: rgba(185, 28, 28, 0.1);
            color: var(--error);
            border: 1px solid rgba(185, 28, 28, 0.3);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .card .edit-mode {
            display: none;
        }

        .card.editing .view-mode {
            display: none;
        }

        .card.editing .edit-mode {
            display: block;
        }

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .header {
                flex-direction: column;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0; color: var(--green-dark);">BantayAni Admin Portal</h1>
            <div class="nav-links">
                <a href="index.php">Dashboard</a>
                <a href="users.php">Users</a>
                <a href="../reports.php" class="nav-link"><span class="nav-icon">📈</span><span>Reports</span></a>
                <a href="benchmarking.php">Pricing</a>
                <a href="profile.php" style="background: rgba(31, 138, 112, 0.1);">Profile</a>
                <a href="/bantayani/user/logout.php">Logout</a>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="profile-grid">
            <!-- Personal Information Card -->
            <div class="card" id="personalCard">
                <h2>Personal Information</h2>
                <div class="profile-header">
                    <img src="<?= htmlspecialchars($public_profile_path) ?>" class="profile-avatar"
                         alt="Profile" onerror="this.src='../images/default-avatar.png';">
                    <div class="profile-info">
                        <h3><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                        <p><?= htmlspecialchars($user['email']) ?></p>
                        <p>Member since: <?= date('M d, Y', strtotime($user['created_at'])) ?></p>
                        <span class="verification-status status-verified">Admin</span>
                    </div>
                </div>

                <div class="view-mode">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>First Name</label>
                            <p><?= htmlspecialchars($user['first_name']) ?></p>
                        </div>
                        <div class="info-item">
                            <label>Last Name</label>
                            <p><?= htmlspecialchars($user['last_name']) ?></p>
                        </div>
                        <div class="info-item">
                            <label>Middle Name</label>
                            <p><?= htmlspecialchars($user['middle_name'] ?? '—') ?></p>
                        </div>
                        <div class="info-item">
                            <label>Contact Number</label>
                            <p><?= htmlspecialchars($user['contact_number'] ?? '—') ?></p>
                        </div>
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <label>Address</label>
                            <p><?= htmlspecialchars($user['address']) ?></p>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary edit-btn" onclick="toggleEditMode()">Edit Profile</button>
                </div>

                <form method="POST" enctype="multipart/form-data" class="edit-mode">
                    <input type="hidden" name="action" value="update_personal">
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <input type="file" name="avatar" accept="image/*">
                    </div>
                    <div class="info-grid">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" name="middle_name" value="<?= htmlspecialchars($user['middle_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number" value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Address</label>
                            <input type="text" name="address" value="<?= htmlspecialchars($user['address']) ?>" required>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn">Save Changes</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleEditMode()">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Admin Role Card -->
            <div class="card">
                <h2>Account Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Role</label>
                        <p>Admin</p>
                    </div>
                </div>
                <p style="color: #666; font-size: 0.9rem; margin-top: 12px;">You have full access to manage users, reports, pricing, and platform settings.</p>
            </div>
        </div>

        <!-- Platform Statistics Card -->
        <div class="card">
            <h2>Platform Overview</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $platform_stats['total_users'] ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $platform_stats['total_farmers'] ?></div>
                    <div class="stat-label">Farmers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $platform_stats['total_buyers'] ?></div>
                    <div class="stat-label">Buyers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $platform_stats['pending_orders'] ?></div>
                    <div class="stat-label">Pending Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $platform_stats['pending_verifications'] ?></div>
                    <div class="stat-label">Pending Verifications</div>
                </div>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 16px; flex-wrap: wrap;">
                <a href="index.php" class="btn">Dashboard</a>
                <a href="users.php" class="btn btn-secondary">Manage Users</a>
                <a href="verify_farmers.php" class="btn btn-secondary">Verify Farmers</a>
            </div>
        </div>
    </div>

    <script>
        function toggleEditMode() {
            const card = document.getElementById('personalCard');
            if (card) card.classList.toggle('editing');
        }
    </script>
</body>
</html>
