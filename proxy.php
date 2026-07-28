<?php
$url = "https://api.opentopodata.org/v1/srtm90m?locations=" . urlencode($_GET['locations']);
header("Content-Type: application/json");
echo file_get_contents($url);
?>