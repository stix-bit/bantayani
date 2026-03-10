<?php
session_start();
require_once "../includes/config.php";

$farmer_id = $_SESSION['user_id'];

// weather alerts - fetch active alerts only (last 24 hours)

?>

<?php
// Get latest weather data for farmer's region (same as API refresh)
require_once '../includes/weather_helper.php';
$current_weather = [];
$weather_service = new WeatherService($conn);
$region = null;
$region_stmt = $conn->prepare("SELECT region FROM farmer_profiles WHERE farmer_id = ?");
if ($region_stmt) {
    $region_stmt->bind_param('i', $farmer_id);
    $region_stmt->execute();
    $region_result = $region_stmt->get_result();
    if ($region_result) {
        $row = $region_result->fetch_assoc();
        if ($row && !empty($row['region'])) {
            $region = $row['region'];
        }
    }
    $region_stmt->close();
}
list($latitude, $longitude) = $weather_service->getRegionCoordinates($region);

$stmt = $conn->prepare("
    SELECT * FROM weather_data 
    WHERE latitude = ? AND longitude = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->bind_param("dd", $latitude, $longitude);
$stmt->execute();
$weather_result = $stmt->get_result()->fetch_assoc();
if ($weather_result && $weather_result['data_json']) {
    $current_weather = json_decode($weather_result['data_json'], true)['current'] ?? [];
}
$weather_alerts = [];

if (!empty($current_weather) && empty($_SESSION['weather_alerts_cleared'])) {
    $temp  = $current_weather['temperature_2m']       ?? null;
    $wind  = $current_weather['wind_speed_10m']       ?? null;
    $rain  = $current_weather['precipitation']        ?? 0;
    $humid = $current_weather['relative_humidity_2m'] ?? null;

    // ── Temperature alerts ────────────────────────────────────────────
    if ($temp !== null) {
        if ($temp >= 38) {
            $weather_alerts[] = [
                'title'    => 'Heat Alert',
                'message'  => 'Temperatures above 38°C may stress crops. Water regularly.',
                'severity' => 'Medium'
            ];
        } elseif ($temp >= 33 && $temp < 38) {
            // NEW LOW: warm but not dangerous
            $weather_alerts[] = [
                'title'    => 'Warm Day Advisory',
                'message'  => 'Temperatures between 33–38°C. Monitor soil moisture and shade sensitive seedlings.',
                'severity' => 'Low'
            ];
        } elseif ($temp <= 5) {
            $weather_alerts[] = [
                'title'    => 'Frost Warning',
                'message'  => 'Temperatures may drop below 5°C tonight. Protect sensitive plants.',
                'severity' => 'High'
            ];
        } elseif ($temp > 5 && $temp <= 10) {
            // NEW LOW: cool but not freezing
            $weather_alerts[] = [
                'title'    => 'Cool Temperature Notice',
                'message'  => 'Temperatures between 5–10°C. Consider light covering for cold-sensitive crops.',
                'severity' => 'Low'
            ];
        }
    }

    // ── Wind alerts ───────────────────────────────────────────────────
    if ($wind !== null) {
        if ($wind >= 50) {
            $weather_alerts[] = [
                'title'    => 'Strong Winds Advisory',
                'message'  => 'Wind speeds of 50+ km/h expected. Secure crops and equipment.',
                'severity' => 'Medium'
            ];
        } elseif ($wind >= 30 && $wind < 50) {
            // NEW LOW: moderate wind
            $weather_alerts[] = [
                'title'    => 'Moderate Wind Notice',
                'message'  => 'Wind speeds of 30–50 km/h expected. Check trellises and support structures.',
                'severity' => 'Low'
            ];
        }
    }

    // ── Rain / Precipitation alerts ───────────────────────────────────
    if ($rain >= 20) {
        $weather_alerts[] = [
            'title'    => 'Heavy Rain Warning',
            'message'  => 'Heavy rainfall expected. Consider early harvest to prevent crop damage.',
            'severity' => 'High'
        ];
    } elseif ($rain >= 5 && $rain < 20) {
        // NEW LOW: moderate rain
        $weather_alerts[] = [
            'title'    => 'Moderate Rainfall Expected',
            'message'  => 'Rainfall of 5–20 mm expected. Check drainage and delay fertilizer application.',
            'severity' => 'Low'
        ];
    } elseif ($rain > 0 && $rain < 5) {
        $weather_alerts[] = [
            'title'    => 'Mild Showers Expected',
            'message'  => 'Light rain expected. No immediate action needed.',
            'severity' => 'Low'
        ];
    }

    // ── Humidity alerts ───────────────────────────────────────────────
    if ($humid !== null) {
        if ($humid >= 90) {
            $weather_alerts[] = [
                'title'    => 'Very High Humidity Notice',
                'message'  => 'Humidity above 90%. Watch for fungal disease on leaves and fruit.',
                'severity' => 'Medium'
            ];
        } elseif ($humid >= 75 && $humid < 90) {
            // NEW LOW: elevated humidity
            $weather_alerts[] = [
                'title'    => 'Elevated Humidity Advisory',
                'message'  => 'Humidity between 75–90%. Ensure good air circulation around crops.',
                'severity' => 'Low'
            ];
        } elseif ($humid <= 20) {
            // NEW LOW: very dry
            $weather_alerts[] = [
                'title'    => 'Low Humidity Notice',
                'message'  => 'Humidity below 20%. Increase irrigation frequency to prevent drought stress.',
                'severity' => 'Low'
            ];
        }
    }
}



// yield analytics
$analytics_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_crops,
        IFNULL(SUM(quantity),0) as total_quantity,
        IFNULL(AVG(quantity),0) as avg_yield
    FROM crops_inventory
    WHERE farmer_id = ?
");
$analytics_stmt->bind_param("i", $farmer_id);
$analytics_stmt->execute();
$analytics = $analytics_stmt->get_result()->fetch_assoc();
$analytics_stmt->close();
?>

<?php
// No output before authentication check
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Farmer');

$farmer_id = $_SESSION['user_id'];

$errors = $errors ?? [];
$success = $success ?? '';

// Handle Harvest Confirmation/Cancel actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['harvest_action'])) {
    $inventory_id = (int)($_POST['inventory_id'] ?? 0);
    $harvest_action = $_POST['harvest_action'];

    if ($inventory_id > 0 && ($harvest_action === 'confirm' || $harvest_action === 'cancel')) {
        if ($harvest_action === 'confirm') {
            $stmt = $conn->prepare("UPDATE crops_inventory SET harvest_status = 'Confirmed', harvest_confirmed_at = NOW() WHERE inventory_id = ? AND farmer_id = ?");
            $stmt->bind_param('ii', $inventory_id, $farmer_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['message'] = 'Harvest confirmed successfully.';
        } else {
            $stmt = $conn->prepare("UPDATE crops_inventory SET harvest_status = 'Cancelled', harvest_cancelled_at = NOW() WHERE inventory_id = ? AND farmer_id = ?");
            $stmt->bind_param('ii', $inventory_id, $farmer_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['message'] = 'Harvest schedule cancelled.';
        }
    }

    header('Location: inventory.php');
    exit;
}

require_once __DIR__ . '/../includes/dynamic_pricing.php';

// Handle Add/Edit/Delete actions - redirect to separate files
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        header('Location: crop_add.php');
        exit;
    }
    
    if ($_POST['action'] === 'edit') {
        $inventory_id = $_POST['inventory_id'] ?? 0;
        header('Location: crop_edit.php?id=' . $inventory_id);
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $inventory_id = $_POST['inventory_id'];
        $stmt = $conn->prepare("DELETE FROM crops_inventory WHERE inventory_id = ? AND farmer_id = ?");
        $stmt->bind_param("ii", $inventory_id, $farmer_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['message'] = 'Crop deleted successfully!';
        header("Location: inventory.php");
        exit;
    }
}

// Fetch all crops
$crops_stmt = $conn->query("SELECT crop_id, crop_name FROM crops ORDER BY crop_name ASC");
$crops = $crops_stmt->fetch_all(MYSQLI_ASSOC);

// Dynamic pricing: allowed range per crop (for modals)
$price_ranges_by_crop = getAllCropsDynamicPriceRanges($conn);

// Fetch farmer inventory with images
$stmt = $conn->prepare("
    SELECT ci.inventory_id, ci.crop_id, ci.quantity, ci.harvest_date, ci.price, c.crop_name, ci.unit,
           GROUP_CONCAT(ci_img.image_path ORDER BY ci_img.is_primary DESC) as images,
           GROUP_CONCAT(ci_img.image_id ORDER BY ci_img.is_primary DESC) as image_ids
    FROM crops_inventory ci
    JOIN crops c ON ci.crop_id = c.crop_id
    LEFT JOIN crop_images ci_img ON ci.inventory_id = ci_img.inventory_id
    WHERE ci.farmer_id = ?
    GROUP BY ci.inventory_id
    ORDER BY ci.created_at DESC
");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$result = $stmt->get_result();
$inventory = $result->fetch_all(MYSQLI_ASSOC);

// Fetch crops that haven't been harvested yet (all scheduled crops)
$due_stmt = $conn->prepare("
    SELECT ci.inventory_id, ci.harvest_date, ci.quantity, c.crop_name, ci.unit
    FROM crops_inventory ci
    JOIN crops c ON ci.crop_id = c.crop_id
    WHERE ci.farmer_id = ?
      AND (ci.harvest_status IS NULL OR ci.harvest_status = 'Scheduled')
    ORDER BY ci.harvest_date ASC, ci.created_at DESC
");
$due_stmt->bind_param('i', $farmer_id);
$due_stmt->execute();
$due_harvests = $due_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$due_stmt->close();

// Fetch harvested crops
$harvested_stmt = $conn->prepare("
    SELECT ci.inventory_id, ci.harvest_date, ci.quantity, c.crop_name, ci.unit, ci.harvest_confirmed_at
    FROM crops_inventory ci
    JOIN crops c ON ci.crop_id = c.crop_id
    WHERE ci.farmer_id = ?
      AND ci.harvest_status = 'Confirmed'
    ORDER BY ci.harvest_confirmed_at DESC
");
$harvested_stmt->bind_param('i', $farmer_id);
$harvested_stmt->execute();
$harvested_crops = $harvested_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$harvested_stmt->close();

// Mark notifications as seen (best-effort)
if (!empty($due_harvests)) {
    $mark_seen = $conn->prepare("UPDATE crops_inventory SET harvest_notification_seen_at = IFNULL(harvest_notification_seen_at, NOW()) WHERE farmer_id = ? AND harvest_date <= CURDATE() AND (harvest_status IS NULL OR harvest_status = 'Scheduled')");
    $mark_seen->bind_param('i', $farmer_id);
    $mark_seen->execute();
    $mark_seen->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory - BANTAY-ANI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        .table-card {
    background: white;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    margin-bottom: 16px;   /* less space between cards */
    max-width: 100%;
    
}

.table-header {
    padding: 16px 20px;   /* smaller padding */
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.data-table th, .data-table td {
    padding: 12px 16px;  /* smaller cell padding */
    font-size: 0.9rem;   /* smaller font */
}

.table-card div {
    gap: 30px;           /* less gap in analytics panel */
    font-size: 0.95rem;  
}

.topbar {
    padding: 16px 24px;   /* slightly smaller topbar */
}

.page-title h1 {
    font-size: 1.6rem;
}

.page-title p {
    font-size: 0.9rem;
}

/* Chart */
#yieldChart {
    max-width: 400px;  /* smaller chart width */
    height: 220px;     /* smaller chart height */
    margin-bottom: 16px;
}
    </style>

    <!-- NEW: Replace old basic styles with this clean & spacious CSS -->
    <style>
        body { font-family: 'Quicksand', 'Segoe UI', sans-serif; background: var(--beige-light); color: var(--text); display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar { width: var(--sidebar-width); background: white; border-right: 1px solid var(--border); position: fixed; height: 100vh; overflow-y: auto; box-shadow: var(--shadow-sm); padding-bottom: 40px; }

        .logo-container { padding: 24px; border-bottom: 1px solid var(--border); }
        .nav-section { padding: 24px 0; }
        .nav-links .nav-link { padding: 16px 24px; margin-bottom:4px; border-radius: var(--radius-sm); }

        /* Main content */
        .main-content { flex: 1; margin-left: var(--sidebar-width); min-height: 100vh; }
        .topbar { background: white; padding: 24px 32px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: var(--shadow-sm); }

        .page-title h1 { font-size: 2rem; margin-bottom: 4px; }
        .page-title p { color: var(--text-light); font-size: 1rem; }

        /* Table cards */
        .table-card { background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); overflow: hidden; margin-bottom: 24px; }
        .table-header { padding: 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .data-table th, .data-table td { padding: 18px 24px; }
        .data-table th { background: #f9fafb; color: var(--text-light); font-weight: 600; }
        .data-table td { border-bottom: 1px solid var(--border); }

        /* Buttons */
        .icon-btn { border: none; background: none; cursor: pointer; font-size: 1.2rem; margin-right: 8px; transition: transform 0.2s; }
        .icon-btn:hover { transform: scale(1.2); }
        .confirm-btn { padding: 10px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
        .confirm-btn.danger { background: #b91c1c; color:white; }

        /* Modals */
        .modal-box { background:white; padding:28px; border-radius:18px; width: 360px; max-width: 95%; }
        .modal-box h3 { margin-bottom: 16px; font-size: 1.3rem; }
        .modal-box label { display:block; margin-top:12px; font-weight:600; font-size:0.95rem; }
        .modal-box input, .modal-box select { width:100%; padding:10px; margin-top:6px; border:1px solid #ccc; border-radius:8px; font-size:0.95rem; }

        /* Spacing for analytics panel */
        .table-card > div { gap: 50px; font-size:1rem; }

        /* Crop Images Styles */
        .crop-images-container {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .crop-image-item {
            position: relative;
            display: inline-block;
        }
        .crop-image-item.primary img {
            border: 2px solid #f28705;
        }
        .image-actions {
            position: absolute;
            top: -5px;
            right: -5px;
            display: flex;
            gap: 2px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 4px;
            padding: 2px;
        }
        .image-actions .icon-btn {
            font-size: 0.8rem;
            padding: 2px;
        }
        .add-image-btn {
            width: 50px;
            height: 50px;
            border: 2px dashed #ccc;
            border-radius: 4px;
            background: #f9fafb;
            color: #666;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .add-image-btn:hover {
            border-color: #1f8a70;
            color: #1f8a70;
            background: #f0fdf4;
        }

        /* Tabs */
        .tab-container {
            display: flex;
            gap: 4px;
            background: #f3f4f6;
            padding: 4px;
            border-radius: 8px;
        }
        .tab-btn {
            padding: 8px 16px;
            border: none;
            background: transparent;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            color: #6b7280;
            transition: all 0.2s;
        }
        .tab-btn.active {
            background: white;
            color: #1f8a70;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .tab-btn:hover:not(.active) {
            color: #374151;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }

        /* Table Controls */
        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding: 12px 0;
        }
        .search-box input {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            width: 250px;
            font-size: 0.9rem;
        }
        .pagination-info {
            color: #6b7280;
            font-size: 0.9rem;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }
        .pagination {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-btn {
            padding: 8px 16px;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        .page-btn:hover:not(:disabled) {
            background: #f9fafb;
            border-color: #1f8a70;
        }
        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .page-info {
            color: #6b7280;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory - BANTAY-ANI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Include the same admin styles as admin/users.php */
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
        .nav-section { padding: 20px 0; }
        .nav-title { padding: 0 24px 12px; font-size: 0.85rem; color: var(--text-light); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .nav-links { list-style: none; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 14px 24px; color: var(--text); text-decoration: none; transition: all 0.2s; border-left: 3px solid transparent; }
        .nav-link:hover { background: rgba(12, 92, 76, 0.05); color: var(--green); }
        .nav-link.active { background: rgba(12, 92, 76, 0.1); color: var(--green); border-left-color: var(--green); }
        .nav-icon { font-size: 1.2rem; width: 24px; text-align: center; }

        /* Main Content */
        .main-content { flex: 1; margin-left: var(--sidebar-width); min-height: 100vh; }
        .topbar { background: white; padding: 20px 32px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .page-title h1 { font-size: 1.8rem; color: var(--text); }
        .page-title p { color: var(--text-light); margin-top: 4px; }

        /* Tables */
        .content { padding: 32px; }
        .table-card { background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); overflow: hidden; }
        .table-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .table-header h3 { font-size: 1.2rem; color: var(--text); }
        .view-all { color: var(--green); text-decoration: none; font-weight: 500; font-size: 0.95rem; }
        .view-all:hover { text-decoration: underline; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 16px 24px; color: var(--text-light); font-weight: 600; border-bottom: 2px solid var(--beige); }
        .data-table td { padding: 16px 24px; border-bottom: 1px solid var(--border); }
        .data-table tr:last-child td { border-bottom: none; }
        .icon-btn { border: none; background: none; cursor: pointer; font-size: 1.1rem; margin-right: 8px; }
        .edit-btn { color: #1d4ed8; }
        .delete-btn { color: #b91c1c; }
        .icon-btn:hover { transform: scale(1.15); }

        /* Modals */
        .modal { display: none; position: fixed; inset:0; background: rgba(0,0,0,0.4); justify-content:center; align-items:center; z-index: 999; }
        .modal-box { background:white; padding:24px; border-radius:14px; width: 320px; }
        .modal-box h3 { margin-bottom:12px; }
        .modal-box label { display:block; margin-top:12px; font-weight:600; }
        .modal-box input, .modal-box select { width:100%; padding:8px; margin-top:4px; }
        .confirm-btn { padding:8px 14px; border:none; border-radius:8px; cursor:pointer; margin-right:8px; }
        .confirm-btn.danger { background: #b91c1c; color:white; }
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
            <li><a href="inventory.php" class="nav-link active"><span class="nav-icon">🌾</span><span>Inventory</span></a></li>
            <li><a href="benchmarking.php" class="nav-link"><span class="nav-icon">📊</span><span>Price Benchmarking</span></a></li>
            <li><a href="reviews.php" class="nav-link"><span class="nav-icon">⭐</span><span>Product Reviews</span></a></li>
        </ul>
    </div>
</aside>

<main class="main-content">
    <div style="width:100%; max-width:1600px; margin:0 auto; padding:32px 24px;">
    <div class="topbar" style="justify-content:center; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; flex-direction:column; align-items:center; gap:8px; text-align:center;">
    <div class="page-title">
        <h1>Inventory Management</h1>
        <p>Manage your crops inventory</p>
    </div>
    <a href="crop_add.php" class="confirm-btn">Add Crop</a>
</div>

<div class="content">
        <?php if (!empty($errors)): ?>
            <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 16px;border-radius:12px;margin-bottom:16px;">
                <?php foreach ($errors as $e): ?>
                    <div><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div style="background:#f0fdf4;border:1px solid #10b981;color:#047857;padding:12px 16px;border-radius:12px;margin-bottom:16px;">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['message'])): ?>
            <div style="background:#f0fdf4;border:1px solid #10b981;color:#047857;padding:12px 16px;border-radius:12px;margin-bottom:16px;">
                <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <!-- TOP SECTION: Harvested Crops (Left and Right) -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">
            <!-- LEFT: Harvest to be Done -->
            <div>
                <?php if (!empty($due_harvests)): ?>
                    <div class="table-card" style="width:100%; box-sizing:border-box; padding:24px;">
                        <div class="table-header">
                            <h3> Harvest to be Done</h3>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Crop</th>
                                    <th>Quantity</th>
                                    <th>Scheduled Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($due_harvests as $h): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($h['crop_name']) ?></td>
                                        <td><?= htmlspecialchars($h['quantity'].' '.$h['unit']) ?></td>
                                        <td><?= htmlspecialchars($h['harvest_date'] && $h['harvest_date'] !== '0000-00-00' ? (new DateTime($h['harvest_date']))->format('Y-m-d') : 'Not set') ?></td>
                                        <td>
                                            <form method="POST" style="display:inline; margin-right: 8px;">
                                                <input type="hidden" name="inventory_id" value="<?= (int)$h['inventory_id'] ?>">
                                                <button type="submit" name="harvest_action" value="confirm" class="confirm-btn" style="background: #3b82f6; color: white;">Confirm</button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="inventory_id" value="<?= (int)$h['inventory_id'] ?>">
                                                <button type="submit" name="harvest_action" value="cancel" class="confirm-btn danger">Cancel</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="table-card" style="width:100%; box-sizing:border-box; padding:24px;">
                        <div class="table-header">
                            <h3> Harvest to be Done</h3>
                        </div>
                        <div style="text-align:center; padding:40px; color:var(--text-light);">
                            No pending harvests
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="table-card" style="margin:0; width:100%; box-sizing:border-box; padding:20px;">
                    <div class="table-header" style="justify-content: space-between; padding: 0 0 12px 0; border-bottom: 1px solid var(--border);">
                        <h3 style="font-size: 1.1rem; margin: 0;">Weather & Alerts</h3>
                        <div style="display: flex; gap: 8px;">
                            <button onclick="refreshWeather()" class="confirm-btn" style="padding:6px 12px; font-size:0.85rem; background:#1f8a70; color:white; border:none; border-radius:6px; cursor:pointer;">🔄 Refresh</button>
                            <button onclick="clearAlerts()" class="confirm-btn" style="padding:6px 12px; font-size:0.85rem; background:#ef4444; color:white; border:none; border-radius:6px; cursor:pointer;">✕ Clear</button>
                        </div>
                    </div>
                    
                    <!-- Current Weather Display -->
                    <?php if (!empty($current_weather)): ?>
                    <div style="padding:12px 0; border-bottom:1px solid var(--border);">
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <div style="text-align: center;">
                                <div style="font-size:2rem; font-weight:bold; color:#1f8a70;">
                                    <?= number_format($current_weather['temperature_2m'] ?? 0, 1) ?>°C
                                </div>
                                <div style="color:var(--text-light); font-size:0.85rem;">Temperature</div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div style="text-align: center; padding: 8px; background: #f9fafb; border-radius: 6px;">
                                    <div style="font-size:1rem; font-weight:bold;">
                                        <?= $current_weather['relative_humidity_2m'] ?? 'N/A' ?>%
                                    </div>
                                    <div style="color:var(--text-light); font-size:0.75rem;">Humidity</div>
                                </div>
                                <div style="text-align: center; padding: 8px; background: #f9fafb; border-radius: 6px;">
                                    <div style="font-size:1rem; font-weight:bold;">
                                        <?= number_format($current_weather['wind_speed_10m'] ?? 0, 1) ?> km/h
                                    </div>
                                    <div style="color:var(--text-light); font-size:0.75rem;">Wind</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="padding:12px; color:var(--text-light); text-align:center; font-size:0.85rem;">
                        No weather data. <a href="#" onclick="refreshWeather(); return false;" style="color:#1f8a70; text-decoration:none;">Fetch now</a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Weather Alerts -->
                    <?php if (!empty($weather_alerts)): ?>
    <div style="padding-top:12px; display:flex; flex-direction:column; gap:8px;">
        <h4 style="font-size:0.9rem; color:var(--text-light); margin-bottom:4px;">Alerts</h4>
        <?php foreach ($weather_alerts as $alert): 
            // Pick colours by severity
            if ($alert['severity'] === 'High') {
                $bg     = '#fef2f2';
                $border = '#ef4444';
                $icon   = '🔴';
                $badge_bg    = '#fecaca';
                $badge_color = '#991b1b';
            } elseif ($alert['severity'] === 'Medium') {
                $bg     = '#fffbeb';
                $border = '#f59e0b';
                $icon   = '🟡';
                $badge_bg    = '#fde68a';
                $badge_color = '#92400e';
            } else {
                // Low
                $bg     = '#f0fdf4';
                $border = '#10b981';
                $icon   = '🟢';
                $badge_bg    = '#d1fae5';
                $badge_color = '#065f46';
            }
        ?>
            <div style="
                background:<?= $bg ?>;
                border-left: 3px solid <?= $border ?>;
                border-radius: 6px;
                padding: 10px 12px;
                display: flex;
                flex-direction: column;
                gap: 4px;
            ">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
                    <span style="font-weight:600; font-size:0.88rem;">
                        <?= $icon ?> <?= htmlspecialchars($alert['title']) ?>
                    </span>
                    <span style="
                        background:<?= $badge_bg ?>;
                        color:<?= $badge_color ?>;
                        font-size:0.72rem;
                        font-weight:700;
                        padding: 2px 8px;
                        border-radius: 20px;
                        white-space: nowrap;
                    "><?= htmlspecialchars($alert['severity']) ?></span>
                </div>
                <div style="font-size:0.82rem; color:var(--text-light); line-height:1.4;">
                    <?= htmlspecialchars($alert['message']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div style="padding-top:12px; text-align:center; color:#10b981; font-size:0.88rem;">
        ✅ No active weather alerts
    </div>
<?php endif; ?>
                </div>

            <!-- RIGHT: Harvested Crops -->
            <div>
                <?php if (!empty($harvested_crops)): ?>
                    <div class="table-card" style="width:100%; box-sizing:border-box; padding:24px;">
                        <div class="table-header">
                            <h3>Recently Harvested</h3>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Crop</th>
                                    <th>Quantity</th>
                                    <th>Harvest Date</th>
                                    <th>Harvested On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($harvested_crops as $h): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($h['crop_name']) ?></td>
                                        <td><?= htmlspecialchars($h['quantity'].' '.$h['unit']) ?></td>
                                        <td><?= htmlspecialchars($h['harvest_date'] && $h['harvest_date'] !== '0000-00-00' ? (new DateTime($h['harvest_date']))->format('Y-m-d') : 'Not set') ?></td>
                                        <td><?= htmlspecialchars($h['harvest_confirmed_at'] ? (new DateTime($h['harvest_confirmed_at']))->format('M d, H:i') : 'N/A') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="table-card" style="width:100%; box-sizing:border-box; padding:24px;">
                        <div class="table-header">
                            <h3>Recently Harvested</h3>
                        </div>
                        <div style="text-align:center; padding:40px; color:var(--text-light);">
                            No harvested crops yet
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- BOTTOM SECTION: All Crops with Tabs -->
        <div class="table-card" style="width:100%; box-sizing:border-box; padding:24px;">
            <div class="table-header">
                <h3>All Crops Inventory</h3>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <div class="tab-container">
                        <button class="tab-btn active" onclick="showTab('all')">All Crops</button>
                        <button class="tab-btn" onclick="showTab('available')">Available</button>
                        <button class="tab-btn" onclick="showTab('scheduled')">Scheduled</button>
                    </div>
                </div>
            </div>

            <div class="table-card" style="margin:0; width:100%; box-sizing:border-box; padding:20px;">
                    <div class="table-header" style="padding: 0 0 12px 0; border-bottom: 1px solid var(--border); justify-content: center;">
                        <h3 style="font-size: 1.1rem; margin: 0;">Yield Analytics</h3>
                    </div>
                    <div style="padding-top:12px;">
                        <div style="display: flex; flex-direction: column; gap: 12px; text-align: center;">
                            <div style="padding: 8px; background: #f9fafb; border-radius: 6px;">
                                <div style="font-size:1.2rem; font-weight:bold; color:#1f8a70;"><?= $analytics['total_crops'] ?></div>
                                <div style="color:var(--text-light); font-size:0.8rem;">Harvest Records</div>
                            </div>
                            <div style="padding: 8px; background: #f9fafb; border-radius: 6px;">
                                <div style="font-size:1.2rem; font-weight:bold; color:#1f8a70;"><?= $analytics['total_quantity'] ?></div>
                                <div style="color:var(--text-light); font-size:0.8rem;">Total Yield</div>
                            </div>
                            <div style="padding: 8px; background: #f9fafb; border-radius: 6px;">
                                <div style="font-size:1.2rem; font-weight:bold; color:#1f8a70;"><?= number_format($analytics['avg_yield'],2) ?></div>
                                <div style="color:var(--text-light); font-size:0.8rem;">Avg Yield</div>
                            </div>
                        </div>
                    </div>
                </div>
            
            <!-- All Crops Tab Content -->
            <div id="all-crops-tab" class="tab-content active">
                <div class="table-controls">
                    <div class="search-box">
                        <input type="text" id="search-input" placeholder="Search crops..." onkeyup="searchCrops()">
                    </div>
                    <div class="pagination-info">
                        Showing <span id="showing-count"><?= count($inventory) ?></span> of <?= count($inventory) ?> crops
                    </div>
                </div>
                
                <table class="data-table" id="crops-table">
                    <thead>
                        <tr>
                            <th>Crop</th>
                            <th>Images</th>
                            <th>Quantity</th>
                            <th>Harvest Date</th>
                            <th>Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($inventory)): ?>
                            <?php foreach ($inventory as $item): ?>
                                <tr class="crop-row" data-crop-name="<?= strtolower(htmlspecialchars($item['crop_name'])) ?>">
                                    <td><?= htmlspecialchars($item['crop_name']) ?></td>
                                    <td>
                                        <div class="crop-images-container">
                                            <?php 
                                            $images = $item['images'] ? explode(',', $item['images']) : [];
                                            $image_ids = $item['image_ids'] ? explode(',', $item['image_ids']) : [];
                                            foreach ($images as $index => $image_path): 
                                                if (!empty($image_path)):
                                                    $image_id = $image_ids[$index] ?? 0;
                                                    $is_primary = $index === 0;
                                            ?>
                                                <div class="crop-image-item <?= $is_primary ? 'primary' : '' ?>">
                                                    <img src="../<?= htmlspecialchars($image_path) ?>" alt="Crop image" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                                    <div class="image-actions">
                                                        <button type="button" class="icon-btn" onclick="setPrimaryImage(<?= $image_id ?>, <?= $item['inventory_id'] ?>)" title="Set as primary">
                                                            <i class="fa-solid fa-star" style="color: <?= $is_primary ? '#f28705' : '#ccc' ?>;"></i>
                                                        </button>
                                                        <button type="button" class="icon-btn delete-btn" onclick="deleteCropImage(<?= $image_id ?>, <?= $item['inventory_id'] ?>)" title="Delete image">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                            <button type="button" class="add-image-btn" onclick="openImageModal(<?= $item['inventory_id'] ?>)" title="Add image">
                                                <i class="fa-solid fa-plus"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($item['quantity'].' '.$item['unit']) ?></td>
                                    <td><?= htmlspecialchars($item['harvest_date'] && $item['harvest_date'] !== '0000-00-00' ? (new DateTime($item['harvest_date']))->format('Y-m-d') : 'Not set') ?></td>
                                    <td><?= number_format($item['price'],2) ?></td>
                                    <td>
                                        <a href="crop_edit.php?id=<?= $item['inventory_id'] ?>" class="icon-btn edit-btn" title="Edit Crop" style="margin-right: 8px;"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="inventory_id" value="<?= $item['inventory_id'] ?>">
                                            <button type="submit" class="icon-btn delete-btn" title="Delete Crop" onclick="return confirm('Are you sure you want to delete this crop?')"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center; padding:40px;">No crops found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <div class="pagination-container">
                    <div class="pagination">
                        <button class="page-btn" onclick="changePage('prev')" id="prev-btn"> Previous</button>
                        <span class="page-info">Page <span id="current-page">1</span> of <span id="total-pages">1</span></span>
                        <button class="page-btn" onclick="changePage('next')" id="next-btn">Next </button>
                    </div>
                </div>
            </div>
            
            <!-- Available Crops Tab Content -->
            <div id="available-crops-tab" class="tab-content">
                <div style="text-align:center; padding:40px; color:var(--text-light);">
                    Available crops (ready for sale) will appear here
                </div>
            </div>
            
            <!-- Scheduled Crops Tab Content -->
            <div id="scheduled-crops-tab" class="tab-content">
                <div style="text-align:center; padding:40px; color:var(--text-light);">
                    Scheduled crops (not yet harvested) will appear here
                </div>
            </div>
        </div>

<script>
// Delete crop image function
function deleteCropImage(imageId, inventoryId) {
    if (!confirm('Are you sure you want to delete this image?')) {
        return;
    }
    
    fetch('delete_crop_image.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            image_id: imageId,
            inventory_id: inventoryId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to delete image'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete image: ' + error.message);
    });
}

// Set primary image function  
function setPrimaryImage(imageId, inventoryId) {
    fetch('set_primary_image.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            image_id: imageId,
            inventory_id: inventoryId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to set primary image'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to set primary image: ' + error.message);
    });
}

// Open image modal function
function openImageModal(inventoryId) {
    // Implementation for image upload modal
    console.log('Open image modal for inventory:', inventoryId);
}
</script>

<!-- Tab and Pagination JavaScript -->
<script>
// Tab functionality
function showTab(tabName) {
    // Hide all tab contents
    const allTabs = document.querySelectorAll('.tab-content');
    allTabs.forEach(tab => tab.classList.remove('active'));
    
    // Remove active class from all tab buttons
    const allButtons = document.querySelectorAll('.tab-btn');
    allButtons.forEach(btn => btn.classList.remove('active'));
    
    // Show selected tab
    document.getElementById(tabName + '-crops-tab').classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
}

// Search functionality
function searchCrops() {
    const searchTerm = document.getElementById('search-input').value.toLowerCase();
    const rows = document.querySelectorAll('#crops-table .crop-row');
    
    rows.forEach(row => {
        const cropName = row.getAttribute('data-crop-name');
        if (cropName.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    updateShowingCount();
}

// Pagination variables
let currentPage = 1;
const rowsPerPage = 10;
let allRows = [];

// Initialize pagination
document.addEventListener('DOMContentLoaded', function() {
    allRows = Array.from(document.querySelectorAll('#crops-table .crop-row'));
    updatePagination();
});

function updatePagination() {
    const totalPages = Math.ceil(allRows.length / rowsPerPage);
    
    // Update page info
    document.getElementById('current-page').textContent = currentPage;
    document.getElementById('total-pages').textContent = totalPages || 1;
    
    // Show/hide rows for current page
    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = startIndex + rowsPerPage;
    
    allRows.forEach((row, index) => {
        if (index >= startIndex && index < endIndex) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update button states
    document.getElementById('prev-btn').disabled = currentPage === 1;
    document.getElementById('next-btn').disabled = currentPage >= totalPages;
    
    updateShowingCount();
}

function changePage(direction) {
    const totalPages = Math.ceil(allRows.length / rowsPerPage);
    
    if (direction === 'prev' && currentPage > 1) {
        currentPage--;
    } else if (direction === 'next' && currentPage < totalPages) {
        currentPage++;
    }
    
    updatePagination();
}

function updateShowingCount() {
    const visibleRows = allRows.filter(row => row.style.display !== 'none');
    const count = visibleRows.length;
    document.getElementById('showing-count').textContent = count;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Refresh weather data
function refreshWeather() {
    console.log('refreshWeather function called');
    
    const btn = document.querySelector('button[onclick="refreshWeather()"]');
    if (!btn) {
        console.error('Could not find refresh button');
        return;
    }
    
    console.log('Found button:', btn);
    btn.disabled = true;
    btn.textContent = '⏳ Updating...';
    
    const apiUrl = '../api/weather.php?action=refresh';
    console.log('Fetching from:', apiUrl);
    
    fetch(apiUrl, {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('Response received. Status:', response.status);
        if (!response.ok) {
            return response.text().then(text => {
                console.error('Response error text:', text);
                throw new Error(`HTTP ${response.status}: ${text}`);
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('Weather data received:', data);
        if (data.success || data.alerts_count !== undefined) {
            console.log('Success! Reloading page...');
            setTimeout(() => {
                location.reload();
            }, 500);
        } else if (data.error) {
            alert('Weather Error: ' + data.error);
            btn.disabled = false;
            btn.textContent = '🔄 Refresh';
        } else {
            console.log('Unexpected response structure');
            alert('Unexpected response: ' + JSON.stringify(data));
            btn.disabled = false;
            btn.textContent = '🔄 Refresh';
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('Failed to refresh weather: ' + error.message);
        btn.disabled = false;
        btn.textContent = '🔄 Refresh';
    });
}

// Clear all weather alerts
function clearAlerts() {
    if (!confirm('Clear all weather alerts?')) {
        return;
    }
    
    fetch('../api/clear_alerts.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        console.log('Clear alerts response:', data);
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to clear alerts'));
        }
    })
    .catch(error => {
        console.error('Clear alerts error:', error);
        alert('Failed to clear alerts: ' + error.message);
    });
}
</script>

<?php
if (!empty($_SESSION['weather_alerts_cleared'])) {
    unset($_SESSION['weather_alerts_cleared']);
}
?>

</body>
</html>
