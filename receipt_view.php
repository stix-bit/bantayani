<?php
session_start();
require_once __DIR__ . '/includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: user/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$invoice_id = isset($_GET['invoice']) ? (int)$_GET['invoice'] : 0;

if ($invoice_id <= 0) {
    header('Location: invoices.php');
    exit;
}

// Fetch invoice and payment details
$query = "
    SELECT i.*, pt.*,
           CONCAT(buyer.first_name, ' ', buyer.last_name) as buyer_name,
           buyer.address as buyer_address,
           CONCAT(farmer.first_name, ' ', farmer.last_name) as farmer_name,
           fp.farm_name, fp.farm_location
    FROM invoices i
    LEFT JOIN payment_transactions pt ON i.invoice_id = pt.invoice_id
    LEFT JOIN users buyer ON i.buyer_id = buyer.user_id
    LEFT JOIN users farmer ON i.farmer_id = farmer.user_id
    LEFT JOIN farmer_profiles fp ON i.farmer_id = fp.farmer_id
    WHERE i.invoice_id = ?
    AND i.payment_status = 'Paid'
    AND (i.buyer_id = ? OR i.farmer_id = ?)
    ORDER BY pt.payment_date DESC
    LIMIT 1
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $invoice_id, $user_id, $user_id);
$stmt->execute();
$receipt_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$receipt_data) {
    header('Location: invoices.php');
    exit;
}

// Generate receipt number if not exists
$receipt_number = 'RCP-' . date('Y') . '-' . str_pad($invoice_id, 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?= $receipt_number ?> - BANTAY-ANI</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap');

        :root {
            --green-dark: #0c5c4c;
            --green: #1f8a70;
            --orange: #f28705;
            --beige: #f6f1e9;
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
            background: var(--beige);
            color: var(--text);
            line-height: 1.6;
        }

        .container {
            max-width: 700px;
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

        .receipt {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border: 3px dashed var(--green);
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border);
        }

        .receipt-header h1 {
            font-size: 2rem;
            color: var(--green-dark);
            margin-bottom: 4px;
        }

        .receipt-header .subtitle {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .receipt-number {
            background: var(--green);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            display: inline-block;
            font-size: 1.2rem;
            font-weight: 700;
            margin: 16px 0;
        }

        .paid-stamp {
            background: rgba(34, 197, 94, 0.1);
            border: 3px solid #16a34a;
            color: #16a34a;
            padding: 8px 24px;
            border-radius: 8px;
            display: inline-block;
            font-size: 1.3rem;
            font-weight: 700;
            transform: rotate(-5deg);
            margin: 16px 0;
        }

        .receipt-info {
            margin: 30px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-light);
        }

        .info-value {
            color: var(--text);
            text-align: right;
        }

        .amount-paid {
            background: rgba(31, 138, 112, 0.1);
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }

        .amount-paid-label {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .amount-paid-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--green-dark);
        }

        .thank-you {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid var(--border);
        }

        .thank-you h3 {
            color: var(--green-dark);
            margin-bottom: 12px;
        }

        .thank-you p {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 60px;
            text-align: center;
        }

        .signature-line {
            border-top: 2px solid var(--text);
            padding-top: 8px;
            margin-top: 40px;
        }

        .signature-label {
            font-weight: 600;
            color: var(--text);
        }

        @media print {
            body {
                background: white;
            }
            .actions {
                display: none;
            }
            .receipt {
                box-shadow: none;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            .receipt {
                padding: 20px;
            }
            .signature-section {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Action Buttons -->
        <div class="actions">
            <button onclick="window.print()" class="btn btn-print">🖨️ Print Receipt</button>
            <a href="invoices.php" class="btn btn-back">← Back to Invoices</a>
        </div>

        <!-- Receipt Document -->
        <div class="receipt">
            <!-- Header -->
            <div class="receipt-header">
                <h1>BANTAY<span style="color: var(--orange);">ANI</span></h1>
                <p class="subtitle">Official Payment Receipt</p>
                <div class="receipt-number">
                    <?= $receipt_number ?>
                </div>
                <div class="paid-stamp">
                    ✓ PAID
                </div>
            </div>

            <!-- Receipt Information -->
            <div class="receipt-info">
                <div class="info-row">
                    <span class="info-label">Receipt Date:</span>
                    <span class="info-value"><?= date('F d, Y h:i A', strtotime($receipt_data['payment_date'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Invoice Number:</span>
                    <span class="info-value"><?= htmlspecialchars($receipt_data['invoice_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Method:</span>
                    <span class="info-value"><?= htmlspecialchars($receipt_data['payment_method']) ?></span>
                </div>
                <?php if ($receipt_data['reference_number']): ?>
                    <div class="info-row">
                        <span class="info-label">Reference Number:</span>
                        <span class="info-value"><?= htmlspecialchars($receipt_data['reference_number']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Transaction Number:</span>
                    <span class="info-value"><?= htmlspecialchars($receipt_data['transaction_number']) ?></span>
                </div>
            </div>

            <!-- Amount Paid -->
            <div class="amount-paid">
                <div class="amount-paid-label">Amount Paid</div>
                <div class="amount-paid-value">
                    ₱<?= number_format($receipt_data['total_amount'], 2) ?>
                </div>
            </div>

            <!-- Parties -->
            <div class="receipt-info">
                <div class="info-row">
                    <span class="info-label">Received From:</span>
                    <span class="info-value">
                        <?= htmlspecialchars($receipt_data['buyer_name']) ?><br>
                        <small style="color: var(--text-light);"><?= htmlspecialchars($receipt_data['buyer_address']) ?></small>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Received By:</span>
                    <span class="info-value">
                        <?= htmlspecialchars($receipt_data['farmer_name']) ?><br>
                        <small style="color: var(--text-light);"><?= htmlspecialchars($receipt_data['farm_name']) ?></small>
                    </span>
                </div>
            </div>

            <!-- Payment Notes -->
            <?php if (!empty($receipt_data['notes'])): ?>
                <div style="background: rgba(31, 138, 112, 0.05); padding: 16px; border-radius: 8px; margin: 20px 0;">
                    <strong>Notes:</strong><br>
                    <?= nl2br(htmlspecialchars($receipt_data['notes'])) ?>
                </div>
            <?php endif; ?>

            <!-- Signature Section -->
            <div class="signature-section">
                <div>
                    <div class="signature-line">
                        <div class="signature-label">Buyer Signature</div>
                    </div>
                </div>
                <div>
                    <div class="signature-line">
                        <div class="signature-label">Seller Signature</div>
                    </div>
                </div>
            </div>

            <!-- Thank You Message -->
            <div class="thank-you">
                <h3>Thank You!</h3>
                <p>This is a computer-generated receipt.</p>
                <p>For any questions or concerns, please contact us through the BANTAY-ANI system.</p>
                <p style="margin-top: 16px; font-size: 0.85rem;">
                    © <?= date('Y') ?> BANTAY-ANI Farm-to-Market System
                </p>
            </div>
        </div>
    </div>
</body>
</html>
