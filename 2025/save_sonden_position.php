<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['lat']) || !isset($data['lng'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$lat = floatval($data['lat']);
$lng = floatval($data['lng']);

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid coordinates']);
    exit;
}

$line = $lat . ',' . $lng . ',' . date('Y-m-d H:i:s') . "\n";
$file = 'sonden_data.txt';
if (file_put_contents($file, $line, FILE_APPEND)) {
    echo json_encode(['message' => 'Sonde point added successfully']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save sonde point']);
}
?>