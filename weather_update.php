<?php
/**
 * Weather Update Service
 * Run this script periodically (every 6 hours) using cron job or scheduled task
 * Usage: Add to cron: 0 *usr/bin/php /path/to/weather_update.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/weather_helper.php';

$weather_service = new WeatherService($conn);

// Use default Philippines location
list($latitude, $longitude) = $weather_service->getRegionCoordinates();

$result = $weather_service->updateWeather($latitude, $longitude);

if (isset($result['error'])) {
    echo json_encode(['error' => 'Failed to fetch weather data: ' . $result['error']]);
} else {
    echo json_encode([
        'success' => true,
        'message' => 'Weather updated successfully for Philippines',
        'alerts_generated' => $result['alerts_count'] ?? 0,
        'updated_at' => date('Y-m-d H:i:s')
    ]);
}
?>
