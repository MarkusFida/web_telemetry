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

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$lat = isset($data['lat']) ? floatval($data['lat']) : null;
$lon = isset($data['lon']) ? floatval($data['lon']) : null;

if ($lat === null || $lon === null || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid coordinates']);
    exit;
}

$payload = [
    'lat' => $lat,
    'lon' => $lon,
    'acc' => isset($data['acc']) ? floatval($data['acc']) : null,
    'battery' => isset($data['batt']) ? floatval($data['batt']) : null,
    'tst' => isset($data['tst']) ? intval($data['tst']) : time(),
    'timestamp' => date('Y-m-d H:i:s')
];

if (file_put_contents('owntracks.json', json_encode($payload, JSON_PRETTY_PRINT))) {
    echo json_encode(['status' => 'ok', 'payload' => $payload]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save OwnTracks data']);
}
?>
