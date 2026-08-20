<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lon = filter_input(INPUT_GET, 'lon', FILTER_VALIDATE_FLOAT);
$altitude = filter_input(INPUT_GET, 'altitude', FILTER_VALIDATE_FLOAT);
$course = filter_input(INPUT_GET, 'course', FILTER_VALIDATE_FLOAT);
$speed = filter_input(INPUT_GET, 'speed', FILTER_VALIDATE_FLOAT);
$startTime = isset($_GET['time']) ? strtotime((string) $_GET['time']) : time();

if ($lat === false || $lon === false || $altitude === false || $course === false || $speed === false || $startTime === false ||
    $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180 || $altitude < 0 || $speed < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige GPS-, Höhen-, Kurs- oder Geschwindigkeitsdaten']);
    exit;
}

$pressureLevels = [1000, 975, 950, 925, 900, 850, 800, 700, 600, 500];
$pressure = 1013.25 * pow(1 - min($altitude, 11000) / 44330, 5.255);
$lowerPressure = min($pressureLevels);
$upperPressure = max($pressureLevels);
foreach ($pressureLevels as $level) {
    if ($level <= $pressure && $level > $lowerPressure) {
        $lowerPressure = $level;
    }
    if ($level >= $pressure && $level < $upperPressure) {
        $upperPressure = $level;
    }
}
$pressureWeight = $upperPressure === $lowerPressure ? 0.0 : ($pressure - $lowerPressure) / ($upperPressure - $lowerPressure);
$pressureWeight = max(0.0, min(1.0, $pressureWeight));

$variables = [];
foreach ($pressureLevels as $level) {
    $variables[] = "wind_speed_{$level}hPa";
    $variables[] = "wind_direction_{$level}hPa";
}

$query = http_build_query([
    'latitude' => $lat,
    'longitude' => $lon,
    'hourly' => implode(',', $variables),
    'models' => 'icon_eu',
    'forecast_days' => 3,
    'timezone' => 'UTC'
]);
$url = "https://api.open-meteo.com/v1/forecast?{$query}";
$context = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
$response = @file_get_contents($url, false, $context);
$forecast = $response !== false ? json_decode($response, true) : null;

if (!is_array($forecast) || !isset($forecast['hourly']['time'])) {
    http_response_code(502);
    echo json_encode(['error' => 'ICON-EU-Wetterdaten konnten nicht geladen werden']);
    exit;
}

$times = $forecast['hourly']['time'];
$timeStamps = array_map('strtotime', $times);

function interpolateSeries(array $values, array $timeStamps, int $timestamp): float {
    $lastIndex = count($timeStamps) - 1;
    if ($lastIndex < 0) return 0.0;
    if ($timestamp <= $timeStamps[0]) return (float) ($values[0] ?? 0);
    if ($timestamp >= $timeStamps[$lastIndex]) return (float) ($values[$lastIndex] ?? 0);
    for ($index = 1; $index <= $lastIndex; $index++) {
        if ($timestamp <= $timeStamps[$index]) {
            $span = $timeStamps[$index] - $timeStamps[$index - 1];
            $factor = $span > 0 ? ($timestamp - $timeStamps[$index - 1]) / $span : 0;
            $before = (float) ($values[$index - 1] ?? 0);
            $after = (float) ($values[$index] ?? 0);
            return $before + ($after - $before) * $factor;
        }
    }
    return 0.0;
}

function getInterpolatedWind(array $forecast, array $timeStamps, int $timestamp, int $lowerPressure, int $upperPressure, float $pressureWeight): array {
    $east = 0.0;
    $north = 0.0;
    foreach ([$lowerPressure, $upperPressure] as $index => $level) {
        $speedKey = "wind_speed_{$level}hPa";
        $directionKey = "wind_direction_{$level}hPa";
        $speed = interpolateSeries($forecast['hourly'][$speedKey] ?? [], $timeStamps, $timestamp) / 3.6;
        $direction = deg2rad(interpolateSeries($forecast['hourly'][$directionKey] ?? [], $timeStamps, $timestamp));
        $levelEast = -$speed * sin($direction);
        $levelNorth = -$speed * cos($direction);
        $levelWeight = $index === 0 ? 1 - $pressureWeight : $pressureWeight;
        $east += $levelEast * $levelWeight;
        $north += $levelNorth * $levelWeight;
    }
    $speed = hypot($east, $north);
    $fromDirection = fmod(rad2deg(atan2(-$east, -$north)) + 360, 360);
    return [$speed, $fromDirection];
}

[$windSpeed, $windDirection] = getInterpolatedWind($forecast, $timeStamps, $startTime, $lowerPressure, $upperPressure, $pressureWeight);
$durationHours = 24;
$stepSeconds = 600;
$points = [[
    'lat' => $lat,
    'lon' => $lon,
    'time' => gmdate('c', $startTime),
    'altitude' => $altitude,
    'wind_speed' => round($windSpeed * 3.6, 1),
    'wind_direction' => round($windDirection, 1)
]];

function movePoint(float $lat, float $lon, float $distance, float $bearing): array {
    $radius = 6371000.0;
    $angularDistance = $distance / $radius;
    $bearingRadians = deg2rad($bearing);
    $latitude = deg2rad($lat);
    $longitude = deg2rad($lon);
    $newLatitude = asin(sin($latitude) * cos($angularDistance) + cos($latitude) * sin($angularDistance) * cos($bearingRadians));
    $newLongitude = $longitude + atan2(sin($bearingRadians) * sin($angularDistance) * cos($latitude), cos($angularDistance) - sin($latitude) * sin($newLatitude));
    return [rad2deg($newLatitude), fmod(rad2deg($newLongitude) + 540, 360) - 180];
}

$currentLat = (float) $lat;
$currentLon = (float) $lon;
$currentTime = $startTime;
$airSpeed = (float) $speed * 0.514444;
$courseRadians = deg2rad((float) $course);

for ($seconds = $stepSeconds; $seconds <= $durationHours * 3600; $seconds += $stepSeconds) {
    $currentTime = $startTime + $seconds;
    [$windSpeed, $windDirection] = getInterpolatedWind($forecast, $timeStamps, $currentTime, $lowerPressure, $upperPressure, $pressureWeight);
    $windBearing = fmod($windDirection + 180, 360);
    $eastVelocity = $airSpeed * sin($courseRadians) + $windSpeed * sin(deg2rad($windBearing));
    $northVelocity = $airSpeed * cos($courseRadians) + $windSpeed * cos(deg2rad($windBearing));
    $distance = hypot($eastVelocity, $northVelocity) * $stepSeconds;
    $bearing = rad2deg(atan2($eastVelocity, $northVelocity));
    [$currentLat, $currentLon] = movePoint($currentLat, $currentLon, $distance, $bearing);
    $points[] = [
        'lat' => $currentLat,
        'lon' => $currentLon,
        'time' => gmdate('c', $currentTime),
        'altitude' => $altitude,
        'wind_speed' => round($windSpeed * 3.6, 1),
        'wind_direction' => round($windDirection, 1)
    ];
}

echo json_encode([
    'model' => 'ICON-EU',
    'pressure' => round($pressure, 1),
    'pressure_levels' => [$lowerPressure, $upperPressure],
    'points' => $points
], JSON_UNESCAPED_SLASHES);