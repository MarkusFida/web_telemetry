<?php
header('Content-Type: application/json');
require_once "db_config.php";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

// Temporäre einfache Version zum Testen
if (isset($_GET['test'])) {
    error_log("Test mode activated");
    $sql = "SELECT gps_lat AS lat, gps_lng AS lon, 
                   CASE 
                       WHEN created_at IS NOT NULL THEN created_at
                       WHEN gps_time IS NOT NULL THEN CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' ', gps_time)
                       ELSE NULL
                   END as zeit
            FROM vario_data
            WHERE gps_lat IS NOT NULL AND gps_lng IS NOT NULL
            ORDER BY 
                CASE 
                    WHEN created_at IS NOT NULL THEN created_at
                    WHEN gps_time IS NOT NULL THEN CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' ', gps_time)
                    ELSE NOW()
                END ASC
            LIMIT 500";

    $res = $conn->query($sql);
    if ($res === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Test query failed: ' . $conn->error]);
        exit;
    }

    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[] = [
            'lat' => floatval($row['lat']),
            'lon' => floatval($row['lon']),
            'zeit' => $row['zeit']
        ];
    }

    $response = [
        'data' => $data,
        'count' => count($data),
        'mode' => 'test',
        'message' => 'Test mode: Last 500 records'
    ];

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

// Parameter auslesen
$from = isset($_GET['from']) ? $_GET['from'] : null;
$to = isset($_GET['to']) ? $_GET['to'] : null;
$interval = isset($_GET['interval']) ? floatval($_GET['interval']) : 0;

if ($from && $to) {
    $fromTs = strtotime($from);
    $toTs = strtotime($to);
    if ($fromTs !== false && $toTs !== false && $fromTs > $toTs) {
        $tmp = $from;
        $from = $to;
        $to = $tmp;
    }
}

// Debug: Parameter loggen
error_log("Track API called with: from=" . ($from ?: 'null') . ", to=" . ($to ?: 'null') . ", interval=" . $interval);

// Test: Einfache Abfrage ohne Filter
$test_sql = "SELECT COUNT(*) as total FROM vario_data";
$test_result = $conn->query($test_sql);
if ($test_result) {
    $test_row = $test_result->fetch_assoc();
    error_log("Total records in database: " . $test_row['total']);
} else {
    error_log("Test query failed: " . $conn->error);
}

// Test: Anzahl gültiger Koordinaten
$coord_test_sql = "SELECT COUNT(*) as valid_coords FROM vario_data WHERE gps_lat IS NOT NULL AND gps_lng IS NOT NULL AND gps_lat != 0 AND gps_lng != 0";
$coord_test_result = $conn->query($coord_test_sql);
if ($coord_test_result) {
    $coord_test_row = $coord_test_result->fetch_assoc();
    error_log("Valid coordinates in database: " . $coord_test_row['valid_coords']);
} else {
    error_log("Coordinate test query failed: " . $conn->error);
}

// Basis-SQL für Zeitraum-Filter
$timeExpr = "CASE 
                    WHEN created_at IS NOT NULL THEN created_at
                    WHEN gps_time IS NOT NULL THEN CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' ', gps_time)
                    ELSE NULL
                END";

if ($from && $to) {
    error_log("Loading data for time range: $from to $to");
    $sql = "SELECT lat, lon, zeit FROM (
                SELECT gps_lat AS lat, gps_lng AS lon,
                       $timeExpr AS zeit
                FROM vario_data
                WHERE gps_lat IS NOT NULL AND gps_lng IS NOT NULL
            ) AS track_data
            WHERE track_data.zeit IS NOT NULL
              AND track_data.zeit >= ?
              AND track_data.zeit <= ?
            ORDER BY track_data.zeit ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['error' => 'SQL prepare failed: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('ss', $from, $to);
}

// Fallback: Wenn keine Zeitparameter angegeben sind, lade die letzten 1000 Einträge
if (!$from || !$to) {
    error_log("No time parameters provided, using fallback mode");
    $sql = "SELECT gps_lat AS lat, gps_lng AS lon, 
                   $timeExpr as zeit
            FROM vario_data
            WHERE gps_lat IS NOT NULL AND gps_lng IS NOT NULL
            ORDER BY 
                $timeExpr DESC
            LIMIT 1000";

    $res = $conn->query($sql);
    if ($res === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Fallback query failed: ' . $conn->error]);
        exit;
    }

    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[] = [
            'lat' => floatval($row['lat']),
            'lon' => floatval($row['lon']),
            'zeit' => $row['zeit']
        ];
    }

    // Daten umkehren (älteste zuerst für Track-Linie)
    $data = array_reverse($data);

    $response = [
        'data' => $data,
        'count' => count($data),
        'mode' => 'fallback',
        'from' => $from,
        'to' => $to,
        'interval' => $interval
    ];

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

// SQL-Abfrage für alle Fälle, in denen kein direkter Zeitraum gesetzt ist
if ($from && $to) {
    // bereits vorbereitet
} else {
    // Alle verfügbaren Punkte laden (interval=0)
    $sql = "SELECT gps_lat AS lat, gps_lng AS lon, 
                   $timeExpr as zeit
            FROM vario_data
            WHERE gps_lat IS NOT NULL AND gps_lng IS NOT NULL
            ORDER BY 
                $timeExpr ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['error' => 'SQL prepare failed: ' . $conn->error]);
        exit;
    }
}

$result = $stmt->execute();
if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'SQL execute failed: ' . $stmt->error]);
    exit;
}

$result = $stmt->get_result();

$data = [];
$lastTime = 0;

while ($row = $result->fetch_assoc()) {
    // Verarbeite Zeitstempel - jetzt direkt aus created_at oder Fallback
    $timeString = $row['zeit'];
    
    $currentTime = strtotime($timeString);
    
    // Überspringe ungültige Zeitstempel
    if ($currentTime === false) {
        error_log("Invalid timestamp skipped: " . $row['zeit']);
        continue;
    }

    // Intervall-Filterung anwenden (falls interval > 0)
    if ($interval > 0 && $lastTime > 0) {
        $timeDiff = $currentTime - $lastTime;
        if ($timeDiff < ($interval * 60)) { // Interval in Minuten umrechnen
            continue; // Zu nah beieinander, überspringen
        }
    }

    $data[] = [
        'lat' => isset($row['lat']) && $row['lat'] !== null ? floatval($row['lat']) : null,
        'lon' => isset($row['lon']) && $row['lon'] !== null ? floatval($row['lon']) : null,
        'zeit' => $timeString
    ];

    $lastTime = $currentTime;
}

// Debug-Info in Response hinzufügen
$response = [
    'data' => $data,
    'count' => count($data),
    'from' => $from,
    'to' => $to,
    'interval' => $interval,
    'query' => $sql
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$stmt->close();
$conn->close();
?>