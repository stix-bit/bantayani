<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Buyer');
include "../includes/config.php";

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_quantity') {
        $key = $_POST['cart_key'] ?? '';
        $delta = (int)($_POST['delta'] ?? 0);

        if ($key !== '' && $delta !== 0 && isset($_SESSION['cart'][$key])) {
            $current = (float)$_SESSION['cart'][$key];
            $newQty = $current + $delta;

            if (strpos($key, 'p_') === 0) {
                $pool_id = (int)substr($key, 2);
                $stmt = $conn->prepare("SELECT total_quantity FROM cooperative_pools WHERE pool_id = ?");
                $stmt->bind_param("i", $pool_id);
                $stmt->execute();
                $stmt->bind_result($available);
                $stmt->fetch();
                $stmt->close();

                if (is_numeric($available) && $newQty > (float)$available) {
                    $newQty = (float)$available;
                }
            } else {
                $inventory_id = (int)$key;
                $stmt = $conn->prepare("SELECT quantity FROM crops_inventory WHERE inventory_id = ?");
                $stmt->bind_param("i", $inventory_id);
                $stmt->execute();
                $stmt->bind_result($available);
                $stmt->fetch();
                $stmt->close();

                if (is_numeric($available) && $newQty > (float)$available) {
                    $newQty = (float)$available;
                }
            }

            if ($newQty > 0) {
                $_SESSION['cart'][$key] = $newQty;
            } else {
                unset($_SESSION['cart'][$key]);
            }
        }
    } else {
        $pool_id = (int)($_POST['pool_id'] ?? 0);
        $inventory_id = (int)($_POST['inventory_id'] ?? 0);
        $qty_posted = (float)($_POST['qty'] ?? 0);

        if ($pool_id > 0 && $qty_posted > 0) {
            // Add cooperative pool item (key: p_POOL_ID)
            $key = 'p_' . $pool_id;
            $stmt = $conn->prepare("SELECT total_quantity FROM cooperative_pools WHERE pool_id = ?");
            $stmt->bind_param("i", $pool_id);
            $stmt->execute();
            $stmt->bind_result($available);
            $stmt->fetch();
            $stmt->close();
            $current = isset($_SESSION['cart'][$key]) ? (float)$_SESSION['cart'][$key] : 0;
            $newQty = $current + $qty_posted;
            if (is_numeric($available) && $newQty > (float)$available) $newQty = (float)$available;
            $_SESSION['cart'][$key] = $newQty;
        } elseif ($inventory_id > 0 && $qty_posted > 0) {
            // Add individual farmer inventory item
            $stmtInv = $conn->prepare("SELECT quantity FROM crops_inventory WHERE inventory_id = ?");
            $stmtInv->bind_param("i", $inventory_id);
            $stmtInv->execute();
            $stmtInv->bind_result($available);
            $stmtInv->fetch();
            $stmtInv->close();
            $current = isset($_SESSION['cart'][$inventory_id]) ? (int)$_SESSION['cart'][$inventory_id] : 0;
            $newQty = $current + (int)$qty_posted;
            if (is_numeric($available) && $newQty > $available) $newQty = (int)$available;
            $_SESSION['cart'][$inventory_id] = $newQty;
        }
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
            <a href="../marketplace.php" class="nav-link">Marketplace</a>
            <a href="cart.php" class="nav-link active">Cart</a>
            <a href="orders.php" class="nav-link">My Orders</a>
            <a href="../announcements.php" class="nav-link">Announcements</a>
            <a href="../invoices.php" class="nav-link">Invoices</a>
            <a href="notifications.php" class="nav-link">Notifications</a>
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

    <div class="main-container">
        <div class="page-header">
            <h1>🛒 Your Cart</h1>
            <p>Review your items before checkout</p>
        </div>

        <div class="cart-card">
            <h2 class="card-title">Cart</h2>

            <?php
            $total = 0;
            $has_pool_price = false;
            $cols = $conn->query("SHOW COLUMNS FROM cooperative_pools LIKE 'unit_price'");
            if ($cols && $cols->num_rows > 0) $has_pool_price = true;

            if (empty($_SESSION['cart'])): ?>
                <div class="cart-empty-msg">
                    Your cart is empty.<br>
                    <a href="marketplace.php">Browse Marketplace</a> or try <a href="product_pool.php">Cooperative Pools</a> for bulk orders.
                </div>
            <?php else:
                foreach ($_SESSION['cart'] as $id => $qty) {
                    $qty = (float)$qty;
                    if ($qty <= 0) continue;

                    $itemName = '';
                    $itemUnit = '';
                    $itemImage = '';
                    $itemPrice = 0;
                    $itemLabel = '';
                    $itemKey = $id;

                    if (is_string($id) && strpos($id, 'p_') === 0) {
                        $pool_id = (int)substr($id, 2);
                        $stmt = $conn->prepare("SELECT p.total_quantity, c.crop_name, p.unit" . ($has_pool_price ? ", p.unit_price" : "") . ", (SELECT ci_img.image_path FROM crops_inventory ci JOIN crop_images ci_img ON ci.inventory_id = ci_img.inventory_id WHERE ci.crop_id = p.crop_id ORDER BY ci_img.is_primary DESC, ci_img.image_id ASC LIMIT 1) AS image_path FROM cooperative_pools p JOIN crops c ON p.crop_id = c.crop_id WHERE p.pool_id = ?");
                        $stmt->bind_param("i", $pool_id);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if (!$row) continue;
                        $itemName = $row['crop_name'];
                        $itemUnit = $row['unit'];
                        $itemImage = $row['image_path'] ?? '';
                        $itemLabel = 'Cooperative';
                        $itemPrice = ($has_pool_price && isset($row['unit_price']) && $row['unit_price'] != null) ? (float)$row['unit_price'] : 0;
                    } else {
                        $inv_id = (int)$id;
                        $stmt = $conn->prepare("SELECT ci.price, c.crop_name, ci.unit, (SELECT image_path FROM crop_images WHERE inventory_id = ci.inventory_id ORDER BY is_primary DESC, image_id ASC LIMIT 1) AS image_path FROM crops_inventory ci JOIN crops c ON ci.crop_id = c.crop_id WHERE ci.inventory_id = ?");
                        $stmt->bind_param("i", $inv_id);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if (!$row) continue;
                        $itemName = $row['crop_name'];
                        $itemUnit = $row['unit'];
                        $itemImage = $row['image_path'] ?? '';
                        $itemLabel = '';
                        $itemPrice = (float)$row['price'];
                    }

                    $subtotal = $itemPrice * $qty;
                    $total += $subtotal;
            ?>
            <div class="cart-item-row">
                <div class="cart-item-thumbnail">
                    <?php if (!empty($itemImage) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/bantayani/' . $itemImage)): ?>
                        <img src="<?= htmlspecialchars('/bantayani/' . $itemImage) ?>" alt="<?= htmlspecialchars($itemName) ?>">
                    <?php elseif (!empty($itemImage)): ?>
                        <img src="<?= htmlspecialchars($itemImage) ?>" alt="<?= htmlspecialchars($itemName) ?>">
                    <?php else: ?>
                        <div class="cart-item-placeholder"><i class="fa-solid fa-image"></i></div>
                    <?php endif; ?>
                </div>
                <div class="cart-item-content">
                    <div class="cart-item-name"><?= htmlspecialchars($itemName) ?> <?php if ($itemLabel): ?><span class="cart-item-label">(<?= htmlspecialchars($itemLabel) ?>)</span><?php endif; ?></div>
                    <div class="cart-item-meta"><?= number_format($qty, 2) ?> <?= htmlspecialchars($itemUnit) ?></div>
                    <div class="quantity-controls">
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="update_quantity">
                            <input type="hidden" name="cart_key" value="<?= htmlspecialchars($itemKey) ?>">
                            <input type="hidden" name="delta" value="-1">
                            <button type="submit" class="qty-btn">−</button>
                        </form>
                        <span class="qty-value"><?= number_format($qty, 2) ?></span>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="update_quantity">
                            <input type="hidden" name="cart_key" value="<?= htmlspecialchars($itemKey) ?>">
                            <input type="hidden" name="delta" value="1">
                            <button type="submit" class="qty-btn">+</button>
                        </form>
                        <form method="post" style="display:inline; margin-left: 12px;">
                            <input type="hidden" name="action" value="update_quantity">
                            <input type="hidden" name="cart_key" value="<?= htmlspecialchars($itemKey) ?>">
                            <input type="hidden" name="delta" value="-999999">
                            <button type="submit" class="remove-btn">Remove</button>
                        </form>
                    </div>
                </div>
                <div class="cart-item-subtotal">₱<?= number_format($subtotal, 2) ?></div>
            </div>
            <?php
                }
                endif;
            ?>

            <?php if (!empty($_SESSION['cart'])): ?>
            <div class="cart-total-bar">
                <span class="cart-total-label">Total</span>
                <span class="cart-total-amount">₱<?= number_format($total, 2) ?></span>
            </div>
            <div class="cart-actions">
                <a class="btn" href="checkout.php">Proceed to Checkout</a>
                <a class="btn" href="../marketplace.php" style="background: var(--beige); color: var(--text); box-shadow: none;">Continue Shopping</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
