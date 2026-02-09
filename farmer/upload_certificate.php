<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Farmer');
require_once __DIR__ . '/../includes/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

/* ============================
   HANDLE FORM SUBMISSION
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $certificate_type = $_POST['certificate_type'];
    $certificate_name = trim($_POST['certificate_name']);
    
    if (empty($certificate_name)) {
        $errors[] = 'Certificate name is required.';
    }
    
    /* ===== Certificate Upload ===== */
    if (empty($_FILES['certificate']['name']) || $_FILES['certificate']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please select a certificate file to upload.';
    } else {
        $tmp = $_FILES['certificate']['tmp_name'];
        $original_name = $_FILES['certificate']['name'];
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Invalid file type. Allowed types: PDF, JPG, PNG, DOC, DOCX';
        } elseif ($_FILES['certificate']['size'] > 5 * 1024 * 1024) { // 5MB limit
            $errors[] = 'File size must be less than 5MB.';
        } else {
            $uploadDir = __DIR__ . '/../uploads/certificates';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $newName = 'certificate_' . $user_id . '_' . uniqid() . '.' . $ext;
            $destination = $uploadDir . '/' . $newName;
            
            if (move_uploaded_file($tmp, $destination)) {
                $certificate_path = 'uploads/certificates/' . $newName;
            } else {
                $errors[] = 'Failed to upload certificate file.';
            }
        }
    }
    
    if (empty($errors)) {
        try {
            /* ===== Insert verification record ===== */
            $stmt = $conn->prepare("
                INSERT INTO farmer_verification 
                (farmer_id, certificate_type, certificate_name, certificate_path, status)
                VALUES (?, ?, ?, ?, 'Pending')
            ");
            $stmt->bind_param('isss', $user_id, $certificate_type, $certificate_name, $certificate_path);
            $stmt->execute();
            $stmt->close();
            
            $success = 'Certificate uploaded successfully! It will be reviewed by the admin.';
            
            // Clear form data
            $_POST = [];
            
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

/* ============================
   FETCH EXISTING CERTIFICATES
============================ */
$stmt = $conn->prepare("
    SELECT verification_id, certificate_type, certificate_name, 
           certificate_path, status, submitted_at, reviewed_at, admin_notes
    FROM farmer_verification
    WHERE farmer_id = ?
    ORDER BY submitted_at DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$certificates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BantayAni | Upload Certificate</title>
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
            max-width: 800px;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text);
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 16px;
        }
        
        .form-group input[type="file"] {
            padding: 10px;
            border: 2px dashed #d1d5db;
            background: #f9fafb;
        }
        
        .file-info {
            margin-top: 10px;
            font-size: 14px;
            color: #666;
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
        
        .btn-secondary {
            background: #e5e7eb;
            color: var(--text);
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
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
        
        .certificates-list {
            margin-top: 30px;
        }
        
        .certificate-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: rgba(31, 138, 112, 0.05);
            border-radius: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--green);
        }
        
        .certificate-info h4 {
            margin: 0 0 5px 0;
            color: var(--green-dark);
        }
        
        .certificate-info p {
            margin: 5px 0;
            color: #666;
            font-size: 14px;
        }
        
        .certificate-status {
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
        
        .certificate-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 15px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--green-dark);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 20px;
            }
            
            .certificate-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .certificate-actions {
                width: 100%;
            }
            
            .btn-small {
                flex: 1;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0; color: var(--green-dark);">BantayAni Farmer Portal</h1>
            <div class="nav-links">
                <a href="index.php">Dashboard</a>
                <a href="inventory.php">Inventory</a>
                <a href="orders.php">Orders</a>
                <a href="profile.php">Profile</a>
                <a href="/bantayani/user/logout.php">Logout</a>
            </div>
        </div>

        <a href="profile.php" class="back-link">← Back to Profile</a>

        <div class="card">
            <h2>Upload Verification Certificate</h2>
            
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

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="certificate_type">Certificate Type</label>
                    <select name="certificate_type" id="certificate_type" required>
                        <option value="">Select Certificate Type</option>
                        <option value="Business Permit" <?= (isset($_POST['certificate_type']) && $_POST['certificate_type'] === 'Business Permit') ? 'selected' : '' ?>>Business Permit</option>
                        <option value="Agricultural License" <?= (isset($_POST['certificate_type']) && $_POST['certificate_type'] === 'Agricultural License') ? 'selected' : '' ?>>Agricultural License</option>
                        <option value="Tax Identification" <?= (isset($_POST['certificate_type']) && $_POST['certificate_type'] === 'Tax Identification') ? 'selected' : '' ?>>Tax Identification</option>
                        <option value="Others" <?= (isset($_POST['certificate_type']) && $_POST['certificate_type'] === 'Others') ? 'selected' : '' ?>>Others</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="certificate_name">Certificate Name/Title</label>
                    <input type="text" name="certificate_name" id="certificate_name" 
                           value="<?= isset($_POST['certificate_name']) ? htmlspecialchars($_POST['certificate_name']) : '' ?>"
                           placeholder="e.g., Farm Business Permit 2024" required>
                </div>

                <div class="form-group">
                    <label for="certificate">Certificate File</label>
                    <input type="file" name="certificate" id="certificate" 
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                    <div class="file-info">
                        Allowed formats: PDF, JPG, PNG, DOC, DOCX (Max size: 5MB)
                    </div>
                </div>

                <button type="submit" class="btn">Upload Certificate</button>
            </form>
        </div>

        <?php if (!empty($certificates)): ?>
            <div class="card">
                <h2>Your Submitted Certificates</h2>
                <div class="certificates-list">
                    <?php foreach ($certificates as $cert): ?>
                        <div class="certificate-item">
                            <div class="certificate-info">
                                <h4><?= htmlspecialchars($cert['certificate_name']) ?></h4>
                                <p><strong>Type:</strong> <?= htmlspecialchars($cert['certificate_type']) ?></p>
                                <p><strong>Submitted:</strong> <?= date('M d, Y h:i A', strtotime($cert['submitted_at'])) ?></p>
                                <?php if ($cert['reviewed_at']): ?>
                                    <p><strong>Reviewed:</strong> <?= date('M d, Y h:i A', strtotime($cert['reviewed_at'])) ?></p>
                                <?php endif; ?>
                                <?php if ($cert['admin_notes']): ?>
                                    <p><strong>Admin Notes:</strong> <?= htmlspecialchars($cert['admin_notes']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="certificate-status status-<?= strtolower($cert['status']) ?>">
                                    <?= htmlspecialchars($cert['status']) ?>
                                </div>
                                <div class="certificate-actions">
                                    <a href="<?= htmlspecialchars($cert['certificate_path']) ?>" target="_blank" 
                                       class="btn btn-secondary btn-small">View</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // File validation
        document.getElementById('certificate').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            
            if (file) {
                if (file.size > maxSize) {
                    alert('File size must be less than 5MB');
                    e.target.value = '';
                    return;
                }
                
                if (!allowedTypes.includes(file.type)) {
                    alert('Invalid file type. Allowed types: PDF, JPG, PNG, DOC, DOCX');
                    e.target.value = '';
                    return;
                }
            }
        });
    </script>
</body>
</html>
