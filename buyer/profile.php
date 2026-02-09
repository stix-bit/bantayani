<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login();
include "../includes/config.php";

$id = $_SESSION['user_id'];

$sql = "SELECT u.*, b.preferred_payment_method
        FROM users u
        JOIN buyer_profiles b ON u.user_id = b.buyer_id
        WHERE u.user_id = $id";

$row = $conn->query($sql)->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Profile</title>
    <link rel="stylesheet" href="assets/css/buyer.css">
</head>
<body>

<div class="card">
    <h2>My Profile</h2>
    <p>Name: <?= $row['first_name'] ?> <?= $row['last_name'] ?></p>
    <p>Email: <?= $row['email'] ?></p>
    <p>Payment: <?= $row['preferred_payment_method'] ?></p>
</div>

</body>
</html>
