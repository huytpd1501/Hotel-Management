<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/Database.php';

class Service {
    private $conn;
    private $table = "service";

    public function __construct($db){ $this->conn=$db; }

    public function getAll(){
        $stmt=$this->conn->prepare("SELECT * FROM {$this->table}");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data){
        $sql="INSERT INTO {$this->table}(service_name, unit_price, description) 
              VALUES(:service_name,:unit_price,:description)";
        $stmt=$this->conn->prepare($sql);
        return $stmt->execute($data);
    }

    public function update($id,$data){
        $data['id']=$id;
        $sql="UPDATE {$this->table} SET service_name=:service_name, unit_price=:unit_price, description=:description WHERE service_id=:id";
        $stmt=$this->conn->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete($id){
        $stmt=$this->conn->prepare("DELETE FROM {$this->table} WHERE service_id=?");
        return $stmt->execute([$id]);
    }
}
