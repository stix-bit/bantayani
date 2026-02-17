<?php
/**
 * Weather API Endpoint
 * Returns current weather and alerts for the logged-in farmer
 */

// Set error handling to not output warnings to the response
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

// DEBUG: Log session for troubleshooting
error_log('API Session Debug: user_id=' . ($_SESSION['user_id'] ?? 'NOT SET') . ', role=' . ($_SESSION['role'] ?? 'NOT SET') . ', user_role=' . ($_SESSION['user_role'] ?? 'NOT SET'));

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/weather_helper.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No user_id in session']);
    exit;
}

// Check if user is a Farmer (check both 'role' and 'user_role' for compatibility)
$user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
$user_role = strtolower($user_role);

if ($user_role !== 'farmer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => "User role is '{$user_role}', expected 'farmer'. Session role='" . ($_SESSION['role'] ?? 'null') . "' user_role='" . ($_SESSION['user_role'] ?? 'null') . "'"]);
    exit;
}

$farmer_id = $_SESSION['user_id'];
$weather_service = new WeatherService($conn);

// Get farmer's region from farmer_profiles if available, otherwise use default
$stmt = $conn->prepare("
    SELECT region
    FROM farmer_profiles
    WHERE farmer_id = ?
");

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('i', $farmer_id);
$stmt->execute();
$farmer_result = $stmt->get_result();
$farmer = $farmer_result->fetch_assoc();
$stmt->close();

$region = $farmer['region'] ?? null;

// Get coordinates based on region or default to Philippines center
list($latitude, $longitude) = $weather_service->getRegionCoordinates($region);

$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ($action === 'refresh') {
    // Fetch fresh weather data
    $result = $weather_service->updateWeather($latitude, $longitude);
    
    // Ensure response always has 'success' field
    if (isset($result['error'])) {
        $result['success'] = false;
    } elseif (!isset($result['success'])) {
        $result['success'] = true;
    }
    
    echo json_encode($result);
} else {
    // Get latest stored weather
    $weather = $weather_service->getLatestWeather($latitude, $longitude);
    
    // Get active alerts
    $alerts = $conn->query("
        SELECT * FROM weather_alerts 
        WHERE created_at > NOW() - INTERVAL 1 DAY
        ORDER BY 
            CASE severity 
                WHEN 'High' THEN 1 
                WHEN 'Medium' THEN 2 
                ELSE 3 
            END,
            created_at DESC
        LIMIT 10
    ");
    
    echo json_encode([
        'success' => true,
        'location' => $farmer['region'] ?? 'Philippines',
        'weather' => $weather ? json_decode($weather['data_json'], true)['current'] ?? $weather : null,
        'stored_weather' => $weather,
        'alerts' => $alerts->fetch_all(MYSQLI_ASSOC),
        'last_updated' => $weather['created_at'] ?? 'Never'
    ]);
}
?>
