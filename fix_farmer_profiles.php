<?php
require_once 'includes/config.php';

echo "<h2>Fix Missing Farmer Profiles</h2>";

// Find all users with role 'Farmer' who don't have farmer_profiles
$stmt = $conn->prepare("
    SELECT u.user_id, u.first_name, u.last_name 
    FROM users u 
    LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
    WHERE u.role = 'Farmer' AND fp.farmer_id IS NULL
");
$stmt->execute();
$result = $stmt->get_result();

$fixed_count = 0;

if ($result->num_rows > 0) {
    echo "<p>Found " . $result->num_rows . " farmers without profiles. Fixing...</p>";
    
    while ($user = $result->fetch_assoc()) {
        $default_farm_name = $user['first_name'] . "'s Farm";
        $default_location = "Location not specified";
        
        // Insert farmer profile
        $insert_stmt = $conn->prepare('INSERT INTO farmer_profiles (farmer_id, farm_name, farm_location) VALUES (?, ?, ?)');
        $insert_stmt->bind_param('iss', $user['user_id'], $default_farm_name, $default_location);
        $insert_stmt->execute();
        $insert_stmt->close();
        
        echo "<p>Fixed farmer ID " . $user['user_id'] . ": " . $user['first_name'] . " " . $user['last_name'] . " → Farm: " . $default_farm_name . "</p>";
        $fixed_count++;
    }
    
    echo "<p><strong>Successfully fixed " . $fixed_count . " farmer profiles!</strong></p>";
} else {
    echo "<p>All farmers have profiles. No fixes needed.</p>";
}

$stmt->close();

// Show current farmer profiles
echo "<h3>Current Farmer Profiles:</h3>";
$result = $conn->query('SELECT u.user_id, u.first_name, u.last_name, fp.farm_name, fp.farm_location 
                         FROM users u 
                         JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                         WHERE u.role = "Farmer" 
                         ORDER BY u.user_id');
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>User ID</th><th>Name</th><th>Farm Name</th><th>Farm Location</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>" . $row['user_id'] . "</td><td>" . $row['first_name'] . " " . $row['last_name'] . "</td><td>" . $row['farm_name'] . "</td><td>" . $row['farm_location'] . "</td></tr>";
}
echo "</table>";
?>
