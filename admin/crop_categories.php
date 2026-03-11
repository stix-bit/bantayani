<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Admin');
require_once '../includes/config.php';

/* =========================
   HANDLE ACTIONS
========================= */
if (isset($_POST['action'])) {

    if ($_POST['action'] === 'add' && isset($_POST['category_name'])) {
        $category_name = $_POST['category_name'];

        $stmt = $conn->prepare("INSERT INTO crop_categories (category_name) VALUES (?)");
        $stmt->bind_param("s", $category_name);
        $stmt->execute();
    }

    if ($_POST['action'] === 'edit' && isset($_POST['category_id'], $_POST['category_name'])) {
        $category_id = (int)$_POST['category_id'];
        $category_name = $_POST['category_name'];

        $stmt = $conn->prepare("UPDATE crop_categories SET category_name = ? WHERE category_id = ?");
        $stmt->bind_param("si", $category_name, $category_id);
        $stmt->execute();
    }

    if ($_POST['action'] === 'delete' && isset($_POST['category_id'])) {
        $category_id = (int)$_POST['category_id'];

        $stmt = $conn->prepare("DELETE FROM crop_categories WHERE category_id = ?");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
    }

    // Preserve filtering/sorting parameters
    $redirect_params = [];
    if (!empty($_POST['search'])) $redirect_params['search'] = $_POST['search'];
    if (!empty($_POST['sort'])) $redirect_params['sort'] = $_POST['sort'];

    header("Location: crop_categories.php" . (empty($redirect_params) ? '' : '?' . http_build_query($redirect_params)));
    exit;
}

/* =========================
   FETCH DATA
========================= */
// Filters & sorting
$filter_search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'name_asc';
$allowed_sort = ['name_asc', 'name_desc'];
if (!in_array($sort, $allowed_sort)) $sort = 'name_asc';

$sql = "SELECT * FROM crop_categories WHERE 1=1";
$params = []; $types = '';

if ($filter_search !== '') {
    $sql .= " AND category_name LIKE ?";
    $params[] = '%' . $filter_search . '%';
    $types .= 's';
}

// Sorting
switch ($sort) {
    case 'name_desc': $sql .= " ORDER BY category_name DESC"; break;
    default: $sql .= " ORDER BY category_name ASC"; break;
}

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Crop Categories Management - BANTAY-ANI</title>
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
                <li><a href="crop_categories.php" class="nav-link active"><span class="nav-icon">📁</span><span>Crop Categories</span></a></li>
                <li><a href="crops.php" class="nav-link"><span class="nav-icon">🌱</span><span>Crops</span></a></li>
                <li><a href="cooperative.php" class="nav-link"><span class="nav-icon">🤝</span><span>Cooperatives</span></a></li>
                <li><a href="benchmarking.php" class="nav-link"><span class="nav-icon">💰</span><span>Price Benchmarks</span></a></li>
            </ul>
        </div>
</aside>

<main class="main-content">
<div class="topbar">
    <div class="page-title">
        <h1>Crop Categories Management</h1>
        <p>View and manage crop categories</p>
    </div>
    <button onclick="openAddModal()" class="confirm-btn">Add Category</button>
</div>

<div class="content">
<div class="table-card">
<div class="table-header">
    <h3>All Categories</h3>
</div>

<!-- Filtering / Sorting Toolbar -->
<form method="GET" class="toolbar" style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:20px; padding:0 16px;">
    <label>Search:</label>
    <input type="text" name="search" placeholder="Category name..." value="<?= htmlspecialchars($filter_search) ?>">
    <label>Sort by:</label>
    <select name="sort">
        <option value="name_asc" <?= $sort=='name_asc'?'selected':'' ?>>Name (A–Z)</option>
        <option value="name_desc" <?= $sort=='name_desc'?'selected':'' ?>>Name (Z–A)</option>
    </select>
    <button type="submit" class="confirm-btn">Apply</button>
</form>

<table class="data-table">
<thead>
<tr>
    <th>Category Name</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php if(!empty($categories)): ?>
    <?php foreach($categories as $cat): ?>
        <tr>
            <td><?= htmlspecialchars($cat['category_name']) ?></td>
            <td>
                <button class="icon-btn edit-btn" onclick="openEditModal(<?= $cat['category_id'] ?>, '<?= htmlspecialchars($cat['category_name'], ENT_QUOTES) ?>')">
                    <i class="fa-solid fa-pen-to-square"></i></button>
                <button class="icon-btn delete-btn" onclick="openDeleteModal(<?= $cat['category_id'] ?>)">
                    <i class="fa-solid fa-trash"></i></button>
            </td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
<tr><td colspan="2" style="text-align:center; padding:40px;">No categories found.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</main>

<!-- ADD MODAL -->
<div id="addModal" class="modal">
<div class="modal-box">
<h3>Add Category</h3>
<form method="POST">
<input type="hidden" name="action" value="add">
<label>Category Name</label>
<input type="text" name="category_name" required>
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
<h3>Edit Category</h3>
<form method="POST">
<input type="hidden" name="action" value="edit">
<input type="hidden" name="category_id" id="editCategoryId">
<label>Category Name</label>
<input type="text" name="category_name" id="editCategoryName" required>
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
<p>Are you sure you want to delete this category?</p>
<form method="POST">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="category_id" id="deleteCategoryId">
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
function openEditModal(id,name){
    document.getElementById('editCategoryId').value=id;
    document.getElementById('editCategoryName').value=name;
    document.getElementById('editModal').style.display='flex';
}
function closeEditModal(){document.getElementById('editModal').style.display='none';}
function openDeleteModal(id){document.getElementById('deleteCategoryId').value=id;document.getElementById('deleteModal').style.display='flex';}
function closeDeleteModal(){document.getElementById('deleteModal').style.display='none';}
</script>

</body>
</html>