<?php
class Database {
  private $host = "localhost";
  private $db_name = "sql_nhom7_itimit";
  private $username = "sql_nhom7_itimit";
  private $password = "c54e874fb2a84";
  public $conn;

  public function getConnection() {
    $this->conn = null;
    try {
      $this->conn = new PDO(
        "mysql:host=".$this->host.";dbname=".$this->db_name.";charset=utf8mb4",
        $this->username,
        $this->password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
      );
    } catch (PDOException $e) {
      http_response_code(500);
      echo json_encode(["ok"=>false,"error"=>"DB connect: ".$e->getMessage()]);
      exit;
    }
    return $this->conn;
  }
}
