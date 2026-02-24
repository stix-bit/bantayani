<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Buyer');
include __DIR__ . '/../includes/config.php';

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$profile_img = null;
$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: marketplace.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT ci.inventory_id, ci.farmer_id, ci.crop_id, ci.quantity, ci.price, ci.harvest_date,
           c.crop_name, ci.unit,
           f.farm_name
    FROM crops_inventory ci
    JOIN crops c ON ci.crop_id = c.crop_id
    JOIN farmer_profiles f ON ci.farmer_id = f.farmer_id
    WHERE ci.inventory_id = ? AND ci.quantity > 0
");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header('Location: marketplace.php');
    exit;
}

// Fetch product images
$product_images = [];
$stmt = $conn->prepare("
    SELECT image_path, is_primary 
    FROM crop_images 
    WHERE inventory_id = ? 
    ORDER BY is_primary DESC, image_id ASC
");
$stmt->bind_param("i", $id);
$stmt->execute();
$product_images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Seller (farmer) ratings summary
$farmer_id = (int) $row['farmer_id'];
$seller_rating = ['avg_rating' => null, 'total_ratings' => 0];
$seller_reviews = [];
$stmt = $conn->prepare("
    SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_ratings
    FROM ratings
    WHERE farmer_id = ?
");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$seller_rating = $stmt->get_result()->fetch_assoc() ?: $seller_rating;
$stmt->close();

$stmt = $conn->prepare("
    SELECT r.rating, r.comment, r.created_at, u.first_name
    FROM ratings r
    JOIN users u ON r.buyer_id = u.user_id
    WHERE r.farmer_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$seller_reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($row['crop_name']) ?> | BANTAY-ANI</title>
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

        /* Product image slider */
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

        .product-image-slider .slider-controls {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 10px;
            pointer-events: none;
        }

        .product-image-slider .slider-btn {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--green-dark);
            pointer-events: all;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .product-image-slider .slider-btn:hover {
            background: white;
            transform: scale(1.1);
        }

        .product-image-slider .image-counter {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .product-image-placeholder {
            width: 100%;
            aspect-ratio: 4/3;
            background: linear-gradient(135deg, rgba(31, 138, 112, 0.12), rgba(12, 92, 76, 0.08));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            font-size: 1rem;
            margin-bottom: 20px;
            border: 2px dashed rgba(31, 138, 112, 0.3);
        }

        .product-image-placeholder .placeholder-icon {
            font-size: 3rem;
            opacity: 0.6;
            margin-bottom: 8px;
        }

        .product-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
            font-size: 1rem;
        }

        .product-detail-row:last-child {
            border-bottom: none;
        }

        .product-detail-label {
            font-weight: 600;
            color: var(--text-light);
        }

        .product-detail-value {
            color: var(--text);
            font-weight: 600;
        }

        .product-price {
            font-size: 1.5rem;
            color: var(--green-dark);
        }

        .add-to-cart-form {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .add-to-cart-form input[type="number"] {
            width: 80px;
            padding: 12px 14px;
            border: 2px solid #e4e7eb;
            border-radius: 12px;
            font-size: 1rem;
            font-family: inherit;
        }

        .add-to-cart-form input[type="number"]:focus {
            outline: none;
            border-color: var(--green);
        }

        .add-to-cart-form .btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            font-family: inherit;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .add-to-cart-form .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(31, 138, 112, 0.35);
        }

        .seller-link {
            color: var(--green-dark);
            text-decoration: none;
            font-weight: 600;
        }

        .seller-link:hover {
            text-decoration: underline;
        }

        /* Seller rating card */
        .rating-badge {
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

        /* Product reviews placeholder */
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
            <a href="marketplace.php" class="nav-link active">Marketplace</a>
            <a href="cart.php" class="nav-link">Cart</a>
            <a href="orders.php" class="nav-link">My Orders</a>
            <a href="profile.php" class="nav-link">Profile</a>
            <a href="ratings.php" class="nav-link">Ratings</a>
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
                        <img src="<?= htmlspecialchars($public_path) ?>"
                             alt="Profile"
                             style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                    <?php else: ?>
                        <?= strtoupper(substr($first_name, 0, 1)) ?>
                    <?php endif; ?>
                </div>
            </a>
            <a href="/bantayani/user/logout.php" class="logout-btn" style="text-decoration:none; display:inline-block;">
                Log Out
            </a>
        </div>
    </nav>

    <div class="product-container">
        <div class="product-breadcrumb">
            <a href="marketplace.php">Marketplace</a>
            <span>›</span>
            <span><?= htmlspecialchars($row['crop_name']) ?></span>
        </div>

        <div class="product-grid">
            <div class="product-card">
                <h2><?= htmlspecialchars($row['crop_name']) ?></h2>

                <?php if (!empty($product_images)): ?>
                    <div class="product-image-slider">
                        <?php foreach ($product_images as $index => $image): ?>
                            <img src="../<?= htmlspecialchars($image['image_path']) ?>" 
                                 alt="<?= htmlspecialchars($row['crop_name']) ?>"
                                 class="<?= $index === 0 ? 'active' : '' ?>">
                        <?php endforeach; ?>
                        
                        <?php if (count($product_images) > 1): ?>
                            <div class="slider-controls">
                                <button class="slider-btn" onclick="changeImage(-1)">‹</button>
                                <button class="slider-btn" onclick="changeImage(1)">›</button>
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
                    <span class="product-detail-value product-price">₱<?= number_format((float) $row['price'], 2) ?> / <?= htmlspecialchars($row['unit']) ?></span>
                </div>
                <div class="product-detail-row">
                    <span class="product-detail-label">Available</span>
                    <span class="product-detail-value"><?= htmlspecialchars($row['quantity']) ?> <?= htmlspecialchars($row['unit']) ?></span>
                </div>
                <div class="product-detail-row">
                    <span class="product-detail-label">Farm</span>
                    <span class="product-detail-value"><?= htmlspecialchars($row['farm_name']) ?></span>
                </div>
                <div class="product-detail-row">
                    <span class="product-detail-label">Seller</span>
                    <span class="product-detail-value">
                        <a href="../user/view_profile.php?id=<?= $farmer_id ?>" class="seller-link">View farmer profile</a>
                    </span>
                </div>
                <?php if (!empty($row['harvest_date'])): ?>
                <div class="product-detail-row">
                    <span class="product-detail-label">Harvest date</span>
                    <span class="product-detail-value"><?= date('M d, Y', strtotime($row['harvest_date'])) ?></span>
                </div>
                <?php endif; ?>

                <form method="post" action="cart.php" class="add-to-cart-form">
                    <input type="hidden" name="inventory_id" value="<?= (int) $row['inventory_id'] ?>">
                    <input type="number" name="qty" min="1" max="<?= (int) $row['quantity'] ?>" value="1" required>
                    <button type="submit" class="btn">Add to Cart</button>
                </form>
            </div>

            <div class="product-card">
                <h2>Seller rating</h2>
                <?php if (!empty($seller_rating['total_ratings']) && $seller_rating['avg_rating'] !== null): ?>
                    <div class="rating-badge">
                        ⭐ <?= number_format((float) $seller_rating['avg_rating'], 1) ?>
                        (<?= (int) $seller_rating['total_ratings'] ?> review<?= (int) $seller_rating['total_ratings'] !== 1 ? 's' : '' ?>)
                    </div>
                    <?php if (!empty($seller_reviews)): ?>
                        <ul class="reviews-list">
                            <?php foreach ($seller_reviews as $rev): ?>
                                <li>
                                    <strong><?= (int) $rev['rating'] ?> ★</strong>
                                    <?php if (!empty($rev['comment'])): ?>
                                        — <?= htmlspecialchars($rev['comment']) ?>
                                    <?php endif; ?>
                                    <div class="review-meta">
                                        <?= htmlspecialchars($rev['first_name'] ?? 'Buyer') ?>
                                        · <?= date('M j, Y', strtotime($rev['created_at'])) ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="color: var(--text-light);">No written reviews yet.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: var(--text-light);">No ratings yet for this seller.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="product-card" style="margin-bottom: 24px;">
            <h2>Product reviews</h2>
            <div class="reviews-placeholder">
                Reviews for this product will appear here once available.
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

// Auto-rotate images every 3 seconds
if (totalImages > 1) {
    setInterval(() => {
        changeImage(1);
    }, 3000);
}
</script>
</html>
