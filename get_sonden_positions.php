<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$file = 'sonden_data.txt';
$positions = [];
if (file_exists($file)) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode(',', $line);
        if (count($parts) >= 2) {
            $positions[] = [
                'lat' => floatval($parts[0]),
                'lng' => floatval($parts[1]),
                'timestamp' => isset($parts[2]) ? $parts[2] : ''
            ];
        }
    }
}
echo json_encode($positions);
?>