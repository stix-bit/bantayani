<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Farmer') {
    header('Location: ../user/login.php');
    exit;
}

$farmer_id = $_SESSION['user_id'];

$sql = "
SELECT 
    o.order_id,
    o.order_status,
    c.crop_name,
    ci.quantity,
    ci.price
FROM order_items oi
JOIN orders o ON oi.order_id = o.order_id
JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
JOIN crops c ON ci.crop_id = c.crop_id
WHERE ci.farmer_id = ?
ORDER BY o.order_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $farmer_id);
$stmt->execute();
$orders = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>BantayAni | Farmer Orders</title>

<style>
@import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600&display=swap');

:root {
    --green-dark:#0c5c4c;
    --green:#1f8a70;
}

body {
    margin:0;
    font-family:'Quicksand',sans-serif;
    background:linear-gradient(135deg,rgba(12,92,76,.08),rgba(242,135,5,.15));
    min-height:100vh;
    padding:40px;
}

.container {
    max-width:900px;
    margin:auto;
}

.card {
    background:white;
    border-radius:24px;
    padding:32px;
    box-shadow:0 20px 50px rgba(12,92,76,.2);
}

.order {
    padding:16px;
    background:#f9fafb;
    border-radius:16px;
    margin-bottom:12px;
}

.badge {
    padding:4px 10px;
    border-radius:999px;
    background:var(--green);
    color:white;
    font-size:.8rem;
}
</style>
</head>
<body>

<div class="container">
    <div class="card">
        <h1>Orders for My Crops</h1>

        <?php if ($orders->num_rows === 0): ?>
            <p>No orders yet.</p>
        <?php endif; ?>

        <?php while ($row = $orders->fetch_assoc()): ?>
            <div class="order">
                <strong>Order #<?= $row['order_id']; ?></strong><br>
                Crop: <?= htmlspecialchars($row['crop_name']); ?><br>
                Quantity: <?= $row['quantity']; ?><br>
                Price: ₱<?= number_format($row['price'], 2); ?><br>
                Status: <span class="badge"><?= $row['order_status']; ?></span>
            </div>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>
