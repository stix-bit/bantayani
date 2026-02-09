<?php
session_start();
include "../includes/config.php";

$buyer_id = $_SESSION['user_id'];
$result = $conn->query("SELECT * FROM orders WHERE buyer_id = $buyer_id");
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Orders</title>
    <link rel="stylesheet" href="assets/css/buyer.css">
</head>
<body>

<h2>My Orders</h2>

<?php while ($row = $result->fetch_assoc()) { ?>
<div class="card">
    Order #<?= $row['order_id'] ?><br>
    Status: <?= $row['order_status'] ?><br>
    <a class="btn" href="order_view.php?id=<?= $row['order_id'] ?>">View</a>
</div>
<?php } ?>

</body>
</html>
