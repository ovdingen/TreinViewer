<?php
include '../vendor/autoload.php';
include '../config.php';
$context = new ZMQContext();
$subscriber = new ZMQSocket($context, ZMQ::SOCKET_SUB);

$subscriber->connect("tcp://pubsub.besteffort.ndovloket.nl:7664");
$subscriber->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "/RIG/NStreinpositiesInterface5");

function maakGeoJSONFeatureVanTreinMaterieelDeel(SimpleXMLElement $materieeldeel) {
    $properties = array();
    $properties['materieelnummer'] = $materieeldeel->MaterieelDeelNummer[0];
    $properties['materieelvolgnummer'] = $materieeldeel->MaterieelVolgNummer;
    $properties['snelheid'] = $materieeldeel->Snelheid[0];
    $properties['richting'] = $materieeldeel->Richting[0];

    $point = new \GeoJson\Geometry\Point([(float) $materieeldeel->Longitude, (float) $materieeldeel->Latitude]);

    return new \GeoJson\Feature\Feature($point, $properties);
}

while (true) {
    //  Read envelope with address
    $address = $subscriber->recv();
    //  Read message contents
    $contents = gzdecode($subscriber->recv());
    $data = simplexml_load_string($contents,null, 0, 'tns', true);
    $features = array();
    foreach($data as $key => $value) {
        if($key == "TreinLocation") {
            $trainNumber = $value->TreinNummer;
            if(is_array($value->TreinMaterieelDelen)) {
                foreach($value->TreinMaterieelDelen as $materieeldeel) {
                    array_push($features, maakGeoJSONFeatureVanTreinMaterieelDeel($materieeldeel));
                }
            } else {
                array_push($features, maakGeoJSONFeatureVanTreinMaterieelDeel($value->TreinMaterieelDelen));
            }
        }
    }
    $featureCollection = new GeoJson\Feature\FeatureCollection($features);
    file_put_contents($config['geojson_location'], json_encode($featureCollection));
}
