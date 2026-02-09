<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login();
require_once __DIR__ . '/../includes/config.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$errors = [];
$success = '';

/* ============================
   FETCH USER DATA
============================ */
$stmt = $conn->prepare("
    SELECT first_name, middle_name, last_name, email,
           contact_number, address, img_path
    FROM users
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ============================
   PROFILE IMAGE PATHS
============================ */
$profile_img = $user['img_path'] ?? '';
$default_avatar = '../images/default-avatar.png';

$public_path = !empty($profile_img)
    ? '../' . ltrim($profile_img, '/')
    : $default_avatar;

/* ============================
   FETCH ROLE-SPECIFIC DATA
============================ */
$role_data = [];

if ($role === 'Farmer') {
    $stmt = $conn->prepare("SELECT * FROM farmer_profiles WHERE farmer_id = ? LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $role_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($role === 'Buyer') {
    $stmt = $conn->prepare("SELECT * FROM buyer_profiles WHERE buyer_id = ? LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $role_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* ============================
   HANDLE FORM SUBMISSION
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);
    $address = trim($_POST['address']);

    if ($first_name === '' || $last_name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please fill out all required fields correctly.';
    }

    /* ===== Avatar Upload ===== */
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

        /* ===== Update users table ===== */
        $sql = "UPDATE users
                SET first_name=?, middle_name=?, last_name=?, email=?, contact_number=?, address=?";

        if (isset($avatar_path)) {
            $sql .= ", img_path=?";
        }

        $sql .= " WHERE user_id=?";

        $stmt = $conn->prepare($sql);

        if (isset($avatar_path)) {
            $stmt->bind_param(
                'sssssssi',
                $first_name, $middle_name, $last_name,
                $email, $contact_number, $address,
                $avatar_path, $user_id
            );
        } else {
            $stmt->bind_param(
                'ssssssi',
                $first_name, $middle_name, $last_name,
                $email, $contact_number, $address,
                $user_id
            );
        }

        $stmt->execute();
        $stmt->close();

        /* ===== Role-specific updates ===== */
        if ($role === 'Farmer') {
            $farm_name = trim($_POST['farm_name']);
            $farm_location = trim($_POST['farm_location']);

            $stmt = $conn->prepare("
                UPDATE farmer_profiles
                SET farm_name=?, farm_location=?
                WHERE farmer_id=?
            ");
            $stmt->bind_param('ssi', $farm_name, $farm_location, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        if ($role === 'Buyer') {
            $preferred = $_POST['preferred_payment_method'];

            $stmt = $conn->prepare("
                UPDATE buyer_profiles
                SET preferred_payment_method=?
                WHERE buyer_id=?
            ");
            $stmt->bind_param('si', $preferred, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        $_SESSION['first_name'] = $first_name;
        $success = 'Profile updated successfully!';
        header("Location: profile.php");
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

        label { 
            display:block; 
            font-weight:600; 
            margin-bottom:6px; 
            color:var(--text); 
            } 

        input[type="text"], input[type="email"], input[type="file"], select { 
            width:100%; 
            padding:14px 16px; 
            border-radius:14px; 
            border:1px solid #d8dee6; 
            font-size:1rem; 
            margin-bottom:20px; } 
        
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
            color:var(--error); margin-bottom:24px; 
            } 
        .success { 
            padding:14px 16px; 
            border-radius:14px; 
            border:1px solid rgba(31,138,112,0.4); 
            background: rgba(31,138,112,0.1); 
            color:var(--green); margin-bottom:24px; 
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

        .back-btn {
            position: absolute;
            top: 28px;
            left: 28px;
            text-decoration: none;
            font-weight: 600;
            color: var(--green-dark);
            background: rgba(31, 138, 112, 0.08);
            padding: 10px 16px;
            border-radius: 999px;
            transition: background 120ms ease, transform 120ms ease;
            }

        .back-btn:hover {
            background: rgba(31, 138, 112, 0.18);
            transform: translateX(-2px);
        }
        </style> 
     </head>
<body>

<div class="card">

<a href="<?= $_SESSION['role'] === 'Admin' ? '../admin/index.php' : '../index.php' ?>" class="back-btn">
    ← Back
</a>
<h1>My Profile</h1>

<?php if ($errors): ?>
<div class="alert">
    <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">

<img src="<?= htmlspecialchars($public_path) ?>" class="avatar-preview"
     onerror="this.src='../images/default-avatar.png';">

<label>Change Avatar</label>
<input type="file" name="avatar" accept="image/*">

<label>First Name</label>
<input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>

<label>Middle Name</label>
<input type="text" name="middle_name" value="<?= htmlspecialchars($user['middle_name']) ?>">

<label>Last Name</label>
<input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>

<label>Email</label>
<input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

<label>Contact Number</label>
<input type="text" name="contact_number" value="<?= htmlspecialchars($user['contact_number']) ?>">

<label>Address</label>
<input type="text" name="address" value="<?= htmlspecialchars($user['address']) ?>" required>

<?php if ($role === 'Farmer'): ?>
<label>Farm Name</label>
<input type="text" name="farm_name" value="<?= htmlspecialchars($role_data['farm_name'] ?? '') ?>" required>

<label>Farm Location</label>
<input type="text" name="farm_location" value="<?= htmlspecialchars($role_data['farm_location'] ?? '') ?>" required>
<?php endif; ?>

<?php if ($role === 'Buyer'): ?>
<label>Preferred Payment</label>
<select name="preferred_payment_method">
    <option value="Cash" <?= ($role_data['preferred_payment_method'] ?? '') === 'Cash' ? 'selected' : '' ?>>Cash</option>
    <option value="Online" <?= ($role_data['preferred_payment_method'] ?? '') === 'Online' ? 'selected' : '' ?>>Online</option>
</select>
<?php endif; ?>

<button type="submit">Update Profile</button>
</form>
</div>

</body>
</html>
