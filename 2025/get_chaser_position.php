<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function sanitize_user_id($userId) {
    $userId = strtolower(trim((string)$userId));
    if ($userId === 'auto') {
        return 'auto';
    }
    $userId = preg_replace('/[^a-z0-9_\-\.]/', '_', $userId);
    if ($userId === '' || $userId === null) {
        $userId = 'default';
    }
    return substr($userId, 0, 64);
}

function parse_position_time($position) {
    if (isset($position['timestamp']) && is_string($position['timestamp'])) {
        $ts = strtotime($position['timestamp']);
        if ($ts !== false) {
            return $ts;
        }
    }
    if (isset($position['tst']) && is_numeric($position['tst'])) {
        return (int)$position['tst'];
    }
    return 0;
}

function get_latest_user_id($positions) {
    $latestUserId = null;
    $latestTs = -1;
    foreach ($positions as $userId => $position) {
        if (!is_array($position)) {
            continue;
        }
        $ts = parse_position_time($position);
        if ($ts > $latestTs) {
            $latestTs = $ts;
            $latestUserId = $userId;
        }
    }
    return $latestUserId;
}

$requestedUserId = isset($_GET['user_id']) ? sanitize_user_id($_GET['user_id']) : 'default';
$multiFile = 'chaser_positions.json';

if (file_exists($multiFile)) {
    $positions = json_decode(file_get_contents($multiFile), true);
    if (!is_array($positions)) {
        echo json_encode(['error' => 'Invalid JSON data']);
        exit;
    }

    if ($requestedUserId === 'auto') {
        $latestUserId = get_latest_user_id($positions);
        if ($latestUserId !== null && isset($positions[$latestUserId]) && is_array($positions[$latestUserId])) {
            $latestData = $positions[$latestUserId];
            $latestData['requested_user_id'] = 'auto';
            $latestData['selected_user_id'] = $latestUserId;
            $latestData['message'] = 'Auto-selected latest OwnTracks user.';
            echo json_encode($latestData);
            exit;
        }
    }

    if (isset($positions[$requestedUserId]) && is_array($positions[$requestedUserId])) {
        echo json_encode($positions[$requestedUserId]);
        exit;
    }

    // Fallback: if requested user has no entry, return the first available user's position.
    $availableUsers = array_keys($positions);
    if (count($availableUsers) > 0) {
        $fallbackUserId = $availableUsers[0];
        $fallbackData = $positions[$fallbackUserId];
        if (is_array($fallbackData)) {
            $fallbackData['requested_user_id'] = $requestedUserId;
            $fallbackData['fallback_user_id'] = $fallbackUserId;
            $fallbackData['message'] = 'Requested user not found. Returned fallback user position.';
            echo json_encode($fallbackData);
            exit;
        }
    }

    echo json_encode([
        'user_id' => $requestedUserId,
        'lat' => null,
        'lng' => null,
        'message' => 'No saved position for this user',
        'available_users' => $availableUsers
    ]);
    exit;
}

// Backward compatibility with the old single-position file.
$legacyFile = 'chaser_position.json';
if (file_exists($legacyFile)) {
    $data = json_decode(file_get_contents($legacyFile), true);
    if ($data) {
        if (!isset($data['user_id'])) {
            $data['user_id'] = 'default';
        }
        echo json_encode($data);
    } else {
        echo json_encode(['error' => 'Invalid JSON data']);
    }
} else {
    echo json_encode(['user_id' => $requestedUserId, 'lat' => null, 'lng' => null, 'message' => 'No saved position']);
}
?>