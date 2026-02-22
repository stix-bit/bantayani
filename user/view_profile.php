<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_login();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/verification_helper.php';

$profile_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($profile_id <= 0) {
    header('Location: ../search.php');
    exit;
}

// Fetch the user we're viewing
$stmt = $conn->prepare("
    SELECT u.user_id, u.first_name, u.middle_name, u.last_name, u.email,
           u.contact_number, u.address, u.img_path, u.role, u.is_verified, u.created_at
    FROM users u
    WHERE u.user_id = ?
    LIMIT 1
");
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Admins don't have a public profile
if (!$user || $user['role'] === 'Admin') {
    header('Location: ../search.php?msg=profile_unavailable');
    exit;
}

$role = $user['role'];
$profile_img = $user['img_path'] ?? '';
$default_avatar = '../images/default-avatar.png';
$public_path = !empty($profile_img) ? '../' . ltrim($profile_img, '/') : $default_avatar;

// Role-specific data
$farmer_profile = [];
$buyer_profile = [];
$verification_docs = [];
$inventory_summary = [];
$inventory_items = [];
$ratings = [];

if ($role === 'Farmer') {
    // Farmer profile data
    $stmt = $conn->prepare("
        SELECT fp.farm_name, fp.farm_location, fp.farm_img_path, fp.region,
               fp.verified_by, fp.verified_at,
               CONCAT(a.first_name, ' ', a.last_name) as verified_by_name
        FROM farmer_profiles fp
        LEFT JOIN users a ON fp.verified_by = a.user_id
        WHERE fp.farmer_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $profile_id);
    $stmt->execute();
    $farmer_profile = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    // Verification documents
    $stmt = $conn->prepare("
        SELECT verification_id, certificate_type, certificate_name,
               certificate_path, status, submitted_at, reviewed_at, admin_notes
        FROM farmer_verification
        WHERE farmer_id = ?
        ORDER BY submitted_at DESC
    ");
    $stmt->bind_param('i', $profile_id);
    $stmt->execute();
    $verification_docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Inventory summary
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_crops, SUM(quantity) as total_quantity
        FROM crops_inventory ci
        JOIN crops c ON ci.crop_id = c.crop_id
        WHERE ci.farmer_id = ?
    ");
    $stmt->bind_param('i', $profile_id);
    $stmt->execute();
    $inventory_summary = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    // Individual products
    $stmt = $conn->prepare("
        SELECT ci.inventory_id, c.crop_name, c.unit, ci.price, ci.quantity
        FROM crops_inventory ci
        JOIN crops c ON ci.crop_id = c.crop_id
        WHERE ci.farmer_id = ? AND ci.quantity > 0
        ORDER BY c.crop_name
    ");
    $stmt->bind_param('i', $profile_id);
    $stmt->execute();
    $inventory_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Ratings summary
    $stmt = $conn->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings
        FROM ratings
        WHERE farmer_id = ?
    ");
    $stmt->bind_param('i', $profile_id);
    $stmt->execute();
    $ratings = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
}

if ($role === 'Buyer') {
    $stmt = $conn->prepare("
        SELECT bp.preferred_payment_method, bp.verified
        FROM buyer_profiles bp
        WHERE bp.buyer_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $profile_id);
    $stmt->execute();
    $buyer_profile = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BantayAni | Profile</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 10px 30px rgba(12, 92, 76, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--green-dark);
        }

        .back-link {
            text-decoration: none;
            color: var(--green-dark);
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 999px;
            background: rgba(31, 138, 112, 0.08);
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 2fr 1.5fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(12, 92, 76, 0.08);
        }

        .card h2 {
            color: var(--green-dark);
            margin-top: 0;
            margin-bottom: 16px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 16px;
        }

        .profile-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--green);
        }

        .profile-info h3 {
            margin: 0;
            color: var(--green-dark);
        }

        .profile-info p {
            margin: 4px 0;
            color: #666;
        }

        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            background: rgba(31, 138, 112, 0.1);
            color: var(--green-dark);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-size: 0.95rem;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #666;
        }

        .detail-value {
            color: var(--text);
            text-align: right;
        }

        .tag {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.8rem;
            background: rgba(31, 138, 112, 0.08);
            color: var(--green-dark);
            margin-right: 6px;
        }

        .farm-image-wrap {
            margin-bottom: 16px;
            border-radius: 12px;
            overflow: hidden;
            background: var(--beige);
        }

        .farm-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
        }

        .products-list p {
            margin: 4px 0;
            font-size: 0.95rem;
        }

        .products-list a {
            color: var(--green-dark);
            text-decoration: none;
            font-weight: 600;
        }

        .products-list a:hover {
            text-decoration: underline;
        }

        .cert-list li {
            margin-bottom: 6px;
            font-size: 0.95rem;
        }

        .cert-list {
            padding-left: 18px;
        }

        @media (max-width: 900px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-title">Profile</div>
            <a href="../search.php" class="back-link">← Back to Search</a>
        </div>

        <div class="profile-grid">
            <div class="card">
                <div class="profile-header">
                    <img src="<?= htmlspecialchars($public_path) ?>" class="profile-avatar"
                         onerror="this.src='../images/default-avatar.png';" alt="">
                    <div class="profile-info">
                        <h3><?= htmlspecialchars(trim($user['first_name'] . ' ' . ($user['middle_name'] ?? '') . ' ' . $user['last_name'])) ?></h3>
                        <p><?= htmlspecialchars($user['email']) ?></p>
                        <span class="role-badge"><?= htmlspecialchars($role) ?></span>
                    </div>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Contact</span>
                    <span class="detail-value"><?= htmlspecialchars($user['contact_number'] ?? '—') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Address</span>
                    <span class="detail-value"><?= htmlspecialchars($user['address'] ?? '—') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Member since</span>
                    <span class="detail-value">
                        <?= !empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : '—' ?>
                    </span>
                </div>
            </div>

            <?php if ($role === 'Farmer' && $farmer_profile): ?>
            <?php
                $farm_img = $farmer_profile['farm_img_path'] ?? '';
                $farm_public_path = !empty($farm_img) ? '../' . ltrim($farm_img, '/') : '../images/default-farm.png';
            ?>
            <div class="card">
                <h2>Farm Details</h2>
                <div class="farm-image-wrap">
                    <img src="<?= htmlspecialchars($farm_public_path) ?>" class="farm-image" alt="Farm"
                         onerror="this.src='../images/default-farm.png';">
                </div>
                <div class="detail-row">
                    <span class="detail-label">Farm name</span>
                    <span class="detail-value"><?= htmlspecialchars($farmer_profile['farm_name'] ?? '—') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Location</span>
                    <span class="detail-value"><?= htmlspecialchars($farmer_profile['farm_location'] ?? '—') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Region</span>
                    <span class="detail-value"><?= htmlspecialchars($farmer_profile['region'] ?? '—') ?></span>
                </div>
                <?php if (!empty($farmer_profile['verified_at'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Verified by</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($farmer_profile['verified_by_name'] ?? '') ?><br>
                        <small><?= date('M d, Y', strtotime($farmer_profile['verified_at'])) ?></small>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <?php elseif ($role === 'Buyer' && $buyer_profile): ?>
            <div class="card">
                <h2>Buyer Details</h2>
                <div class="detail-row">
                    <span class="detail-label">Preferred payment</span>
                    <span class="detail-value"><?= htmlspecialchars($buyer_profile['preferred_payment_method'] ?? '—') ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($role === 'Farmer'): ?>
        <div class="profile-grid">
            <div class="card">
                <h2>Products</h2>
                <?php if (!empty($inventory_items)): ?>
                    <div class="products-list">
                        <?php foreach ($inventory_items as $item): ?>
                            <p>
                                <a href="../buyer/product.php?id=<?= (int) $item['inventory_id'] ?>">
                                    <?= htmlspecialchars($item['crop_name']) ?>
                                </a>
                                – <?= htmlspecialchars($item['quantity']) ?> <?= htmlspecialchars($item['unit']) ?>
                                @ ₱<?= number_format((float) $item['price'], 2) ?>
                            </p>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No active products listed.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Certificates & Ratings</h2>
                <?php if (!empty($ratings) && (float) ($ratings['avg_rating'] ?? 0) > 0): ?>
                    <p>
                        <span class="tag">
                            ⭐ <?= number_format((float) $ratings['avg_rating'], 1) ?> (<?= (int) $ratings['total_ratings'] ?> ratings)
                        </span>
                    </p>
                <?php endif; ?>

                <h3 style="margin-top: 16px; margin-bottom: 8px; font-size: 1rem;">Certificates</h3>
                <?php if (!empty($verification_docs)): ?>
                    <ul class="cert-list">
                        <?php foreach ($verification_docs as $doc): ?>
                            <li>
                                <?= htmlspecialchars($doc['certificate_name']) ?>
                                (<?= htmlspecialchars($doc['certificate_type']) ?>,
                                <?= htmlspecialchars($doc['status']) ?>)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No certificates on file.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
