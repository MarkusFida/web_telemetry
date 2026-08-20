<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

/// --- Aktuelle Werte aus Datei ---
$current = [
    'currentDirection' => 0.0,
    'avgDirection' => 0.0,
    'currentSpeed' => 0.0,
    'avgSpeed' => 0.0,
    'lastUpdate' => null
];

$file = @file_get_contents('wind_data.txt');
if ($file) {
    $lines = explode("\n", trim($file));
     foreach ($lines as $line) {
        if (preg_match('/Current Direction: ([\d.]+) deg/', $line, $matches)) {
            $current['currentDirection'] = floatval($matches[1]);
        } elseif (preg_match('/5-Min Avg Direction: ([\d.]+) deg/', $line, $matches)) {
            $current['avgDirection'] = floatval($matches[1]);
        } elseif (preg_match('/Current Speed: ([\d.]+) m\/s/', $line, $matches)) {
            $current['currentSpeed'] = floatval($matches[1]);
        } elseif (preg_match('/5-Min Avg Speed: ([\d.]+) m\/s/', $line, $matches)) {
            $current['avgSpeed'] = floatval($matches[1]);
        } elseif (preg_match('/Timestamp: ([\d\-T:]+)(Z)?/', $line, $matches)) {
            $current['lastUpdate'] = $matches[1] . 'Z';
        }
    }
}

// --- History aus Datenbank ---
$dbhost = "mysqlsvr84.world4you.com"; // oder den von World4You angegebenen Hostnamen
$dbuser = "sql2989988";
$dbpass = "jfk3g+mk";
$dbname = "9622030db2";

$conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) { http_response_code(500); exit("DB-Fehler: " . $conn->connect_error); }

$now = new DateTime("now", new DateTimeZone("UTC"));
$twoHoursAgo = clone $now;
$twoHoursAgo->modify('-2 hours');
$from = $twoHoursAgo->format('Y-m-d H:i:s');

$sqlHistory = "SELECT * FROM windlog WHERE timestamp >= ? ORDER BY timestamp ASC";
$stmt = $conn->prepare($sqlHistory);
$stmt->bind_param("s", $from);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $dt = new DateTime($row['timestamp'], new DateTimeZone('UTC'));
    $row['timestamp'] = $dt->format('Y-m-d\TH:i:s\Z');
    $history[] = $row;
}

// Fallback: Wenn weniger als z.B. 10 Einträge, hole alle Daten
if (count($history) < 10) {
    $sqlAll = "SELECT * FROM windlog ORDER BY timestamp ASC";
    $resultAll = $conn->query($sqlAll);
    $history = [];
    while ($row = $resultAll->fetch_assoc()) {
        $dt = new DateTime($row['timestamp'], new DateTimeZone('UTC'));
        $row['timestamp'] = $dt->format('Y-m-d\TH:i:s\Z');
        $history[] = $row;
    }
}

$stmt->close();
$conn->close();

// --- Ausgabe ---
echo json_encode([
    'current' => $current,
    'history' => $history
]);
?>