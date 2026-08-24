<?php
header('Content-Type: application/json');

require_once "db_config.php";
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB-Fehler"]);
    exit;
}

$result = $conn->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS changed_at,
            sand_count, bag_count
     FROM vario_data
     WHERE sand_count IS NOT NULL OR bag_count IS NOT NULL
     ORDER BY created_at DESC
     LIMIT 5000"
);

$rows = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

function same_count($left, $right) {
    if ($left === null || $right === null) {
        return $left === $right;
    }
    return (string) $left === (string) $right;
}

$latest = $rows[0] ?? null;
$response = [
    'sand_count' => $latest['sand_count'] ?? null,
    'bag_count' => $latest['bag_count'] ?? null,
    'sand_changed_at' => null,
    'bag_changed_at' => null
];

if ($latest) {
    $previousSand = $latest['sand_count'];
    $previousBag = $latest['bag_count'];

    foreach ($rows as $row) {
        if ($response['sand_changed_at'] === null && !same_count($row['sand_count'], $previousSand)) {
            $response['sand_changed_at'] = $previousRow['changed_at'];
        }
        if ($response['bag_changed_at'] === null && !same_count($row['bag_count'], $previousBag)) {
            $response['bag_changed_at'] = $previousRow['changed_at'];
        }
        if ($response['sand_changed_at'] !== null && $response['bag_changed_at'] !== null) {
            break;
        }
        $previousSand = $row['sand_count'];
        $previousBag = $row['bag_count'];
        $previousRow = $row;
    }
}

$conn->close();
echo json_encode($response);
?>