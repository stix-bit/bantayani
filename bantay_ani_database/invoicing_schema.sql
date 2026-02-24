-- Payment Invoicing System Schema

-- Invoices Table
CREATE TABLE IF NOT EXISTS invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    order_id INT NOT NULL,
    buyer_id INT NOT NULL,
    farmer_id INT NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) DEFAULT 0.00,
    discount_amount DECIMAL(10, 2) DEFAULT 0.00,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('Unpaid', 'Partially Paid', 'Paid', 'Overdue') DEFAULT 'Unpaid',
    payment_method ENUM('Cash', 'Bank Transfer', 'GCash', 'PayMaya', 'Credit Card', 'Check') NULL,
    payment_date DATETIME NULL,
    due_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_buyer (buyer_id),
    INDEX idx_farmer (farmer_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invoice Items Table (detailed breakdown)
CREATE TABLE IF NOT EXISTS invoice_items (
    invoice_item_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    inventory_id INT NOT NULL,
    crop_name VARCHAR(100) NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE,
    FOREIGN KEY (inventory_id) REFERENCES crops_inventory(inventory_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payment Transactions Table (payment history)
CREATE TABLE IF NOT EXISTS payment_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    transaction_number VARCHAR(50) UNIQUE NOT NULL,
    amount_paid DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('Cash', 'Bank Transfer', 'GCash', 'PayMaya', 'Credit Card', 'Check') NOT NULL,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    reference_number VARCHAR(100) NULL,
    notes TEXT NULL,
    processed_by INT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_invoice (invoice_id),
    INDEX idx_transaction_number (transaction_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Receipts Table (official receipts)
CREATE TABLE IF NOT EXISTS receipts (
    receipt_id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    invoice_id INT NOT NULL,
    transaction_id INT NULL,
    issued_to INT NOT NULL,
    issued_by INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    receipt_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    qr_code_path VARCHAR(255) NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES payment_transactions(transaction_id) ON DELETE SET NULL,
    FOREIGN KEY (issued_to) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trigger to automatically create invoice when order is confirmed
DELIMITER //
CREATE TRIGGER after_order_confirmed
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF NEW.order_status = 'Confirmed' AND OLD.order_status != 'Confirmed' THEN
        -- Generate invoice number
        SET @invoice_num = CONCAT('INV-', YEAR(NOW()), LPAD(MONTH(NOW()), 2, '0'), '-', LPAD(NEW.order_id, 6, '0'));
        
        -- Calculate totals from order items
        SET @subtotal = (
            SELECT SUM(ci.price * oi.quantity)
            FROM order_items oi
            JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
            WHERE oi.order_id = NEW.order_id
        );
        
        -- Get farmer_id from first item in order
        SET @farmer_id = (
            SELECT ci.farmer_id
            FROM order_items oi
            JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
            WHERE oi.order_id = NEW.order_id
            LIMIT 1
        );
        
        -- Create invoice
        INSERT INTO invoices (
            invoice_number, 
            order_id, 
            buyer_id, 
            farmer_id,
            subtotal, 
            total_amount, 
            due_date
        ) VALUES (
            @invoice_num,
            NEW.order_id,
            NEW.buyer_id,
            @farmer_id,
            @subtotal,
            @subtotal,
            DATE_ADD(NOW(), INTERVAL 7 DAY)
        );
        
        -- Get the created invoice_id
        SET @new_invoice_id = LAST_INSERT_ID();
        
        -- Insert invoice items
        INSERT INTO invoice_items (invoice_id, inventory_id, crop_name, quantity, unit_price, total_price)
        SELECT 
            @new_invoice_id,
            oi.inventory_id,
            c.crop_name,
            oi.quantity,
            ci.price,
            ci.price * oi.quantity
        FROM order_items oi
        JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
        JOIN crops c ON ci.crop_id = c.crop_id
        WHERE oi.order_id = NEW.order_id;
    END IF;
END//
DELIMITER ;
