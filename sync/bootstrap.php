<?php
require_once("vendor/autoload.php");

$environment = array_merge($_ENV, $_SERVER);
ksort($environment);

$locations = ['LOCAL', 'REMOTE'];
/** @var \Predis\Client[] $redis */
$redis = [];
foreach($locations as $location) {
    $url = [
        'scheme' => isset($environment[$location . '_REDIS_PROTOCOL']) ? $environment[$location . '_REDIS_PROTOCOL'] : 'tcp',
        'host'   => isset($environment[$location . '_REDIS_HOST'])     ? $environment[$location . '_REDIS_HOST'] : null,
        'port'   => isset($environment[$location . '_REDIS_PORT'])     ? $environment[$location . '_REDIS_PORT'] : null,
        'user'   => isset($environment[$location . '_REDIS_USER'])     ? $environment[$location . '_REDIS_USER'] : null,
        'pass'   => isset($environment[$location . '_REDIS_PASS'])     ? $environment[$location . '_REDIS_PASS'] : null,
    ];
    $options = [];
    if(isset($environment[$location . '_ADDED_PREFIX'])){
        $options['prefix'] = $environment[$location . '_ADDED_PREFIX'];
    }

    $url = array_filter($url);
    $redis[$location] = new Predis\Client(http_build_url($url), $options);
}


