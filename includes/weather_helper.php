<?php
/**
 * Weather Helper - Fetches real weather data and stores alerts
 * Uses Open-Meteo (free, no API key needed)
 */

class WeatherService {
    private $conn;
    private $weather_api_url = 'https://api.open-meteo.com/v1/forecast';
    
    // Default Philippines coordinates (center - Nueva Ecija)
    private $default_latitude = 14.8589;
    private $default_longitude = 121.7732;
    
    // Philippine provinces & regions with their coordinates
    private $philippines_regions = [
        'Manila' => ['lat' => 14.5995, 'lon' => 120.9842],
        'Nueva Ecija' => ['lat' => 14.8589, 'lon' => 121.7732],
        'Bulacan' => ['lat' => 14.7569, 'lon' => 120.8429],
        'Batangas' => ['lat' => 13.7573, 'lon' => 121.0426],
        'Laguna' => ['lat' => 14.3733, 'lon' => 121.4280],
        'Quezon' => ['lat' => 14.6091, 'lon' => 121.6050],
        'Cavite' => ['lat' => 14.3549, 'lon' => 120.8935],
        'Rizal' => ['lat' => 14.5775, 'lon' => 121.3045],
        'Camarines Sur' => ['lat' => 13.5895, 'lon' => 123.4506],
        'Cebu' => ['lat' => 10.3157, 'lon' => 123.8854],
        'Davao' => ['lat' => 7.0731, 'lon' => 125.6121],
        'Mindanao' => ['lat' => 7.0731, 'lon' => 125.6121],
        'Luzon' => ['lat' => 14.8589, 'lon' => 121.7732],
        'Visayas' => ['lat' => 10.3157, 'lon' => 123.8854],
    ];
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    /**
     * Get coordinates for a province/region
     * @param string $region
     * @return array [latitude, longitude]
     */
    public function getRegionCoordinates($region = null) {
        if ($region && isset($this->philippines_regions[$region])) {
            return [
                $this->philippines_regions[$region]['lat'],
                $this->philippines_regions[$region]['lon']
            ];
        }
        return [$this->default_latitude, $this->default_longitude];
    }
    
    /**
     * Get list of available regions
     * @return array
     */
    public function getAvailableRegions() {
        return array_keys($this->philippines_regions);
    }
    
    /**
     * Get weather data for a location (latitude, longitude)
     * @param float $latitude
     * @param float $longitude
     * @return array Weather data
     */
    public function getWeatherData($latitude, $longitude) {
        try {
            // Simplified API parameters - only request what we need and what the API supports
            $params = [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'current' => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m',
                'timezone' => 'auto'
            ];
            
            $url = $this->weather_api_url . '?' . http_build_query($params);
            
            // Check if cURL is available
            if (!function_exists('curl_init')) {
                return ['error' => 'cURL is not enabled in PHP. Please enable it in php.ini'];
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'BANTAY-ANI-Weather-Service/1.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Allow self-signed certificates
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $curl_error = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Debug info
            if ($curl_error) {
                return ['error' => 'cURL error: ' . $curl_error];
            }
            
            if (!$response) {
                return ['error' => 'Empty response from API'];
            }
            
            if ($http_code !== 200) {
                return ['error' => "API returned HTTP {$http_code}. Response: " . substr($response, 0, 200)];
            }
            
            $decoded = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['error' => 'Invalid JSON response: ' . json_last_error_msg()];
            }
            
            return $decoded;
        } catch (Exception $e) {
            return ['error' => 'Exception: ' . $e->getMessage()];
        }
    }
    
    /**
     * Process weather data and generate alerts
     * @param array $weather_data
     * @return array Alerts to store
     */
    public function generateAlerts($weather_data) {
        $alerts = [];
        
        if (isset($weather_data['error'])) {
            return $alerts;
        }
        
        $current = $weather_data['current'] ?? [];
        
        // Get current temperature
        $temp = $current['temperature_2m'] ?? null;
        
        // Alert for high temperature (> 35°C)
        if ($temp && $temp > 35) {
            $alerts[] = [
                'title' => 'High Temperature Warning',
                'message' => "Ang kasalukuyang temperatura ay {$temp}°C. Paniguraduhin na ang halaman ay nasa tamang kondisyon.",
                'severity' => 'High'
            ];
        }
        
        // Alert for moderate temperature (28-35°C)
        if ($temp && $temp >= 28 && $temp <= 35) {
            $alerts[] = [
                'title' => 'Warm Weather',
                'message' => "Temperature: {$temp}°C. I-monitor ang moisture ng mga halaman.",
                'severity' => 'Medium'
            ];
        }
        
        // Alert for cold temperature (< 10°C)
        if ($temp && $temp < 10) {
            $alerts[] = [
                'title' => 'Low Temperature Warning',
                'message' => "Ang kasalukuyang temperatura is {$temp}°C. Maaaring makaapekto ang panganib ng frost sa mga sensitibong pananim.
.",
                'severity' => 'High'
            ];
        }
        
        // Check humidity
        $humidity = $current['relative_humidity_2m'] ?? 0;
        if ($humidity > 85) {
            $alerts[] = [
                'title' => 'High Humidity',
                'message' => "Humidity: {$humidity}%. Mataas ang panganib ng mga sakit na dulot ng fungi. Pagbutihin ang sirkulasyon ng hangin.
",
                'severity' => 'Medium'
            ];
        }
        
        // Check for high wind
        $wind = $current['wind_speed_10m'] ?? 0;
        if ($wind > 50) {
            $alerts[] = [
                'title' => 'High Wind Warning',
                'message' => "Wind speed: {$wind} km/h. Siguraduhin ang mga pananim at protektahan ang mga ito laban sa pinsalang dulot ng malakas na hangin.
",
                'severity' => 'High'
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Store weather data in database
     * @param array $weather_data
     * @param float $latitude
     * @param float $longitude
     * @return bool
     */
    public function storeWeatherData($weather_data, $latitude, $longitude) {
        if (isset($weather_data['error'])) {
            return false;
        }
        
        $current = $weather_data['current'] ?? [];
        $temp = $current['temperature_2m'] ?? null;
        $humidity = $current['relative_humidity_2m'] ?? null;
        $wind = $current['wind_speed_10m'] ?? 0;
        
        $stmt = $this->conn->prepare("
            INSERT INTO weather_data (latitude, longitude, temperature, humidity, wind_speed, data_json)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $json_data = json_encode($weather_data);
        $stmt->bind_param('dddids', $latitude, $longitude, $temp, $humidity, $wind, $json_data);
        
        return $stmt->execute();
    }
    
    /**
     * Check weather_alerts schema and add region field if missing
     */
    private function ensureWeatherAlertRegionColumn() {
        $result = $this->conn->query("SHOW COLUMNS FROM weather_alerts LIKE 'region'");
        if ($result && $result->num_rows === 0) {
            $this->conn->query("ALTER TABLE weather_alerts ADD COLUMN region VARCHAR(100) NULL AFTER severity");
        }
    }

    /**
     * Clear old alerts and store new ones
     * @param array $alerts
     * @param string|null $region
     * @return bool
     */
    public function storeAlerts($alerts, $region = null) {
        $this->ensureWeatherAlertRegionColumn();

        // Clear old alerts (older than 1 day)
        $this->conn->query("DELETE FROM weather_alerts WHERE created_at < NOW() - INTERVAL 1 DAY");
        
        // Insert new alerts
        $stmt = $this->conn->prepare("
            INSERT INTO weather_alerts (title, message, severity, region)
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($alerts as $alert) {
            $title = $alert['title'];
            $message = $alert['message'];
            $severity = $alert['severity'];
            $stmt->bind_param('ssss', $title, $message, $severity, $region);
            $stmt->execute();
        }
        
        return true;
    }
    
    /**
     * Update weather and generate alerts
     * @param float $latitude
     * @param float $longitude
     * @return array Updated weather data
     */
    public function updateWeather($latitude, $longitude, $region = null) {
        $weather_data = $this->getWeatherData($latitude, $longitude);
        
        if (isset($weather_data['error'])) {
            return $weather_data;
        }
        
        // Store weather data
        $this->storeWeatherData($weather_data, $latitude, $longitude);
        
        // Generate and store alerts
        $alerts = $this->generateAlerts($weather_data);
        if (!empty($alerts)) {
            $this->storeAlerts($alerts, $region);
        }
        
        return [
            'success' => true,
            'current_weather' => $weather_data['current'] ?? [],
            'alerts_count' => count($alerts)
        ];
    }
    
    /**
     * Get latest weather data from database
     * @param float $latitude
     * @param float $longitude
     * @return array Latest weather record
     */
    public function getLatestWeather($latitude, $longitude) {
        $stmt = $this->conn->prepare("
            SELECT * FROM weather_data 
            WHERE latitude = ? AND longitude = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        
        $stmt->bind_param('dd', $latitude, $longitude);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc() ?? null;
    }
}
?>
