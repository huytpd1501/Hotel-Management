<?php
// StaffController.php
header("Access-Control-Allow-Origin: http://localhost:8000");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/Database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

function body_json() {
  $raw = file_get_contents("php://input");
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}

switch ($method) {
  case 'GET':
    // list all staff (basic info + username)
    $sql = "SELECT s.staff_id, s.full_name, s.role, s.phone_number, s.email, a.username, a.role AS account_role, a.account_id
            FROM staff s 
            LEFT JOIN account a ON s.account_id = a.account_id
            ORDER BY s.staff_id DESC";
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
    break;

  case 'POST':
    // create staff + account
    $d = body_json();
    $full_name = trim($d['full_name'] ?? '');
    $username  = trim($d['username'] ?? '');
    $password  = $d['password'] ?? '';
    $phone     = trim($d['phone_number'] ?? '');
    $email     = trim($d['email'] ?? '');
    $role      = 'Staff'; // only Staff via API

    if ($full_name==='' || $username==='' || $password==='') {
      echo json_encode(['success'=>false,'message'=>'Thiếu thông tin']); break;
    }
    // unique username
    $st = $db->prepare("SELECT 1 FROM account WHERE username=?");
    $st->execute([$username]);
    if ($st->fetch()) { echo json_encode(['success'=>false,'message'=>'Username đã tồn tại']); break; }

    $db->beginTransaction();
    try {
      $hash = password_hash($password, PASSWORD_BCRYPT);
      $insA = $db->prepare("INSERT INTO account (username, password_hash, role) VALUES (?, ?, ?)");
      $insA->execute([$username, $hash, $role]);
      $account_id = (int)$db->lastInsertId();

      $insS = $db->prepare("INSERT INTO staff (full_name, role, phone_number, email, account_id) VALUES (?, ?, ?, ?, ?)");
      $insS->execute([$full_name, $role, $phone, $email, $account_id]);

      $db->commit();
      echo json_encode(['success'=>true, 'staff_id'=>(int)$db->lastInsertId(), 'account_id'=>$account_id]);
    } catch(Exception $e) {
      $db->rollBack();
      echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    break;

  case 'PUT':
    // update staff basic info
    parse_str($_SERVER["QUERY_STRING"] ?? "", $q);
    $id = (int)($q['id'] ?? 0);
    if ($id<=0) { echo json_encode(['success'=>false,'message'=>'Thiếu id']); break; }

    $d = body_json();
    $full_name = trim($d['full_name'] ?? '');
    $phone     = trim($d['phone_number'] ?? '');
    $email     = trim($d['email'] ?? '');

    $sql = "UPDATE staff SET full_name=?, phone_number=?, email=? WHERE staff_id=?";
    $ok = $db->prepare($sql)->execute([$full_name, $phone, $email, $id]);
    echo json_encode(['success'=>$ok]);
    break;

  case 'DELETE':
    // delete staff + account
    parse_str($_SERVER["QUERY_STRING"] ?? "", $q);
    $id = (int)($q['id'] ?? 0);
    if ($id<=0) { echo json_encode(['success'=>false,'message'=>'Thiếu id']); break; }

    // get account_id
    $st = $db->prepare("SELECT account_id FROM staff WHERE staff_id=?");
    $st->execute([$id]);
    $acc = $st->fetch(PDO::FETCH_ASSOC);
    if (!$acc) { echo json_encode(['success'=>false,'message'=>'Không tìm thấy staff']); break; }

    $db->beginTransaction();
    try {
      $db->prepare("DELETE FROM staff WHERE staff_id=?")->execute([$id]);
      if (!empty($acc['account_id'])) {
        $db->prepare("DELETE FROM account WHERE account_id=?")->execute([$acc['account_id']]);
      }
      $db->commit();
      echo json_encode(['success'=>true]);
    } catch(Exception $e) {
      $db->rollBack();
      echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    break;

  default:
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
}
