<?php
// DB-Zugangsdaten anpassen!
require_once "db_config.php";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'DB connection failed: ' . $conn->connect_error]));
}

// Hole die letzten X Stunden
$hours = isset($_GET['hours']) ? floatval($_GET['hours']) : 2.0;
$seconds = intval($hours * 3600);

// DEBUG: Return row count and timeframe info
$countSql = "SELECT COUNT(*) as cnt FROM vario_data WHERE created_at >= UTC_TIMESTAMP() - INTERVAL $seconds SECOND";
$countRes = $conn->query($countSql);
$countRow = $countRes->fetch_assoc();
$rowCount = intval($countRow['cnt']);

// Get min/max dates
$minMaxSql = "SELECT MIN(created_at) as min_time, MAX(created_at) as max_time, COUNT(*) as total FROM vario_data";
$mmRes = $conn->query($minMaxSql);
$mmRow = $mmRes->fetch_assoc();

// Main query
$sql = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as gps_time_utc,
            DATE_FORMAT(created_at, '%H:%i:%s') as ts_utc,
            DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as datetime_utc,
            gps_course as track, 
            gps_speed as speed, 
            alt_ft as baro_alt, 
            temp, vario, 
            gps_lat AS lat, 
            gps_lng AS lon, 
            peak, valley, ballast_drop, ballast_drop_count,
            humidity, sand_count, bag_count
        FROM vario_data
        WHERE created_at >= UTC_TIMESTAMP() - INTERVAL $seconds SECOND
        ORDER BY created_at ASC
        LIMIT 5000";

$res = $conn->query($sql);
if (!$res) {
    http_response_code(400);
    echo json_encode(['error' => 'DB query failed: ' . $conn->error, 'sql' => $sql]);
    exit;
}

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
        'gps_time' => $row['gps_time_utc'],
        'ts' => $row['ts_utc'],
        'datetime' => $row['datetime_utc'],
        'track' => floatval($row['track']),
        'speed' => floatval($row['speed']),
        'baro_alt' => floatval($row['baro_alt']),
        'temp' => isset($row['temp']) ? floatval($row['temp']) : null,
        'vario' => isset($row['vario']) ? floatval($row['vario']) : null,
        'lat' => isset($row['lat']) ? floatval($row['lat']) : null,
        'lon' => isset($row['lon']) ? floatval($row['lon']) : null,
        'peak' => isset($row['peak']) ? intval($row['peak']) : 0,
        'valley' => isset($row['valley']) ? intval($row['valley']) : 0,
        'ballast_drop' => isset($row['ballast_drop']) ? $row['ballast_drop'] : "",
        'ballast_drop_count' => isset($row['ballast_drop_count']) ? intval($row['ballast_drop_count']) : 0,
        'humidity' => ($row['humidity'] !== null && $row['humidity'] !== '' && strtolower($row['humidity']) !== 'null') ? floatval($row['humidity']) : null,
        'sand_count' => isset($row['sand_count']) ? intval($row['sand_count']) : 0,
        'bag_count' => isset($row['bag_count']) ? intval($row['bag_count']) : 0
    ];
}

header('Content-Type: application/json');
// Return just the data array for backward compatibility with chart_1.html
echo json_encode($data);
$conn->close();
?>