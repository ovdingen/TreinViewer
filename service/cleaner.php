<?php
// Clears all DVS messages. Execute every day at 5 AM.
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$db = new PDO("mysql:host=localhost;dbname=dvs", "dvs", "dvs", $opt);

$stmt = $db->prepare("DELETE FROM dvs");

$stmt->execute();

