<?php
// Datenbank-Zugangsdaten anpassen!
require_once "db_config.php";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    die("DB-Fehler");
}

// Daten aus POST holen
$data = [];
parse_str(file_get_contents("php://input"), $data);

// Felder prüfen und vorbereiten
$fields = [
    'qnh', 'alt_ft', 'alt_m', 'gps_alt', 'gps_lat', 'gps_lng',
    'gps_course', 'gps_speed', 'gps_sats', 'peak', 'valley', 'vario', 'temp', 'humidity', 'gps_time', 'ballast_drop', 'ballast_drop_count'
];
$values = [];
foreach ($fields as $f) {
    $values[$f] = isset($data[$f]) ? trim($data[$f]) : '';
}

// Für numerische Felder leere Strings in NULL umwandeln
$numericFields = [
    'qnh', 'alt_ft', 'alt_m', 'gps_alt', 'gps_lat', 'gps_lng',
    'gps_course', 'gps_speed', 'gps_sats', 'peak', 'valley', 'vario', 'temp', 'humidity', 'ballast_drop_count'
];
foreach ($numericFields as $f) {
    if ($values[$f] === '' || !is_numeric($values[$f])) {
        $values[$f] = 'NULL';
    } else {
        $values[$f] = $conn->real_escape_string($values[$f]);
    }
}

// Für Textfelder escapen
$textFields = ['gps_time', 'ballast_drop'];
foreach ($textFields as $f) {
    $values[$f] = $values[$f] !== '' ? ("'" . $conn->real_escape_string($values[$f]) . "'") : 'NULL';
}

// SQL-Statement bauen
$sql = "INSERT INTO vario_data (
    qnh, alt_ft, alt_m, gps_alt, gps_lat, gps_lng, gps_course, gps_speed, gps_sats,
    peak, valley, vario, temp, humidity, gps_time, ballast_drop, ballast_drop_count, created_at
) VALUES (
    {$values['qnh']}, {$values['alt_ft']}, {$values['alt_m']}, {$values['gps_alt']}, {$values['gps_lat']}, {$values['gps_lng']},
    {$values['gps_course']}, {$values['gps_speed']}, {$values['gps_sats']}, {$values['peak']}, {$values['valley']},
    {$values['vario']}, {$values['temp']}, {$values['humidity']}, {$values['gps_time']}, {$values['ballast_drop']}, {$values['ballast_drop_count']}, NOW()
)";

if ($conn->query($sql) === TRUE) {
    echo "OK";
} else {
    http_response_code(500);
    echo "DB-Fehler: " . $conn->error;
}
$conn->close();
?>