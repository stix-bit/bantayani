<?php
// Start the session so we can set a flag
session_start();

// Set a session flag indicating weather alerts are cleared
$_SESSION['weather_alerts_cleared'] = true;

// Return JSON so the front-end fetch() can parse it
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Weather alerts cleared'
]);
exit;