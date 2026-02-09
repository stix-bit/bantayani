<?php
session_start();
include "../includes/config.php";

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$profile_img = null;
$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

$buyer_id = $_SESSION['user_id'];
$result = $conn->query("SELECT * FROM orders WHERE buyer_id = $buyer_id ORDER BY order_id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Orders</title>
    <link rel="stylesheet" href="assets/css/buyer.css">
</head>
<body>

<nav class="navbar">
        <div class="logo-container">
            <div class="logo">BA</div>
            <div class="logo-text">BANTAY<span>ANI</span></div>
        </div>
        
        <div class="nav-links">
            <a href="../index.php" class="nav-link">Dashboard</a>
            <a href="marketplace.php" class="nav-link active">Marketplace</a>
            <a href="cart.php" class="nav-link">Cart</a>
            <a href="orders.php" class="nav-link">My Orders</a>
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

<h2>My Orders</h2>

<?php if (!empty($_SESSION['message'])): ?>
    <div class="alert"><?= htmlspecialchars($_SESSION['message']) ?></div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<?php while ($row = $result->fetch_assoc()) { ?>
<div class="card">
    Order #<?= $row['order_id'] ?><br>
    Delivery Status: <?= $row['order_status'] ?><br>
    <a class="btn" href="orders_view.php?id=<?= $row['order_id'] ?>">View</a>

    <?php if ($row['order_status'] === 'Pending'): ?>
        <form method="POST" action="cancel_order.php" style="display:inline-block; margin-left:10px;">
            <input type="hidden" name="order_id" value="<?= $row['order_id'] ?>">
            <button type="submit" class="btn btn-cancel">Cancel</button>
        </form>
    <?php endif; ?>
</div>
<?php } ?>

</body>
</html>
