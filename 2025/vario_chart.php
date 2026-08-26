<?php
// DB-Zugangsdaten anpassen!
require_once "db_config.php";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    die("DB-Fehler");
}

// Hole die letzten X Stunden
$hours = isset($_GET['hours']) ? floatval($_GET['hours']) : 2.0;
$seconds = intval($hours * 3600);
$sql = "SELECT 
            CONVERT_TZ(gps_time, @@session.time_zone, '+00:00') as gps_time_utc,
            DATE_FORMAT(CONVERT_TZ(created_at, @@session.time_zone, '+00:00'), '%H:%i') as ts_utc,
            DATE_FORMAT(CONVERT_TZ(created_at, @@session.time_zone, '+00:00'), '%Y-%m-%d %H:%i:%s') as datetime_utc,
            gps_course as track, 
            gps_speed as speed, 
            alt_ft as baro_alt, 
            temp, vario, 
            gps_lat AS lat, 
            gps_lng AS lon, 
            peak, valley, ballast_drop, ballast_drop_count,
            humidity
        FROM vario_data
        WHERE created_at >= NOW() - INTERVAL $seconds SECOND
        ORDER BY created_at ASC";
$res = $conn->query($sql);

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
        'humidity' => ($row['humidity'] !== null && $row['humidity'] !== '' && strtolower($row['humidity']) !== 'null') ? floatval($row['humidity']) : null
    ];
}
header('Content-Type: application/json');
echo json_encode($data);
$conn->close();
?>