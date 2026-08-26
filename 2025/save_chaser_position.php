<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

function sanitize_user_id($userId) {
    $userId = strtolower(trim((string)$userId));
    $userId = preg_replace('/[^a-z0-9_\-\.]/', '_', $userId);
    if ($userId === '' || $userId === null) {
        $userId = 'default';
    }
    return substr($userId, 0, 64);
}

function user_id_from_payload($data) {
    if (isset($data['user_id']) && trim((string)$data['user_id']) !== '') {
        return sanitize_user_id($data['user_id']);
    }

    // OwnTracks often uses "tid" as tracker/device id.
    if (isset($data['tid']) && trim((string)$data['tid']) !== '') {
        return sanitize_user_id($data['tid']);
    }

    // Optional extraction from topic: owntracks/<user>/<device>
    if (isset($data['topic']) && is_string($data['topic'])) {
        $parts = explode('/', trim($data['topic']));
        if (count($parts) >= 3 && trim($parts[2]) !== '') {
            return sanitize_user_id($parts[2]);
        }
    }

    return 'default';
}

function owntracks_identity_from_payload($data) {
    $topicUser = null;
    $topicDevice = null;

    if (isset($data['topic']) && is_string($data['topic'])) {
        $parts = explode('/', trim($data['topic']));
        // owntracks/<user>/<device>
        if (count($parts) >= 3) {
            $topicUser = trim((string)$parts[1]) !== '' ? trim((string)$parts[1]) : null;
            $topicDevice = trim((string)$parts[2]) !== '' ? trim((string)$parts[2]) : null;
        }
    }

    $configuredUser = null;
    if (isset($data['username']) && trim((string)$data['username']) !== '') {
        $configuredUser = trim((string)$data['username']);
    } elseif (isset($data['user']) && trim((string)$data['user']) !== '') {
        $configuredUser = trim((string)$data['user']);
    } elseif (isset($data['userid']) && trim((string)$data['userid']) !== '') {
        $configuredUser = trim((string)$data['userid']);
    }

    $displayUser = $configuredUser ?: $topicUser;
    $displayDevice = null;
    if (isset($data['device']) && trim((string)$data['device']) !== '') {
        $displayDevice = trim((string)$data['device']);
    } elseif (isset($data['tid']) && trim((string)$data['tid']) !== '') {
        $displayDevice = trim((string)$data['tid']);
    } elseif ($topicDevice) {
        $displayDevice = $topicDevice;
    }

    $displayName = null;
    if ($displayUser && $displayDevice) {
        $displayName = $displayUser . '/' . $displayDevice;
    } elseif ($displayUser) {
        $displayName = $displayUser;
    } elseif ($displayDevice) {
        $displayName = $displayDevice;
    }

    return [
        'configured_user' => $configuredUser,
        'topic_user' => $topicUser,
        'topic_device' => $topicDevice,
        'display_name' => $displayName
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
if ($rawInput === false || trim($rawInput) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request body']);
    exit;
}

$data = json_decode($rawInput, true);

if (!is_array($data)) {
    // Fallback for form-encoded payloads.
    parse_str($rawInput, $data);
}

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

if (isset($data['_type']) && $data['_type'] !== 'location') {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported OwnTracks _type']);
    exit;
}

$latValue = $data['lat'] ?? $data['latitude'] ?? null;
$lngValue = $data['lng'] ?? $data['lon'] ?? $data['longitude'] ?? null;

if ($latValue === null || $lngValue === null || !is_numeric($latValue) || !is_numeric($lngValue)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid coordinates (expected lat and lng/lon)']);
    exit;
}

$lat = (float) $latValue;
$lng = (float) $lngValue;

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'Coordinates out of range']);
    exit;
}

$timestamp = date('c');
if (isset($data['tst']) && is_numeric($data['tst'])) {
    $timestamp = gmdate('c', (int) $data['tst']);
} elseif (isset($data['timestamp']) && is_string($data['timestamp']) && trim($data['timestamp']) !== '') {
    $timestamp = trim($data['timestamp']);
}

$userId = user_id_from_payload($data);
$identity = owntracks_identity_from_payload($data);

$position = [
    'user_id' => $userId,
    'display_name' => $identity['display_name'] ?: $userId,
    'configured_user' => $identity['configured_user'],
    'topic_user' => $identity['topic_user'],
    'topic_device' => $identity['topic_device'],
    'lat' => $lat,
    'lng' => $lng,
    'timestamp' => $timestamp,
    'tst' => isset($data['tst']) && is_numeric($data['tst']) ? (int)$data['tst'] : time(),
    'source' => isset($data['_type']) ? 'owntracks' : 'manual'
];

$storageFile = 'chaser_positions.json';
$positions = [];

if (file_exists($storageFile)) {
    $decoded = json_decode(file_get_contents($storageFile), true);
    if (is_array($decoded)) {
        $positions = $decoded;
    }
}

$positions[$userId] = $position;
$saved = file_put_contents($storageFile, json_encode($positions, JSON_PRETTY_PRINT), LOCK_EX);

// Backward compatibility for tools that still read the single-position file.
if ($saved !== false) {
    file_put_contents('chaser_position.json', json_encode($position, JSON_PRETTY_PRINT), LOCK_EX);
}

if ($saved === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save position']);
    exit;
}

echo json_encode([
    'message' => 'Position saved successfully',
    'user_id' => $userId,
    'display_name' => $position['display_name'],
    'lat' => $lat,
    'lng' => $lng,
    'timestamp' => $timestamp
]);
?>