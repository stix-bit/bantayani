<?php
session_start();
require_once '../includes/config.php';

// Admin access check
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
//     header('Location: ../user/login.php');
//     exit;
// }

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

    header("Location: users.php");
    exit;
}

// Fetch users
$stmt = $conn->query("
    SELECT user_id, first_name, last_name, email, role, is_verified, created_at
    FROM users
    ORDER BY created_at DESC
");
$users = $stmt->fetch_all(MYSQLI_ASSOC);
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

        <form method="POST">
            <input type="hidden" name="user_id" id="deleteUserId">
            <input type="hidden" name="action" value="delete">
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

        <form method="POST">
            <input type="hidden" name="user_id" id="editUserId">
            <input type="hidden" name="action" value="change_role">

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
            <li><a href="verification.php" class="nav-link"><span class="nav-icon">✅</span><span>Verification</span></a></li>
            <li><a href="orders.php" class="nav-link"><span class="nav-icon">📦</span><span>Orders</span></a></li>
            <li><a href="reports.php" class="nav-link"><span class="nav-icon">📈</span><span>Reports</span></a></li>
        </ul>
    </div>

    <div class="nav-section">
            <div class="nav-title">Management</div>
            <ul class="nav-links">
                <li><a href="crops.php" class="nav-link"><span class="nav-icon">🌱</span><span>Crop Categories</span></a></li>
                <li><a href="pricing.php" class="nav-link"><span class="nav-icon">💰</span><span>Price Benchmarks</span></a></li>
                <li><a href="delivery.php" class="nav-link"><span class="nav-icon">🚚</span><span>Delivery Partners</span></a></li>
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
