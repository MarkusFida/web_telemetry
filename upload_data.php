<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = file_get_contents('php://input');
    file_put_contents('wuzi_data.txt', $data);
    http_response_code(200);
    echo "Daten erfolgreich gespeichert";
} else {
    http_response_code(405);
    echo "Nur POST-Anfragen erlaubt";
}
?>