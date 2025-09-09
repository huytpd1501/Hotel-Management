<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/Database.php';

class Staff {
    private PDO $conn;

    public function __construct(PDO $db){
        $this->conn = $db;
    }

    /** Lấy tất cả staff + account info */
    public function all(): array {
        $sql = "SELECT s.staff_id, s.full_name, s.role AS staff_role, s.phone_number, s.email,
                       s.account_id, a.username, a.role AS account_role
                FROM staff s
                LEFT JOIN account a ON a.account_id = s.account_id
                ORDER BY s.staff_id DESC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Tạo account + staff */
    public function create(array $p): int {
        $this->conn->beginTransaction();
        try {
            // 1) account
            $hash = password_hash($p['password'], PASSWORD_BCRYPT);  // dùng bcrypt
            $stmtA = $this->conn->prepare("INSERT INTO account(username, password_hash, role) VALUES(?,?,?)");
            $stmtA->execute([$p['username'], $hash, $p['account_role']]);
            $account_id = (int)$this->conn->lastInsertId();

            // 2) staff
            $stmtS = $this->conn->prepare("INSERT INTO staff(full_name, role, phone_number, email, account_id)
                                           VALUES(?,?,?,?,?)");
            $stmtS->execute([
                $p['full_name'],
                $p['staff_role'],
                $p['phone_number'] ?? null,
                $p['email'] ?? null,
                $account_id
            ]);

            $this->conn->commit();
            return $account_id;
        } catch(Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /** Cập nhật staff + account; password có thể bỏ trống */
    public function update(int $staff_id, array $p): bool {
        $this->conn->beginTransaction();
        try {
            // Lấy account_id
            $stmt = $this->conn->prepare("SELECT account_id FROM staff WHERE staff_id=?");
            $stmt->execute([$staff_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception("Staff không tồn tại");
            $account_id = (int)$row['account_id'];

            // Update account
            $sets = ["role = ?"];
            $vals = [$p['account_role']];
            if (!empty($p['username'])) { $sets[] = "username = ?"; $vals[] = $p['username']; }
            if (!empty($p['password'])) { 
                $sets[] = "password_hash = ?";
                $vals[] = password_hash($p['password'], PASSWORD_BCRYPT);
            }
            $vals[] = $account_id;
            $sqlA = "UPDATE account SET ".implode(',', $sets)." WHERE account_id=?";
            $this->conn->prepare($sqlA)->execute($vals);

            // Update staff
            $sqlS = "UPDATE staff SET full_name=?, role=?, phone_number=?, email=? WHERE staff_id=?";
            $this->conn->prepare($sqlS)->execute([
                $p['full_name'],
                $p['staff_role'],
                $p['phone_number'] ?? null,
                $p['email'] ?? null,
                $staff_id
            ]);

            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /** Xoá staff + account an toàn */
    public function delete(int $staff_id): bool {
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare("SELECT account_id FROM staff WHERE staff_id=?");
            $stmt->execute([$staff_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception("Staff không tồn tại");
            $account_id = (int)$row['account_id'];

            // Update invoice nếu có FK
            $this->conn->prepare("UPDATE invoice SET staff_id = NULL WHERE staff_id = ?")->execute([$staff_id]);

            // Delete staff và account
            $this->conn->prepare("DELETE FROM staff WHERE staff_id=?")->execute([$staff_id]);
            if ($account_id) {
                $this->conn->prepare("DELETE FROM account WHERE account_id=?")->execute([$account_id]);
            }

            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
}
