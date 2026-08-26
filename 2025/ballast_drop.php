<?php
// ballast_drop.php
require_once "db_config.php";
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { http_response_code(500); die("DB-Fehler"); }

// Jetzt auch alt_ft, created_at und ballast_drop_count mit ausgeben:
$sql = "SELECT DISTINCT ballast_drop, alt_ft, created_at, ballast_drop_count FROM vario_data
        WHERE ballast_drop IS NOT NULL AND ballast_drop != '' AND ballast_drop != 'NO_GPS'
        AND created_at >= NOW() - INTERVAL 24 HOUR
        ORDER BY ballast_drop ASC";
$res = $conn->query($sql);

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
        'ballast_drop' => $row['ballast_drop'],
        'alt_ft' => $row['alt_ft'],
        'created_at' => $row['created_at'],
        'ballast_drop_count' => $row['ballast_drop_count']  // Neu hinzugefügt
    ];
}
header('Content-Type: application/json');
echo json_encode($data);
$conn->close();
?>