<?php
session_start();
require_once __DIR__ . "/../includes/auth_helper.php";
require_login('Farmer');
require_once '../includes/config.php';

$farmer_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

// Handle setting primary image
if (isset($_POST['set_primary']) && $_POST['set_primary'] == '1') {
    $image_id = (int)($_POST['image_id'] ?? 0);
    $inventory_id = (int)($_POST['inventory_id'] ?? 0);
    
    if ($image_id <= 0 || $inventory_id <= 0) {
        $response['message'] = 'Invalid IDs';
        echo json_encode($response);
        exit;
    }
    
    // Verify ownership
    $check_stmt = $conn->prepare("
        SELECT ci.image_id 
        FROM crop_images ci 
        JOIN crops_inventory inv ON ci.inventory_id = inv.inventory_id 
        WHERE ci.image_id = ? AND ci.inventory_id = ? AND inv.farmer_id = ?
    ");
    $check_stmt->bind_param("iii", $image_id, $inventory_id, $farmer_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $response['message'] = 'Permission denied';
        echo json_encode($response);
        exit;
    }
    $check_stmt->close();
    
    // Unset all primary images for this inventory
    $unset_primary_stmt = $conn->prepare("UPDATE crop_images SET is_primary = 0 WHERE inventory_id = ?");
    $unset_primary_stmt->bind_param("i", $inventory_id);
    $unset_primary_stmt->execute();
    $unset_primary_stmt->close();
    
    // Set this image as primary
    $set_primary_stmt = $conn->prepare("UPDATE crop_images SET is_primary = 1 WHERE image_id = ?");
    $set_primary_stmt->bind_param("i", $image_id);
    
    if ($set_primary_stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Primary image updated successfully';
    } else {
        $response['message'] = 'Database error: ' . $conn->error;
    }
    $set_primary_stmt->close();
    
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['crop_image'])) {
    $inventory_id = (int)($_POST['inventory_id'] ?? 0);
    
    if ($inventory_id <= 0) {
        $response['message'] = 'Invalid inventory ID';
        echo json_encode($response);
        exit;
    }
    
    // Verify this inventory belongs to the farmer
    $check_stmt = $conn->prepare("SELECT inventory_id FROM crops_inventory WHERE inventory_id = ? AND farmer_id = ?");
    $check_stmt->bind_param("ii", $inventory_id, $farmer_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $response['message'] = 'You do not have permission to upload images for this crop';
        echo json_encode($response);
        exit;
    }
    $check_stmt->close();
    
    $file = $_FILES['crop_image'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Validate file
    if (!in_array($file['type'], $allowed_types)) {
        $response['message'] = 'Only JPEG, PNG, and GIF images are allowed';
        echo json_encode($response);
        exit;
    }
    
    if ($file['size'] > $max_size) {
        $response['message'] = 'Image size must be less than 5MB';
        echo json_encode($response);
        exit;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = 'Upload error: ' . $file['error'];
        echo json_encode($response);
        exit;
    }
    
    // Create unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'crop_' . $inventory_id . '_' . time() . '.' . $extension;
    $upload_dir = '../uploads/crop_images/';
    $upload_path = $upload_dir . $filename;
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Save to database
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        
        // If setting as primary, unset other primary images for this inventory
        if ($is_primary) {
            $unset_primary_stmt = $conn->prepare("UPDATE crop_images SET is_primary = 0 WHERE inventory_id = ?");
            $unset_primary_stmt->bind_param("i", $inventory_id);
            $unset_primary_stmt->execute();
            $unset_primary_stmt->close();
        }
        
        $insert_stmt = $conn->prepare("INSERT INTO crop_images (inventory_id, image_path, is_primary) VALUES (?, ?, ?)");
        $db_path = 'uploads/crop_images/' . $filename;
        $insert_stmt->bind_param("isi", $inventory_id, $db_path, $is_primary);
        
        if ($insert_stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Image uploaded successfully';
            $response['image_id'] = $conn->insert_id;
            $response['image_path'] = $db_path;
        } else {
            $response['message'] = 'Database error: ' . $conn->error;
            // Remove uploaded file if database insert failed
            unlink($upload_path);
        }
        $insert_stmt->close();
    } else {
        $response['message'] = 'Failed to upload file';
    }
} else {
    $response['message'] = 'No file uploaded';
}

echo json_encode($response);
$conn->close();
?>
