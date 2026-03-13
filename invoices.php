<?php
session_start();
require_once __DIR__ . '/includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: user/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$first_name = $_SESSION['first_name'];

// Get profile image
$profile_img = null;
$stmt = $conn->prepare("SELECT img_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($profile_img);
$stmt->fetch();
$stmt->close();

// Fetch invoices based on user role and separate cooperative vs direct orders
if ($user_role === 'Buyer') {
    // Buyer sees invoices they need to pay
    $query = "
        SELECT i.*, 
       p.payment_status,
       CONCAT(u.first_name, ' ', u.last_name) as farmer_name,
       fp.farm_name,
       o.order_status,
       CASE 
           WHEN EXISTS(SELECT 1 FROM order_items oi WHERE oi.order_id = i.order_id AND oi.pool_id IS NOT NULL) 
           THEN 'Cooperative Pooling'
           ELSE 'Direct Order'
       END as order_type
FROM invoices i
LEFT JOIN payment p ON i.order_id = p.order_id
LEFT JOIN users u ON i.farmer_id = u.user_id
LEFT JOIN farmer_profiles fp ON i.farmer_id = fp.farmer_id
LEFT JOIN orders o ON i.order_id = o.order_id
WHERE i.buyer_id = ?
ORDER BY i.created_at DESC
    ";
} else if ($user_role === 'Farmer') {
    // Farmer sees invoices for their sales
    $query = "
        SELECT i.*, 
       p.payment_status,
       CONCAT(u.first_name, ' ', u.last_name) as buyer_name,
       c.company_name,
       o.order_status,
       CASE 
           WHEN EXISTS(SELECT 1 FROM order_items oi WHERE oi.order_id = i.order_id AND oi.pool_id IS NOT NULL) 
           THEN 'Cooperative Pooling'
           ELSE 'Direct Order'
       END as order_type
        FROM invoices i
        LEFT JOIN payment p ON i.order_id = p.order_id
        LEFT JOIN users u ON i.buyer_id = u.user_id
        LEFT JOIN buyer_profiles bp ON i.buyer_id = bp.buyer_id
        LEFT JOIN company_buyers cb ON cb.buyer_id = bp.buyer_id
        LEFT JOIN companies c ON c.company_id = cb.company_id
        LEFT JOIN orders o ON i.order_id = o.order_id
        WHERE i.farmer_id = ?
        ORDER BY i.created_at DESC
    ";
} else {
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$stats = [
    'total_invoices' => 0,
    'paid' => 0,
    'unpaid' => 0,
    'overdue' => 0,
    'total_amount' => 0
];

// Separate invoices by order type and compute status
$cooperative_invoices = [];
$direct_invoices = [];

foreach ($invoices as $invoice) {
    // Determine real-time status
    $payment_status = $invoice['payment_status'] ?? 'Unpaid';

    // If unpaid and due date passed → mark overdue
    if (
        $payment_status !== 'Paid' &&
        !empty($invoice['due_date']) &&
        strtotime($invoice['due_date']) < time()
    ) {
        $payment_status = 'Overdue';
    }

    $invoice['computed_status'] = $payment_status;

    // Separate by order type
    if ($invoice['order_type'] === 'Cooperative Pooling') {
        $cooperative_invoices[] = $invoice;
    } else {
        $direct_invoices[] = $invoice;
    }

    $stats['total_invoices']++;
    $stats['total_amount'] += $invoice['total_amount'];

    switch ($payment_status) {
        case 'Paid':
            $stats['paid']++;
            break;
        case 'Unpaid':
            $stats['unpaid']++;
            break;
        case 'Overdue':
            $stats['overdue']++;
            break;
    }
}
unset($invoice);

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ./user/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices - BANTAY-ANI</title>
    <style>
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
            --shadow-sm: 0 2px 8px rgba(12, 92, 76, 0.08);
            --shadow-md: 0 8px 20px rgba(12, 92, 76, 0.12);
            --shadow-lg: 0 25px 60px rgba(12, 92, 76, 0.2);
            --radius-sm: 12px;
            --radius-md: 20px;
            --radius-lg: 28px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Quicksand', 'Segoe UI', sans-serif;
            background: var(--beige-light);
            color: var(--text);
            line-height: 1.5;
            min-height: 100vh;
        }

        /* Navigation */
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

        .user-menu {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--green-light), var(--green));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .logout-btn {
            background: var(--beige);
            color: var(--text-light);
            border: none;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: rgba(185, 28, 28, 0.1);
            color: #b91c1c;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 32px;
        }

        .page-header h1 {
            font-size: 2.5rem;
            color: var(--green-dark);
            margin-bottom: 8px;
        }

        .page-header p {
            color: var(--text-light);
            font-size: 1.1rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-value.green { color: var(--green); }
        .stat-value.orange { color: var(--orange); }
        .stat-value.red { color: #dc2626; }

        .stat-label {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Invoices Table */
        .invoices-section {
            background: white;
            border-radius: var(--radius-md);
            padding: 32px;
            box-shadow: var(--shadow-sm);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--green-dark);
        }

        .invoices-table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoices-table th {
            text-align: left;
            padding: 12px;
            background: var(--beige);
            color: var(--text);
            font-weight: 600;
            border-bottom: 2px solid var(--border);
        }

        .invoices-table td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--border);
        }

        .invoices-table tr:hover {
            background: rgba(31, 138, 112, 0.02);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-paid { background: rgba(34, 197, 94, 0.1); color: #16a34a; }
        .status-unpaid { background: rgba(251, 191, 36, 0.1); color: #d97706; }
        .status-overdue { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
        .status-partially { background: rgba(59, 130, 246, 0.1); color: #2563eb; }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--green);
            color: white;
        }

        .btn-primary:hover {
            background: var(--green-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(31, 138, 112, 0.3);
        }

        .btn-secondary {
            background: var(--beige);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: #e8dfd0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 32px;
            color: var(--text-light);
            font-size: 0.9rem;
            border-top: 1px solid var(--border);
            margin-top: 48px;
            background: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                flex-wrap: wrap;
            }

            .container {
                padding: 16px;
            }

            .invoices-table {
                font-size: 0.9rem;
            }

            .invoices-table th,
            .invoices-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="logo-container">
            <div class="logo">BA</div>
            <div class="logo-text">BANTAY<span>ANI</span></div>
        </div>
        
        <div class="nav-links">
            <?php if ($user_role === 'Farmer'): ?>
                <a href="index.php" class="nav-link">Dashboard</a>
                <a href="marketplace.php" class="nav-link">Marketplace</a>
                <a href="./farmer/inventory.php" class="nav-link">My Inventory</a>
                <a href="./farmer/orders.php" class="nav-link">Orders</a>
                <a href="./farmer/cooperative.php" class="nav-link">Cooperative</a>
                <a href="reports.php" class="nav-link">Reports</a>
                <a href="announcements.php" class="nav-link">Announcements</a>
                <a href="invoices.php" class="nav-link active">Invoices</a>
                <a href="./farmer/notifications.php" class="nav-link">Notifications</a>
            <?php elseif ($user_role === 'Buyer'): ?>
                <a href="index.php" class="nav-link">Dashboard</a>
                <a href="marketplace.php" class="nav-link">Marketplace</a>
                <a href="./buyer/cart.php" class="nav-link">Cart</a>
                <a href="./buyer/orders.php" class="nav-link">My Orders</a>
                <a href="announcements.php" class="nav-link">Announcements</a>
                <a href="invoices.php" class="nav-link active">Invoices</a>
                <a href="./buyer/notifications.php" class="nav-link">Notifications</a>
            <?php endif; ?>
        </div>
        
        <div class="user-menu">
            <?php
                $profile_img = trim($profile_img ?? '');
                $absolute_path = $_SERVER['DOCUMENT_ROOT'] . '/bantayani/' . $profile_img;
                $public_path   = '/bantayani/' . $profile_img;
            ?>
            <a href="./user/profile.php" title="View Profile">
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
            <form method="GET" style="display:inline;">
                <button type="submit" name="logout" value="1" class="logout-btn">
                    Log Out
                </button>
            </form>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>📄 Invoices</h1>
            <p><?= $user_role === 'Buyer' ? 'Track your payment invoices' : 'Manage your sales invoices' ?></p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_invoices'] ?></div>
                <div class="stat-label">Total Invoices</div>
            </div>
            <div class="stat-card">
                <div class="stat-value green"><?= $stats['paid'] ?></div>
                <div class="stat-label">Paid</div>
            </div>
            <div class="stat-card">
                <div class="stat-value orange"><?= $stats['unpaid'] ?></div>
                <div class="stat-label">Unpaid</div>
            </div>
            <div class="stat-card">
                <div class="stat-value red"><?= $stats['overdue'] ?></div>
                <div class="stat-label">Overdue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value green">₱<?= number_format($stats['total_amount'], 2) ?></div>
                <div class="stat-label">Total Amount</div>
            </div>
        </div>

        <!-- Cooperative Pooling Invoices -->
        <div class="invoices-section">
            <div class="section-header">
                <h2 class="section-title">🤝 Cooperative Pooling Invoices</h2>
                <span style="color: var(--text-light); font-size: 0.9rem;"><?= count($cooperative_invoices) ?> invoices</span>
            </div>

            <?php if (empty($cooperative_invoices)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🤝</div>
                    <h2>No Cooperative Pooling Invoices</h2>
                    <p>Cooperative pooling invoices will appear here once orders are confirmed.</p>
                </div>
            <?php else: ?>
                <table class="invoices-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th><?= $user_role === 'Buyer' ? 'Farmer' : 'Buyer' ?></th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Order Status</th>
                            <th>Due Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cooperative_invoices as $invoice): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong></td>
                                <td><?= date('M d, Y', strtotime($invoice['created_at'])) ?></td>
                                <td>
                                    <?php if ($user_role === 'Buyer'): ?>
                                        <?= htmlspecialchars($invoice['farmer_name']) ?><br>
                                        <small style="color: var(--text-light);"><?= htmlspecialchars($invoice['farm_name'] ?? '') ?></small>
                                    <?php else: ?>
                                        <?= htmlspecialchars($invoice['buyer_name']) ?><br>
                                        <small style="color: var(--text-light);"><?= htmlspecialchars($invoice['company_name'] ?? '') ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><strong>₱<?= number_format($invoice['total_amount'], 2) ?></strong></td>
                                <td>
                                    <?php if ($invoice['order_status'] === 'Cancelled'): ?>
                                        <span class="status-badge status-cancelled">Cancelled</span>
                                    <?php else: ?>
                                    <span class="status-badge status-<?= strtolower(str_replace(' ', '', $invoice['computed_status'])) ?>">
                                        <?= $invoice['computed_status'] ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($invoice['order_status'] ?? '-') ?></td>
                                <td>
                                    <?php if ($invoice['due_date']): ?>
                                        <?= date('M d, Y', strtotime($invoice['due_date'])) ?>
                                        <?php if (strtotime($invoice['due_date']) < time() && $invoice['payment_status'] !== 'Paid'): ?>
                                            <br><small style="color: #dc2626;">⚠️ Overdue</small>
                                        <?php elseif ($invoice['order_status'] === 'Cancelled'): ?>
                                            <br><small style="color: #dc2626;">⚠️ Cancelled</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="invoice_view.php?id=<?= $invoice['invoice_id'] ?>" class="btn btn-primary">
                                        View
                                    </a>
                                    <?php if ($invoice['computed_status'] === 'Paid'): ?>
                                        <a href="receipt_view.php?invoice=<?= $invoice['invoice_id'] ?>" class="btn btn-secondary">
                                            Receipt
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Direct Order Invoices -->
        <div class="invoices-section" style="margin-top: 32px;">
            <div class="section-header">
                <h2 class="section-title">🛒 Direct Order Invoices</h2>
                <span style="color: var(--text-light); font-size: 0.9rem;"><?= count($direct_invoices) ?> invoices</span>
            </div>

            <?php if (empty($direct_invoices)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">�</div>
                    <h2>No Direct Order Invoices</h2>
                    <p>Direct order invoices will appear here once orders are confirmed.</p>
                </div>
            <?php else: ?>
                <table class="invoices-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th><?= $user_role === 'Buyer' ? 'Farmer' : 'Buyer' ?></th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Order Status</th>
                            <th>Due Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($direct_invoices as $invoice): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong></td>
                                <td><?= date('M d, Y', strtotime($invoice['created_at'])) ?></td>
                                <td>
                                    <?php if ($user_role === 'Buyer'): ?>
                                        <?= htmlspecialchars($invoice['farmer_name']) ?><br>
                                        <small style="color: var(--text-light);"><?= htmlspecialchars($invoice['farm_name'] ?? '') ?></small>
                                    <?php else: ?>
                                        <?= htmlspecialchars($invoice['buyer_name']) ?><br>
                                        <small style="color: var(--text-light);"><?= htmlspecialchars($invoice['company_name'] ?? '') ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><strong>₱<?= number_format($invoice['total_amount'], 2) ?></strong></td>
                                <td>
                                    <?php if ($invoice['order_status'] === 'Cancelled'): ?>
                                        <span class="status-badge status-cancelled">Cancelled</span>
                                    <?php else: ?>
                                    <span class="status-badge status-<?= strtolower(str_replace(' ', '', $invoice['computed_status'])) ?>">
                                        <?= $invoice['computed_status'] ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($invoice['order_status'] ?? '-') ?></td>
                                <td>
                                    <?php if ($invoice['due_date']): ?>
                                        <?= date('M d, Y', strtotime($invoice['due_date'])) ?>
                                        <?php if (strtotime($invoice['due_date']) < time() && $invoice['payment_status'] !== 'Paid'): ?>
                                            <br><small style="color: #dc2626;">⚠️ Overdue</small>
                                        <?php elseif ($invoice['order_status'] === 'Cancelled'): ?>
                                            <br><small style="color: #dc2626;">⚠️ Cancelled</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="invoice_view.php?id=<?= $invoice['invoice_id'] ?>" class="btn btn-primary">
                                        View
                                    </a>
                                    <?php if ($invoice['computed_status'] === 'Paid'): ?>
                                        <a href="receipt_view.php?invoice=<?= $invoice['invoice_id'] ?>" class="btn btn-secondary">
                                            Receipt
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>© <?= date('Y') ?> BANTAY-ANI Farm-to-Market System. All rights reserved.</p>
    </footer>
</body>
</html>
