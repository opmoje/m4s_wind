<?php

use App\Classes\RollingCurlX;
use Predis\Client;

include '../vendor/autoload.php';

$client = new Client([
    'scheme' => 'tcp',
    'host'   => 'redis',
    'port'   => 6379,
]);

$redisKey = 'forecast';

if (($cached = $client->get($redisKey))) {
    header('Content-Type: application/json');
    echo $cached;
    return;
}

$sourcesRaw = file_get_contents(__DIR__ . '/../src/data/wind-sources.json');
$sourcesJson = json_decode($sourcesRaw, true);
unset($sourcesRaw);

$curlX = new RollingCurlX();
$curlX->setMaxConcurrent(3);
$results = [];

while (!empty($sourcesJson)) {
    $source = array_shift($sourcesJson);
    $curlX->addRequest($source[4], [], function ($response, $url, $requestInfo, $userData) use (&$results) {
        $regex = '/направление ветра<\/td>\s*<td[^>]*>([^<]+)<\/td>/uis';
        $windDirection = null;

        if (preg_match($regex, $response, $match)) {
            $windDirection = $match[1];
        }

        $regex = '/средняя скорость ветра, м\/с<\/td>\s*<td[^>]*>([^<]+)<\/td>/uis';
        $windSpeed = null;

        if (preg_match($regex, $response, $match)) {
            $windSpeed = $match[1];
        }

        $regex = '/температура воздуха[^<]+<\/td>\s*<td[^>]*>([^<]+)<\/td>/uis';
        $temperature = null;

        if (preg_match($regex, $response, $match)) {
            $temperature = $match[1];
        }

        if (empty($windDirection) || $windSpeed === null) {
            return;
        }

        $results[] = [
            'wind_direction' => (string)$windDirection,
            'wind_speed' => (int)$windSpeed,
            'temperature' => (float)$temperature,
            'source' => $userData[1],
            'lat' => $userData[2],
            'lon' => $userData[3]
        ];

    }, $source);
}

$curlX->execute();
$results = json_encode($results);
$client->set($redisKey, $results);
$client->expireat($redisKey, time() + 30 * 60);
header('Content-Type: application/json');
echo $results;
