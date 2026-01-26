<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];


if (isset($_POST['upload_image'])) {
    $target_dir = "uploads/profile_images/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

    $file_name = basename($_FILES["profile_image"]["name"]);
    $target_file = $target_dir . time() . "_" . $file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    $check = getimagesize($_FILES["profile_image"]["tmp_name"]);
    if ($check !== false) {
        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
            // Update database
            $sql = "UPDATE users SET img_path = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $target_file, $user_id);
            $stmt->execute();
            $success_msg = "Profile picture updated!";
        } else {
            $error_msg = "Sorry, there was an error uploading your file.";
        }
    } else {
        $error_msg = "File is not a valid image.";
    }
}


$sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch role-specific profile info
$role = $user['role'];
$profile = [];

if ($role === 'Farmer') {
    $sql = "SELECT * FROM farmer_profiles WHERE farmer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
} elseif ($role === 'Buyer') {
    $sql = "SELECT * FROM buyer_profiles WHERE buyer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>
        Profile - <?= htmlspecialchars($user['first_name']) ?>
</title>
<style>
    body { 
        font-family: Arial, 
        sans-serif; 
        background-color: #f5f6fa; 
        color: #2f3640; 
        margin: 0; 
        padding: 0; 
    }
    .container { 
        width: 90%; 
        max-width: 800px; 
        margin: 50px auto; 
        background-color: #fff; 
        border-radius: 10px; 
        box-shadow: 0 5px 20px rgba(0,0,0,0.1); 
        padding: 30px; 
    }
    h1 { 
        text-align: center; 
        color: #4b7bec; 
    }
    .profile-section { 
        display: flex; 
        flex-direction: column; 
        gap: 15px; 
        margin-top: 20px; 
    }
    .profile-section label { 
        font-weight: bold; 
    }
    .profile-section span { 
        display: block; padding: 8px; 
        background-color: #f1f2f6; 
        border-radius: 5px; 
    }
    .profile-img { 
        display: block; 
        margin: 0 auto 20px; 
        width: 120px; 
        height: 120px; 
        object-fit: cover; 
        border-radius: 50%; 
        border: 3px solid #4b7bec; 
    }
    .button { 
        display: block; 
        width: 200px; 
        margin: 20px auto 0; 
        padding: 10px; 
        text-align: center; 
        background-color: #4b7bec; 
        color: white; 
        text-decoration: none; 
        border-radius: 5px; 
    }
    .button:hover { 
        background-color: #3867d6; 
    }
    input[type="file"] { 
        display: none; 
    }
    .custom-file-upload { 
        border: 1px solid #ccc; 
        display: inline-block; 
        padding: 6px 12px; 
        cursor: pointer; 
        border-radius: 5px; 
        background-color: #4b7bec; 
        color: white; 
        margin: 10px auto; 
        text-align: center; 
    }
    .custom-file-upload:hover { 
        background-color: #3867d6; 
    }
    .message { 
        text-align: center; 
        margin-top: 10px; 
        color: green; 
    }
    .error { 
        text-align: center; 
        margin-top: 10px; 
        color: red; 
        }

</style>
    </head>
        <body>
            <div class="container">
    <h1><?= htmlspecialchars($role) ?> Profile</h1>
    <img src="<?= !empty($user['img_path']) ? htmlspecialchars($user['img_path']) : 'default-avatar.png' ?>" class="profile-img" alt="Profile Image">

    <!-- Upload Form -->
    <form method="POST" enctype="multipart/form-data" style="text-align:center;">
        <label class="custom-file-upload">
            <input type="file" name="profile_image" onchange="this.form.submit()"/>
            Change Profile Picture
        </label>
    </form>

    <?php if(isset($success_msg)) echo '<div class="message">'.$success_msg.'</div>'; ?>
    <?php if(isset($error_msg)) echo '<div class="error">'.$error_msg.'</div>'; ?>

    <div class="profile-section">
        <label>Full Name:</label>
        <span><?= htmlspecialchars($user['first_name'].' '.$user['middle_name'].' '.$user['last_name']) ?></span>

        <label>Email:</label>
        <span><?= htmlspecialchars($user['email']) ?></span>

        <label>Contact Number:</label>
        <span><?= htmlspecialchars($user['contact_number'] ?? '-') ?></span>

        <label>Address:</label>
        <span><?= htmlspecialchars($user['address']) ?></span>

        <?php if ($role === 'Farmer'): ?>
            <label>Farm Name:</label>
            <span><?= htmlspecialchars($profile['farm_name'] ?? '-') ?></span>

            <label>Farm Location:</label>
            <span><?= htmlspecialchars($profile['farm_location'] ?? '-') ?></span>
        <?php elseif ($role === 'Buyer'): ?>
            <label>Preferred Payment Method:</label>
            <span><?= htmlspecialchars($profile['preferred_payment_method'] ?? '-') ?></span>
        <?php endif; ?>
    </div>

    <a href="edit_profile.php" class="button">Edit Profile</a>
    <a href="logout.php" class="button" style="background-color:#e84118;">Logout</a>
</div>
</body>
</html>
