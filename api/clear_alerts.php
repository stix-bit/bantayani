<?php
/**
 * Clear all weather alerts
 * Deletes all active weather alerts from the database
 */

// Set error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check if user is a Farmer
$user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (strtolower($user_role) !== 'farmer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only farmers can clear alerts']);
    exit;
}

// Delete all alerts
$result = $conn->query("DELETE FROM weather_alerts WHERE created_at > NOW() - INTERVAL 30 DAY");

if ($result) {
    echo json_encode([
        'success' => true,
        'message' => 'All alerts cleared successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $conn->error
    ]);
}
?>
