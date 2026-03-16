<?php
require_once __DIR__ . '/../includes/config.php';

$category_id = intval($_GET['category_id'] ?? 0);
$crops = [];

if ($category_id > 0) {
    // Only return non-archived crops for this category
    $stmt = $conn->prepare("SELECT crop_id, crop_name FROM crops WHERE category_id = ? AND deleted_at IS NULL ORDER BY crop_name");
    $stmt->bind_param('i', $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $crops[] = $row;
    }
    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($crops);