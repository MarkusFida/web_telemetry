<?php
// Setze den Content-Type auf JSON
header('Content-Type: application/json');

// Stelle sicher, dass der 'settings' Ordner existiert
$settingsDir = 'settings/';
if (!is_dir($settingsDir)) {
    mkdir($settingsDir, 0755, true); // Erstelle den Ordner, falls er nicht existiert
}

// Liste alle JSON-Dateien im 'settings/' Unterordner
$files = glob($settingsDir . '*.json');

// Entferne den Pfad-Präfix, um nur die Dateinamen zurückzugeben (optional, aber nützlich für das Select)
$files = array_map(function($file) {
    return basename($file);
}, $files);

// Gib das JSON zurück
echo json_encode($files);
?>