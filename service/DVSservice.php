<?php

include '../vendor/autoload.php';

if(!file_exists("../vendor/autoload.php")) {
    die("please run composer install, vendor/autoload.php does not exist");
}

include '../config.php';

include '../classes/DVS.php';

$dvs = new DVS($config['dvs_db_host'], $config['dvs_db_name'], $config['dvs_db_user'], $config['dvs_db_pass'], $config['dvs_db_table']);
$context = new ZMQContext();
$subscriber = new ZMQSocket($context, ZMQ::SOCKET_SUB);
$subscriber->connect("tcp://pubsub.besteffort.ndovloket.nl:7664");

$subscriber->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "/RIG/NStreinpositiesInterface5");
$subscriber->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "/RIG/InfoPlusDVSInterface4");

$dbopt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$db = new PDO("mysql:host=".$config['dvs_db_host'].";dbname=".$config['dvs_db_name'], $config['dvs_db_user'], $config['dvs_db_pass'], $dbopt);

function verwerkNSDVSBericht(string $contents, PDO $db) {
    $xml = simplexml_load_string($contents, null, 0, 'ns2', true);
    foreach($xml->ReisInformatieProductDVS->DynamischeVertrekStaat as $data) {
        $stmt = $db->prepare("INSERT INTO dvs VALUES(:receive, :transportmodecode, :treinnummer, :treinformule, :materieel, :vervoerder, :treineindbestemming, :toeslag, :reserveren, :nietinstappen, :speciaalkaartje, :stopstations)");
        $db_fields = ["receive" => time(), "transportmodecode" => (string) $data->Trein->TreinSoort , "treinnummer" => (string) $data->RitId, "treinformule" => (int) $data->Trein->TreinFormule, "materieel" => json_encode($data->Trein->TreinVleugel->MaterieelDeelDVS), "vervoerder" => (string) $data->Trein->Vervoerder, "treineindbestemming" => json_encode($data->Trein->TreinVleugel->TreinVleugelEindBestemming), "toeslag" => (string) $data->Trein->Toeslag, "reserveren" => (string) $data->Trein->Reserveren, "nietinstappen" => (string) $data->Trein->NietInstappen, "speciaalkaartje" => (string) $data->Trein->SpeciaalKaartje, "stopstations" => json_encode($data->Trein->TreinVleugel->StopStations)];
        echo("PLACING DVS MESSAGE FOR TRAIN " . (string) $data->RitId . " INTO DB\n");
        $stmt->execute($db_fields);
    }
    return true;
}

function verwerkNSTreinPositieBericht(string $contents, DVS $dvs, array $config) {
    $data = simplexml_load_string($contents, null, 0, 'tns', true);
    $features = array();
    foreach($data as $key => $value) {
        if ($key == "TreinLocation") {
            $trainNumber = (string)$value->TreinNummer;
            if (is_array($value->TreinMaterieelDelen)) {
                foreach($value->TreinMaterieelDelen as $materieeldeel) {
                    array_push($features, maakGeoJSONFeatureVanTreinMaterieelDeel($materieeldeel, $trainNumber, $dvs));
                }
            } else {
                array_push($features, maakGeoJSONFeatureVanTreinMaterieelDeel($value->TreinMaterieelDelen, $trainNumber, $dvs));
            }
        }
    }
    $featureCollection = new \GeoJson\Feature\FeatureCollection($features);
    echo("WRITING " . count($features) . " GEOJSON FEATURES\n");
    file_put_contents($config['geojson_location'], json_encode($featureCollection));
    return true;
}

function maakGeoJSONFeatureVanTreinMaterieelDeel(SimpleXMLElement $materieeldeel, $treinnummer, DVS $dvs) {
    $dvsData = $dvs->getJourney($treinnummer);
    $properties = array();
    $properties['materieelnummer'] = $materieeldeel->MaterieelDeelNummer[0];
    $properties['materieelvolgnummer'] = $materieeldeel->MaterieelVolgNummer;
    $properties['snelheid'] = $materieeldeel->Snelheid[0];
    $properties['richting'] = $materieeldeel->Richting[0];
    if ($properties)
        {
        $properties['transportmodecode'] = $dvsData['transportmodecode'];
        }
      else
        {
        $properties['transportmodecode'] = "UNKNOWN";
        }

    $properties['id'] = (int)$materieeldeel->MaterieelDeelNummer; // This will allow leaflet-realtime to keep track of the entries in the GeoJSON
    $properties['popupContent'] = "<b>" . $properties['transportmodecode'] . "Trein " . $treinnummer . "<br />Materieelnummer " . $properties['materieelnummer'] . "<br />" . "Snelheid: " . $properties['snelheid'] . "km/h<br />" . "Richting: " . $properties['richting'] . " graden<br />";
    $point = new \GeoJson\Geometry\Point([(float)$materieeldeel->Longitude, (float)$materieeldeel->Latitude]);
    return new \GeoJson\Feature\Feature($point, $properties);
}

while (true) {

    //  Read envelope with address

    $address = $subscriber->recv();

    //  Read message contents

    $contents = gzdecode($subscriber->recv());
    switch($address) {
        case "/RIG/NStreinpositiesInterface5":
            verwerkNSTreinPositieBericht($contents, $dvs, $config);
            break;
        case "/RIG/InfoPlusDVSInterface4":
            verwerkNSDVSBericht($contents, $db);
            break;
        default:
            break;
    }
}
