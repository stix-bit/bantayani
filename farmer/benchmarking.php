<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Farmer') {
    header("Location: ../user/login.php");
    exit;
}

$farmer_id = $_SESSION['user_id'];
$days = $_GET['days'] ?? 30;

$sql = "
SELECT 
    c.crop_id,
    c.crop_name,
    c.unit,
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
    AND o.order_status = 'Completed'
    AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
GROUP BY c.crop_id
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $farmer_id, $days);
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function getStatus($farmer, $avg) {
    if ($farmer == null) return "No Price";
    if ($farmer < $avg * 0.9) return "Too Low";
    if ($farmer > $avg * 1.1) return "Too High";
    return "Fair";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Price Benchmarking</title>
    <style>
        body { font-family: 'Quicksand', Arial; padding:30px; background:#f6f1e9; }
        table { width:100%; border-collapse:collapse; background:white; }
        th, td { padding:12px; border-bottom:1px solid #ddd; text-align:center; }
        th { background:#1f8a70; color:white; }
        .low { color:#b91c1c; }
        .high { color:#b45309; }
        .fair { color:#1f8a70; }

        @import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap');

        :root {
            --green-dark: #0c5c4c;
            --green: #1f8a70;
            --green-light: #4cb69f;
            --orange: #f28705;
            --beige: #f6f1e9;
            --beige-light: #fcfaf6;
            --text: #1f2933;
            --text-light: #4c5662;
            --border: #e4e7eb;
            --success: #0c5c4c;
            --warning: #f28705;
            --info: #1d4ed8;
            --error: #b91c1c;
            --shadow-sm: 0 2px 8px rgba(12, 92, 76, 0.08);
            --shadow-md: 0 8px 20px rgba(12, 92, 76, 0.12);
            --shadow-lg: 0 25px 60px rgba(12, 92, 76, 0.2);
            --radius-sm: 12px;
            --radius-md: 20px;
            --radius-lg: 28px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        .nav-links {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .nav-link {
            text-decoration: none;
            color: var(--text-light);
            font-weight: 500;
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            transition: all 0.2s;
        }

        .nav-link:hover {
            color: var(--green);
            background: rgba(12, 92, 76, 0.05);
        }

        .nav-link.active {
            color: var(--green);
            background: rgba(12, 92, 76, 0.1);
        }

        .navbar {
            background: white;
            padding: 16px 32px;
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--green-dark);
        }

        .logo-text span {
            color: var(--orange);
        }

    </style>
</head>
<body>

<nav class="navbar">
        <div class="logo-container">
            <div class="logo">BA</div>
            <div class="logo-text">BANTAY<span>ANI</span></div>
        </div>
        
        <div class="nav-links">
            <a href="../index.php" class="nav-link active">Dashboard</a>
            <a href="./inventory.php" class="nav-link">My Inventory</a>
            <a href="./orders.php" class="nav-link">Orders</a>
            <a href="./cooperative.php" class="nav-link">Cooperative</a>
            <a href="./benchmarking.php" class="nav-link">Pricing</a>
        </div>
</nav>
<br>

<h1>🌱 Pricing Comparison & Recommendation</h1>
<br>

<form method="GET">
    Compare last:
    <select name="days" onchange="this.form.submit()">
        <option value="30" <?= $days==30?'selected':'' ?>>30 Days</option>
        <option value="60" <?= $days==60?'selected':'' ?>>60 Days</option>
        <option value="90" <?= $days==90?'selected':'' ?>>90 Days</option>
    </select>
</form>

<br>

<table>
<tr>
    <th>Crop</th>
    <th>Your Price</th>
    <th>Market Avg</th>
    <th>Min</th>
    <th>Max</th>
    <th>Recommendation</th>
</tr>

<?php foreach ($data as $row): 
    $status = getStatus($row['farmer_price'], $row['market_avg']);
?>
<tr>
    <td><?= htmlspecialchars($row['crop_name']) ?> (<?= $row['unit'] ?>)</td>
    <td><?= $row['farmer_price'] ? '₱'.$row['farmer_price'] : '-' ?></td>
    <td>₱<?= number_format($row['market_avg'],2) ?></td>
    <td>₱<?= $row['market_min'] ?></td>
    <td>₱<?= $row['market_max'] ?></td>
    <td class="<?= strtolower(str_replace(' ', '', $status)) ?>">
        <?= $status ?>
    </td>
</tr>
<?php endforeach; ?>

</table>

</body>
</html>
