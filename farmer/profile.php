<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Farmer');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/verification_helper.php';

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
   FETCH FARMER PROFILE DATA
============================ */
$stmt = $conn->prepare("
    SELECT fp.farm_name, fp.farm_location, fp.farm_img_path, fp.region,
           fp.verified_by, fp.verified_at,
           CONCAT(a.first_name, ' ', a.last_name) as verified_by_name
    FROM farmer_profiles fp
    LEFT JOIN users a ON fp.verified_by = a.user_id
    WHERE fp.farmer_id = ?
    LIMIT 1
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$farmer_profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ============================
   FETCH VERIFICATION DOCUMENTS
============================ */
$stmt = $conn->prepare("
    SELECT verification_id, certificate_type, certificate_name, 
           certificate_path, status, submitted_at, reviewed_at, admin_notes
    FROM farmer_verification
    WHERE farmer_id = ?
    ORDER BY submitted_at DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$verification_docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ============================
   FETCH INVENTORY SUMMARY
============================ */
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_crops, SUM(quantity) as total_quantity
    FROM crops_inventory ci
    JOIN crops c ON ci.crop_id = c.crop_id
    WHERE ci.farmer_id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$inventory_summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ============================
   FETCH RATINGS
============================ */
$stmt = $conn->prepare("
    SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings
    FROM ratings
    WHERE farmer_id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$ratings = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ============================
   PROFILE IMAGE PATHS
============================ */
$profile_img = $user['img_path'] ?? '';
$default_avatar = '../images/default-avatar.png';
$farm_img = $farmer_profile['farm_img_path'] ?? '';
$default_farm = '../images/default-farm.png';

$public_profile_path = !empty($profile_img)
    ? '../' . ltrim($profile_img, '/')
    : $default_avatar;

$public_farm_path = !empty($farm_img)
    ? '../' . ltrim($farm_img, '/')
    : $default_farm;

/* ============================
   HANDLE FORM SUBMISSION (separated actions)
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ----- Update Personal Information -----
    if ($action === 'update_personal') {
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($first_name === '' || $last_name === '') {
            $errors[] = 'Please fill out all required fields correctly.';
        }

        /* Avatar Upload (personal) */
        if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['avatar']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif'];

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
            $sql = "UPDATE users
                    SET first_name=?, middle_name=?, last_name=?, contact_number=?, address=?";

            if (isset($avatar_path)) {
                $sql .= ", img_path=?";
            }

            $sql .= " WHERE user_id=?";

            $stmt = $conn->prepare($sql);

            if (isset($avatar_path)) {
                $stmt->bind_param(
                    'ssssssi',
                    $first_name, $middle_name, $last_name,
                    $contact_number, $address,
                    $avatar_path, $user_id
                );
            } else {
                $stmt->bind_param(
                    'sssssi',
                    $first_name, $middle_name, $last_name,
                    $contact_number, $address,
                    $user_id
                );
            }

            $stmt->execute();
            $stmt->close();

            $_SESSION['first_name'] = $first_name;
            $success = 'Profile updated successfully!';
            header("Location: profile.php");
            exit;
        }
    }

    // ----- Update Farm Information -----
    if ($action === 'update_farm') {
        $farm_name = trim($_POST['farm_name'] ?? '');
        $farm_location = trim($_POST['farm_location'] ?? '');
        $region = trim($_POST['region'] ?? '');

        // Validate region if provided
        $valid_regions = ['', 'Manila', 'Nueva Ecija', 'Bulacan', 'Batangas', 'Laguna',
                          'Quezon', 'Cavite', 'Rizal', 'Camarines Sur', 'Cebu', 'Davao',
                          'Mindanao', 'Luzon', 'Visayas'];

        if ($farm_name === '' || $farm_location === '') {
            $errors[] = 'Please fill out all required farm fields.';
        }

        if (!in_array($region, $valid_regions)) {
            $errors[] = 'Invalid region selected.';
        }

        /* Farm Image Upload (farm) */
        if (!empty($_FILES['farm_image']['name']) && $_FILES['farm_image']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['farm_image']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['farm_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif'];

            if (!in_array($ext, $allowed)) {
                $errors[] = 'Invalid farm image type.';
            } else {
                $uploadDir = __DIR__ . '/../images/uploads/farms';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $newName = 'farm_' . uniqid() . '.' . $ext;
                $destination = $uploadDir . '/' . $newName;

                if (move_uploaded_file($tmp, $destination)) {
                    $farm_image_path = 'images/uploads/farms/' . $newName;
                } else {
                    $errors[] = 'Failed to upload farm image.';
                }
            }
        }

        if (empty($errors)) {
            $farm_sql = "UPDATE farmer_profiles
                         SET farm_name=?, farm_location=?, region=?";

            if (isset($farm_image_path)) {
                $farm_sql .= ", farm_img_path=?";
            }

            $farm_sql .= " WHERE farmer_id=?";

            $stmt = $conn->prepare($farm_sql);

            if (isset($farm_image_path)) {
                $stmt->bind_param('ssssi', $farm_name, $farm_location, $region, $farm_image_path, $user_id);
            } else {
                $stmt->bind_param('sssi', $farm_name, $farm_location, $region, $user_id);
            }

            $stmt->execute();
            $stmt->close();

            $success = 'Farm information updated successfully!';
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
    <title>BantayAni | Farmer Profile</title>
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
        
        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
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
        
        .farm-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 15px;
            margin-bottom: 20px;
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
        
        .documents-list {
            margin-top: 20px;
        }
        
        .document-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: rgba(31, 138, 112, 0.05);
            border-radius: 10px;
            margin-bottom: 10px;
        }
        
        .document-info h4 {
            margin: 0;
            color: var(--green-dark);
        }
        
        .document-info p {
            margin: 5px 0 0;
            color: #666;
            font-size: 14px;
        }
        
        .document-status {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
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
        
        .view-mode .edit-btn {
            display: inline-block;
        }
        
        .edit-mode .view-btn {
            display: none;
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
            <h1 style="margin: 0; color: var(--green-dark);">BantayAni Farmer Portal</h1>
            <div class="nav-links">
                 <a href="../index.php" class="nav-link">Dashboard</a>
                <a href="inventory.php" class="nav-link">My Inventory</a>
                <a href="orders.php" class="nav-link">Orders</a>
                <a href="cooperative.php" class="nav-link">Cooperative</a>
                <a href="../reports.php" class="nav-link">Reports</a>
                <a href="../announcements.php" class="nav-link">Announcements</a>
                <a href="../invoices.php" class="nav-link">Invoices</a>
                <a href="notifications.php" class="nav-link active">Notifications</a>
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
                         onerror="this.src='../images/default-avatar.png';">
                    <div class="profile-info">
                        <h3><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                        <p><?= htmlspecialchars($user['email']) ?></p>
                        <p>Member since: <?= date('M d, Y', strtotime($user['created_at'])) ?></p>
                        <?= getVerificationStatusBadge($user['is_verified']) ?>
                        <?php 
                        $verification_summary = getFarmerVerificationSummary($conn, $user_id);
                        if ($verification_summary['total_certificates'] > 0): 
                        ?>
                            <p style="font-size: 14px; color: #666; margin-top: 5px;">
                                <?= getVerificationRequirementsText($conn, $user_id) ?>
                            </p>
                        <?php endif; ?>
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
                            <p><?= htmlspecialchars($user['contact_number']) ?></p>
                        </div>
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <label>Address</label>
                            <p><?= htmlspecialchars($user['address']) ?></p>
                        </div>
                    </div>
                    <button class="btn btn-secondary edit-btn" onclick="toggleEditMode()">Edit Profile</button>
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
                            <input type="text" name="middle_name" value="<?= htmlspecialchars($user['middle_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number" value="<?= htmlspecialchars($user['contact_number']) ?>">
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

            <!-- Farm Information Card -->
            <div class="card" id="farmCard">
                <h2>Farm Information</h2>
                <?php if ($farmer_profile): ?>
                    <?php if ($farmer_profile['farm_img_path']): ?>
                        <img src="<?= htmlspecialchars($public_farm_path) ?>" class="farm-image" 
                             onerror="this.src='../images/default-farm.png';">
                    <?php endif; ?>
                    
                    <div class="view-mode">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Farm Name</label>
                                <p><?= htmlspecialchars($farmer_profile['farm_name']) ?></p>
                            </div>
                            <div class="info-item">
                                <label>Farm Location</label>
                                <p><?= htmlspecialchars($farmer_profile['farm_location']) ?></p>
                            </div>
                            <div class="info-item">
                                <label>Farm Region</label>
                                <p><?= htmlspecialchars($farmer_profile['region'] ?? 'Not specified') ?></p>
                            </div>
                            <?php if ($farmer_profile['verified_by']): ?>
                                <div class="info-item">
                                    <label>Verified By</label>
                                    <p><?= htmlspecialchars($farmer_profile['verified_by_name']) ?></p>
                                </div>
                                <div class="info-item">
                                    <label>Verified On</label>
                                    <p><?= date('M d, Y', strtotime($farmer_profile['verified_at'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn-secondary edit-btn" onclick="toggleFarmEditMode()">Edit Farm Info</button>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="edit-mode" id="farmEditForm">
                        <input type="hidden" name="action" value="update_farm">
                        <div class="form-group">
                            <label>Farm Image</label>
                            <input type="file" name="farm_image" accept="image/*">
                        </div>
                        <div class="info-grid">
                            <div class="form-group">
                                <label>Farm Name</label>
                                <input type="text" name="farm_name" value="<?= htmlspecialchars($farmer_profile['farm_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Farm Location</label>
                                <input type="text" name="farm_location" value="<?= htmlspecialchars($farmer_profile['farm_location']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Farm Region (Optional)</label>
                                <select name="region">
                                    <option value="">-- Philippines Default --</option>
                                    <option value="Manila" <?= ($farmer_profile['region'] === 'Manila' ? 'selected' : '') ?>>Manila</option>
                                    <option value="Nueva Ecija" <?= ($farmer_profile['region'] === 'Nueva Ecija' ? 'selected' : '') ?>>Nueva Ecija</option>
                                    <option value="Bulacan" <?= ($farmer_profile['region'] === 'Bulacan' ? 'selected' : '') ?>>Bulacan</option>
                                    <option value="Batangas" <?= ($farmer_profile['region'] === 'Batangas' ? 'selected' : '') ?>>Batangas</option>
                                    <option value="Laguna" <?= ($farmer_profile['region'] === 'Laguna' ? 'selected' : '') ?>>Laguna</option>
                                    <option value="Quezon" <?= ($farmer_profile['region'] === 'Quezon' ? 'selected' : '') ?>>Quezon</option>
                                    <option value="Cavite" <?= ($farmer_profile['region'] === 'Cavite' ? 'selected' : '') ?>>Cavite</option>
                                    <option value="Rizal" <?= ($farmer_profile['region'] === 'Rizal' ? 'selected' : '') ?>>Rizal</option>
                                    <option value="Camarines Sur" <?= ($farmer_profile['region'] === 'Camarines Sur' ? 'selected' : '') ?>>Camarines Sur</option>
                                    <option value="Cebu" <?= ($farmer_profile['region'] === 'Cebu' ? 'selected' : '') ?>>Cebu</option>
                                    <option value="Davao" <?= ($farmer_profile['region'] === 'Davao' ? 'selected' : '') ?>>Davao</option>
                                    <option value="Mindanao" <?= ($farmer_profile['region'] === 'Mindanao' ? 'selected' : '') ?>>Mindanao</option>
                                    <option value="Luzon" <?= ($farmer_profile['region'] === 'Luzon' ? 'selected' : '') ?>>Luzon</option>
                                    <option value="Visayas" <?= ($farmer_profile['region'] === 'Visayas' ? 'selected' : '') ?>>Visayas</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn">Save Changes</button>
                            <button type="button" class="btn btn-secondary" onclick="toggleFarmEditMode()">Cancel</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 40px 0;">
                        No farm information available. Please update your profile.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics Card -->
        <div class="card">
            <h2>Farm Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $inventory_summary['total_crops'] ?? 0 ?></div>
                    <div class="stat-label">Crop Types</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($inventory_summary['total_quantity'] ?? 0, 2) ?></div>
                    <div class="stat-label">Total Quantity (kg)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $ratings['total_ratings'] ?? 0 ?></div>
                    <div class="stat-label">Total Reviews</div>
                </div>
            </div>
            <?php if ($ratings['avg_rating']): ?>
                <div style="text-align: center; margin-top: 20px;">
                    <div style="font-size: 36px; color: var(--green-dark); font-weight: 600;">
                        <?= number_format($ratings['avg_rating'], 1) ?> ⭐
                    </div>
                    <div style="color: #666;">Average Rating</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- My Crops Showcase -->
        <div class="card">
            <h2>My Crops</h2>
            <?php 
            // Fetch farmer's crops with images
            $crops_stmt = $conn->prepare("
                SELECT ci.inventory_id, ci.crop_id, ci.quantity, ci.harvest_date, ci.price, c.crop_name, ci.unit,
                       GROUP_CONCAT(ci_img.image_path ORDER BY ci_img.is_primary DESC) as images,
                       GROUP_CONCAT(ci_img.image_id ORDER BY ci_img.is_primary DESC) as image_ids
                FROM crops_inventory ci
                JOIN crops c ON ci.crop_id = c.crop_id
                LEFT JOIN crop_images ci_img ON ci.inventory_id = ci_img.inventory_id
                WHERE ci.farmer_id = ?
                GROUP BY ci.inventory_id
                ORDER BY ci.created_at DESC
            ");
            $crops_stmt->bind_param("i", $user_id);
            $crops_stmt->execute();
            $crops_result = $crops_stmt->get_result();
            $crops = $crops_result->fetch_all(MYSQLI_ASSOC);
            $crops_stmt->close();
            ?>
            
            <?php if (!empty($crops)): ?>
                <div class="crops-showcase" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-top: 16px;">
                    <?php foreach ($crops as $crop): ?>
                        <?php 
                        $images = $crop['images'] ? explode(',', $crop['images']) : [];
                        $primary_image = !empty($images) ? $images[0] : null;
                        ?>
                        <div class="crop-card" style="border: 1px solid #e4e7eb; border-radius: 12px; padding: 12px; text-align: center;">
                            <?php if ($primary_image): ?>
                                <div style="width: 100%; height: 100px; margin-bottom: 8px; overflow: hidden; border-radius: 8px;">
                                    <img src="../<?= htmlspecialchars($primary_image) ?>" 
                                         alt="<?= htmlspecialchars($crop['crop_name']) ?>" 
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                            <?php else: ?>
                                <div style="width: 100%; height: 100px; background: #f9fafb; border-radius: 8px; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; color: #666;">
                                    <i class="fa-solid fa-image" style="font-size: 1.5rem;"></i>
                                </div>
                            <?php endif; ?>
                            <h4 style="margin: 0 0 4px 0; color: var(--text); font-size: 0.95rem;"><?= htmlspecialchars($crop['crop_name']) ?></h4>
                            <p style="margin: 2px 0; font-size: 0.85rem; color: var(--text-light);">
                                <?= htmlspecialchars($crop['quantity']) ?> <?= htmlspecialchars($crop['unit']) ?>
                            </p>
                            <p style="margin: 2px 0; font-size: 0.85rem; color: var(--green); font-weight: 600;">
                                ₱<?= number_format($crop['price'], 2) ?>/<?= htmlspecialchars($crop['unit']) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="text-align: center; margin-top: 16px;">
                    <a href="inventory.php" class="btn" style="display: inline-block; padding: 8px 16px; background: var(--green); color: white; text-decoration: none; border-radius: 6px; font-size: 0.9rem;">Manage Inventory</a>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--text-light);">
                    <i class="fa-solid fa-seedling" style="font-size: 2rem; margin-bottom: 8px;"></i>
                    <p>No crops added yet</p>
                    <a href="inventory.php" class="btn" style="display: inline-block; margin-top: 12px; padding: 8px 16px; background: var(--green); color: white; text-decoration: none; border-radius: 6px; font-size: 0.9rem;">Add Your First Crop</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Verification Documents Card -->
        <div class="card">
            <h2>Verification Documents</h2>
            <?php if (!empty($verification_docs)): ?>
                <div class="documents-list">
                    <?php foreach ($verification_docs as $doc): ?>
                        <div class="document-item">
                            <div class="document-info">
                                <h4><?= htmlspecialchars($doc['certificate_name']) ?></h4>
                                <p><?= htmlspecialchars($doc['certificate_type']) ?> • Submitted: <?= date('M d, Y', strtotime($doc['submitted_at'])) ?></p>
                                <?php if ($doc['admin_notes']): ?>
                                    <p style="color: var(--text); font-style: italic;">Note: <?= htmlspecialchars($doc['admin_notes']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?= getCertificateStatusBadge($doc['status']) ?>
                                <div style="margin-top: 5px;">
                                    <a href="<?= htmlspecialchars('/bantayani/' . ltrim($doc['certificate_path'], '/')) ?>" target="_blank" class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;">View</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #666; text-align: center; padding: 40px 0;">
                    No verification documents submitted yet.
                </p>
            <?php endif; ?>
            <div style="text-align: center; margin-top: 20px;">
                <a href="upload_certificate.php" class="btn">Upload Certificate</a>
            </div>
        </div>
    </div>

    <script>
        function toggleEditMode() {
            const card = document.getElementById('personalCard');
            if (card) card.classList.toggle('editing');
        }

        function toggleFarmEditMode() {
            const farmCard = document.getElementById('farmCard');
            if (farmCard) farmCard.classList.toggle('editing');
        }
    </script>
</body>
</html>
