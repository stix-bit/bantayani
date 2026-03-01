<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/dynamic_pricing.php';

header('Content-Type: application/json');

$crop_id = intval($_GET['crop_id'] ?? 0);
if($crop_id <= 0){
    echo json_encode(['has_data'=>false]);
    exit;
}

$range = getDynamicPriceRange($conn, $crop_id);
echo json_encode($range);