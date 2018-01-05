<?php
include '../vendor/autoload.php';
include '../config.php';
$context = new ZMQContext();
$subscriber = new ZMQSocket($context, ZMQ::SOCKET_SUB);

$subscriber->connect("tcp://pubsub.besteffort.ndovloket.nl:7664");
$subscriber->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "/RIG/NStreinpositiesInterface5");

function maakGeoJSONFeatureVanTreinMaterieelDeel(SimpleXMLElement $materieeldeel, $treinnummer) {
    $properties = array();
    $properties['materieelnummer'] = $materieeldeel->MaterieelDeelNummer[0];
    $properties['materieelvolgnummer'] = $materieeldeel->MaterieelVolgNummer;
    $properties['snelheid'] = $materieeldeel->Snelheid[0];
    $properties['richting'] = $materieeldeel->Richting[0];
    $properties['id'] = (int) $materieeldeel->MaterieelDeelNummer; // This will allow leaflet-realtime to keep track of the entries in the GeoJSON
    $properties['popupContent'] = "<b>Trein " . $treinnummer . "<br>Materieelnummer " . $properties['materieelnummer'] . "<br>" . "Snelheid: " . $properties['snelheid'] . "km/h<br>" . "Richting: " . $properties['richting'] . " graden";

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
            $trainNumber = (string) $value->TreinNummer;
            if(is_array($value->TreinMaterieelDelen)) {
                foreach($value->TreinMaterieelDelen as $materieeldeel) {
                    array_push($features, maakGeoJSONFeatureVanTreinMaterieelDeel($materieeldeel, $trainNumber));
                }
            } else {
                array_push($features, maakGeoJSONFeatureVanTreinMaterieelDeel($value->TreinMaterieelDelen, $trainNumber));
            }
        }
    }
    $featureCollection = new GeoJson\Feature\FeatureCollection($features);
    file_put_contents($config['geojson_location'], json_encode($featureCollection));
}
