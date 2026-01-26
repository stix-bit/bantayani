<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$errors = [];
$success = '';

// Fetch current user data
$stmt = $conn->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Fetch role-specific data
$role_data = [];
if ($role === 'Farmer') {
    $stmt = $conn->prepare('SELECT * FROM farmer_profiles WHERE farmer_id = ? LIMIT 1');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $role_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif ($role === 'Buyer') {
    $stmt = $conn->prepare('SELECT * FROM buyer_profiles WHERE buyer_id = ? LIMIT 1');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $role_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($first_name === '' || $last_name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please fill out all required fields with a valid email.';
    }

    // Handle avatar upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['avatar']['tmp_name'];
        $fileName = basename($_FILES['avatar']['name']);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif'];

        if (!in_array($ext, $allowed)) {
            $errors[] = 'Invalid avatar format. Allowed: jpg, jpeg, png, gif.';
        } else {
            $uploadDir = __DIR__ . '/../uploads/avatars/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $newFileName = $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($fileTmp, $uploadDir . $newFileName)) {
                $avatar_path = 'uploads/avatars/' . $newFileName;
            } else {
                $errors[] = 'Failed to upload avatar.';
            }
        }
    }

    if (empty($errors)) {
        // Update users table
        $stmt = $conn->prepare('UPDATE users SET first_name=?, middle_name=?, last_name=?, email=?, contact_number=?, address=?' . (isset($avatar_path) ? ', img_path=?' : '') . ' WHERE user_id=?');
        if (isset($avatar_path)) {
            $stmt->bind_param('sssssssi', $first_name, $middle_name, $last_name, $email, $contact_number, $address, $avatar_path, $user_id);
        } else {
            $stmt->bind_param('ssssssi', $first_name, $middle_name, $last_name, $email, $contact_number, $address, $user_id);
        }
        $stmt->execute();
        $stmt->close();

        // Update role-specific data
        if ($role === 'Farmer') {
            $farm_name = trim($_POST['farm_name'] ?? '');
            $farm_location = trim($_POST['farm_location'] ?? '');
            $stmt = $conn->prepare('UPDATE farmer_profiles SET farm_name=?, farm_location=? WHERE farmer_id=?');
            $stmt->bind_param('ssi', $farm_name, $farm_location, $user_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($role === 'Buyer') {
            $preferred_payment = $_POST['preferred_payment_method'] ?? 'Cash';
            $stmt = $conn->prepare('UPDATE buyer_profiles SET preferred_payment_method=? WHERE buyer_id=?');
            $stmt->bind_param('si', $preferred_payment, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        $success = 'Profile updated successfully!';
        // Refresh session first name if changed
        $_SESSION['first_name'] = $first_name;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>BantayAni | Profile</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600&display=swap');

:root {
    --green-dark: #0c5c4c;
    --green: #1f8a70;
    --beige: #f6f1e9;
    --text: #1f2933;
    --error: #b91c1c;
}

* { box-sizing: border-box; }
body {
    margin:0;
    min-height:100vh;
    font-family:'Quicksand', 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, rgba(12, 92, 76, 0.08), rgba(242, 135, 5, 0.15));
    display:flex;
    align-items:center;
    justify-content:center;
    padding:24px;
}
.card {
    width:min(600px,100%);
    background:white;
    border-radius:28px;
    padding:48px 40px;
    box-shadow:0 25px 60px rgba(12, 92, 76, 0.2);
}
h1 {
    margin:0 0 8px;
    font-size:1.8rem;
    color:var(--green-dark);
    text-align:center;
}
p.subtitle {
    text-align:center;
    margin:0 0 32px;
    color:#4c5662;
}
label { display:block; font-weight:600; margin-bottom:6px; color:var(--text); }
input[type="text"], input[type="email"], input[type="file"], select {
    width:100%;
    padding:14px 16px;
    border-radius:14px;
    border:1px solid #d8dee6;
    font-size:1rem;
    margin-bottom:20px;
}
button[type="submit"] {
    width:100%;
    padding:14px 16px;
    border-radius:999px;
    border:none;
    background:var(--green);
    color:white;
    font-size:1rem;
    font-weight:600;
    cursor:pointer;
    transition:transform 120ms ease, background 120ms ease;
}
button[type="submit"]:hover {
    background:var(--green-dark);
    transform:translateY(-1px);
}
.alert {
    padding:14px 16px;
    border-radius:14px;
    border:1px solid rgba(185, 28, 28, 0.4);
    background: rgba(185,28,28,0.1);
    color:var(--error);
    margin-bottom:24px;
}
.success {
    padding:14px 16px;
    border-radius:14px;
    border:1px solid rgba(31,138,112,0.4);
    background: rgba(31,138,112,0.1);
    color:var(--green);
    margin-bottom:24px;
}
.avatar-preview {
    display:block;
    margin: 0 auto 20px;
    width:120px;
    height:120px;
    border-radius:50%;
    object-fit:cover;
    border:2px solid var(--green);
}
</style>
</head>
<body>
<div class="card">
<h1>My Profile</h1>
<p class="subtitle">Edit your personal information below.</p>

<?php if (!empty($errors)) : ?>
    <div class="alert">
        <ul>
            <?php foreach($errors as $error): ?>
                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" autocomplete="off">
    <img class="avatar-preview" src="<?= htmlspecialchars($user['img_path'] ?? 'https://via.placeholder.com/120', ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar">
    <label for="avatar">Change Avatar</label>
    <input type="file" name="avatar" id="avatar" accept="image/*">

    <label for="first_name">First Name</label>
    <input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? $user['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required>

    <label for="middle_name">Middle Name</label>
    <input type="text" name="middle_name" value="<?= htmlspecialchars($_POST['middle_name'] ?? $user['middle_name'], ENT_QUOTES, 'UTF-8'); ?>">

    <label for="last_name">Last Name</label>
    <input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? $user['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required>

    <label for="email">Email</label>
    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? $user['email'], ENT_QUOTES, 'UTF-8'); ?>" required>

    <label for="contact_number">Contact Number</label>
    <input type="text" name="contact_number" value="<?= htmlspecialchars($_POST['contact_number'] ?? $user['contact_number'], ENT_QUOTES, 'UTF-8'); ?>">

    <label for="address">Address</label>
    <input type="text" name="address" value="<?= htmlspecialchars($_POST['address'] ?? $user['address'], ENT_QUOTES, 'UTF-8'); ?>" required>

    <?php if($role === 'Farmer'): ?>
        <label for="farm_name">Farm Name</label>
        <input type="text" name="farm_name" value="<?= htmlspecialchars($_POST['farm_name'] ?? $role_data['farm_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
        <label for="farm_location">Farm Location</label>
        <input type="text" name="farm_location" value="<?= htmlspecialchars($_POST['farm_location'] ?? $role_data['farm_location'], ENT_QUOTES, 'UTF-8'); ?>" required>
    <?php elseif($role === 'Buyer'): ?>
        <label for="preferred_payment_method">Preferred Payment Method</label>
        <select name="preferred_payment_method">
            <option value="Cash" <?= ($role_data['preferred_payment_method'] ?? '') === 'Cash' ? 'selected' : ''; ?>>Cash</option>
            <option value="Online" <?= ($role_data['preferred_payment_method'] ?? '') === 'Online' ? 'selected' : ''; ?>>Online</option>
        </select>
    <?php endif; ?>

    <button type="submit">Update Profile</button>
</form>
</div>
</body>
</html>
