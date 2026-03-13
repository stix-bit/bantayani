<?php
// Make sure no output before header redirect
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Buyer');  // Only buyers can rate
include "../includes/config.php";

// Get buyer ID from session
$buyer_id = $_SESSION['user_id'];

// Get POST data safely
$inventory_id = isset($_POST['inventory_id']) ? (int)$_POST['inventory_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : null;

// Validate input
if ($inventory_id <= 0 || $rating <= 0 || $rating > 5) {
    $_SESSION['error'] = "Invalid rating submission.";
    header("Location: ../marketplace.php");
    exit();
}

// Check if this buyer has ordered from this pool before allowing review
$pool_check_stmt = $conn->prepare("
    SELECT COUNT(*) as has_ordered 
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
    WHERE o.buyer_id = ? 
    AND ci.crop_id = (SELECT crop_id FROM crops_inventory WHERE inventory_id = ?)
    AND o.order_status IN ('Confirmed', 'Completed', 'Delivered')
");
$pool_check_stmt->bind_param("ii", $buyer_id, $inventory_id);
$pool_check_stmt->execute();
$pool_result = $pool_check_stmt->get_result()->fetch_assoc();
$pool_check_stmt->close();

if (!$pool_result || $pool_result['has_ordered'] == 0) {
    $_SESSION['error'] = "You can only review products you have ordered from.";
    header("Location: ../marketplace.php");
    exit();
}

// Check if this buyer already rated this inventory
$check_stmt = $conn->prepare("SELECT rating_id FROM ratings WHERE buyer_id = ? AND inventory_id = ?");
$check_stmt->bind_param("ii", $buyer_id, $inventory_id);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    // Buyer has rated before → update existing rating
    $update_stmt = $conn->prepare("UPDATE ratings SET rating = ?, comment = ?, created_at = NOW() WHERE buyer_id = ? AND inventory_id = ?");
    $update_stmt->bind_param("isii", $rating, $comment, $buyer_id, $inventory_id);
    $update_stmt->execute();
    $update_stmt->close();
} else {
    // Buyer has not rated → insert new rating
    $insert_stmt = $conn->prepare("INSERT INTO ratings (rating, comment, farmer_id, buyer_id, inventory_id, created_at) 
                                   SELECT ?, ?, ci.farmer_id, ?, ci.inventory_id, NOW() 
                                   FROM crops_inventory ci 
                                   WHERE ci.inventory_id = ?");
    $insert_stmt->bind_param("isii", $rating, $comment, $buyer_id, $inventory_id);
    $insert_stmt->execute();

    if ($conn->affected_rows === 0) {
        $_SESSION['error'] = "Rating submission failed. Inventory item not found.";
        header("Location: ../marketplace.php");
        exit();
    }

    $insert_stmt->close();
}

$check_stmt->close();

// Redirect back to marketplace with success
header("Location: ../marketplace.php");
exit();