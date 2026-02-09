<?php
session_start();
include "../includes/config.php";

$buyer_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'];

$conn->query("INSERT INTO orders (buyer_id) VALUES ($buyer_id)");
$order_id = $conn->insert_id;

foreach ($cart as $inventory_id => $qty) {
    $conn->query("INSERT INTO order_items (order_id, inventory_id)
                  VALUES ($order_id, $inventory_id)");

    $conn->query("UPDATE crops_inventory
                  SET quantity = quantity - $qty
                  WHERE inventory_id = $inventory_id");
}

$conn->query("INSERT INTO payment (order_id, payment_method)
              VALUES ($order_id, 'Cash')");

unset($_SESSION['cart']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Checkout</title>
    <link rel="stylesheet" href="assets/css/buyer.css">
</head>
<body>

<div class="card">
    <h2>Order Successful</h2>
    <p>Your order has been placed.</p>
    <a class="btn" href="orders.php">View Orders</a>
</div>

</body>
</html>
