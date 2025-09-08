<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/Database.php';

class Customer {
    private $conn;
    private $table = "customer";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table}");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

public function create($data) {
    // Kiểm tra dữ liệu bắt buộc
    $required = ['full_name','gender','phone_number','email','id_card','address'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return [
                'success' => false,
                'message' => "Thiếu dữ liệu bắt buộc: $field"
            ];
        }
    }

    $sql = "INSERT INTO {$this->table} 
            (full_name, gender, phone_number, email, id_card, address) 
            VALUES (:full_name, :gender, :phone_number, :email, :id_card, :address)";
    $stmt = $this->conn->prepare($sql);

    $params = [
        ':full_name'   => $data['full_name'],
        ':gender'      => $data['gender'],
        ':phone_number'=> $data['phone_number'],
        ':email'       => $data['email'],
        ':id_card'     => $data['id_card'],
        ':address'     => $data['address']
    ];

    if ($stmt->execute($params)) {
        return [
            'success' => true,
            'message' => 'Tạo khách hàng thành công',
            'customer_id' => $this->conn->lastInsertId()
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Lỗi khi tạo khách hàng'
        ];
    }
}

    public function update($id, $data) {
    if (empty($id)) {
        return [
            'success' => false,
            'message' => 'Thiếu customer_id để cập nhật'
        ];
    }

    // Kiểm tra các field bắt buộc
    $required = ['full_name','gender','phone_number','email','id_card','address'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            return [
                'success' => false,
                'message' => "Thiếu dữ liệu bắt buộc: $field"
            ];
        }
    }

    $sql = "UPDATE {$this->table} SET 
            full_name=:full_name, gender=:gender, phone_number=:phone_number,
            email=:email, id_card=:id_card, address=:address
            WHERE customer_id=:id";
    $stmt = $this->conn->prepare($sql);

    $params = [
        ':full_name'   => $data['full_name'],
        ':gender'      => $data['gender'],
        ':phone_number'=> $data['phone_number'],
        ':email'       => $data['email'],
        ':id_card'     => $data['id_card'],
        ':address'     => $data['address'],
        ':id'          => $id
    ];

    if ($stmt->execute($params)) {
        return [
            'success' => true,
            'message' => 'Cập nhật khách hàng thành công'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Lỗi khi cập nhật khách hàng'
        ];
    }
}

public function delete($id) {
    if (empty($id)) {
        return [
            'success' => false,
            'message' => 'Thiếu customer_id để xóa'
        ];
    }

    $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE customer_id=:id");
    if ($stmt->execute([':id' => $id])) {
        return [
            'success' => true,
            'message' => 'Xóa khách hàng thành công'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Lỗi khi xóa khách hàng'
        ];
    }
}
}
