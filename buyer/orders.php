<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Buyer') {
    header('Location: ../user/login.php');
    exit;
}

$buyer_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$profile_img = null;
$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BantayAni | My Orders</title>

<style>
@import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600&display=swap');

:root {
    --green-dark:#0c5c4c;
    --green:#1f8a70;
    --beige:#f6f1e9;
    --text:#1f2933;
}

* {
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body {
    font-family:'Quicksand',sans-serif;
    background:linear-gradient(135deg,rgba(12,92,76,.08),rgba(242,135,5,.15));
    min-height:100vh;
}

.navbar {
    background:#fff;
    padding:16px 32px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 2px 8px rgba(0,0,0,.1);
}

.logo-container {
    display:flex;
    align-items:center;
    gap:8px;
}

.logo {
    width:40px;
    height:40px;
    background:var(--green);
    color:#fff;
    border-radius:8px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:bold;
}

.logo-text {
    font-weight:600;
    color:var(--text);
}

.logo-text span {
    color:var(--green);
}

.nav-links {
    display:flex;
    gap:24px;
}

.nav-link {
    text-decoration:none;
    color:var(--text);
    font-weight:500;
    transition:color .2s;
}

.nav-link.active {
    color:var(--green);
    border-bottom:2px solid var(--green);
}

.user-menu {
    display:flex;
    gap:16px;
    align-items:center;
}

.user-avatar {
    width:40px;
    height:40px;
    background:var(--green);
    color:#fff;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:bold;
    cursor:pointer;
}

.logout-btn {
    background:var(--green);
    color:#fff;
    border:none;
    padding:8px 16px;
    border-radius:8px;
    cursor:pointer;
    font-weight:600;
    transition:background .2s;
}

.logout-btn:hover {
    background:var(--green-dark);
}

.container {
    max-width:900px;
    margin:40px auto;
    padding:0 20px;
}

.card {
    background:#fff;
    border-radius:24px;
    padding:32px;
    box-shadow:0 20px 50px rgba(12,92,76,.2);
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
    border-left:4px solid var(--green);
}

.order-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:8px;
}

.order-id {
    font-weight:600;
    color:var(--text);
}

.badge {
    padding:4px 10px;
    border-radius:999px;
    font-size:.8rem;
    font-weight:600;
    background:var(--green);
    color:#fff;
}

.badge.pending {
    background:#f39c12;
}

.order-details {
    font-size:.9rem;
    color:#666;
    margin-bottom:12px;
}

.order-actions {
    display:flex;
    gap:8px;
}

.btn {
    padding:6px 12px;
    border-radius:6px;
    background:var(--green);
    color:#fff;
    text-decoration:none;
    border:none;
    cursor:pointer;
    font-weight:600;
    font-size:.85rem;
    transition:background .2s;
}

.btn:hover {
    background:var(--green-dark);
}

.btn-cancel {
    background:#e74c3c;
}

.btn-cancel:hover {
    background:#c0392b;
}

.empty-state {
    text-align:center;
    padding:40px;
    color:#999;
}
</style>
</head>
<body>

<nav class="navbar">
    <div class="logo-container">
        <div class="logo">BA</div>
        <div class="logo-text">BANTAY<span>ANI</span></div>
    </div>
    
    <div class="nav-links">
        <a href="../index.php" class="nav-link">Dashboard</a>
        <a href="marketplace.php" class="nav-link">Marketplace</a>
        <a href="cart.php" class="nav-link">Cart</a>
        <a href="orders.php" class="nav-link active">My Orders</a>
        <a href="profile.php" class="nav-link">Profile</a>
        <a href="ratings.php" class="nav-link">Ratings</a>
    </div>
    
    <div class="user-menu">
        <?php
            $profile_img = trim($profile_img ?? '');
            $absolute_path = $_SERVER['DOCUMENT_ROOT'] . '/bantayani/' . $profile_img;
            $public_path   = '/bantayani/' . $profile_img;
        ?>
        <a href="profile.php" title="View Profile">
            <div class="user-avatar">
                <?php if (!empty($profile_img) && file_exists($absolute_path)): ?>
                    <img src="<?= htmlspecialchars($public_path) ?>"
                         alt="Profile"
                         style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                <?php else: ?>
                    <?= strtoupper(substr($first_name, 0, 1)) ?>
                <?php endif; ?>
            </div>
        </a>
        <form method="GET" style="display:inline;">
            <button type="submit" name="logout" value="1" class="logout-btn">
                Log Out
            </button>
        </form>
    </div>
</nav>

<div class="container">
    <div class="card">
        <h1>My Orders</h1>

        <?php if (!empty($_SESSION['message'])): ?>
            <div style="background:#d4edda; color:#155724; padding:12px; border-radius:8px; margin-bottom:20px;">
                <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <?php if ($orders->num_rows === 0): ?>
            <div class="empty-state">
                <p>You have no orders yet. Start shopping at the <a href="marketplace.php" style="color:var(--green);">Marketplace</a>!</p>
            </div>
        <?php endif; ?>

        <?php while ($row = $orders->fetch_assoc()): ?>
            <div class="order">
                <div class="order-header">
                    <span class="order-id">Order #<?= $row['order_id']; ?></span>
                    <span class="badge <?= $row['order_status'] === 'Pending' ? 'pending' : ''; ?>">
                        <?= $row['order_status']; ?>
                    </span>
                </div>
                <div class="order-details">
                    <strong>Date:</strong> <?= date('F d, Y', strtotime($row['order_date'])); ?><br>
                    <strong>Delivery:</strong> <?= $row['delivery_status'] ?? 'Pending'; ?><br>
                    <strong>Payment:</strong> <?= $row['payment_status'] ?? 'Pending'; ?>
                </div>
                <div class="order-actions">
                    <a class="btn" href="orders_view.php?id=<?= $row['order_id'] ?>">View</a>
                    <?php if ($row['order_status'] === 'Pending'): ?>
                        <form method="POST" action="cancel_order.php" style="display:inline;">
                            <input type="hidden" name="order_id" value="<?= $row['order_id'] ?>">
                            <button type="submit" class="btn btn-cancel">Cancel</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>
