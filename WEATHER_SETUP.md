# Weather Alerts System Setup Guide

## Overview
This weather system automatically fetches real-time weather data and generates alerts for all farmers in the Philippines. It uses **Open-Meteo** (free weather API, no API key required). All farmers get weather data for their selected region or Philippines default.

## Features
✅ Real-time temperature, humidity, wind speed, and precipitation  
✅ Automatic alert generation based on weather conditions  
✅ Smart alerts for high temperature, cold, rain, wind, and humidity  
✅ Forecast-based warnings for next 3 days  
✅ Philippines region-based weather data (no manual coordinates needed!)  

## Installation Steps

### Step 1: Add Database Tables
Run this SQL in your database manager (phpMyAdmin):

```sql
-- Weather data storage table
CREATE TABLE IF NOT EXISTS weather_data (
    weather_id INT AUTO_INCREMENT PRIMARY KEY,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    temperature DECIMAL(5, 2),
    humidity INT,
    wind_speed DECIMAL(5, 2),
    precipitation DECIMAL(10, 2),
    data_json LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_location (latitude, longitude),
    INDEX idx_date (created_at)
);

-- Add region field to users table (optional, for farmer-specific regions)
ALTER TABLE users ADD COLUMN IF NOT EXISTS region VARCHAR(100);
```

Or import the provided file: `backend_sql/weather_data_table.sql`

### Step 2: Verify Files Are Created
These files have been created:
- `includes/weather_helper.php` - Weather service class (includes Philippines regions)
- `api/weather.php` - Weather API endpoint
- `weather_update.php` - Batch update script
- `farmer/inventory.php` - Updated with weather display

### Step 3: Farmers OPTIONALLY Add Their Region
Farmers can optionally specify their region for more accurate weather. Add this field to farmer profile:

```php
<label>Your Farm Region (Optional)</label>
<select name="region">
    <option value="">-- Philippines Default --</option>
    <option value="Manila">Manila</option>
    <option value="Nueva Ecija">Nueva Ecija</option>
    <option value="Bulacan">Bulacan</option>
    <option value="Batangas">Batangas</option>
    <option value="Laguna">Laguna</option>
    <option value="Quezon">Quezon</option>
    <option value="Cavite">Cavite</option>
    <option value="Rizal">Rizal</option>
    <option value="Camarines Sur">Camarines Sur</option>
    <option value="Cebu">Cebu</option>
    <option value="Davao">Davao</option>
    <option value="Mindanao">Mindanao</option>
    <option value="Luzon">Luzon</option>
    <option value="Visayas">Visayas</option>
</select>
```

### Step 4: Set Up Auto-Update (Recommended)

#### Option A: Windows Task Scheduler
1. Create a batch file `C:\weather_update.bat`:
```batch
@echo off
"C:\xampp\php\php.exe" "C:\xampp\htdocs\bantayani\weather_update.php"
```

2. Open Task Scheduler → Create Basic Task
3. Set trigger: Daily, every 6 hours
4. Set action: Run `weather_update.bat`

#### Option B: Linux/UNIX Cron Job
Add to crontab (`crontab -e`):
```bash
0 */6 * * * /usr/bin/php /var/www/html/bantayani/weather_update.php
```

#### Option C: Manual Testing
Visit in browser: `http://localhost/bantayani/weather_update.php`

### Step 5: Test the System

1. **Optional: Set Farmer Region:**
   - Go to farmer profile
   - Select their region (or leave blank for Philippines default)
   - Save

2. **Fetch Weather Data:**
   - Go to Inventory page
   - Click "🔄 Refresh Weather" button
   - Should see current temperature, humidity, wind, precipitation for Philippines

3. **Check Alerts:**
   - Alerts appear automatically based on weather conditions
   - See color-coded severity (Red=High, Yellow=Medium, Green=Low)

## Alert Thresholds

The system automatically creates alerts for:

| Condition | Threshold | Severity |
|-----------|-----------|----------|
| High Temperature | > 35°C | High |
| Moderate Heat | 28-35°C | Medium |
| Cold Freezing | < 10°C | High |
| Heavy Rain | > 2mm current | High |
| High Wind | > 50 km/h | High |
| High Humidity | > 85% | Medium |
| Rain Forecast | > 20mm in 3 days | Medium |

## API Documentation

### Get Current Weather
```
GET /api/weather.php
Response: {
    location: "Farm Location",
    weather: {current weather object},
    alerts: [{alert objects}],
    last_updated: "timestamp"
}
```

### Refresh Weather
```
GET /api/weather.php?action=refresh
Response: {
    success: true,
    current_weather: {weather data},
    alerts_count: 5
}
```

## Troubleshooting

### Weather data not showing?
1. Run `weather_update.php` manually to test
2. Check if weather data was inserted into `weather_data` table
3. Check database for errors

### API connection error?
- Make sure cURL is enabled in PHP (`php -m | grep curl`)
- Check firewall isn't blocking api.open-meteo.com
- Test: `curl https://api.open-meteo.com/v1/forecast?latitude=14.8589&longitude=121.7732&current=temperature_2m`

### Alerts not generating?
- Check if alerts are older than 24 hours (old alerts auto-delete)
- Verify weather data was fetched successfully
- Check `weather_alerts` table

### Different weather per region?
- Add the optional `region` field to users table
- Farmers can select their region
- System automatically uses that region's coordinates

## Manual Weather Testing

```php
<?php
require_once 'includes/config.php';
require_once 'includes/weather_helper.php';

$weather_service = new WeatherService($conn);

// Test with default Philippines coordinates
list($lat, $lon) = $weather_service->getRegionCoordinates();
$data = $weather_service->getWeatherData($lat, $lon);
echo json_encode($data, JSON_PRETTY_PRINT);

// Or test a specific region
list($lat, $lon) = $weather_service->getRegionCoordinates('Nueva Ecija');
$data = $weather_service->getWeatherData($lat, $lon);
echo json_encode($data, JSON_PRETTY_PRINT);
?>
```

## Database Queries for Monitoring

View all alerts:
```sql
SELECT * FROM weather_alerts ORDER BY created_at DESC;
```

View latest weather:
```sql
SELECT * FROM weather_data ORDER BY created_at DESC LIMIT 1;
```

View farmer regions:
```sql
SELECT user_id, user_name, user_email, region 
FROM users 
WHERE user_role = 'Farmer' AND region IS NOT NULL;
```

Available regions/provinces:
- Manila, Nueva Ecija, Bulacan, Batangas, Laguna, Quezon, Cavite, Rizal
- Camarines Sur, Cebu, Davao, Mindanao, Luzon, Visayas

## Cost
✅ **FREE** - Open-Meteo has no API key requirement and no cost!

## Support
For issues or feature requests, check:
- Log output of `weather_update.php`
- Browser console for JavaScript errors
- Database tables for missing data
