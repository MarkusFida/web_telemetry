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

// Letzten GPS-Datensatz holen (achte auf die richtigen Spaltennamen!)
$res = $db->query("SELECT gps_lat, gps_lng, gps_time FROM vario_data WHERE gps_lat IS NOT NULL AND gps_lng IS NOT NULL AND gps_lat != 0 AND gps_lng != 0 ORDER BY created_at DESC LIMIT 1");
$row = $res ? $res->fetch_assoc() : null;

header('Content-Type: application/json');
if ($row) {
    echo json_encode([
        'gps_lat' => isset($row['gps_lat']) ? floatval($row['gps_lat']) : null,
        'gps_lng' => isset($row['gps_lng']) ? floatval($row['gps_lng']) : null,
        'gps_time' => $row['gps_time']
    ]);
} else {
    echo json_encode(["gps_lat" => null, "gps_lng" => null, "gps_time" => null]);
}
$db->close();
?>