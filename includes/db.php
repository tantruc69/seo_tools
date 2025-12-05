<?php
require_once 'config.php';

class Database {
    private $conn;
    
    public function __construct() {
        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            
            $this->conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            die("Database connection error: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function escape($value) {
        return $this->conn->real_escape_string($value);
    }
    
    public function query($sql) {
        return $this->conn->query($sql);
    }
    
    public function insert($table, $data) {
        $keys = array_keys($data);
        $values = array_values($data);
        
        $escaped_values = array_map([$this, 'escape'], $values);
        
        $sql = "INSERT INTO $table (" . implode(', ', $keys) . ") 
                VALUES ('" . implode("', '", $escaped_values) . "')";
        
        if ($this->conn->query($sql)) {
            return $this->conn->insert_id;
        }
        
        return false;
    }
    
    public function close() {
        $this->conn->close();
    }
}

// Khởi tạo database connection
$db = new Database();
?>