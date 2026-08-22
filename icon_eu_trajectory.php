<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function clampInt(int $value, int $min, int $max): int {
    return max($min, min($max, $value));
}

function clampFloat(float $value, float $min, float $max): float {
    return max($min, min($max, $value));
}

function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $radius = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    return $radius * $c;
}

function normalizeLongitude(float $lon): float {
    $wrapped = fmod($lon + 180.0, 360.0);
    if ($wrapped < 0) {
        $wrapped += 360.0;
    }
    return $wrapped - 180.0;
}

function fetchForecastWithCache(float $lat, float $lon, string $model, array $variables, int $cacheTtlSeconds, array &$stats, int $maxApiCalls): ?array {
    $gridLat = round($lat * 4) / 4;
    $gridLon = round(normalizeLongitude($lon) * 4) / 4;
    $cacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'forecast';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }

    $cacheKey = sha1('v3|' . $model . '|' . $gridLat . '|' . $gridLon . '|' . implode(',', $variables));
    $cachePath = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.json';
    $now = time();

    if (is_file($cachePath) && is_readable($cachePath)) {
        $cachedRaw = @file_get_contents($cachePath);
        if ($cachedRaw !== false) {
            $cached = json_decode($cachedRaw, true);
            $fetchedAt = isset($cached['fetched_at']) ? strtotime((string) $cached['fetched_at']) : false;
            if (is_array($cached) && isset($cached['forecast']['hourly']['time']) && $fetchedAt !== false && ($now - $fetchedAt) <= $cacheTtlSeconds) {
                $stats['cache_hits']++;
                return [
                    'forecast' => $cached['forecast'],
                    'source_fetched_at' => $cached['fetched_at'],
                    'grid_lat' => $gridLat,
                    'grid_lon' => $gridLon
                ];
            }
        }
    }

    if ($stats['api_calls'] >= $maxApiCalls) {
        return null;
    }

    $query = http_build_query([
        'latitude' => $gridLat,
        'longitude' => $gridLon,
        'hourly' => implode(',', $variables),
        'models' => $model,
        'forecast_days' => 3,
        'timezone' => 'UTC'
    ]);
    $url = "https://api.open-meteo.com/v1/forecast?{$query}";
    $context = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
    $response = @file_get_contents($url, false, $context);
    $forecast = $response !== false ? json_decode($response, true) : null;
    if (!is_array($forecast) || !isset($forecast['hourly']['time'])) {
        return null;
    }

    $stats['api_calls']++;
    $sourceFetchedAt = gmdate('c', $now);
    $cachePayload = [
        'fetched_at' => $sourceFetchedAt,
        'forecast' => $forecast
    ];
    @file_put_contents($cachePath, json_encode($cachePayload, JSON_UNESCAPED_SLASHES));

    return [
        'forecast' => $forecast,
        'source_fetched_at' => $sourceFetchedAt,
        'grid_lat' => $gridLat,
        'grid_lon' => $gridLon
    ];
}

function buildSpatialForecastPackage(float $lat, float $lon, string $model, array $variables, array $pressureLevels, float $pressure, int $referenceTime, int $cacheTtlSeconds, int $maxApiCalls, array &$stats, float $gridResolutionDeg): ?array {
    $res = max(0.1, min(1.0, $gridResolutionDeg));
    $latClamped = max(-90.0, min(90.0, $lat));
    $lonWrapped = normalizeLongitude($lon);

    $lat0 = floor($latClamped / $res) * $res;
    $lat1 = min(90.0, $lat0 + $res);
    if ($lat1 <= $lat0) {
        $lat0 = max(-90.0, $lat1 - $res);
    }

    $lon0 = floor($lonWrapped / $res) * $res;
    $lon1 = normalizeLongitude($lon0 + $res);

    $corners = [
        ['key' => 'sw', 'lat' => $lat0, 'lon' => $lon0],
        ['key' => 'se', 'lat' => $lat0, 'lon' => $lon1],
        ['key' => 'nw', 'lat' => $lat1, 'lon' => $lon0],
        ['key' => 'ne', 'lat' => $lat1, 'lon' => $lon1]
    ];

    $cornerPackages = [];
    $sourceFetchedAt = null;
    $referencePackage = null;
    foreach ($corners as $corner) {
        $envelope = fetchForecastWithCache($corner['lat'], $corner['lon'], $model, $variables, $cacheTtlSeconds, $stats, $maxApiCalls);
        if ($envelope === null || !is_array($envelope['forecast'])) {
            return null;
        }
        $package = buildForecastPackage($envelope['forecast'], $pressureLevels, $pressure, $referenceTime, (string) $envelope['source_fetched_at']);
        if ($package === null) {
            return null;
        }
        if ($sourceFetchedAt === null || strtotime((string) $package['sourceFetchedAt']) > strtotime((string) $sourceFetchedAt)) {
            $sourceFetchedAt = $package['sourceFetchedAt'];
        }
        if ($referencePackage === null) {
            $referencePackage = $package;
        }
        $cornerPackages[$corner['key']] = $package;
    }

    return [
        'lat0' => $lat0,
        'lat1' => $lat1,
        'lon0' => $lon0,
        'lon1' => $lon1,
        'corners' => $cornerPackages,
        'reference' => $referencePackage,
        'sourceFetchedAt' => $sourceFetchedAt
    ];
}

function buildForecastPackage(array $forecast, array $pressureLevels, float $pressure, int $referenceTime, string $sourceFetchedAt): ?array {
    $availableLevels = array_values(array_filter($pressureLevels, function ($level) use ($forecast) {
        $values = $forecast['hourly']["wind_speed_{$level}hPa"] ?? [];
        foreach ($values as $value) {
            if ($value !== null) {
                return true;
            }
        }
        return false;
    }));
    if (empty($availableLevels)) {
        return null;
    }

    $lowerPressure = min($availableLevels);
    $upperPressure = max($availableLevels);
    foreach ($availableLevels as $level) {
        if ($level <= $pressure && $level > $lowerPressure) {
            $lowerPressure = $level;
        }
        if ($level >= $pressure && $level < $upperPressure) {
            $upperPressure = $level;
        }
    }
    $pressureWeight = $upperPressure === $lowerPressure ? 0.0 : ($pressure - $lowerPressure) / ($upperPressure - $lowerPressure);
    $pressureWeight = max(0.0, min(1.0, $pressureWeight));

    $times = $forecast['hourly']['time'] ?? [];
    if (!is_array($times) || empty($times)) {
        return null;
    }
    $timeStamps = [];
    foreach ($times as $timeValue) {
        $timestamp = strtotime((string) $timeValue);
        if ($timestamp !== false) {
            $timeStamps[] = $timestamp;
        }
    }
    if (empty($timeStamps)) {
        return null;
    }

    $forecastValidIndex = 0;
    foreach ($timeStamps as $index => $forecastTimestamp) {
        if ($forecastTimestamp <= $referenceTime) {
            $forecastValidIndex = $index;
        }
    }

    return [
        'forecast' => $forecast,
        'times' => $times,
        'timeStamps' => $timeStamps,
        'lowerPressure' => $lowerPressure,
        'upperPressure' => $upperPressure,
        'pressureWeight' => $pressureWeight,
        'forecastValidIndex' => $forecastValidIndex,
        'sourceFetchedAt' => $sourceFetchedAt
    ];
}

$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lon = filter_input(INPUT_GET, 'lon', FILTER_VALIDATE_FLOAT);
$altitude = filter_input(INPUT_GET, 'altitude', FILTER_VALIDATE_FLOAT);
$course = filter_input(INPUT_GET, 'course', FILTER_VALIDATE_FLOAT);
$speed = filter_input(INPUT_GET, 'speed', FILTER_VALIDATE_FLOAT);
$durationHours = filter_input(INPUT_GET, 'duration', FILTER_VALIDATE_INT);
$model = isset($_GET['model']) ? (string) $_GET['model'] : 'icon_eu';
$freeModels = [
    'gfs_seamless',
    'icon_seamless',
    'ecmwf_ifs025',
    'icon_eu',
    'icon_d2'
];
$model = in_array($model, $freeModels, true) ? $model : 'icon_eu';
$course = ($course === null || $course === false) ? 0.0 : (float) $course;
$speed = ($speed === null || $speed === false) ? 0.0 : (float) $speed;
$durationHours = ($durationHours === null || $durationHours === false) ? 24 : max(1, min(48, (int) $durationHours));
$startTime = isset($_GET['time']) ? strtotime((string) $_GET['time']) : time();
$refreshIntervalMinutes = clampInt((int) ($_GET['refresh_minutes'] ?? 120), 15, 240);
$refreshDistanceKm = clampFloat((float) ($_GET['refresh_distance_km'] ?? 75), 10.0, 250.0);
$maxApiCalls = clampInt((int) ($_GET['max_api_calls'] ?? 8), 1, 30);
$cacheTtlSeconds = clampInt((int) ($_GET['cache_ttl_seconds'] ?? 1200), 300, 3600);
$gridResolutionDeg = clampFloat((float) ($_GET['grid_resolution_deg'] ?? 0.25), 0.1, 1.0);

if ($lat === false || $lon === false || $altitude === false || $startTime === false ||
    $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180 || $altitude < 0 || $speed < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige GPS-, Höhen-, Kurs- oder Geschwindigkeitsdaten']);
    exit;
}

$pressureLevels = [1000, 975, 950, 925, 900, 850, 800, 700, 600, 500];
$pressure = 1013.25 * pow(1 - min($altitude, 11000) / 44330, 5.255);

$variables = [];
foreach ($pressureLevels as $level) {
    $variables[] = "wind_speed_{$level}hPa";
    $variables[] = "wind_direction_{$level}hPa";
}

$stats = [
    'api_calls' => 0,
    'cache_hits' => 0,
    'forecast_refreshes' => 0
];
$spatialPackage = buildSpatialForecastPackage((float) $lat, (float) $lon, $model, $variables, $pressureLevels, (float) $pressure, $startTime, $cacheTtlSeconds, $maxApiCalls, $stats, $gridResolutionDeg);
if ($spatialPackage === null) {
    http_response_code(502);
    echo json_encode(['error' => 'Wetterdaten konnten nicht geladen werden']);
    exit;
}

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

// Converts wind speed/direction to east/north vector components per hourly sample,
// so direction (a circular quantity) is never linearly interpolated directly.
function windComponentSeries(array $speeds, array $directions): array {
    $count = min(count($speeds), count($directions));
    $east = [];
    $north = [];
    for ($index = 0; $index < $count; $index++) {
        $speedMs = ((float) ($speeds[$index] ?? 0)) / 3.6;
        $directionRad = deg2rad((float) ($directions[$index] ?? 0));
        $east[$index] = -$speedMs * sin($directionRad);
        $north[$index] = -$speedMs * cos($directionRad);
    }
    return [$east, $north];
}

function getInterpolatedWindComponents(array $forecast, array $timeStamps, int $timestamp, int $lowerPressure, int $upperPressure, float $pressureWeight): array {
    $east = 0.0;
    $north = 0.0;
    foreach ([$lowerPressure, $upperPressure] as $index => $level) {
        $speeds = $forecast['hourly']["wind_speed_{$level}hPa"] ?? [];
        $directions = $forecast['hourly']["wind_direction_{$level}hPa"] ?? [];
        [$eastSeries, $northSeries] = windComponentSeries($speeds, $directions);
        $levelEast = interpolateSeries($eastSeries, $timeStamps, $timestamp);
        $levelNorth = interpolateSeries($northSeries, $timeStamps, $timestamp);
        $levelWeight = $index === 0 ? 1 - $pressureWeight : $pressureWeight;
        $east += $levelEast * $levelWeight;
        $north += $levelNorth * $levelWeight;
    }
    return [$east, $north];
}

function getSpatialInterpolatedWind(array $spatialPackage, int $timestamp, float $lat, float $lon): array {
    $lat0 = $spatialPackage['lat0'];
    $lat1 = $spatialPackage['lat1'];
    $lon0 = $spatialPackage['lon0'];
    $lon1 = $spatialPackage['lon1'];

    $u = ($lat1 - $lat0) > 0 ? (($lat - $lat0) / ($lat1 - $lat0)) : 0.0;
    $u = max(0.0, min(1.0, $u));

    $lonNorm = normalizeLongitude($lon);
    $lon1Adj = $lon1;
    if ($lon1Adj < $lon0) {
        $lon1Adj += 360.0;
    }
    $lonAdj = $lonNorm;
    if ($lonAdj < $lon0) {
        $lonAdj += 360.0;
    }
    $t = ($lon1Adj - $lon0) > 0 ? (($lonAdj - $lon0) / ($lon1Adj - $lon0)) : 0.0;
    $t = max(0.0, min(1.0, $t));

    $weights = [
        'sw' => (1 - $u) * (1 - $t),
        'se' => (1 - $u) * $t,
        'nw' => $u * (1 - $t),
        'ne' => $u * $t
    ];

    $east = 0.0;
    $north = 0.0;
    foreach ($weights as $key => $weight) {
        $corner = $spatialPackage['corners'][$key] ?? null;
        if ($corner === null) {
            continue;
        }
        [$cornerEast, $cornerNorth] = getInterpolatedWindComponents(
            $corner['forecast'],
            $corner['timeStamps'],
            $timestamp,
            $corner['lowerPressure'],
            $corner['upperPressure'],
            $corner['pressureWeight']
        );
        $east += $cornerEast * $weight;
        $north += $cornerNorth * $weight;
    }

    $speed = hypot($east, $north);
    $fromDirection = fmod(rad2deg(atan2(-$east, -$north)) + 360, 360);
    return [$speed, $fromDirection];
}

[$windSpeed, $windDirection] = getSpatialInterpolatedWind($spatialPackage, $startTime, (float) $lat, (float) $lon);
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
$lastForecastLat = $currentLat;
$lastForecastLon = $currentLon;
$nextRefreshTime = $startTime + ($refreshIntervalMinutes * 60);
$refreshDistanceMeters = $refreshDistanceKm * 1000.0;

for ($seconds = $stepSeconds; $seconds <= $durationHours * 3600; $seconds += $stepSeconds) {
    $currentTime = $startTime + $seconds;

    $timeRefreshDue = $currentTime >= $nextRefreshTime;
    $distanceRefreshDue = haversineMeters($lastForecastLat, $lastForecastLon, $currentLat, $currentLon) >= $refreshDistanceMeters;
    if ($timeRefreshDue || $distanceRefreshDue) {
        $updatedSpatialPackage = buildSpatialForecastPackage($currentLat, $currentLon, $model, $variables, $pressureLevels, (float) $pressure, $currentTime, $cacheTtlSeconds, $maxApiCalls, $stats, $gridResolutionDeg);
        if ($updatedSpatialPackage !== null) {
            $spatialPackage = $updatedSpatialPackage;
            $lastForecastLat = $currentLat;
            $lastForecastLon = $currentLon;
            $nextRefreshTime = $currentTime + ($refreshIntervalMinutes * 60);
            $stats['forecast_refreshes']++;
        }
    }

    [$windSpeed, $windDirection] = getSpatialInterpolatedWind($spatialPackage, $currentTime, $currentLat, $currentLon);
    $windBearing = fmod($windDirection + 180, 360);
    // Free-floating balloon/sonde: the path is driven purely by wind, not by its current ground course/speed
    $eastVelocity = $windSpeed * sin(deg2rad($windBearing));
    $northVelocity = $windSpeed * cos(deg2rad($windBearing));
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
    'model' => $model,
    'source_fetched_at' => $spatialPackage['sourceFetchedAt'],
    'forecast_valid_from' => $spatialPackage['reference']['times'][$spatialPackage['reference']['forecastValidIndex']] ?? null,
    'pressure' => round($pressure, 1),
    'pressure_levels' => [$spatialPackage['reference']['lowerPressure'], $spatialPackage['reference']['upperPressure']],
    'request_budget' => [
        'max_api_calls' => $maxApiCalls,
        'api_calls_used' => $stats['api_calls'],
        'cache_hits' => $stats['cache_hits'],
        'forecast_refreshes' => $stats['forecast_refreshes'],
        'refresh_interval_minutes' => $refreshIntervalMinutes,
        'refresh_distance_km' => $refreshDistanceKm,
        'cache_ttl_seconds' => $cacheTtlSeconds,
        'grid_resolution_deg' => $gridResolutionDeg,
        'spatial_interpolation' => 'bilinear_4_corners'
    ],
    'points' => $points
], JSON_UNESCAPED_SLASHES);