<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Buyer');
include "../includes/config.php";

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$profile_img = null;
$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

$pool_id = (int)($_GET['pool_id'] ?? 0);
if ($pool_id <= 0) {
    header('Location: ../marketplace.php');
    exit;
}

$has_unit_price = false;
$cols = $conn->query("SHOW COLUMNS FROM cooperative_pools LIKE 'unit_price'");
if ($cols && $cols->num_rows > 0) $has_unit_price = true;

$select_extras = ($has_unit_price ? ", p.unit_price" : "");
$sql = "SELECT p.pool_id, p.crop_id, p.total_quantity, c.crop_name, p.unit $select_extras
        FROM cooperative_pools p
        JOIN crops c ON p.crop_id = c.crop_id
        WHERE p.pool_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $pool_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header('Location: ../marketplace.php');
    exit;
}

$available = (float)($row['total_quantity'] ?? 0);

// Load images for this crop (from individual inventory entries for the same crop)
$product_images = [];
$stmt = $conn->prepare("SELECT ci_img.image_path
    FROM crop_images ci_img
    JOIN crops_inventory ci ON ci_img.inventory_id = ci.inventory_id
    WHERE ci.crop_id = ?
    ORDER BY ci_img.is_primary DESC, ci_img.image_id ASC");
$stmt->bind_param("i", $row['crop_id']);
$stmt->execute();
$product_images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Ratings summary for this crop pool (aggregate by crop)
$pool_rating = ['avg_rating' => null, 'total_ratings' => 0];
$stmt = $conn->prepare("SELECT AVG(r.rating) AS avg_rating, COUNT(*) AS total_ratings
    FROM ratings r
    JOIN crops_inventory ci ON r.inventory_id = ci.inventory_id
    WHERE ci.crop_id = ?");
$stmt->bind_param("i", $row['crop_id']);
$stmt->execute();
$pool_rating = $stmt->get_result()->fetch_assoc() ?: $pool_rating;
$stmt->close();

$pool_reviews = [];
$stmt = $conn->prepare("SELECT r.rating, r.comment, r.created_at, u.first_name
    FROM ratings r
    JOIN crops_inventory ci ON r.inventory_id = ci.inventory_id
    JOIN users u ON r.buyer_id = u.user_id
    WHERE ci.crop_id = ?
    ORDER BY r.created_at DESC
    LIMIT 8");
$stmt->bind_param("i", $row['crop_id']);
$stmt->execute();
$pool_reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Find a representative inventory item for submitting ratings
$rating_inventory_id = 0;
$stmt = $conn->prepare("SELECT inventory_id FROM crops_inventory WHERE crop_id = ? AND quantity > 0 ORDER BY quantity DESC LIMIT 1");
$stmt->bind_param("i", $row['crop_id']);
$stmt->execute();
$stmt->bind_result($rating_inventory_id);
$stmt->fetch();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cooperative Pool - <?= htmlspecialchars($row['crop_name']) ?></title>
    <link rel="stylesheet" href="assets/css/buyer.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600&display=swap');

        :root {
            --green-dark: #0c5c4c;
            --green: #1f8a70;
            --beige: #f6f1e9;
            --text: #1f2933;
            --text-light: #4c5662;
        }

        .product-page body {
            font-family: 'Quicksand', 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, rgba(12, 92, 76, 0.08), rgba(242, 135, 5, 0.15));
            min-height: 100vh;
        }

        .product-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }

        .product-breadcrumb {
            margin-bottom: 20px;
        }

        .product-breadcrumb a {
            color: var(--green-dark);
            text-decoration: none;
            font-weight: 600;
        }

        .product-breadcrumb a:hover {
            text-decoration: underline;
        }

        .product-breadcrumb span {
            color: var(--text-light);
            margin: 0 8px;
        }

        .product-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .product-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(12, 92, 76, 0.1);
        }

        .product-card h2 {
            color: var(--green-dark);
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }

        .product-image-slider {
            position: relative;
            width: 100%;
            aspect-ratio: 4/3;
            background: linear-gradient(135deg, rgba(31, 138, 112, 0.12), rgba(12, 92, 76, 0.08));
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 20px;
            border: 2px solid rgba(31, 138, 112, 0.2);
        }

        .product-image-slider img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }

        .product-image-slider img.active {
            display: block;
        }

        .slider-controls {
            position: absolute;
            inset: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 12px;
            pointer-events: none;
        }

        .slider-btn {
            pointer-events: all;
            border: none;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--green-dark);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
        }

        .image-counter {
            position: absolute;
            bottom: 10px;
            right: 12px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 2px 10px;
            font-size: 0.85rem;
            color: #4b5563;
        }

        .product-image-placeholder {
            width: 100%;
            aspect-ratio: 4 / 3;
            border-radius: 16px;
            background: #f8fafc;
            border: 2px dashed rgba(31, 138, 112, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            margin-bottom: 20px;
        }

        .placeholder-icon {
            font-size: 2rem;
        }

        .product-detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            align-items: center;
        }

        .product-detail-label {
            color: var(--text-light);
            font-weight: 600;
        }

        .product-detail-value {
            font-weight: 700;
            color: var(--green-dark);
        }

        .product-price {
            color: var(--green-dark);
        }

        .add-to-cart-form {
            margin-top: 20px;
        }

        .product-page .rating-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: rgba(31, 138, 112, 0.1);
            border-radius: 999px;
            font-weight: 600;
            color: var(--green-dark);
            margin-bottom: 16px;
        }

        .reviews-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .reviews-list li {
            padding: 14px 0;
            border-bottom: 1px solid #eee;
            font-size: 0.95rem;
        }

        .reviews-list li:last-child {
            border-bottom: none;
        }

        .review-meta {
            color: var(--text-light);
            font-size: 0.85rem;
            margin-top: 4px;
        }

        .reviews-placeholder {
            padding: 24px;
            background: rgba(31, 138, 112, 0.06);
            border-radius: 12px;
            border: 2px dashed rgba(31, 138, 112, 0.25);
            color: var(--text-light);
            text-align: center;
        }

        @media (max-width: 900px) {
            .product-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="product-page">

<nav class="navbar">
    <div class="logo-container">
        <div class="logo">BA</div>
        <div class="logo-text">BANTAY<span>ANI</span></div>
    </div>
    <div class="nav-links">
        <a href="../index.php" class="nav-link">Dashboard</a>
            <a href="../marketplace.php" class="nav-link">Marketplace</a>
            <a href="cart.php" class="nav-link active">Cart</a>
            <a href="orders.php" class="nav-link">My Orders</a>
            <a href="../announcements.php" class="nav-link">Announcements</a>
            <a href="../invoices.php" class="nav-link">Invoices</a>
            <a href="notifications.php" class="nav-link">Notifications</a>
    </div>
    <div class="user-menu">
        <?php
            $profile_img = trim($profile_img ?? '');
            $absolute_path = $_SERVER['DOCUMENT_ROOT'] . '/bantayani/' . $profile_img;
            $public_path   = '/bantayani/' . $profile_img;
        ?>
        <a href="profile.php" title="View Profile">
            <div class="user-avatar">
                <?php if (!empty($profile_img) && file_exists($absolute_path)): ?>
                    <img src="<?= htmlspecialchars($public_path) ?>" alt="Profile" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                <?php else: ?>
                    <?= strtoupper(substr($first_name, 0, 1)) ?>
                <?php endif; ?>
            </div>
        </a>
        <a href="/bantayani/user/logout.php" class="logout-btn" style="text-decoration:none; display:inline-block;">Log Out</a>
    </div>
</nav>

<div class="product-container">
    <div class="product-breadcrumb">
        <a href="../marketplace.php">Marketplace</a>
        <span>›</span>
        <span><?= htmlspecialchars($row['crop_name']) ?></span>
    </div>

    <div class="product-grid">
        <div class="product-card">
            <h2><?= htmlspecialchars($row['crop_name']) ?></h2>

            <?php if (!empty($product_images)): ?>
                <div class="product-image-slider">
                    <?php foreach ($product_images as $index => $image): ?>
                        <img src="<?= htmlspecialchars('../' . $image['image_path']) ?>" 
                             alt="<?= htmlspecialchars($row['crop_name']) ?>"
                             class="<?= $index === 0 ? 'active' : '' ?>">
                    <?php endforeach; ?>

                    <?php if (count($product_images) > 1): ?>
                        <div class="slider-controls">
                            <button type="button" class="slider-btn" onclick="changeImage(-1)">‹</button>
                            <button type="button" class="slider-btn" onclick="changeImage(1)">›</button>
                        </div>
                        <div class="image-counter"><?= count($product_images) ?></div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="product-image-placeholder">
                    <div style="text-align: center;">
                        <div class="placeholder-icon">🌾</div>
                        <div>No product image</div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="product-detail-row">
                <span class="product-detail-label">Price</span>
                <span class="product-detail-value product-price">
                    ₱<?= $has_unit_price && isset($row['unit_price']) && $row['unit_price'] != null ? number_format((float) $row['unit_price'], 2) : 'N/A' ?> / <?= htmlspecialchars($row['unit']) ?>
                </span>
            </div>
            <div class="product-detail-row">
                <span class="product-detail-label">Available</span>
                <span class="product-detail-value"><?= number_format($available, 2) ?> <?= htmlspecialchars($row['unit']) ?></span>
            </div>

            <form method="post" action="cart.php" class="add-to-cart-form">
                <input type="hidden" name="pool_id" value="<?= (int) $row['pool_id'] ?>">
                <div style="display:flex; align-items:center; gap:8px;">
                    <input type="number" name="qty" min="0.01" step="0.01" max="<?= $available ?>" value="1" required>
                    <span><?= htmlspecialchars($row['unit']) ?></span>
                </div>
                <button type="submit" class="btn">Add to Cart</button>
            </form>
        </div>

        <div class="product-card" style="margin-top:24px;">
            <h2>Pool reviews</h2>
            <div class="rating-badge" style="margin-bottom: 16px;">
                <strong>Overall</strong>&nbsp;
                <?php if (!empty($pool_rating['avg_rating']) && $pool_rating['total_ratings'] > 0): ?>
                    ⭐ <?= number_format((float)$pool_rating['avg_rating'], 1) ?> / 5 (<?= (int)$pool_rating['total_ratings'] ?> reviews)
                <?php else: ?>
                    No ratings yet
                <?php endif; ?>
            </div>

            <?php if (!empty($pool_reviews)): ?>
                <ul class="reviews-list">
                    <?php foreach ($pool_reviews as $review): ?>
                        <li>
                            <strong><?= (int)$review['rating'] ?> ★</strong>
                            <?php if (!empty($review['comment'])): ?> — <?= htmlspecialchars($review['comment']) ?><?php endif; ?>
                            <div class="review-meta"><?= htmlspecialchars($review['first_name']) ?> · <?= date('M j, Y', strtotime($review['created_at'])) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="reviews-placeholder">No reviews yet for this pool. Be the first to rate!</div>
            <?php endif; ?>

            <?php if ($rating_inventory_id > 0): ?>
                <form method="post" action="submit_rating.php" style="margin-top: 16px; display:grid; gap:8px;">
                    <input type="hidden" name="inventory_id" value="<?= (int)$rating_inventory_id ?>">
                    <label for="rating">Your rating</label>
                    <select name="rating" id="rating" required>
                        <option value="">Select rating</option>
                        <option value="5">⭐⭐⭐⭐⭐ (5)</option>
                        <option value="4">⭐⭐⭐⭐ (4)</option>
                        <option value="3">⭐⭐⭐ (3)</option>
                        <option value="2">⭐⭐ (2)</option>
                        <option value="1">⭐ (1)</option>
                    </select>
                    <textarea name="comment" placeholder="Leave a comment (optional)"></textarea>
                    <button type="submit" class="btn">Submit Review</button>
                </form>
            <?php else: ?>
                <div style="margin-top: 16px; color: #6b7280;">Rating submission unavailable until there is stock.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
<script>
let currentImageIndex = 0;
const images = document.querySelectorAll('.product-image-slider img');
const totalImages = images.length;

function changeImage(direction) {
    images[currentImageIndex].classList.remove('active');
    currentImageIndex = (currentImageIndex + direction + totalImages) % totalImages;
    images[currentImageIndex].classList.add('active');
}

if (totalImages > 1) {
    setInterval(() => {
        changeImage(1);
    }, 3000);
}
</script>
</html>
