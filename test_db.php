<?php
// Einfacher Datenbank-Test
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $db_host = "mysqlsvr75.world4you.com";
    $db_user = "sql4017583";
    $db_pass = "z0fppd*h";
    $db_name = "9622030db5";

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception('Connection failed: ' . $conn->connect_error);
    }

    echo json_encode(['status' => 'Database connection successful']);
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
