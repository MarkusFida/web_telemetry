<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Datenbank-Zugangsdaten
    $db_host = "";
    $db_user = "";
    $db_pass = "";
    $db_name = "";

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    $callsign = isset($_GET['callsign']) ? $_GET['callsign'] : '';
    $hours = isset($_GET['hours']) ? intval($_GET['hours']) : 24;

    if (empty($callsign)) {
        // Alle verfügbaren Flugzeuge zurückgeben
        $sql = "SELECT DISTINCT callsign, registration, country_code FROM adsb_data ORDER BY timestamp DESC LIMIT 50";
        $result = $conn->query($sql);

        $aircraft = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $aircraft[] = [
                    'callsign' => $row['callsign'],
                    'registration' => $row['registration'],
                    'country_code' => $row['country_code']
                ];
            }
        }

        echo json_encode(['aircraft' => $aircraft]);
        exit;
    }

    // Daten für ein bestimmtes Flugzeug laden
    $sql = "SELECT timestamp, altitude, speed, track, latitude, longitude
            FROM adsb_data
            WHERE callsign = ?
            AND timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ORDER BY timestamp ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $callsign, $hours);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [
        'timestamps' => [],
        'altitude' => [],
        'speed' => [],
        'track' => [],
        'latitude' => [],
        'longitude' => []
    ];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data['timestamps'][] = $row['timestamp'];
            $data['altitude'][] = $row['altitude'] !== null ? floatval($row['altitude']) : null;
            $data['speed'][] = $row['speed'] !== null ? floatval($row['speed']) : null;
            $data['track'][] = $row['track'] !== null ? floatval($row['track']) : null;
            $data['latitude'][] = $row['latitude'] !== null ? floatval($row['latitude']) : null;
            $data['longitude'][] = $row['longitude'] !== null ? floatval($row['longitude']) : null;
        }
    }

    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
