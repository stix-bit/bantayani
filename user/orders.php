<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Buyer') {
    header('Location: ../user/login.php');
    exit;
}

$buyer_id = $_SESSION['user_id'];

$sql = "
SELECT 
    o.order_id,
    o.order_date,
    o.order_status,
    d.delivery_status,
    p.payment_status
FROM orders o
LEFT JOIN deliveries d ON o.order_id = d.order_id
LEFT JOIN payment p ON o.order_id = p.order_id
WHERE o.buyer_id = ?
ORDER BY o.order_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $buyer_id);
$stmt->execute();
$orders = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>BantayAni | My Orders</title>

<style>
@import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600&display=swap');

:root {
    --green-dark:#0c5c4c;
    --green:#1f8a70;
    --beige:#f6f1e9;
    --text:#1f2933;
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
    background:#fff;
    border-radius:24px;
    padding:32px;
    box-shadow:0 20px 50px rgba(12,92,76,.2);
    margin-bottom:20px;
}

h1 {
    color:var(--green-dark);
    margin-bottom:24px;
}

.order {
    padding:16px;
    border-radius:16px;
    background:#f9fafb;
    margin-bottom:12px;
}

.badge {
    padding:4px 10px;
    border-radius:999px;
    font-size:.8rem;
    font-weight:600;
    background:var(--green);
    color:#fff;
}
</style>
</head>
<body>

<div class="container">
    <div class="card">
        <h1>My Orders</h1>

        <?php if ($orders->num_rows === 0): ?>
            <p>You have no orders yet.</p>
        <?php endif; ?>

        <?php while ($row = $orders->fetch_assoc()): ?>
            <div class="order">
                <strong>Order #<?= $row['order_id']; ?></strong><br>
                Date: <?= date('F d, Y', strtotime($row['order_date'])); ?><br>
                Status: <span class="badge"><?= $row['order_status']; ?></span><br>
                Delivery: <?= $row['delivery_status'] ?? 'Pending'; ?><br>
                Payment: <?= $row['payment_status'] ?? 'Pending'; ?>
            </div>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>