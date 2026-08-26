<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$file = 'x_position.json';
if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true);
    if ($data && isset($data['lat']) && isset($data['lng'])) {
        echo json_encode([
            'lat' => floatval($data['lat']),
            'lng' => floatval($data['lng']),
            'timestamp' => isset($data['timestamp']) ? $data['timestamp'] : null
        ]);
    } else {
        echo json_encode(['error' => 'Invalid JSON data']);
    }
} else {
    echo json_encode(['lat' => null, 'lng' => null, 'message' => 'No saved X position']);
}
?>