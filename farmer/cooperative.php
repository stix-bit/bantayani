<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_login('Farmer');
require_once __DIR__ . '/../includes/config.php';

$farmer_id = (int) $_SESSION['user_id'];
$message = '';
$error = '';

// Optional columns (from cooperative_pools_migration.sql) - use if present
$has_unit_price = false;
$cols = $conn->query("SHOW COLUMNS FROM cooperative_pools LIKE 'unit_price'");
if ($cols && $cols->num_rows > 0) $has_unit_price = true;

$has_pool_status = false;
$cols = $conn->query("SHOW COLUMNS FROM cooperative_pools LIKE 'status'");
if ($cols && $cols->num_rows > 0) $has_pool_status = true;

// Handle cooperative order status update by farmer (for pool member orders)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'], $_POST['order_id'], $_POST['order_status'])) {
    $order_id = (int)($_POST['order_id']);
    $order_status = trim($_POST['order_status']);
    $valid_status = ['Pending', 'Confirmed', 'Shipped', 'Delivered', 'Cancelled'];
    if ($order_id > 0 && in_array($order_status, $valid_status, true)) {
        // ensure farmer is member of at least one pool in the order
        $validation_stmt = $conn->prepare(
            "SELECT 1 FROM order_items oi JOIN cooperative_members cm ON oi.pool_id = cm.pool_id WHERE oi.order_id = ? AND cm.farmer_id = ? LIMIT 1"
        );
        $validation_stmt->bind_param('ii', $order_id, $farmer_id);
        $validation_stmt->execute();
        $validation_stmt->store_result();
        if ($validation_stmt->num_rows > 0) {
            // Update order status
            $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
            $stmt->bind_param('si', $order_status, $order_id);
            $stmt->execute();
            $stmt->close();

            // If order is confirmed, mark related payments and invoice as paid
            if ($order_status === 'Confirmed') {
                $pay_stmt = $conn->prepare("
                    UPDATE payment
                    SET payment_status = 'Paid',
                        payment_date = NOW()
                    WHERE order_id = ?
                ");
                if ($pay_stmt) {
                    $pay_stmt->bind_param('i', $order_id);
                    $pay_stmt->execute();
                    $pay_stmt->close();
                }

                $inv_stmt = $conn->prepare("
                    UPDATE invoices
                    SET payment_status = 'Paid'
                    WHERE order_id = ?
                ");
                if ($inv_stmt) {
                    $inv_stmt->bind_param('i', $order_id);
                    $inv_stmt->execute();
                    $inv_stmt->close();
                }
            }

            $message = 'Order status updated successfully.';
        } else {
            $error = 'You do not have permission to update this order status.';
        }
        $validation_stmt->close();
    } else {
        $error = 'Invalid order status selection.';
    }
}

// Handle contribute POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contribute'])) {
    $pool_id = (int) ($_POST['pool_id'] ?? 0);
    $inventory_id = (int) ($_POST['inventory_id'] ?? 0);
    $quantity = (float) ($_POST['quantity'] ?? 0);

    if ($pool_id > 0 && $inventory_id > 0 && $quantity > 0) {
        // Verify pool exists and get crop_id
        $stmt = $conn->prepare("SELECT crop_id FROM cooperative_pools WHERE pool_id = ?");
        $stmt->bind_param("i", $pool_id);
        $stmt->execute();
        $pool = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$pool) {
            $error = 'Invalid pool.';
        } else {
            // Verify inventory belongs to this farmer and matches pool crop
            $stmt = $conn->prepare("SELECT quantity, price FROM crops_inventory WHERE inventory_id = ? AND farmer_id = ? AND crop_id = ?");
            $stmt->bind_param("iii", $inventory_id, $farmer_id, $pool['crop_id']);
            $stmt->execute();
            $inv = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$inv) {
                $error = 'Invalid inventory or crop does not match pool.';
            } elseif ($inv['quantity'] < $quantity) {
                $error = 'Not enough quantity in your inventory (max ' . (float) $inv['quantity'] . ').';
            } else {
                $conn->begin_transaction();
                try {
                    // Deduct from farmer inventory
                    $stmt = $conn->prepare("UPDATE crops_inventory SET quantity = quantity - ? WHERE inventory_id = ? AND farmer_id = ?");
                    $stmt->bind_param("dii", $quantity, $inventory_id, $farmer_id);
                    $stmt->execute();
                    $stmt->close();

                    // Update or insert cooperative_members (any farmer can contribute)
                    $stmt = $conn->prepare("SELECT quantity_contributed FROM cooperative_members WHERE pool_id = ? AND farmer_id = ?");
                    $stmt->bind_param("ii", $pool_id, $farmer_id);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($res) {
                        $stmt = $conn->prepare("UPDATE cooperative_members SET quantity_contributed = quantity_contributed + ? WHERE pool_id = ? AND farmer_id = ?");
                        $stmt->bind_param("dii", $quantity, $pool_id, $farmer_id);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        $stmt = $conn->prepare("INSERT INTO cooperative_members (pool_id, farmer_id, quantity_contributed) VALUES (?, ?, ?)");
                        $stmt->bind_param("iid", $pool_id, $farmer_id, $quantity);
                        $stmt->execute();
                        $stmt->close();
                    }

                    // Update pool total_quantity
                    $stmt = $conn->prepare("UPDATE cooperative_pools SET total_quantity = IFNULL(total_quantity, 0) + ? WHERE pool_id = ?");
                    $stmt->bind_param("di", $quantity, $pool_id);
                    $stmt->execute();
                    $stmt->close();

                    // If pool has unit_price column and is not set, set from this contribution's price
                    if ($has_unit_price) {
                        $stmt = $conn->prepare("UPDATE cooperative_pools SET unit_price = COALESCE(unit_price, ?) WHERE pool_id = ? AND (unit_price IS NULL OR unit_price = 0)");
                        $stmt->bind_param("di", $inv['price'], $pool_id);
                        $stmt->execute();
                        $stmt->close();
                    }

                    $conn->commit();
                    $message = 'Contribution added successfully.';
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Failed to contribute: ' . $e->getMessage();
                }
            }
        }
    } else {
        $error = 'Please select a pool, your inventory, and a valid quantity.';
    }
}

// Create new pool (any farmer can create a pool for a crop)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_pool'])) {
    $crop_id = (int) ($_POST['crop_id'] ?? 0);
    if ($crop_id > 0) {
        if ($has_pool_status) {
            $stmt = $conn->prepare("INSERT INTO cooperative_pools (crop_id, total_quantity, status) VALUES (?, 0, 'Open')");
            $stmt->bind_param("i", $crop_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO cooperative_pools (crop_id, total_quantity) VALUES (?, 0)");
            $stmt->bind_param("i", $crop_id);
        }
        $stmt->execute();
        $stmt->close();
        $message = 'New cooperative pool created. You can contribute to it below.';
    } else {
        $error = 'Please select a crop.';
    }
}

// Load all pools (any farmer can see and contribute to any pool)
$select_extras = ($has_unit_price ? ", p.unit_price" : "");
$status_select = $has_pool_status ? ", p.status" : "";
$sql = "SELECT p.pool_id, p.crop_id, p.total_quantity, c.crop_name, p.unit $select_extras $status_select
        FROM cooperative_pools p
        JOIN crops c ON p.crop_id = c.crop_id
        ORDER BY c.crop_name, p.pool_id";
$pools_result = $conn->query($sql);
$pools = $pools_result ? $pools_result->fetch_all(MYSQLI_ASSOC) : [];

// Pools the farmer is a member of for management section
$member_pools_sql = "
    SELECT p.pool_id, p.crop_id, p.total_quantity, c.crop_name, p.unit {$select_extras} {$status_select}
    FROM cooperative_pools p
    JOIN crops c ON p.crop_id = c.crop_id
    JOIN cooperative_members m ON p.pool_id = m.pool_id
    WHERE m.farmer_id = ?
    GROUP BY p.pool_id  
    ORDER BY p.created_at DESC
";
$member_stmt = $conn->prepare($member_pools_sql);
$member_stmt->bind_param('i', $farmer_id);
$member_stmt->execute();
$member_pools = $member_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$member_stmt->close();

// My contributions per pool
$my_contrib = [];
$stmt = $conn->prepare("SELECT pool_id, quantity_contributed FROM cooperative_members WHERE farmer_id = ?");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$cr = $stmt->get_result();
while ($row = $cr->fetch_assoc()) {
    $my_contrib[$row['pool_id']] = (float) $row['quantity_contributed'];
}
$stmt->close();

// Cooperative orders where this farmer has contributed via pool items
// Use cooperative pool unit_price as fallback so totals are not zero
$coop_orders_sql = "
    SELECT 
        o.order_id, 
        o.order_date, 
        o.order_status, 
        SUM(oi.quantity * COALESCE(ci.price, cp.unit_price, 0)) AS total_amount
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN cooperative_members cm ON oi.pool_id = cm.pool_id
    LEFT JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
    LEFT JOIN cooperative_pools cp ON oi.pool_id = cp.pool_id
    WHERE cm.farmer_id = ?
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
";
$coop_orders_stmt = $conn->prepare($coop_orders_sql);
$coop_orders_stmt->bind_param('i', $farmer_id);
$coop_orders_stmt->execute();
$my_coop_orders = $coop_orders_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$coop_orders_stmt->close();

// My inventory (for contribution form) - all my crops
$stmt = $conn->prepare("
    SELECT ci.inventory_id, ci.crop_id, ci.quantity, c.crop_name, ci.unit
    FROM crops_inventory ci
    JOIN crops c ON ci.crop_id = c.crop_id
    WHERE ci.farmer_id = ? AND ci.quantity > 0
    ORDER BY c.crop_name
");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$my_inventory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Load all crop categories (for selection)
$crop_categories = $conn->query("SELECT category_id, category_name FROM crop_categories ORDER BY display_order, category_name")->fetch_all(MYSQLI_ASSOC);

// Load crops by category (all crops available, deduplicated by crop_id automatically)
$crops_by_category = [];
$crops_result = $conn->query("SELECT crop_id, crop_name, category_id FROM crops ORDER BY category_id, crop_name");
if ($crops_result) {
    while ($row = $crops_result->fetch_assoc()) {
        $catId = (int)$row['category_id'];
        $cropId = (int)$row['crop_id'];
        if (!isset($crops_by_category[$catId])) {
            $crops_by_category[$catId] = [];
        }
        $crops_by_category[$catId][$cropId] = $row['crop_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cooperative Pools - BANTAY-ANI</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap');
        :root {
            --green-dark: #0c5c4c;
            --green: #1f8a70;
            --beige-light: #fcfaf6;
            --text: #1f2933;
            --text-light: #4c5662;
            --border: #e4e7eb;
            --radius: 12px;
            --shadow: 0 2px 8px rgba(12,92,76,0.08);
        }
        body { font-family: 'Quicksand', sans-serif; background: var(--beige-light); color: var(--text); margin: 0; padding: 24px; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: var(--green-dark); margin-bottom: 8px; }
        .subtitle { color: var(--text-light); margin-bottom: 24px; }
        .card { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); padding: 24px; margin-bottom: 24px; }
        .card h2 { font-size: 1.1rem; margin-bottom: 16px; color: var(--green-dark); }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid var(--border); }
        th { color: var(--text-light); font-weight: 600; }
        .msg { padding: 12px; border-radius: 8px; margin-bottom: 16px; }
        .msg.success { background: #d1fae5; color: #065f46; }
        .msg.error { background: #fee2e2; color: #991b1b; }
        label { display: block; font-weight: 600; margin-top: 12px; }
        input, select { width: 100%; max-width: 280px; padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; margin-top: 4px; }
        .btn { display: inline-block; padding: 10px 18px; background: var(--green); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration: none; margin-top: 12px; }
        .btn:hover { background: var(--green-dark); }
        .btn-secondary { background: #6b7280; }
        .btn-secondary:hover { background: #4b5563; }
        .nav { margin-bottom: 24px; }
        .nav a { color: var(--green); text-decoration: none; margin-right: 16px; }
        .nav a:hover { text-decoration: underline; }
        .badge { font-size: 0.85rem; padding: 2px 8px; border-radius: 6px; background: rgba(31,138,112,0.15); color: var(--green-dark); }
    </style>
</head>
<body>
<div class="container">
    <div class="nav">
        <a href="../index.php">← Dashboard</a>
        <a href="inventory.php">My Inventory</a>
    </div>

    <h1>Cooperative Pools</h1>
    <p class="subtitle">Mag-ambag ng iyong ani sa anumang pool. Ang sinumang magsasaka ay maaaring mag-ambag sa anumang pool upang makatulong na matupad ang malalaking dami ng mga order.</p>

    <?php if ($message): ?>
        <div class="msg success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="msg error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Create new pool (any farmer) -->
    <div class="card">
        <h2>Create a new pool</h2>
        <form method="post">
            <input type="hidden" name="create_pool" value="1">
            <label>Crop Category</label>
            <select name="category_id" id="category_id" required>
                <option value="">Select category</option>
                <?php foreach ($crop_categories as $cat): ?>
                    <option value="<?= (int)$cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Crop</label>
            <select name="crop_id" id="crop_id" required>
                <option value="">Select crop</option>
                <!-- populated by JS based on selected category -->
            </select>

            <button type="submit" class="btn">Create pool</button>
        </form>
    </div>

    <!-- Contribute to a pool -->
    <div class="card">
        <h2>Contribute to a pool</h2>
        <p style="color: var(--text-light); margin-bottom: 16px;">Pumili ng anumang pool at kung gaano karami sa iyong imbentaryo ang iaambag. Ang iyong stock ay ibabawas at idaragdag sa pool.</p>
        <form method="post">
            <input type="hidden" name="contribute" value="1">
            <label>Pool</label>
            <select name="pool_id" id="pool_id" required>
                <option value="">Select pool</option>
                <?php foreach ($pools as $p): ?>
                    <option value="<?= (int)$p['pool_id'] ?>" data-crop-id="<?= (int)$p['crop_id'] ?>">
                        <?= htmlspecialchars($p['crop_name']) ?> — <?= number_format((float)($p['total_quantity'] ?? 0), 2) ?> <?= htmlspecialchars($p['unit']) ?> available
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Your inventory (same crop as pool)</label>
            <select name="inventory_id" id="inventory_id" required>
                <option value="">Select your inventory</option>
                <?php foreach ($my_inventory as $inv): ?>
                    <option value="<?= (int)$inv['inventory_id'] ?>" data-crop-id="<?= (int)$inv['crop_id'] ?>" data-max="<?= (float)$inv['quantity'] ?>">
                        <?= htmlspecialchars($inv['crop_name']) ?> — <?= number_format((float)$inv['quantity'], 2) ?> <?= htmlspecialchars($inv['unit']) ?> available
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Dami ng iaambag</label>
            <input type="number" name="quantity" id="quantity" step="0.01" min="0.01" required placeholder="0">
            <button type="submit" class="btn">Iambag</button>
        </form>
    </div>

    <!-- List all pools (any farmer can see all) -->
    <div class="card">
        <h2>All cooperative pools</h2>
        <?php if (empty($pools)): ?>
            <p style="color: var(--text-light);">Wala pang pool. Gumawa ng isa sa itaas, pagkatapos ay mag-ambag.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Crop</th>
                        <th>Total quantity</th>
                        <?php if ($has_unit_price): ?><th>Unit price</th><?php endif; ?>
                        <th>Your contribution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pools as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['crop_name']) ?></td>
                            <td><?= number_format((float)($p['total_quantity'] ?? 0), 2) ?> <?= htmlspecialchars($p['unit']) ?></td>
                            <?php if ($has_unit_price): ?>
                                <td><?= isset($p['unit_price']) && $p['unit_price'] != null ? '₱' . number_format((float)$p['unit_price'], 2) : '—' ?></td>
                            <?php endif; ?>
                            <td>
                                <?php if (isset($my_contrib[$p['pool_id']]) && $my_contrib[$p['pool_id']] > 0): ?>
                                    <span class="badge"><?= number_format($my_contrib[$p['pool_id']], 2) ?> <?= htmlspecialchars($p['unit']) ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Pool management for member (farmer) -->
    <div class="card">
        <h2>My Cooperative Pool Memberships</h2>
        <?php if (empty($member_pools)): ?>
            <p style="color: var(--text-light);">You are not a member of any cooperative pool yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Pool</th>
                        <th>Total quantity</th>
                        <?php if ($has_unit_price): ?><th>Unit price</th><?php endif; ?>
                        <th>Iyong ambag</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($member_pools as $mp): ?>
                        <tr>
                            <td><?= htmlspecialchars($mp['crop_name']) ?></td>
                            <td><?= number_format((float)($mp['total_quantity'] ?? 0), 2) ?> <?= htmlspecialchars($mp['unit']) ?></td>
                            <?php if ($has_unit_price): ?>
                                <td><?= isset($mp['unit_price']) && $mp['unit_price'] != null ? '₱' . number_format((float)$mp['unit_price'], 2) : '—' ?></td>
                            <?php endif; ?>
                            <td>
                                <?php if (isset($my_contrib[$mp['pool_id']]) && $my_contrib[$mp['pool_id']] > 0): ?>
                                    <span class="badge"><?= number_format($my_contrib[$mp['pool_id']], 2) ?> <?= htmlspecialchars($mp['unit']) ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Cooperative orders for member (farmer) -->
    <div class="card">
        <h2>My Cooperative Pool Orders</h2>
        <?php if (empty($my_coop_orders)): ?>
            <p style="color: var(--text-light);">No cooperative pool orders found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Update status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_coop_orders as $order): ?>
                        <tr>
                            <td><?= (int)$order['order_id'] ?></td>
                            <td><?= htmlspecialchars($order['order_date']) ?></td>
                            <td><?= htmlspecialchars($order['order_status']) ?></td>
                            <td>₱<?= number_format((float)$order['total_amount'], 2) ?></td>
                            <td>
                                <form method="POST" style=" display:flex; gap:6px; align-items:center;">
                                    <input type="hidden" name="update_order_status" value="1">
                                    <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                                    <select name="order_status" style="font-size:0.9rem;">
                                        <?php foreach (['Pending','Confirmed','Shipped','Delivered','Cancelled'] as $status): ?>
                                            <option value="<?= $status ?>" <?= ($order['order_status'] === $status ? 'selected' : '') ?>><?= $status ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-secondary" style="padding:6px 10px;">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<script>
// Populate crop dropdown from selected category using all crops (deduplicated by crop ID)
var cropsByCategory = <?php echo json_encode($crops_by_category); ?>;

document.getElementById('category_id').addEventListener('change', function() {
    var categoryId = this.value;
    var cropSelect = document.getElementById('crop_id');
    cropSelect.innerHTML = '<option value="">Select crop</option>';

    if (categoryId && cropsByCategory[categoryId]) {
        Object.keys(cropsByCategory[categoryId]).sort(function(a,b){
            return cropsByCategory[categoryId][a].localeCompare(cropsByCategory[categoryId][b]);
        }).forEach(function(cropId) {
            var option = document.createElement('option');
            option.value = cropId;
            option.text = cropsByCategory[categoryId][cropId];
            cropSelect.appendChild(option);
        });
    }
});

// Existing contribution filters for pool/inventory selection

document.getElementById('pool_id').addEventListener('change', function() {
    var cropId = this.options[this.selectedIndex].dataset.cropId;
    var invSelect = document.getElementById('inventory_id');
    for (var i = 0; i < invSelect.options.length; i++) {
        var opt = invSelect.options[i];
        if (opt.value === '') { opt.style.display = ''; continue; }
        opt.style.display = opt.dataset.cropId === cropId ? '' : 'none';
        if (opt.dataset.cropId !== cropId) opt.selected = false;
        else if (invSelect.value === '' && opt.dataset.cropId === cropId) invSelect.value = opt.value;
    }
    var q = document.getElementById('quantity');
    var sel = invSelect.options[invSelect.selectedIndex];
    if (sel && sel.dataset.max) q.max = sel.dataset.max;
});
document.getElementById('inventory_id').addEventListener('change', function() {
    var sel = this.options[this.selectedIndex];
    var q = document.getElementById('quantity');
    if (sel && sel.dataset.max) { q.max = sel.dataset.max; q.placeholder = 'Max ' + sel.dataset.max; }
});
</script>
</body>
</html>
