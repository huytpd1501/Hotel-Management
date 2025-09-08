<?php
class Booking {
  private $conn;
  private $tblBooking      = 'booking';
  private $tblCustomer     = 'customer';
  private $tblRoom         = 'room';
  private $tblServiceUsage = 'service_usage';
  private $tblService      = 'service'; // số ít

  public function __construct($db) { $this->conn = $db; }

  /** Danh sách booking kèm tên KH & số phòng */
  public function getAll() {
    $sql = "
      SELECT b.booking_id, b.customer_id, b.room_id, b.checkin_date, b.checkout_date, b.status,
             c.full_name AS customer_name,
             r.room_number
      FROM `{$this->tblBooking}` b
      LEFT JOIN `{$this->tblCustomer}` c ON c.customer_id = b.customer_id
      LEFT JOIN `{$this->tblRoom}` r     ON r.room_id     = b.room_id
      ORDER BY b.booking_id DESC
    ";
    $st = $this->conn->query($sql);
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }

  /** Lấy 1 booking + danh sách dịch vụ */
  public function getById($id) {
    $id = (int)$id;
    $sql = "
      SELECT b.*, c.full_name AS customer_name, r.room_number
      FROM `{$this->tblBooking}` b
      LEFT JOIN `{$this->tblCustomer}` c ON c.customer_id = b.customer_id
      LEFT JOIN `{$this->tblRoom}` r     ON r.room_id     = b.room_id
      WHERE b.booking_id = :id
      LIMIT 1
    ";
    $st = $this->conn->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    // dịch vụ đã dùng
    $sqlSvc = "
      SELECT su.service_id, su.quantity,
             s.service_name, s.unit_price
      FROM `{$this->tblServiceUsage}` su
      JOIN `{$this->tblService}` s ON s.service_id = su.service_id
      WHERE su.booking_id = :id
      ORDER BY su.service_id
    ";
    $st2 = $this->conn->prepare($sqlSvc);
    $st2->execute([':id' => $id]);
    $row['services'] = $st2->fetchAll(PDO::FETCH_ASSOC);

    return $row;
  }

  /** Tạo booking; $services = [{service_id, quantity}] */
  public function create($payload, $services = []) {
    $this->conn->beginTransaction();
    try {
      // Chuẩn hoá status booking
      $status = strtoupper($payload['status'] ?? 'CONFIRMED');

      $sql = "
        INSERT INTO `{$this->tblBooking}`
          (customer_id, room_id, checkin_date, checkout_date, status)
        VALUES (:customer_id, :room_id, :checkin_date, :checkout_date, :status)
      ";
      $st = $this->conn->prepare($sql);
      $st->execute([
        ':customer_id'  => $payload['customer_id'],
        ':room_id'      => $payload['room_id'],
        ':checkin_date' => $payload['checkin_date'],
        ':checkout_date'=> $payload['checkout_date'],
        ':status'       => $status,
      ]);
      $bookingId = (int)$this->conn->lastInsertId();

      // Ghi service_usage (nếu có)
      if (!empty($services)) {
        $ins = $this->conn->prepare("
          INSERT INTO `{$this->tblServiceUsage}` (booking_id, service_id, quantity, usage_date)
          VALUES (:booking_id, :service_id, :quantity, CURDATE())
        ");
        foreach ($services as $s) {
          if (empty($s['service_id']) || (int)$s['quantity'] <= 0) continue;
          $ins->execute([
            ':booking_id' => $bookingId,
            ':service_id' => $s['service_id'],
            ':quantity'   => (int)$s['quantity'],
          ]);
        }
      }

      // 🔴 Cập nhật trạng thái phòng theo booking mới
      if ($status === 'CONFIRMED') {
        // Nếu muốn “chỉ đặt trước” thì dùng 'BOOKED' thay 'IN_USE'
        $this->conn->prepare("UPDATE `{$this->tblRoom}` SET status='IN_USE' WHERE room_id=:rid")
                   ->execute([':rid' => $payload['room_id']]);
      } else {
        $this->conn->prepare("UPDATE `{$this->tblRoom}` SET status='AVAILABLE' WHERE room_id=:rid")
                   ->execute([':rid' => $payload['room_id']]);
      }

      $this->conn->commit();
      return $bookingId;
    } catch (PDOException $e) {
      $this->conn->rollBack();
      throw $e;
    }
  }

  /** Cập nhật booking; có thể thay thế toàn bộ services */
  public function update($id, $payload, $services = null) {
    $id = (int)$id;
    $this->conn->beginTransaction();
    try {
      // Lấy booking cũ để so sánh phòng & status
      $stOld = $this->conn->prepare("SELECT room_id, status FROM `{$this->tblBooking}` WHERE booking_id=:id LIMIT 1");
      $stOld->execute([':id' => $id]);
      $old = $stOld->fetch(PDO::FETCH_ASSOC);
      $oldRoomId = $old ? (int)$old['room_id'] : null;
      $oldStatus = $old ? strtoupper($old['status']) : null;

      // Chuẩn hoá status mới
      $newStatus = strtoupper($payload['status'] ?? 'CONFIRMED');
      $newRoomId = (int)$payload['room_id'];

      // Cập nhật booking
      $sql = "
        UPDATE `{$this->tblBooking}`
        SET customer_id = :customer_id,
            room_id     = :room_id,
            checkin_date = :checkin_date,
            checkout_date = :checkout_date,
            status = :status
        WHERE booking_id = :id
      ";
      $st = $this->conn->prepare($sql);
      $st->execute([
        ':customer_id'  => $payload['customer_id'],
        ':room_id'      => $newRoomId,
        ':checkin_date' => $payload['checkin_date'],
        ':checkout_date'=> $payload['checkout_date'],
        ':status'       => $newStatus,
        ':id'           => $id
      ]);

      // Thay thế services nếu truyền vào mảng
      if (is_array($services)) {
        $del = $this->conn->prepare("DELETE FROM `{$this->tblServiceUsage}` WHERE booking_id = :id");
        $del->execute([':id' => $id]);

        if (!empty($services)) {
          $ins = $this->conn->prepare("
            INSERT INTO `{$this->tblServiceUsage}` (booking_id, service_id, quantity, usage_date)
            VALUES (:booking_id, :service_id, :quantity, CURDATE())
          ");
          foreach ($services as $s) {
            if (empty($s['service_id']) || (int)$s['quantity'] <= 0) continue;
            $ins->execute([
              ':booking_id' => $id,
              ':service_id' => $s['service_id'],
              ':quantity'   => (int)$s['quantity'],
            ]);
          }
        }
      }

      // 🔴 Đồng bộ trạng thái phòng theo thay đổi
      if ($oldRoomId && $oldRoomId !== $newRoomId) {
        // Đổi phòng: trả phòng cũ về AVAILABLE
        $this->conn->prepare("UPDATE `{$this->tblRoom}` SET status='AVAILABLE' WHERE room_id=:id")
                   ->execute([':id' => $oldRoomId]);
      }

      // Với phòng mới:
      if ($newStatus === 'CONFIRMED') {
        // Nếu muốn “chỉ đặt trước” thì dùng 'BOOKED' thay 'IN_USE'
        $this->conn->prepare("UPDATE `{$this->tblRoom}` SET status='IN_USE' WHERE room_id=:id")
                   ->execute([':id' => $newRoomId]);
      } else {
        $this->conn->prepare("UPDATE `{$this->tblRoom}` SET status='AVAILABLE' WHERE room_id=:id")
                   ->execute([':id' => $newRoomId]);
      }

      // Trường hợp không đổi phòng nhưng đổi status:
      if ($oldRoomId === $newRoomId && $oldStatus !== $newStatus) {
        if ($newStatus === 'CONFIRMED') {
          $this->conn->prepare("UPDATE `{$this->tblRoom}` SET status='IN_USE' WHERE room_id=:id")
                     ->execute([':id' => $newRoomId]);
        } else {
          $this->conn->prepare("UPDATE `{$this->tblRoom}` SET status='AVAILABLE' WHERE room_id=:id")
                     ->execute([':id' => $newRoomId]);
        }
      }

      $this->conn->commit();
      return true;
    } catch (PDOException $e) {
      $this->conn->rollBack();
      throw $e;
    }
  }

  /** Xóa booking an toàn và trả phòng về AVAILABLE */
  public function delete($id) {
    $id = (int)$id;
    try {
      $this->conn->beginTransaction();

      // Lấy room_id trước khi xoá để trả phòng
      $stOld = $this->conn->prepare("SELECT room_id FROM `{$this->tblBooking}` WHERE booking_id=:id LIMIT 1");
      $stOld->execute([':id'=>$id]);
      $old = $stOld->fetch(PDO::FETCH_ASSOC);
      $oldRoomId = $old ? (int)$old['room_id'] : null;

      // Xoá sử dụng dịch vụ
      $st1 = $this->conn->prepare("DELETE FROM `{$this->tblServiceUsage}` WHERE booking_id = :id");
      $st1->execute([':id' => $id]);

      // Xoá invoice (nếu có)
      $st2 = $this->conn->prepare("DELETE FROM `invoice` WHERE booking_id = :id");
      $st2->execute([':id' => $id]);

      // Xoá booking
      $st3 = $this->conn->prepare("DELETE FROM `{$this->tblBooking}` WHERE booking_id = :id");
      $st3->execute([':id' => $id]);

      // 🔴 Trả phòng về AVAILABLE
      if ($oldRoomId) {
        $this->conn->prepare("UPDATE `{$this->tblRoom}` SET status='AVAILABLE' WHERE room_id=:id")
                   ->execute([':id' => $oldRoomId]);
      }

      $this->conn->commit();
      return true;
    } catch (PDOException $e) {
      $this->conn->rollBack();
      throw $e;
    }
  }
}
