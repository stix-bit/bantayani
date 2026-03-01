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

// Get invoice ID
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($invoice_id <= 0) {
    header('Location: invoices.php');
    exit;
}

// Fetch invoice with details
$query = "
    SELECT i.*,
           o.order_date, o.order_status,
           CONCAT(buyer.first_name, ' ', buyer.last_name) as buyer_name,
           buyer.email as buyer_email,
           buyer.contact_number as buyer_contact,
           buyer.address as buyer_address,
           c.company_name,
           CONCAT(farmer.first_name, ' ', farmer.last_name) as farmer_name,
           farmer.email as farmer_email,
           farmer.contact_number as farmer_contact,
           fp.farm_name,
           fp.farm_location
    FROM invoices i
    LEFT JOIN orders o ON i.order_id = o.order_id
    LEFT JOIN users buyer ON i.buyer_id = buyer.user_id
    LEFT JOIN buyer_profiles bp ON i.buyer_id = bp.buyer_id
    LEFT JOIN company_buyers cb ON cb.buyer_id = bp.buyer_id
    LEFT JOIN companies c ON c.company_id = cb.company_id
    LEFT JOIN users farmer ON i.farmer_id = farmer.user_id
    LEFT JOIN farmer_profiles fp ON i.farmer_id = fp.farmer_id
    WHERE i.invoice_id = ?
    AND (i.buyer_id = ? OR i.farmer_id = ?)
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $invoice_id, $user_id, $user_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();



if (!$invoice) {
    header('Location: invoices.php');
    exit;
}

// Fetch invoice items
$stmt = $conn->prepare("
    SELECT * FROM invoice_items
    WHERE invoice_id = ?
");
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch payments for this invoice
$payments = [];
$payment_stmt = $conn->prepare("
    SELECT * 
    FROM payment
    WHERE order_id = ?
");
// Get payments for this order
$payment_status = 'Unpaid'; // default
$payment_stmt = $conn->prepare("SELECT payment_status FROM payment WHERE order_id = ?");
$payment_stmt->bind_param("i", $invoice['order_id']);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();

while ($row = $payment_result->fetch_assoc()) {
    if ($row['payment_status'] === 'Paid') {
        $payment_status = 'Paid';
        break; // at least one payment is paid
    }
}
$payment_stmt->close();


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?> - BANTAY-ANI</title>
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
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-print {
            background: var(--green);
            color: white;
        }

        .btn-back {
            background: var(--beige);
            color: var(--text);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .invoice {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid var(--green);
        }

        .logo-section h1 {
            font-size: 2rem;
            color: var(--green-dark);
            margin-bottom: 4px;
        }

        .logo-section .tagline {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .invoice-info {
            text-align: right;
        }

        .invoice-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--green-dark);
            margin-bottom: 8px;
        }

        .invoice-date {
            color: var(--text-light);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 8px;
        }

        .status-paid { background: rgba(34, 197, 94, 0.1); color: #16a34a; }
        .status-unpaid { background: rgba(251, 191, 36, 0.1); color: #d97706; }
        .status-overdue { background: rgba(239, 68, 68, 0.1); color: #dc2626; }

        .parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .party h3 {
            color: var(--green-dark);
            margin-bottom: 12px;
            font-size: 1.1rem;
        }

        .party p {
            margin: 4px 0;
            color: var(--text-light);
        }

        .party strong {
            color: var(--text);
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }

        .items-table thead {
            background: var(--beige);
        }

        .items-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: var(--text);
            border-bottom: 2px solid var(--border);
        }

        .items-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }

        .items-table tbody tr:hover {
            background: rgba(31, 138, 112, 0.02);
        }

        .text-right {
            text-align: right;
        }

        .totals {
            margin-left: auto;
            width: 300px;
            margin-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }

        .total-row.grand-total {
            border-top: 2px solid var(--green);
            padding-top: 12px;
            margin-top: 12px;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--green-dark);
        }

        .payment-history {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid var(--border);
        }

        .payment-history h3 {
            color: var(--green-dark);
            margin-bottom: 16px;
        }

        .payment-item {
            background: var(--beige);
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notes {
            margin-top: 30px;
            padding: 16px;
            background: rgba(31, 138, 112, 0.05);
            border-left: 4px solid var(--green);
            border-radius: 4px;
        }

        .notes h4 {
            color: var(--green-dark);
            margin-bottom: 8px;
        }

        .footer-text {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            color: var(--text-light);
            font-size: 0.9rem;
        }

        @media print {
            body {
                background: white;
            }
            .actions {
                display: none;
            }
            .invoice {
                box-shadow: none;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            .invoice {
                padding: 20px;
            }
            .invoice-header {
                flex-direction: column;
                gap: 20px;
            }
            .invoice-info {
                text-align: left;
            }
            .parties {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .items-table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Action Buttons -->
        <div class="actions">
            <button onclick="window.print()" class="btn btn-print">🖨️ Print Invoice</button>
            <a href="invoices.php" class="btn btn-back">← Back to Invoices</a>
        </div>

        <!-- Invoice Document -->
        <div class="invoice">
            <!-- Header -->
            <div class="invoice-header">
                <div class="logo-section">
                    <h1>BANTAY<span style="color: var(--orange);">ANI</span></h1>
                    <p class="tagline">Farm-to-Market System</p>
                </div>
                <div class="invoice-info">
                    <div class="invoice-number">INVOICE</div>
                    <div class="invoice-number"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                    <div class="invoice-date">
                        Date: <?= date('F d, Y', strtotime($invoice['created_at'])) ?>
                    </div>

                        <div class="status-badge status-<?= strtolower(str_replace(' ', '', $payment_status)) ?>">
                            <?= $payment_status ?>
                        </div>
                </div>
            </div>

            <!-- Parties Information -->
            <div class="parties">
                <div class="party">
                    <h3>From:</h3>
                    <p><strong><?= htmlspecialchars($invoice['farmer_name']) ?></strong></p>
                    <p><?= htmlspecialchars($invoice['farm_name'] ?? '') ?></p>
                    <p><?= htmlspecialchars($invoice['farm_location'] ?? '') ?></p>
                    <p><?= htmlspecialchars($invoice['farmer_email']) ?></p>
                    <p><?= htmlspecialchars($invoice['farmer_contact']) ?></p>
                </div>
                <div class="party">
                    <h3>To:</h3>
                    <p><strong><?= htmlspecialchars($invoice['buyer_name']) ?></strong></p>
                    <?php if (!empty($invoice['company_name'])): ?>
                        <p><?= htmlspecialchars($invoice['company_name']) ?></p>
                    <?php endif; ?>
                    <p><?= htmlspecialchars($invoice['buyer_address']) ?></p>
                    <p><?= htmlspecialchars($invoice['buyer_email']) ?></p>
                    <p><?= htmlspecialchars($invoice['buyer_contact']) ?></p>
                </div>
            </div>

            <!-- Invoice Items -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['crop_name']) ?></td>
                            <td class="text-right"><?= number_format($item['quantity'], 2) ?></td>
                            <td class="text-right">₱<?= number_format($item['unit_price'], 2) ?></td>
                            <td class="text-right"><strong>₱<?= number_format($item['total_price'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Totals -->
            <div class="totals">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>₱<?= number_format($invoice['subtotal'], 2) ?></span>
                </div>
                <?php if ($invoice['tax_amount'] > 0): ?>
                    <div class="total-row">
                        <span>Tax:</span>
                        <span>₱<?= number_format($invoice['tax_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($invoice['discount_amount'] > 0): ?>
                    <div class="total-row">
                        <span>Discount:</span>
                        <span>-₱<?= number_format($invoice['discount_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <div class="total-row grand-total">
                    <span>Total Amount:</span>
                    <span>₱<?= number_format($invoice['total_amount'], 2) ?></span>
                </div>
            </div>

        

            <!-- Due Date -->
            <?php if ($invoice['due_date'] && $invoice['payment_status'] !== 'Paid'): ?>
                <div class="notes">
                    <h4>Payment Terms</h4>
                    <p>
                        <strong>Due Date:</strong> <?= date('F d, Y', strtotime($invoice['due_date'])) ?>
                        <?php if (strtotime($invoice['due_date']) < time()): ?>
                            <span style="color: #dc2626;"> (Overdue)</span>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Notes -->
            <?php if (!empty($invoice['notes'])): ?>
                <div class="notes">
                    <h4>Notes</h4>
                    <p><?= nl2br(htmlspecialchars($invoice['notes'])) ?></p>
                </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="footer-text">
                <p>Thank you for your business!</p>
                <p>BANTAY-ANI Farm-to-Market System</p>
                <p style="margin-top: 8px; font-size: 0.85rem;">
                    This is a computer-generated invoice. For inquiries, please contact us through the system.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
