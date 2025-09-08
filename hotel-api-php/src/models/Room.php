<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/Database.php';

class Room {
    private $conn;
    private $table = "room";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Lấy tất cả phòng, có thể lọc theo status
    public function read($status = null) {
        $query = "SELECT * FROM {$this->table}";
        if ($status) {
            $query .= " WHERE status = :status";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':status' => $status]);
        } else {
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
        }
        return $stmt;
    }

    public function create($data) {
        $query = "INSERT INTO {$this->table} 
                  (room_number, room_type, status, price_per_night, description)
                  VALUES (:room_number, :room_type, :status, :price_per_night, :description)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':room_number'     => $data['room_number'] ?? null,
            ':room_type'       => $data['room_type'] ?? null,
            ':status'          => $data['status'] ?? 'AVAILABLE',
            ':price_per_night' => $data['price_per_night'] ?? null,
            ':description'     => $data['description'] ?? null
        ]);
    }

    public function update($id, $data) {
        $query = "UPDATE {$this->table} SET 
                  room_number=:room_number, room_type=:room_type,
                  status=:status, price_per_night=:price_per_night, description=:description
                  WHERE room_id=:id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':room_number'     => $data['room_number'] ?? null,
            ':room_type'       => $data['room_type'] ?? null,
            ':status'          => $data['status'] ?? null,
            ':price_per_night' => $data['price_per_night'] ?? null,
            ':description'     => $data['description'] ?? null,
            ':id'              => $id
        ]);
    }

    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE room_id=:id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':id'=>$id]);
    }

    // Đổi trạng thái phòng
    public function setStatus($id, $status) {
        $query = "UPDATE {$this->table} SET status=:status WHERE room_id=:id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':status'=>$status, ':id'=>$id]);
    }
    public function updateStatus($room_id, $status) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET status=? WHERE room_id=?");
        return $stmt->execute([$status, $room_id]);
    }
}
?>
