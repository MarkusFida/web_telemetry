<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Erlaube CORS für lokale Entwicklung

$file = 'chaser_position.json';
if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true);
    if ($data) {
        echo json_encode($data);
    } else {
        echo json_encode(['error' => 'Invalid JSON data']);
    }
} else {
    echo json_encode(['lat' => null, 'lng' => null, 'message' => 'No saved position']);
}
?>