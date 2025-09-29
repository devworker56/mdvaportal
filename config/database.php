<?php
class Database {
    private $host = "localhost";
    private $db_name = "u834808878_db_mdva";
    private $username = "u834808878_admin_mdva";
    private $password = "Ossouka@1968";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>