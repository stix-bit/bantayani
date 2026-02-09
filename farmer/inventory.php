<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Farmer');
require_once '../includes/config.php';

$farmer_id = $_SESSION['user_id'];

// Handle Add/Edit/Delete actions
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
    $crop_id = $_POST['crop_id'];
    $quantity = $_POST['quantity'];
    $harvest_date = $_POST['harvest_date'];
    $price = $_POST['price'];

    // Check if farmer already has this crop
    $check = $conn->prepare("SELECT inventory_id FROM crops_inventory WHERE farmer_id=? AND crop_id=?");
    $check->bind_param("ii", $farmer_id, $crop_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $errors[] = "You already have this crop in your inventory. Please edit it instead.";
    } else {
        $stmt = $conn->prepare("INSERT INTO crops_inventory (farmer_id, crop_id, quantity, harvest_date, price) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iidsd", $farmer_id, $crop_id, $quantity, $harvest_date, $price);
        $stmt->execute();
        $stmt->close();
        $success = "Crop added successfully!";
    }
    $check->close();
}

    if ($_POST['action'] === 'edit') {
        $inventory_id = $_POST['inventory_id'];
        $crop_id = $_POST['crop_id'];
        $quantity = $_POST['quantity'];
        $harvest_date = $_POST['harvest_date'];
        $price = $_POST['price'];

        $stmt = $conn->prepare("UPDATE crops_inventory SET crop_id = ?, quantity = ?, harvest_date = ?, price = ? WHERE inventory_id = ? AND farmer_id = ?");
        $stmt->bind_param("idsdii", $crop_id, $quantity, $harvest_date, $price, $inventory_id, $farmer_id);
        $stmt->execute();
    }

    if ($_POST['action'] === 'delete') {
        $inventory_id = $_POST['inventory_id'];
        $stmt = $conn->prepare("DELETE FROM crops_inventory WHERE inventory_id = ? AND farmer_id = ?");
        $stmt->bind_param("ii", $inventory_id, $farmer_id);
        $stmt->execute();
    }

    header("Location: inventory.php");
    exit;
}

// Fetch all crops
$crops_stmt = $conn->query("SELECT crop_id, crop_name, unit FROM crops ORDER BY crop_name ASC");
$crops = $crops_stmt->fetch_all(MYSQLI_ASSOC);

// Fetch farmer inventory
$stmt = $conn->prepare("
    SELECT ci.inventory_id, ci.crop_id, ci.quantity, ci.harvest_date, ci.price, c.crop_name, c.unit
    FROM crops_inventory ci
    JOIN crops c ON ci.crop_id = c.crop_id
    WHERE ci.farmer_id = ?
    ORDER BY ci.created_at DESC
");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$result = $stmt->get_result();
$inventory = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Inventory - BANTAY-ANI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Simple styling for table and modals */
        body { font-family: Arial, sans-serif; background: #f6f6f6; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #eee; }
        button { padding: 6px 12px; margin: 2px; cursor: pointer; }
        .modal { display: none; position: fixed; inset:0; background: rgba(0,0,0,0.4); justify-content:center; align-items:center; }
        .modal-box { background:white; padding:20px; border-radius:10px; width: 320px; }
        .modal-box h3 { margin-bottom: 12px; }
        .modal-box input, .modal-box select { width:100%; padding:6px; margin-bottom:10px; }
    </style>
</head>
<body>
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory - BANTAY-ANI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="index.php" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Include the same admin styles as admin/users.php */
        @import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap');

        :root {
            --green-dark: #0c5c4c;
            --green: #1f8a70;
            --green-light: #4cb69f;
            --orange: #f28705;
            --red: #b91c1c;
            --blue: #1d4ed8;
            --purple: #7c3aed;
            --beige: #f6f1e9;
            --beige-light: #fcfaf6;
            --text: #1f2933;
            --text-light: #4c5662;
            --border: #e4e7eb;
            --sidebar-width: 260px;
            --shadow-sm: 0 2px 8px rgba(12, 92, 76, 0.08);
            --shadow-md: 0 8px 20px rgba(12, 92, 76, 0.12);
            --radius-sm: 12px;
            --radius-md: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Quicksand', 'Segoe UI', sans-serif; background: var(--beige-light); color: var(--text); display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar { width: var(--sidebar-width); background: white; border-right: 1px solid var(--border); position: fixed; height: 100vh; overflow-y: auto; box-shadow: var(--shadow-sm); }
        .logo-container { padding: 24px; border-bottom: 1px solid var(--border); }
        .logo { display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .logo-icon { width: 36px; height: 36px; background: linear-gradient(135deg, var(--green), var(--green-dark)); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.1rem; }
        .logo-text { font-size: 1.3rem; font-weight: 700; color: var(--green-dark); }
        .logo-text span { color: var(--orange); }
        .admin-badge, .farmer-badge { display: inline-block; background: rgba(12, 92, 76, 0.1); color: var(--green-dark); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; margin-left: 8px; }
        .nav-section { padding: 20px 0; }
        .nav-title { padding: 0 24px 12px; font-size: 0.85rem; color: var(--text-light); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .nav-links { list-style: none; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 14px 24px; color: var(--text); text-decoration: none; transition: all 0.2s; border-left: 3px solid transparent; }
        .nav-link:hover { background: rgba(12, 92, 76, 0.05); color: var(--green); }
        .nav-link.active { background: rgba(12, 92, 76, 0.1); color: var(--green); border-left-color: var(--green); }
        .nav-icon { font-size: 1.2rem; width: 24px; text-align: center; }

        /* Main Content */
        .main-content { flex: 1; margin-left: var(--sidebar-width); min-height: 100vh; }
        .topbar { background: white; padding: 20px 32px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .page-title h1 { font-size: 1.8rem; color: var(--text); }
        .page-title p { color: var(--text-light); margin-top: 4px; }

        /* Tables */
        .content { padding: 32px; }
        .table-card { background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); overflow: hidden; }
        .table-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .table-header h3 { font-size: 1.2rem; color: var(--text); }
        .view-all { color: var(--green); text-decoration: none; font-weight: 500; font-size: 0.95rem; }
        .view-all:hover { text-decoration: underline; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 16px 24px; color: var(--text-light); font-weight: 600; border-bottom: 2px solid var(--beige); }
        .data-table td { padding: 16px 24px; border-bottom: 1px solid var(--border); }
        .data-table tr:last-child td { border-bottom: none; }
        .icon-btn { border: none; background: none; cursor: pointer; font-size: 1.1rem; margin-right: 8px; }
        .edit-btn { color: #1d4ed8; }
        .delete-btn { color: #b91c1c; }
        .icon-btn:hover { transform: scale(1.15); }

        /* Modals */
        .modal { display: none; position: fixed; inset:0; background: rgba(0,0,0,0.4); justify-content:center; align-items:center; z-index: 999; }
        .modal-box { background:white; padding:24px; border-radius:14px; width: 320px; }
        .modal-box h3 { margin-bottom:12px; }
        .modal-box label { display:block; margin-top:12px; font-weight:600; }
        .modal-box input, .modal-box select { width:100%; padding:8px; margin-top:4px; }
        .confirm-btn { padding:8px 14px; border:none; border-radius:8px; cursor:pointer; margin-right:8px; }
        .confirm-btn.danger { background: #b91c1c; color:white; }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="logo-container">
        <a href="../index.php" class="logo">
            <div class="logo-icon">BA</div>
            <div class="logo-text">BANTAY<span>ANI</span></div>
        </a>
        <div class="farmer-badge">Farmer</div>
    </div>
    <div class="nav-section">
        <div class="nav-title">Main</div>
        <ul class="nav-links">
            <li><a href="inventory.php" class="nav-link active"><span class="nav-icon">🌾</span><span>Inventory</span></a></li>
            <li><a href="orders.php" class="nav-link"><span class="nav-icon">📦</span><span>Orders</span></a></li>
            <li><a href="profile.php" class="nav-link"><span class="nav-icon">👤</span><span>Profile</span></a></li>
        </ul>
    </div>
</aside>

<main class="main-content">
    <div class="topbar">
        <div class="page-title">
            <h1>Inventory Management</h1>
            <p>Manage your crops inventory</p>
        </div>
        <button onclick="openAddModal()" class="confirm-btn">Add Crop</button>
    </div>

    <div class="content">
        <div class="table-card">
            <div class="table-header">
                <h3>My Crops</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Crop</th>
                        <th>Quantity</th>
                        <th>Harvest Date</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($inventory)): ?>
                    <?php foreach ($inventory as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['crop_name']) ?></td>
                            <td><?= htmlspecialchars($item['quantity'].' '.$item['unit']) ?></td>
                            <td><?= htmlspecialchars($item['harvest_date']) ?></td>
                            <td><?= number_format($item['price'],2) ?></td>
                            <td>
                                <button class="icon-btn edit-btn" onclick="openEditModal(<?= $item['inventory_id'] ?>, <?= $item['crop_id'] ?>, <?= $item['quantity'] ?>, '<?= $item['harvest_date'] ?>', <?= $item['price'] ?>)"><i class="fa-solid fa-pen-to-square"></i></button>
                                <button class="icon-btn delete-btn" onclick="openDeleteModal(<?= $item['inventory_id'] ?>)"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:40px;">No crops found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Add/Edit/Delete Modals same as previous example -->
<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="modal-box">
        <h3>Add Crop</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <label>Crop</label>
            <select name="crop_id" required>
                <?php foreach ($crops as $crop): ?>
                    <option value="<?= $crop['crop_id'] ?>"><?= htmlspecialchars($crop['crop_name'].' ('.$crop['unit'].')') ?></option>
                <?php endforeach; ?>
            </select>
            <label>Quantity</label><input type="number" step="0.01" name="quantity" required>
            <label>Harvest Date</label><input type="date" name="harvest_date" required>
            <label>Price</label><input type="number" step="0.01" name="price" required>
            <div style="margin-top:10px;">
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
            <input type="hidden" name="inventory_id" id="editInventoryId">
            <label>Crop</label>
            <select name="crop_id" id="editCropId" required>
                <?php foreach ($crops as $crop): ?>
                    <option value="<?= $crop['crop_id'] ?>"><?= htmlspecialchars($crop['crop_name'].' ('.$crop['unit'].')') ?></option>
                <?php endforeach; ?>
            </select>
            <label>Quantity</label><input type="number" id="editQuantity" step="0.01" name="quantity" required>
            <label>Harvest Date</label><input type="date" id="editHarvestDate" name="harvest_date" required>
            <label>Price</label><input type="number" id="editPrice" step="0.01" name="price" required>
            <div style="margin-top:10px;">
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
            <input type="hidden" name="inventory_id" id="deleteInventoryId">
            <div style="margin-top:10px;">
                <button type="submit" class="confirm-btn danger">Delete</button>
                <button type="button" onclick="closeDeleteModal()" class="confirm-btn">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() { document.getElementById('addModal').style.display='flex'; }
function closeAddModal() { document.getElementById('addModal').style.display='none'; }

function openEditModal(id, crop_id, quantity, harvest_date, price) {
    document.getElementById('editInventoryId').value = id;
    document.getElementById('editCropId').value = crop_id;
    document.getElementById('editQuantity').value = quantity;
    document.getElementById('editHarvestDate').value = harvest_date;
    document.getElementById('editPrice').value = price;
    document.getElementById('editModal').style.display='flex';
}
function closeEditModal() { document.getElementById('editModal').style.display='none'; }

function openDeleteModal(id) { document.getElementById('deleteInventoryId').value = id; document.getElementById('deleteModal').style.display='flex'; }
function closeDeleteModal() { document.getElementById('deleteModal').style.display='none'; }
</script>
</body>
</html>
