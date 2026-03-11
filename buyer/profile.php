<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Buyer');
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
   FETCH BUYER PROFILE DATA
============================ */
$stmt = $conn->prepare("
    SELECT bp.preferred_payment_method, bp.verified
    FROM buyer_profiles bp
    WHERE bp.buyer_id = ?
    LIMIT 1
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$buyer_profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ============================
   FETCH ORDER STATISTICS
============================ */
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN order_status = 'Pending' THEN 1 ELSE 0 END) AS pending_orders,
        SUM(CASE WHEN order_status IN ('Delivered', 'Shipped') THEN 1 ELSE 0 END) AS completed_orders
    FROM orders
    WHERE buyer_id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$order_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

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
            $success = 'Profile updated successfully!';
            header("Location: profile.php");
            exit;
        }
    }

    if ($action === 'update_buyer') {
        $preferred_payment_method = $_POST['preferred_payment_method'] ?? '';
        $valid_methods = ['Cash', 'Online'];
        if (!in_array($preferred_payment_method, $valid_methods)) {
            $errors[] = 'Please select a valid payment method.';
        } else {
            $stmt = $conn->prepare("UPDATE buyer_profiles SET preferred_payment_method = ? WHERE buyer_id = ?");
            $stmt->bind_param('si', $preferred_payment_method, $user_id);
            $stmt->execute();
            $stmt->close();
            $buyer_profile['preferred_payment_method'] = $preferred_payment_method;
            $success = 'Payment preference updated successfully!';
            header("Location: profile.php");
            exit;
        }
    }
}

$is_buyer_verified = ($buyer_profile['verified'] ?? 0) || ($user['is_verified'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BantayAni | Buyer Profile</title>
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

        .status-not-verified {
            background: rgba(185, 28, 28, 0.1);
            color: var(--error);
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
            <h1 style="margin: 0; color: var(--green-dark);">BantayAni Buyer Portal</h1>
            <div class="nav-links">
            <a href="../index.php" class="nav-link">Dashboard</a>
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
                        <span class="verification-status <?= $is_buyer_verified ? 'status-verified' : 'status-not-verified' ?>">
                            <?= $is_buyer_verified ? 'Verified Buyer' : 'Not Verified' ?>
                        </span>
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
                            <p><?= htmlspecialchars($user['middle_name']) ?></p>
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

            <!-- Buyer / Account Information Card -->
            <div class="card" id="buyerCard">
                <h2>Account Information</h2>
                <?php if ($buyer_profile): ?>
                    <div class="view-mode">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Preferred Payment Method</label>
                                <p><?= htmlspecialchars($buyer_profile['preferred_payment_method'] ?? '—') ?></p>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary edit-btn" onclick="toggleBuyerEditMode()">Edit Payment Preference</button>
                    </div>

                    <form method="POST" class="edit-mode" id="buyerEditForm">
                        <input type="hidden" name="action" value="update_buyer">
                        <div class="form-group">
                            <label>Preferred Payment Method</label>
                            <select name="preferred_payment_method">
                                <option value="Cash" <?= ($buyer_profile['preferred_payment_method'] ?? '') === 'Cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="Online" <?= ($buyer_profile['preferred_payment_method'] ?? '') === 'Online' ? 'selected' : '' ?>>Online</option>
                            </select>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn">Save Changes</button>
                            <button type="button" class="btn btn-secondary" onclick="toggleBuyerEditMode()">Cancel</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p style="color: #666;">No buyer profile data.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order Statistics Card -->
        <div class="card">
            <h2>Order Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= (int)($order_stats['total_orders'] ?? 0) ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= (int)($order_stats['pending_orders'] ?? 0) ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= (int)($order_stats['completed_orders'] ?? 0) ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            <div style="margin-top: 16px;">
                <a href="orders.php" class="btn">View My Orders</a>
            </div>
        </div>
    </div>

    <script>
        function toggleEditMode() {
            const card = document.getElementById('personalCard');
            if (card) card.classList.toggle('editing');
        }

        function toggleBuyerEditMode() {
            const card = document.getElementById('buyerCard');
            if (card) card.classList.toggle('editing');
        }
    </script>
</body>
</html>
