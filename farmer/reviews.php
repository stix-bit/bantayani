<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Farmer');
require_once __DIR__ . '/../includes/config.php';

$farmer_id = $_SESSION['user_id'];

// Get all reviews for this farmer's products
$stmt = $conn->prepare("SELECT r.rating, r.comment, r.created_at, r.inventory_id, c.crop_name, ci.unit, ci.price, u.first_name AS buyer_first_name, u.last_name AS buyer_last_name
    FROM ratings r
    JOIN crops_inventory ci ON r.inventory_id = ci.inventory_id
    JOIN crops c ON ci.crop_id = c.crop_id
    JOIN users u ON r.buyer_id = u.user_id
    WHERE ci.farmer_id = ?
    ORDER BY r.created_at DESC");
$stmt->bind_param('i', $farmer_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Farmer aggregates
$rating_summary_stmt = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews FROM ratings r JOIN crops_inventory ci ON r.inventory_id = ci.inventory_id WHERE ci.farmer_id = ?");
$rating_summary_stmt->bind_param('i', $farmer_id);
$rating_summary_stmt->execute();
$rating_summary = $rating_summary_stmt->get_result()->fetch_assoc();
$rating_summary_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Reviews - BANTAY-ANI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>

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

        .nav-section { padding: 16px; }
        .nav-title { margin-bottom: 10px; color: var(--text-light); font-weight: 600; font-size: 0.9rem; }
        .nav-links { list-style: none; padding: 0; margin: 0; }
        .nav-links li+a { margin-top: 4px; }
        .nav-link { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; color: var(--text); text-decoration: none; margin-bottom: 5px; font-weight: 500; }
        .nav-link.active, .nav-link:hover { background: #ecfdf5; color: #065f46; }
        .nav-icon { font-size: 0.95rem; }

        .main-content { flex: 1; margin-left: var(--sidebar-width); min-height: 100vh; }
        .topbar { background: white; padding: 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .page-title h1 { font-size: 1.8rem; }
        .page-title p { color: var(--text-light); margin-top: 4px; }

        .content { padding: 24px; max-width: 1200px; margin: 0 auto; }
        .table-card { background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); overflow: hidden; margin-bottom: 24px; }
        .table-header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 14px 16px; border-bottom: 1px solid var(--border); text-align: left; }
        .data-table th { background: #f9fafb; color: var(--text-light); font-weight: 700; }
        .data-table tr:hover { background: #f6f9f8; }

        .summary { display: flex; gap: 16px; margin-bottom: 16px; align-items: center; }
        .badge { background: #ecfdf5; color: #065f46; border-radius: 8px; padding: 10px 14px; font-weight: 600; }
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
            <li><a href="inventory.php" class="nav-link"><span class="nav-icon">🌾</span><span>Inventory</span></a></li>
            <li><a href="benchmarking.php" class="nav-link"><span class="nav-icon">📊</span><span>Price Benchmarking</span></a></li>
            <li><a href="reviews.php" class="nav-link active"><span class="nav-icon">⭐</span><span>Product Reviews</span></a></li>
        </ul>
    </div>
</aside>

<main class="main-content">
    <div class="topbar">
        <div class="page-title">
            <h1>Product Reviews</h1>
            <p>See ratings and feedback for your listed products</p>
        </div>
    </div>

    <div class="content">
        <div class="summary">
            <span class="badge">Kabuuang rating: <?= $rating_summary['avg_rating'] ? number_format($rating_summary['avg_rating'], 2) : 'N/A' ?> </span>
            <span class="badge">Total reviews: <?= (int)$rating_summary['total_reviews'] ?></span>
            <span class="badge">Product results: <?= count($reviews) ?></span>
        </div>

        <div class="table-card">
            <div class="table-header">
                <h3 style="margin:0;">Detalye ng review</h3>
            </div>
            <?php if (empty($reviews)): ?>
                <div style="padding: 32px; color: var(--text-light); text-align:center;">No reviews yet for your products.</div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Crop</th>
                        <th>Buyer</th>
                        <th>Rating</th>
                        <th>Comment</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reviews as $rev): ?>
                    <tr>
                        <td><?= htmlspecialchars($rev['crop_name']) ?></td>
                        <td><?= htmlspecialchars($rev['buyer_first_name'] . ' ' . $rev['buyer_last_name']) ?></td>
                        <td><?= str_repeat('⭐', max(1, min(5, (int)$rev['rating']))) ?> (<?= (int)$rev['rating'] ?>)</td>
                        <td><?= htmlspecialchars($rev['comment'] ?: 'No comment') ?></td>
                        <td><?= htmlspecialchars((new DateTime($rev['created_at']))->format('Y-m-d H:i')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
