<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Admin');
require_once __DIR__ . '/../includes/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$admin_id = $_SESSION['user_id'];
$errors = [];
$success = '';

/* ============================
   HANDLE VERIFICATION ACTIONS
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    $verification_id = $_POST['verification_id'];
    $action = $_POST['action'];
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    if ($action === 'approve' || $action === 'reject') {
        try {
            $conn->begin_transaction();
            
            /* ===== Update verification status ===== */
            $status = ($action === 'approve') ? 'Approved' : 'Rejected';
            $stmt = $conn->prepare("
                UPDATE farmer_verification 
                SET status = ?, reviewed_at = NOW(), reviewed_by = ?, admin_notes = ?
                WHERE verification_id = ?
            ");
            $stmt->bind_param('sisi', $status, $admin_id, $admin_notes, $verification_id);
            $stmt->execute();
            $stmt->close();
            
            /* ===== Get farmer_id for this verification ===== */
            $stmt = $conn->prepare("
                SELECT farmer_id FROM farmer_verification WHERE verification_id = ?
            ");
            $stmt->bind_param('i', $verification_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $farmer_data = $result->fetch_assoc();
            $stmt->close();
            
            if ($farmer_data) {
                $farmer_id = $farmer_data['farmer_id'];
                
                /* ===== Check if all certificates are approved ===== */
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as total, 
                           SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved
                    FROM farmer_verification 
                    WHERE farmer_id = ?
                ");
                $stmt->bind_param('i', $farmer_id);
                $stmt->execute();
                $cert_stats = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                /* ===== If all certificates are approved, verify the farmer ===== */
                if ($cert_stats['total'] > 0 && $cert_stats['total'] == $cert_stats['approved']) {
                    $stmt = $conn->prepare("
                        UPDATE users 
                        SET is_verified = 1 
                        WHERE user_id = ?
                    ");
                    $stmt->bind_param('i', $farmer_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    /* ===== Update farmer_profiles with verification info ===== */
                    $stmt = $conn->prepare("
                        UPDATE farmer_profiles 
                        SET verified_by = ?, verified_at = NOW()
                        WHERE farmer_id = ?
                    ");
                    $stmt->bind_param('ii', $admin_id, $farmer_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            $conn->commit();
            $success = "Certificate " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully!";
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

/* ============================
   FETCH PENDING VERIFICATIONS
============================ */
$pending_stmt = $conn->prepare("
    SELECT fv.*, 
           CONCAT(u.first_name, ' ', u.last_name) as farmer_name,
           u.email as farmer_email,
           u.contact_number,
           fp.farm_name,
           fp.farm_location
    FROM farmer_verification fv
    JOIN users u ON fv.farmer_id = u.user_id
    LEFT JOIN farmer_profiles fp ON fv.farmer_id = fp.farmer_id
    WHERE fv.status = 'Pending'
    ORDER BY fv.submitted_at ASC
");
$pending_stmt->execute();
$pending_verifications = $pending_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pending_stmt->close();

/* ============================
   FETCH ALL VERIFICATIONS HISTORY
============================ */
$all_stmt = $conn->prepare("
    SELECT fv.*, 
           CONCAT(u.first_name, ' ', u.last_name) as farmer_name,
           CONCAT(a.first_name, ' ', a.last_name) as admin_name,
           fp.farm_name
    FROM farmer_verification fv
    JOIN users u ON fv.farmer_id = u.user_id
    LEFT JOIN users a ON fv.reviewed_by = a.user_id
    LEFT JOIN farmer_profiles fp ON fv.farmer_id = fp.farmer_id
    ORDER BY fv.submitted_at DESC
    LIMIT 50
");
$all_stmt->execute();
$all_verifications = $all_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$all_stmt->close();

/* ============================
   FETCH VERIFICATION STATISTICS
============================ */
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_submissions,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM farmer_verification
");
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BantayAni | Farmer Verification</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600&display=swap');
        
        :root {
            --green-dark: #0c5c4c;
            --green: #1f8a70;
            --beige: #f6f1e9;
            --text: #1f2933;
            --error: #b91c1c;
            --warning: #f59e0b;
            --success: #10b981;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            font-family: 'Quicksand', 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, rgba(12, 92, 76, 0.08), rgba(242, 135, 5, 0.15));
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(12, 92, 76, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--green-dark);
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 25px;
            transition: background 0.3s ease;
        }
        
        .nav-links a:hover {
            background: rgba(31, 138, 112, 0.1);
        }
        
        .nav-links a.active {
            background: var(--green);
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(12, 92, 76, 0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 600;
            color: var(--green-dark);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(12, 92, 76, 0.1);
            margin-bottom: 30px;
        }
        
        .card h2 {
            color: var(--green-dark);
            margin-top: 0;
            margin-bottom: 20px;
        }
        
        .verification-item {
            background: rgba(31, 138, 112, 0.05);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border-left: 4px solid var(--green);
        }
        
        .verification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .farmer-info h3 {
            margin: 0 0 10px 0;
            color: var(--green-dark);
        }
        
        .farmer-info p {
            margin: 5px 0;
            color: #666;
        }
        
        .certificate-info {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .certificate-info h4 {
            margin: 0 0 15px 0;
            color: var(--green-dark);
        }
        
        .certificate-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-item strong {
            color: var(--text);
            margin-bottom: 5px;
        }
        
        .detail-item span {
            color: #666;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            min-width: 100px;
        }
        
        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .status-approved {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-rejected {
            background: rgba(185, 28, 28, 0.1);
            color: var(--error);
        }
        
        .verification-actions {
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: var(--green);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
            background: var(--green-dark);
        }
        
        .btn-danger {
            background: var(--error);
        }
        
        .btn-danger:hover {
            background: #991b1b;
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: var(--text);
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text);
        }
        
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
        }
        
        .action-form {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: rgba(185, 28, 28, 0.1);
            color: var(--error);
            border: 1px solid rgba(185, 28, 28, 0.3);
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .certificate-link {
            color: var(--green);
            text-decoration: none;
            font-weight: 600;
        }
        
        .certificate-link:hover {
            text-decoration: underline;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            color: #666;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            color: var(--green-dark);
            border-bottom-color: var(--green);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .verification-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .certificate-details {
                grid-template-columns: 1fr;
            }
            
            .verification-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0; color: var(--green-dark);">BantayAni Admin Portal</h1>
            <div class="nav-links">
                <a href="index.php">Dashboard</a>
                <a href="users.php">Users</a>
                <a href="crops.php">Crops</a>
                <a href="verify_farmers.php" class="active">Verification</a>
                <a href="/bantayani/user/logout.php">Logout</a>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_submissions'] ?></div>
                <div class="stat-label">Total Submissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['approved'] ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['rejected'] ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="card">
            <h2>Farmer Verification Management</h2>
            
            <div class="tabs">
                <button class="tab active" onclick="showTab('pending')">Pending Review (<?= count($pending_verifications) ?>)</button>
                <button class="tab" onclick="showTab('history')">All History</button>
            </div>

            <!-- Pending Review Tab -->
            <div id="pending" class="tab-content active">
                <?php if (empty($pending_verifications)): ?>
                    <p style="text-align: center; color: #666; padding: 40px 0;">
                        No pending verifications at the moment.
                    </p>
                <?php else: ?>
                    <?php foreach ($pending_verifications as $verification): ?>
                        <div class="verification-item">
                            <div class="verification-header">
                                <div class="farmer-info">
                                    <h3><?= htmlspecialchars($verification['farmer_name']) ?></h3>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($verification['farmer_email']) ?></p>
                                    <p><strong>Contact:</strong> <?= htmlspecialchars($verification['contact_number']) ?></p>
                                    <?php if ($verification['farm_name']): ?>
                                        <p><strong>Farm:</strong> <?= htmlspecialchars($verification['farm_name']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($verification['farm_location']): ?>
                                        <p><strong>Location:</strong> <?= htmlspecialchars($verification['farm_location']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="verification-actions">
                                    <div>
                                        <span class="status-badge status-pending">Pending</span>
                                    </div>
                                </div>
                            </div>

                            <div class="certificate-info">
                                <h4><?= htmlspecialchars($verification['certificate_name']) ?></h4>
                                <div class="certificate-details">
                                    <div class="detail-item">
                                        <strong>Type:</strong>
                                        <span><?= htmlspecialchars($verification['certificate_type']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <strong>Submitted:</strong>
                                        <span><?= date('M d, Y h:i A', strtotime($verification['submitted_at'])) ?></span>
                                    </div>
                                    <div class="detail-item" style="grid-column: 1 / -1;">
                                        <strong>Certificate:</strong>
                                        <span>
                                            <a href="<?= htmlspecialchars('/bantayani/' . ltrim($verification['certificate_path'], '/')) ?>" 
                                               target="_blank" class="certificate-link">View Document</a>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="action-form">
                                <form method="POST" onsubmit="return confirm('Are you sure you want to approve this certificate?');">
                                    <input type="hidden" name="verification_id" value="<?= $verification['verification_id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    
                                    <div class="form-group">
                                        <label for="notes_approve_<?= $verification['verification_id'] ?>">Admin Notes (Optional)</label>
                                        <textarea name="admin_notes" id="notes_approve_<?= $verification['verification_id'] ?>" 
                                                  placeholder="Add any notes for the farmer..."></textarea>
                                    </div>
                                    
                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" class="btn">Approve Certificate</button>
                                        <button type="button" class="btn btn-danger" onclick="showRejectForm(<?= $verification['verification_id'] ?>)">Reject</button>
                                    </div>
                                </form>

                                <form method="POST" id="reject_form_<?= $verification['verification_id'] ?>" style="display: none; margin-top: 15px;" onsubmit="return confirm('Are you sure you want to reject this certificate?');">
                                    <input type="hidden" name="verification_id" value="<?= $verification['verification_id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    
                                    <div class="form-group">
                                        <label for="notes_reject_<?= $verification['verification_id'] ?>">Rejection Reason *</label>
                                        <textarea name="admin_notes" id="notes_reject_<?= $verification['verification_id'] ?>" 
                                                  placeholder="Please provide a reason for rejection..." required></textarea>
                                    </div>
                                    
                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                                        <button type="button" class="btn btn-secondary" onclick="hideRejectForm(<?= $verification['verification_id'] ?>)">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- History Tab -->
            <div id="history" class="tab-content">
                <?php if (empty($all_verifications)): ?>
                    <p style="text-align: center; color: #666; padding: 40px 0;">
                        No verification history found.
                    </p>
                <?php else: ?>
                    <?php foreach ($all_verifications as $verification): ?>
                        <div class="verification-item">
                            <div class="verification-header">
                                <div class="farmer-info">
                                    <h3><?= htmlspecialchars($verification['farmer_name']) ?></h3>
                                    <p><strong>Certificate:</strong> <?= htmlspecialchars($verification['certificate_name']) ?></p>
                                    <p><strong>Type:</strong> <?= htmlspecialchars($verification['certificate_type']) ?></p>
                                    <?php if ($verification['farm_name']): ?>
                                        <p><strong>Farm:</strong> <?= htmlspecialchars($verification['farm_name']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="status-badge status-<?= strtolower($verification['status']) ?>">
                                        <?= htmlspecialchars($verification['status']) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="certificate-details" style="margin-top: 15px;">
                                <div class="detail-item">
                                    <strong>Submitted:</strong>
                                    <span><?= date('M d, Y h:i A', strtotime($verification['submitted_at'])) ?></span>
                                </div>
                                <?php if ($verification['reviewed_at']): ?>
                                    <div class="detail-item">
                                        <strong>Reviewed:</strong>
                                        <span><?= date('M d, Y h:i A', strtotime($verification['reviewed_at'])) ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($verification['admin_name']): ?>
                                    <div class="detail-item">
                                        <strong>Reviewed By:</strong>
                                        <span><?= htmlspecialchars($verification['admin_name']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <strong>Certificate:</strong>
                                    <span>
                                        <a href="<?= htmlspecialchars('/bantayani/' . ltrim($verification['certificate_path'], '/')) ?>" 
                                           target="_blank" class="certificate-link">View Document</a>
                                    </span>
                                </div>
                                <?php if ($verification['admin_notes']): ?>
                                    <div class="detail-item" style="grid-column: 1 / -1;">
                                        <strong>Admin Notes:</strong>
                                        <span><?= htmlspecialchars($verification['admin_notes']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        function showRejectForm(verificationId) {
            document.getElementById('reject_form_' + verificationId).style.display = 'block';
        }

        function hideRejectForm(verificationId) {
            document.getElementById('reject_form_' + verificationId).style.display = 'none';
        }
    </script>
</body>
</html>
