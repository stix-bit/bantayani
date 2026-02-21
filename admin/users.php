<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Admin');
require_once '../includes/config.php';

// Handle actions
if (isset($_POST['action'], $_POST['user_id'])) {
    $user_id = (int) $_POST['user_id'];

    if ($_POST['action'] === 'verify') {
        $stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }

    if ($_POST['action'] === 'unverify') {
        $stmt = $conn->prepare("UPDATE users SET is_verified = 0 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }

    if ($_POST['action'] === 'delete') {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }

    if ($_POST['action'] === 'change_role' && isset($_POST['role'])) {
        $role = $_POST['role'];
        $is_verified = isset($_POST['is_verified']) ? (int)$_POST['is_verified'] : 0;
        $stmt = $conn->prepare("UPDATE users SET role = ?, is_verified = ? WHERE user_id = ?");
        $stmt->bind_param("sii", $role, $is_verified, $user_id);
        $stmt->execute();
    }

    $redirect_params = [];
    $src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    if (!empty($src['role'])) $redirect_params['role'] = $src['role'];
    if (isset($src['verified']) && $src['verified'] !== '') $redirect_params['verified'] = $src['verified'];
    if (!empty($src['sort'])) $redirect_params['sort'] = $src['sort'];
    if (!empty($src['search'])) $redirect_params['search'] = $src['search'];
    header("Location: users.php" . (empty($redirect_params) ? '' : '?' . http_build_query($redirect_params)));
    exit;
}

// Filter & sort from GET
$filter_role = isset($_GET['role']) ? trim($_GET['role']) : '';
$filter_verified = isset($_GET['verified']) ? trim($_GET['verified']) : '';
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'joined_desc';
$allowed_sort = ['joined_desc', 'joined_asc', 'name_asc', 'name_desc', 'email_asc', 'email_desc', 'role', 'verified'];
if (!in_array($sort, $allowed_sort)) $sort = 'joined_desc';

// Build query
$sql = "SELECT user_id, first_name, last_name, email, role, is_verified, created_at FROM users WHERE 1=1";
$params = [];
$types = '';
if ($filter_role !== '') {
    $sql .= " AND role = ?";
    $params[] = $filter_role;
    $types .= 's';
}
if ($filter_verified !== '') {
    $sql .= " AND is_verified = ?";
    $params[] = ($filter_verified === '1' ? 1 : 0);
    $types .= 'i';
}
if ($filter_search !== '') {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $term = '%' . $filter_search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= 'sss';
}
switch ($sort) {
    case 'joined_asc':  $sql .= " ORDER BY created_at ASC"; break;
    case 'name_asc':    $sql .= " ORDER BY first_name ASC, last_name ASC"; break;
    case 'name_desc':   $sql .= " ORDER BY first_name DESC, last_name DESC"; break;
    case 'email_asc':   $sql .= " ORDER BY email ASC"; break;
    case 'email_desc':  $sql .= " ORDER BY email DESC"; break;
    case 'role':        $sql .= " ORDER BY role ASC, created_at DESC"; break;
    case 'verified':    $sql .= " ORDER BY is_verified DESC, created_at DESC"; break;
    default:            $sql .= " ORDER BY created_at DESC";
}

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - BANTAY-ANI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="index.php" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        <?php include '../includes/styles/admin_style.css'; // put your existing CSS in a separate file for reuse ?>
    </style>
</head>

<script>
function openDeleteModal(userId) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function openEditModal(id, role, verified) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editRole').value = role;
    document.getElementById('editVerified').value = verified;
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>


<!-- Delete Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-box">
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete this user?</p>

        <form method="POST" id="deleteForm">
            <input type="hidden" name="user_id" id="deleteUserId">
            <input type="hidden" name="action" value="delete">
            <?php if ($filter_role !== ''): ?><input type="hidden" name="role" value="<?= htmlspecialchars($filter_role) ?>"><?php endif; ?>
            <?php if ($filter_verified !== ''): ?><input type="hidden" name="verified" value="<?= htmlspecialchars($filter_verified) ?>"><?php endif; ?>
            <?php if ($sort !== 'joined_desc'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>
            <?php if ($filter_search !== ''): ?><input type="hidden" name="search" value="<?= htmlspecialchars($filter_search) ?>"><?php endif; ?>
            <br>
            <button type="submit" class="confirm-btn danger">Delete</button>
            <button type="button" onclick="closeDeleteModal()" class="confirm-btn">Cancel</button>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-box">
        <h3>Edit User</h3>

        <form method="POST" id="editForm">
            <input type="hidden" name="user_id" id="editUserId">
            <input type="hidden" name="action" value="change_role">
            <?php if ($filter_role !== ''): ?><input type="hidden" name="role" value="<?= htmlspecialchars($filter_role) ?>"><?php endif; ?>
            <?php if ($filter_verified !== ''): ?><input type="hidden" name="verified" value="<?= htmlspecialchars($filter_verified) ?>"><?php endif; ?>
            <?php if ($sort !== 'joined_desc'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>
            <?php if ($filter_search !== ''): ?><input type="hidden" name="search" value="<?= htmlspecialchars($filter_search) ?>"><?php endif; ?>

            <label>Role</label>
            <select name="role" id="editRole" required>
                <option value="Farmer">Farmer</option>
                <option value="Buyer">Buyer</option>
                <option value="Admin">Admin</option>
            </select>

            <label>Verified</label>
            <select id="editVerified" name="is_verified">
                <option value="1">Verified</option>
                <option value="0">Pending</option>
            </select>

            <div style="margin-top:15px;">
                <button type="submit" class="confirm-btn">Save</button>
                <button type="button" onclick="closeEditModal()" class="confirm-btn">Cancel</button>
            </div>
        </form>
    </div>
</div>

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
            <li><a href="users.php" class="nav-link active"><span class="nav-icon">👥</span><span>Users</span></a></li>
            <li><a href="verify_farmers.php" class="nav-link"><span class="nav-icon">✅</span><span>Verification</span></a></li>
            <li><a href="orders.php" class="nav-link"><span class="nav-icon">📦</span><span>Orders</span></a></li>
            <li><a href="reports.php" class="nav-link"><span class="nav-icon">📈</span><span>Reports</span></a></li>
        </ul>
    </div>

    <div class="nav-section">
            <div class="nav-title">Management</div>
            <ul class="nav-links">
                <li><a href="crops.php" class="nav-link"><span class="nav-icon">🌱</span><span>Crop Categories</span></a></li>
                <li><a href="cooperative.php" class="nav-link"><span class="nav-icon">🤝</span><span>Cooperatives</span></a></li>
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
            <h1>Users Management</h1>
            <p>View and manage all system users</p>
        </div>
    </div>

    <div class="content">
        <div class="table-card">
            <div class="table-header">
                <h3>All Users</h3>
            </div>

            <form method="GET" class="toolbar" style="display:flex; flex-wrap:wrap; gap:16px; align-items:center; margin-bottom:20px; padding: 0 16px;">
                <label style="font-weight:600; color:var(--text-light);">Role:</label>
                <select name="role" style="padding:8px 12px; border:1px solid var(--border); border-radius:var(--radius-sm); min-width:120px;">
                    <option value="">All</option>
                    <option value="Farmer" <?= $filter_role === 'Farmer' ? 'selected' : '' ?>>Farmer</option>
                    <option value="Buyer"  <?= $filter_role === 'Buyer'  ? 'selected' : '' ?>>Buyer</option>
                    <option value="Admin"  <?= $filter_role === 'Admin'  ? 'selected' : '' ?>>Admin</option>
                </select>
                <label style="font-weight:600; color:var(--text-light);">Verified:</label>
                <select name="verified" style="padding:8px 12px; border:1px solid var(--border); border-radius:var(--radius-sm); min-width:120px;">
                    <option value="">All</option>
                    <option value="1" <?= $filter_verified === '1' ? 'selected' : '' ?>>Verified</option>
                    <option value="0" <?= $filter_verified === '0' ? 'selected' : '' ?>>Pending</option>
                </select>
                <label style="font-weight:600; color:var(--text-light);">Search:</label>
                <input type="text" name="search" value="<?= htmlspecialchars($filter_search) ?>" placeholder="Name or email..." style="padding:8px 12px; border:1px solid var(--border); border-radius:var(--radius-sm); min-width:180px;">
                <label style="font-weight:600; color:var(--text-light);">Sort by:</label>
                <select name="sort" style="padding:8px 12px; border:1px solid var(--border); border-radius:var(--radius-sm); min-width:160px;">
                    <option value="joined_desc" <?= $sort === 'joined_desc' ? 'selected' : '' ?>>Joined (newest first)</option>
                    <option value="joined_asc"  <?= $sort === 'joined_asc'  ? 'selected' : '' ?>>Joined (oldest first)</option>
                    <option value="name_asc"    <?= $sort === 'name_asc'   ? 'selected' : '' ?>>Name (A–Z)</option>
                    <option value="name_desc"   <?= $sort === 'name_desc'   ? 'selected' : '' ?>>Name (Z–A)</option>
                    <option value="email_asc"  <?= $sort === 'email_asc'  ? 'selected' : '' ?>>Email (A–Z)</option>
                    <option value="email_desc" <?= $sort === 'email_desc' ? 'selected' : '' ?>>Email (Z–A)</option>
                    <option value="role"       <?= $sort === 'role'       ? 'selected' : '' ?>>Role</option>
                    <option value="verified"   <?= $sort === 'verified'   ? 'selected' : '' ?>>Verified first</option>
                </select>
                <button type="submit" style="padding:8px 16px; background:var(--green); color:white; border:none; border-radius:var(--radius-sm); font-weight:600; cursor:pointer;">Apply</button>
            </form>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Verified</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['role']) ?></td>
                            <td>
                                <?= $user['is_verified'] ? 
                                    '<span class="status-badge status-confirmed">Verified</span>' :
                                    '<span class="status-badge status-pending">Pending</span>' ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <button class="icon-btn edit-btn"
                                    onclick="openEditModal(
                                        <?= $user['user_id'] ?>,
                                        '<?= $user['role'] ?>',
                                        <?= $user['is_verified'] ?>
                                    )">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>

                                <button class="icon-btn delete-btn"
                                    onclick="openDeleteModal(<?= $user['user_id'] ?>)">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:40px;">
                            No users found.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

</body>
</html>
