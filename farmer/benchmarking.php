<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Farmer');
require_once '../includes/config.php';
require_once __DIR__ . '/../includes/dynamic_pricing.php';

$farmer_id = $_SESSION['user_id'];
$days = (int)($_GET['days'] ?? 30);

// Market stats from completed orders (Delivered/Shipped)
$sql = "
SELECT 
    c.crop_id,
    c.crop_name,
    ci.unit,
    ROUND(AVG(ci.price),2) AS market_avg,
    MIN(ci.price) AS market_min,
    MAX(ci.price) AS market_max,
    ROUND(AVG(ci.price),2) AS farmer_price
FROM crops c
LEFT JOIN crops_inventory ci 
    ON c.crop_id = ci.crop_id AND ci.farmer_id = ?
LEFT JOIN order_items oi 
    ON ci.inventory_id = oi.inventory_id
LEFT JOIN orders o 
    ON oi.order_id = o.order_id
    AND o.order_status IN ('Delivered', 'Shipped')
    AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
GROUP BY c.crop_id
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $farmer_id, $days);
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Dynamic pricing: recommended and allowed range per crop
$dynamic_ranges = getAllCropsDynamicPriceRanges($conn, $days);

function getStatus($farmer, $avg) {
    if ($farmer == null) return "No Price";
    if ($farmer < $avg * 0.9) return "Too Low";
    if ($farmer > $avg * 1.1) return "Too High";
    return "Fair";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Price Benchmarking - BANTAY-ANI</title>
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
        .farmer-badge { display: inline-block; background: rgba(12, 92, 76, 0.1); color: var(--green-dark); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; margin-left: 8px; }
        .nav-section { padding: 20px 0; }
        .nav-title { padding: 0 24px 12px; font-size: 0.85rem; color: var(--text-light); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .nav-links { list-style: none; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 14px 24px; color: var(--text); text-decoration: none; transition: all 0.2s; border-left: 3px solid transparent; }
        .nav-link:hover { background: rgba(12, 92, 76, 0.05); color: var(--green); }
        .nav-link.active { background: rgba(12, 92, 76, 0.1); color: var(--green); border-left-color: var(--green); }
        .nav-icon { font-size: 1.2rem; width: 24px; text-align: center; }

        /* Main Content */
        .main-content { flex: 1; margin-left: var(--sidebar-width); min-height: 100vh; }
        .topbar { background: white; padding: 24px 32px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: var(--shadow-sm); }
        .page-title h1 { font-size: 2rem; margin-bottom: 4px; }
        .page-title p { color: var(--text-light); font-size: 1rem; }

        /* Content Area */
        .content { padding: 32px; max-width: 1200px; margin: 0 auto; }

        /* Card */
        .card { background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 32px; }
        .card-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .card-header h2 { font-size: 1.5rem; color: var(--text); }
        .card-header select { padding: 8px 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-family: 'Quicksand'; font-size: 0.95rem; cursor: pointer; }

        /* Table */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 16px; color: var(--text-light); font-weight: 600; border-bottom: 2px solid var(--beige); background: #f9fafb; }
        .data-table td { padding: 16px; border-bottom: 1px solid var(--border); }
        .data-table tr:hover { background: #f9fafb; }
        .data-table tr:last-child td { border-bottom: none; }

        /* Status Badges */
        .status-low { color: #b91c1c; font-weight: 600; }
        .status-high { color: #b45309; font-weight: 600; }
        .status-fair { color: #1f8a70; font-weight: 600; }
        .status-no-price { color: #6b7280; font-weight: 600; }

        /* Price Cell */
        .price-cell { font-family: 'Courier New', monospace; font-weight: 600; }
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
            <li><a href="benchmarking.php" class="nav-link active"><span class="nav-icon">📊</span><span>Price Benchmarking</span></a></li>
            <li><a href="reviews.php" class="nav-link"><span class="nav-icon">⭐</span><span>Product Reviews</span></a></li>
        </ul>
    </div>
</aside>

<main class="main-content">
    <div class="topbar">
        <div class="page-title">
            <h1>Price Benchmarking</h1>
            <p>I-kompara ang presyo ng iyong pananim sa kabuuan</p>
        </div>
    </div>

    <div class="content">
        <div class="card">
            <div class="card-header">
                <h2>🌱 Pagkumpara ng Presyo & Rekomenda na Presyo</h2>
                <form method="GET" style="display: flex; align-items: center; gap: 12px;">
                    <label style="color: var(--text-light); font-weight: 500;">Compare last:</label>
                    <select name="days" onchange="this.form.submit()" style="min-width: 120px;">
                        <option value="30" <?= $days==30?'selected':'' ?>>30 Days</option>
                        <option value="60" <?= $days==60?'selected':'' ?>>60 Days</option>
                        <option value="90" <?= $days==90?'selected':'' ?>>90 Days</option>
                    </select>
                </form>
            </div>
            <p style="color:var(--text-light); font-size:0.9rem; margin:-8px 0 16px 0;">Rekomendadong presyo ay batay sa supply, demand, mga kasalukuyang listing, at historical na mga pagbebenta. Kapag nagdaragdag o nagbabago ng inventory, ang iyong presyo ay dapat nasa <strong>Pinapayagan na Range</strong>.</p>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Crop</th>
                        <th>Your Price</th>
                        <th>Market Avg</th>
                        <!-- <th>Min</th>
                        <th>Max</th> -->
                        <th>Dynamic Recommended</th>
                        <th>Allowed Range</th>
                        <th>Recommendation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data)): ?>
                        <?php foreach ($data as $row): 
                            $status = getStatus($row['farmer_price'], $row['market_avg']);
                            $dr = $dynamic_ranges[$row['crop_id']] ?? null;
                            $unit = trim($row['unit'] ?? '');
                            $unit_suffix = $unit !== '' ? ' / ' . htmlspecialchars($unit) : '';
                        ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($row['crop_name']) ?>
                                <?= $unit !== '' ? ' ('.htmlspecialchars($unit).')' : '' ?>
                            </td>
                            <td class="price-cell">
                                <?= $row['farmer_price'] ? '₱'.number_format($row['farmer_price'], 2) . $unit_suffix : '-' ?>
                            </td>
                            <td class="price-cell">
                                <?= $row['market_avg'] !== null ? '₱'.number_format($row['market_avg'],2) . $unit_suffix : '—' ?>
                            </td>
                            <!-- <td class="price-cell">₱<?= number_format($row['market_min'], 2) ?></td>
                            <td class="price-cell">₱<?= number_format($row['market_max'], 2) ?></td> -->
                            <td class="price-cell">
                                <?= $dr && $dr['has_data'] ? '₱'.number_format($dr['recommended'], 2) . $unit_suffix : '—' ?>
                            </td>
                            <td class="price-cell" style="font-size:0.9rem;">
                                <?php if ($dr && $dr['has_data']): ?>
                                    ₱<?= number_format($dr['price_min'], 2) ?> – ₱<?= number_format($dr['price_max'], 2) ?><?= $unit_suffix ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="status-<?= strtolower(str_replace(' ', '', $status)) ?>">
                                <?= $status ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding:40px; color:var(--text-light);">
                                Wala pang data sa pananim. Magdagdag ng pananim sa iyong inventory at maghintay ng mga order para makita ang benchmarking dito.
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
