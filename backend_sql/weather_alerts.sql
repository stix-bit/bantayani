CREATE TABLE IF NOT EXISTS weather_alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    severity ENUM('Low','Medium','High') DEFAULT 'Low',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO weather_alerts (title, message, severity) VALUES
('Heavy Rain Warning','Heavy rainfall expected. Consider early harvest.','High'),
('Hot Weather','High temperature may affect crops. Water regularly.','Medium');
