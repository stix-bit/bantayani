<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Admin');
require_once '../includes/config.php';

/* =========================
   HANDLE ACTIONS
========================= */
if (isset($_POST['action'])) {

    if ($_POST['action'] === 'add' && isset($_POST['crop_name'], $_POST['unit'], $_POST['category_id'])) {
        $crop_name = $_POST['crop_name'];
        $unit = $_POST['unit'];
        $category_id = (int)$_POST['category_id'];

        $stmt = $conn->prepare("INSERT INTO crops (category_id, crop_name, unit) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $category_id, $crop_name, $unit);
        $stmt->execute();
    }

    if ($_POST['action'] === 'edit' && isset($_POST['crop_id'], $_POST['crop_name'], $_POST['unit'], $_POST['category_id'])) {
        $crop_id = (int)$_POST['crop_id'];
        $crop_name = $_POST['crop_name'];
        $unit = $_POST['unit'];
        $category_id = (int)$_POST['category_id'];

        $stmt = $conn->prepare("UPDATE crops SET category_id = ?, crop_name = ?, unit = ? WHERE crop_id = ?");
        $stmt->bind_param("issi", $category_id, $crop_name, $unit, $crop_id);
        $stmt->execute();
    }

    if ($_POST['action'] === 'delete' && isset($_POST['crop_id'])) {
        $crop_id = (int)$_POST['crop_id'];

        // Soft delete crop
        $stmt = $conn->prepare("UPDATE crops SET deleted_at = NOW() WHERE crop_id = ?");
        $stmt->bind_param("i", $crop_id);
        $stmt->execute();
    }

    if ($_POST['action'] === 'restore' && isset($_POST['crop_id'])) {
        $crop_id = (int)$_POST['crop_id'];

        $stmt = $conn->prepare("UPDATE crops SET deleted_at = NULL WHERE crop_id = ?");
        $stmt->bind_param("i", $crop_id);
        $stmt->execute();
    }

    // Preserve filtering/sorting parameters on redirect
    $redirect_params = [];
    if (!empty($_POST['category'])) $redirect_params['category'] = $_POST['category'];
    if (!empty($_POST['unit'])) $redirect_params['unit'] = $_POST['unit'];
    if (!empty($_POST['sort'])) $redirect_params['sort'] = $_POST['sort'];
    if (!empty($_POST['search'])) $redirect_params['search'] = $_POST['search'];

    header("Location: crops.php" . (empty($redirect_params) ? '' : '?' . http_build_query($redirect_params)));
    exit;
}

/* =========================
   FETCH DATA
========================= */
// Only allow selecting non-archived crop categories in forms/filters
$categories = $conn->query("SELECT * FROM crop_categories WHERE deleted_at IS NULL ORDER BY category_name ASC")->fetch_all(MYSQLI_ASSOC);

// Filters & sorting
$filter_category = $_GET['category'] ?? '';
$filter_unit = $_GET['unit'] ?? '';
$filter_search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'name_asc';
$allowed_sort = ['name_asc','name_desc','category','unit'];
if (!in_array($sort,$allowed_sort)) $sort='name_asc';

// Build query dynamically (include both active and archived crops/categories)
$sql = "SELECT c.crop_id, c.crop_name, c.unit, c.category_id, cc.category_name, c.deleted_at 
        FROM crops c 
        LEFT JOIN crop_categories cc ON c.category_id = cc.category_id
        WHERE 1=1";
$params = []; $types = '';

if ($filter_category !== '') {
    $sql .= " AND c.category_id = ?";
    $params[] = $filter_category;
    $types .= 'i';
}
if ($filter_unit !== '') {
    $sql .= " AND c.unit = ?";
    $params[] = $filter_unit;
    $types .= 's';
}
if ($filter_search !== '') {
    $sql .= " AND c.crop_name LIKE ?";
    $params[] = '%'.$filter_search.'%';
    $types .= 's';
}

// Sorting
switch ($sort) {
    case 'name_desc': $sql .= " ORDER BY c.crop_name DESC"; break;
    case 'category':  $sql .= " ORDER BY cc.category_name ASC, c.crop_name ASC"; break;
    case 'unit':      $sql .= " ORDER BY c.unit ASC, c.crop_name ASC"; break;
    default:          $sql .= " ORDER BY c.crop_name ASC"; break;
}

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$crops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Crops Management - BANTAY-ANI</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
<?php include '../includes/styles/admin_style.css'; ?>
</style>
</head>

<body>
<aside class="sidebar">
    <div class="logo-container">
        <a href="index.php" class="logo">
            <div class="logo-icon">BA</div>
            <div class="logo-text">BANTAY<span>ANI</span></div>
        </a>
        <div class="admin-badge">Admin</div>
    </div>

    <div class="nav-section">
            <div class="nav-title">Main</div>
            <ul class="nav-links">
                <li><a href="index.php" class="nav-link"><span class="nav-icon">📊</span><span>Dashboard</span></a></li>
                <li><a href="verify_farmers.php" class="nav-link"><span class="nav-icon">✅</span><span>Certificate Verification</span></a></li>
                <li><a href="announcements.php" class="nav-link"><span class="nav-icon">📢</span><span>Announcements</span></a></li>
                <li><a href="../reports.php" class="nav-link"><span class="nav-icon">📈</span><span>Reports</span></a></li>
            </ul>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Management</div>
            <ul class="nav-links">
                <li><a href="users.php" class="nav-link"><span class="nav-icon">👥</span><span>Users</span></a></li>
                <li><a href="orders.php" class="nav-link"><span class="nav-icon">📦</span><span>Orders</span></a></li>
                <li><a href="crop_categories.php" class="nav-link"><span class="nav-icon">📁</span><span>Crop Categories</span></a></li>
                <li><a href="crops.php" class="nav-link active"><span class="nav-icon">🌱</span><span>Crops</span></a></li>
                <li><a href="cooperative.php" class="nav-link"><span class="nav-icon">🤝</span><span>Cooperatives</span></a></li>
                <li><a href="benchmarking.php" class="nav-link"><span class="nav-icon">💰</span><span>Price Benchmarks</span></a></li>
            </ul>
        </div>
</aside>

<main class="main-content">
<div class="topbar">
    <div class="page-title">
        <h1>Crops Management</h1>
        <p>Manage all crop types and categories</p>
    </div>
    <button onclick="openAddModal()" class="confirm-btn">Add Crop</button>
</div>

<div class="content">
<div class="table-card">
<div class="table-header">
    <h3>All Crops</h3>
</div>

<!-- Filtering / Sorting Toolbar -->
<form method="GET" class="toolbar" style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:20px; padding:0 16px;">
    <label>Category:</label>
    <select name="category">
        <option value="">All</option>
        <?php foreach($categories as $cat): ?>
            <option value="<?= $cat['category_id'] ?>" <?= $filter_category == $cat['category_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['category_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Unit:</label>
    <select name="unit">
        <option value="">All</option>
        <?php foreach(['kg','g','pieces','sack','bundle'] as $unit): ?>
            <option value="<?= $unit ?>" <?= $filter_unit==$unit?'selected':'' ?>><?= $unit ?></option>
        <?php endforeach; ?>
    </select>

    <label>Search:</label>
    <input type="text" name="search" placeholder="Crop name..." value="<?= htmlspecialchars($filter_search) ?>">

    <label>Sort by:</label>
    <select name="sort">
        <option value="name_asc" <?= $sort=='name_asc'?'selected':'' ?>>Crop Name (A–Z)</option>
        <option value="name_desc" <?= $sort=='name_desc'?'selected':'' ?>>Crop Name (Z–A)</option>
        <option value="category" <?= $sort=='category'?'selected':'' ?>>Category</option>
        <option value="unit" <?= $sort=='unit'?'selected':'' ?>>Unit</option>
    </select>

    <button type="submit" class="confirm-btn">Apply</button>
</form>

<table class="data-table">
<thead>
<tr>
    <th>Crop Name</th>
    <th>Category</th>
    <th>Unit</th>
    <th>Status</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php if(!empty($crops)): ?>
    <?php foreach($crops as $crop): ?>
        <?php $is_archived = !empty($crop['deleted_at']); ?>
        <tr>
            <td><?= htmlspecialchars($crop['crop_name']) ?></td>
            <td><?= htmlspecialchars($crop['category_name'] ?? 'Uncategorized') ?></td>
            <td><?= htmlspecialchars($crop['unit']) ?></td>
            <td>
                <?php if ($is_archived): ?>
                    <span class="status-badge status-pending" style="background:#fee2e2;color:#b91c1c;">
                        ARCHIVED
                    </span>
                <?php else: ?>
                    <span class="status-badge status-confirmed">
                        Active
                    </span>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!$is_archived): ?>
                    <button class="icon-btn edit-btn" onclick="openEditModal(
                        <?= $crop['crop_id'] ?>,
                        '<?= htmlspecialchars($crop['crop_name'], ENT_QUOTES) ?>',
                        '<?= $crop['unit'] ?>',
                        <?= $crop['category_id'] ?>
                    )"><i class="fa-solid fa-pen-to-square"></i></button>
                    <button class="icon-btn delete-btn" onclick="openDeleteModal(<?= $crop['crop_id'] ?>)">
                        <i class="fa-solid fa-trash"></i></button>
                <?php else: ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="restore">
                        <input type="hidden" name="crop_id" value="<?= $crop['crop_id'] ?>">
                        <button type="submit" class="icon-btn edit-btn" title="Restore crop">
                            <i class="fa-solid fa-rotate-left"></i>
                        </button>
                    </form>
                <?php endif; ?>
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

<!-- MODALS (Add/Edit/Delete) -->
<!-- ADD MODAL -->
<div id="addModal" class="modal">
<div class="modal-box">
<h3>Add Crop</h3>
<form method="POST">
<input type="hidden" name="action" value="add">
<label>Category</label>
<select name="category_id" required>
<?php foreach($categories as $cat): ?>
<option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
<?php endforeach; ?>
</select>
<label>Crop Name</label>
<input type="text" name="crop_name" required>
<label>Unit</label>
<select name="unit" required>
<option value="kg">kg</option>
<option value="g">g</option>
<option value="pieces">pieces</option>
<option value="sack">sack</option>
<option value="bundle">bundle</option>
</select>
<div style="margin-top:15px;">
<button type="submit" class="confirm-btn">Add</button>
<button type="button" onclick="closeAddModal()" class="confirm-btn">Cancel</button>
</div>
</form>
</div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="modal">
<div class="modal-box">
<h3>Edit Crop</h3>
<form method="POST">
<input type="hidden" name="action" value="edit">
<input type="hidden" name="crop_id" id="editCropId">
<label>Category</label>
<select name="category_id" id="editCategoryId" required>
<?php foreach($categories as $cat): ?>
<option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
<?php endforeach; ?>
</select>
<label>Crop Name</label>
<input type="text" name="crop_name" id="editCropName" required>
<label>Unit</label>
<select name="unit" id="editUnit" required>
<option value="kg">kg</option>
<option value="g">g</option>
<option value="pieces">pieces</option>
<option value="sack">sack</option>
<option value="bundle">bundle</option>
</select>
<div style="margin-top:15px;">
<button type="submit" class="confirm-btn">Save</button>
<button type="button" onclick="closeEditModal()" class="confirm-btn">Cancel</button>
</div>
</form>
</div>
</div>

<!-- DELETE MODAL -->
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
function openAddModal(){document.getElementById('addModal').style.display='flex';}
function closeAddModal(){document.getElementById('addModal').style.display='none';}
function openEditModal(id,name,unit,category_id){
    document.getElementById('editCropId').value=id;
    document.getElementById('editCropName').value=name;
    document.getElementById('editUnit').value=unit;
    document.getElementById('editCategoryId').value=category_id;
    document.getElementById('editModal').style.display='flex';
}
function closeEditModal(){document.getElementById('editModal').style.display='none';}
function openDeleteModal(id){document.getElementById('deleteCropId').value=id;document.getElementById('deleteModal').style.display='flex';}
function closeDeleteModal(){document.getElementById('deleteModal').style.display='none';}
</script>
</body>
</html>