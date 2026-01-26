<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* Fetch user */
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("User not found.");
}

$role = $user['role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Profile</title>

<style>
:root {
    --green-dark: #0c5c4c;
    --green: #1f8a70;
    --beige: #f6f1e9;
    --text: #1f2933;
}

* { box-sizing: border-box; }

body {
    margin: 0;
    min-height: 100vh;
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, rgba(12,92,76,.08), rgba(242,135,5,.15));
    display: flex;
    justify-content: center;
    align-items: center;
}

.card {
    width: min(420px, 100%);
    background: white;
    border-radius: 28px;
    padding: 40px;
    box-shadow: 0 25px 60px rgba(12,92,76,.2);
}

h1 {
    text-align: center;
    color: var(--green-dark);
    margin-bottom: 16px;
}

.avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 4px solid var(--green);
    object-fit: cover;
    display: block;
    margin: 0 auto 20px;
}

.label {
    font-weight: 600;
    color: var(--text);
    margin-top: 14px;
}

.value {
    background: #f1f5f9;
    padding: 12px 14px;
    border-radius: 14px;
}

.upload-btn {
    margin: 16px auto;
    display: block;
    background: var(--green);
    color: white;
    padding: 10px 18px;
    border-radius: 999px;
    cursor: pointer;
    border: none;
    font-weight: 600;
}

.upload-btn:hover {
    background: var(--green-dark);
}

input[type="file"] {
    display: none;
}
</style>
</head>

<body>
<div class="card">

    <h1><?= htmlspecialchars($role) ?> Profile</h1>

    <img
        src="<?= $user['img_path'] ?: '../images/default-avatar.png' ?>"
        class="avatar"
        alt="Profile Picture"
    >

    <!-- Upload photo -->
    <form method="POST" enctype="multipart/form-data">
        <label class="upload-btn">
            Change Picture
            <input type="file" name="profile_image" onchange="this.form.submit()">
        </label>
    </form>

    <div class="label">Full Name</div>
    <div class="value">
        <?= htmlspecialchars($user['first_name'].' '.$user['middle_name'].' '.$user['last_name']) ?>
    </div>

    <div class="label">Email</div>
    <div class="value"><?= htmlspecialchars($user['email']) ?></div>

    <div class="label">Contact</div>
    <div class="value"><?= htmlspecialchars($user['contact_number'] ?? '-') ?></div>

    <div class="label">Address</div>
    <div class="value"><?= htmlspecialchars($user['address']) ?></div>

</div>
</body>
</html>
