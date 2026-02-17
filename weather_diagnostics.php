<?php
/**
 * Weather System Diagnostics
 * Run this to test if everything is working correctly
 */

echo "=== BANTAY-ANI Weather System Diagnostics ===\n\n";

// Test 1: Check PHP version
echo "1. PHP Info:\n";
echo "   Version: " . phpversion() . "\n";

// Test 2: Check if cURL is enabled
echo "\n2. cURL Status:\n";
if (function_exists('curl_version')) {
    $curl_info = curl_version();
    echo "   ✓ cURL is ENABLED\n";
    echo "   Version: " . $curl_info['version'] . "\n";
} else {
    echo "   ✗ cURL is DISABLED - Enable in php.ini\n";
}

// Test 3: Check database connection
echo "\n3. Database Connection:\n";
try {
    require_once __DIR__ . '/includes/config.php';
    $result = $conn->query("SELECT 1");
    echo "   ✓ Database connected successfully\n";
} catch (Exception $e) {
    echo "   ✗ Database error: " . $e->getMessage() . "\n";
}

// Test 4: Check if tables exist
echo "\n4. Required Database Tables:\n";
$tables = ['weather_data', 'weather_alerts', 'farmer_profiles'];
foreach ($tables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check && $check->num_rows > 0) {
        echo "   ✓ $table exists\n";
    } else {
        echo "   ✗ $table NOT FOUND - Run SQL to create it\n";
    }
}

// Test 5: Test API connection
echo "\n5. API Connection Test:\n";
echo "   Attempting to connect to Open-Meteo API...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.open-meteo.com/v1/forecast?latitude=14.8589&longitude=121.7732&current=temperature_2m');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($error) {
    echo "   ✗ cURL Error: $error\n";
} elseif ($http_code !== 200) {
    echo "   ✗ HTTP Error: $http_code\n";
} elseif ($response) {
    $data = json_decode($response, true);
    if (isset($data['current']['temperature_2m'])) {
        echo "   ✓ API Connection successful!\n";
        echo "   Current temperature at Nueva Ecija: " . $data['current']['temperature_2m'] . "°C\n";
    } else {
        echo "   ✗ Invalid API response\n";
    }
} else {
    echo "   ✗ No response from API\n";
}

// Test 6: Check weather service
echo "\n6. Weather Service Class:\n";
try {
    require_once __DIR__ . '/includes/weather_helper.php';
    $service = new WeatherService($conn);
    echo "   ✓ WeatherService class loaded\n";
    
    list($lat, $lon) = $service->getRegionCoordinates('Nueva Ecija');
    echo "   Nueva Ecija coordinates: $lat, $lon\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 7: Test full weather update
echo "\n7. Full Weather Update Test:\n";
try {
    $result = $service->updateWeather(14.8589, 121.7732);
    if (isset($result['error'])) {
        echo "   ✗ Error: " . $result['error'] . "\n";
    } else {
        echo "   ✓ Weather updated successfully\n";
        echo "   Alerts generated: " . ($result['alerts_count'] ?? 0) . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ Exception: " . $e->getMessage() . "\n";
}

echo "\n=== Diagnostics Complete ===\n";
?>
