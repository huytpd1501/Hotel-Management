<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/Database.php';

class ServiceUsage {
  private $conn;
  private $table = "service_usage";

  public function __construct($db){ $this->conn = $db; }

  public function getAll(){
    $sql = "SELECT su.usage_id, su.booking_id, su.service_id, su.quantity, su.usage_date,
                   s.service_name, s.unit_price,
                   b.room_id, r.room_number
            FROM service_usage su
            JOIN service s ON su.service_id = s.service_id
            JOIN booking b ON su.booking_id = b.booking_id
            JOIN room r ON b.room_id = r.room_id
            ORDER BY su.usage_date DESC, su.usage_id DESC";
    return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  public function getByBooking($booking_id){
    $stmt = $this->conn->prepare("
      SELECT su.usage_id, su.booking_id, su.service_id, su.quantity, su.usage_date,
             s.service_name, s.unit_price
      FROM service_usage su
      JOIN service s ON su.service_id = s.service_id
      WHERE su.booking_id = ?
      ORDER BY su.usage_date DESC, su.usage_id DESC
    ");
    $stmt->execute([$booking_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function create($d){
    $booking_id = (int)($d['booking_id'] ?? 0);
    $service_id = (int)($d['service_id'] ?? 0);
    $quantity   = (int)($d['quantity'] ?? 0);
    $usage_date = trim($d['usage_date'] ?? '');

    if($booking_id<=0 || $service_id<=0 || $quantity<=0){
      return false;
    }
    if($usage_date === '') $usage_date = date('Y-m-d');

    $stmt = $this->conn->prepare("
      INSERT INTO service_usage (booking_id, service_id, quantity, usage_date)
      VALUES (?, ?, ?, ?)
    ");
    return $stmt->execute([$booking_id, $service_id, $quantity, $usage_date]);
  }

  public function update($usage_id, $d){
    if($usage_id<=0) return false;
    // cho phép sửa service_id/quantity/usage_date
    $service_id = isset($d['service_id']) ? (int)$d['service_id'] : null;
    $quantity   = isset($d['quantity']) ? (int)$d['quantity'] : null;
    $usage_date = array_key_exists('usage_date', $d) ? trim($d['usage_date']) : null;

    $fields = [];
    $params = [];
    if($service_id !== null){ $fields[] = "service_id=?"; $params[] = $service_id; }
    if($quantity !== null){ $fields[] = "quantity=?"; $params[] = $quantity; }
    if($usage_date !== null && $usage_date!==''){ $fields[] = "usage_date=?"; $params[] = $usage_date; }

    if(empty($fields)) return true;

    $params[] = $usage_id;
    $sql = "UPDATE service_usage SET ".implode(",", $fields)." WHERE usage_id=?";
    return $this->conn->prepare($sql)->execute($params);
  }

  public function delete($usage_id){
    if($usage_id<=0) return false;
    $stmt = $this->conn->prepare("DELETE FROM service_usage WHERE usage_id=?");
    return $stmt->execute([$usage_id]);
  }

  public function getBookingIdByUsage($usage_id){
    $stmt = $this->conn->prepare("SELECT booking_id FROM service_usage WHERE usage_id=?");
    $stmt->execute([$usage_id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return $r ? (int)$r['booking_id'] : 0;
  }
}
