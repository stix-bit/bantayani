<?php
session_start();
include "../includes/config.php";

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $_SESSION['cart'][$_POST['inventory_id']] = $_POST['qty'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cart</title>
    <link rel="stylesheet" href="assets/css/buyer.css">
</head>
<body>

<h2>Your Cart</h2>

<?php
$total = 0;
foreach ($_SESSION['cart'] as $id => $qty) {
    $sql = "SELECT ci.price, c.crop_name
            FROM crops_inventory ci
            JOIN crops c ON ci.crop_id = c.crop_id
            WHERE ci.inventory_id = $id";
    $row = $conn->query($sql)->fetch_assoc();

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
