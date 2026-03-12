<?php
session_start();
require_once __DIR__ . "/../includes/auth_helper.php";
require_login('Farmer');
require_once '../includes/config.php';

$farmer_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $image_id = 0;

    // Support JSON body from JS fetch and form data submits
    if (isset($_POST['image_id'])) {
        $image_id = (int)$_POST['image_id'];
    } else {
        $rawBody = file_get_contents('php://input');
        $jsonBody = json_decode($rawBody, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($jsonBody['image_id'])) {
            $image_id = (int)$jsonBody['image_id'];
        }
    }

    if ($image_id <= 0) {
        $response['message'] = 'Invalid image ID';
        echo json_encode($response);
        exit;
    }
    
    // Get image info and verify ownership
    $get_image_stmt = $conn->prepare("
        SELECT ci.image_path, ci.inventory_id, inv.farmer_id 
        FROM crop_images ci 
        JOIN crops_inventory inv ON ci.inventory_id = inv.inventory_id 
        WHERE ci.image_id = ?
    ");
    $get_image_stmt->bind_param("i", $image_id);
    $get_image_stmt->execute();
    $image_result = $get_image_stmt->get_result();
    
    if ($image_result->num_rows === 0) {
        $response['message'] = 'Image not found';
        echo json_encode($response);
        exit;
    }
    
    $image_data = $image_result->fetch_assoc();
    $get_image_stmt->close();
    
    if ($image_data['farmer_id'] != $farmer_id) {
        $response['message'] = 'You do not have permission to delete this image';
        echo json_encode($response);
        exit;
    }
    
    // Delete from database
    $delete_stmt = $conn->prepare("DELETE FROM crop_images WHERE image_id = ?");
    $delete_stmt->bind_param("i", $image_id);
    
    if ($delete_stmt->execute()) {
        // Delete physical file
        $file_path = '../' . $image_data['image_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        $response['success'] = true;
        $response['message'] = 'Image deleted successfully';
    } else {
        $response['message'] = 'Database error: ' . $conn->error;
    }
    $delete_stmt->close();
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);
$conn->close();
?>
