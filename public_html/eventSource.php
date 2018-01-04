<?php
// This script sends UPDATE if a file has changed in the last 10 seconds.
// Currently unused.

die();
/*
set_time_limit(0);

require '../vendor/autoload.php';
require '../config.php';

use Igorw\EventSource\Stream;

foreach (Stream::getHeaders() as $name => $value) {
    header("$name: $value");
}

$stream = new Stream();

global $stream;

while (true) {
    $minimalTime = time() - 10;
    if($minimalTime < filectime($config['geojson_location'])) {
        $stream
            ->event()
                 ->setData("UPDATE")
            ->end()
            ->flush();
   }
    sleep(10);
}


*/
