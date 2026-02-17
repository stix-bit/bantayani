-- Weather data storage table
CREATE TABLE IF NOT EXISTS weather_data (
    weather_id INT AUTO_INCREMENT PRIMARY KEY,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    temperature DECIMAL(5, 2),
    humidity INT,
    wind_speed DECIMAL(5, 2),

    data_json LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_location (latitude, longitude),
    INDEX idx_date (created_at)
);

-- Add region column to farmer_profiles table
ALTER TABLE farmer_profiles ADD COLUMN IF NOT EXISTS region VARCHAR(100);


