<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!empty($_GET['url'])) {
    $target = $_GET['url'];
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: Mozilla/5.0\r\nAccept: */*\r\n",
            'ignore_errors' => true,
            'timeout' => 20,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $data = @file_get_contents($target, false, $context);
    if ($data === false) {
        http_response_code(502);
        echo 'Proxy fetch failed';
        exit;
    }

    $contentType = 'application/xml';
    $lower = strtolower($target);
    if (strpos($lower, '.geojson') !== false || substr($lower, -5) === '.json' || strpos($lower, 'json') !== false) {
        $contentType = 'application/json';
    }

    header('Content-Type: ' . $contentType);
    echo $data;
    exit;
}

$url = "https://api.opentopodata.org/v1/srtm90m?locations=" . urlencode($_GET['locations']);
header("Content-Type: application/json");
echo file_get_contents($url);
?>