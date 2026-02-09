<?php
session_start();
include "../includes/config.php";

$id = $_GET['id'];

$sql = "SELECT ci.*, c.crop_name, f.farm_name
        FROM crops_inventory ci
        JOIN crops c ON ci.crop_id = c.crop_id
        JOIN farmer_profiles f ON ci.farmer_id = f.farmer_id
        WHERE ci.inventory_id = $id";

$row = $conn->query($sql)->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Product</title>
    <link rel="stylesheet" href="assets/css/buyer.css">
</head>
<body>

<div class="card">
    <h2><?= $row['crop_name'] ?></h2>
    <p>Farm: <?= $row['farm_name'] ?></p>
    <p>Price: ₱<?= $row['price'] ?></p>

    <form method="post" action="cart.php">
        <input type="hidden" name="inventory_id" value="<?= $row['inventory_id'] ?>">
        <input type="number" name="qty" min="1" max="<?= $row['quantity'] ?>" required>
        <button class="btn">Add to Cart</button>
    </form>
</div>

</body>
</html>
