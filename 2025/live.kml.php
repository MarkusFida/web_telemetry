<?php
header('Content-Type: application/vnd.google-earth.kml+xml');
require_once "db_config.php";

$db = new mysqli($db_host, $db_user, $db_pass, $db_name);

$res = $db->query("SELECT * FROM vario_data WHERE created_at > NOW() - INTERVAL 12 HOUR ORDER BY created_at ASC");

$coords = [];
while ($row = $res->fetch_assoc()) {
    // GPS-Koordinaten inkl. Höhe
    $coords[] = "{$row['gps_lng']},{$row['gps_lat']}," . ($row['gps_alt'] ?? 0);
}
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<kml xmlns="http://www.opengis.net/kml/2.2">
<Document>
  <NetworkLinkControl>
    <minRefreshPeriod>5</minRefreshPeriod>
  </NetworkLinkControl>
  <Placemark>
    <name>Track</name>
    <Style>
      <LineStyle>
        <color>ff00ffff</color>
        <width>4</width>
      </LineStyle>
    </Style>
    <LineString>
      <tessellate>1</tessellate>
      <altitudeMode>absolute</altitudeMode>
      <coordinates>
<?php foreach($coords as $c) echo $c."\n"; ?>
      </coordinates>
    </LineString>
  </Placemark>
</Document>
</kml>