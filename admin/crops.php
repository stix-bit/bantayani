<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Admin');
require_once '../includes/config.php';

// Handle actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' && isset($_POST['crop_name'], $_POST['unit'])) {
        $crop_name = $_POST['crop_name'];
        $unit = $_POST['unit'];

        $stmt = $conn->prepare("INSERT INTO crops (crop_name, unit) VALUES (?, ?)");
        $stmt->bind_param("ss", $crop_name, $unit);
        $stmt->execute();
    }

    if ($action === 'edit' && isset($_POST['crop_id'], $_POST['crop_name'], $_POST['unit'])) {
        $crop_id = (int) $_POST['crop_id'];
        $crop_name = $_POST['crop_name'];
        $unit = $_POST['unit'];

        $stmt = $conn->prepare("UPDATE crops SET crop_name = ?, unit = ? WHERE crop_id = ?");
        $stmt->bind_param("ssi", $crop_name, $unit, $crop_id);
        $stmt->execute();
    }

    if ($action === 'delete' && isset($_POST['crop_id'])) {
        $crop_id = (int) $_POST['crop_id'];

        $stmt = $conn->prepare("DELETE FROM crops WHERE crop_id = ?");
        $stmt->bind_param("i", $crop_id);
        $stmt->execute();
    }

    header("Location: crops.php");
    exit;
}

// Fetch all crops
$result = $conn->query("SELECT crop_id, crop_name, unit FROM crops ORDER BY crop_name ASC");
$crops = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Crops - BANTAY-ANI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="index.php" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Same CSS as admin/users.php */
        <?php include '../includes/styles/admin_style.css'; // put your existing CSS in a separate file for reuse ?>
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="logo-container">
        <a href="http://localhost/bantayani/index.php" class="logo">
            <div class="logo-icon">BA</div>
            <div class="logo-text">BANTAY<span>ANI</span></div>
        </a>
        <div class="admin-badge">Admin</div>
    </div>

    <div class="nav-section">
        <div class="nav-title">Main</div>
        <ul class="nav-links">
            <li><a href="index.php" class="nav-link"><span class="nav-icon">📊</span><span>Dashboard</span></a></li>
            <li><a href="users.php" class="nav-link"><span class="nav-icon">👥</span><span>Users</span></a></li>
            <li><a href="verify_farmers.php" class="nav-link"><span class="nav-icon">✅</span><span>Verification</span></a></li>
            <li><a href="orders.php" class="nav-link"><span class="nav-icon">📦</span><span>Orders</span></a></li>
            <li><a href="reports.php" class="nav-link"><span class="nav-icon">📈</span><span>Reports</span></a></li>
        </ul>
    </div>

    <div class="nav-section">
            <div class="nav-title">Management</div>
            <ul class="nav-links">
                <li><a href="crops.php" class="nav-link active"><span class="nav-icon">🌱</span><span>Crop Categories</span></a></li>
                <li><a href="benchmarking.php" class="nav-link"><span class="nav-icon">💰</span><span>Price Benchmarks</span></a></li>
                <li><a href="announcements.php" class="nav-link"><span class="nav-icon">📢</span><span>Announcements</span></a></li>
            </ul>
        </div>
        
        <!-- <div class="nav-section">
            <div class="nav-title">System</div>
            <ul class="nav-links">
                <li><a href="settings.php" class="nav-link"><span class="nav-icon">⚙️</span><span>Settings</span></a></li>
                <li><a href="logs.php" class="nav-link"><span class="nav-icon">📝</span><span>System Logs</span></a></li>
                <li><a href="backup.php" class="nav-link"><span class="nav-icon">💾</span><span>Backup</span></a></li>
            </ul>
        </div> -->
</aside>

<main class="main-content">
    <div class="topbar">
        <div class="page-title">
            <h1>Crops Management</h1>
            <p>Manage all crop types available for farmers</p>
        </div>
        <button onclick="openAddModal()" class="confirm-btn">Add Crop</button>
    </div>

    <div class="content">
        <div class="table-card">
            <div class="table-header">
                <h3>All Crops</h3>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Crop Name</th>
                        <th>Unit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($crops)): ?>
                    <?php foreach ($crops as $crop): ?>
                        <tr>
                            <td><?= htmlspecialchars($crop['crop_name']) ?></td>
                            <td><?= htmlspecialchars($crop['unit']) ?></td>
                            <td>
                                <button class="icon-btn edit-btn" onclick="openEditModal(
                                    <?= $crop['crop_id'] ?>,
                                    '<?= $crop['crop_name'] ?>',
                                    '<?= $crop['unit'] ?>'
                                )"><i class="fa-solid fa-pen-to-square"></i></button>

                                <button class="icon-btn delete-btn" onclick="openDeleteModal(<?= $crop['crop_id'] ?>)">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align:center; padding:40px;">No crops found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="modal-box">
        <h3>Add Crop</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <label>Crop Name</label>
            <input type="text" name="crop_name" required>
            <label>Unit</label>
            <select name="unit" required>
                <option value="kg">kg</option>
                <option value="g">g</option>
                <option value="pieces">pieces</option>
            </select>
            <div style="margin-top:15px;">
                <button type="submit" class="confirm-btn">Add</button>
                <button type="button" onclick="closeAddModal()" class="confirm-btn">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-box">
        <h3>Edit Crop</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="crop_id" id="editCropId">

            <label>Crop Name</label>
            <input type="text" name="crop_name" id="editCropName" required>

            <label>Unit</label>
            <select name="unit" id="editUnit" required>
                <option value="kg">kg</option>
                <option value="g">g</option>
                <option value="pieces">pieces</option>
            </select>

            <div style="margin-top:15px;">
                <button type="submit" class="confirm-btn">Save</button>
                <button type="button" onclick="closeEditModal()" class="confirm-btn">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-box">
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete this crop?</p>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="crop_id" id="deleteCropId">
            <div style="margin-top:15px;">
                <button type="submit" class="confirm-btn danger">Delete</button>
                <button type="button" onclick="closeDeleteModal()" class="confirm-btn">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}
function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

function openEditModal(id, name, unit) {
    document.getElementById('editCropId').value = id;
    document.getElementById('editCropName').value = name;
    document.getElementById('editUnit').value = unit;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function openDeleteModal(id) {
    document.getElementById('deleteCropId').value = id;
    document.getElementById('deleteModal').style.display = 'flex';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
</script>
</body>
</html>
