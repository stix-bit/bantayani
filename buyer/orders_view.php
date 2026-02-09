<?php
session_start();
include "../includes/config.php";

$order_id = $_GET['id'];

$sql = "SELECT c.crop_name
        FROM order_items oi
        JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
        JOIN crops c ON ci.crop_id = c.crop_id
        WHERE oi.order_id = $order_id";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Order Details</title>
    <link rel="stylesheet" href="assets/css/buyer.css">
</head>
<body>

<h2>Order #<?= $order_id ?></h2>

<?php while ($row = $result->fetch_assoc()) { ?>
<div class="cart-item"><?= $row['crop_name'] ?></div>
<?php } ?>

</body>
</html>
