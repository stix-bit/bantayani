<?php
session_start();
include "../includes/config.php";

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $inventory_id = (int)($_POST['inventory_id'] ?? 0);
    $qty_posted = (int)($_POST['qty'] ?? 0);
    if ($inventory_id > 0 && $qty_posted > 0) {
        // Check available stock
        $stmtInv = $conn->prepare("SELECT quantity FROM crops_inventory WHERE inventory_id = ?");
        $stmtInv->bind_param("i", $inventory_id);
        $stmtInv->execute();
        $stmtInv->bind_result($available);
        $stmtInv->fetch();
        $stmtInv->close();

        $current = isset($_SESSION['cart'][$inventory_id]) ? (int)$_SESSION['cart'][$inventory_id] : 0;
        $newQty = $current + $qty_posted;

        // Cap to available stock if known
        if (is_numeric($available)) {
            if ($newQty > $available) {
                $newQty = $available;
            }
        }

        $_SESSION['cart'][$inventory_id] = $newQty;
    }
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$profile_img = null;
$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../user/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cart</title>
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

    <br>

<h2>Your Cart</h2>

<?php
$total = 0;
foreach ($_SESSION['cart'] as $id => $qty) {
    $stmt = $conn->prepare("SELECT ci.price, c.crop_name
        FROM crops_inventory ci
        JOIN crops c ON ci.crop_id = c.crop_id
        WHERE ci.inventory_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $subtotal = $row['price'] * $qty;
    $total += $subtotal;
?>
<div class="cart-item">
    <b><?= $row['crop_name'] ?></b><br>
    Quantity: <?= $qty ?><br>
    Subtotal: ₱<?= $subtotal ?>
</div>
<?php } ?>

<div class="total">Total: ₱<?= $total ?></div>
<a class="btn" href="checkout.php">Checkout</a>

</body>
</html>
