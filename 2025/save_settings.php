<?php
// Stelle sicher, dass der 'settings' Ordner existiert
$settingsDir = 'settings/';
if (!is_dir($settingsDir)) {
    mkdir($settingsDir, 0755, true); // Erstelle den Ordner, falls er nicht existiert
}

// Hole den Dateinamen aus dem Query-Parameter
$filename = isset($_GET['filename']) ? $_GET['filename'] : null;
if (!$filename) {
    http_response_code(400);
    echo "Fehler: Dateiname fehlt.";
    exit;
}

// Stelle sicher, dass der Dateiname sicher ist (nur alphanumerische Zeichen, Unterstriche, Bindestriche und Punkte)
if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
    http_response_code(400);
    echo "Fehler: Ungültiger Dateiname.";
    exit;
}

// Hole die JSON-Daten aus dem POST-Body
$jsonData = file_get_contents('php://input');
if (!$jsonData) {
    http_response_code(400);
    echo "Fehler: Keine Daten empfangen.";
    exit;
}

// Validiere, dass es gültiges JSON ist
$decoded = json_decode($jsonData);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo "Fehler: Ungültiges JSON.";
    exit;
}

// Schreibe die Daten in die Datei im 'settings' Ordner
$filePath = $settingsDir . $filename;
if (file_put_contents($filePath, $jsonData) === false) {
    http_response_code(500);
    echo "Fehler: Datei konnte nicht gespeichert werden.";
    exit;
}

echo "Datei '$filename' erfolgreich in '$settingsDir' gespeichert.";
?>