<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/Database.php';

class Staff {
  private $conn;
  private $table = "staff";
  public function __construct($db){ $this->conn = $db; }
}

  public function all() {
    $sql = "SELECT s.staff_id, s.full_name, s.role AS staff_role, s.phone_number, s.email,
                   s.account_id, a.username, a.role AS account_role
            FROM staff s
            LEFT JOIN account a ON a.account_id = s.account_id
            ORDER BY s.staff_id DESC";
    return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  /** Tạo account (Admin/Staff) + staff (vai trò công việc) */
  public function create($p) {
    $this->db->beginTransaction();
    try {
      // 1) account
      $qa = $this->db->prepare("INSERT INTO account(username, password_hash, role) VALUES(?,?,?)");
      $qa->execute([
        $p['username'],
        md5($p['password']),                 // DB đang lưu MD5
        $p['account_role']                   // 'Admin' | 'Staff'
      ]);
      $account_id = (int)$this->db->lastInsertId();

      // 2) staff
      $qs = $this->db->prepare("INSERT INTO staff(full_name, role, phone_number, email, account_id)
                                VALUES(?,?,?,?,?)");
      $qs->execute([
        $p['full_name'],
        $p['staff_role'],                    // ví dụ 'Lễ tân', 'Kế toán', ...
        $p['phone_number'] ?? null,
        $p['email'] ?? null,
        $account_id
      ]);

      $this->db->commit();
      return $account_id;
    } catch(Exception $e){
      $this->db->rollBack();
      throw $e;
    }
  }

  /** Cập nhật cả account + staff; password có thể bỏ trống để giữ nguyên */
  public function update($staff_id, $p) {
    $this->db->beginTransaction();
    try {
      // lấy account_id
      $q = $this->db->prepare("SELECT account_id FROM staff WHERE staff_id=?");
      $q->execute([$staff_id]);
      $row = $q->fetch(PDO::FETCH_ASSOC);
      if (!$row) throw new Exception("Staff không tồn tại");
      $account_id = (int)$row['account_id'];

      // update account
      $sets = ["role = ?"]; $vals = [$p['account_role']];
      if (!empty($p['username'])) { $sets[]="username = ?"; $vals[]=$p['username']; }
      if (!empty($p['password'])) { $sets[]="password_hash = ?"; $vals[]=md5($p['password']); }
      $vals[] = $account_id;
      $sqlA = "UPDATE account SET ".implode(',', $sets)." WHERE account_id=?";
      $this->db->prepare($sqlA)->execute($vals);

      // update staff
      $sqlS = "UPDATE staff SET full_name=?, role=?, phone_number=?, email=? WHERE staff_id=?";
      $this->db->prepare($sqlS)->execute([
        $p['full_name'], $p['staff_role'], $p['phone_number'] ?? null, $p['email'] ?? null, $staff_id
      ]);

      $this->db->commit();
      return true;
    } catch(Exception $e){
      $this->db->rollBack();
      throw $e;
    }
  }

  /** Xoá theo thứ tự an toàn: invoice -> staff -> account (tuỳ FK) */
  public function delete($staff_id) {
    $this->db->beginTransaction();
    try {
      // lấy account_id
      $q = $this->db->prepare("SELECT account_id FROM staff WHERE staff_id=?");
      $q->execute([$staff_id]);
      $row = $q->fetch(PDO::FETCH_ASSOC);
      if (!$row) throw new Exception("Staff không tồn tại");
      $account_id = (int)$row['account_id'];

      // vì invoice.staff_id FK (không cascade trong DB gốc), nên đặt staff_id = NULL trên invoice trước khi xoá staff
      $this->db->prepare("UPDATE invoice SET staff_id = NULL WHERE staff_id = ?")->execute([$staff_id]);

      // xoá staff -> rồi account
      $this->db->prepare("DELETE FROM staff WHERE staff_id=?")->execute([$staff_id]);
      $this->db->prepare("DELETE FROM account WHERE account_id=?")->execute([$account_id]);

      $this->db->commit();
      return true;
    } catch(Exception $e){
      $this->db->rollBack();
      throw $e;
    }
  }
}
