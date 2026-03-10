<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Check if farmer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Farmer') {
    header('Location: ../user/login.php');
    exit;
}

$farmer_id = $_SESSION['user_id'];

// Handle crop deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_crop'])) {
    $inventory_id = intval($_POST['delete_crop']);
    
    try {
        // Verify the crop belongs to this farmer
        $verify_stmt = $conn->prepare("SELECT inventory_id FROM crops_inventory WHERE inventory_id = ? AND farmer_id = ?");
        $verify_stmt->bind_param('ii', $inventory_id, $farmer_id);
        $verify_stmt->execute();
        $result = $verify_stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Delete the crop
            $delete_stmt = $conn->prepare("DELETE FROM crops_inventory WHERE inventory_id = ? AND farmer_id = ?");
            $delete_stmt->bind_param('ii', $inventory_id, $farmer_id);
            $delete_stmt->execute();
            
            $_SESSION['success_message'] = 'Crop removed from inventory successfully!';
        } else {
            $_SESSION['error_message'] = 'Crop not found or access denied.';
        }
        
        $verify_stmt->close();
        $delete_stmt->close();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error deleting crop: ' . $e->getMessage();
    }
    
    header('Location: crop_manage.php');
    exit;
}

// Fetch farmer's crops with category information
$crops_query = "
    SELECT ci.inventory_id, c.crop_name, ci.quantity, ci.unit, ci.price, ci.harvest_date, 
           ci.harvest_status, ci_img.image_path, cc.category_name
    FROM crops_inventory ci
    JOIN crops c ON ci.crop_id = c.crop_id
    JOIN crop_categories cc ON c.category_id = cc.category_id
    JOIN crop_images ci_img ON ci.inventory_id = ci_img.inventory_id
    WHERE ci.farmer_id = ?
    ORDER BY ci.harvest_date DESC, ci.created_at DESC
";

$crops_stmt = $conn->prepare($crops_query);
$crops_stmt->bind_param('i', $farmer_id);
$crops_stmt->execute();
$crops_result = $crops_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Crops | BantayAni</title>
    <style>
        :root {
            --green-dark: #0c5c4c;
            --green: #1f8a70;
            --beige: #f6f1e9;
            --orange: #f28705;
            --text: #1f2933;
            --red: #dc2626;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(130deg, rgba(12, 92, 76, 0.07), rgba(242, 135, 5, 0.12));
            color: var(--text);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 16px;
        }

        .header {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(12, 92, 76, 0.15);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            margin: 0;
            color: var(--green-dark);
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            background: var(--green);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn:hover {
            background: var(--green-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #6b7280;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: var(--red);
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .crops-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .crop-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(12, 92, 76, 0.15);
            overflow: hidden;
            transition: transform 0.2s ease;
        }

        .crop-card:hover {
            transform: translateY(-2px);
        }

        .crop-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: var(--beige);
        }

        .crop-content {
            padding: 20px;
        }

        .crop-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .crop-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--green-dark);
            margin: 0;
        }

        .crop-category {
            background: var(--beige);
            color: var(--green-dark);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .crop-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 16px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 2px;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text);
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
        }

        .status-scheduled {
            background: #fef3c7;
            color: #92400e;
        }

        .status-confirmed {
            background: #d1fae5;
            color: #0353a4;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .crop-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .alert-error {
            background: rgba(185, 28, 28, 0.1);
            border: 1px solid rgba(185, 28, 28, 0.4);
            color: #b91c1c;
        }

        .alert-success {
            background: rgba(31, 138, 112, 0.1);
            border: 1px solid rgba(31, 138, 112, 0.4);
            color: var(--green-dark);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .empty-state h3 {
            margin: 0 0 16px;
            color: var(--green-dark);
        }

        @media (max-width: 768px) {
            .crops-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }
            
            .header-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Crops Inventory</h1>
            <div class="header-actions">
                <a href="crop_add.php" class="btn">
                    <span>+</span> Add New Crop
                </a>
                <a href="inventory.php" class="btn btn-secondary">
                    View Full Inventory Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo htmlspecialchars($_SESSION['success_message']); 
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?php 
                    echo htmlspecialchars($_SESSION['error_message']); 
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if ($crops_result->num_rows > 0): ?>
            <div class="crops-grid">
                <?php while ($crop = $crops_result->fetch_assoc()): ?>
                    <div class="crop-card">
                        <?php if ($crop['image_path']): ?>
                            <?php
                                $cropImagePath = trim($crop['image_path']);
                                if (preg_match('#^(https?://|/)#', $cropImagePath)) {
                                    $imgSrc = $cropImagePath;
                                } else {
                                    $imgSrc = '../' . ltrim($cropImagePath, '/');
                                }
                            ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>" 
                                 alt="<?= htmlspecialchars($crop['crop_name']); ?>" 
                                 class="crop-image">
                        <?php else: ?>
                            <div class="crop-image">
                                <img src="../images/default-crop.png" alt="No image" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                        <?php endif; ?>
                        
                        <div class="crop-content">
                            <div class="crop-header">
                                <h3 class="crop-title"><?php echo htmlspecialchars($crop['crop_name']); ?></h3>
                                <span class="crop-category"><?php echo htmlspecialchars($crop['category_name']); ?></span>
                            </div>
                            
                            <div class="crop-details">
                                <div class="detail-item">
                                    <span class="detail-label">Quantity</span>
                                    <span class="detail-value"><?php echo number_format($crop['quantity'], 2); ?> <?php echo htmlspecialchars($crop['unit']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Price</span>
                                    <span class="detail-value">₱<?php echo number_format($crop['price'], 2); ?> / <?php echo htmlspecialchars($crop['unit']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Harvest Date</span>
                                    <span class="detail-value"><?php echo date('M d, Y', strtotime($crop['harvest_date'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="status-badge status-<?php echo strtolower($crop['harvest_status']); ?>">
                                <?php echo htmlspecialchars($crop['harvest_status']); ?>
                            </div>
                            
                            <div class="crop-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="delete_crop" value="<?php echo $crop['inventory_id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-small" 
                                            onclick="return confirm('Are you sure you want to remove this crop from inventory?')">
                                        Remove
                                    </button>
                                </form>
                                <a href="crop_edit.php?id=<?php echo $crop['inventory_id']; ?>" class="btn btn-secondary btn-small">
                                    Edit
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No crops in inventory</h3>
                <p>Start adding your harvested crops to make them available for buyers.</p>
                <a href="crop_add.php" class="btn">Add Your First Crop</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
