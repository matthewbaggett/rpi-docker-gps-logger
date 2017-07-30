<?php
require_once("vendor/autoload.php");

$environment = array_merge($_ENV, $_SERVER);
ksort($environment);

$redisClient = new Predis\Client([
    'scheme' => 'tcp',
    'host'   => $environment['REDIS_HOST'],
    'port'   => $environment['REDIS_PORT'],
]);

$gpsClient = new \Nykopol\GpsdClient\Client(
    $environment['GPSD_HOST'],
    $environment['GPSD_PORT']
);


