<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$multiFile = 'chaser_positions.json';

if (!file_exists($multiFile)) {
    echo json_encode(['users' => []]);
    exit;
}

$positions = json_decode(file_get_contents($multiFile), true);
if (!is_array($positions)) {
    echo json_encode(['error' => 'Invalid JSON data', 'users' => []]);
    exit;
}

$usersWithTs = [];
foreach ($positions as $userId => $position) {
    if (!is_array($position)) {
        continue;
    }
    $ts = 0;
    if (isset($position['tst']) && is_numeric($position['tst'])) {
        $ts = (int)$position['tst'];
    } elseif (isset($position['timestamp']) && is_string($position['timestamp'])) {
        $parsed = strtotime($position['timestamp']);
        if ($parsed !== false) {
            $ts = $parsed;
        }
    }
    $label = isset($position['display_name']) && trim((string)$position['display_name']) !== ''
        ? trim((string)$position['display_name'])
        : (string)$userId;
    $usersWithTs[] = ['user_id' => (string)$userId, 'label' => $label, 'ts' => $ts];
}

usort($usersWithTs, function($a, $b) {
    return $b['ts'] <=> $a['ts'];
});

$users = array_map(function($entry) {
    return [
        'user_id' => $entry['user_id'],
        'label' => $entry['label']
    ];
}, $usersWithTs);

echo json_encode([
    'users' => $users,
    'count' => count($users)
]);
?>