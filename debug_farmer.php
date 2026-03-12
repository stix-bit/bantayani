<?php
require_once 'includes/config.php';
session_start();

echo "<h2>Farmer Profile Debug</h2>";

if (isset($_SESSION['user_id'])) {
    $farmer_id = $_SESSION['user_id'];
    echo "<p><strong>Session farmer_id:</strong> " . $farmer_id . "</p>";
    echo "<p><strong>Session role:</strong> " . ($_SESSION['role'] ?? 'Not set') . "</p>";
    
    // Check if farmer exists in users table
    $stmt = $conn->prepare('SELECT user_id, role, first_name, last_name FROM users WHERE user_id = ?');
    $stmt->bind_param('i', $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo "<p><strong>User found:</strong> " . $row['user_id'] . " (" . $row['role'] . ") - " . $row['first_name'] . " " . $row['last_name'] . "</p>";
    } else {
        echo "<p><strong>User not found in users table</strong></p>";
    }
    $stmt->close();
    
    // Check if farmer profile exists
    $stmt = $conn->prepare('SELECT farmer_id, farm_name, farm_location FROM farmer_profiles WHERE farmer_id = ?');
    $stmt->bind_param('i', $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo "<p><strong>Farmer profile found:</strong> " . $row['farmer_id'] . " (" . $row['farm_name'] . ") - " . $row['farm_location'] . "</p>";
    } else {
        echo "<p><strong>Farmer profile NOT found - this is the problem!</strong></p>";
    }
    $stmt->close();
    
    // Show all farmer profiles for reference
    echo "<h3>All Farmer Profiles:</h3>";
    $result = $conn->query('SELECT farmer_id, farm_name, farm_location FROM farmer_profiles');
    echo "<table border='1'><tr><th>farmer_id</th><th>farm_name</th><th>farm_location</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>" . $row['farmer_id'] . "</td><td>" . $row['farm_name'] . "</td><td>" . $row['farm_location'] . "</td></tr>";
    }
    echo "</table>";
    
} else {
    echo "<p><strong>No user session found</strong></p>";
}
?>
