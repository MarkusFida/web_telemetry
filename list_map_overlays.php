<?php
// Setze den Content-Type auf JSON
header('Content-Type: application/json');

// Stelle sicher, dass der 'map_overlays' Ordner existiert
$overlaysDir = 'map_overlays/';
if (!is_dir($overlaysDir)) {
    mkdir($overlaysDir, 0755, true); // Erstelle den Ordner, falls er nicht existiert
}

// Liste alle GPX/KML/KMZ Dateien im 'map_overlays/' Unterordner
$files = array_merge(
    glob($overlaysDir . '*.gpx'),
    glob($overlaysDir . '*.kml'),
    glob($overlaysDir . '*.kmz')
);

// Entferne den Pfad-Präfix, um nur die Dateinamen zurückzugeben
$files = array_map(function($file) {
    return basename($file);
}, $files);

sort($files);

// Gib das JSON zurück
echo json_encode(array_values($files));
?>
