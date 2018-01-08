<?php

class ARNU {
    function __construct(string $dbhost, string $dbname, string $dbuser, string $dbpass, string $tablename) {
        $dbopt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $this->db = new PDO("mysql:host=".$dbhost.";dbname=".$dbname, $dbuser, $dbpass, $dbopt);
        $this->tableName = $tablename;
        return true;
    }
    function getJourney(string $serviceCode) {
        $stmt = $this->db->prepare("SELECT * FROM " . $this->tableName . " WHERE servicecode = :servicecode ORDER BY receive DESC LIMIT 1"); // table and column names cannot be replaced by parameters in PDO
        $stmt->execute([$serviceCode]);
        return $stmt->fetch();
    }
}
