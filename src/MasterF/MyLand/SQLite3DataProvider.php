<?php

namespace MasterF\MyLand;

class SQLite3DataProvider {

    private $db;

    public function __construct($path) {
        $this->db = new \SQLite3($path."DataDB.sqlite3");
        $this->db->exec(
            "CREATE table if not exists land (
        id     integer primary key autoincrement,
        owner  text not null,
        startx integer not null,
        startz integer not null,
        endx   integer not null,
        endz   integer not null,
        world  text not null
      )"
        );

        $this->db->exec(
            "CREATE table if not exists invite (
        id   integer not null,
        name text not null
      )"
        );
    }

    /**
     * @param $owner
     * @param $sx
     * @param $sz
     * @param $ex
     * @param $ez
     * @param $world
     */
    public function createLand($owner, $sx, $sz, $ex, $ez, $world) {
        $sql = $this->db->prepare(
            "INSERT INTO land (owner, startx, startz, endx, endz, world) 
      VALUES (:owner, :sx, :sz, :ex, :ez, :world)"
        );


        $sql->bindValue(":owner", $owner, SQLITE3_TEXT);
        $sql->bindValue(":sx",    $sx, SQLITE3_INTEGER);
        $sql->bindValue(":sz",    $sz, SQLITE3_INTEGER);
        $sql->bindValue(":ex",    $ex, SQLITE3_INTEGER);
        $sql->bindValue(":ez",    $ez, SQLITE3_INTEGER);
        $sql->bindValue(":world", $world, SQLITE3_TEXT);

        $sql->execute();
    }

    public function getLand($x, $z, $world) {
        $sql = $this->db->prepare(
            "SELECT * from land
      WHERE (startx <= :x and endx >= :x) and (startz <= :z and endz >= :z) and world = :world"
        );

        $sql->bindValue(":x", $x, SQLITE3_INTEGER);
        $sql->bindValue(":z", $z, SQLITE3_INTEGER);
        $sql->bindValue(":world", $world, SQLITE3_TEXT);

        $result = $sql->execute();

        $land = $result->fetchArray(SQLITE3_ASSOC);
        return ($land !== false) ? $land : null;
    }


    public function getLandById($id) {
        $sql = $this->db->prepare(
            "SELECT * from land WHERE id = :id"
        );

        $sql->bindValue(":id", $id, SQLITE3_INTEGER);

        $result = $sql->execute();

        $land = $result->fetchArray(SQLITE3_ASSOC);
        return ($land !== false) ? $land : null;
    }

    public function getAllLands() {
        $sql = $this->db->prepare(
            "SELECT * from land"
        );

        $result = $sql->execute();

        $lands = [];

        while($land = $result->fetchArray(SQLITE3_ASSOC)) {
            $lands[] = $land;
        }

        return $lands;
    }

    public function existsLand($x, $z, $world) {
        $sql = $this->db->prepare(
            "SELECT count(*) from land
      WHERE (startx <= :x and endx >= :x) and (startz <= :z and endz >= :z) and world = :world"
        );

        $sql->bindValue(":x", $x, SQLITE3_INTEGER);
        $sql->bindValue(":z", $z, SQLITE3_INTEGER);
        $sql->bindValue(":world", $world, SQLITE3_TEXT);

        $result = $sql->execute();

        $landCount = $result->fetchArray();

        return ($landCount[0] > 0);
    }

    public function addGuest($id, $name) {
        $land = $this->getLandById($id);

        if($land === null) return false; //もし存在しない土地ならば

        $sql = $this->db->prepare(
            "INSERT INTO invite (id, name) VALUES (:id, :name)"
        );

        $sql->bindValue(":id", $id, SQLITE3_INTEGER);
        $sql->bindValue(":name", $name, SQLITE3_TEXT);

        $sql->execute();

    }

    public function existsGuest($id, $name) {

        $sql = $this->db->prepare(
            "SELECT count(*) from invite WHERE id = :id and name = :name"
        );

        $sql->bindValue(":id", $id, SQLITE3_INTEGER);
        $sql->bindValue(":name", $name, SQLITE3_TEXT);

        $result = $sql->execute();

        return $result->fetchArray()[0] > 0;
    }

}
