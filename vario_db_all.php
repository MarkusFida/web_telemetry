<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- DB-Verbindung ---
require_once "db_config.php";
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => $conn->connect_error]);
    exit;
}

// --- ALLE Daten holen ---
$sql = "SELECT *, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as ts FROM vario_data ORDER BY created_at DESC";
$res = $conn->query($sql);

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);
$conn->close();
?>