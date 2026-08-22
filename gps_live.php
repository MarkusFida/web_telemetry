<?php
// filepath: c:\Users\m_fid\Documents\PlatformIO\Projects\Vario_Wuzi\HTTP\gps_live.php

// gps_live.php

// Datenbankverbindung herstellen
require_once "db_config.php";

$db = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($db->connect_error) {
    http_response_code(500);
    die("DB-Fehler");
}

// Letzten vollständigen Datensatz mit gültiger GPS-Position holen
$res = $db->query("SELECT gps_lat, gps_lng, gps_time, qnh, alt_ft, alt_m, gps_alt, gps_course, gps_speed, gps_sats, peak, valley, vario, temp, humidity, ballast_drop FROM vario_data WHERE gps_lat IS NOT NULL AND gps_lng IS NOT NULL AND gps_lat != 0 AND gps_lng != 0 ORDER BY created_at DESC LIMIT 1");
$row = $res ? $res->fetch_assoc() : null;

header('Content-Type: application/json');
if ($row) {
    echo json_encode([
        'gps_lat' => isset($row['gps_lat']) ? floatval($row['gps_lat']) : null,
        'gps_lng' => isset($row['gps_lng']) ? floatval($row['gps_lng']) : null,
        'gps_time' => $row['gps_time'],
        'qnh' => isset($row['qnh']) ? $row['qnh'] : null,
        'alt_ft' => isset($row['alt_ft']) ? $row['alt_ft'] : null,
        'alt_m' => isset($row['alt_m']) ? $row['alt_m'] : null,
        'gps_alt' => isset($row['gps_alt']) ? $row['gps_alt'] : null,
        'gps_course' => isset($row['gps_course']) ? $row['gps_course'] : null,
        'gps_speed' => isset($row['gps_speed']) ? $row['gps_speed'] : null,
        'gps_sats' => isset($row['gps_sats']) ? $row['gps_sats'] : null,
        'peak' => isset($row['peak']) ? $row['peak'] : null,
        'valley' => isset($row['valley']) ? $row['valley'] : null,
        'vario' => isset($row['vario']) ? $row['vario'] : null,
        'temp' => isset($row['temp']) ? $row['temp'] : null,
        'humidity' => isset($row['humidity']) ? $row['humidity'] : null,
        'ballast_drop' => isset($row['ballast_drop']) ? $row['ballast_drop'] : null
    ]);
} else {
    echo json_encode([
        "gps_lat" => null,
        "gps_lng" => null,
        "gps_time" => null,
        "qnh" => null,
        "alt_ft" => null,
        "alt_m" => null,
        "gps_alt" => null,
        "gps_course" => null,
        "gps_speed" => null,
        "gps_sats" => null,
        "peak" => null,
        "valley" => null,
        "vario" => null,
        "temp" => null,
        "humidity" => null,
        "ballast_drop" => null
    ]);
}
$db->close();
?>