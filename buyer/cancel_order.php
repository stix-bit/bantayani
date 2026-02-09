<?php
session_start();
include "../includes/config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $buyer_id = $_SESSION['user_id'] ?? 0;
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

    if ($order_id > 0 && $buyer_id > 0) {
        // Only allow cancel if the order belongs to the buyer and is not completed/canceled yet
        $stmt = $conn->prepare("SELECT order_status FROM orders WHERE order_id = ? AND buyer_id = ?");
        $stmt->bind_param("ii", $order_id, $buyer_id);
        $stmt->execute();
        $stmt->bind_result($status);

        if ($stmt->fetch()) {
            // Check order status
            if ($status === 'Pending') { 
                // Update to Canceled
                $stmt->close(); // close the first statement BEFORE starting the update
                $update = $conn->prepare("UPDATE orders SET order_status = 'Cancelled' WHERE order_id = ? AND buyer_id = ?");
                $update->bind_param("ii", $order_id, $buyer_id);
                $update->execute();
                $update->close();
                $_SESSION['message'] = "Order #$order_id has been canceled.";
            } else {
                $_SESSION['message'] = "Only pending orders can be canceled.";
                $stmt->close(); // close here because we didn't do the update
            }
        } else {
            $_SESSION['message'] = "Order not found.";
            $stmt->close(); // close here as well
        }
    }
}

header("Location: orders.php");
exit;
