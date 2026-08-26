<?php
// Error reporting für Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Datenbank-Zugangsdaten
    $db_host = "mysqlsvr75.world4you.com";
    $db_user = "sql4017583";
    $db_pass = "z0fppd*h";
    $db_name = "9622030db5";

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Test, ob die Tabelle existiert
    $result = $conn->query("SHOW TABLES LIKE 'adsb_data'");
    if ($result->num_rows == 0) {
        throw new Exception('Table adsb_data does not exist. Please run the SQL script first.');
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['flights']) || !is_array($data['flights'])) {
        throw new Exception('Invalid data format');
    }

    // Gemeinsamen Zeitstempel für alle Flüge erstellen
    $timestamp = date('Y-m-d H:i:s');

    // Alle Flüge in die Datenbank einfügen
    $inserted = 0;
    $errors = [];

    foreach ($data['flights'] as $flight) {
        // Daten validieren
        if (!isset($flight['callsign']) || !isset($flight['lat']) || !isset($flight['lon'])) {
            $errors[] = 'Missing required fields for flight: ' . ($flight['callsign'] ?? 'unknown');
            continue;
        }

        $callsign = $conn->real_escape_string($flight['callsign']);
        $registration = isset($flight['registration']) ? $conn->real_escape_string($flight['registration']) : '';
        $altitude = isset($flight['altitude']) && is_numeric($flight['altitude']) ? $flight['altitude'] : 'NULL';
        $speed = isset($flight['speed']) && is_numeric($flight['speed']) ? $flight['speed'] : 'NULL';
        $track = isset($flight['track']) && is_numeric($flight['track']) ? $flight['track'] : 'NULL';
        $latitude = $flight['lat'];
        $longitude = $flight['lon'];
        $country_code = isset($flight['country_code']) ? $conn->real_escape_string($flight['country_code']) : '';

        $sql = "INSERT INTO adsb_data (
            callsign, registration, altitude, speed, track, latitude, longitude, country_code, timestamp
        ) VALUES (
            '$callsign', '$registration', $altitude, $speed, $track, $latitude, $longitude, '$country_code', '$timestamp'
        )";

        if ($conn->query($sql) === TRUE) {
            $inserted++;
        } else {
            $errors[] = 'Failed to insert flight ' . $callsign . ': ' . $conn->error;
        }
    }

    $conn->close();

    if (count($errors) > 0) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Some flights failed to save',
            'inserted' => $inserted,
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'message' => 'ADSB data saved successfully',
            'inserted' => $inserted,
            'timestamp' => $timestamp
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
