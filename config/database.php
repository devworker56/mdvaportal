<?php
class Database {
    // Hostinger database configuration
    private $host = "localhost";
    private $db_name = "u834808878_db_mdva"; // Your actual database name
    private $username = "u834808878_admin_mdva"; // Your actual username
    private $password = "Ossouka@1968"; // Your actual password
    public $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                $this->username, 
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_PERSISTENT => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                )
            );
            
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            $this->conn = null;
        }
        
        return $this->conn;
    }
}
?>