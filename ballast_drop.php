<?php
// ballast_drop.php
require_once "db_config.php";
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { http_response_code(500); die("DB-Fehler"); }

// Debug-Modus: ?debug=1 zeigt alle letzten 50 Zeilen ohne Filter
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    $sql = "SELECT ballast_drop, alt_ft, created_at, ballast_drop_count, sand_count, bag_count FROM vario_data
            ORDER BY created_at DESC LIMIT 50";
    $res = $conn->query($sql);
    $data = [];
    while ($row = $res->fetch_assoc()) { $data[] = $row; }
    header('Content-Type: application/json');
    echo json_encode($data);
    $conn->close();
    exit;
}

// Alle Zeilen der letzten 24h holen, nach Zeit sortiert
$sql = "SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at, alt_ft, sand_count, bag_count
        FROM vario_data
        WHERE created_at >= NOW() - INTERVAL 24 HOUR
          AND (sand_count IS NOT NULL OR bag_count IS NOT NULL)
        ORDER BY created_at ASC";
$res = $conn->query($sql);

$rows = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
}

// Drop-Ereignisse ableiten:
// sand_count steigt (abgeworfener Sand) UND bag_count sinkt (verbleibende Bags) — gleiches Event
$drops = [];
$prevSand = null;
$prevBag  = null;

foreach ($rows as $row) {
    $sand = $row['sand_count'] !== null ? intval($row['sand_count']) : null;
    $bag  = $row['bag_count']  !== null ? intval($row['bag_count'])  : null;

    $sandDrop = ($prevSand !== null && $sand !== null && $sand > $prevSand);
    $bagUsed  = ($prevBag  !== null && $bag  !== null && $bag  < $prevBag);

    if ($sandDrop || $bagUsed) {
        $deltaSand = ($sandDrop && $prevSand !== null) ? ($sand - $prevSand) : 0;
        $deltaBag  = ($bagUsed  && $prevBag  !== null) ? ($prevBag - $bag)  : 0;
        $drops[] = [
            'created_at' => $row['created_at'],
            'alt_ft'     => $row['alt_ft'],
            'sand_count' => $sand,
            'bag_count'  => $bag,
            'delta_sand' => $deltaSand,
            'delta_bag'  => $deltaBag
        ];
    }

    if ($sand !== null) $prevSand = $sand;
    if ($bag  !== null) $prevBag  = $bag;
}

header('Content-Type: application/json');
echo json_encode($drops);
$conn->close();
?>