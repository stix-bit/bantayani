<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Buyer');
include "../includes/config.php";

$buyer_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'] ?? [];
$order_confirmed = isset($_POST['confirm_checkout']) && $_POST['confirm_checkout'] === '1';

// Check if order_items has quantity column (from cooperative_pools_migration.sql)
$has_oi_quantity = false;
$cols = $conn->query("SHOW COLUMNS FROM order_items LIKE 'quantity'");
if ($cols && $cols->num_rows > 0) $has_oi_quantity = true;

// Only process the order if user confirmed
if ($order_confirmed) {
    $conn->begin_transaction();
    $ok = true;

    $stmtOrder = $conn->prepare("INSERT INTO orders (buyer_id) VALUES (?)");
    $stmtOrder->bind_param("i", $buyer_id);
    $stmtOrder->execute();
    $order_id = $conn->insert_id;
    $stmtOrder->close();

    foreach ($cart as $key => $qty) {
        $qty = (float)$qty;
        if ($qty <= 0) continue;

        // Cooperative pool item (key = p_POOL_ID)
        if (is_string($key) && strpos($key, 'p_') === 0) {
            $pool_id = (int)substr($key, 2);
            $stmtChk = $conn->prepare("SELECT total_quantity FROM cooperative_pools WHERE pool_id = ? FOR UPDATE");
            $stmtChk->bind_param("i", $pool_id);
            $stmtChk->execute();
            $stmtChk->bind_result($available);
            $stmtChk->fetch();
            $stmtChk->close();
            if (!is_numeric($available) || (float)$available < $qty) {
                $ok = false;
                break;
            }
            if ($has_oi_quantity) {
                $stmtItem = $conn->prepare("INSERT INTO order_items (order_id, inventory_id, pool_id, quantity) VALUES (?, NULL, ?, ?)");
                $stmtItem->bind_param("iid", $order_id, $pool_id, $qty);
            } else {
                $stmtItem = $conn->prepare("INSERT INTO order_items (order_id, pool_id) VALUES (?, ?)");
                $stmtItem->bind_param("ii", $order_id, $pool_id);
            }
            $stmtItem->execute();
            $stmtItem->close();
            $stmtUpd = $conn->prepare("UPDATE cooperative_pools SET total_quantity = total_quantity - ? WHERE pool_id = ?");
            $stmtUpd->bind_param("di", $qty, $pool_id);
            $stmtUpd->execute();
            $stmtUpd->close();
            continue;
        }

        // Individual inventory item
        $inventory_id = (int)$key;
        if ($inventory_id <= 0) continue;

        $stmtChk = $conn->prepare("SELECT quantity FROM crops_inventory WHERE inventory_id = ? FOR UPDATE");
        $stmtChk->bind_param("i", $inventory_id);
        $stmtChk->execute();
        $stmtChk->bind_result($available);
        $stmtChk->fetch();
        $stmtChk->close();
        if (!is_numeric($available) || $available < $qty) {
            $ok = false;
            break;
        }
        if ($has_oi_quantity) {
            $stmtItem = $conn->prepare("INSERT INTO order_items (order_id, inventory_id, pool_id, quantity) VALUES (?, ?, NULL, ?)");
            $stmtItem->bind_param("iid", $order_id, $inventory_id, $qty);
        } else {
            $stmtItem = $conn->prepare("INSERT INTO order_items (order_id, inventory_id) VALUES (?, ?)");
            $stmtItem->bind_param("ii", $order_id, $inventory_id);
        }
        $stmtItem->execute();
        $stmtItem->close();
        $stmtUpd = $conn->prepare("UPDATE crops_inventory SET quantity = quantity - ? WHERE inventory_id = ?");
        $stmtUpd->bind_param("di", $qty, $inventory_id);
        $stmtUpd->execute();
        $stmtUpd->close();
    }

    if ($ok) {
        $stmtPay = $conn->prepare("INSERT INTO payment (order_id, payment_method) VALUES (?, 'Cash')");
        $stmtPay->bind_param("i", $order_id);
        $stmtPay->execute();
        $stmtPay->close();
        $conn->commit();
        unset($_SESSION['cart']);
        $show_success = true;
    } else {
        $conn->rollback();
        $show_error = true;
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

?>
<!DOCTYPE html>
<html>
<head>
    <title>Checkout</title>
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
            <a href="/bantayani/user/logout.php" class="logout-btn" style="text-decoration:none; display:inline-block;">
                Log Out
            </a>
        </div>
    </nav>

<div class="card">
    <?php if (isset($show_success)): ?>
        <h2>Order Successful</h2>
        <p>Your order has been placed.</p>
        <a class="btn" href="orders.php">View Orders</a>
    <?php elseif (isset($show_error)): ?>
        <h2>Order Failed</h2>
        <p>Insufficient stock for one or more items.</p>
        <a class="btn" href="cart.php">Back to Cart</a>
    <?php else: ?>
        <h2>Confirm Checkout</h2>
        <p>Please review your order before payment:</p>
        
        <div style="margin: 20px 0; border-top: 1px solid #ddd; padding-top: 15px;">
            <?php
            $total = 0;
            $has_pool_price = false;
            $cols = $conn->query("SHOW COLUMNS FROM cooperative_pools LIKE 'unit_price'");
            if ($cols && $cols->num_rows > 0) $has_pool_price = true;

            foreach ($cart as $id => $qty) {
                $qty = (float)$qty;
                if ($qty <= 0) continue;

                if (is_string($id) && strpos($id, 'p_') === 0) {
                    $pool_id = (int)substr($id, 2);
                    $stmt = $conn->prepare("SELECT c.crop_name, p.unit" . ($has_pool_price ? ", p.unit_price" : "") . " FROM cooperative_pools p JOIN crops c ON p.crop_id = c.crop_id WHERE p.pool_id = ?");
                    $stmt->bind_param("i", $pool_id);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$row) continue;
                    $price = ($has_pool_price && isset($row['unit_price']) && $row['unit_price'] != null) ? (float)$row['unit_price'] : 0;
                    $subtotal = $price * $qty;
                    $total += $subtotal;
            ?>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                <div>
                    <strong><?= htmlspecialchars($row['crop_name']) ?> <span style="font-size:0.85rem; color:#666;">(Cooperative)</span></strong><br>
                    <small>Qty: <?= number_format($qty, 2) ?> <?= htmlspecialchars($row['unit']) ?> × ₱<?= number_format($price, 2) ?></small>
                </div>
                <div style="text-align: right;">
                    <strong>₱<?= number_format($subtotal, 2) ?></strong>
                </div>
            </div>
            <?php
                } else {
                    $inv_id = (int)$id;
                    $stmt = $conn->prepare("SELECT ci.price, c.crop_name, ci.unit FROM crops_inventory ci JOIN crops c ON ci.crop_id = c.crop_id WHERE ci.inventory_id = ?");
                    $stmt->bind_param("i", $inv_id);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$row) continue;
                    $subtotal = (float)$row['price'] * $qty;
                    $total += $subtotal;
            ?>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                <div>
                    <strong><?= htmlspecialchars($row['crop_name']) ?></strong><br>
                    <small>Qty: <?= number_format($qty, 2) ?> × ₱<?= number_format($row['price'], 2) ?></small>
                </div>
                <div style="text-align: right;">
                    <strong>₱<?= number_format($subtotal, 2) ?></strong>
                </div>
            </div>
            <?php } ?>
            <?php } ?>
        </div>

        <div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <div style="display: flex; justify-content: space-between; font-size: 1.2em; font-weight: bold;">
                <span>Total Amount:</span>
                <span style="color: #27ae60;">₱<?= number_format($total, 2) ?></span>
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: center;">
            <form method="POST">
                <input type="hidden" name="confirm_checkout" value="1">
                <button type="submit" class="btn" style="background-color: #27ae60; padding: 2px 15px; font-size: 0.9em;">
                    Confirm & Pay ₱<?= number_format($total, 2) ?>
                </button>
            </form>

            <a class="btn" href="cart.php" style="background-color: #95a5a6; padding: 2px 15px; display: flex; align-items: center; font-size: 0.9em;">
                Back to Cart
            </a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
